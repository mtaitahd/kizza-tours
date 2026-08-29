<?php
/**
 * Persists the structured itinerary days for a tour and manages image cleanup.
 *
 * Shared helper (unit-testable without an admin request lifecycle). Relies on
 * db() and deleteFile() from includes/config.php.
 *
 * Safety model:
 *  - Rows are reconciled by their database id: existing rows are updated, new
 *    rows (id 0) are inserted, and only rows that existed before AND are absent
 *    from the submitted set are deleted. A malformed/partial request can never
 *    wipe out child rows it did not mention.
 *  - The helper participates in an already-open outer transaction instead of
 *    starting a nested one, so the whole tour save can be all-or-nothing.
 *  - Old image files are only removed AFTER a successful commit, and never if
 *    the same path is still referenced by another kept day.
 *
 * @param int   $tourId            The tour id the days belong to.
 * @param array $submittedDays     Each item: day_id, day_number, title,
 *                                 description, drive_time, meals, accommodation,
 *                                 alt, final_image. final_image = the resolved
 *                                 image path (uploaded new, kept existing, or
 *                                 null when removed).
 * @param array $uploadedNewImages Paths of files just uploaded during this
 *                                 request; removed again if the DB save fails so
 *                                 we never leave orphaned uploads.
 */
function saveItineraryDays($tourId, $submittedDays, $uploadedNewImages) {
    $db = db();

    // Final image set so we can safely garbage-collect old files afterwards.
    $finalImages = [];
    foreach ($submittedDays as $d) {
        if (!empty($d['final_image'])) $finalImages[] = $d['final_image'];
    }

    // Current rows -> memory of what exists, what is being replaced, and what
    // is removed entirely.
    $oldRows = $db->fetchAll("SELECT id, image_path FROM itinerary_days WHERE tour_id = ?", [$tourId]);
    $oldById = [];
    foreach ($oldRows as $row) {
        $oldById[intval($row['id'])] = $row['image_path'];
    }

    $submittedIds = [];
    foreach ($submittedDays as $d) {
        $id = intval($d['day_id'] ?? 0);
        if ($id > 0) $submittedIds[$id] = true;
    }

    $outerTxn = $db->getConnection()->inTransaction();

    try {
        if (!$outerTxn) $db->beginTransaction();
        $sort = 0;
        foreach ($submittedDays as $d) {
            $id       = intval($d['day_id'] ?? 0);
            $dayNumber = max(1, intval($d['day_number'] ?? ($sort + 1)));
            $title     = mb_substr(trim($d['title'] ?? ''), 0, 255);
            $description = trim($d['description'] ?? '');
            $drive     = mb_substr(trim($d['drive_time'] ?? ''), 0, 255);
            $meals     = mb_substr(trim($d['meals'] ?? ''), 0, 255);
            $accom     = mb_substr(trim($d['accommodation'] ?? ''), 0, 255);
            $image     = trim($d['final_image'] ?? '') ?: null;
            $alt       = mb_substr(trim($d['alt'] ?? ''), 0, 255);

            if ($id > 0 && isset($oldById[$id])) {
                $db->query(
                    "UPDATE itinerary_days SET day_number=?, title=?, description=?, drive_time=?, meals=?, accommodation=?, image_path=?, image_alt=?, sort_order=? WHERE id=? AND tour_id=?",
                    [$dayNumber, $title, $description, $drive, $meals, $accom, $image, $alt, $sort, $id, $tourId]
                );
            } else {
                $db->insert(
                    "INSERT INTO itinerary_days (tour_id, day_number, title, description, drive_time, meals, accommodation, image_path, image_alt, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$tourId, $dayNumber, $title, $description, $drive, $meals, $accom, $image, $alt, $sort]
                );
            }
            $sort++;
        }

        // Delete only rows that existed before but were not re-submitted.
        $deletedImages = [];
        foreach ($oldById as $id => $oldImage) {
            if (isset($submittedIds[$id])) continue;
            $db->query("DELETE FROM itinerary_days WHERE id=? AND tour_id=?", [$id, $tourId]);
            $deletedImages[$id] = $oldImage;
        }

        if (!$outerTxn) $db->commit();
    } catch (\Throwable $e) {
        if (!$outerTxn && $db->getConnection()->inTransaction()) {
            $db->rollback();
        }
        // Remove newly uploaded files so we don't leave orphans.
        foreach ($uploadedNewImages as $p) {
            deleteFile($p);
        }
        error_log("saveItineraryDays error: " . $e->getMessage());
        throw $e;
    }

    // Only after a successful commit do we delete now-orphaned old images:
    // images removed with their row, or replaced on a kept row. A path still
    // referenced by a kept day is never touched.
    $toDelete = [];
    foreach ($oldById as $id => $oldImage) {
        if (empty($oldImage) || in_array($oldImage, $finalImages, true)) continue;
        if (isset($submittedIds[$id]) || isset($deletedImages[$id])) {
            $toDelete[] = $oldImage;
        }
    }
    foreach ($deletedImages as $oldImage) {
        if (!empty($oldImage) && !in_array($oldImage, $finalImages, true)) {
            $toDelete[] = $oldImage;
        }
    }
    foreach (array_unique($toDelete) as $p) {
        deleteFile($p);
    }
}