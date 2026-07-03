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

$q = trim($_GET['q'] ?? '');
$results = ['bookings' => [], 'tours' => [], 'destinations' => [], 'inquiries' => []];
$totalResults = 0;

if ($q !== '') {
    $searchTerm = "%$q%";

    // Search bookings
    $results['bookings'] = $db->fetchAll(
        "SELECT * FROM bookings WHERE booking_reference LIKE ? OR full_name LIKE ? OR email LIKE ? OR phone LIKE ? OR status LIKE ? ORDER BY created_at DESC LIMIT 10",
        [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]
    );
    $totalResults += count($results['bookings']);

    // Search tours
    $results['tours'] = $db->fetchAll(
        "SELECT * FROM tour_packages WHERE title LIKE ? OR slug LIKE ? OR duration LIKE ? OR country LIKE ? OR description LIKE ? ORDER BY title LIMIT 10",
        [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]
    );
    $totalResults += count($results['tours']);

    // Search destinations
    $results['destinations'] = $db->fetchAll(
        "SELECT * FROM destinations WHERE name LIKE ? OR country LIKE ? OR short_description LIKE ? ORDER BY name LIMIT 10",
        [$searchTerm, $searchTerm, $searchTerm]
    );
    $totalResults += count($results['destinations']);

    // Search inquiries
    $results['inquiries'] = $db->fetchAll(
        "SELECT * FROM inquiries WHERE full_name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ? ORDER BY created_at DESC LIMIT 10",
        [$searchTerm, $searchTerm, $searchTerm, $searchTerm]
    );
    $totalResults += count($results['inquiries']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search - Kizza Tours Admin</title>
    <link rel="icon" href="../assets/images/log.png" type="image/png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="../templates/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../templates/assets/css/ruang-admin.min.css" rel="stylesheet">
    <link href="css/admin.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .sidebar-brand { background-color: #0A2540 !important; }
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
        .result-section { margin-bottom: 1.5rem; }
        .result-section h5 { border-bottom: 2px solid #0A2540; padding-bottom: 0.5rem; margin-bottom: 1rem; color: #0A2540; }
        .no-results { text-align: center; padding: 4rem 1rem; color: #6c757d; }
        .no-results i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.3; }
    </style>
</head>
<body id="page-top">
    <div id="wrapper">
        <ul class="navbar-nav sidebar sidebar-light accordion" id="accordionSidebar">
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="#" style="background-color: #0A2540;">
                <div class="sidebar-brand-icon"><img src="../assets/images/log.png" alt="Kizza Tours" height="35" style="border-radius: 50%;"></div>
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
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Account</div>
            <li class="nav-item"><a class="nav-link" href="profile"><i class="fas fa-fw fa-user"></i><span>My Profile</span></a></li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">System</div>
            <li class="nav-item"><a class="nav-link" href="settings"><i class="fas fa-fw fa-cog"></i><span>Settings</span></a></li>
            <li class="nav-item"><a class="nav-link" href="logout"><i class="fas fa-fw fa-sign-out-alt"></i><span>Logout</span></a></li>
            <hr class="sidebar-divider d-none d-md-block">
            <div class="version">Version 1.0</div>
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
                                        <input type="text" class="form-control bg-light border-1 small" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="What do you want to look for?" aria-label="Search">
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
                        <h4 class="mb-0 text-gray-800"><img src="../assets/images/log.png" alt="" height="32" class="mr-2" style="border-radius:6px;">
                            <?php if ($q !== ''): ?>
                                Search results for "<strong><?php echo htmlspecialchars($q); ?></strong>"
                                <span class="badge badge-secondary ml-2"><?php echo $totalResults; ?> found</span>
                            <?php else: ?>
                                Search
                            <?php endif; ?>
                        </h4>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard">Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Search</li>
                        </ol>
                    </div>

                    <?php if ($q === ''): ?>
                        <div class="no-results">
                            <i class="fas fa-search"></i>
                            <h5>Enter a search term to find bookings, tours, destinations, or inquiries</h5>
                        </div>
                    <?php elseif ($totalResults === 0): ?>
                        <div class="no-results">
                            <i class="fas fa-search-minus"></i>
                            <h5>No results found for "<?php echo htmlspecialchars($q); ?>"</h5>
                            <p>Try different keywords or check your spelling</p>
                        </div>
                    <?php else: ?>

                        <?php if (!empty($results['bookings'])): ?>
                        <div class="result-section">
                            <h5><i class="fas fa-calendar-check mr-2"></i>Bookings (<?php echo count($results['bookings']); ?>)</h5>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead><tr><th>Ref</th><th>Name</th><th>Email</th><th>Status</th><th>Date</th><th></th></tr></thead>
                                    <tbody>
                                        <?php foreach ($results['bookings'] as $r): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($r['booking_reference']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($r['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($r['email']); ?></td>
                                            <td><span class="badge badge-<?php echo $r['status'] === 'confirmed' ? 'success' : ($r['status'] === 'cancelled' ? 'danger' : 'warning'); ?>"><?php echo $r['status']; ?></span></td>
                                            <td><?php echo date('M d, Y', strtotime($r['created_at'])); ?></td>
                                            <td><a href="bookings" class="btn btn-sm btn-outline-primary">View</a></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($results['tours'])): ?>
                        <div class="result-section">
                            <h5><i class="fas fa-safari mr-2"></i>Tours (<?php echo count($results['tours']); ?>)</h5>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead><tr><th>Title</th><th>Duration</th><th>Price</th><th>Country</th><th></th></tr></thead>
                                    <tbody>
                                        <?php foreach ($results['tours'] as $r): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($r['title']); ?></td>
                                            <td><?php echo htmlspecialchars($r['duration']); ?></td>
                                            <td><?php echo htmlspecialchars($r['currency'] . ' ' . number_format($r['price'], 2)); ?></td>
                                            <td><?php echo htmlspecialchars($r['country']); ?></td>
                                            <td><a href="tours" class="btn btn-sm btn-outline-primary">View</a></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($results['destinations'])): ?>
                        <div class="result-section">
                            <h5><i class="fas fa-map-marker-alt mr-2"></i>Destinations (<?php echo count($results['destinations']); ?>)</h5>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead><tr><th>Name</th><th>Country</th><th></th></tr></thead>
                                    <tbody>
                                        <?php foreach ($results['destinations'] as $r): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($r['name']); ?></td>
                                            <td><?php echo htmlspecialchars($r['country']); ?></td>
                                            <td><a href="destinations" class="btn btn-sm btn-outline-primary">View</a></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($results['inquiries'])): ?>
                        <div class="result-section">
                            <h5><i class="fas fa-envelope mr-2"></i>Inquiries (<?php echo count($results['inquiries']); ?>)</h5>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead><tr><th>Name</th><th>Email</th><th>Subject</th><th>Status</th><th></th></tr></thead>
                                    <tbody>
                                        <?php foreach ($results['inquiries'] as $r): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($r['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($r['email']); ?></td>
                                            <td><?php echo htmlspecialchars($r['subject'] ?? '—'); ?></td>
                                            <td><span class="badge badge-<?php echo $r['status'] === 'new' ? 'danger' : ($r['status'] === 'read' ? 'info' : 'success'); ?>"><?php echo $r['status']; ?></span></td>
                                            <td><a href="inquiries" class="btn btn-sm btn-outline-primary">View</a></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>

                    <?php endif; ?>
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

    <script src="../templates/assets/vendor/jquery/jquery.min.js"></script>
    <script src="../templates/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../templates/assets/vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="../templates/assets/js/ruang-admin.min.js"></script>
</body>
</html>
