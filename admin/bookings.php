<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/mail.php';
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
        .sidebar-brand { background-color: #0A2540 !important; }
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
    </style>
</head>
<body id="page-top">
    <div id="wrapper">
        <!-- Sidebar -->
        <ul class="navbar-nav sidebar sidebar-light accordion" id="accordionSidebar">
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="#" style="background-color: #0A2540;">
                <div class="sidebar-brand-icon"><img src="../assets/images/log.png" alt="Kizza Tours" height="35" style="border-radius: 50%;"></div>
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
                        <h4 class="mb-0 text-gray-800"><img src="../assets/images/log.png" alt="" height="32" class="mr-2" style="border-radius:6px;"> Manage Bookings</h4>
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
                                <table class="table table-striped mb-0" id="dataTable">
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
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bookings as $b): ?>
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
                                                <div class="d-flex">
                                                    <button class="btn btn-sm btn-outline-secondary mr-1" data-toggle="modal" data-target="#viewModal<?php echo $b['id']; ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <select name="status" class="form-control form-control-sm" style="width:auto;display:inline-block;" onchange="this.form.submit()">
                                                            <option value="pending" <?php echo $b['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                            <option value="confirmed" <?php echo $b['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                                            <option value="completed" <?php echo $b['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                            <option value="cancelled" <?php echo $b['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                        </select>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- View Modal -->
                                        <div class="modal fade" id="viewModal<?php echo $b['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg modal-dialog-centered">
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
                                                                <p><strong>Destination:</strong> <?php echo htmlspecialchars(ucfirst(str_replace('-', ' ', $b['destination_id'] ?? 'N/A'))); ?></p>
                                                                <p><strong>Guests:</strong> <?php echo $b['guests']; ?></p>
                                                                <p><strong>Budget:</strong> <?php echo htmlspecialchars($b['budget'] ?: 'N/A'); ?></p>
                                                                <p><strong>Accommodation:</strong> <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $b['accommodation'] ?? 'N/A'))); ?></p>
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

                                                        <?php if (!empty($replies[$b['id']])): ?>
                                                        <hr>
                                                        <h6 class="text-muted mb-3"><i class="fas fa-reply-all mr-1"></i> Reply History</h6>
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
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                        <button type="button" class="btn btn-outline-primary" data-toggle="modal" data-target="#replyModal<?php echo $b['id']; ?>"><i class="fas fa-reply"></i> Reply</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Reply Modal -->
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
                                        <?php if (empty($bookings)): ?>
                                        <tr><td colspan="9" class="text-center py-4 text-muted">No bookings found</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
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
    </script>
</body>
</html>
