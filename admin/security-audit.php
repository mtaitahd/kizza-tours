<?php
// SECURITY AUDIT SCRIPT - Kizza Tours
// This script helps investigate unauthorized access
// DELETE THIS FILE after investigation!

require_once '../includes/config.php';
require_once '../includes/db.php';

// Simple password protection for this script
$AUDIT_KEY = 'kizza-secure-2026';
if (!isset($_GET['key']) || $_GET['key'] !== $AUDIT_KEY) {
    http_response_code(403);
    die('<h1>403 Forbidden</h1><p>Access denied. This is a restricted security tool.</p>');
}

$db = db();
$results = [];

// 1. List ALL admin accounts
$results['admins'] = $db->fetchAll("SELECT id, username, email, full_name, role, last_login, created_at, updated_at FROM admin_users ORDER BY id ASC");

// 2. Check for unauthorized admin accounts (expected: only 1)
$results['admin_count'] = count($results['admins']);

// 3. Check when admin credentials were last modified
$results['recent_changes'] = $db->fetchAll(
    "SELECT id, username, email, updated_at FROM admin_users WHERE updated_at > DATE_SUB(NOW(), INTERVAL 30 DAY) ORDER BY updated_at DESC"
);

// 4. Check last login timestamps
$results['last_logins'] = $db->fetchAll(
    "SELECT id, username, last_login FROM admin_users WHERE last_login IS NOT NULL ORDER BY last_login DESC LIMIT 10"
);

// 5. Check for recent password hash changes (updated_at = password changed)
$results['password_changes'] = $db->fetchAll(
    "SELECT id, username, updated_at FROM admin_users ORDER BY updated_at DESC LIMIT 5"
);

// 6. Check recent database activity on all tables
$results['recent_inserts'] = [];
try {
    // Check bookings
    $results['recent_inserts']['bookings'] = $db->fetchAll(
        "SELECT COUNT(*) as count, DATE(created_at) as date FROM bookings WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY date ORDER BY date DESC"
    );
    // Check inquiries
    $results['recent_inserts']['inquiries'] = $db->fetchAll(
        "SELECT COUNT(*) as count, DATE(created_at) as date FROM inquiries WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY date ORDER BY date DESC"
    );
} catch (Exception $e) {
    $results['table_error'] = $e->getMessage();
}

// 7. Check for suspicious file uploads
$results['recent_uploads'] = [];
$uploadDir = dirname(__DIR__) . '/uploads/';
if (is_dir($uploadDir)) {
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($uploadDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $ext = strtolower($file->getExtension());
            // Look for PHP files in uploads (possible webshell)
            if (in_array($ext, ['php', 'php5', 'phtml', 'pht', 'sh', 'pl', 'cgi'])) {
                $files[] = [
                    'path' => str_replace(dirname(__DIR__) . '/', '', $file->getPathname()),
                    'modified' => date('Y-m-d H:i:s', $file->getMTime()),
                    'size' => $file->getSize()
                ];
            }
        }
    }
    $results['suspicious_uploads'] = $files;
}

// 8. Check for recently modified PHP files in the project
$results['recent_php_changes'] = [];
$projectDir = dirname(__DIR__);
$phpIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($projectDir, RecursiveDirectoryIterator::SKIP_DOTS)
);
foreach ($phpIterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $mtime = $file->getMTime();
        // Files modified in the last 7 days
        if ((time() - $mtime) < (7 * 24 * 3600)) {
            $relPath = str_replace($projectDir . '/', '', $file->getPathname());
            // Skip vendor, cache, and normal development files
            if (strpos($relPath, 'vendor/') === false &&
                strpos($relPath, 'cache/') === false &&
                strpos($relPath, 'templates/') === false) {
                $results['recent_php_changes'][] = [
                    'path' => $relPath,
                    'modified' => date('Y-m-d H:i:s', $mtime),
                    'size' => $file->getSize()
                ];
            }
        }
    }
}
usort($results['recent_php_changes'], function($a, $b) {
    return strtotime($b['modified']) - strtotime($a['modified']);
});

// 9. Check settings table for tampering
$results['settings_changes'] = $db->fetchAll(
    "SELECT setting_key, updated_at FROM settings WHERE updated_at > DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY updated_at DESC LIMIT 20"
);

