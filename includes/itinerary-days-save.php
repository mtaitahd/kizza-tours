<?php
/**
 * Persists the structured itinerary days for a tour and manages image cleanup.
 *
 * This is a shared helper so it can be unit-tested independently of the admin
 * request lifecycle. Relies on db() and deleteFile() from includes/config.php.
 *
 * @param int   $tourId            The tour id the days belong to.
 * @param array $submittedDays     Each item: day_number, title, description,
 *                                 alt, final_image. final_image = the resolved
 *                                 image path (uploaded new, kept existing, or
 *                                 null when removed). Prepared by the admin form.
 * @param array $uploadedNewImages Paths of files just uploaded during this
 *                                 request; removed again if the DB save fails so
 *                                 we never leave orphaned uploads.
 */
function saveItineraryDays($tourId, $submittedDays, $uploadedNewImages) {
    $db = db();
    // Compute final image set so we can safely garbage-collect old files.
    $finalImages = [];
    foreach ($submittedDays as $d) {
        if (!empty($d['final_image'])) $finalImages[] = $d['final_image'];
    }

    // What old images no longer exist in the new set -> candidates for cleanup.
    $imagesToDelete = [];
    foreach ($db->fetchAll("SELECT id, image_path FROM itinerary_days WHERE tour_id = ?", [$tourId]) as $old) {
        if (!empty($old['image_path']) && !in_array($old['image_path'], $finalImages, true)) {
            $imagesToDelete[] = $old['image_path'];
        }
    }

    try {
        $db->beginTransaction();
        $db->query("DELETE FROM itinerary_days WHERE tour_id = ?", [$tourId]);
        $sort = 0;
        foreach ($submittedDays as $d) {
            $db->insert(
                "INSERT INTO itinerary_days (tour_id, day_number, title, description, image_path, image_alt, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $tourId,
                    intval($d['day_number']),
                    mb_substr(trim($d['title'] ?? ''), 0, 255),
                    trim($d['description'] ?? ''),
                    trim($d['final_image'] ?? '') ?: null,
                    mb_substr(trim($d['alt'] ?? ''), 0, 255),
                    $sort++,
                ]
            );
        }
        $db->commit();
    } catch (\Throwable $e) {
        if ($db->getConnection()->inTransaction()) {
            $db->rollback();
        }
        // Remove newly uploaded files so we don't leave orphans.
        foreach ($uploadedNewImages as $p) {
            deleteFile($p);
        }
        error_log("saveItineraryDays error: " . $e->getMessage());
        throw $e;
    }

    // Only after a successful commit do we delete now-orphaned old images.
    foreach (array_unique($imagesToDelete) as $p) {
        if (!empty($p) && !in_array($p, $finalImages, true)) {
            deleteFile($p);
        }
    }
}
