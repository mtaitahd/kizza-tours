<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: ./');
    exit;
}

$db = db();

// Ensure profile image is in session
if (empty($_SESSION['admin_image']) && isset($_SESSION['admin_id'])) {
    $row = $db->fetchOne("SELECT profile_image FROM admin_users WHERE id = ?", [$_SESSION['admin_id']]);
    $_SESSION['admin_image'] = $row['profile_image'] ?? null;
}

function ensureToursTable() {
    try {
        $db = db();
        try { $db->query("ALTER TABLE tour_packages ADD COLUMN hero_image VARCHAR(255) DEFAULT NULL AFTER image"); } catch (\Throwable $e) {}
        try { $db->query("ALTER TABLE tour_packages ADD COLUMN meta_title VARCHAR(255) DEFAULT NULL AFTER status"); } catch (\Throwable $e) {}
        try { $db->query("ALTER TABLE tour_packages ADD COLUMN meta_description TEXT DEFAULT NULL AFTER meta_title"); } catch (\Throwable $e) {}
        try { $db->query("ALTER TABLE tour_packages ADD COLUMN meta_keywords VARCHAR(255) DEFAULT NULL AFTER meta_description"); } catch (\Throwable $e) {}
        try { $db->query("ALTER TABLE tour_packages ADD COLUMN no_robots TINYINT(1) DEFAULT 0 AFTER meta_keywords"); } catch (\Throwable $e) {}
        try { $db->query("ALTER TABLE tour_packages ADD COLUMN overview_image_1 VARCHAR(255) DEFAULT NULL AFTER hero_image"); } catch (\Throwable $e) {}
        try { $db->query("ALTER TABLE tour_packages ADD COLUMN overview_image_2 VARCHAR(255) DEFAULT NULL AFTER overview_image_1"); } catch (\Throwable $e) {}
        try { $db->query("ALTER TABLE tour_packages ADD COLUMN overview_image_3 VARCHAR(255) DEFAULT NULL AFTER overview_image_2"); } catch (\Throwable $e) {}
        return true;
    } catch (\Throwable $e) { return false; }
}

function ensureItineraryDayColumns() {
    try {
        $db = db();
        $existing = [];
        foreach ($db->fetchAll("SHOW COLUMNS FROM itinerary_days") as $row) {
            $existing[$row['Field']] = true;
        }
        $additions = [
            'drive_time'     => "ALTER TABLE itinerary_days ADD COLUMN drive_time VARCHAR(255) DEFAULT NULL AFTER description",
            'meals'          => "ALTER TABLE itinerary_days ADD COLUMN meals VARCHAR(255) DEFAULT NULL AFTER drive_time",
            'accommodation'  => "ALTER TABLE itinerary_days ADD COLUMN accommodation VARCHAR(255) DEFAULT NULL AFTER meals",
            'location_name'  => "ALTER TABLE itinerary_days ADD COLUMN location_name VARCHAR(255) DEFAULT NULL AFTER accommodation",
            'lat'            => "ALTER TABLE itinerary_days ADD COLUMN lat DECIMAL(10,7) DEFAULT NULL AFTER location_name",
            'lng'            => "ALTER TABLE itinerary_days ADD COLUMN lng DECIMAL(10,7) DEFAULT NULL AFTER lat",
        ];
        foreach ($additions as $field => $sql) {
            if (!isset($existing[$field])) $db->query($sql);
        }
        return true;
    } catch (\Throwable $e) { return false; }
}

