<?php
// SMTP TEST - DELETE THIS FILE AFTER TESTING
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/mail.php';

$result = sendMail('info@kizzatoursandsafaris.com', 'SMTP Test from Kizza Tours', '<h2>It works!</h2><p>If you see this, SMTP is configured correctly.</p>');

if ($result) {
    echo 'Email sent successfully! Check your inbox and spam folder.';
} else {
    echo 'Email FAILED to send. Check SMTP settings.';
    echo '<br>SMTP Host: ' . getSetting('smtp_host');
    echo '<br>SMTP User: ' . getSetting('smtp_user');
    echo '<br>SMTP Port: ' . getSetting('smtp_port');
}
