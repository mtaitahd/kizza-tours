<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/mail.php';
require_once '../includes/pdf_quote.php';
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: ./');
    exit;
}

$db = db();

// Ensure profile image is in session
if (empty($_SESSION['admin_image']) && isset($_SESSION['admin_id'])) {
    $row = $db->fetchOne("SELECT profile_image FROM admin_users WHERE id = ?", [$_SESSION['admin_id']]);
    $_SESSION['admin_image'] = $row['profile_image'] ?? null;
}

$currentPage = 'bookings';

function ensureQuoteTables() {
    try {
        $db = db();
        $db->fetchOne("SELECT 1 FROM quotes LIMIT 1");
        try {
            $db->query("ALTER TABLE quotes ADD COLUMN booking_id INT DEFAULT NULL AFTER inquiry_id");
        } catch (\Throwable $e) {
        }
        try {
            $db->query("ALTER TABLE quotes MODIFY COLUMN inquiry_id INT DEFAULT NULL");
        } catch (\Throwable $e) {
        }
        return true;
    } catch (\Throwable $e) {
        try {
            $schema = file_get_contents(__DIR__ . '/../database/quotes.sql');
            $db->query("DROP TABLE IF EXISTS quote_items");
            $db->query("DROP TABLE IF EXISTS quotes");
            $db->getConnection()->exec($schema);
            return true;
        } catch (\Throwable $e2) {
            error_log("ensureQuoteTables failed: " . $e2->getMessage());
            return false;
        }
    }
}

function generateQuoteNumber($db) {
    $prefix = 'QTE-' . date('Y') . '-';
    $last = $db->fetchOne("SELECT quote_number FROM quotes WHERE quote_number LIKE ? ORDER BY id DESC LIMIT 1", [$prefix . '%']);
    if ($last) {
        $num = intval(substr($last['quote_number'], strlen($prefix))) + 1;
    } else {
        $num = 1;
    }
    return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
}

function recalcQuote(&$db, $quoteId) {
    $items = $db->fetchAll("SELECT SUM(total) as subtotal FROM quote_items WHERE quote_id = ?", [$quoteId]);
    $subtotal = floatval($items[0]['subtotal'] ?? 0);
    $quote = $db->fetchOne("SELECT tax_percent, discount FROM quotes WHERE id = ?", [$quoteId]);
    $taxPercent = floatval($quote['tax_percent'] ?? 0);
    $discount = floatval($quote['discount'] ?? 0);
    $taxAmount = round($subtotal * $taxPercent / 100, 2);
    $total = $subtotal + $taxAmount - $discount;
    if ($total < 0) $total = 0;
    $db->query("UPDATE quotes SET subtotal = ?, tax_amount = ?, total = ? WHERE id = ?", [$subtotal, $taxAmount, $total, $quoteId]);
}

