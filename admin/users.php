<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once __DIR__ . '/../includes/admin-auth.php';

$db = db();
requireAdminOwner();

$permissions = $db->fetchAll("SELECT permission_code, label, module FROM admin_permissions ORDER BY sort_order, label");
$permissionMap = [];
foreach ($permissions as $permission) $permissionMap[$permission['permission_code']] = $permission;
$error = '';
$selectedId = (int)($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $fullName = trim($_POST['full_name'] ?? '');
            $role = in_array($_POST['role'] ?? '', ['admin', 'editor'], true) ? $_POST['role'] : 'editor';
            $password = (string)($_POST['password'] ?? '');
            if ($username === '' || $email === '' || $fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Enter a name, username, and valid email address.');
            }
            if (strlen($password) < 12) throw new RuntimeException('Use a password with at least 12 characters.');
            $granted = array_values(array_intersect(array_map('strval', (array)($_POST['permissions'] ?? [])), array_keys($permissionMap)));

            $db->beginTransaction();
            $newId = (int)$db->insert(
                "INSERT INTO admin_users (username, email, password, full_name, role, is_active) VALUES (?, ?, ?, ?, ?, 1)",
                [$username, $email, password_hash($password, PASSWORD_DEFAULT), $fullName, $role]
            );
            foreach ($granted as $code) {
                $db->query("INSERT INTO admin_user_permissions (admin_id, permission_code) VALUES (?, ?)", [$newId, $code]);
            }
            $db->commit();
            adminLogActivity('created', 'users', $newId, $fullName, ['role' => $role, 'permission_count' => count($granted)]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Staff user created.'];
            header('Location: users?edit=' . $newId);
            exit;
        }

        $targetId = (int)($_POST['admin_id'] ?? 0);
        $target = $db->fetchOne("SELECT id, username, full_name, role, is_active FROM admin_users WHERE id = ?", [$targetId]);
        if (!$target) throw new RuntimeException('That user could not be found.');
        if ($target['role'] === 'super_admin') throw new RuntimeException('The owner account is protected.');

        if ($action === 'save') {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $fullName = trim($_POST['full_name'] ?? '');
            $role = in_array($_POST['role'] ?? '', ['admin', 'editor'], true) ? $_POST['role'] : 'editor';
            if ($username === '' || $fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Enter a name, username, and valid email address.');
            }
            $granted = array_values(array_intersect(array_map('strval', (array)($_POST['permissions'] ?? [])), array_keys($permissionMap)));
            $db->beginTransaction();
            $db->query("UPDATE admin_users SET username = ?, email = ?, full_name = ?, role = ? WHERE id = ?", [$username, $email, $fullName, $role, $targetId]);
            $db->query("DELETE FROM admin_user_permissions WHERE admin_id = ?", [$targetId]);
            foreach ($granted as $code) {
                $db->query("INSERT INTO admin_user_permissions (admin_id, permission_code) VALUES (?, ?)", [$targetId, $code]);
            }
            $db->commit();
            adminLogActivity('updated', 'users', $targetId, $fullName, ['role' => $role, 'permission_count' => count($granted)]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'User details and permissions saved.'];
            header('Location: users?edit=' . $targetId);
            exit;
        }

        if ($action === 'toggle') {
            $active = (int)$target['is_active'] === 1 ? 0 : 1;
            $db->query("UPDATE admin_users SET is_active = ? WHERE id = ?", [$active, $targetId]);
            adminLogActivity($active ? 'activated' : 'deactivated', 'users', $targetId, $target['full_name']);
            $_SESSION['flash'] = ['type' => 'success', 'message' => $active ? 'User activated.' : 'User deactivated.'];
            header('Location: users?edit=' . $targetId);
            exit;
        }

        if ($action === 'reset_password') {
            $password = (string)($_POST['new_password'] ?? '');
            if (strlen($password) < 12) throw new RuntimeException('Use a password with at least 12 characters.');
            $db->query("UPDATE admin_users SET password = ?, session_version = session_version + 1 WHERE id = ?", [password_hash($password, PASSWORD_DEFAULT), $targetId]);
            adminLogActivity('password_reset', 'users', $targetId, $target['full_name']);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Password reset. Share the new password securely.'];
            header('Location: users?edit=' . $targetId);
            exit;
        }
        throw new RuntimeException('Unknown user action.');
    } catch (Throwable $e) {
        if ($db->getConnection()->inTransaction()) $db->rollback();
        $error = $e instanceof RuntimeException ? $e->getMessage() : 'Could not save this user. Check that the username and email are unique.';
        if (!($e instanceof RuntimeException)) error_log('User management error: ' . $e->getMessage());
        $selectedId = (int)($_POST['admin_id'] ?? 0);
    }
}

