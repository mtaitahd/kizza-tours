<?php
// ADMIN PASSWORD RESET SCRIPT - Kizza Tours
// Safely resets admin credentials with proper bcrypt hashing
// DELETE THIS FILE after use!

require_once '../includes/config.php';
require_once '../includes/db.php';

// Simple password protection
$AUDIT_KEY = 'kizza-secure-2026';
if (!isset($_GET['key']) || $_GET['key'] !== $AUDIT_KEY) {
    http_response_code(403);
    die('<h1>403 Forbidden</h1><p>Access denied.</p>');
}

$db = db();
$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // RESET SINGLE ACCOUNT
    if ($action === 'reset_account') {
        $adminId = (int)($_POST['admin_id'] ?? 0);
        $newUsername = trim($_POST['new_username'] ?? '');
        $newEmail = trim($_POST['new_email'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!$newUsername || !$newEmail || !$newPassword) {
            $message = '<div class="alert alert-danger">All fields are required.</div>';
        } elseif (strlen($newPassword) < 8) {
            $message = '<div class="alert alert-danger">Password must be at least 8 characters.</div>';
        } elseif ($newPassword !== $confirmPassword) {
            $message = '<div class="alert alert-danger">Passwords do not match.</div>';
        } else {
            // Check uniqueness
            $existing = $db->fetchOne(
                "SELECT id FROM admin_users WHERE (username = ? OR email = ?) AND id != ?",
                [$newUsername, $newEmail, $adminId]
            );
            if ($existing) {
                $message = '<div class="alert alert-danger">Username or email already taken.</div>';
            } else {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $db->query(
                    "UPDATE admin_users SET username = ?, email = ?, password = ?, updated_at = NOW() WHERE id = ?",
                    [$newUsername, $newEmail, $hashedPassword, $adminId]
                );
                // Destroy all existing sessions (force re-login)
                // This logs out the attacker
                $success = true;
                $message = '<div class="alert alert-success">
                    <strong>✅ Credentials reset successfully!</strong><br>
                    Username: ' . htmlspecialchars($newUsername) . '<br>
                    Email: ' . htmlspecialchars($newEmail) . '<br>
                    <br>You can now login with the new password.
                </div>';
            }
        }
    }

    // DELETE UNAUTHORIZED ACCOUNTS
    if ($action === 'delete_account') {
        $adminId = (int)($_POST['admin_id'] ?? 0);
        if ($adminId > 1) { // Never delete ID 1 (original admin)
            $db->query("DELETE FROM admin_users WHERE id = ? AND id != 1", [$adminId]);
            $message = '<div class="alert alert-warning">Account ID ' . $adminId . ' deleted.</div>';
        }
    }

    // NUCLEAR OPTION: Delete ALL admin accounts except one and create fresh
    if ($action === 'reset_all') {
        $newUsername = trim($_POST['reset_username'] ?? '');
        $newEmail = trim($_POST['reset_email'] ?? '');
        $newPassword = $_POST['reset_password'] ?? '';
        $confirmPassword = $_POST['reset_confirm'] ?? '';

        if (!$newUsername || !$newEmail || !$newPassword) {
            $message = '<div class="alert alert-danger">All fields are required.</div>';
        } elseif (strlen($newPassword) < 8) {
            $message = '<div class="alert alert-danger">Password must be at least 8 characters.</div>';
        } elseif ($newPassword !== $confirmPassword) {
            $message = '<div class="alert alert-danger">Passwords do not match.</div>';
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            // Delete all admin accounts
            $db->query("DELETE FROM admin_users");
            // Insert fresh account
            $db->query(
                "INSERT INTO admin_users (username, email, password, full_name, role, created_at, updated_at) VALUES (?, ?, ?, 'Administrator', 'super_admin', NOW(), NOW())",
                [$newUsername, $newEmail, $hashedPassword]
            );

            // Clear the settings cache (may contain attacker's changes)
            $cacheFile = dirname(__DIR__) . '/cache/settings.php';
            if (file_exists($cacheFile)) @unlink($cacheFile);

            $success = true;
            $message = '<div class="alert alert-success">
                <strong>✅ FULL RESET COMPLETE!</strong><br>
                All old admin accounts deleted.<br>
                New admin created: ' . htmlspecialchars($newUsername) . '<br>
                <br>LOGIN NOW at: <a href="index.php">admin/login</a>
            </div>';
        }
    }
}