function ensureItineraryDaysTable() {
    try {
        $db = db();
        $tables = $db->fetchAll("SHOW TABLES");
        foreach ($tables as $row) {
            if (in_array('itinerary_days', array_values($row))) return ensureItineraryDayColumns();
        }
        $db->query("CREATE TABLE IF NOT EXISTS itinerary_days (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tour_id INT NOT NULL,
            day_number INT NOT NULL DEFAULT 1,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            drive_time VARCHAR(255) DEFAULT NULL,
            meals VARCHAR(255) DEFAULT NULL,
            accommodation VARCHAR(255) DEFAULT NULL,
            location_name VARCHAR(255) DEFAULT NULL,
            lat DECIMAL(10,7) DEFAULT NULL,
            lng DECIMAL(10,7) DEFAULT NULL,
            image_path VARCHAR(255) DEFAULT NULL,
            image_alt VARCHAR(255) DEFAULT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_itinerary_days_tour (tour_id, sort_order),
            CONSTRAINT fk_itinerary_days_tour FOREIGN KEY (tour_id) REFERENCES tour_packages(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return true;
    } catch (\Throwable $e) { return false; }
}
ensureToursTable();
ensureItineraryDaysTable();
ensureFaqTourColumn();

require_once __DIR__ . '/../includes/itinerary-days-save.php';
require_once __DIR__ . '/../includes/tour-faqs-save.php';

function ensureFaqTourColumn() {
    try {
        $db = db();
        $cols = $db->fetchAll("SHOW COLUMNS FROM faq");
        foreach ($cols as $c) {
            if (strtolower($c['Field']) === 'tour_id') return true;
        }
        $db->query("ALTER TABLE faq ADD COLUMN tour_id INT DEFAULT NULL AFTER id");
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    
    if (in_array($action, ['add', 'edit'])) {
        $tourId = intval($_POST['tour_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $rawSlug = trim($_POST['slug'] ?? '');
        $slug = !empty($rawSlug) ? strtolower(preg_replace('/[^a-z0-9]+/i', '-', $rawSlug)) : slugify($title);
        $slug = trim($slug, '-');
        $duration = trim($_POST['duration'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $country = trim($_POST['country'] ?? '');
        $destination_id = intval($_POST['destination_id'] ?? 0) ?: null;
        $rating = floatval($_POST['rating'] ?? 5);
        $max_guests = intval($_POST['max_guests'] ?? 10);
        $description = trim($_POST['description'] ?? '');
        // Itemized lists are stored as JSON arrays; legacy comma-separated values
        // (and newer one-per-line submissions) are normalized on save.
        $highlights = tourListStorage($_POST['highlights'] ?? '');
        $includes = tourListStorage($_POST['includes'] ?? '');
        $excludes = tourListStorage($_POST['excludes'] ?? '');
        $gallery = trim($_POST['gallery'] ?? '');
        $status = trim($_POST['status'] ?? 'active');
        $meta_title = trim($_POST['meta_title'] ?? '');
        $meta_description = trim($_POST['meta_description'] ?? '');
        $meta_keywords = trim($_POST['meta_keywords'] ?? '');
        $no_robots = intval($_POST['no_robots'] ?? 0);

        $image = '';
        $hasNewImage = false;
        $galleryImage = trim($_POST['image_gallery'] ?? '');
        if ($galleryImage !== '' && strpos($galleryImage, 'uploads/') === 0) {
            $image = $galleryImage;
            $hasNewImage = true;
        }

        $heroImage = '';
        $hasNewHero = false;
        $galleryHero = trim($_POST['hero_image_gallery'] ?? '');
        if ($galleryHero !== '' && strpos($galleryHero, 'uploads/') === 0) {
            $heroImage = $galleryHero;
            $hasNewHero = true;
        }
        
        // ---- Tour Overview images (the 3 collage photos) ----
        // Each slot keeps its previously saved file unless a gallery pick replaces
        // it or it is explicitly removed. The three values are written together
        // whenever any of them changed, so untouched slots are preserved.
        $overviewImage1 = trim($_POST['overview_image_1_current'] ?? '');
        $overviewImage2 = trim($_POST['overview_image_2_current'] ?? '');
        $overviewImage3 = trim($_POST['overview_image_3_current'] ?? '');
        $overviewImagesChanged = false;
        foreach ([1 => 'overview_image_1', 2 => 'overview_image_2', 3 => 'overview_image_3'] as $ov => $field) {
            $current = trim($_POST[$field . '_current'] ?? '');
            $remove = !empty($_POST[$field . '_remove']);
            $final = $remove ? '' : $current;
            if ($remove) $overviewImagesChanged = true;
            ${'overviewImage' . $ov} = $final;
        }
        
        // ---- Structured itinerary days ----
        $dayIds = array_map('intval', $_POST['day_id'] ?? []);
        $dayNumbers = $_POST['day_number'] ?? [];
        $dayTitles = $_POST['day_title'] ?? [];
        $dayDescs = $_POST['day_description'] ?? [];
        $dayDrives = $_POST['day_drive_time'] ?? [];
        $dayMeals = $_POST['day_meals'] ?? [];
        $dayAccoms = $_POST['day_accommodation'] ?? [];
        $dayLocNames = $_POST['day_location_name'] ?? [];
        $dayLats = $_POST['day_lat'] ?? [];
        $dayLngs = $_POST['day_lng'] ?? [];
        $dayAlts = $_POST['day_alt'] ?? [];
        $dayExistingImgs = $_POST['day_existing_image'] ?? [];
        $dayRemoveImgs = $_POST['day_remove_image'] ?? [];

        $submittedDays = [];
        for ($i = 0; $i < count($dayTitles); $i++) {
            $dayLat = trim($dayLats[$i] ?? '');
            $dayLng = trim($dayLngs[$i] ?? '');
            $submittedDays[] = [
                'day_id'        => intval($dayIds[$i] ?? 0),
                'day_number'    => isset($dayNumbers[$i]) ? intval($dayNumbers[$i]) : ($i + 1),
                'title'         => trim($dayTitles[$i] ?? ''),
                'description'   => trim($dayDescs[$i] ?? ''),
                'drive_time'    => trim($dayDrives[$i] ?? ''),
                'meals'         => trim($dayMeals[$i] ?? ''),
                'accommodation' => trim($dayAccoms[$i] ?? ''),
                'location_name' => trim($dayLocNames[$i] ?? ''),
                'lat'           => ($dayLat !== '' && is_numeric($dayLat)) ? (float)$dayLat : null,
                'lng'           => ($dayLng !== '' && is_numeric($dayLng)) ? (float)$dayLng : null,
                'alt'           => trim($dayAlts[$i] ?? ''),
                'existing_image'=> trim($dayExistingImgs[$i] ?? ''),
                'remove_image'  => !empty($dayRemoveImgs[$i]),
            ];
        }

        // Resolve each day's final image from its gallery pick (currently only
        // gallery-picked paths are supported; an uploaded file is never sent).
        $uploadedNewImages = [];
        foreach ($submittedDays as &$d) {
            $d['final_image'] = $d['remove_image'] ? null : $d['existing_image'];
        }
        unset($d);

        // ---- Per-tour FAQs ----
        $faqIds = $_POST['faq_id'] ?? [];
        $faqQs = $_POST['faq_question'] ?? [];
        $faqAs = $_POST['faq_answer'] ?? [];
        $faqCs = $_POST['faq_category'] ?? [];
        $faqSs = $_POST['faq_status'] ?? [];
        $submittedFaqs = [];
        for ($i = 0; $i < count((array)$faqQs); $i++) {
            $submittedFaqs[] = [
                'id'       => intval($faqIds[$i] ?? 0),
                'question' => trim($faqQs[$i] ?? ''),
                'answer'   => trim($faqAs[$i] ?? ''),
                'category' => trim($faqCs[$i] ?? ''),
                'status'   => trim($faqSs[$i] ?? 'active'),
            ];
        }

        if ($action === 'add') {
            $itinerary = '';
            $newTourId = 0;
            try {
                if (!$db->getConnection()->inTransaction()) $db->beginTransaction();
                $newTourId = $db->insert(
                    "INSERT INTO tour_packages (title, slug, duration, price, country, destination_id, rating, max_guests, description, highlights, includes, excludes, gallery, itinerary, image, hero_image, overview_image_1, overview_image_2, overview_image_3, status, meta_title, meta_description, meta_keywords, no_robots) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$title, $slug, $duration, $price, $country, $destination_id, $rating, $max_guests, $description, $highlights, $includes, $excludes, $gallery, $itinerary, $image, $heroImage, $overviewImage1, $overviewImage2, $overviewImage3, $status, $meta_title, $meta_description, $meta_keywords, $no_robots]
                );
                saveItineraryDays($newTourId, $submittedDays, $uploadedNewImages);
                saveTourFaqs($newTourId, $submittedFaqs);
                if ($db->getConnection()->inTransaction()) $db->commit();
            } catch (\Throwable $e) {
                if ($db->getConnection()->inTransaction()) $db->rollback();
                // Roll back the tour we just created and clean up new uploads.
                foreach ($uploadedNewImages as $p) deleteFile($p);
                if ($hasNewImage && !empty($image)) deleteFile($image);
                if ($hasNewHero && !empty($heroImage)) deleteFile($heroImage);
                try { $db->query("DELETE FROM tour_packages WHERE id = ?", [$newTourId]); } catch (\Throwable $ignore) {}
                error_log("Tour add error: " . $e->getMessage());
                $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Could not save the tour with its itinerary days and FAQs.'];
                header('Location: tours');
                exit;
            }
            try { seoGenerateSitemap(); } catch (\Throwable $e) { error_log("Sitemap gen error: " . $e->getMessage()); }
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Tour added successfully'];
            $_SESSION['drafts_cleared'] = true;
        } else {
            // Preserve existing legacy itinerary text (never overwrite old tours).
            $existingRow = $db->fetchOne("SELECT itinerary, image, hero_image, overview_image_1, overview_image_2, overview_image_3 FROM tour_packages WHERE id = ?", [$tourId]);
            $itinerary = $existingRow['itinerary'] ?? '';

            $sql = "UPDATE tour_packages SET title=?, slug=?, duration=?, price=?, country=?, destination_id=?, rating=?, max_guests=?, description=?, highlights=?, includes=?, excludes=?, gallery=?, itinerary=?, status=?, meta_title=?, meta_description=?, meta_keywords=?, no_robots=?";
            $params = [$title, $slug, $duration, $price, $country, $destination_id, $rating, $max_guests, $description, $highlights, $includes, $excludes, $gallery, $itinerary, $status, $meta_title, $meta_description, $meta_keywords, $no_robots];
            if ($hasNewImage) { $sql .= ", image=?"; $params[] = $image; }
            if ($hasNewHero) { $sql .= ", hero_image=?"; $params[] = $heroImage; }
            // Overview slots may have been changed simply by picking a different
            // gallery image in the dropdown, so compare the submitted paths with
            // what's already stored before deciding whether to write them.
            $ovDiffers = false;
            foreach ([1 => $overviewImage1, 2 => $overviewImage2, 3 => $overviewImage3] as $ovNum => $ovVal) {
                if ((string)($existingRow['overview_image_' . $ovNum] ?? '') !== (string)$ovVal) {
                    $ovDiffers = true;
                    break;
                }
            }
            if ($overviewImagesChanged || $ovDiffers) {
                $sql .= ", overview_image_1=?, overview_image_2=?, overview_image_3=?";
                $params[] = $overviewImage1;
                $params[] = $overviewImage2;
                $params[] = $overviewImage3;
            }
            $sql .= " WHERE id=?";
            $params[] = $tourId;

            // A gallery picker always submits its current value, so only treat
            // the main/hero image as "new" when the submitted path actually
            // differs from what's stored. Otherwise a plain re-save would
            // rewrite (and then orphan-delete) the still-referenced file.
            if ($hasNewImage && $image === ($existingRow['image'] ?? '')) {
                $hasNewImage = false;
                $image = $existingRow['image'] ?? '';
            }
            if ($hasNewHero && $heroImage === ($existingRow['hero_image'] ?? '')) {
                $hasNewHero = false;
                $heroImage = $existingRow['hero_image'] ?? '';
            }

            // The tour row, its itinerary days and its FAQs are saved atomically.
            try {
                if (!$db->getConnection()->inTransaction()) $db->beginTransaction();
                $db->query($sql, $params);
                saveItineraryDays($tourId, $submittedDays, $uploadedNewImages);
                saveTourFaqs($tourId, $submittedFaqs);
                if ($db->getConnection()->inTransaction()) $db->commit();
            } catch (\Throwable $e) {
                if ($db->getConnection()->inTransaction()) $db->rollback();
                foreach ($uploadedNewImages as $p) deleteFile($p);
                if ($hasNewImage && !empty($image)) deleteFile($image);
                if ($hasNewHero && !empty($heroImage)) deleteFile($heroImage);
                error_log("Tour update save error: " . $e->getMessage());
                $_SESSION['flash'] = ['type' => 'danger', 'message' => 'The tour could not be fully saved. No changes were applied.'];
                header('Location: tours');
                exit;
            }

            // Cleanup tour-level images only after a successful commit.
            if ($hasNewImage && !empty($existingRow['image'])) deleteFile($existingRow['image']);
            if ($hasNewHero && !empty($existingRow['hero_image'])) deleteFile($existingRow['hero_image']);
            if ($overviewImagesChanged) {
                foreach ([1, 2, 3] as $ov) {
                    $old = trim($existingRow['overview_image_' . $ov] ?? '');
                    $newVal = ${'overviewImage' . $ov};
                    if ($old !== '' && $old !== $newVal) deleteFile($old);
                }
            }

            try { seoGenerateSitemap(); } catch (\Throwable $e) { error_log("Sitemap gen error: " . $e->getMessage()); }
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Tour updated successfully'];
            $_SESSION['drafts_cleared'] = true;
        }
    } elseif ($action === 'delete') {
        $tourId = intval($_POST['tour_id'] ?? 0);
        $tour = $db->fetchOne("SELECT image, hero_image FROM tour_packages WHERE id = ?", [$tourId]);
        if ($tour && $tour['image']) deleteFile($tour['image']);
        if ($tour && $tour['hero_image']) deleteFile($tour['hero_image']);
        foreach ($db->fetchAll("SELECT image_path FROM itinerary_days WHERE tour_id = ?", [$tourId]) as $day) {
            if (!empty($day['image_path'])) deleteFile($day['image_path']);
        }
        try { $db->query("DELETE FROM faq WHERE tour_id = ?", [$tourId]); } catch (\Throwable $ignore) {}
        try { $db->query("DELETE FROM itinerary_days WHERE tour_id = ?", [$tourId]); } catch (\Throwable $ignore) {}
        $db->query("DELETE FROM tour_packages WHERE id = ?", [$tourId]);
        try { seoGenerateSitemap(); } catch (\Throwable $e) { error_log("Sitemap gen error: " . $e->getMessage()); }
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Tour deleted successfully'];
    }
    
    header('Location: tours');
    exit;
}

$tours = $db->fetchAll("SELECT p.*, d.name as dest_name FROM tour_packages p LEFT JOIN destinations d ON p.destination_id = d.id ORDER BY p.created_at DESC");
$destinations = $db->fetchAll("SELECT id, name, country FROM destinations WHERE status = 'active' ORDER BY name");

// Load all itinerary days once, grouped by tour, for the edit modal.
$allDays = $db->fetchAll("SELECT id, tour_id, day_number, title, description, image_path, image_alt, location_name, lat, lng FROM itinerary_days ORDER BY tour_id ASC, sort_order ASC, id ASC");
$daysByTour = [];
foreach ($allDays as $day) {
    $daysByTour[$day['tour_id']][] = $day;
}

// Load per-tour FAQs once, grouped by tour, for the edit modal.
$allFaqs = $db->fetchAll("SELECT id, tour_id, question, answer, category, sort_order, status FROM faq WHERE tour_id IS NOT NULL AND tour_id != 0 ORDER BY sort_order ASC, id ASC");
$faqsByTour = [];
foreach ($allFaqs as $faq) {
    $faqsByTour[$faq['tour_id']][] = $faq;
}

// Active gallery images grouped by category, used by the image pickers in the
// tour form. Picking an already-web-optimised gallery image avoids the slow
// per-upload conversion that used to happen on every tour save.
$galleryImages = $db->fetchAll("SELECT title, category, image FROM gallery WHERE status = 'active' AND image IS NOT NULL AND image != '' ORDER BY category ASC, title ASC");
$galleryCategoryRows = $db->fetchAll("SELECT name, slug FROM gallery_categories ORDER BY sort_order ASC, name ASC");
$galleryCatNames = [];
foreach ($galleryCategoryRows as $cat) {
    $galleryCatNames[$cat['slug']] = $cat['name'];
}
$galleryTree = [];
foreach ($galleryImages as $g) {
    $catSlug = !empty($g['category']) ? $g['category'] : 'general';
    if (!isset($galleryTree[$catSlug])) {
        $galleryTree[$catSlug] = [
            'name'  => $galleryCatNames[$catSlug] ?? ucwords(str_replace(['_', '-'], ' ', $catSlug)),
            'items' => [],
        ];
    }
    $galleryTree[$catSlug]['items'][] = [
        'path'  => $g['image'],
        'title' => !empty($g['title']) ? $g['title'] : basename($g['image']),
    ];
}
// Mix in any images that are already saved on tours but not in the gallery yet
// (e.g. legacy uploads/tours images) so the picker never shows a broken choice.
$referencedPaths = ['uploads/tours/' => true];
foreach ($tours as $tour) {
    foreach (['image', 'hero_image', 'overview_image_1', 'overview_image_2', 'overview_image_3'] as $imgField) {
        if (!empty($tour[$imgField])) $referencedPaths[$tour[$imgField]] = true;
    }
    foreach ($daysByTour[$tour['id']] ?? [] as $day) {
        if (!empty($day['image_path'])) $referencedPaths[$day['image_path']] = true;
    }
}
$knownPaths = [];
foreach ($galleryTree as $cat) {
    foreach ($cat['items'] as $item) $knownPaths[$item['path']] = true;
}
$legacyBucket = ['name' => 'Previously uploaded', 'items' => []];
foreach ($referencedPaths as $path => $_) {
    if (!isset($knownPaths[$path])) {
        $legacyBucket['items'][] = ['path' => $path, 'title' => basename($path)];
    }
}
if (!empty($legacyBucket['items'])) {
    $galleryTree['_legacy'] = $legacyBucket;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tours - Kizza Tours Admin</title>
    <link rel="icon" href="../assets/images/log.png" type="image/png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../templates/assets/css/ruang-admin.min.css" rel="stylesheet">
    <link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">
    <link href="css/admin.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .sidebar-light .sidebar-brand { background-color: #0A2540 !important; }
        .bg-navbar { background-color: #0A2540 !important; }
        #accordionSidebar { position: fixed; top: 0; left: 0; height: 100vh; z-index: 1030; overflow-y: auto; }
        #content-wrapper { margin-left: 14rem; transition: margin-left 0.3s ease-in-out; }
        body.sidebar-toggled #content-wrapper { margin-left: 6.5rem; }
        .topbar { position: fixed; top: 0; right: 0; left: 14rem; z-index: 1020; transition: left 0.3s ease-in-out; }
        body.sidebar-toggled .topbar { left: 6.5rem; }
        #content { padding-top: 70px; }
        @media (max-width: 768px) {
            #accordionSidebar { width: 0; }
            #content-wrapper { margin-left: 0; }
            body.sidebar-toggled #content-wrapper { margin-left: 0; }
            .topbar { left: 0; }
            body.sidebar-toggled .topbar { left: 0; }
        }

        /* ── Itinerary day location map (Leaflet / OpenStreetMap) ── */
        .itinerary-location-map-wrapper {
            position: relative;
            width: 100%;
            margin-top: 10px;
            border: 1px solid #dce3ea;
            border-radius: 10px;
            overflow: hidden;
            background: #eef2f5;
        }
        .itinerary-location-map {
            position: relative;
            display: block;
            width: 100%;
            height: 300px;
            min-height: 300px;
            overflow: hidden;
            z-index: 1;
            background: #e8eef2;
        }
        .itinerary-location-map .leaflet-pane,
        .itinerary-location-map .leaflet-tile,
        .itinerary-location-map .leaflet-marker-icon,
        .itinerary-location-map .leaflet-marker-shadow,
        .itinerary-location-map .leaflet-pane > svg,
        .itinerary-location-map .leaflet-pane > canvas {
            position: absolute;
        }
        .itinerary-location-map img.leaflet-tile,
        .itinerary-location-map img.leaflet-marker-icon,
        .itinerary-location-map img.leaflet-marker-shadow {
            max-width: none !important;
            max-height: none !important;
            width: auto;
            height: auto;
            padding: 0;
            margin: 0;
            border: 0;
        }
        .itinerary-map-marker-wrapper { background: transparent; border: 0; }
        .itinerary-map-marker {
            position: relative;
            width: 36px;
            height: 46px;
        }
        .itinerary-map-marker::before {
            content: '';
            position: absolute;
            left: 4px; top: 0;
            width: 28px; height: 28px;
            background: #c13d31;
            border: 2px solid #fff;
            border-radius: 50% 50% 50% 0;
            transform: rotate(-45deg);
        }
        .itinerary-map-marker span {
            position: absolute;
            left: 0; right: 0; top: 7px;
            text-align: center;
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            z-index: 2;
        }
        .loc-result-item { cursor: pointer; }

        /* ── Gallery image picker ── */
        .gal-filter-btn.active,
        .gal-filter-btn.active:hover {
            background-color: #0A2540 !important;
            border-color: #0A2540 !important;
            color: #fff !important;
        }
        .gal-thumb {
            flex: 0 0 auto;
            width: 92px;
            margin: 0 6px 8px 0;
            padding: 4px;
            border: 1px solid #dce3ea;
            border-radius: 6px;
            background: #fff;
            text-align: center;
            cursor: pointer;
            vertical-align: top;
        }
        .gal-thumb:hover,
        .gal-thumb:focus {
            border-color: #0A2540;
            box-shadow: 0 1px 4px rgba(10, 37, 64, 0.25);
            outline: none;
        }
        .gal-thumb img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
            display: block;
            margin: 0 auto;
        }
        .gal-thumb span {
            display: block;
            font-size: 0.66rem;
            color: #555;
            margin-top: 3px;
            line-height: 1.15;
            max-height: 2.3em;
            overflow: hidden;
        }
        .gal-cat { margin-bottom: 10px; }
        .tour-image-thumb img {
            width: 72px;
            height: 72px;
            object-fit: cover;
            border-radius: 4px;
            cursor: pointer;
            border: 1px solid #dce3ea;
        }
        #tourModal .modal-dialog {
            height: calc(100vh - 2rem);
            max-height: calc(100vh - 2rem);
            margin: 1rem auto;
        }
        #tourModal .modal-content {
            height: 100%;
            max-height: calc(100vh - 2rem);
            display: -ms-flexbox;
            display: flex;
            -ms-flex-direction: column;
            flex-direction: column;
            overflow: hidden;
        }
        #tourModal .modal-header,
        #tourModal .modal-footer {
            -ms-flex-negative: 0;
            flex-shrink: 0;
        }
        #tourModal form {
            -ms-flex: 1 1 auto;
            flex: 1 1 auto;
            min-height: 0;
            display: -ms-flexbox;
            display: flex;
            -ms-flex-direction: column;
            flex-direction: column;
        }
        #tourModal .modal-body {
            -ms-flex: 1 1 auto;
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
            overscroll-behavior: contain;
            -webkit-overflow-scrolling: touch;
        }
    </style>
</head>
<body id="page-top">
    <div id="wrapper">
        <ul class="navbar-nav sidebar sidebar-light accordion" id="accordionSidebar">
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="#">
                <div class="sidebar-brand-icon"><img src="../assets/images/log.png" alt="Kizza Tours" height="35"></div>
                <div class="sidebar-brand-text mx-3 text-white">Admin</div>
            </a>
            <hr class="sidebar-divider my-0">
            <li class="nav-item"><a class="nav-link" href="dashboard"><i class="fas fa-fw fa-tachometer-alt"></i><span>Dashboard</span></a></li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Management</div>
            <li class="nav-item"><a class="nav-link" href="bookings"><i class="fas fa-fw fa-calendar-check"></i><span>Bookings</span></a></li>
            <li class="nav-item active"><a class="nav-link" href="tours"><i class="fas fa-fw fa-safari"></i><span>Tours</span></a></li>
            <li class="nav-item"><a class="nav-link" href="destinations"><i class="fas fa-fw fa-map-marker-alt"></i><span>Destinations</span></a></li>
            <li class="nav-item"><a class="nav-link" href="gallery"><i class="fas fa-fw fa-images"></i><span>Gallery</span></a></li>
            <li class="nav-item"><a class="nav-link" href="testimonials"><i class="fas fa-fw fa-star"></i><span>Testimonials</span></a></li>
            <li class="nav-item"><a class="nav-link" href="faqs"><i class="fas fa-fw fa-question-circle"></i><span>FAQs</span></a></li>
            <li class="nav-item"><a class="nav-link" href="inquiries"><i class="fas fa-fw fa-envelope"></i><span>Inquiries</span></a></li>
            <li class="nav-item"><a class="nav-link" href="quotes"><i class="fas fa-fw fa-file-invoice"></i><span>Quotes</span></a></li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Tools</div>
            <li class="nav-item"><a class="nav-link" href="compress-images"><i class="fas fa-fw fa-compress-alt"></i><span>Compress Images</span></a></li>
            <li class="nav-item"><a class="nav-link" href="sitemap"><i class="fas fa-fw fa-sitemap"></i><span>Sitemap</span></a></li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Account</div>
            <li class="nav-item"><a class="nav-link" href="profile"><i class="fas fa-fw fa-user"></i><span>My Profile</span></a></li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">System</div>
            <li class="nav-item"><a class="nav-link" href="settings"><i class="fas fa-fw fa-cog"></i><span>Settings</span></a></li>
            <li class="nav-item"><a class="nav-link" href="logout"><i class="fas fa-fw fa-sign-out-alt"></i><span>Logout</span></a></li>
            <hr class="sidebar-divider d-none d-md-block">
            <div class="version" id="version-ruangadmin">Version 1.0</div>
        </ul>

        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <nav class="navbar navbar-expand navbar-light bg-navbar topbar mb-4 static-top" style="background-color: #0A2540;">
                    <button id="sidebarToggleTop" class="btn btn-link rounded-circle mr-3"><i class="fa fa-bars text-white"></i></button>
                    <ul class="navbar-nav ml-auto">
                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="searchDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-search fa-fw text-white"></i>
                            </a>
                            <div class="dropdown-menu dropdown-menu-right p-3 shadow animated--grow-in" aria-labelledby="searchDropdown">
                                <form class="navbar-search" action="search" method="GET">
                                    <div class="input-group">
                                        <input type="text" class="form-control bg-light border-1 small" name="q" placeholder="What do you want to look for?" aria-label="Search">
                                        <div class="input-group-append">
                                            <button class="btn btn-primary" type="submit" style="background-color: #0A2540; border-color: #0A2540;">
                                                <i class="fas fa-search fa-sm"></i>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </li>
                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <?php if (!empty($_SESSION['admin_image']) && file_exists(__DIR__ . '/uploads/profile/' . $_SESSION['admin_image'])): ?>
                                    <img src="uploads/profile/<?php echo $_SESSION['admin_image']; ?>" class="rounded-circle mr-1" style="width: 30px; height: 30px; object-fit: cover;">
                                <?php else: ?>
                                    <i class="fas fa-user-circle text-white mr-1"></i>
                                <?php endif; ?>
                                <span class="ml-2 d-none d-lg-inline text-white small"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="userDropdown">
                                <a class="dropdown-item" href="profile"><i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i> Profile</a>
                                <a class="dropdown-item" href="settings"><i class="fas fa-cogs fa-sm fa-fw mr-2 text-gray-400"></i> Settings</a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="logout"><i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i> Logout</a>
                            </div>
                        </li>
                    </ul>
                </nav>

                <div class="container-fluid" id="container-wrapper">
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h4 class="mb-0 text-gray-800"><img src="../assets/images/log.png" alt="" height="32" class="mr-2"> Manage Tours</h4>
                        <button class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#tourModal" onclick="openAddTour()">
                            <i class="fas fa-plus"></i> Add Tour
                        </button>
                    </div>
                    
                    <?php
                    $draftsCleared = !empty($_SESSION['drafts_cleared']);
                    unset($_SESSION['drafts_cleared']);
                    ?>

                    <?php if (isset($_SESSION['flash'])): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <?php echo htmlspecialchars($_SESSION['flash']['message']); unset($_SESSION['flash']); ?>
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                    <?php endif; ?>

                    <div class="card mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <input type="text" id="tableSearch" class="form-control form-control-sm" style="max-width: 300px;" placeholder="Filter tours..." onkeyup="filterTable(this.value)">
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0" id="dataTable">
                                    <thead>
                                        <tr>
                                            <th>Image</th>
                                            <th>Title</th>
                                            <th>Destination</th>
                                            <th>Duration</th>
                                            <th>Price</th>
                                            <th>Rating</th>
                                            <th>SEO</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tours as $tour):
                                            $editTourData = $tour;
                                            $editTourData['days'] = $daysByTour[$tour['id']] ?? [];
                                            $editTourData['faqs'] = $faqsByTour[$tour['id']] ?? [];
                                        ?>
                                        <tr>
                                            <td>
                                                <?php if ($tour['image'] && file_exists(BASE_PATH . $tour['image'])): ?>
                                                    <img src="../<?php echo $tour['image']; ?>" style="width:60px;height:40px;object-fit:cover;border-radius:4px;">
                                                <?php else: ?>
                                                    <span class="text-muted"><i class="fas fa-image"></i></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><strong><?php echo htmlspecialchars($tour['title']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($tour['dest_name'] ?: $tour['country'] ?: '-'); ?></td>
                                            <td><?php echo htmlspecialchars($tour['duration'] ?: '-'); ?></td>
                                            <td>$<?php echo number_format($tour['price'], 0); ?></td>
                                            <td>
                                                <?php for ($i = 0; $i < 5; $i++): ?>
                                                    <i class="fas fa-star" style="color: <?php echo $i < $tour['rating'] ? '#0A2540' : '#e0e0e0'; ?>; font-size: 0.7rem;"></i>
                                                <?php endfor; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($tour['no_robots'])): ?>
                                                    <span class="badge badge-warning" title="No Index - hidden from search engines"><i class="fas fa-eye-slash"></i> NoIndex</span>
                                                <?php elseif (!empty($tour['meta_title']) || !empty($tour['meta_description'])): ?>
                                                    <span class="badge badge-info" title="Custom SEO meta set"><i class="fas fa-check"></i> Custom</span>
                                                <?php else: ?>
                                                    <span class="badge badge-light text-muted" title="Auto-generated SEO"><i class="fas fa-robot"></i> Auto</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="badge badge-<?php echo $tour['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($tour['status']); ?></span></td>
                                            <td>
                                                <div class="d-flex">
                                                    <a href="../safari/<?php echo htmlspecialchars($tour['slug']); ?>" target="_blank" class="btn btn-sm btn-outline-info mr-1" title="View Tour">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <button class="btn btn-sm btn-outline-secondary mr-1" onclick="editTour(<?php echo htmlspecialchars(json_encode($editTourData)); ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this tour?');">
                                                        <?php csrf_field(); ?>
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="tour_id" value="<?php echo $tour['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($tours)): ?>
                                        <tr><td colspan="9" class="text-center py-4 text-muted">No tours found. Add your first tour!</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright &copy; <script>document.write(new Date().getFullYear());</script> - <b>Kizza Tours & Safaris</b></span>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- Tour Modal -->
    <div class="modal fade" id="tourModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tourModalTitle">Add Tour</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <?php csrf_field(); ?>
                    <div class="modal-body" tabindex="-1">
                        <input type="hidden" name="action" id="tourAction" value="add">
                        <input type="hidden" name="tour_id" id="tourId" value="0">

                        <div id="tourDraftBanner" class="alert alert-warning d-none" role="alert">
                            <div class="d-flex align-items-center justify-content-between flex-wrap">
                                <span><i class="fas fa-save mr-1"></i> <strong>Draft found</strong> — auto-saved <span id="tourDraftTime"></span>. Continue where you left off.</span>
                                <span class="ml-auto">
                                    <button type="button" class="btn btn-sm btn-warning" onclick="resumeTourDraft()"><i class="fas fa-redo-alt mr-1"></i> Resume draft</button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="discardTourDraft()"><i class="fas fa-trash mr-1"></i> Discard</button>
                                </span>
                            </div>
                            <small class="d-block text-muted mt-2">All text is restored. Images you had picked must be selected again (browsers can't save files in a draft).</small>
                        </div>
                        <div id="tourDraftRestoredMsg" class="alert alert-info d-none"></div>

                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label>Tour Title</label>
                                    <input type="text" class="form-control" name="title" id="tourTitle" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Slug</label>
                                    <input type="text" class="form-control" name="slug" id="tourSlug">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Duration</label>
                                    <input type="text" class="form-control" name="duration" id="tourDuration" placeholder="7 Days / 6 Nights">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Price (USD)</label>
                                    <input type="number" step="0.01" class="form-control" name="price" id="tourPrice">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Rating (1-5)</label>
                                    <input type="number" step="0.1" min="1" max="5" class="form-control" name="rating" id="tourRating" value="5">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Country</label>
                                    <input type="text" class="form-control" name="country" id="tourCountry" placeholder="Tanzania">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Destination</label>
                                    <select class="form-control" name="destination_id" id="tourDest">
                                        <option value="">None</option>
                                        <?php foreach ($destinations as $d): ?>
                                        <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name'] . ' (' . $d['country'] . ')'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Max Guests</label>
                                    <input type="number" class="form-control" name="max_guests" id="tourGuests" value="10">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Status</label>
                                    <select class="form-control" name="status" id="tourStatus">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group tour-image-field">
                                    <label>Image</label>
                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-block" onclick="openGalleryPicker({type:'main', inputId:'image_gallery', previewImgId:'imagePreview', wrapId:'imagePreviewWrap'})"><i class="fas fa-images mr-1"></i> Choose from gallery</button>
                                    <div class="tour-image-thumb d-none my-1 text-center" id="imagePreviewWrap">
                                        <img src="" alt="" id="imagePreview" onclick="openGalleryPicker({type:'main', inputId:'image_gallery', previewImgId:'imagePreview', wrapId:'imagePreviewWrap'})">
                                        <button type="button" class="btn btn-sm btn-outline-danger ml-1 align-top" title="Remove" onclick="clearGalleryField('image_gallery','imagePreview','imagePreviewWrap')"><i class="fas fa-times"></i></button>
                                    </div>
                                    <input type="hidden" name="image_gallery" id="image_gallery" value="">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group tour-image-field">
                                    <label>Hero Image <small class="text-muted">(full-width banner)</small></label>
                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-block" onclick="openGalleryPicker({type:'main', inputId:'hero_image_gallery', previewImgId:'heroPreview', wrapId:'heroPreviewWrap'})"><i class="fas fa-images mr-1"></i> Choose from gallery</button>
                                    <div class="tour-image-thumb d-none my-1 text-center" id="heroPreviewWrap">
                                        <img src="" alt="" id="heroPreview" onclick="openGalleryPicker({type:'main', inputId:'hero_image_gallery', previewImgId:'heroPreview', wrapId:'heroPreviewWrap'})">
                                        <button type="button" class="btn btn-sm btn-outline-danger ml-1 align-top" title="Remove" onclick="clearGalleryField('hero_image_gallery','heroPreview','heroPreviewWrap')"><i class="fas fa-times"></i></button>
                                    </div>
                                    <input type="hidden" name="hero_image_gallery" id="hero_image_gallery" value="">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-images mr-1"></i> Tour Overview Images <small class="text-muted">(the 3 collage photos on the tour page)</small></label>
                            <div class="row">
                                <?php for ($ov = 1; $ov <= 3; $ov++): ?>
                                <div class="col-md-4">
                                    <div class="overview-img-preview border rounded p-2 mb-2 text-center bg-white" id="ovPrevWrap<?php echo $ov; ?>">
                                        <img id="ovPrev<?php echo $ov; ?>" src="" alt="" style="max-width:100%; max-height:100px; object-fit:cover; display:none;">
                                        <span id="ovEmpty<?php echo $ov; ?>" class="text-muted small"><i class="fas fa-image"></i> no image</span>
                                    </div>
                                    <input type="hidden" name="overview_image_<?php echo $ov; ?>_current" id="ovCur<?php echo $ov; ?>" value="">
                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-block mb-1" onclick="openGalleryPicker({type:'overview', idx:<?php echo $ov; ?>})"><i class="fas fa-images mr-1"></i> Choose from gallery</button>
                                    <div class="form-check form-check-inline mt-1">
                                        <input class="form-check-input" type="checkbox" name="overview_image_<?php echo $ov; ?>_remove" id="ovRemove<?php echo $ov; ?>" value="1">
                                        <label class="form-check-label small" for="ovRemove<?php echo $ov; ?>">Remove</label>
                                    </div>
                                </div>
                                <?php endfor; ?>
                            </div>
                            <small class="text-muted">Pick ready WebP photos from the gallery — shown as thumbnail previews grouped by category. Slot 1 is the large image; slots 2 &amp; 3 are the overlapping photos. Leave all three empty to keep the old auto-behaviour (featured image + gallery).</small>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea class="form-control" name="description" id="tourDescription" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Highlights</label>
                                    <textarea class="form-control" name="highlights" id="tourHighlights" rows="4" placeholder="Wildlife viewing, Scenic game drives, Professional guide ..."></textarea>
                                    <small class="d-block text-muted mt-1">Paste all highlights separated by commas. Each one becomes a bullet on the tour page.</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Includes</label>
                                    <textarea class="form-control" name="includes" id="tourIncludes" rows="4" placeholder="Park fees, Accommodation, Meals ..."></textarea>
                                    <small class="d-block text-muted mt-1">Paste all includes separated by commas. Each one becomes a bullet on the tour page.</small>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Excludes</label>
                                    <textarea class="form-control" name="excludes" id="tourExcludes" rows="4" placeholder="Flights, Visas, Travel insurance ..."></textarea>
                                    <small class="d-block text-muted mt-1">Paste all excludes separated by commas. Each one becomes a bullet on the tour page.</small>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-group">
                                    <label>Gallery Images (comma separated)</label>
                                    <textarea class="form-control" name="gallery" id="tourGallery" rows="2" placeholder="uploads/tours/image1.webp, uploads/tours/image2.webp"></textarea>
                                    <small class="text-muted">Enter file paths relative to project root, separated by commas. Upload images to <code>uploads/tours/</code> via FTP or file manager first.</small>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-map-signs mr-1"></i> Itinerary Days</label>
                            <p class="text-muted small">Add each day of the tour with a title, description and an optional image. Days are shown in the order listed — use the up/down buttons to reorder.</p>
                            <div id="itineraryDaysContainer" class="mb-2"></div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addItineraryDay()">
                                <i class="fas fa-plus"></i> Add Day
                            </button>
                            <small class="d-block text-muted mt-2" id="legacyItineraryNote" style="display:none;">
                                <i class="fas fa-info-circle"></i> This tour has legacy itinerary text still shown on the frontend until you add structured days above.
                            </small>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-question-circle mr-1"></i> Tour FAQs</label>
                            <p class="text-muted small">FAQs shown only on this tour's detail page. Leave empty to hide the FAQ section for that tour.</p>
                            <div id="tourFaqsContainer" class="mb-2"></div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addTourFaq({})">
                                <i class="fas fa-plus"></i> Add FAQ
                            </button>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Meta Title <small class="text-muted">(max 255 chars)</small></label>
                                    <input type="text" class="form-control" name="meta_title" id="tourMetaTitle" maxlength="255">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Meta Keywords <small class="text-muted">(max 255 chars)</small></label>
                                    <input type="text" class="form-control" name="meta_keywords" id="tourMetaKeywords" maxlength="255">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Meta Description <small class="text-muted">(recommended max 160 chars for Google)</small></label>
                            <textarea class="form-control" name="meta_description" id="tourMetaDesc" rows="3" maxlength="500"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Search Engine Indexing</label>
                                    <select class="form-control" name="no_robots" id="tourNoRobots">
                                        <option value="0">Index (allow search engines)</option>
                                        <option value="1">No Index (hide from search engines)</option>
                                    </select>
                                    <small class="text-muted">No Index tours won't appear in Google or the sitemap.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <span id="tourDraftSavedMsg" class="text-muted small mr-auto" style="opacity:0;"></span>
                        <button type="button" class="btn btn-outline-warning" onclick="saveTourDraftNow()"><i class="fas fa-save mr-1"></i> Save Draft</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-outline-secondary">Save Tour</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Shared Gallery Picker Modal (centred, thumbnail previews by category) -->
    <div class="modal fade" id="galleryPickerModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title"><i class="fas fa-images mr-1"></i> Choose image from gallery</h6>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body p-3">
                    <div id="galPickerFilter" class="d-flex flex-wrap align-items-center mb-2"></div>
                    <input type="search" id="galPickerSearch" class="form-control form-control-sm mb-2" placeholder="Filter images by name...">
                    <div id="galPickerGrid" style="max-height:55vh;overflow-y:auto;padding-right:4px;"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-danger mr-auto" id="galPickerClear"><i class="fas fa-times mr-1"></i> No image</button>
                    <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-easing/1.4.1/jquery.easing.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="../templates/assets/js/ruang-admin.min.js"></script>
    <script>
        var TOUR_DRAFTS_CLEARED = <?php echo $draftsCleared ? 'true' : 'false'; ?>;
        var TOUR_GALLERY_TREE = <?php echo json_encode($galleryTree ?: new stdClass()); ?>;

        // ── Gallery image picker ─────────────────────────────────
        // Every image field in the tour form opens a centred picker that shows
        // small square WebP previews grouped by category. Picking one just
        // stores its existing "uploads/..." path, so saving a tour no longer
        // re-uploads/re-converts whole folders of photos.

        var TOUR_GALLERY_PICKER_BUILT = false;
        var TOUR_GALLERY_ACTIVE_CAT = '';
        var TOUR_GALLERY_PICK_TARGET = null;

        function openGalleryPicker(target) {
            if (!TOUR_GALLERY_PICKER_BUILT) {
                renderGalleryPicker();
                setupGalleryPickerEvents();
                TOUR_GALLERY_PICKER_BUILT = true;
            }
            TOUR_GALLERY_PICK_TARGET = target || null;
            TOUR_GALLERY_ACTIVE_CAT = '';
            var btns = document.querySelectorAll('.gal-filter-btn');
            Array.prototype.forEach.call(btns, function (b) {
                b.classList.toggle('active', b.getAttribute('data-cat') === '');
            });
            var search = document.getElementById('galPickerSearch');
            if (search) search.value = '';
            filterGalleryGrid();
            $('#galleryPickerModal').modal('show');
        }

        function renderGalleryPicker() {
            var filter = document.getElementById('galPickerFilter');
            var grid = document.getElementById('galPickerGrid');
            if (!filter || !grid) return;
            var cats = Object.keys(TOUR_GALLERY_TREE || {});
            var catHtml = '<button type="button" class="btn btn-sm btn-outline-secondary mr-1 mb-1 gal-filter-btn active" data-cat="">All</button>';
            cats.forEach(function (slug) {
                if (slug === '_legacy') return;
                var cat = TOUR_GALLERY_TREE[slug] || {};
                catHtml += '<button type="button" class="btn btn-sm btn-outline-secondary mr-1 mb-1 gal-filter-btn" data-cat="' + escapeAttr(slug) + '">' + escapeAttr(cat.name || slug) + '</button>';
            });
            filter.innerHTML = catHtml;
            var g = '';
            cats.forEach(function (slug) {
                var cat = TOUR_GALLERY_TREE[slug] || {};
                g += '<div class="gal-cat" data-cat="' + escapeAttr(slug) + '">';
                g += '<div class="gal-cat-title small font-weight-bold text-uppercase text-muted my-1">' + escapeAttr(cat.name || slug) + '</div>';
                g += '<div class="d-flex flex-wrap">';
                (cat.items || []).forEach(function (item) {
                    g += '<button type="button" class="gal-thumb" data-path="' + escapeAttr(item.path) + '" data-title="' + escapeAttr(item.title || '') + '">'
                        + '<img src="../' + escapeAttr(item.path).replace(/^\//, '') + '" alt="" loading="lazy">'
                        + '<span>' + escapeAttr(item.title || '') + '</span></button>';
                });
                g += '</div></div>';
            });
            if (!cats.length) g = '<p class="text-muted small mb-0">No images in the gallery yet. Add some in the Gallery section first.</p>';
            grid.innerHTML = g;
        }

        function setupGalleryPickerEvents() {
            var filter = document.getElementById('galPickerFilter');
            if (filter) {
                filter.addEventListener('click', function (e) {
                    var btn = e.target.closest('.gal-filter-btn');
                    if (!btn) return;
                    TOUR_GALLERY_ACTIVE_CAT = btn.getAttribute('data-cat') || '';
                    Array.prototype.forEach.call(filter.querySelectorAll('.gal-filter-btn'), function (b) {
                        b.classList.toggle('active', b === btn);
                    });
                    filterGalleryGrid();
                });
            }
            var search = document.getElementById('galPickerSearch');
            if (search) search.addEventListener('input', filterGalleryGrid);
            var grid = document.getElementById('galPickerGrid');
            if (grid) {
                grid.addEventListener('click', function (e) {
                    var thumb = e.target.closest('.gal-thumb');
                    if (thumb) pickGalleryImage(thumb.getAttribute('data-path'));
                });
            }
            var clearBtn = document.getElementById('galPickerClear');
            if (clearBtn) clearBtn.addEventListener('click', function () { pickGalleryImage(''); });
        }

        function filterGalleryGrid() {
            var grid = document.getElementById('galPickerGrid');
            if (!grid) return;
            var search = document.getElementById('galPickerSearch');
            var q = search ? search.value.trim().toLowerCase() : '';
            Array.prototype.forEach.call(grid.querySelectorAll('.gal-cat'), function (cat) {
                var catSlug = cat.getAttribute('data-cat') || '';
                var showCat = TOUR_GALLERY_ACTIVE_CAT === '' || catSlug === TOUR_GALLERY_ACTIVE_CAT;
                var anyVisible = false;
                Array.prototype.forEach.call(cat.querySelectorAll('.gal-thumb'), function (thumb) {
                    var hay = ((thumb.getAttribute('data-title') || '') + ' ' + (thumb.getAttribute('data-path') || '')).toLowerCase();
                    var visible = showCat && (!q || hay.indexOf(q) > -1);
                    thumb.style.display = visible ? '' : 'none';
                    if (visible) anyVisible = true;
                });
                cat.style.display = showCat && anyVisible ? '' : 'none';
            });
        }

        function pickGalleryImage(path) {
            path = path || '';
            var t = TOUR_GALLERY_PICK_TARGET;
            $('#galleryPickerModal').modal('hide');
            if (!t) return;
            if (t.type === 'main') {
                var input = document.getElementById(t.inputId);
                if (input) input.value = path;
                var wrap = document.getElementById(t.wrapId);
                var previewImg = document.getElementById(t.previewImgId);
                if (previewImg) previewImg.src = path ? '../' + path.replace(/^\//, '') : '';
                if (wrap) wrap.classList.toggle('d-none', !path);
            } else if (t.type === 'overview') {
                setOverviewSlot(t.idx, path);
            } else if (t.type === 'day') {
                setDayGalleryImage(t.row, path);
            }
            TOUR_GALLERY_PICK_TARGET = null;
        }

        // Bootstrap 4 bug: with two stacked modals, hiding the top one removes
        // body.modal-open, which would re-enable scrolling of the background
        // page. Restore it while the tour modal is still open.
        $('#galleryPickerModal').on('hidden.bs.modal', function () {
            if ($('#tourModal').hasClass('show')) {
                document.body.classList.add('modal-open');
            }
            var body = document.querySelector('#tourModal .modal-body');
            if (body) body.focus();
        });

        function setThumbFromHidden(inputId, previewImgId, wrapId) {
            var input = document.getElementById(inputId);
            var previewImg = document.getElementById(previewImgId);
            var wrap = document.getElementById(wrapId);
            var path = input ? input.value : '';
            if (previewImg) previewImg.src = path ? '../' + path.replace(/^\//, '') : '';
            if (wrap) wrap.classList.toggle('d-none', !path);
        }

        function clearGalleryField(inputId, previewImgId, wrapId) {
            var input = document.getElementById(inputId);
            if (input) input.value = '';
            setThumbFromHidden(inputId, previewImgId, wrapId);
        }

        function resetMainImages() {
            clearGalleryField('image_gallery', 'imagePreview', 'imagePreviewWrap');
            clearGalleryField('hero_image_gallery', 'heroPreview', 'heroPreviewWrap');
        }

        function setMainImages(imagePath, heroPath) {
            var imgInput = document.getElementById('image_gallery');
            if (imgInput) { imgInput.value = imagePath || ''; setThumbFromHidden('image_gallery', 'imagePreview', 'imagePreviewWrap'); }
            var heroInput = document.getElementById('hero_image_gallery');
            if (heroInput) { heroInput.value = heroPath || ''; setThumbFromHidden('hero_image_gallery', 'heroPreview', 'heroPreviewWrap'); }
        }

        function openAddTour() {
            document.getElementById('tourAction').value = 'add';
            document.getElementById('tourId').value = '0';
            document.getElementById('tourModalTitle').textContent = 'Add Tour';
        }

        function escapeAttr(v) {
            if (typeof v !== 'string') v = String(v == null ? '' : v);
            var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
            return v.replace(/[&<>"']/g, function(c) { return map[c]; });
        }

        function listToComma(raw) {
            var items = [];
            if (raw) {
                if (typeof raw === 'string') {
                    raw = raw.trim();
                    if (raw.charAt(0) === '[') {
                        try {
                            var arr = JSON.parse(raw);
                            if (Array.isArray(arr)) items = arr;
                        } catch (e) { items = []; }
                    } else {
                        items = raw.split(/\r\n|\r|\n|,/).map(function(s) { return s.trim(); });
                    }
                } else if (Array.isArray(raw)) {
                    items = raw;
                }
            }
            return items.filter(function(s) { return typeof s === 'string' && s.trim() !== ''; }).join(', ');
        }

        // ── Auto-Save Draft (localStorage) ─────────────────────────────
        // Every field of the tour form (text, itinerary days, FAQs) is saved to
        // localStorage a moment after each edit and when the modal closes. When
        // you come back later and reopen the same "Add Tour" / "Edit Tour", a
        // banner lets you resume the draft. Browsers can't save files, so image
        // uploads must be re-picked (the form tells you which ones).

        var TOUR_DRAFT = (function () {
            var PREFIX = 'kizza_tour_draft_';
            function cleanId(id) {
                var n = parseInt(id, 10);
                return isNaN(n) ? 0 : n;
            }
            function keyFor(mode, id) {
                return PREFIX + (mode === 'edit' ? 'edit_' + cleanId(id) : 'add_0');
            }
            function currentKey() {
                var action = document.getElementById('tourAction');
                var idEl = document.getElementById('tourId');
                var mode = action && action.value === 'edit' ? 'edit' : 'add';
                return keyFor(mode, idEl ? idEl.value : 0);
            }
            function read(key) {
                try { return JSON.parse(localStorage.getItem(key) || 'null'); }
                catch (e) { return null; }
            }
            function save(key) {
                var form = document.querySelector('#tourModal form');
                if (!form) return false;
                var action = document.getElementById('tourAction');
                var idEl = document.getElementById('tourId');
                var mode = action && action.value === 'edit' ? 'edit' : 'add';
                var data = serializeTourForm();
                if (!draftHasContent(data)) return false;
                var draft = {
                    ts: Date.now(),
                    mode: mode,
                    tourId: idEl ? idEl.value : '0',
                    values: data
                };
                try {
                    localStorage.setItem(key || currentKey(), JSON.stringify(draft));
                    return true;
                } catch (e) { return false; }
            }
            function draftHasContent(d) {
                var keys = ['title', 'slug', 'duration', 'price', 'description',
                            'highlights', 'includes', 'excludes', 'gallery',
                            'country', 'destination_id',
                            'meta_title', 'meta_keywords', 'meta_description'];
                for (var i = 0; i < keys.length; i++) {
                    if (d[keys[i]] !== undefined && String(d[keys[i]]).trim() !== '') return true;
                }
                if ((d.day_title || []).some(function (t) { return t && String(t).trim() !== ''; })) return true;
                if ((d.faq_question || []).some(function (q) { return q && String(q).trim() !== ''; })) return true;
                return false;
            }
            function clear(key) {
                try { localStorage.removeItem(key || currentKey()); } catch (e) {}
            }
            function clearAll() {
                try {
                    var doomed = [];
                    for (var i = 0; i < localStorage.length; i++) {
                        var k = localStorage.key(i);
                        if (k && k.indexOf(PREFIX) === 0) doomed.push(k);
                    }
                    doomed.forEach(function (k) { localStorage.removeItem(k); });
                } catch (e) {}
            }
            return { currentKey: currentKey, keyFor: keyFor, read: read, save: save, clear: clear, clearAll: clearAll };
        })();

        function serializeTourForm() {
            var form = document.querySelector('#tourModal form');
            var data = {};
            if (!form) return data;
            Array.prototype.forEach.call(form.elements, function (el) {
                if (!el.name || el.name === 'csrf_token') return;
                var isArr = el.name.slice(-2) === '[]';
                var key = isArr ? el.name.slice(0, -2) : el.name;
                var val;
                if (el.type === 'checkbox' || el.type === 'radio') {
                    val = el.checked;
                } else {
                    val = el.value;
                }
                if (isArr) {
                    if (!data[key]) data[key] = [];
                    data[key].push(val);
                } else {
                    data[key] = val;
                }
            });
            return data;
        }

        function timeAgo(ts) {
            if (!ts) return '';
            var mins = Math.max(0, Math.floor((Date.now() - ts) / 60000));
            if (mins < 1) return 'just now';
            if (mins < 60) return mins + ' min ago';
            var h = Math.floor(mins / 60);
            var m = mins % 60;
            return h + (m ? 'h ' + m + 'm' : 'h') + ' ago';
        }

        function updateDraftBanner() {
            var banner = document.getElementById('tourDraftBanner');
            if (!banner) return;
            var draft = TOUR_DRAFT.read(TOUR_DRAFT.currentKey());
            if (!draft || !draft.values) { banner.classList.add('d-none'); return; }
            banner.classList.remove('d-none');
            document.getElementById('tourDraftTime').textContent = timeAgo(draft.ts);
        }

        function showRestoreNotice(missing) {
            var el = document.getElementById('tourDraftRestoredMsg');
            if (!el) return;
            var msg = 'Draft restored.';
            if (missing && missing.length) {
                msg = 'Draft restored. Images to re-select: ' + missing.join(', ') + '.';
            }
            el.textContent = msg;
            el.classList.remove('d-none');
            setTimeout(function () { el.classList.add('d-none'); }, 9000);
        }

        function resumeTourDraft() {
            var draft = TOUR_DRAFT.read(TOUR_DRAFT.currentKey());
            if (!draft || !draft.values) return;
            var d = draft.values;
            var actionEl = document.getElementById('tourAction');
            var idEl = document.getElementById('tourId');
            var modalTitle = document.getElementById('tourModalTitle');
            if (actionEl) actionEl.value = draft.mode === 'edit' ? 'edit' : 'add';
            if (idEl) idEl.value = draft.tourId || '0';
            if (modalTitle) modalTitle.textContent = draft.mode === 'edit' ? 'Edit Tour' : 'Add Tour';

            var simple = {
                title: 'tourTitle', slug: 'tourSlug', duration: 'tourDuration', price: 'tourPrice',
                rating: 'tourRating', country: 'tourCountry', destination_id: 'tourDest',
                max_guests: 'tourGuests', status: 'tourStatus', description: 'tourDescription',
                highlights: 'tourHighlights', includes: 'tourIncludes', excludes: 'tourExcludes',
                gallery: 'tourGallery', meta_title: 'tourMetaTitle', meta_keywords: 'tourMetaKeywords',
                meta_description: 'tourMetaDesc', no_robots: 'tourNoRobots'
            };
            Object.keys(simple).forEach(function (k) {
                var el = document.getElementById(simple[k]);
                if (el && d[k] !== undefined) el.value = d[k];
            });

            [['image_gallery', 'imagePreview', 'imagePreviewWrap'], ['hero_image_gallery', 'heroPreview', 'heroPreviewWrap']].forEach(function (m) {
                var el = document.getElementById(m[0]);
                if (el && d[m[0]] !== undefined) {
                    el.value = d[m[0]] || '';
                    setThumbFromHidden(m[0], m[1], m[2]);
                }
            });

            for (var i = 1; i <= 3; i++) {
                setOverviewSlot(i, d['overview_image_' + i + '_current'] || '');
            }

            var form = document.querySelector('#tourModal form');
            if (form) {
                Array.prototype.forEach.call(form.elements, function (el) {
                    if (!el.name || el.name.indexOf('[]') !== -1 || el.name === 'csrf_token') return;
                    if (el.type !== 'checkbox' && el.type !== 'radio') return;
                    if (d[el.name] !== undefined) el.checked = !!d[el.name];
                });
            }

            restoreItineraryDays(d);
            restoreTourFaqs(d);

            var banner = document.getElementById('tourDraftBanner');
            if (banner) banner.classList.add('d-none');
            showRestoreNotice([]);
        }

        function restoreItineraryDays(d) {
            var container = document.getElementById('itineraryDaysContainer');
            if (!container) return;
            container.innerHTML = '';
            var n = (d.day_title || []).length;
            if (!n) { addItineraryDay({ day_number: 1 }); return; }
            for (var i = 0; i < n; i++) {
                addItineraryDay({
                    id: (d.day_id && d.day_id[i]) || 0,
                    day_number: (d.day_number && d.day_number[i]) || (i + 1),
                    title: (d.day_title && d.day_title[i]) || '',
                    description: (d.day_description && d.day_description[i]) || '',
                    drive_time: (d.day_drive_time && d.day_drive_time[i]) || '',
                    meals: (d.day_meals && d.day_meals[i]) || '',
                    accommodation: (d.day_accommodation && d.day_accommodation[i]) || '',
                    location_name: (d.day_location_name && d.day_location_name[i]) || '',
                    lat: (d.day_lat && d.day_lat[i]) || '',
                    lng: (d.day_lng && d.day_lng[i]) || '',
                    alt: (d.day_alt && d.day_alt[i]) || '',
                    existing_image: (d.day_existing_image && d.day_existing_image[i]) || ''
                });
            }
            if (!container.children.length) addItineraryDay({ day_number: 1 });
            Array.prototype.forEach.call(container.querySelectorAll('.itinerary-day-row'), function (row, idx) {
                if (d.day_remove_image && d.day_remove_image[idx]) {
                    var cb = row.querySelector('input[type=checkbox]');
                    var hidden = row.querySelector('.itinerary-day-remove-value');
                    if (cb) cb.checked = true;
                    if (hidden) hidden.value = '1';
                }
            });
        }

        function restoreTourFaqs(d) {
            var n = (d.faq_question || []).length;
            var faqs = [];
            for (var i = 0; i < n; i++) {
                faqs.push({
                    id: (d.faq_id && d.faq_id[i]) || 0,
                    question: (d.faq_question && d.faq_question[i]) || '',
                    answer: (d.faq_answer && d.faq_answer[i]) || '',
                    category: (d.faq_category && d.faq_category[i]) || '',
                    status: (d.faq_status && d.faq_status[i]) || 'active'
                });
            }
            loadTourFaqs(faqs);
        }

        function discardTourDraft() {
            TOUR_DRAFT.clear();
            var banner = document.getElementById('tourDraftBanner');
            if (banner) banner.classList.add('d-none');
        }

        function saveTourDraftNow() {
            var ok = TOUR_DRAFT.save();
            var msg = document.getElementById('tourDraftSavedMsg');
            if (!msg) return;
            msg.textContent = ok ? 'Draft saved (just now)' : 'Could not save draft';
            msg.style.opacity = '1';
            clearTimeout(msg._t);
            msg._t = setTimeout(function () { msg.style.opacity = '0.4'; }, 4000);
        }

        var isTourModalActive = false;
        var draftSaveTimer = null;

        function scheduleDraftSave() {
            if (!isTourModalActive) return;
            if (draftSaveTimer) clearTimeout(draftSaveTimer);
            draftSaveTimer = setTimeout(function () { TOUR_DRAFT.save(); }, 1500);
        }

        function addTourFaq(f) {
            f = f || {};
            var container = document.getElementById('tourFaqsContainer');
            var wrap = document.createElement('div');
            wrap.className = 'tour-faq-row border rounded p-3 mb-3 bg-white';
            var esc = escapeAttr;
            var html = '';
            html += '<div class="d-flex justify-content-between align-items-center mb-2">';
            html += '<strong><i class="fas fa-question-circle"></i> FAQ</strong>';
            html += '<button type="button" class="btn btn-sm btn-outline-danger" title="Remove FAQ" onclick="this.closest(\'.tour-faq-row\').remove()"><i class="fas fa-trash"></i></button>';
            html += '</div>';
            html += '<input type="hidden" name="faq_id[]" value="' + esc(f.id || 0) + '">';
            html += '<div class="form-group"><label class="small">Question</label><input type="text" class="form-control" name="faq_question[]" value="' + esc(f.question || '') + '"></div>';
            html += '<div class="form-group mb-2"><label class="small">Answer</label><textarea class="form-control" name="faq_answer[]" rows="2">' + esc(f.answer || '') + '</textarea></div>';
            html += '<div class="form-row">';
            html += '<div class="col-md-6"><div class="form-group"><label class="small">Category</label><input type="text" class="form-control" name="faq_category[]" value="' + esc(f.category || '') + '" placeholder="optional"></div></div>';
            html += '<div class="col-md-6"><div class="form-group"><label class="small">Status</label><select class="form-control" name="faq_status[]">'
                + '<option value="active"' + (f.status === 'active' || !f.status ? ' selected' : '') + '>Active</option>'
                + '<option value="inactive"' + (f.status === 'inactive' ? ' selected' : '') + '>Inactive</option></select></div></div>';
            html += '</div>';
            wrap.innerHTML = html;
            container.appendChild(wrap);
        }

        function loadTourFaqs(faqs) {
            var container = document.getElementById('tourFaqsContainer');
            container.innerHTML = '';
            (faqs || []).forEach(function(f) { addTourFaq(f); });
        }

        function setOverviewPreview(i, src) {
            var img = document.getElementById('ovPrev' + i);
            var empty = document.getElementById('ovEmpty' + i);
            img.src = src;
            img.style.display = 'inline-block';
            if (empty) empty.style.display = 'none';
        }

        function setOverviewSlot(i, path) {
            var cur = document.getElementById('ovCur' + i);
            var rem = document.getElementById('ovRemove' + i);
            if (cur) cur.value = path || '';
            if (rem) rem.checked = false;
            if (path) {
                setOverviewPreview(i, '../' + path.replace(/^\//, ''));
            } else {
                var img = document.getElementById('ovPrev' + i);
                var empty = document.getElementById('ovEmpty' + i);
                if (img) img.style.display = 'none';
                if (empty) empty.style.display = '';
            }
        }

        function setOverviewImages(t) {
            for (var i = 1; i <= 3; i++) setOverviewSlot(i, t['overview_image_' + i] || '');
        }

        function resetOverviewImages() {
            for (var i = 1; i <= 3; i++) setOverviewSlot(i, '');
        }

        function editTour(t) {
            document.getElementById('tourAction').value = 'edit';
            document.getElementById('tourId').value = t.id;
            document.getElementById('tourTitle').value = t.title;
            document.getElementById('tourSlug').value = t.slug;
            document.getElementById('tourDuration').value = t.duration || '';
            document.getElementById('tourPrice').value = t.price || '';
            document.getElementById('tourRating').value = t.rating || 5;
            document.getElementById('tourCountry').value = t.country || '';
            document.getElementById('tourDest').value = t.destination_id || '';
            document.getElementById('tourGuests').value = t.max_guests || 10;
            document.getElementById('tourStatus').value = t.status || 'active';
            document.getElementById('tourDescription').value = t.description || '';
            document.getElementById('tourHighlights').value = listToComma(t.highlights);
            document.getElementById('tourIncludes').value = listToComma(t.includes);
            document.getElementById('tourExcludes').value = listToComma(t.excludes);
            document.getElementById('tourGallery').value = t.gallery || '';
            setOverviewImages(t);
            document.getElementById('tourMetaTitle').value = t.meta_title || '';
            document.getElementById('tourMetaDesc').value = t.meta_description || '';
            document.getElementById('tourMetaKeywords').value = t.meta_keywords || '';
            document.getElementById('tourNoRobots').value = t.no_robots || 0;
            setMainImages(t.image || '', t.hero_image || '');
            document.getElementById('tourModalTitle').textContent = 'Edit Tour';
            loadItineraryDays(t.days || []);
            loadTourFaqs(t.faqs || []);
            document.getElementById('legacyItineraryNote').style.display = (t.itinerary && (!t.days || !t.days.length)) ? 'block' : 'none';
            $('#tourModal').modal('show');
        }

        function addItineraryDay(day) {
            day = day || {};
            var container = document.getElementById('itineraryDaysContainer');
            var index = container.children.length;
            var wrap = document.createElement('div');
            wrap.className = 'itinerary-day-row border rounded p-3 mb-3 bg-white';
            wrap.setAttribute('data-index', index);
            wrap.setAttribute('data-day-idx', (window.__itDaySeq = (window.__itDaySeq || 0) + 1));

            var dayNo = day.day_number ? day.day_number : (index + 1);
            var title = day.title || '';
            var desc = day.description || '';
            var drive = day.drive_time || '';
            var meals = day.meals || '';
            var accommodation = day.accommodation || '';
            var alt = day.alt || day.image_alt || '';
            var existing = day.image_path || day.existing_image || '';
            var dayId = day.id || 0;
            var locationName = day.location_name || '';
            var lat = day.lat != null && day.lat !== '' ? day.lat : '';
            var lng = day.lng != null && day.lng !== '' ? day.lng : '';
            var idxKey = wrap.getAttribute('data-day-idx');

            var esc = function(v, m) {
                if (typeof v !== 'string') v = String(v == null ? '' : v);
                var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
                return (m ? v : v.replace(/[&<>"']/g, function(c) { return map[c]; }));
            };

            var html = '';
            html += '<input type="hidden" name="day_id[]" value="' + esc(dayId) + '">';
            html += '<div class="d-flex justify-content-between mb-2">';
            html += '<strong class="itinerary-day-label">Day ' + esc(dayNo) + '</strong>';
            html += '<div class="btn-group btn-group-sm">';
            html += '<button type="button" class="btn btn-outline-secondary" title="Move up" onclick="moveDay(this, -1)"><i class="fas fa-arrow-up"></i></button>';
            html += '<button type="button" class="btn btn-outline-secondary" title="Move down" onclick="moveDay(this, 1)"><i class="fas fa-arrow-down"></i></button>';
            html += '<button type="button" class="btn btn-outline-danger" title="Remove day" onclick="removeDay(this)"><i class="fas fa-trash"></i></button>';
            html += '</div></div>';

            html += '<div class="form-row">';
            html += '<div class="col-md-2"><div class="form-group"><label>Day #</label><input type="number" class="form-control itinerary-day-number" name="day_number[]" value="' + esc(dayNo) + '" min="1"></div></div>';
            html += '<div class="col-md-10"><div class="form-group"><label>Title</label><input type="text" class="form-control" name="day_title[]" value="' + esc(title) + '" placeholder="e.g. Day 1: Arusha - Tarangire National Park"></div></div>';
            html += '</div>';

            html += '<div class="form-group"><label>Description</label><textarea class="form-control" name="day_description[]" rows="3">' + esc(desc) + '</textarea></div>';

            html += '<div class="form-row">';
            html += '<div class="col-md-4"><div class="form-group"><label>Drive Time</label><input type="text" class="form-control" name="day_drive_time[]" value="' + esc(drive) + '" placeholder="e.g. ~2.5 hrs"></div></div>';
            html += '<div class="col-md-4"><div class="form-group"><label>Meals</label><input type="text" class="form-control" name="day_meals[]" value="' + esc(meals) + '" placeholder="e.g. L, D"></div></div>';
            html += '<div class="col-md-4"><div class="form-group"><label>Accommodation</label><input type="text" class="form-control" name="day_accommodation[]" value="' + esc(accommodation) + '" placeholder="e.g. Serengeti Lodge"></div></div>';
            html += '</div>';

            html += '<div class="form-row">';
            html += '<div class="col-md-12"><div class="form-group"><label>Map Location <small class="text-muted">(optional)</small></label></div></div>';
            html += '<div class="col-md-12">';
            html += '<div class="input-group input-group-sm">';
            html += '<input type="text" class="form-control loc-search-input" data-day-idx="' + idxKey + '" placeholder="e.g. Serengeti National Park" value="' + esc(locationName) + '">';
            html += '<div class="input-group-append"><button type="button" class="btn btn-outline-primary loc-search-btn" data-day-idx="' + idxKey + '">Search</button></div>';
            html += '</div>';
            html += '<div class="loc-results small mt-1" data-day-idx="' + idxKey + '" style="max-height:140px;overflow-y:auto;"></div>';
            html += '</div>';
            html += '</div>';

            html += '<div class="itinerary-location-map-wrapper" data-day-idx="' + idxKey + '" style="display:none;">';
            html += '<div class="itinerary-location-map" data-location-map data-day-idx="' + idxKey + '"></div>';
            html += '</div>';

            html += '<div class="loc-summary small text-success mt-1 d-none" data-day-idx="' + idxKey + '">';
            html += '<span class="loc-summary-text"></span> ';
            html += '<button type="button" class="btn btn-sm btn-outline-danger ms-2 loc-clear-btn" data-day-idx="' + idxKey + '">Clear Location</button>';
            html += '</div>';

            html += '<input type="hidden" name="day_location_name[]" class="loc-field-location_name" value="' + esc(locationName) + '">';
            html += '<input type="hidden" name="day_lat[]" class="loc-field-lat" value="' + esc(lat) + '">';
            html += '<input type="hidden" name="day_lng[]" class="loc-field-lng" value="' + esc(lng) + '">';

            html += '<div class="form-row align-items-end">';
            html += '<div class="col-md-8"><div class="form-group"><label>Image <small class="text-muted">from gallery</small></label>';
            html += '<button type="button" class="btn btn-sm btn-outline-secondary btn-block text-left" onclick="openDayGalleryPicker(this)"><i class="fas fa-images mr-1"></i> <span class="itinerary-day-gallery-label">' + (existing ? 'Change image' : 'Choose from gallery') + '</span></button>';
            html += '<small class="text-muted d-block">Pick a gallery photo — shown as thumbnail previews grouped by category.</small>';
            html += '</div></div>';
            html += '<div class="col-md-4"><div class="form-group"><label>Image Alt Text</label><input type="text" class="form-control itinerary-day-alt" name="day_alt[]" value="' + esc(alt) + '"></div></div>';
            html += '</div>';

            html += '<input type="hidden" class="itinerary-day-existing" name="day_existing_image[]" value="' + esc(existing) + '">';
            html += '<input type="hidden" name="day_remove_image[]" class="itinerary-day-remove-value" value="0">';

            html += '<div class="itinerary-day-preview" style="display:none;"></div>';

            if (existing) {
                html += '<div class="mt-2 d-flex align-items-center itinerary-day-existing-block">';
                html += '<img src="../' + esc(existing) + '" alt="" style="width:80px;height:60px;object-fit:cover;border-radius:4px;margin-right:10px;">';
                html += '<div class="form-check">';
                html += '<input class="form-check-input" type="checkbox" onchange="this.closest(\'.itinerary-day-row\').querySelector(\'.itinerary-day-remove-value\').value = this.checked ? 1 : 0" ' + (existing && false ? 'checked' : '') + '>';
                html += '<label class="form-check-label small">Remove current image</label>';
                html += '</div></div>';
            }

            wrap.innerHTML = html;
            container.appendChild(wrap);
            renumberDays();
        }

        function removeDay(btn) {
            var wrap = btn.closest('.itinerary-day-row');
            if (wrap) wrap.remove();
            renumberDays();
        }

        function moveDay(btn, dir) {
            var wrap = btn.closest('.itinerary-day-row');
            if (!wrap) return;
            var container = wrap.parentNode;
            var rows = Array.prototype.slice.call(container.children);
            var idx = rows.indexOf(wrap);
            var target = idx + dir;
            if (target < 0 || target >= rows.length) return;
            if (dir < 0) { container.insertBefore(wrap, rows[target]); }
            else { container.insertBefore(rows[target], wrap); }
            renumberDays();
        }

        function renumberDays() {
            var container = document.getElementById('itineraryDaysContainer');
            Array.prototype.forEach.call(container.children, function(row) {
                var num = row.querySelector('.itinerary-day-number');
                var label = row.querySelector('.itinerary-day-label');
                if (label && num) label.textContent = 'Day ' + num.value;
            });
        }

        function openDayGalleryPicker(btn) {
            if (!btn) return;
            openGalleryPicker({ type: 'day', row: btn.closest('.itinerary-day-row') });
        }

        function setDayGalleryImage(row, path) {
            if (!row) return;
            var existingHidden = row.querySelector('.itinerary-day-existing');
            var removeVal = row.querySelector('.itinerary-day-remove-value');
            var block = row.querySelector('.itinerary-day-existing-block');
            var removeCheckbox = block ? block.querySelector('input[type=checkbox]') : null;
            var preview = row.querySelector('.itinerary-day-preview');
            var label = row.querySelector('.itinerary-day-gallery-label');
            if (removeVal) removeVal.value = '0';
            if (removeCheckbox) removeCheckbox.checked = false;
            if (existingHidden) existingHidden.value = path || '';
            if (label) label.textContent = path ? 'Change image' : 'Choose from gallery';
            if (block) {
                if (path) {
                    var img = block.querySelector('img');
                    if (img) img.src = '../' + path.replace(/^\//, '');
                    block.style.display = '';
                } else {
                    block.style.display = 'none';
                }
            } else if (preview) {
                if (path) {
                    preview.style.display = 'block';
                    preview.innerHTML = '<img src="../' + path.replace(/^\//, '') + '" alt="Preview" style="max-width:180px;max-height:120px;object-fit:cover;border-radius:4px;margin-top:8px;">';
                } else {
                    preview.style.display = 'none';
                    preview.innerHTML = '';
                }
            }
        }

        function loadItineraryDays(days) {
            var container = document.getElementById('itineraryDaysContainer');
            container.innerHTML = '';
            if (!days || !days.length) {
                addItineraryDay({ day_number: 1 });
                return;
            }
            days.forEach(function(d) { addItineraryDay(d); });
        }

        (function() {
            var tourForm = document.querySelector('#tourModal form');
            $('#tourModal').on('show.bs.modal', function() {
                isTourModalActive = true;
                if (document.getElementById('tourAction').value === 'add') {
                    var container = document.getElementById('itineraryDaysContainer');
                    container.innerHTML = '';
                    addItineraryDay({ day_number: 1 });
                    document.getElementById('legacyItineraryNote').style.display = 'none';
                    document.getElementById('tourHighlights').value = '';
                    document.getElementById('tourIncludes').value = '';
                    document.getElementById('tourExcludes').value = '';
                    loadTourFaqs([]);
                    resetOverviewImages();
                    resetMainImages();
                } else {
                    // Editing: (re)create preview maps for days that already
                    // have coordinates set.
                    setTimeout(initDayLocationMaps, 350);
                }
                updateDraftBanner();
            });
            $('#tourModal').on('hidden.bs.modal', function() {
                isTourModalActive = false;
                if (draftSaveTimer) clearTimeout(draftSaveTimer);
                TOUR_DRAFT.save();
            });
            if (tourForm) {
                tourForm.addEventListener('input', scheduleDraftSave);
                tourForm.addEventListener('change', scheduleDraftSave);
            }
            window.addEventListener('beforeunload', function() {
                if (isTourModalActive) TOUR_DRAFT.save();
            });
            if (TOUR_DRAFTS_CLEARED) TOUR_DRAFT.clearAll();
        })();
    </script>
    <script>
        /* ── Itinerary day location picker (Leaflet / OpenStreetMap) ──
           Mirrors htdocs/tour: type a place, click Search, choose a result, and
           the day's map preview appears with a numbered draggable marker. The
           chosen name + lat/lng are stored in hidden fields saved with the form.
           Existing days re-show their saved marker when the modal opens.      */
        window._locMaps = window._locMaps || {};
        window._locMarkers = window._locMarkers || {};

        function createItineraryMarkerIcon(dayNumber) {
            return L.divIcon({
                className: 'itinerary-map-marker-wrapper',
                html: '<div class="itinerary-map-marker"><span>' + dayNumber + '</span></div>',
                iconSize: [36, 46],
                iconAnchor: [18, 46],
                popupAnchor: [0, -44]
            });
        }

        function _showLocationMap(wrapper) {
            if (!wrapper) return;
            wrapper.style.display = 'block';
        }

        function _initLocMap(container) {
            var idx = container.getAttribute('data-day-idx');
            if (!idx || window._locMaps[idx]) return;
            var map = L.map(container, { scrollWheelZoom: false, zoomControl: true }).setView([-6.3690, 34.8888], 6);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);
            window._locMaps[idx] = map;
            requestAnimationFrame(function () { map.invalidateSize(true); });
            map.on('click', function (e) {
                var dayNo = _dayNumberFor(idx);
                _setMapPoint(idx, dayNo, e.latlng.lat, e.latlng.lng, 'Selected map location');
            });
        }

        function _dayNumberFor(idx) {
            var row = document.querySelector('.itinerary-day-row[data-day-idx="' + idx + '"]');
            if (row) {
                var n = row.querySelector('.itinerary-day-number');
                if (n && n.value) return n.value;
            }
            return 1;
        }

        function _setMapPoint(idx, dayNumber, lat, lng, name) {
            var map = window._locMaps[idx];
            if (!map) return;
            if (window._locMarkers[idx]) map.removeLayer(window._locMarkers[idx]);
            var marker = L.marker([lat, lng], { draggable: true, icon: createItineraryMarkerIcon(dayNumber) }).addTo(map);
            window._locMarkers[idx] = marker;
            marker.bindPopup(name || 'Selected map location').openPopup();
            map.setView([lat, lng], Math.max(map.getZoom(), 11));
            requestAnimationFrame(function () { map.invalidateSize(true); });

            var row = document.querySelector('.itinerary-day-row[data-day-idx="' + idx + '"]');
            if (row) {
                row.querySelector('.loc-field-lat').value = lat.toFixed(7);
                row.querySelector('.loc-field-lng').value = lng.toFixed(7);
                row.querySelector('.loc-field-location_name').value = name || 'Selected map location';
                var summary = row.querySelector('.loc-summary');
                summary.classList.remove('d-none');
                summary.querySelector('.loc-summary-text').textContent = 'Selected: ' + (name || 'Selected map location');
            }
            marker.on('dragend', function () {
                var pos = marker.getLatLng();
                _setMapPoint(idx, dayNumber, pos.lat, pos.lng, name || 'Selected map location');
            });
        }

        /* ── Search (OpenStreetMap Nominatim, browser-side) ────────── */
        document.addEventListener('click', function (e) {
            var btn = e.target.closest ? e.target.closest('.loc-search-btn') : null;
            if (btn) { e.preventDefault(); runLocationSearch(btn.getAttribute('data-day-idx')); return; }

            var result = e.target.closest ? e.target.closest('.loc-result-item') : null;
            if (result) { pickLocationResult(result); return; }

            var clear = e.target.closest ? e.target.closest('.loc-clear-btn') : null;
            if (clear) { clearLocation(clear.getAttribute('data-day-idx')); }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && e.target.classList && e.target.classList.contains('loc-search-input')) {
                e.preventDefault();
                runLocationSearch(e.target.getAttribute('data-day-idx'));
            }
        });

        function runLocationSearch(idx) {
            var input = document.querySelector('.loc-search-input[data-day-idx="' + idx + '"]');
            var query = (input ? input.value : '').trim();
            if (query.length < 3) return;
            var results = document.querySelector('.loc-results[data-day-idx="' + idx + '"]');
            if (results) results.innerHTML = '<span class="text-muted">Searching…</span>';
            if (typeof window._locFetchCancel === 'function') window._locFetchCancel();

            var ctrl = new AbortController();
            window._locFetchCancel = function () { ctrl.abort(); };
            fetch('https://nominatim.openstreetmap.org/search?format=json&limit=6&q=' + encodeURIComponent(query), {
                headers: { 'Accept': 'application/json' },
                signal: ctrl.signal
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                window._locFetchCancel = null;
                if (!results) return;
                if (!data || !data.length) {
                    results.innerHTML = '<span class="text-muted">No matching locations found</span>';
                    return;
                }
                results.innerHTML = data.map(function (r) {
                    return '<div class="loc-result-item p-1 px-2 rounded mb-1" data-day-idx="' + idx +
                        '" data-lat="' + r.lat + '" data-lng="' + r.lon + '" data-name="' + String(r.display_name || '').replace(/"/g, '&quot;') + '">' +
                        String(r.display_name || '') + '</div>';
                }).join('');
            })
            .catch(function () {
                window._locFetchCancel = null;
                if (results) results.innerHTML = '<span class="text-muted">Unable to search locations right now</span>';
            });
        }

        function pickLocationResult(el) {
            var idx = el.getAttribute('data-day-idx');
            var lat = parseFloat(el.getAttribute('data-lat'));
            var lng = parseFloat(el.getAttribute('data-lng'));
            var name = el.getAttribute('data-name');
            var wrapper = document.querySelector('.itinerary-location-map-wrapper[data-day-idx="' + idx + '"]');
            var container = document.querySelector('.itinerary-location-map[data-day-idx="' + idx + '"]');
            _showLocationMap(wrapper);
            if (container && !window._locMaps[idx]) _initLocMap(container);
            var dayNo = _dayNumberFor(idx);
            setTimeout(function () { _setMapPoint(idx, dayNo, lat, lng, name); }, 60);
            var results = document.querySelector('.loc-results[data-day-idx="' + idx + '"]');
            if (results) results.innerHTML = '';
        }

        function clearLocation(idx) {
            var row = document.querySelector('.itinerary-day-row[data-day-idx="' + idx + '"]');
            if (row) {
                row.querySelector('.loc-field-lat').value = '';
                row.querySelector('.loc-field-lng').value = '';
                row.querySelector('.loc-field-location_name').value = '';
                var summary = row.querySelector('.loc-summary');
                summary.classList.add('d-none');
                summary.querySelector('.loc-summary-text').textContent = '';
            }
            if (window._locMarkers[idx] && window._locMaps[idx]) {
                window._locMaps[idx].removeLayer(window._locMarkers[idx]);
                delete window._locMarkers[idx];
            }
            if (window._locMaps[idx]) {
                window._locMaps[idx].setView([-6.3690, 34.8888], 6);
                requestAnimationFrame(function () { window._locMaps[idx].invalidateSize(true); });
            }
        }

        function initDayLocationMaps() {
            if (typeof L === 'undefined') return;
            var rows = document.querySelectorAll('.itinerary-day-row');
            Array.prototype.forEach.call(rows, function (row) {
                var idx = row.getAttribute('data-day-idx');
                var latVal = (row.querySelector('.loc-field-lat') || {}).value;
                var lngVal = (row.querySelector('.loc-field-lng') || {}).value;
                var nameVal = (row.querySelector('.loc-field-location_name') || {}).value;
                var wrapper = row.querySelector('.itinerary-location-map-wrapper');
                var container = row.querySelector('.itinerary-location-map');
                if (latVal && lngVal && wrapper && container) {
                    _showLocationMap(wrapper);
                    if (!window._locMaps[idx]) _initLocMap(container);
                    var dayNo = _dayNumberFor(idx);
                    setTimeout(function () { _setMapPoint(idx, dayNo, parseFloat(latVal), parseFloat(lngVal), nameVal); }, 60);
                }
            });
        }

        window.addEventListener('resize', function () {
            Object.keys(window._locMaps).forEach(function (idx) {
                var map = window._locMaps[idx];
                if (map) requestAnimationFrame(function () { map.invalidateSize(true); });
            });
        });

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () { setTimeout(initDayLocationMaps, 500); });
        } else {
            setTimeout(initDayLocationMaps, 500);
        }
    </script>
    <script>
        function filterTable(val) {
            var rows = document.querySelectorAll('#dataTable tbody tr');
            rows.forEach(function(row) { row.style.display = row.textContent.toLowerCase().indexOf(val.toLowerCase()) > -1 ? '' : 'none'; });
        }
    </script>
</body>
</html>