$users = $db->fetchAll("SELECT id, username, email, full_name, role, is_active, last_login, created_at FROM admin_users ORDER BY role = 'super_admin' DESC, full_name ASC");
$editing = null;
$assigned = [];
if ($selectedId > 0) {
    $editing = $db->fetchOne("SELECT id, username, email, full_name, role, is_active, last_login, created_at FROM admin_users WHERE id = ?", [$selectedId]);
    if ($editing) {
        $assignedRows = $db->fetchAll("SELECT permission_code FROM admin_user_permissions WHERE admin_id = ?", [$selectedId]);
        $assigned = array_column($assignedRows, 'permission_code');
    }
}
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Kizza Tours Admin</title>
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
        .permission-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 8px; }
        .permission-item { border: 1px solid #e3e6f0; border-radius: 6px; padding: 9px 12px; }
        .owner-row { background: #fff9e8; }
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
            <li class="nav-item"><a class="nav-link" href="faqs"><i class="fas fa-fw fa-question-circle"></i><span>FAQs</span></a></li>
            <li class="nav-item"><a class="nav-link" href="inquiries"><i class="fas fa-fw fa-envelope"></i><span>Inquiries</span></a></li>
            <li class="nav-item"><a class="nav-link" href="quotes"><i class="fas fa-fw fa-file-invoice"></i><span>Quotes</span></a></li>
            <li class="nav-item"><a class="nav-link" href="pages"><i class="fas fa-fw fa-file-alt"></i><span>Pages</span></a></li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Tools</div>
            <li class="nav-item"><a class="nav-link" href="compress-images"><i class="fas fa-fw fa-compress-alt"></i><span>Compress Images</span></a></li>
            <li class="nav-item"><a class="nav-link" href="sitemap"><i class="fas fa-fw fa-sitemap"></i><span>Sitemap</span></a></li>
            <?php echo adminOwnerMenu(); ?>
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
                    <?php echo admin_image_search_menu(); ?>
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
                        <div>
                            <h4 class="mb-0 text-gray-800"><img src="../assets/images/log.png" alt="" height="32" class="mr-2"> Manage Users</h4>
                            <p class="text-muted mb-0">Create staff accounts and choose the admin modules they can manage.</p>
                        </div>
                        <div>
                            <a class="btn btn-outline-primary btn-sm" href="activity"><i class="fas fa-clipboard-list mr-1"></i> Activity Log</a>
                            <ol class="breadcrumb d-inline-flex mb-0 ml-2">
                                <li class="breadcrumb-item"><a href="dashboard">Home</a></li>
                                <li class="breadcrumb-item active">Manage Users</li>
                            </ol>
                        </div>
                    </div>

                    <?php if ($flash): ?><div class="alert alert-<?= ($flash['type'] ?? '') === 'success' ? 'success' : 'warning' ?>"><?= htmlspecialchars($flash['message'] ?? '') ?></div><?php endif; ?>
                    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

                    <?php if (!$editing): ?>
                    <div class="card shadow-sm mb-4"><div class="card-header font-weight-bold">Add staff user</div><div class="card-body"><form method="post">
                        <?php csrf_field(); ?><input type="hidden" name="action" value="create">
                        <div class="form-row"><div class="form-group col-md-4"><label>Full name</label><input class="form-control" name="full_name" required></div><div class="form-group col-md-4"><label>Username</label><input class="form-control" name="username" required autocomplete="off"></div><div class="form-group col-md-4"><label>Email</label><input class="form-control" type="email" name="email" required></div></div>
                        <div class="form-row"><div class="form-group col-md-4"><label>Initial password (12+ characters)</label><input class="form-control" type="password" name="password" minlength="12" required autocomplete="new-password"></div><div class="form-group col-md-4"><label>Account type</label><select class="form-control" name="role"><option value="editor">Editor</option><option value="admin">Admin</option></select><small class="form-text text-muted">Module permissions below control what this user can access.</small></div></div>
                        <label class="font-weight-bold">Permissions</label><div class="permission-grid mb-3">
                        <?php foreach ($permissions as $permission): ?><label class="permission-item mb-0"><input type="checkbox" name="permissions[]" value="<?= htmlspecialchars($permission['permission_code']) ?>" <?= $permission['permission_code'] === 'view_dashboard' ? 'checked' : '' ?>> <?= htmlspecialchars($permission['label']) ?></label><?php endforeach; ?>
                        </div><button class="btn btn-primary" type="submit"><i class="fas fa-user-plus mr-1"></i> Create user</button>
                    </form></div></div>
                    <?php else: ?>
                    <div class="card shadow-sm mb-4"><div class="card-header d-flex justify-content-between"><span class="font-weight-bold">Edit user</span><a href="users">Back to all users</a></div><div class="card-body">
                        <?php if ($editing['role'] === 'super_admin'): ?><div class="alert alert-info mb-0">This is the protected owner account. Its role, access, and status cannot be changed here.</div>
                        <?php else: ?><form method="post"><input type="hidden" name="action" value="save"><input type="hidden" name="admin_id" value="<?= (int)$editing['id'] ?>"><?php csrf_field(); ?>
                            <div class="form-row"><div class="form-group col-md-4"><label>Full name</label><input class="form-control" name="full_name" value="<?= htmlspecialchars($editing['full_name']) ?>" required></div><div class="form-group col-md-4"><label>Username</label><input class="form-control" name="username" value="<?= htmlspecialchars($editing['username']) ?>" required></div><div class="form-group col-md-4"><label>Email</label><input class="form-control" type="email" name="email" value="<?= htmlspecialchars($editing['email']) ?>" required></div></div>
                            <div class="form-group"><label>Account type</label><select class="form-control" name="role"><option value="editor" <?= $editing['role'] === 'editor' ? 'selected' : '' ?>>Editor</option><option value="admin" <?= $editing['role'] === 'admin' ? 'selected' : '' ?>>Admin</option></select></div>
                            <label class="font-weight-bold">Permissions</label><div class="permission-grid mb-3"><?php foreach ($permissions as $permission): ?><label class="permission-item mb-0"><input type="checkbox" name="permissions[]" value="<?= htmlspecialchars($permission['permission_code']) ?>" <?= in_array($permission['permission_code'], $assigned, true) ? 'checked' : '' ?>> <?= htmlspecialchars($permission['label']) ?></label><?php endforeach; ?></div>
                            <button class="btn btn-primary" type="submit"><i class="fas fa-save mr-1"></i> Save user</button>
                        </form><hr>
                        <div class="d-flex flex-wrap align-items-start justify-content-between"><form method="post" class="mb-3"><input type="hidden" name="action" value="toggle"><input type="hidden" name="admin_id" value="<?= (int)$editing['id'] ?>"><?php csrf_field(); ?><button class="btn btn-<?= (int)$editing['is_active'] === 1 ? 'outline-danger' : 'outline-success' ?>" type="submit"><?= (int)$editing['is_active'] === 1 ? 'Deactivate account' : 'Activate account' ?></button></form><form method="post" class="form-inline mb-3"><input type="hidden" name="action" value="reset_password"><input type="hidden" name="admin_id" value="<?= (int)$editing['id'] ?>"><?php csrf_field(); ?><input class="form-control mr-2" type="password" name="new_password" minlength="12" placeholder="New password (12+ chars)" required autocomplete="new-password"><button class="btn btn-outline-primary" type="submit">Reset password</button></form></div>
                        <p class="small text-muted mb-0">Last login: <?= htmlspecialchars($editing['last_login'] ?: 'Never') ?> · <a href="activity?user_id=<?= (int)$editing['id'] ?>">View this user's activity</a></p>
                        <?php endif; ?>
                    </div></div>
                    <?php endif; ?>

                    <div class="card shadow-sm"><div class="card-header font-weight-bold">Accounts</div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th></th></tr></thead><tbody>
                    <?php foreach ($users as $user): ?><tr class="<?= $user['role'] === 'super_admin' ? 'owner-row' : '' ?>"><td><?= htmlspecialchars($user['full_name']) ?><?= $user['role'] === 'super_admin' ? ' <span class="badge badge-warning">Owner</span>' : '' ?></td><td><?= htmlspecialchars($user['username']) ?></td><td><?= htmlspecialchars($user['email']) ?></td><td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $user['role']))) ?></td><td><span class="badge badge-<?= (int)$user['is_active'] === 1 ? 'success' : 'secondary' ?>"><?= (int)$user['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td><td><?= htmlspecialchars($user['last_login'] ?: 'Never') ?></td><td><?php if ($user['role'] !== 'super_admin'): ?><a class="btn btn-sm btn-outline-primary" href="users?edit=<?= (int)$user['id'] ?>">Manage</a><?php endif; ?></td></tr><?php endforeach; ?>
                    </tbody></table></div></div>
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
</body>
</html>
