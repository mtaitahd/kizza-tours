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
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Kizza Tours Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../templates/assets/css/ruang-admin.min.css" rel="stylesheet"><link href="css/admin.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>.admin-wrap{padding:28px}.permission-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:8px}.permission-item{border:1px solid #e3e6f0;border-radius:6px;padding:9px 12px}.owner-row{background:#fff9e8}</style>
</head>
<body><div class="container-fluid admin-wrap">
    <div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h3">User Management</h1><p class="text-muted mb-0">Create staff accounts and choose the admin modules they can manage.</p></div><div><a class="btn btn-outline-primary" href="activity"><i class="fas fa-clipboard-list mr-1"></i> Activity Log</a> <a class="btn btn-outline-secondary" href="dashboard">Dashboard</a></div></div>
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
</div></body></html>