// 10. Check for any users table (public-facing) with admin-like data
try {
    $results['user_count'] = $db->fetchOne("SELECT COUNT(*) as count FROM users")['count'] ?? 'N/A';
} catch (Exception $e) {
    $results['user_count'] = 'No users table';
}

// Output
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Security Audit - Kizza Tours</title>
    <style>
        body { font-family: monospace; background: #1a1a2e; color: #0f0; padding: 20px; }
        h1 { color: #ff6b6b; border-bottom: 2px solid #ff6b6b; padding-bottom: 10px; }
        h2 { color: #ffd93d; margin-top: 30px; }
        .danger { color: #ff6b6b; font-weight: bold; }
        .warning { color: #ffd93d; }
        .ok { color: #0f0; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #333; padding: 8px; text-align: left; }
        th { background: #16213e; color: #ffd93d; }
        tr:nth-child(even) { background: #0f3460; }
        .card { background: #16213e; border: 1px solid #333; border-radius: 8px; padding: 15px; margin: 10px 0; }
        pre { background: #000; padding: 10px; overflow-x: auto; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>🔒 SECURITY AUDIT REPORT</h1>
    <p>Generated: <?php echo date('Y-m-d H:i:s'); ?></p>
    <p class="warning">⚠️ DELETE THIS FILE AFTER INVESTIGATION: admin/security-audit.php</p>

    <!-- ADMIN ACCOUNTS -->
    <h2>1. Admin Accounts (Should be only 1)</h2>
    <div class="card <?php echo $results['admin_count'] > 1 ? 'danger' : 'ok'; ?>">
        <p>Total admin accounts: <strong><?php echo $results['admin_count']; ?></strong>
        <?php if ($results['admin_count'] > 1): ?>
            <span class="danger"> ⚠️ MORE THAN 1 ADMIN ACCOUNT FOUND - UNAUTHORIZED ACCESS POSSIBLE!</span>
        <?php else: ?>
            <span class="ok"> ✓ Normal (1 account)</span>
        <?php endif; ?>
        </p>
    </div>
    <table>
        <tr><th>ID</th><th>Username</th><th>Email</th><th>Full Name</th><th>Role</th><th>Last Login</th><th>Created</th><th>Last Modified</th></tr>
        <?php foreach ($results['admins'] as $admin): ?>
        <tr>
            <td><?php echo $admin['id']; ?></td>
            <td><?php echo htmlspecialchars($admin['username']); ?></td>
            <td><?php echo htmlspecialchars($admin['email']); ?></td>
            <td><?php echo htmlspecialchars($admin['full_name']); ?></td>
            <td><?php echo $admin['role']; ?></td>
            <td><?php echo $admin['last_login'] ?? 'Never'; ?></td>
            <td><?php echo $admin['created_at']; ?></td>
            <td class="<?php echo strtotime($admin['updated_at']) > strtotime('-7 days') ? 'warning' : ''; ?>"><?php echo $admin['updated_at']; ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <!-- RECENT CHANGES -->
    <h2>2. Recent Admin Account Changes</h2>
    <?php if (empty($results['recent_changes'])): ?>
        <div class="card ok">No admin account changes in the last 30 days.</div>
    <?php else: ?>
        <div class="card warning">Changes detected in the last 30 days:</div>
        <table>
            <tr><th>ID</th><th>Username</th><th>Email</th><th>Modified At</th></tr>
            <?php foreach ($results['recent_changes'] as $change): ?>
            <tr>
                <td><?php echo $change['id']; ?></td>
                <td><?php echo htmlspecialchars($change['username']); ?></td>
                <td><?php echo htmlspecialchars($change['email']); ?></td>
                <td class="warning"><?php echo $change['updated_at']; ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <!-- LAST LOGINS -->
    <h2>3. Recent Login Activity</h2>
    <?php if (empty($results['last_logins'])): ?>
        <div class="card">No login records found.</div>
    <?php else: ?>
        <table>
            <tr><th>ID</th><th>Username</th><th>Last Login</th></tr>
            <?php foreach ($results['last_logins'] as $login): ?>
            <tr>
                <td><?php echo $login['id']; ?></td>
                <td><?php echo htmlspecialchars($login['username']); ?></td>
                <td><?php echo $login['last_login']; ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <!-- SUSPICIOUS UPLOADS -->
    <h2>4. Suspicious Files in /uploads/</h2>
    <?php if (empty($results['suspicious_uploads'])): ?>
        <div class="card ok">✓ No suspicious PHP/script files found in uploads directory.</div>
    <?php else: ?>
        <div class="card danger">⚠️ DANGEROUS FILES FOUND IN UPLOADS:</div>
        <table>
            <tr><th>File Path</th><th>Modified</th><th>Size</th></tr>
            <?php foreach ($results['suspicious_uploads'] as $file): ?>
            <tr class="danger">
                <td><?php echo htmlspecialchars($file['path']); ?></td>
                <td><?php echo $file['modified']; ?></td>
                <td><?php echo $file['size']; ?> bytes</td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <!-- RECENT PHP CHANGES -->
    <h2>5. Recently Modified PHP Files (Last 7 Days)</h2>
    <?php if (empty($results['recent_php_changes'])): ?>
        <div class="card ok">No unusual PHP file modifications.</div>
    <?php else: ?>
        <table>
            <tr><th>File</th><th>Modified</th><th>Size</th></tr>
            <?php foreach ($results['recent_php_changes'] as $file): ?>
            <tr>
                <td><?php echo htmlspecialchars($file['path']); ?></td>
                <td><?php echo $file['modified']; ?></td>
                <td><?php echo $file['size']; ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <!-- SETTINGS CHANGES -->
    <h2>6. Recent Settings Changes</h2>
    <?php if (empty($results['settings_changes'])): ?>
        <div class="card ok">No settings changed in the last 7 days.</div>
    <?php else: ?>
        <table>
            <tr><th>Setting Key</th><th>Modified</th></tr>
            <?php foreach ($results['settings_changes'] as $setting): ?>
            <tr>
                <td><?php echo htmlspecialchars($setting['setting_key']); ?></td>
                <td class="warning"><?php echo $setting['updated_at']; ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <!-- DIAGNOSIS -->
    <h2>7. DIAGNOSIS</h2>
    <div class="card">
        <?php
        $issues = [];
        
        if ($results['admin_count'] > 1) {
            $issues[] = "⛔ Multiple admin accounts found - unauthorized admin created!";
        }
        
        if (!empty($results['suspicious_uploads'])) {
            $issues[] = "⛔ Suspicious PHP files found in uploads - possible backdoor!";
        }
        
        if (!empty($results['recent_changes'])) {
            $issues[] = "⚠️ Admin credentials were modified recently - check if authorized.";
        }
        
        if (empty($issues)) {
            echo '<p class="ok">✓ No obvious security issues detected from database analysis.</p>';
            echo '<p class="warning">The attacker likely changed credentials via the admin panel profile page (no CSRF protection).</p>';
        } else {
            foreach ($issues as $issue) {
                echo "<p class='danger'>$issue</p>";
            }
        }
        
        echo '<br><h3>Why Login Fails After Database Password Change:</h3>';
        echo '<p>The login uses <code>password_verify()</code> which requires a bcrypt hash.</p>';
        echo '<p>If you put a <strong>plaintext password</strong> in the database, login will ALWAYS fail.</p>';
        echo '<p>Use the <code>reset-admin.php</code> script instead to properly hash and set credentials.</p>';
        ?>
    </div>

    <h2>8. RECOMMENDED NEXT STEPS</h2>
    <div class="card">
        <ol style="line-height: 2;">
            <li>Run <a href="reset-admin.php?key=<?php echo $AUDIT_KEY; ?>" style="color: #ffd93d;">reset-admin.php</a> to set new admin credentials safely</li>
            <li>Delete this audit script: <code>admin/security-audit.php</code></li>
            <li>Delete the reset script: <code>admin/reset-admin.php</code></li>
            <li>Change the Gmail SMTP password exposed in <code>cache/settings.php</code></li>
            <li>Add CSRF protection to admin forms</li>
            <li>Remove the school templates from <code>templates/</code></li>
            <li>Set a strong MySQL password on production</li>
        </ol>
    </div>
</body>
</html>
