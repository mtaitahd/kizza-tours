<?php
// Shared admin identity, permission checks, and append-only activity logging.
// Requires includes/config.php and includes/db.php to have been loaded first.

function adminCurrentUser($refresh = false)
{
    static $current = null;
    if ($current !== null && !$refresh) return $current;

    $adminId = (int)($_SESSION['admin_id'] ?? 0);
    if ($adminId <= 0) return null;

    try {
        $current = db()->fetchOne(
            "SELECT id, username, email, full_name, role, is_active, session_version, profile_image FROM admin_users WHERE id = ? LIMIT 1",
            [$adminId]
        );
    } catch (Throwable $e) {
        error_log('Admin identity lookup failed: ' . $e->getMessage());
        http_response_code(503);
        exit('Admin access is temporarily unavailable. Apply the user-management database migration and try again.');
    }

    if (!$current || (int)$current['is_active'] !== 1 || (isset($_SESSION['admin_auth_version']) && (int)$_SESSION['admin_auth_version'] !== (int)$current['session_version'])) {
        unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_role'], $_SESSION['admin_image'], $_SESSION['admin_auth_version']);
        if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
        header('Location: ./');
        exit;
    }

    $_SESSION['admin_auth_version'] = (int)$current['session_version'];
    $_SESSION['admin_name'] = $current['full_name'];
    $_SESSION['admin_role'] = $current['role'];
    $_SESSION['admin_image'] = $current['profile_image'] ?? null;
    return $current;
}

function adminRequireLogin()
{
    if (empty($_SESSION['admin_id'])) {
        header('Location: ./');
        exit;
    }
    return adminCurrentUser();
}

function adminIsOwner()
{
    $admin = adminRequireLogin();
    return $admin && $admin['role'] === 'super_admin';
}

function adminCan($permissionCode)
{
    $admin = adminRequireLogin();
    if ($admin['role'] === 'super_admin') return true;

    static $permissionCache = [];
    $key = (int)$admin['id'] . ':' . (string)$permissionCode;
    if (array_key_exists($key, $permissionCache)) return $permissionCache[$key];

    try {
        $row = db()->fetchOne(
            "SELECT 1 AS allowed FROM admin_user_permissions WHERE admin_id = ? AND permission_code = ? LIMIT 1",
            [(int)$admin['id'], (string)$permissionCode]
        );
    } catch (Throwable $e) {
        error_log('Admin permission lookup failed: ' . $e->getMessage());
        http_response_code(503);
        exit('Admin permissions are unavailable. Apply the user-management database migration and try again.');
    }

    return $permissionCache[$key] = (bool)$row;
}

function requireAdminPermission($permissionCode)
{
    if (!adminCan($permissionCode)) {
        http_response_code(403);
        exit('You do not have permission to access this section.');
    }
}

function requireAdminOwner()
{
    if (!adminIsOwner()) {
        http_response_code(403);
        exit('Only the owner can access this section.');
    }
}

function adminActivityText($value, $limit)
{
    $value = (string)$value;
    return function_exists('mb_substr') ? mb_substr($value, 0, $limit) : substr($value, 0, $limit);
}

function adminLogActivity($action, $module, $recordId = null, $recordTitle = null, array $metadata = [])
{
    static $actor = null;
    if ($actor === null) {
        $adminId = (int)($_SESSION['admin_id'] ?? 0);
        if ($adminId <= 0) return false;
        try {
            $actor = db()->fetchOne(
                "SELECT id, full_name, is_active, session_version FROM admin_users WHERE id = ? LIMIT 1",
                [$adminId]
            );
        } catch (Throwable $e) {
            error_log('Admin activity actor lookup failed: ' . $e->getMessage());
            return false;
        }
    }
    if (!$actor || (int)$actor['is_active'] !== 1) return false;
    if (isset($_SESSION['admin_auth_version']) && (int)$_SESSION['admin_auth_version'] !== (int)$actor['session_version']) return false;

    $safeMetadata = [];
    foreach ($metadata as $key => $value) {
        if (!preg_match('/^[a-z0-9_]{1,48}$/i', (string)$key)) continue;
        if (is_scalar($value) || $value === null) {
            $safeMetadata[$key] = is_string($value) ? adminActivityText($value, 180) : $value;
        }
    }

    try {
        db()->query(
            "INSERT INTO admin_activity_log
                (actor_admin_id, actor_name, action, module, record_id, record_title, metadata, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                (int)$actor['id'],
                adminActivityText($actor['full_name'], 100),
                adminActivityText($action, 40),
                adminActivityText($module, 40),
                $recordId !== null ? (int)$recordId : null,
                $recordTitle !== null ? adminActivityText($recordTitle, 255) : null,
                $safeMetadata ? json_encode($safeMetadata, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : null,
                filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ? $_SERVER['REMOTE_ADDR'] : null,
            ]
        );
        return true;
    } catch (Throwable $e) {
        // Activity capture must not turn a successful existing CRUD operation into a failure.
        error_log('Admin activity log write failed: ' . $e->getMessage());
        return false;
    }
}

function adminOwnerMenu()
{
    if (!adminIsOwner()) return '';
    return '<hr class="sidebar-divider"><div class="sidebar-heading">Owner</div>' .
        '<li class="nav-item"><a class="nav-link" href="users"><i class="fas fa-fw fa-users-cog"></i><span>Users</span></a></li>' .
        '<li class="nav-item"><a class="nav-link" href="activity"><i class="fas fa-fw fa-clipboard-list"></i><span>Activity Log</span></a></li>';
}

