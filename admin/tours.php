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
        return true;
    } catch (\Throwable $e) { return false; }
}

function ensureItineraryDaysTable() {
    try {
        $db = db();
        $tables = $db->fetchAll("SHOW TABLES");
        foreach ($tables as $row) {
            if (in_array('itinerary_days', array_values($row))) return true;
        }
        $db->query("CREATE TABLE IF NOT EXISTS itinerary_days (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tour_id INT NOT NULL,
            day_number INT NOT NULL DEFAULT 1,
            title VARCHAR(255) NOT NULL,
            description TEXT,
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

require_once __DIR__ . '/../includes/itinerary-days-save.php';

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
        $highlights = trim($_POST['highlights'] ?? '');
        $includes = trim($_POST['includes'] ?? '');
        $excludes = trim($_POST['excludes'] ?? '');
        $gallery = trim($_POST['gallery'] ?? '');
        $status = trim($_POST['status'] ?? 'active');
        $meta_title = trim($_POST['meta_title'] ?? '');
        $meta_description = trim($_POST['meta_description'] ?? '');
        $meta_keywords = trim($_POST['meta_keywords'] ?? '');
        $no_robots = intval($_POST['no_robots'] ?? 0);

        $image = '';
        $hasNewImage = false;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploaded = uploadFile($_FILES['image'], BASE_PATH . 'uploads/tours/', 'tour_' . $slug);
            if ($uploaded) {
                $image = $uploaded;
                $hasNewImage = true;
            }
        }

        $heroImage = '';
        $hasNewHero = false;
        if (isset($_FILES['hero_image']) && $_FILES['hero_image']['error'] === UPLOAD_ERR_OK) {
            $uploaded = uploadFile($_FILES['hero_image'], BASE_PATH . 'uploads/tours/', 'hero_' . $slug);
            if ($uploaded) {
                $heroImage = $uploaded;
                $hasNewHero = true;
            }
        }
        
        // ---- Structured itinerary days ----
        $dayIds = array_map('intval', $_POST['day_id'] ?? []);
        $dayNumbers = $_POST['day_number'] ?? [];
        $dayTitles = $_POST['day_title'] ?? [];
        $dayDescs = $_POST['day_description'] ?? [];
        $dayAlts = $_POST['day_alt'] ?? [];
        $dayExistingImgs = $_POST['day_existing_image'] ?? [];
        $dayRemoveImgs = $_POST['day_remove_image'] ?? [];
        $dayFiles = $_FILES['day_image'] ?? null;

        $submittedDays = [];
        for ($i = 0; $i < count($dayTitles); $i++) {
            $submittedDays[] = [
                'day_id'        => intval($dayIds[$i] ?? 0),
                'day_number'    => isset($dayNumbers[$i]) ? intval($dayNumbers[$i]) : ($i + 1),
                'title'         => trim($dayTitles[$i] ?? ''),
                'description'   => trim($dayDescs[$i] ?? ''),
                'alt'           => trim($dayAlts[$i] ?? ''),
                'existing_image'=> trim($dayExistingImgs[$i] ?? ''),
                'remove_image'  => !empty($dayRemoveImgs[$i]),
                'new_image'     => ($dayFiles && $dayFiles['error'][$i] === UPLOAD_ERR_OK) ? [
                                    'tmp_name' => $dayFiles['tmp_name'][$i],
                                    'name'     => $dayFiles['name'][$i],
                                    'size'     => $dayFiles['size'][$i],
                                    'error'    => $dayFiles['error'][$i],
                                  ] : null,
            ];
        }

        // Upload new images now (track them so we can clean up if save fails).
        $uploadedNewImages = [];
        $dayImageErrors = [];
        foreach ($submittedDays as &$d) {
            $finalImage = null;
            if ($d['new_image']) {
                $ext = strtolower(pathinfo($d['new_image']['name'], PATHINFO_EXTENSION));
                $dayImgAllowed = ['jpg', 'jpeg', 'png', 'webp'];
                $isImage = in_array($ext, $dayImgAllowed, true)
                    && (getimagesize($d['new_image']['tmp_name']) !== false)
                    && $d['new_image']['size'] <= MAX_FILE_SIZE;
                if (!$isImage) {
                    $dayImageErrors[] = ($d['title'] !== '' ? $d['title'] : ('Day ' . $d['day_number']))
                        . ': image must be JPG, PNG or WebP and under ' . round(MAX_FILE_SIZE / 1048576) . ' MB.';
                    $finalImage = $d['remove_image'] ? null : $d['existing_image'];
                } else {
                    $up = uploadFile($d['new_image'], BASE_PATH . 'uploads/tours/', 'day_' . $slug);
                    if ($up) {
                        $finalImage = $up;
                        $uploadedNewImages[] = $up;
                    } else {
                        $finalImage = $d['remove_image'] ? null : $d['existing_image'];
                    }
                }
            } elseif ($d['remove_image']) {
                $finalImage = null;
            } else {
                $finalImage = $d['existing_image'];
            }
            $d['final_image'] = $finalImage;
        }
        unset($d);

        if ($action === 'add') {
            $itinerary = '';
            try {
                $newTourId = $db->insert(
                    "INSERT INTO tour_packages (title, slug, duration, price, country, destination_id, rating, max_guests, description, highlights, includes, excludes, gallery, itinerary, image, hero_image, status, meta_title, meta_description, meta_keywords, no_robots) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$title, $slug, $duration, $price, $country, $destination_id, $rating, $max_guests, $description, $highlights, $includes, $excludes, $gallery, $itinerary, $image, $heroImage, $status, $meta_title, $meta_description, $meta_keywords, $no_robots]
                );
                saveItineraryDays($newTourId, $submittedDays, $uploadedNewImages);
            } catch (\Throwable $e) {
                // Roll back the tour we just created and clean up new uploads.
                foreach ($uploadedNewImages as $p) deleteFile($p);
                try { $db->query("DELETE FROM tour_packages WHERE id = ?", [$newTourId]); } catch (\Throwable $ignore) {}
                error_log("Tour add error: " . $e->getMessage());
                $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Could not save the tour with its itinerary days.'];
                header('Location: tours');
                exit;
            }
            try { seoGenerateSitemap(); } catch (\Throwable $e) { error_log("Sitemap gen error: " . $e->getMessage()); }
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Tour added successfully'];
            if (!empty($dayImageErrors)) {
                $_SESSION['flash']['message'] .= ' Removed invalid image uploads: ' . implode(' ', $dayImageErrors);
            }
        } else {
            // Preserve existing legacy itinerary text (never overwrite old tours).
            $existingRow = $db->fetchOne("SELECT itinerary, image, hero_image FROM tour_packages WHERE id = ?", [$tourId]);
            $itinerary = $existingRow['itinerary'] ?? '';

            $sql = "UPDATE tour_packages SET title=?, slug=?, duration=?, price=?, country=?, destination_id=?, rating=?, max_guests=?, description=?, highlights=?, includes=?, excludes=?, gallery=?, itinerary=?, status=?, meta_title=?, meta_description=?, meta_keywords=?, no_robots=?";
            $params = [$title, $slug, $duration, $price, $country, $destination_id, $rating, $max_guests, $description, $highlights, $includes, $excludes, $gallery, $itinerary, $status, $meta_title, $meta_description, $meta_keywords, $no_robots];
            if ($hasNewImage) { $sql .= ", image=?"; $params[] = $image; }
            if ($hasNewHero) { $sql .= ", hero_image=?"; $params[] = $heroImage; }
            $sql .= " WHERE id=?";
            $params[] = $tourId;
            $db->query($sql, $params);

            // days: save submitted rows; helper cleans up replaced/removed images
            try {
                saveItineraryDays($tourId, $submittedDays, $uploadedNewImages);
            } catch (\Throwable $e) {
                error_log("Tour update day-save error: " . $e->getMessage());
                $_SESSION['flash'] = ['type' => 'danger', 'message' => 'The tour was updated, but its itinerary days could not be saved.'];
                header('Location: tours');
                exit;
            }

            // cleanup tour-level images after successful update
            if ($hasNewImage && !empty($existingRow['image'])) deleteFile($existingRow['image']);
            if ($hasNewHero && !empty($existingRow['hero_image'])) deleteFile($existingRow['hero_image']);

            try { seoGenerateSitemap(); } catch (\Throwable $e) { error_log("Sitemap gen error: " . $e->getMessage()); }
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Tour updated successfully'];
            if (!empty($dayImageErrors)) {
                $_SESSION['flash']['message'] .= ' Removed invalid image uploads: ' . implode(' ', $dayImageErrors);
            }
        }
    } elseif ($action === 'delete') {
        $tourId = intval($_POST['tour_id'] ?? 0);
        $tour = $db->fetchOne("SELECT image, hero_image FROM tour_packages WHERE id = ?", [$tourId]);
        if ($tour && $tour['image']) deleteFile($tour['image']);
        if ($tour && $tour['hero_image']) deleteFile($tour['hero_image']);
        foreach ($db->fetchAll("SELECT image_path FROM itinerary_days WHERE tour_id = ?", [$tourId]) as $day) {
            if (!empty($day['image_path'])) deleteFile($day['image_path']);
        }
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
$allDays = $db->fetchAll("SELECT id, tour_id, day_number, title, description, image_path, image_alt FROM itinerary_days ORDER BY tour_id ASC, sort_order ASC, id ASC");
$daysByTour = [];
foreach ($allDays as $day) {
    $daysByTour[$day['tour_id']][] = $day;
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
                        <button class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#tourModal">
                            <i class="fas fa-plus"></i> Add Tour
                        </button>
                    </div>
                    
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
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tourModalTitle">Add Tour</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <?php csrf_field(); ?>
                    <div class="modal-body">
                        <input type="hidden" name="action" id="tourAction" value="add">
                        <input type="hidden" name="tour_id" id="tourId" value="0">
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
                                <div class="form-group">
                                    <label>Image</label>
                                    <input type="file" class="form-control-file" name="image" accept="image/*">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Hero Image <small class="text-muted">(full-width banner)</small></label>
                                    <input type="file" class="form-control-file" name="hero_image" accept="image/*">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea class="form-control" name="description" id="tourDescription" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Highlights (comma separated)</label>
                                    <textarea class="form-control" name="highlights" id="tourHighlights" rows="2"></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Includes (comma separated)</label>
                                    <textarea class="form-control" name="includes" id="tourIncludes" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Excludes (comma separated)</label>
                                    <textarea class="form-control" name="excludes" id="tourExcludes" rows="2"></textarea>
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
                        <hr>
                        <h6 class="font-weight-bold"><i class="fas fa-search mr-1"></i> SEO & Meta</h6>
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
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-outline-secondary">Save Tour</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-easing/1.4.1/jquery.easing.min.js"></script>
    <script src="../templates/assets/js/ruang-admin.min.js"></script>
    <script>
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
            document.getElementById('tourHighlights').value = t.highlights || '';
            document.getElementById('tourIncludes').value = t.includes || '';
            document.getElementById('tourExcludes').value = t.excludes || '';
            document.getElementById('tourGallery').value = t.gallery || '';
            document.getElementById('tourMetaTitle').value = t.meta_title || '';
            document.getElementById('tourMetaDesc').value = t.meta_description || '';
            document.getElementById('tourMetaKeywords').value = t.meta_keywords || '';
            document.getElementById('tourNoRobots').value = t.no_robots || 0;
            document.getElementById('tourModalTitle').textContent = 'Edit Tour';
            loadItineraryDays(t.days || []);
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

            var dayNo = day.day_number ? day.day_number : (index + 1);
            var title = day.title || '';
            var desc = day.description || '';
            var alt = day.alt || day.image_alt || '';
            var existing = day.image_path || day.existing_image || '';
            var dayId = day.id || 0;

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

            html += '<div class="form-row align-items-end">';
            html += '<div class="col-md-6"><div class="form-group"><label>Image</label><input type="file" class="form-control-file itinerary-day-file" name="day_image[]" accept="image/*" onchange="previewDayImage(this)"></div></div>';
            html += '<div class="col-md-6"><div class="form-group"><label>Image Alt Text</label><input type="text" class="form-control" name="day_alt[]" value="' + esc(alt) + '"></div></div>';
            html += '</div>';

            html += '<input type="hidden" class="itinerary-day-existing" name="day_existing_image[]" value="' + esc(existing) + '">';
            html += '<input type="hidden" name="day_remove_image[]" class="itinerary-day-remove-value" value="0">';

            html += '<div class="itinerary-day-preview" style="display:none;"></div>';

            if (existing) {
                html += '<div class="mt-2 d-flex align-items-center">';
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

        function previewDayImage(input) {
            var row = input.closest('.itinerary-day-row');
            if (!row) return;
            var preview = row.querySelector('.itinerary-day-preview');
            preview.innerHTML = '';
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    preview.style.display = 'block';
                    preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview" style="max-width:180px;max-height:120px;object-fit:cover;border-radius:4px;margin-top:8px;">';
                };
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
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
            $('#tourModal').on('show.bs.modal', function() {
                if (document.getElementById('tourAction').value === 'add') {
                    var container = document.getElementById('itineraryDaysContainer');
                    container.innerHTML = '';
                    addItineraryDay({ day_number: 1 });
                    document.getElementById('legacyItineraryNote').style.display = 'none';
                }
            });
        })();
    </script>
    <script>
        function filterTable(val) {
            var rows = document.querySelectorAll('#dataTable tbody tr');
            rows.forEach(function(row) { row.style.display = row.textContent.toLowerCase().indexOf(val.toLowerCase()) > -1 ? '' : 'none'; });
        }
    </script>
</body>
</html>
