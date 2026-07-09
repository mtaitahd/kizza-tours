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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if (in_array($action, ['add', 'edit'])) {
        $id = intval($_POST['id'] ?? 0);
        $customer_name = trim($_POST['customer_name'] ?? '');
        $customer_title = trim($_POST['customer_title'] ?? '');
        $review = trim($_POST['review'] ?? '');
        $rating = floatval($_POST['rating'] ?? 5);
        $country = trim($_POST['country'] ?? '');
        $tour_package = trim($_POST['tour_package'] ?? '');
        $status = trim($_POST['status'] ?? 'approved');
        
        $photo = '';
        $hasNewPhoto = false;
        if (isset($_FILES['customer_photo']) && $_FILES['customer_photo']['error'] === UPLOAD_ERR_OK) {
            $uploaded = uploadFile($_FILES['customer_photo'], BASE_PATH . 'uploads/', 'testimonial_' . slugify($customer_name));
            if ($uploaded) {
                $photo = $uploaded;
                $hasNewPhoto = true;
            }
        }
        
        if ($action === 'add') {
            $db->insert(
                "INSERT INTO testimonials (customer_name, customer_title, customer_photo, review, rating, country, tour_package, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$customer_name, $customer_title, $photo, $review, $rating, $country, $tour_package, $status]
            );
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Testimonial added'];
        } else {
            if ($hasNewPhoto) {
                $old = $db->fetchOne("SELECT customer_photo FROM testimonials WHERE id = ?", [$id]);
                if ($old && $old['customer_photo']) deleteFile($old['customer_photo']);
                $db->query(
                    "UPDATE testimonials SET customer_name=?, customer_title=?, customer_photo=?, review=?, rating=?, country=?, tour_package=?, status=? WHERE id=?",
                    [$customer_name, $customer_title, $photo, $review, $rating, $country, $tour_package, $status, $id]
                );
            } else {
                $db->query(
                    "UPDATE testimonials SET customer_name=?, customer_title=?, review=?, rating=?, country=?, tour_package=?, status=? WHERE id=?",
                    [$customer_name, $customer_title, $review, $rating, $country, $tour_package, $status, $id]
                );
            }
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Testimonial updated'];
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $t = $db->fetchOne("SELECT customer_photo FROM testimonials WHERE id = ?", [$id]);
        if ($t && $t['customer_photo']) deleteFile($t['customer_photo']);
        $db->query("DELETE FROM testimonials WHERE id = ?", [$id]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Testimonial deleted'];
    }
    
    header('Location: testimonials');
    exit;
}

$testimonials = $db->fetchAll("SELECT * FROM testimonials ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Testimonials - Kizza Tours Admin</title>
    <link rel="icon" href="../assets/images/log.png" type="image/png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../templates/assets/css/ruang-admin.min.css" rel="stylesheet">
    <link href="css/admin.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .sidebar-brand { background: none !important; }
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
            <li class="nav-item"><a class="nav-link" href="tours"><i class="fas fa-fw fa-safari"></i><span>Tours</span></a></li>
            <li class="nav-item"><a class="nav-link" href="destinations"><i class="fas fa-fw fa-map-marker-alt"></i><span>Destinations</span></a></li>
            <li class="nav-item"><a class="nav-link" href="gallery"><i class="fas fa-fw fa-images"></i><span>Gallery</span></a></li>
            <li class="nav-item active"><a class="nav-link" href="testimonials"><i class="fas fa-fw fa-star"></i><span>Testimonials</span></a></li>
            <li class="nav-item"><a class="nav-link" href="inquiries"><i class="fas fa-fw fa-envelope"></i><span>Inquiries</span></a></li>
            <li class="nav-item"><a class="nav-link" href="quotes"><i class="fas fa-fw fa-file-invoice"></i><span>Quotes</span></a></li>
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
                        <h4 class="mb-0 text-gray-800"><img src="../assets/images/log.png" alt="" height="32" class="mr-2"> Manage Testimonials</h4>
                        <button class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#testimonialModal">
                            <i class="fas fa-plus"></i> Add Testimonial
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
                            <input type="text" id="tableSearch" class="form-control form-control-sm" style="max-width: 300px;" placeholder="Filter testimonials..." onkeyup="filterTable(this.value)">
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0" id="dataTable">
                                    <thead>
                                        <tr>
                                            <th>Photo</th>
                                            <th>Customer</th>
                                            <th>Review</th>
                                            <th>Rating</th>
                                            <th>Country</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($testimonials as $t): ?>
                                        <tr>
                                            <td>
                                                <?php if ($t['customer_photo'] && file_exists(BASE_PATH . $t['customer_photo'])): ?>
                                                    <img src="../<?php echo $t['customer_photo']; ?>" style="width:40px;height:40px;object-fit:cover;border-radius:50%;">
                                                <?php else: ?>
                                                    <span class="text-muted"><i class="fas fa-user-circle fa-2x"></i></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><strong><?php echo htmlspecialchars($t['customer_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars(substr($t['review'], 0, 60)) . '...'; ?></td>
                                            <td>
                                                <?php for ($i = 0; $i < 5; $i++): ?>
                                                    <i class="fas fa-star" style="color: <?php echo $i < $t['rating'] ? '#0A2540' : '#e0e0e0'; ?>; font-size: 0.7rem;"></i>
                                                <?php endfor; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($t['country'] ?: '-'); ?></td>
                                            <td><span class="badge badge-<?php echo $t['status'] === 'approved' ? 'success' : 'warning'; ?>"><?php echo ucfirst($t['status']); ?></span></td>
                                            <td>
                                                <div class="d-flex">
                                                    <button class="btn btn-sm btn-outline-secondary mr-1" onclick='editTestimonial(<?php echo json_encode($t); ?>)'>
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($testimonials)): ?>
                                        <tr><td colspan="7" class="text-center py-4 text-muted">No testimonials yet</td></tr>
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

    <!-- Testimonial Modal -->
    <div class="modal fade" id="testimonialModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="testimonialModalTitle">Add Testimonial</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="testimonialAction" value="add">
                        <input type="hidden" name="id" id="testimonialId" value="0">
                        <div class="form-group">
                            <label>Customer Name</label>
                            <input type="text" class="form-control" name="customer_name" id="testimonialName" required>
                        </div>
                        <div class="form-group">
                            <label>Title/Location</label>
                            <input type="text" class="form-control" name="customer_title" id="testimonialTitle" placeholder="e.g., United States - Serengeti Safari">
                        </div>
                        <div class="form-group">
                            <label>Photo</label>
                            <input type="file" class="form-control-file" name="customer_photo" accept="image/*">
                        </div>
                        <div class="form-group">
                            <label>Review</label>
                            <textarea class="form-control" name="review" id="testimonialReview" rows="4" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Rating</label>
                                    <input type="number" class="form-control" name="rating" id="testimonialRating" min="1" max="5" step="0.1" value="5">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Country</label>
                                    <input type="text" class="form-control" name="country" id="testimonialCountry" placeholder="United States">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Tour Package</label>
                                    <input type="text" class="form-control" name="tour_package" id="testimonialPackage" placeholder="Serengeti Safari">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select class="form-control" name="status" id="testimonialStatus">
                                <option value="approved">Approved</option>
                                <option value="pending">Pending</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-outline-secondary">Save</button>
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
        function editTestimonial(t) {
            document.getElementById('testimonialAction').value = 'edit';
            document.getElementById('testimonialId').value = t.id;
            document.getElementById('testimonialName').value = t.customer_name;
            document.getElementById('testimonialTitle').value = t.customer_title || '';
            document.getElementById('testimonialReview').value = t.review;
            document.getElementById('testimonialRating').value = t.rating;
            document.getElementById('testimonialCountry').value = t.country || '';
            document.getElementById('testimonialPackage').value = t.tour_package || '';
            document.getElementById('testimonialStatus').value = t.status;
            document.getElementById('testimonialModalTitle').textContent = 'Edit Testimonial';
            $('#testimonialModal').modal('show');
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
