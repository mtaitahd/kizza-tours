<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: ./');
    exit;
}

$db = db();

if (empty($_SESSION['admin_image']) && isset($_SESSION['admin_id'])) {
    $row = $db->fetchOne("SELECT profile_image FROM admin_users WHERE id = ?", [$_SESSION['admin_id']]);
    $_SESSION['admin_image'] = $row['profile_image'] ?? null;
}

// Count live sitemap pages
$sitemapPageCount = 0;
try {
    $tours = $db->fetchAll("SELECT COUNT(*) as cnt FROM tour_packages WHERE status = 'active' AND slug IS NOT NULL AND slug != ''");
    $dests = $db->fetchAll("SELECT COUNT(*) as cnt FROM destinations WHERE status = 'active' AND slug IS NOT NULL AND slug != ''");
    $pages = $db->fetchAll("SELECT COUNT(*) as cnt FROM pages WHERE status = 'active' AND slug IS NOT NULL AND slug != ''");
    $sitemapPageCount = ($tours[0]['cnt'] ?? 0) + ($dests[0]['cnt'] ?? 0) + ($pages[0]['cnt'] ?? 0) + 11; // +11 static pages
} catch (Exception $e) {
    $sitemapPageCount = '~30'; // fallback
}

// Regenerate static sitemap.xml (optional, for backward compat)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'regenerate_cache') {
        $ok = seoGenerateSitemap();
        $_SESSION['flash'] = ['type' => $ok ? 'success' : 'danger', 'message' => $ok ? 'Static sitemap.xml regenerated' : 'Regeneration failed'];
    }
    header('Location: sitemap');
    exit;
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$sitemapUrl = SITE_URL . '/sitemap.xml';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sitemap - Kizza Tours Admin</title>
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
        @media (max-width: 768px) { #accordionSidebar { width: 0; } #content-wrapper { margin-left: 0; } .topbar { left: 0; } }
        .sitemap-status { font-size: 1.1rem; }
        .sitemap-preview { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 20px; max-height: 400px; overflow-y: auto; font-family: monospace; font-size: 0.85rem; white-space: pre-wrap; word-break: break-all; }
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
        <li class="nav-item"><a class="nav-link" href="tours"><i class="fas fa-fw fa-safari"></i><span>Tours</span></a></li>
        <li class="nav-item"><a class="nav-link" href="destinations"><i class="fas fa-fw fa-map-marker-alt"></i><span>Destinations</span></a></li>
        <li class="nav-item"><a class="nav-link" href="gallery"><i class="fas fa-fw fa-images"></i><span>Gallery</span></a></li>
        <li class="nav-item"><a class="nav-link" href="testimonials"><i class="fas fa-fw fa-star"></i><span>Testimonials</span></a></li>
        <li class="nav-item"><a class="nav-link" href="inquiries"><i class="fas fa-fw fa-envelope"></i><span>Inquiries</span></a></li>
        <li class="nav-item"><a class="nav-link" href="quotes"><i class="fas fa-fw fa-file-invoice"></i><span>Quotes</span></a></li>
        <li class="nav-item"><a class="nav-link" href="pages"><i class="fas fa-fw fa-file-alt"></i><span>Pages</span></a></li>
        <hr class="sidebar-divider">
        <div class="sidebar-heading">Tools</div>
        <li class="nav-item"><a class="nav-link" href="compress-images"><i class="fas fa-fw fa-compress-alt"></i><span>Compress Images</span></a></li>
        <li class="nav-item active"><a class="nav-link" href="sitemap"><i class="fas fa-fw fa-sitemap"></i><span>Sitemap</span></a></li>
        <hr class="sidebar-divider">
        <div class="sidebar-heading">Account</div>
        <li class="nav-item"><a class="nav-link" href="profile"><i class="fas fa-fw fa-user"></i><span>My Profile</span></a></li>
        <hr class="sidebar-divider">
        <div class="sidebar-heading">System</div>
        <li class="nav-item"><a class="nav-link" href="settings"><i class="fas fa-fw fa-cog"></i><span>Settings</span></a></li>
        <li class="nav-item"><a class="nav-link" href="logout"><i class="fas fa-fw fa-sign-out-alt"></i><span>Logout</span></a></li>
    </ul>

    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            <nav class="navbar navbar-expand navbar-light bg-navbar topbar mb-4 static-top" style="background-color: #0A2540;">
                <button id="sidebarToggleTop" class="btn btn-link rounded-circle mr-3"><i class="fa fa-bars text-white"></i></button>
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item dropdown no-arrow">
                        <a class="nav-link" href="profile"><i class="fas fa-user-circle text-white"></i><span class="ml-2 text-white"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></span></a>
                    </li>
                </ul>
            </nav>

            <div class="container-fluid" id="container-wrapper">
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-sitemap mr-2"></i>Sitemap</h1>
                    <div>
                        <a href="<?= htmlspecialchars($sitemapUrl) ?>" target="_blank" class="btn btn-success btn-sm"><i class="fas fa-external-link-alt mr-1"></i> View Live Sitemap</a>
                        <form method="POST" action="" class="d-inline">
                            <input type="hidden" name="action" value="regenerate_cache">
                            <button type="submit" class="btn btn-outline-primary btn-sm ml-1"><i class="fas fa-sync mr-1"></i> Regenerate Static Cache</button>
                        </form>
                    </div>
                </div>

                <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : ($flash['type'] === 'warning' ? 'warning' : 'danger') ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'warning' ? 'exclamation-triangle' : 'exclamation-circle') ?> mr-2"></i><?= htmlspecialchars($flash['message']) ?>
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold">Sitemap Status</h6>
                            </div>
                            <div class="card-body">
                                <div class="sitemap-status">
                                    <p class="text-success"><i class="fas fa-check-circle mr-2"></i>Live sitemap is <strong>active</strong> and always up-to-date.</p>
                                    <p><i class="fas fa-link mr-2"></i>URL: <a href="<?= htmlspecialchars($sitemapUrl) ?>" target="_blank"><code><?= htmlspecialchars($sitemapUrl) ?></code></a></p>
                                    <p><i class="fas fa-list mr-2"></i>Discovered pages: <strong><?= $sitemapPageCount ?></strong></p>
                                    <p><i class="fas fa-info-circle mr-2 text-primary"></i> The sitemap is generated dynamically on each request. New tours, destinations, and pages appear automatically.</p>
                                    <hr>
                                    <p class="mb-0 text-muted small"><i class="fas fa-lightbulb mr-1"></i> Submit the sitemap URL to Google Search Console for better indexing.</p>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-4">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold">Live Sitemap Preview</h6>
                                <a href="<?= htmlspecialchars($sitemapUrl) ?>" target="_blank" class="btn btn-outline-primary btn-sm"><i class="fas fa-external-link-alt mr-1"></i> Open Raw</a>
                            </div>
                            <div class="card-body">
                                <?php
                                $sitemapContent = @file_get_contents($sitemapUrl);
                                if ($sitemapContent !== false):
                                ?>
                                    <div class="sitemap-preview"><?= htmlspecialchars($sitemapContent) ?></div>
                                <?php else: ?>
                                    <p class="text-danger text-center my-4"><i class="fas fa-exclamation-triangle mr-2"></i>Could not fetch live sitemap. Check server configuration.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold">Info</h6>
                            </div>
                            <div class="card-body">
                                <p><i class="fas fa-info-circle mr-2 text-primary"></i> The live sitemap includes:</p>
                                <ul class="list-unstyled ml-4">
                                    <li><i class="fas fa-globe mr-2 text-success"></i> 11 Static pages</li>
                                    <li><i class="fas fa-safari mr-2 text-success"></i> Active tours</li>
                                    <li><i class="fas fa-map-marker-alt mr-2 text-success"></i> Active destinations</li>
                                    <li><i class="fas fa-file-alt mr-2 text-success"></i> Custom pages</li>
                                </ul>
                                <hr>
                                <p class="mb-0 text-muted small"><i class="fas fa-lightbulb mr-1"></i> The sitemap updates automatically when new content is added. No manual generation needed.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <footer class="sticky-footer bg-white"><div class="container my-auto"><div class="copyright text-center my-auto"><span>&copy; <?= date('Y') ?> Kizza Tours & Safaris</span></div></div></footer>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/js/bootstrap.bundle.min.js"></script>
<script src="../templates/assets/js/ruang-admin.min.js"></script>
</body>
</html>
