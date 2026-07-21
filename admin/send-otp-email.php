<?php
// Background OTP email sender - called via AJAX after OTP form loads
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['ok' => false]));
}

// Load config (which starts session)
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mail.php';

$email = $_SESSION['otp_pending_email'] ?? '';
$otp = $_SESSION['otp_pending_otp'] ?? '';
$body = $_SESSION['otp_pending_body'] ?? '';

if (empty($email) || empty($otp)) {
    die(json_encode(['ok' => false, 'msg' => 'No pending OTP']));
}

$sent = sendMail($email, "Your Admin Login Code: " . $otp, $body);

// Clean up
unset($_SESSION['otp_pending_email'], $_SESSION['otp_pending_otp'], $_SESSION['otp_pending_body']);

echo json_encode(['ok' => $sent, 'to' => $email]);