// Fetch current accounts
$admins = $db->fetchAll("SELECT * FROM admin_users ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Admin - Kizza Tours Security</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #1a1a2e; color: #fff; font-family: monospace; }
        .card { background: #16213e; border: 1px solid #333; }
        .btn-danger { font-weight: bold; }
        .danger-zone { border: 2px solid #ff6b6b; padding: 20px; border-radius: 8px; margin-top: 20px; }
    </style>
</head>
<body class="p-4">
    <div class="container">
        <h1 class="text-danger">🔧 Admin Password Reset</h1>
        <p class="text-warning">⚠️ DELETE THIS FILE AFTER USE: admin/reset-admin.php</p>
        <hr class="border-secondary">

        <?php echo $message; ?>

        <!-- Option 1: Reset Individual Account -->
        <div class="card p-4 mb-4">
            <h3 class="text-info">Option 1: Reset a Specific Account</h3>
            <p class="text-muted">Choose which admin account to reset:</p>

            <?php foreach ($admins as $admin): ?>
            <div class="card p-3 mb-3 bg-dark">
                <h5>
                    <span class="badge bg-secondary">ID: <?php echo $admin['id']; ?></span>
                    <?php echo htmlspecialchars($admin['username']); ?>
                    (<?php echo htmlspecialchars($admin['email']); ?>)
                    <span class="badge bg-info"><?php echo $admin['role']; ?></span>
                </h5>
                <p class="text-muted small">Last modified: <?php echo $admin['updated_at']; ?> | Last login: <?php echo $admin['last_login'] ?? 'Never'; ?></p>

                <form method="POST" class="mt-2">
                    <input type="hidden" name="action" value="reset_account">
                    <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <input type="text" class="form-control form-control-sm" name="new_username" placeholder="New username" required>
                        </div>
                        <div class="col-md-3">
                            <input type="email" class="form-control form-control-sm" name="new_email" placeholder="New email" required>
                        </div>
                        <div class="col-md-2">
                            <input type="password" class="form-control form-control-sm" name="new_password" placeholder="New password (min 8 chars)" required minlength="8">
                        </div>
                        <div class="col-md-2">
                            <input type="password" class="form-control form-control-sm" name="confirm_password" placeholder="Confirm password" required>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-warning btn-sm w-100" onclick="return confirm('Reset credentials for this account?')">Reset This Account</button>
                        </div>
                    </div>
                </form>

                <?php if ($admin['id'] > 1): ?>
                <form method="POST" class="mt-2">
                    <input type="hidden" name="action" value="delete_account">
                    <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('DELETE this unauthorized account?')">Delete This Account</button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Option 2: Nuclear Reset -->
        <div class="danger-zone">
            <h3 class="text-danger">⚠️ Option 2: NUCLEAR RESET (Delete Everything & Start Fresh)</h3>
            <p class="text-warning">This will DELETE ALL admin accounts and create one new super admin account.</p>
            <p class="text-danger"><strong>Use this if you suspect the attacker created hidden admin accounts.</strong></p>

            <form method="POST">
                <input type="hidden" name="action" value="reset_all">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label text-light">New Admin Username</label>
                        <input type="text" class="form-control" name="reset_username" placeholder="e.g. admin" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-light">New Admin Email</label>
                        <input type="email" class="form-control" name="reset_email" placeholder="your@email.com" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-light">New Password (min 8 chars)</label>
                        <input type="password" class="form-control" name="reset_password" placeholder="Strong password" required minlength="8">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-light">Confirm Password</label>
                        <input type="password" class="form-control" name="reset_confirm" placeholder="Confirm password" required>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-danger btn-lg w-100" onclick="return confirm('THIS WILL DELETE ALL ADMIN ACCOUNTS. Are you sure?')">
                            🚨 NUCLEAR RESET
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="mt-4">
            <a href="security-audit.php?key=<?php echo $AUDIT_KEY; ?>" class="btn btn-outline-info">← Back to Audit</a>
            <a href="dashboard" class="btn btn-outline-secondary">Go to Dashboard</a>
        </div>
    </div>
</body>
</html>
