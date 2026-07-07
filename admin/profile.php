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

$adminId = $_SESSION['admin_id'];
$flash = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');

        if ($fullName && $email && $username) {
            // Check username/email uniqueness
            $existing = $db->fetchOne(
                "SELECT id FROM admin_users WHERE (username = ? OR email = ?) AND id != ?",
                [$username, $email, $adminId]
            );
            if ($existing) {
                $flash = '<div class="alert alert-danger">Username or email already taken</div>';
            } else {
                $db->query("UPDATE admin_users SET full_name = ?, email = ?, username = ? WHERE id = ?",
                    [$fullName, $email, $username, $adminId]);
                $_SESSION['admin_name'] = $fullName;
                $flash = '<div class="alert alert-success">Profile updated successfully</div>';
            }
        } else {
            $flash = '<div class="alert alert-danger">All fields are required</div>';
        }
    }

    if ($action === 'change_password') {
        $currentPw = $_POST['current_password'] ?? '';
        $newPw = $_POST['new_password'] ?? '';
        $confirmPw = $_POST['confirm_password'] ?? '';

        $admin = $db->fetchOne("SELECT password FROM admin_users WHERE id = ?", [$adminId]);

        if (!password_verify($currentPw, $admin['password'])) {
            $flash = '<div class="alert alert-danger">Current password is incorrect</div>';
        } elseif (strlen($newPw) < 6) {
            $flash = '<div class="alert alert-danger">New password must be at least 6 characters</div>';
        } elseif ($newPw !== $confirmPw) {
            $flash = '<div class="alert alert-danger">Passwords do not match</div>';
        } else {
            $db->query("UPDATE admin_users SET password = ? WHERE id = ?",
                [password_hash($newPw, PASSWORD_DEFAULT), $adminId]);
            $flash = '<div class="alert alert-success">Password changed successfully</div>';
        }
    }

    if ($action === 'upload_image') {
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $filename = 'admin_' . $adminId . '_' . time() . '.' . $ext;
                $dest = __DIR__ . '/uploads/profile/' . $filename;
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $dest)) {
                    $converted = convertToWebp($dest);
                    $filename = basename($converted);
                    // Delete old image
                    $old = $db->fetchOne("SELECT profile_image FROM admin_users WHERE id = ?", [$adminId]);
                    if ($old && $old['profile_image'] && file_exists(__DIR__ . '/uploads/profile/' . $old['profile_image'])) {
                        unlink(__DIR__ . '/uploads/profile/' . $old['profile_image']);
                    }
                    $db->query("UPDATE admin_users SET profile_image = ? WHERE id = ?", [$filename, $adminId]);
                    $_SESSION['admin_image'] = $filename;
                    $flash = '<div class="alert alert-success">Profile image updated</div>';
                } else {
                    $flash = '<div class="alert alert-danger">Failed to upload image</div>';
                }
            } else {
                $flash = '<div class="alert alert-danger">Allowed types: jpg, jpeg, png, gif, webp</div>';
            }
        } else {
            $flash = '<div class="alert alert-danger">No image selected</div>';
        }
    }

    if ($action === 'remove_image') {
        $old = $db->fetchOne("SELECT profile_image FROM admin_users WHERE id = ?", [$adminId]);
        if ($old && $old['profile_image'] && file_exists(__DIR__ . '/uploads/profile/' . $old['profile_image'])) {
            unlink(__DIR__ . '/uploads/profile/' . $old['profile_image']);
        }
        $db->query("UPDATE admin_users SET profile_image = NULL WHERE id = ?", [$adminId]);
        $_SESSION['admin_image'] = null;
        $flash = '<div class="alert alert-success">Profile image removed</div>';
    }
}

