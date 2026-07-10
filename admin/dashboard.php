<?php
// KIZZA TOURS & SAFARIS - Admin Dashboard
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

// Get counts
$totalBookings = $db->fetchOne("SELECT COUNT(*) as count FROM bookings")['count'] ?? 0;
$pendingBookings = $db->fetchOne("SELECT COUNT(*) as count FROM bookings WHERE status = 'pending'")['count'] ?? 0;
$totalInquiries = $db->fetchOne("SELECT COUNT(*) as count FROM inquiries")['count'] ?? 0;
$unreadInquiries = $db->fetchOne("SELECT COUNT(*) as count FROM inquiries WHERE status = 'new'")['count'] ?? 0;
$totalTours = $db->fetchOne("SELECT COUNT(*) as count FROM tour_packages WHERE status = 'active'")['count'] ?? 0;
$totalTestimonials = $db->fetchOne("SELECT COUNT(*) as count FROM testimonials WHERE status = 'approved'")['count'] ?? 0;
$totalGallery = $db->fetchOne("SELECT COUNT(*) as count FROM gallery WHERE status = 'active'")['count'] ?? 0;
$totalSubscribers = $db->fetchOne("SELECT COUNT(*) as count FROM subscribers WHERE status = 'active'")['count'] ?? 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Kizza Tours Admin</title>
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

        .card { border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .card-header { background: #fff; border-bottom: 1px solid #e9ecef; padding: 1rem 1.5rem; }
        .card-header h6 { margin: 0; font-weight: 700; color: #333; }
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
        .table thead th { border-top: none; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: #6c757d; font-weight: 700; }
    </style>
</head>
<body id="page-top">
    <div id="wrapper">
        <!-- Sidebar -->
        <ul class="navbar-nav sidebar sidebar-light accordion" id="accordionSidebar">
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="#">
                <div class="sidebar-brand-icon">
                    <img src="../assets/images/log.png" alt="Kizza Tours" height="35">
                </div>
                <div class="sidebar-brand-text mx-3 text-white">Admin</div>
            </a>
            <hr class="sidebar-divider my-0">
            <li class="nav-item active">
                <a class="nav-link" href="dashboard">
                    <i class="fas fa-fw fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Management</div>
            <li class="nav-item">
                <a class="nav-link" href="bookings">
                    <i class="fas fa-fw fa-calendar-check"></i>
                    <span>Bookings</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="tours">
                    <i class="fas fa-fw fa-safari"></i>
                    <span>Tours</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="destinations">
                    <i class="fas fa-fw fa-map-marker-alt"></i>
                    <span>Destinations</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="gallery">
                    <i class="fas fa-fw fa-images"></i>
                    <span>Gallery</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="testimonials">
                    <i class="fas fa-fw fa-star"></i>
                    <span>Testimonials</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="inquiries">
                    <i class="fas fa-fw fa-envelope"></i>
                    <span>Inquiries</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="quotes">
                    <i class="fas fa-fw fa-file-invoice"></i>
                    <span>Quotes</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="pages">
                    <i class="fas fa-fw fa-file-alt"></i>
                    <span>Pages</span>
                </a>
            </li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Tools</div>
            <li class="nav-item">
                <a class="nav-link" href="compress-images">
                    <i class="fas fa-fw fa-compress-alt"></i>
                    <span>Compress Images</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="sitemap">
                    <i class="fas fa-fw fa-sitemap"></i>
                    <span>Sitemap</span>
                </a>
            </li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Account</div>
            <li class="nav-item">
                <a class="nav-link" href="profile">
                    <i class="fas fa-fw fa-user"></i>
                    <span>My Profile</span>
                </a>
            </li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">System</div>
            <li class="nav-item">
                <a class="nav-link" href="settings">
                    <i class="fas fa-fw fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logout">
                    <i class="fas fa-fw fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
            <hr class="sidebar-divider d-none d-md-block">
            <div class="version" id="version-ruangadmin">Version 1.0</div>
        </ul>
        <!-- End Sidebar -->

        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <!-- TopBar -->
                <nav class="navbar navbar-expand navbar-light bg-navbar topbar mb-4 static-top" style="background-color: #0A2540;">
                    <button id="sidebarToggleTop" class="btn btn-link rounded-circle mr-3">
                        <i class="fa fa-bars text-white"></i>
                    </button>
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
                                <?php
                                $imgPath = $_SESSION['admin_image'] ?? null;
                                if ($imgPath && file_exists(__DIR__ . '/uploads/profile/' . $imgPath)) {
                                    echo '<img src="uploads/profile/' . $imgPath . '" class="rounded-circle mr-1" style="width: 30px; height: 30px; object-fit: cover;">';
                                } else {
                                    echo '<i class="fas fa-user-circle text-white mr-1"></i>';
                                }
                                ?>
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
                <!-- End TopBar -->

                <!-- Main Content -->
                <div class="container-fluid" id="container-wrapper">
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h4 class="mb-0 text-gray-800"><img src="../assets/images/log.png" alt="" height="32" class="mr-2"> Dashboard</h4>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard">Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
                        </ol>
                    </div>

                    <!-- Stats Cards -->
                    <div class="row mb-4">
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-uppercase mb-1">Total Bookings</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totalBookings; ?></div>
                                            <div class="mt-2 mb-0 text-muted text-xs">
                                                <span class="text-success mr-2"><i class="fas fa-arrow-up"></i> <?php echo $pendingBookings; ?> pending</span>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-calendar-check fa-2x text-success"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-uppercase mb-1">Active Tours</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totalTours; ?></div>
                                            <div class="mt-2 mb-0 text-muted text-xs">
                                                <span>available packages</span>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-safari fa-2x text-info"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-uppercase mb-1">Total Inquiries</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totalInquiries; ?></div>
                                            <div class="mt-2 mb-0 text-muted text-xs">
                                                <span class="text-warning mr-2"><i class="fas fa-arrow-up"></i> <?php echo $unreadInquiries; ?> new</span>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-envelope fa-2x text-warning"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-uppercase mb-1">Testimonials</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totalTestimonials; ?></div>
                                            <div class="mt-2 mb-0 text-muted text-xs">
                                                <span>approved reviews</span>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-star fa-2x text-primary"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row -->
                    <div class="row mb-4">
                        <div class="col-xl-8 col-lg-7 mb-4">
                            <div class="card">
                                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold">Monthly Overview</h6>
                                </div>
                                <div class="card-body">
                                    <div class="chart-area" style="position: relative; height: 300px;">
                                        <canvas id="myAreaChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-4 col-lg-5 mb-4">
                            <div class="card">
                                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold">Bookings by Status</h6>
                                </div>
                                <div class="card-body">
                                    <div class="chart-pie pt-4 pb-2" style="position: relative; height: 260px;">
                                        <canvas id="myPieChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <!-- End Main Content -->
            </div>

            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright &copy; <script>document.write(new Date().getFullYear());</script> - <b>Kizza Tours & Safaris</b></span>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-easing/1.4.1/jquery.easing.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js"></script>
    <script src="../templates/assets/js/ruang-admin.min.js"></script>
    <script>
        Chart.defaults.global.defaultFontFamily = 'Nunito, -apple-system,system-ui,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';
        Chart.defaults.global.defaultFontColor = '#858796';

        var ctx = document.getElementById("myAreaChart");
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"],
                    datasets: [{
                        label: "Bookings",
                        lineTension: 0.3,
                        backgroundColor: "rgba(10, 37, 64, 0.3)",
                        borderColor: "#0A2540",
                        pointRadius: 3,
                        pointBackgroundColor: "#0A2540",
                        pointBorderColor: "#0A2540",
                        pointHoverRadius: 3,
                        pointHoverBackgroundColor: "#0A2540",
                        pointHoverBorderColor: "#0A2540",
                        pointHitRadius: 10,
                        pointBorderWidth: 2,
                        data: [<?php
                            // Get booking counts per month for current year
                            $monthlyData = [];
                            for ($m = 1; $m <= 12; $m++) {
                                $count = $db->fetchOne(
                                    "SELECT COUNT(*) as count FROM bookings WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = ?",
                                    [$m]
                                )['count'] ?? 0;
                                $monthlyData[] = $count;
                            }
                            echo implode(',', $monthlyData);
                        ?>],
                    }],
                },
                options: {
                    maintainAspectRatio: false,
                    layout: { padding: { left: 10, right: 25, top: 25, bottom: 0 } },
                    scales: {
                        xAxes: [{ time: { unit: 'date' }, gridLines: { display: false, drawBorder: false }, ticks: { maxTicksLimit: 7 } }],
                        yAxes: [{ ticks: { maxTicksLimit: 5, padding: 10, beginAtZero: true }, gridLines: { color: "rgb(234, 236, 244)", zeroLineColor: "rgb(234, 236, 244)", drawBorder: false, borderDash: [2], zeroLineBorderDash: [2] } }]
                    },
                    legend: { display: false },
                    tooltips: {
                        backgroundColor: "rgb(255,255,255)", bodyFontColor: "#858796", titleMarginBottom: 10, titleFontColor: '#6e707e',
                        titleFontSize: 14, borderColor: '#dddfeb', borderWidth: 1, xPadding: 15, yPadding: 15, displayColors: false,
                        intersect: false, mode: 'index', caretPadding: 10
                    }
                }
            });
        }

        var ctx2 = document.getElementById("myPieChart");
        if (ctx2) {
            <?php
            $confirmedBookings = $db->fetchOne("SELECT COUNT(*) as count FROM bookings WHERE status = 'confirmed'")['count'] ?? 0;
            $pendingCount = $db->fetchOne("SELECT COUNT(*) as count FROM bookings WHERE status = 'pending'")['count'] ?? 0;
            $cancelledBookings = $db->fetchOne("SELECT COUNT(*) as count FROM bookings WHERE status = 'cancelled'")['count'] ?? 0;
            ?>
            new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: ["Confirmed", "Pending", "Cancelled"],
                    datasets: [{ data: [<?php echo "$confirmedBookings, $pendingCount, $cancelledBookings"; ?>], backgroundColor: ["#10B981", "#F59E0B", "#EF4444"], hoverBackgroundColor: ["#059669", "#D97706", "#DC2626"], hoverBorderColor: "rgba(234, 236, 244, 1)" }]
                },
                options: {
                    maintainAspectRatio: false,
                    tooltips: { backgroundColor: "rgb(255,255,255)", bodyFontColor: "#858796", borderColor: '#dddfeb', borderWidth: 1, xPadding: 15, yPadding: 15, displayColors: false, caretPadding: 10 },
                    legend: { display: false },
                    cutoutPercentage: 80
                }
            });
        }
    </script>
</body>
</html>
