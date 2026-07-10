<?php
// KIZZA TOURS & SAFARIS - Booking API
// Premium East Africa Tourism Platform

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

set_exception_handler(function($e) {
    error_log("Uncaught Booking Error: " . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again or contact us via WhatsApp.']);
    exit;
});

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/mail.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Sanitize and validate inputs
    $dest_country = trim($_POST['destination_country'] ?? '');
    $dest_place = trim($_POST['destination_place'] ?? '');
    $packages = isset($_POST['packages']) ? (array)$_POST['packages'] : [];
    $packageStr = !empty($packages) ? implode(', ', $packages) : '';
    $travel_date = trim($_POST['travel_date'] ?? '');
    $guests = intval($_POST['guests'] ?? 1);
    $budget = trim($_POST['budget'] ?? '');
    $accommodation = trim($_POST['accommodation'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // Build readable destination labels
    $countryLabel = !empty($dest_country) ? ucfirst(str_replace('-', ' ', $dest_country)) : '';
    $placeLabel = !empty($dest_place) ? ucfirst(str_replace('-', ' ', $dest_place)) : '';
    $destination = !empty($countryLabel) && !empty($placeLabel) ? $countryLabel . ' - ' . $placeLabel : trim($countryLabel . ' ' . $placeLabel);

    // Validate required fields
    $errors = [];
    if (empty($dest_country)) $errors[] = 'Country is required';
    if (empty($dest_place)) $errors[] = 'Place is required';
    if (empty($full_name)) $errors[] = 'Full name is required';
    if (empty($email)) $errors[] = 'Email is required';
    if (empty($phone)) $errors[] = 'Phone is required';
    if (empty($travel_date)) $errors[] = 'Travel date is required';
    if ($guests < 1) $errors[] = 'At least 1 guest is required';

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => implode('. ', $errors)]);
        exit;
    }

    // Generate booking reference
    $reference = BOOKING_PREFIX . '-' . strtoupper(substr(uniqid(), -6)) . rand(100, 999);

    // Append destination/package/budget info to message (table has no matching varchar columns)
    $enrichedMessage = "Country: {$dest_country}\nPlace: {$dest_place}\nPackages: {$packageStr}\nBudget: {$budget}\nAccommodation: {$accommodation}\n\n{$message}";

    // Insert into database
    $db = db();
    $bookingId = $db->insert(
        "INSERT INTO bookings (booking_reference, full_name, email, phone, travel_date, guests, accommodation, message, status, source) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'website')",
        [
            $reference,
            $full_name,
            $email,
            $phone,
            $travel_date,
            $guests,
            $accommodation,
            $enrichedMessage
        ]
    );

    // Send email notification to admin
    $emailBody = "
    <html>
    <body style='font-family: Arial, sans-serif; padding: 20px;'>
        <h2 style='color: #D4AF37;'>New Booking Inquiry</h2>
        <p><strong>Reference:</strong> {$reference}</p>
        <p><strong>Name:</strong> {$full_name}</p>
        <p><strong>Email:</strong> {$email}</p>
        <p><strong>Phone:</strong> {$phone}</p>
        <p><strong>Country:</strong> {$countryLabel}</p>
        <p><strong>Place:</strong> {$placeLabel}</p>
        <p><strong>Packages:</strong> {$packageStr}</p>
        <p><strong>Travel Date:</strong> {$travel_date}</p>
        <p><strong>Guests:</strong> {$guests}</p>
        <p><strong>Budget:</strong> {$budget}</p>
        <p><strong>Accommodation:</strong> {$accommodation}</p>
        <p><strong>Message:</strong><br>{$message}</p>
        <hr>
        <p><small>This is an automated notification from Kizza Tours &amp; Safaris booking system.</small></p>
    </body>
    </html>";

    try { sendMail('kizzaadventures@gmail.com', "New Booking Inquiry - {$reference}", $emailBody, $email, $full_name); } catch (\Throwable $e) {}

    // Send confirmation email to customer
    $customerSubject = "Booking Received - {$reference} - Kizza Tours & Safaris";
    $customerBody = "
    <html>
    <body style='font-family: Arial, sans-serif; padding: 20px;'>
        <h2 style='color: #D4AF37;'>Thank You for Your Inquiry!</h2>
        <p>Dear {$full_name},</p>
        <p>Thank you for choosing Kizza Tours &amp; Safaris for your adventure journey. Please allow us a little time to prepare and send you your itinerary package.</p>
        <p><strong>Your Booking Reference:</strong> {$reference}</p>
        <p><strong>Country:</strong> {$countryLabel}</p>
        <p><strong>Place:</strong> {$placeLabel}</p>
        <p><strong>Travel Date:</strong> {$travel_date}</p>
        <p><strong>Guests:</strong> {$guests}</p>
        <p>We will contact you within 24 hours with a personalized itinerary and quotation.</p>
        <p>In the meantime, feel free to reach out to us on WhatsApp or call us directly.</p>
        <br>
        <p>Warm regards,</p>
        <p><strong>Kizza Tours &amp; Safaris Team</strong></p>
        <p><small>Email: " . SITE_EMAIL . " | Phone: " . SITE_PHONE . "</small></p>
    </body>
    </html>";

    try { sendMail($email, $customerSubject, $customerBody); } catch (\Throwable $e) {}

    echo json_encode([
        'success' => true,
        'message' => 'Thank you for choosing Kizza Tours and Safaris for your adventure journey. Please allow us a little time to prepare and send you your itinerary package.',
        'reference' => $reference,
        'booking_id' => $bookingId
    ]);

} catch (PDOException $e) {
    $msg = $e->getMessage();
    error_log("Booking PDO Error: " . $msg);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again or contact us via WhatsApp.']);
} catch (\Throwable $e) {
    $msg = $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
    error_log("Booking Error: " . $msg);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again or contact us via WhatsApp.']);
}