$admin = $db->fetchOne("SELECT * FROM admin_users WHERE id = ?", [$adminId]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Kizza Tours Admin</title>
    <link rel="icon" href="../assets/images/log.png" type="image/png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
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
        .avatar-wrap { width: 150px; height: 150px; border-radius: 50%; overflow: hidden; border: 4px solid #e9ecef; margin: 0 auto 1rem; }
        .avatar-wrap img { width: 100%; height: 100%; object-fit: cover; }
        .profile-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: #6c757d; font-weight: 700; margin-bottom: 0.25rem; }
        .profile-value { font-size: 1rem; color: #333; margin-bottom: 1rem; }
        .card { border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .card-header { background: #fff; border-bottom: 1px solid #e9ecef; }
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
            <li class="nav-item"><a class="nav-link" href="quotes"><i class="fas fa-fw fa-file-invoice"></i><span>Quotes</span></a></li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Account</div>
            <li class="nav-item active"><a class="nav-link" href="profile"><i class="fas fa-fw fa-user"></i><span>My Profile</span></a></li>
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
                        <h4 class="mb-0 text-gray-800"><img src="../assets/images/log.png" alt="" height="32" class="mr-2" style="border-radius:6px;"> My Profile</h4>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard">Home</a></li>
                            <li class="breadcrumb-item active">Profile</li>
                        </ol>
                    </div>

                    <?php if ($flash): echo $flash; endif; ?>

                    <div class="row">
                        <!-- Avatar Card -->
                        <div class="col-lg-4 mb-4">
                            <div class="card">
                                <div class="card-body text-center py-4">
                                    <div class="avatar-wrap">
                                        <?php if ($admin['profile_image'] && file_exists(__DIR__ . '/uploads/profile/' . $admin['profile_image'])): ?>
                                            <img src="uploads/profile/<?php echo $admin['profile_image']; ?>" alt="Profile">
                                        <?php else: ?>
                                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($admin['full_name']); ?>&background=0A2540&color=fff&size=150" alt="Profile">
                                        <?php endif; ?>
                                    </div>
                                    <h5 class="mt-2"><?php echo htmlspecialchars($admin['full_name']); ?></h5>
                                    <span class="badge badge-primary"><?php echo $admin['role']; ?></span>

                                    <hr>
                                    <form method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="action" value="upload_image">
                                        <div class="custom-file mb-2 text-left">
                                            <input type="file" class="custom-file-input" id="profile_image" name="profile_image" accept="image/*">
                                            <label class="custom-file-label" for="profile_image">Choose image</label>
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-sm btn-block" style="background-color: #0A2540; border-color: #0A2540;">Upload Photo</button>
                                    </form>
                                    <?php if ($admin['profile_image']): ?>
                                    <form method="POST" class="mt-2">
                                        <input type="hidden" name="action" value="remove_image">
                                        <button type="submit" class="btn btn-outline-danger btn-sm btn-block">Remove Photo</button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-8">
                            <!-- Profile Info -->
                            <div class="card mb-4">
                                <div class="card-header"><h6 class="m-0 font-weight-bold">Profile Information</h6></div>
                                <div class="card-body">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="update_profile">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="profile-label">Full Name</label>
                                                    <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($admin['full_name']); ?>" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="profile-label">Username</label>
                                                    <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($admin['username']); ?>" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="profile-label">Email</label>
                                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                                        </div>
                                        <div class="form-group mb-0">
                                            <label class="profile-label">Role</label>
                                            <input type="text" class="form-control" value="<?php echo $admin['role']; ?>" disabled>
                                        </div>
                                        <hr>
                                        <button type="submit" class="btn btn-primary" style="background-color: #0A2540; border-color: #0A2540;">Save Changes</button>
                                    </form>
                                </div>
                            </div>

                            <!-- Change Password -->
                            <div class="card">
                                <div class="card-header"><h6 class="m-0 font-weight-bold">Change Password</h6></div>
                                <div class="card-body">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="change_password">
                                        <div class="form-group">
                                            <label class="profile-label">Current Password</label>
                                            <input type="password" class="form-control" name="current_password" required>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="profile-label">New Password</label>
                                                    <input type="password" class="form-control" name="new_password" required minlength="6">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="profile-label">Confirm Password</label>
                                                    <input type="password" class="form-control" name="confirm_password" required minlength="6">
                                                </div>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-warning">Change Password</button>
                                    </form>
                                </div>
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

    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-easing/1.4.1/jquery.easing.min.js"></script>
    <script src="../templates/assets/js/ruang-admin.min.js"></script>
    <script>
        $('.custom-file-input').on('change', function() {
            var fileName = $(this).val().split('\\').pop();
            $(this).next('.custom-file-label').html(fileName);
        });
    </script>
</body>
</html>
