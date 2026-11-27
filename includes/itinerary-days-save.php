<?php
/**
 * Persists itinerary days while retaining shared image files.
 *
 * Shared helper (unit-testable without an admin request lifecycle). Relies on
 * the db() database helper.
 *
 * Safety model:
 *  - Rows are reconciled by their database id: existing rows are updated, new
 *    rows (id 0) are inserted, and only rows that existed before AND are absent
 *    from the submitted set are deleted. A malformed/partial request can never
 *    wipe out child rows it did not mention.
 *  - The helper participates in an already-open outer transaction instead of
 *    starting a nested one, so the whole tour save can be all-or-nothing.
 *  - Image files are retained on replacement, record deletion and rollback,
 *    because photos can be shared across tours, overviews and the gallery.
 *
 * @param int   $tourId            The tour id the days belong to.
 * @param array $submittedDays     Each item: day_id, day_number, title,
 *                                 description, drive_time, meals, accommodation,
 *                                 location_name, lat, lng, alt, final_image.
 *                                 final_image = the resolved
 *                                 image path (uploaded new, kept existing, or
 *                                 null when removed).
 * @param array $uploadedNewImages Legacy argument retained for caller compatibility.
 */
function saveItineraryDays($tourId, $submittedDays, $uploadedNewImages) {
    $db = db();

    // Existing row IDs allow updates and removal without touching shared media.
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
            $locName   = mb_substr(trim($d['location_name'] ?? ''), 0, 255);
            $lat       = isset($d['lat']) && is_numeric($d['lat']) ? (float)$d['lat'] : null;
            $lng       = isset($d['lng']) && is_numeric($d['lng']) ? (float)$d['lng'] : null;
            $image     = trim($d['final_image'] ?? '') ?: null;
            $alt       = mb_substr(trim($d['alt'] ?? ''), 0, 255);

            if ($id > 0 && isset($oldById[$id])) {
                $db->query(
                    "UPDATE itinerary_days SET day_number=?, title=?, description=?, drive_time=?, meals=?, accommodation=?, location_name=?, lat=?, lng=?, image_path=?, image_alt=?, sort_order=? WHERE id=? AND tour_id=?",
                    [$dayNumber, $title, $description, $drive, $meals, $accom, $locName, $lat, $lng, $image, $alt, $sort, $id, $tourId]
                );
            } else {
                $db->insert(
                    "INSERT INTO itinerary_days (tour_id, day_number, title, description, drive_time, meals, accommodation, location_name, lat, lng, image_path, image_alt, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$tourId, $dayNumber, $title, $description, $drive, $meals, $accom, $locName, $lat, $lng, $image, $alt, $sort]
                );
            }
            $sort++;
        }

        // Delete only rows that existed before but were not re-submitted.
        foreach ($oldById as $id => $oldImage) {
            if (isset($submittedIds[$id])) continue;
            $db->query("DELETE FROM itinerary_days WHERE id=? AND tour_id=?", [$id, $tourId]);
        }

        if (!$outerTxn) $db->commit();
    } catch (\Throwable $e) {
        if (!$outerTxn && $db->getConnection()->inTransaction()) {
            $db->rollback();
        }
        // Preserve media even after rollback: selected files can be shared.
        error_log("saveItineraryDays error: " . $e->getMessage());
        throw $e;
    }

    // Photo files are retained: another tour or gallery entry may still reference them.
}
