<?php
// KIZZA TOURS & SAFARIS - Admin Logout
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once __DIR__ . '/../includes/admin-auth.php';

if (!empty($_SESSION['admin_id'])) {
    try { adminLogActivity('logout', 'logout'); } catch (Throwable $e) { error_log('Admin logout log failed: ' . $e->getMessage()); }
}

$_SESSION = [];

// Remove the session cookie so the browser also forgets it.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();

header('Location: ./');
exit;
