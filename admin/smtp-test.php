<?php
// SMTP TEST - DELETE THIS FILE AFTER TESTING
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(20);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mail.php';

echo "<h2>SMTP Test</h2>";

// Show current settings (masked password)
$pass = getSetting('smtp_pass', '');
$maskedPass = substr($pass, 0, 3) . str_repeat('*', max(strlen($pass) - 3, 3));
echo "<p><b>Host:</b> " . getSetting('smtp_host') . "</p>";
echo "<p><b>Port:</b> " . getSetting('smtp_port') . "</p>";
echo "<p><b>User:</b> " . getSetting('smtp_user') . "</p>";
echo "<p><b>Pass:</b> " . htmlspecialchars($maskedPass) . "</p>";
echo "<p><b>Encryption:</b> " . getSetting('smtp_encryption') . "</p>";
echo "<hr>";

echo "<p>Sending test email...</p>";
flush();

$start = time();
$result = sendMail('info@kizzatoursandsafaris.com', 'SMTP Test - Kizza Tours', '<h2>SMTP is working!</h2><p>Sent at: ' . date('Y-m-d H:i:s') . '</p>');
$elapsed = time() - $start;

if ($result) {
    echo "<p style='color:green'><b>Email sent successfully!</b> ({$elapsed}s)</p>";
    echo "<p>Check your inbox AND spam folder.</p>";
} else {
    echo "<p style='color:red'><b>Email FAILED to send.</b> ({$elapsed}s)</p>";
    echo "<p>Check error_log for details.</p>";
}

echo "<hr>";
echo "<p><a href='smtp-test'>Try Again</a> | <a href='dashboard'>Dashboard</a></p>";
echo "<p style='color:red'><b>DELETE this file after testing!</b></p>";
