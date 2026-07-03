<?php
// KIZZA TOURS & SAFARIS - Contact API
// Premium East Africa Tourism Platform

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/mail.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // Validate
    $errors = [];
    if (empty($full_name)) $errors[] = 'Name is required';
    if (empty($email)) $errors[] = 'Email is required';
    if (empty($message)) $errors[] = 'Message is required';
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => implode('. ', $errors)]);
        exit;
    }

    // Insert inquiry
    $db = db();
    $db->insert(
        "INSERT INTO inquiries (full_name, email, phone, subject, message, status, source) 
         VALUES (?, ?, ?, ?, ?, 'new', 'contact_form')",
        [$full_name, $email, $phone, $subject, $message]
    );

    // Send email notification
    $emailBody = "
    <html>
    <body style='font-family: Arial, sans-serif; padding: 20px;'>
        <h2 style='color: #D4AF37;'>New Contact Inquiry</h2>
        <p><strong>Name:</strong> {$full_name}</p>
        <p><strong>Email:</strong> {$email}</p>
        <p><strong>Phone:</strong> {$phone}</p>
        <p><strong>Subject:</strong> {$subject}</p>
        <p><strong>Message:</strong><br>{$message}</p>
    </body>
    </html>";

    sendMail(SITE_EMAIL, "New Inquiry from {$full_name}", $emailBody, $email, $full_name);

    echo json_encode([
        'success' => true,
        'message' => 'Message sent successfully! We will get back to you within 24 hours.'
    ]);

} catch (Exception $e) {
    error_log("Contact Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