$quotesTablesOk = ensureQuoteTables();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $bookingId = intval($_POST['booking_id'] ?? 0);
    if ($_POST['action'] === 'update_status') {
        $status = trim($_POST['status'] ?? 'pending');
        $booking = $db->fetchOne("SELECT * FROM bookings WHERE id = ?", [$bookingId]);
        $db->query("UPDATE bookings SET status = ? WHERE id = ?", [$status, $bookingId]);

        if ($status === 'confirmed' && $booking) {
            $subject = 'Your Safari Booking is Confirmed - ' . htmlspecialchars($booking['booking_reference']);
            $body = '<html><body style="font-family:Arial,sans-serif;padding:20px;max-width:600px;margin:auto;">';
            $body .= '<div style="text-align:center;margin-bottom:20px;"><img src="' . SITE_URL . '/assets/images/log.png" alt="Kizza Tours" style="height:60px;"></div>';
            $body .= '<h1 style="color:#0A2540;text-align:center;">Booking Confirmed!</h1>';
            $body .= '<p style="font-size:1.1em;color:#333;">Dear ' . htmlspecialchars($booking['full_name']) . ',</p>';
            $body .= '<p>Great news! Your safari booking with <strong>Kizza Tours &amp; Safaris</strong> has been confirmed.</p>';
            $body .= '<div style="background:#f0f4f8;border-radius:12px;padding:20px;margin:20px 0;">';
            $body .= '<table style="width:100%;border-collapse:collapse;">';
            $body .= '<tr><td style="padding:6px 0;color:#6c757d;">Reference</td><td style="padding:6px 0;font-weight:700;color:#0A2540;">' . htmlspecialchars($booking['booking_reference']) . '</td></tr>';
            $body .= '<tr><td style="padding:6px 0;color:#6c757d;">Travel Date</td><td style="padding:6px 0;font-weight:700;color:#0A2540;">' . ($booking['travel_date'] ? date('F d, Y', strtotime($booking['travel_date'])) : 'N/A') . '</td></tr>';
            $body .= '<tr><td style="padding:6px 0;color:#6c757d;">Guests</td><td style="padding:6px 0;font-weight:700;color:#0A2540;">' . $booking['guests'] . '</td></tr>';
            if ($booking['accommodation']) {
                $body .= '<tr><td style="padding:6px 0;color:#6c757d;">Accommodation</td><td style="padding:6px 0;font-weight:700;color:#0A2540;">' . htmlspecialchars(ucfirst(str_replace('_', ' ', $booking['accommodation']))) . '</td></tr>';
            }
            $body .= '</table></div>';
            $body .= '<p>Our team will contact you shortly with further details including pickup information, packing guidelines, and your complete itinerary.</p>';
            $body .= '<p>If you have any questions before your trip, please reply to this email or contact us at <a href="tel:' . htmlspecialchars(getSetting('site_phone', SITE_PHONE)) . '" style="color:#D4AF37;text-decoration:none;">' . htmlspecialchars(getSetting('site_phone', SITE_PHONE)) . '</a>.</p>';
            $body .= '<p style="margin-top:25px;">Safe travels, and we look forward to welcoming you!</p>';
            $body .= '<p style="font-weight:700;color:#0A2540;">Kizza Tours &amp; Safaris Team</p>';
            $body .= '<hr style="border:1px solid #eee;margin:20px 0;">';
            $body .= '<p style="font-size:0.8em;color:#adb5bd;text-align:center;">' . SITE_URL . '</p>';
            $body .= '</body></html>';

            if (sendMail($booking['email'], $subject, $body)) {
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Booking confirmed & confirmation email sent to ' . htmlspecialchars($booking['email'])];
            } else {
                $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Booking confirmed but confirmation email failed to send. Check SMTP settings.'];
            }
        } else {
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Booking status updated successfully'];
        }
    } elseif ($_POST['action'] === 'delete_reply') {
        $replyId = intval($_POST['reply_id'] ?? 0);
        $db->query("DELETE FROM booking_replies WHERE id = ?", [$replyId]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Reply deleted successfully'];
    } elseif ($_POST['action'] === 'send_booking_reply') {
        $subject = trim($_POST['reply_subject'] ?? '');
        $message = trim($_POST['reply_message'] ?? '');
        $booking = $db->fetchOne("SELECT * FROM bookings WHERE id = ?", [$bookingId]);
        if ($booking && !empty($subject) && !empty($message)) {
            $body = '<html><body style="font-family:Arial,sans-serif;padding:20px;">';
            $body .= '<h2 style="color:#0A2540;">' . htmlspecialchars($subject) . '</h2>';
            $body .= '<div style="background:#f8f9fa;padding:15px;border-radius:8px;margin:15px 0;">';
            $body .= nl2br(htmlspecialchars($message));
            $body .= '</div>';
            $body .= '<hr style="border:1px solid #eee;">';
            $body .= '<p style="color:#6c757d;font-size:0.85em;">';
            $body .= '<strong>Original Booking:</strong> ' . htmlspecialchars($booking['booking_reference']) . '<br>';
            $body .= '<strong>Name:</strong> ' . htmlspecialchars($booking['full_name']) . '<br>';
            $body .= '<strong>Travel Date:</strong> ' . ($booking['travel_date'] ? date('F d, Y', strtotime($booking['travel_date'])) : 'N/A') . '<br>';
            $body .= '<strong>Guests:</strong> ' . $booking['guests'] . '<br>';
            if ($booking['message']) {
                $body .= '<strong>Customer Message:</strong><br><em>' . nl2br(htmlspecialchars($booking['message'])) . '</em>';
            }
            $body .= '</p>';
            $body .= '<p style="color:#6c757d;font-size:0.8em;">— Kizza Tours &amp; Safaris Team</p>';
            $body .= '</body></html>';

            if (sendMail($booking['email'], $subject, $body)) {
                $db->insert("INSERT INTO booking_replies (booking_id, admin_id, subject, message) VALUES (?, ?, ?, ?)",
                    [$bookingId, $_SESSION['admin_id'], $subject, $message]);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Reply sent successfully to ' . htmlspecialchars($booking['email'])];
            } else {
                $_SESSION['flash'] = ['type' => 'error', 'message' => 'Reply saved but email failed to send. Check SMTP settings.'];
                $db->insert("INSERT INTO booking_replies (booking_id, admin_id, subject, message) VALUES (?, ?, ?, ?)",
                    [$bookingId, $_SESSION['admin_id'], $subject, $message]);
            }
        } else {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Failed to send reply. Subject and message are required.'];
        }
    } elseif ($_POST['action'] === 'save_quote') {
        $bookingId = intval($_POST['booking_id'] ?? 0);
        $quoteId = intval($_POST['quote_id'] ?? 0);
        $itemsJson = $_POST['items_json'] ?? '[]';
        $items = json_decode($itemsJson, true);
        if (!is_array($items)) $items = [];

        $taxPercent = floatval($_POST['tax_percent'] ?? 0);
        $discount = floatval($_POST['discount'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        $terms = trim($_POST['terms'] ?? '');
        $validUntil = !empty($_POST['valid_until']) ? $_POST['valid_until'] : null;

        if ($quoteId) {
            $db->query("UPDATE quotes SET tax_percent = ?, discount = ?, notes = ?, terms = ?, valid_until = ? WHERE id = ?",
                [$taxPercent, $discount, $notes, $terms, $validUntil, $quoteId]);
            $db->query("DELETE FROM quote_items WHERE quote_id = ?", [$quoteId]);
        } else {
            $quoteNumber = generateQuoteNumber($db);
            $quoteId = $db->insert("INSERT INTO quotes (booking_id, quote_number, status, tax_percent, discount, notes, terms, valid_until, created_by) VALUES (?, ?, 'draft', ?, ?, ?, ?, ?, ?)",
                [$bookingId, $quoteNumber, $taxPercent, $discount, $notes, $terms, $validUntil, $_SESSION['admin_id']]);
        }

        $sortOrder = 0;
        foreach ($items as $item) {
            $desc = trim($item['description'] ?? '');
            if (empty($desc)) continue;
            $qty = max(1, intval($item['quantity'] ?? 1));
            $unitPrice = floatval($item['unit_price'] ?? 0);
            $total = round($qty * $unitPrice, 2);
            $db->query("INSERT INTO quote_items (quote_id, description, quantity, unit_price, total, sort_order) VALUES (?, ?, ?, ?, ?, ?)",
                [$quoteId, $desc, $qty, $unitPrice, $total, $sortOrder++]);
        }

        recalcQuote($db, $quoteId);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Quote saved as draft'];

    } elseif ($_POST['action'] === 'prepare_quote') {
        $quoteId = intval($_POST['quote_id'] ?? 0);
        $db->query("UPDATE quotes SET status = 'prepared' WHERE id = ?", [$quoteId]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Quote marked as prepared'];

    } elseif ($_POST['action'] === 'confirm_quote') {
        $quoteId = intval($_POST['quote_id'] ?? 0);
        $db->query("UPDATE quotes SET status = 'confirmed' WHERE id = ?", [$quoteId]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Quote confirmed'];

    } elseif ($_POST['action'] === 'delete_quote') {
        $quoteId = intval($_POST['quote_id'] ?? 0);
        $quote = $db->fetchOne("SELECT pdf_path FROM quotes WHERE id = ?", [$quoteId]);
        if ($quote && !empty($quote['pdf_path'])) {
            $pdfFile = __DIR__ . '/../' . $quote['pdf_path'];
            if (file_exists($pdfFile)) @unlink($pdfFile);
        }
        $db->query("DELETE FROM quotes WHERE id = ?", [$quoteId]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Quote deleted'];

    } elseif ($_POST['action'] === 'generate_pdf') {
        $quoteId = intval($_POST['quote_id'] ?? 0);
        try {
            $pdf = generateQuotePdf($quoteId);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'PDF generated successfully'];
        } catch (Exception $e) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'PDF generation failed: ' . $e->getMessage()];
        }

    } elseif ($_POST['action'] === 'update_payment') {
        $paymentStatus = trim($_POST['payment_status'] ?? 'unpaid');
        $db->query("UPDATE bookings SET payment_status = ? WHERE id = ?", [$paymentStatus, $bookingId]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Payment status updated to ' . ucfirst(str_replace('_', ' ', $paymentStatus))];

    } elseif ($_POST['action'] === 'send_quote_email') {
        $quoteId = intval($_POST['quote_id'] ?? 0);
        try {
            $sent = sendQuoteEmail($quoteId);
            if ($sent) {
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Quote sent via email successfully'];
            } else {
                $_SESSION['flash'] = ['type' => 'error', 'message' => 'Failed to send quote email'];
            }
        } catch (Exception $e) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Error: ' . $e->getMessage()];
        }
    }
    header('Location: bookings');
    exit;
}

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$total = $db->fetchOne("SELECT COUNT(*) as count FROM bookings")['count'];
$totalPages = ceil($total / $perPage);

$bookings = $db->fetchAll(
    "SELECT * FROM bookings ORDER BY created_at DESC LIMIT $perPage OFFSET $offset"
);

// Fetch latest reply per booking for reply history display
$bookingIds = array_column($bookings, 'id');
$replies = [];
if (!empty($bookingIds)) {
    $placeholders = implode(',', array_fill(0, count($bookingIds), '?'));
    $rows = $db->fetchAll(
        "SELECT r.*, a.username AS admin_name FROM booking_replies r 
         LEFT JOIN admin_users a ON r.admin_id = a.id 
         WHERE r.booking_id IN ($placeholders) ORDER BY r.created_at ASC",
        $bookingIds
    );
    foreach ($rows as $r) {
        $replies[$r['booking_id']][] = $r;
    }
}

// Fetch quotes per booking
$quotesByBooking = [];
if ($quotesTablesOk) {
    foreach ($bookings as $b) {
        try {
            $q = $db->fetchOne("SELECT * FROM quotes WHERE booking_id = ? ORDER BY id DESC LIMIT 1", [$b['id']]);
            if ($q) {
                $q['items'] = $db->fetchAll("SELECT * FROM quote_items WHERE quote_id = ? ORDER BY sort_order ASC", [$q['id']]);
            }
            $quotesByBooking[$b['id']] = $q;
        } catch (\Throwable $e) {
            $quotesByBooking[$b['id']] = null;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookings - Kizza Tours Admin</title>
    <link rel="icon" href="../assets/images/log.png" type="image/png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../templates/assets/css/ruang-admin.min.css" rel="stylesheet">
    <link href="css/admin.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .reply-entry { background: #f8f9fa; border-left: 3px solid #0A2540; padding: 0.75rem 1rem; margin-bottom: 0.75rem; border-radius: 0 8px 8px 0; }
        .reply-entry .reply-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.3rem; }
        .reply-entry .reply-admin { font-weight: 700; font-size: 0.85rem; color: #0A2540; }
        .reply-entry .reply-date { font-size: 0.75rem; color: #adb5bd; }
        .reply-entry .reply-subject { font-weight: 600; font-size: 0.9rem; color: #495057; margin-bottom: 0.25rem; }
        .reply-entry .reply-message { font-size: 0.85rem; color: #6c757d; line-height: 1.5; }
        .sidebar-light .sidebar-brand { background-color: #0A2540 !important; }
        .bg-navbar { background-color: #0A2540 !important; }
        #accordionSidebar { position: fixed; top: 0; left: 0; height: 100vh; z-index: 1030; overflow-y: auto; }
        #content-wrapper { margin-left: 14rem; transition: margin-left 0.3s ease-in-out; }
        body.sidebar-toggled #content-wrapper { margin-left: 6.5rem; }
        .topbar { position: fixed; top: 0; right: 0; left: 14rem; z-index: 1020; transition: left 0.3s ease-in-out; }
        body.sidebar-toggled .topbar { left: 6.5rem; }
        #content { padding-top: 70px; }
        @media (max-width: 768px) {
            #accordionSidebar { width: 0; }
            #content-wrapper { margin-left: 0; }
            body.sidebar-toggled #content-wrapper { margin-left: 0; }
            .topbar { left: 0; }
            body.sidebar-toggled .topbar { left: 0; }
        }
        .quote-section { background: #f8f9fa; border-radius: 8px; padding: 15px; margin-top: 15px; border: 1px solid #e9ecef; }
        .quote-section h6 { color: #0A2540; font-weight: 700; margin-bottom: 10px; }
        .quote-badge { font-size: 0.75rem; padding: 3px 8px; }
        .quote-items-table td, .quote-items-table th { vertical-align: middle; font-size: 0.85rem; }
        .quote-items-table .form-control-sm { font-size: 0.8rem; padding: 2px 6px; height: auto; }
        .quote-summary { background: #fff; border-radius: 6px; padding: 12px; margin-top: 10px; }
        .quote-summary .row { margin-bottom: 5px; }
        .quote-summary .total-row { font-size: 1.1rem; font-weight: 700; color: #0A2540; border-top: 2px solid #D4AF37; padding-top: 8px; }
        .quote-actions { margin-top: 12px; }
        .quote-actions .btn { font-size: 0.8rem; }
        .modal-xl .modal-dialog { max-width: 90%; }
        .item-row { transition: background 0.2s; }
        .item-row:hover { background: #f0f4ff; }
        .currency-symbol { font-weight: 600; }
        #wrapper #content-wrapper { overflow-x: visible; width: calc(100% - 14rem); }
        body.sidebar-toggled #wrapper #content-wrapper { width: calc(100% - 6.5rem); }
        #container-wrapper { max-width: 100%; width: 100%; padding-left: 1.5rem; padding-right: 1.5rem; }
        .card.mb-4 { width: 100%; max-width: 100%; }
        .card-body.p-0 { width: 100%; }
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .empty-state { padding: 60px 20px; text-align: center; color: #adb5bd; }
        .empty-state i { font-size: 3rem; margin-bottom: 15px; display: block; }
        #dataTable { width: 100%; margin-bottom: 0; }
        #dataTable th, #dataTable td { vertical-align: middle; }
        #dataTable thead th { white-space: nowrap; }
        #dataTable tbody td { white-space: nowrap; }
        #dataTable th:nth-child(1), #dataTable td:nth-child(1) { min-width: 100px; }
        #dataTable th:nth-child(2), #dataTable td:nth-child(2) { min-width: 170px; }
        #dataTable th:nth-child(3), #dataTable td:nth-child(3) { min-width: 240px; }
        #dataTable th:nth-child(4), #dataTable td:nth-child(4) { min-width: 170px; }
        #dataTable th:nth-child(5), #dataTable td:nth-child(5) { min-width: 150px; }
        #dataTable th:nth-child(6), #dataTable td:nth-child(6) { min-width: 80px; text-align: center; }
        #dataTable th:nth-child(7), #dataTable td:nth-child(7) { min-width: 120px; }
        #dataTable th:nth-child(8), #dataTable td:nth-child(8) { min-width: 120px; }
        #dataTable th:nth-child(9), #dataTable td:nth-child(9) { min-width: 120px; }
        #dataTable th:nth-child(10), #dataTable td:nth-child(10) { min-width: 220px; }
        #dataTable td.actions-cell { white-space: nowrap; }
        #dataTable td.actions-cell .btn { white-space: nowrap; }
        #dataTable .badge { white-space: nowrap; }
    </style>
</head>
<body id="page-top">
    <div id="wrapper">
        <!-- Sidebar -->
        <ul class="navbar-nav sidebar sidebar-light accordion" id="accordionSidebar">
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="#">
                <div class="sidebar-brand-icon"><img src="../assets/images/log.png" alt="Kizza Tours" height="35"></div>
                <div class="sidebar-brand-text mx-3 text-white">Admin</div>
            </a>
            <hr class="sidebar-divider my-0">
            <li class="nav-item"><a class="nav-link" href="dashboard"><i class="fas fa-fw fa-tachometer-alt"></i><span>Dashboard</span></a></li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Management</div>
            <li class="nav-item active"><a class="nav-link" href="bookings"><i class="fas fa-fw fa-calendar-check"></i><span>Bookings</span></a></li>
            <li class="nav-item"><a class="nav-link" href="tours"><i class="fas fa-fw fa-safari"></i><span>Tours</span></a></li>
            <li class="nav-item"><a class="nav-link" href="destinations"><i class="fas fa-fw fa-map-marker-alt"></i><span>Destinations</span></a></li>
            <li class="nav-item"><a class="nav-link" href="gallery"><i class="fas fa-fw fa-images"></i><span>Gallery</span></a></li>
            <li class="nav-item"><a class="nav-link" href="testimonials"><i class="fas fa-fw fa-star"></i><span>Testimonials</span></a></li>
            <li class="nav-item"><a class="nav-link" href="inquiries"><i class="fas fa-fw fa-envelope"></i><span>Inquiries</span></a></li>
            <li class="nav-item"><a class="nav-link" href="quotes"><i class="fas fa-fw fa-file-invoice"></i><span>Quotes</span></a></li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Account</div>
            <li class="nav-item"><a class="nav-link" href="profile"><i class="fas fa-fw fa-user"></i><span>My Profile</span></a></li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">System</div>
            <li class="nav-item"><a class="nav-link" href="settings"><i class="fas fa-fw fa-cog"></i><span>Settings</span></a></li>
            <li class="nav-item"><a class="nav-link" href="logout"><i class="fas fa-fw fa-sign-out-alt"></i><span>Logout</span></a></li>
            <hr class="sidebar-divider d-none d-md-block">
            <div class="version" id="version-ruangadmin">Version 1.0</div>
        </ul>

        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <nav class="navbar navbar-expand navbar-light bg-navbar topbar mb-4 static-top" style="background-color: #0A2540;">
                    <button id="sidebarToggleTop" class="btn btn-link rounded-circle mr-3"><i class="fa fa-bars text-white"></i></button>
                    <ul class="navbar-nav ml-auto">
                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="searchDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-search fa-fw text-white"></i>
                            </a>
                            <div class="dropdown-menu dropdown-menu-right p-3 shadow animated--grow-in" aria-labelledby="searchDropdown">
                                <form class="navbar-search" action="search" method="GET">
                                    <div class="input-group">
                                        <input type="text" class="form-control bg-light border-1 small" name="q" placeholder="What do you want to look for?" aria-label="Search">
                                        <div class="input-group-append">
                                            <button class="btn btn-primary" type="submit" style="background-color: #0A2540; border-color: #0A2540;">
                                                <i class="fas fa-search fa-sm"></i>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </li>
                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <?php if (!empty($_SESSION['admin_image']) && file_exists(__DIR__ . '/uploads/profile/' . $_SESSION['admin_image'])): ?>
                                    <img src="uploads/profile/<?php echo $_SESSION['admin_image']; ?>" class="rounded-circle mr-1" style="width: 30px; height: 30px; object-fit: cover;">
                                <?php else: ?>
                                    <i class="fas fa-user-circle text-white mr-1"></i>
                                <?php endif; ?>
                                <span class="ml-2 d-none d-lg-inline text-white small"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="userDropdown">
                                <a class="dropdown-item" href="profile"><i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i> Profile</a>
                                <a class="dropdown-item" href="settings"><i class="fas fa-cogs fa-sm fa-fw mr-2 text-gray-400"></i> Settings</a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="logout"><i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i> Logout</a>
                            </div>
                        </li>
                    </ul>
                </nav>

                <div class="container-fluid" id="container-wrapper">
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h4 class="mb-0 text-gray-800"><img src="../assets/images/log.png" alt="" height="40" class="mr-2"> Manage Bookings</h4>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard">Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Bookings</li>
                        </ol>
                    </div>

                    <?php if (isset($_SESSION['flash'])): ?>
                        <div class="alert alert-<?php echo $_SESSION['flash']['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
                            <?php echo htmlspecialchars($_SESSION['flash']['message']); unset($_SESSION['flash']); ?>
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                    <?php endif; ?>

                    <div class="card mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <input type="text" id="tableSearch" class="form-control form-control-sm" style="max-width: 300px;" placeholder="Filter bookings..." onkeyup="filterTable(this.value)">
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped align-middle mb-0" id="dataTable">
                                    <thead>
                                        <tr>
                                            <th>Ref</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Date</th>
                                            <th>Guests</th>
                                            <th>Status</th>
                                            <th>Payment</th>
                                            <th>Quote</th>
                                            <th class="actions-cell">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bookings as $b):
                                            $quoteData = $quotesByBooking[$b['id']] ?? null;
                                        ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($b['booking_reference']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($b['full_name']); ?></td>
                                            <td><a href="mailto:<?php echo $b['email']; ?>" style="color: #0A2540;"><?php echo htmlspecialchars($b['email']); ?></a></td>
                                            <td><?php echo htmlspecialchars($b['phone']); ?></td>
                                            <td><?php echo $b['travel_date'] ? date('M d, Y', strtotime($b['travel_date'])) : 'N/A'; ?></td>
                                            <td><?php echo $b['guests']; ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $b['status'] === 'confirmed' ? 'success' : ($b['status'] === 'pending' ? 'warning' : 'secondary'); ?>">
                                                    <?php echo ucfirst($b['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $b['payment_status'] === 'paid' ? 'success' : ($b['payment_status'] === 'partial' ? 'info' : 'secondary'); ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $b['payment_status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($quoteData): ?>
                                                    <span class="badge badge-<?php
                                                        $s = $quoteData['status'];
                                                        echo $s === 'draft' ? 'secondary' : ($s === 'prepared' ? 'info' : ($s === 'confirmed' ? 'primary' : 'success'));
                                                    ?> quote-badge"><?php echo ucfirst($quoteData['status']); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted" style="font-size:0.8rem;">None</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="actions-cell">
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-secondary" data-toggle="modal" data-target="#viewModal<?php echo $b['id']; ?>" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if ($b['status'] === 'pending'): ?>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="status" value="confirmed">
                                                        <button type="submit" class="btn btn-outline-success" title="Confirm"><i class="fas fa-check"></i></button>
                                                    </form>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="status" value="cancelled">
                                                        <button type="submit" class="btn btn-outline-danger" title="Cancel"><i class="fas fa-times"></i></button>
                                                    </form>
                                                    <?php elseif ($b['status'] === 'confirmed'): ?>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="status" value="completed">
                                                        <button type="submit" class="btn btn-outline-success" title="Complete"><i class="fas fa-check-double"></i></button>
                                                    </form>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="status" value="cancelled">
                                                        <button type="submit" class="btn btn-outline-danger" title="Cancel"><i class="fas fa-times"></i></button>
                                                    </form>
                                                    <?php elseif (in_array($b['status'], ['completed', 'cancelled'])): ?>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="status" value="pending">
                                                        <button type="submit" class="btn btn-outline-warning" title="Reopen"><i class="fas fa-undo"></i></button>
                                                    </form>
                                                    <?php endif; ?>
                                                    <?php if ($b['payment_status'] !== 'paid'): ?>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                                        <input type="hidden" name="action" value="update_payment">
                                                        <input type="hidden" name="payment_status" value="paid">
                                                        <button type="submit" class="btn btn-outline-primary" title="Mark as Paid"><i class="fas fa-credit-card"></i> Pay</button>
                                                    </form>
                                                    <?php else: ?>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                                        <input type="hidden" name="action" value="update_payment">
                                                        <input type="hidden" name="payment_status" value="unpaid">
                                                        <button type="submit" class="btn btn-outline-warning" title="Mark as Unpaid"><i class="fas fa-undo"></i></button>
                                                    </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($bookings)): ?>
                                        <tr>
                                            <td colspan="10">
                                                <div class="empty-state">
                                                    <i class="fas fa-calendar-check text-muted"></i>
                                                    <h5 class="text-muted">No bookings yet</h5>
                                                    <p class="text-muted">Bookings will appear here once customers make bookings.</p>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- View & Reply Modals (outside table) -->
                    <?php foreach ($bookings as $b):
                        $quoteData = $quotesByBooking[$b['id']] ?? null;
                    ?>
                    <div class="modal fade" id="viewModal<?php echo $b['id']; ?>" tabindex="-1">
                        <div class="modal-dialog modal-xl modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Booking - <?php echo htmlspecialchars($b['booking_reference']); ?></h5>
                                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                                </div>
                                <div class="modal-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>Name:</strong> <?php echo htmlspecialchars($b['full_name']); ?></p>
                                            <p><strong>Email:</strong> <a href="mailto:<?php echo $b['email']; ?>" style="color: #0A2540;"><?php echo htmlspecialchars($b['email']); ?></a></p>
                                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($b['phone']); ?></p>
                                            <p><strong>Travel Date:</strong> <?php echo $b['travel_date'] ? date('F d, Y', strtotime($b['travel_date'])) : 'N/A'; ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <?php
                                            $destDisplay = 'N/A';
                                            if (!empty($b['message']) && preg_match('/^Destination:\s*(.+)/m', $b['message'], $m)) {
                                                $destDisplay = trim($m[1]);
                                            } elseif (!empty($b['destination_id'])) {
                                                $destDisplay = ucfirst(str_replace('-', ' ', $b['destination_id']));
                                            }
                                            ?>
                                            <p><strong>Destination:</strong> <?php echo htmlspecialchars($destDisplay); ?></p>
                                            <p><strong>Guests:</strong> <?php echo $b['guests']; ?></p>
                                            <p><strong>Budget:</strong> <?php echo htmlspecialchars($b['budget'] ?: 'N/A'); ?></p>
                                            <p><strong>Accommodation:</strong> <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $b['accommodation'] ?? 'N/A'))); ?></p>
                                            <p><strong>Payment:</strong>
                                                <span class="badge badge-<?php echo $b['payment_status'] === 'paid' ? 'success' : ($b['payment_status'] === 'partial' ? 'info' : 'secondary'); ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $b['payment_status'])); ?>
                                                </span>
                                                <?php if ($b['payment_status'] !== 'paid'): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                                    <input type="hidden" name="action" value="update_payment">
                                                    <input type="hidden" name="payment_status" value="paid">
                                                    <button type="submit" class="btn btn-sm btn-outline-success ml-2"><i class="fas fa-credit-card"></i> Mark Paid</button>
                                                </form>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <?php if ($b['message']): ?>
                                        <div class="col-12">
                                            <p><strong>Message:</strong></p>
                                            <p style="background: #f8f9fa; padding: 1rem; border-radius: 8px;"><?php echo nl2br(htmlspecialchars($b['message'])); ?></p>
                                        </div>
                                        <?php endif; ?>
                                        <div class="col-12">
                                            <p><strong>Booked On:</strong> <?php echo date('F d, Y \a\t h:i A', strtotime($b['created_at'])); ?></p>
                                        </div>
                                    </div>

                                    <hr>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6 class="text-muted mb-3"><i class="fas fa-reply-all mr-1"></i> Reply History</h6>
                                            <?php if (!empty($replies[$b['id']])): ?>
                                            <?php foreach ($replies[$b['id']] as $r): ?>
                                            <div class="reply-entry">
                                                <div class="reply-header">
                                                    <span class="reply-admin"><?php echo htmlspecialchars($r['admin_name'] ?? 'Admin'); ?></span>
                                                    <span class="reply-date"><?php echo date('M d, Y \a\t h:i A', strtotime($r['created_at'])); ?></span>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this reply?');">
                                                        <input type="hidden" name="action" value="delete_reply">
                                                        <input type="hidden" name="reply_id" value="<?php echo $r['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-link text-danger p-0 ml-2" title="Delete reply"><i class="fas fa-trash-alt"></i></button>
                                                    </form>
                                                </div>
                                                <div class="reply-subject"><?php echo htmlspecialchars($r['subject']); ?></div>
                                                <div class="reply-message"><?php echo nl2br(htmlspecialchars($r['message'])); ?></div>
                                            </div>
                                            <?php endforeach; ?>
                                            <?php else: ?>
                                            <p class="text-muted">No replies yet.</p>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-toggle="modal" data-target="#replyModal<?php echo $b['id']; ?>"><i class="fas fa-reply"></i> Reply</button>
                                        </div>

                                        <div class="col-md-6">
                                            <h6 class="text-success"><i class="fas fa-file-invoice mr-2"></i>Quote Management</h6>
                                            <?php if ($quoteData): ?>
                                                <div class="quote-section">
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <strong>#<?php echo htmlspecialchars($quoteData['quote_number']); ?></strong>
                                                        <span class="badge badge-<?php
                                                            $s = $quoteData['status'];
                                                            echo $s === 'draft' ? 'secondary' : ($s === 'prepared' ? 'info' : ($s === 'confirmed' ? 'primary' : 'success'));
                                                        ?>"><?php echo ucfirst($s); ?></span>
                                                    </div>
                                                    <?php if (!empty($quoteData['items'])): ?>
                                                        <table class="table table-sm quote-items-table mb-2">
                                                            <thead><tr><th>#</th><th>Item</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead>
                                                            <tbody>
                                                            <?php $i = 1; foreach ($quoteData['items'] as $item): ?>
                                                                <tr>
                                                                    <td><?php echo $i++; ?></td>
                                                                    <td><?php echo htmlspecialchars($item['description']); ?></td>
                                                                    <td><?php echo (int)$item['quantity']; ?></td>
                                                                    <td><?php echo '$' . number_format($item['unit_price'], 2); ?></td>
                                                                    <td><?php echo '$' . number_format($item['total'], 2); ?></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                        <div class="text-right" style="font-size:0.85rem;">
                                                            <strong>Total: $<?php echo number_format($quoteData['total'], 2); ?></strong>
                                                        </div>
                                                    <?php else: ?>
                                                        <p class="text-muted mb-2">No items added yet.</p>
                                                    <?php endif; ?>

                                                    <div class="quote-actions">
                                                        <?php if ($quoteData['status'] === 'draft'): ?>
                                                            <button class="btn btn-sm btn-outline-primary" onclick="openBookingQuoteEditor(<?php echo $b['id']; ?>, <?php echo $quoteData['id']; ?>)"><i class="fas fa-edit"></i> Edit Quote</button>
                                                            <form method="POST" style="display:inline;">
                                                                <input type="hidden" name="action" value="prepare_quote">
                                                                <input type="hidden" name="quote_id" value="<?php echo $quoteData['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-info"><i class="fas fa-check"></i> Mark Prepared</button>
                                                            </form>
                                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this quote?');">
                                                                <input type="hidden" name="action" value="delete_quote">
                                                                <input type="hidden" name="quote_id" value="<?php echo $quoteData['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                                            </form>
                                                        <?php elseif ($quoteData['status'] === 'prepared'): ?>
                                                            <button class="btn btn-sm btn-outline-primary" onclick="openBookingQuoteEditor(<?php echo $b['id']; ?>, <?php echo $quoteData['id']; ?>)"><i class="fas fa-edit"></i> Edit</button>
                                                            <form method="POST" style="display:inline;">
                                                                <input type="hidden" name="action" value="confirm_quote">
                                                                <input type="hidden" name="quote_id" value="<?php echo $quoteData['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check-double"></i> Confirm Quote</button>
                                                            </form>
                                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this quote?');">
                                                                <input type="hidden" name="action" value="delete_quote">
                                                                <input type="hidden" name="quote_id" value="<?php echo $quoteData['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                                            </form>
                                                        <?php elseif ($quoteData['status'] === 'confirmed' || $quoteData['status'] === 'sent'): ?>
                                                            <button class="btn btn-sm btn-outline-primary" onclick="openBookingQuoteEditor(<?php echo $b['id']; ?>, <?php echo $quoteData['id']; ?>)"><i class="fas fa-edit"></i> Edit</button>
                                                            <?php if (empty($quoteData['pdf_path'])): ?>
                                                                <form method="POST" style="display:inline;">
                                                                    <input type="hidden" name="action" value="generate_pdf">
                                                                    <input type="hidden" name="quote_id" value="<?php echo $quoteData['id']; ?>">
                                                                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-file-pdf"></i> Generate PDF</button>
                                                                </form>
                                                            <?php else: ?>
                                                                <form method="POST" style="display:inline;">
                                                                    <input type="hidden" name="action" value="generate_pdf">
                                                                    <input type="hidden" name="quote_id" value="<?php echo $quoteData['id']; ?>">
                                                                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-file-pdf"></i> Regenerate PDF</button>
                                                                </form>
                                                                <a href="<?php echo '../' . $quoteData['pdf_path']; ?>" class="btn btn-sm btn-outline-primary" target="_blank"><i class="fas fa-file-pdf"></i> View PDF</a>
                                                                <a href="<?php echo '../' . $quoteData['pdf_path']; ?>" class="btn btn-sm btn-outline-secondary" download><i class="fas fa-download"></i> Download</a>
                                                            <?php endif; ?>
                                                            <form method="POST" style="display:inline;">
                                                                <input type="hidden" name="action" value="send_quote_email">
                                                                <input type="hidden" name="quote_id" value="<?php echo $quoteData['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-envelope"></i> <?php echo $quoteData['status'] === 'sent' ? 'Resend' : 'Send via'; ?> Email</button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="quote-section text-center py-3">
                                                    <p class="text-muted mb-2">No quote has been prepared for this booking yet.</p>
                                                    <button class="btn btn-sm btn-success" onclick="openBookingQuoteEditor(<?php echo $b['id']; ?>, 0)"><i class="fas fa-plus"></i> Prepare Quote</button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal fade" id="replyModal<?php echo $b['id']; ?>" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Reply to <?php echo htmlspecialchars($b['full_name']); ?></h5>
                                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                                </div>
                                <form method="POST">
                                    <div class="modal-body">
                                        <input type="hidden" name="action" value="send_booking_reply">
                                        <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                        <div class="form-group">
                                            <label class="font-weight-bold">To</label>
                                            <p class="form-control-static"><?php echo htmlspecialchars($b['full_name']); ?> &lt;<?php echo htmlspecialchars($b['email']); ?>&gt;</p>
                                        </div>
                                        <div class="form-group">
                                            <label for="reply_subject_<?php echo $b['id']; ?>">Subject <span class="text-danger">*</span></label>
                                            <input type="text" name="reply_subject" id="reply_subject_<?php echo $b['id']; ?>" class="form-control" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="reply_message_<?php echo $b['id']; ?>">Message <span class="text-danger">*</span></label>
                                            <textarea name="reply_message" id="reply_message_<?php echo $b['id']; ?>" rows="6" class="form-control" required></textarea>
                                        </div>
                                        <div class="form-group mb-0">
                                            <label>Quick Templates</label>
                                            <select class="form-control form-control-sm" onchange="fillTemplate(this, <?php echo $b['id']; ?>)">
                                                <option value="">-- Select Template --</option>
                                                <option value="thank_you">Thank You - Getting Back Soon</option>
                                                <option value="more_info">Request More Information</option>
                                                <option value="quote_sent">Quote / Itinerary Sent</option>
                                                <option value="booking_confirmed">Booking Confirmed</option>
                                                <option value="custom">Custom (Blank)</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary" style="background-color:#0A2540;border-color:#0A2540;"><i class="fas fa-paper-plane"></i> Send Reply</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($totalPages > 1): ?>
                    <nav>
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                            </li>
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>

            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright &copy; <script>document.write(new Date().getFullYear());</script> - <b>Kizza Tours & Safaris</b></span>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- Quote Editor Modal -->
    <div class="modal fade" id="bookingQuoteEditorModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-file-invoice text-success mr-2"></i>Prepare Quote</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <form method="POST" id="bookingQuoteForm">
                    <input type="hidden" name="action" value="save_quote">
                    <input type="hidden" name="booking_id" id="qBookingId" value="0">
                    <input type="hidden" name="quote_id" id="qQuoteId" value="0">
                    <div class="modal-body">
                        <p class="text-muted mb-3">Add items, set pricing, and prepare a professional quote for this booking.</p>

                        <div class="table-responsive mb-3">
                            <table class="table table-bordered quote-items-table mb-0" id="itemsTable">
                                <thead>
                                    <tr>
                                        <th style="width:5%;">#</th>
                                        <th style="width:50%;">Description</th>
                                        <th style="width:12%;">Qty</th>
                                        <th style="width:16%;">Unit Price ($)</th>
                                        <th style="width:12%;">Total</th>
                                        <th style="width:5%;"></th>
                                    </tr>
                                </thead>
                                <tbody id="itemsBody">
                                </tbody>
                            </table>
                        </div>

                        <button type="button" class="btn btn-sm btn-outline-success mb-3" onclick="addItemRow()"><i class="fas fa-plus"></i> Add Item</button>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Tax (%)</label>
                                    <input type="number" class="form-control form-control-sm" name="tax_percent" id="qTaxPercent" value="0" min="0" max="100" step="0.1" onchange="calcTotals()" onkeyup="calcTotals()">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Discount ($)</label>
                                    <input type="number" class="form-control form-control-sm" name="discount" id="qDiscount" value="0" min="0" step="0.01" onchange="calcTotals()" onkeyup="calcTotals()">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Valid Until</label>
                                    <input type="date" class="form-control form-control-sm" name="valid_until" id="qValidUntil">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Notes (optional)</label>
                                    <textarea class="form-control" name="notes" id="qNotes" rows="3" placeholder="Additional notes for the customer..."></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Terms &amp; Conditions (optional)</label>
                                    <textarea class="form-control" name="terms" id="qTerms" rows="3" placeholder="Payment terms, cancellation policy, etc..."></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="quote-summary">
                            <div class="row"><div class="col-md-8 text-right">Subtotal:</div><div class="col-md-4 text-right"><span id="qSubtotal">$0.00</span></div></div>
                            <div class="row"><div class="col-md-8 text-right">Tax:</div><div class="col-md-4 text-right"><span id="qTaxAmount">$0.00</span></div></div>
                            <div class="row"><div class="col-md-8 text-right">Discount:</div><div class="col-md-4 text-right">-<span id="qDiscountAmount">$0.00</span></div></div>
                            <div class="row total-row"><div class="col-md-8 text-right">Total:</div><div class="col-md-4 text-right"><span id="qTotal">$0.00</span></div></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Quote as Draft</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-easing/1.4.1/jquery.easing.min.js"></script>
    <script src="../templates/assets/js/ruang-admin.min.js"></script>
    <script>
        var replyTemplates = {
            thank_you: { subject: 'Thank You for Your Booking Inquiry', message: 'Dear [Name],\n\nThank you for your booking inquiry with Kizza Tours & Safaris.\n\nOur team is reviewing your request and will get back to you shortly with a custom itinerary tailored to your preferences.\n\nWe appreciate your interest in exploring East Africa with us!\n\nBest regards,\nKizza Tours & Safaris Team' },
            more_info: { subject: 'More Information Needed for Your Booking', message: 'Dear [Name],\n\nThank you for reaching out to Kizza Tours & Safaris.\n\nWe would like to gather a few more details to help us create the perfect safari experience for you. Could you kindly provide:\n\n- Preferred travel dates\n- Number of guests\n- Type of accommodation preferred\n- Any specific destinations or activities you are interested in\n\nLooking forward to hearing from you!\n\nBest regards,\nKizza Tours & Safaris Team' },
            quote_sent: { subject: 'Your Custom Safari Itinerary & Quote', message: 'Dear [Name],\n\nWe hope this message finds you well.\n\nAs promised, we have prepared a custom safari itinerary and quote based on your preferences. Please find the details attached or linked below.\n\nFeel free to reach out if you have any questions or would like to make adjustments.\n\nWe look forward to welcoming you to East Africa!\n\nBest regards,\nKizza Tours & Safaris Team' },
            booking_confirmed: { subject: 'Your Safari Booking is Confirmed!', message: 'Dear [Name],\n\nGreat news! Your safari booking with Kizza Tours & Safaris has been confirmed.\n\nWe are excited to have you on board for this incredible adventure. Our team will be in touch shortly with further details and preparation guidelines.\n\nIf you have any special requests or questions before your trip, please do not hesitate to contact us.\n\nSafe travels!\n\nBest regards,\nKizza Tours & Safaris Team' },
            custom: { subject: '', message: '' }
        };

        function fillTemplate(sel, id) {
            var val = sel.value;
            if (!val || !replyTemplates[val]) return;
            var customerName = sel.closest('.modal').querySelector('.modal-title').textContent.replace('Reply to ', '').trim();
            var tpl = replyTemplates[val];
            if (val === 'custom') {
                document.getElementById('reply_subject_' + id).value = '';
                document.getElementById('reply_message_' + id).value = '';
            } else {
                if (tpl.subject) document.getElementById('reply_subject_' + id).value = tpl.subject;
                if (tpl.message) document.getElementById('reply_message_' + id).value = tpl.message.replace(/\[Name\]/g, customerName);
            }
        }

        function filterTable(val) {
            var rows = document.querySelectorAll('#dataTable tbody tr');
            rows.forEach(function(row) { row.style.display = row.textContent.toLowerCase().indexOf(val.toLowerCase()) > -1 ? '' : 'none'; });
        }

        var itemRowIndex = 0;
        var savedItems = [];

        function addItemRow(desc, qty, price) {
            var tbody = document.getElementById('itemsBody');
            var index = itemRowIndex++;
            var tr = document.createElement('tr');
            tr.className = 'item-row';
            tr.id = 'itemRow_' + index;
            tr.innerHTML = `
                <td class="text-center item-num">${tbody.children.length + 1}</td>
                <td><input type="text" class="form-control form-control-sm item-desc" placeholder="e.g. Safari Package - 3 Days" value="${desc || ''}" required></td>
                <td><input type="number" class="form-control form-control-sm item-qty" value="${qty || 1}" min="1" onchange="calcRowTotal(this)" onkeyup="calcRowTotal(this)"></td>
                <td><input type="number" class="form-control form-control-sm item-price" value="${price || 0}" min="0" step="0.01" onchange="calcRowTotal(this)" onkeyup="calcRowTotal(this)"></td>
                <td class="text-right"><span class="item-total">$${(parseFloat(qty) * parseFloat(price)).toFixed(2)}</span></td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeItemRow(this)"><i class="fas fa-times"></i></button></td>
            `;
            tbody.appendChild(tr);
            updateItemNumbers();
            calcTotals();
        }

        function removeItemRow(btn) {
            $(btn).closest('tr').remove();
            updateItemNumbers();
            calcTotals();
        }

        function updateItemNumbers() {
            var rows = document.querySelectorAll('#itemsBody .item-row');
            rows.forEach(function(row, idx) {
                row.querySelector('.item-num').textContent = idx + 1;
            });
        }

        function calcRowTotal(el) {
            var tr = el.closest('tr');
            var qty = parseFloat(tr.querySelector('.item-qty').value) || 0;
            var price = parseFloat(tr.querySelector('.item-price').value) || 0;
            tr.querySelector('.item-total').textContent = '$' + (qty * price).toFixed(2);
            calcTotals();
        }

        function calcTotals() {
            var totals = document.querySelectorAll('#itemsBody .item-total');
            var subtotal = 0;
            totals.forEach(function(el) {
                subtotal += parseFloat(el.textContent.replace('$', '')) || 0;
            });

            var taxPercent = parseFloat(document.getElementById('qTaxPercent').value) || 0;
            var discount = parseFloat(document.getElementById('qDiscount').value) || 0;
            var taxAmount = subtotal * taxPercent / 100;
            var total = subtotal + taxAmount - discount;
            if (total < 0) total = 0;

            document.getElementById('qSubtotal').textContent = '$' + subtotal.toFixed(2);
            document.getElementById('qTaxAmount').textContent = '$' + taxAmount.toFixed(2);
            document.getElementById('qDiscountAmount').textContent = discount.toFixed(2);
            document.getElementById('qTotal').textContent = '$' + total.toFixed(2);
        }

        function openBookingQuoteEditor(bookingId, quoteId) {
            document.getElementById('qBookingId').value = bookingId;
            document.getElementById('qQuoteId').value = quoteId;
            document.getElementById('itemsBody').innerHTML = '';
            itemRowIndex = 0;

            document.getElementById('qTaxPercent').value = '0';
            document.getElementById('qDiscount').value = '0';
            document.getElementById('qValidUntil').value = '';
            document.getElementById('qNotes').value = '';
            document.getElementById('qTerms').value = '';
            calcTotals();

            if (quoteId > 0) {
                var modal = document.getElementById('viewModal' + bookingId);
                var quoteSection = modal.querySelector('.quote-section');
                if (quoteSection) {
                    var items = quoteSection.querySelectorAll('.quote-items-table tbody tr');
                    items.forEach(function(row) {
                        var cells = row.querySelectorAll('td');
                        if (cells.length >= 5) {
                            var desc = cells[1].textContent.trim();
                            var qty = cells[2].textContent.trim();
                            var price = cells[3].textContent.replace('$', '').trim();
                            addItemRow(desc, qty, price);
                        }
                    });
                }
                if (document.querySelectorAll('#itemsBody .item-row').length === 0) {
                    addItemRow('', 1, 0);
                }
            } else {
                addItemRow('', 1, 0);
            }

            $('#bookingQuoteEditorModal').modal('show');
        }

        $('#bookingQuoteForm').on('submit', function() {
            var items = [];
            document.querySelectorAll('#itemsBody .item-row').forEach(function(row) {
                var desc = row.querySelector('.item-desc').value.trim();
                var qty = row.querySelector('.item-qty').value;
                var price = row.querySelector('.item-price').value;
                if (desc) {
                    items.push({ description: desc, quantity: qty, unit_price: price });
                }
            });
            if (items.length === 0) {
                alert('Please add at least one item to the quote.');
                return false;
            }
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'items_json';
            input.value = JSON.stringify(items);
            this.appendChild(input);
            return true;
        });

        $('#bookingQuoteEditorModal').on('hidden.bs.modal', function() {
            location.reload();
        });
    </script>
</body>
</html>
