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

    if (empty($_SESSION['admin_image']) && isset($_SESSION['admin_id'])) {
        $row = $db->fetchOne("SELECT profile_image FROM admin_users WHERE id = ?", [$_SESSION['admin_id']]);
        $_SESSION['admin_image'] = $row['profile_image'] ?? null;
    }

    require_once '../includes/pdf_quote.php';

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
$quotesTablesOk = ensureQuoteTables();

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = intval($_POST['id'] ?? 0);

    if ($_POST['action'] === 'mark_read') {
        $db->query("UPDATE inquiries SET status = 'read' WHERE id = ?", [$id]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Marked as read'];

    } elseif ($_POST['action'] === 'mark_replied') {
        $db->query("UPDATE inquiries SET status = 'replied' WHERE id = ?", [$id]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Updated successfully'];

    } elseif ($_POST['action'] === 'delete') {
        $db->query("DELETE FROM inquiries WHERE id = ?", [$id]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Deleted successfully'];

    } elseif ($_POST['action'] === 'send_reply') {
        $inqId = intval($_POST['inq_id'] ?? 0);
        $replySubject = trim($_POST['reply_subject'] ?? '');
        $replyMessage = trim($_POST['reply_message'] ?? '');
        $inq = $db->fetchOne("SELECT * FROM inquiries WHERE id = ?", [$inqId]);
        if ($inq && !empty($replySubject) && !empty($replyMessage)) {
            $body = "
            <html>
            <body style='font-family: Arial, sans-serif; padding: 20px;'>
                <h2 style='color: #D4AF37;'>" . htmlspecialchars($replySubject) . "</h2>
                <p>" . nl2br(htmlspecialchars($replyMessage)) . "</p>
                <hr>
                <p><small>Original message from " . htmlspecialchars($inq['full_name']) . ":</small></p>
                <blockquote style='border-left: 3px solid #D4AF37; padding-left: 15px; color: #666; font-size: 0.9em;'>" . nl2br(htmlspecialchars($inq['message'])) . "</blockquote>
                <hr>
                <p style='font-size: 0.85em; color: #888;'>Kizza Tours &amp; Safaris</p>
            </body>
            </html>";
            sendMail($inq['email'], $replySubject, $body);
            $db->query("UPDATE inquiries SET status = 'replied' WHERE id = ?", [$inqId]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Reply sent successfully'];
        } else {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Failed to send reply'];
        }

    } elseif ($_POST['action'] === 'save_quote') {
        $inqId = intval($_POST['inq_id'] ?? 0);
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
            $quoteId = $db->insert("INSERT INTO quotes (inquiry_id, quote_number, status, tax_percent, discount, notes, terms, valid_until, created_by) VALUES (?, ?, 'draft', ?, ?, ?, ?, ?, ?)",
                [$inqId, $quoteNumber, $taxPercent, $discount, $notes, $terms, $validUntil, $_SESSION['admin_id']]);
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

    header('Location: inquiries');
    exit;
}

$inquiries = $db->fetchAll("SELECT * FROM inquiries ORDER BY created_at DESC");

$quotesByInquiry = [];
if ($quotesTablesOk) {
    foreach ($inquiries as $inq) {
        try {
            $q = $db->fetchOne("SELECT * FROM quotes WHERE inquiry_id = ? ORDER BY id DESC LIMIT 1", [$inq['id']]);
            if ($q) {
                $q['items'] = $db->fetchAll("SELECT * FROM quote_items WHERE quote_id = ? ORDER BY sort_order ASC", [$q['id']]);
            }
            $quotesByInquiry[$inq['id']] = $q;
        } catch (\Throwable $e) {
            $quotesByInquiry[$inq['id']] = null;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inquiries - Kizza Tours Admin</title>
    <link rel="icon" href="../assets/images/log.png" type="image/png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../templates/assets/css/ruang-admin.min.css" rel="stylesheet">
    <link href="css/admin.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .sidebar-light .sidebar-brand { background: none !important; }
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
        .flash-error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .currency-symbol { font-weight: 600; }
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        #dataTable { table-layout: fixed; width: 100%; }
        #dataTable td, #dataTable th { overflow-wrap: break-word; word-wrap: break-word; word-break: break-word; }
        #dataTable td.actions-cell, #dataTable th.actions-cell { white-space: nowrap; width: 1%; }
        #dataTable td.actions-cell .btn { white-space: nowrap; }
    </style>
</head>
<body id="page-top">
    <div id="wrapper">
        <ul class="navbar-nav sidebar sidebar-light accordion" id="accordionSidebar">
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="#">
                <div class="sidebar-brand-icon"><img src="../assets/images/log.png" alt="Kizza Tours" height="35"></div>
                <div class="sidebar-brand-text mx-3 text-white">Admin</div>
            </a>
            <hr class="sidebar-divider my-0">
            <li class="nav-item"><a class="nav-link" href="dashboard"><i class="fas fa-fw fa-tachometer-alt"></i><span>Dashboard</span></a></li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Management</div>
            <li class="nav-item"><a class="nav-link" href="bookings"><i class="fas fa-fw fa-calendar-check"></i><span>Bookings</span></a></li>
            <li class="nav-item"><a class="nav-link" href="tours"><i class="fas fa-fw fa-safari"></i><span>Tours</span></a></li>
            <li class="nav-item"><a class="nav-link" href="destinations"><i class="fas fa-fw fa-map-marker-alt"></i><span>Destinations</span></a></li>
            <li class="nav-item"><a class="nav-link" href="gallery"><i class="fas fa-fw fa-images"></i><span>Gallery</span></a></li>
            <li class="nav-item"><a class="nav-link" href="testimonials"><i class="fas fa-fw fa-star"></i><span>Testimonials</span></a></li>
            <li class="nav-item active"><a class="nav-link" href="inquiries"><i class="fas fa-fw fa-envelope"></i><span>Inquiries</span></a></li>
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
                        <h4 class="mb-0 text-gray-800"><img src="../assets/images/log.png" alt="" height="32" class="mr-2"> Manage Inquiries</h4>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard">Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Inquiries</li>
                        </ol>
                    </div>
                    
                    <?php if (isset($_SESSION['flash'])): ?>
                        <div class="alert <?php echo isset($_SESSION['flash']['type']) && $_SESSION['flash']['type'] === 'error' ? 'alert-danger flash-error' : 'alert-success'; ?> alert-dismissible fade show">
                            <?php echo htmlspecialchars($_SESSION['flash']['message']); unset($_SESSION['flash']); ?>
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($dompdfError) && $dompdfError): ?>
                        <div class="alert alert-warning alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle mr-2"></i> <strong>PDF Library Unavailable:</strong> <?php echo htmlspecialchars($dompdfError); ?> ΓÇö PDF generation and email will not work.
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($phpmailerError) && $phpmailerError): ?>
                        <div class="alert alert-warning alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle mr-2"></i> <strong>Mail Library Unavailable:</strong> <?php echo htmlspecialchars($phpmailerError); ?> ΓÇö email sending will not work.
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                    <?php endif; ?>

                    <div class="card mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <input type="text" id="tableSearch" class="form-control form-control-sm" style="max-width: 300px;" placeholder="Filter inquiries..." onkeyup="filterTable(this.value)">
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0" id="dataTable">
                                    <thead>
                                        <tr>
                                            <th style="width:16%">Name</th>
                                            <th style="width:18%">Email</th>
                                            <th style="width:13%">Phone</th>
                                            <th style="width:25%">Subject</th>
                                            <th style="width:11%">Date</th>
                                            <th style="width:8%">Status</th>
                                            <th style="width:8%">Quote</th>
                                            <th class="actions-cell">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($inquiries as $inq):
                                            $quoteData = $quotesByInquiry[$inq['id']] ?? null;
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($inq['full_name']); ?></td>
                                            <td><a href="mailto:<?php echo $inq['email']; ?>" style="color: #0A2540;"><?php echo htmlspecialchars($inq['email']); ?></a></td>
                                            <td><?php echo htmlspecialchars($inq['phone'] ?: '-'); ?></td>
                                            <td><?php echo htmlspecialchars($inq['subject'] ?: '-'); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($inq['created_at'])); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $inq['status'] === 'new' ? 'warning' : ($inq['status'] === 'read' ? 'info' : 'success'); ?>">
                                                    <?php echo ucfirst($inq['status']); ?>
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
                                                <div class="d-flex">
                                                    <button class="btn btn-sm btn-outline-secondary mr-1" data-toggle="modal" data-target="#inqModal<?php echo $inq['id']; ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <form method="POST" style="display:inline;" class="mr-1">
                                                        <input type="hidden" name="id" value="<?php echo $inq['id']; ?>">
                                                        <input type="hidden" name="action" value="mark_read">
                                                        <button type="submit" class="btn btn-sm btn-outline-info" title="Mark as read"><i class="fas fa-check"></i></button>
                                                    </form>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this inquiry?');">
                                                        <input type="hidden" name="id" value="<?php echo $inq['id']; ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($inquiries)): ?>
                                        <tr><td colspan="8" class="text-center py-4 text-muted">No inquiries yet</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- View Modals (outside table) -->
                    <?php foreach ($inquiries as $inq):
                        $quoteData = $quotesByInquiry[$inq['id']] ?? null;
                    ?>
                    <div class="modal fade" id="inqModal<?php echo $inq['id']; ?>" tabindex="-1">
                        <div class="modal-dialog modal-xl modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Inquiry from <?php echo htmlspecialchars($inq['full_name']); ?></h5>
                                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                                </div>
                                <div class="modal-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>Email:</strong> <a href="mailto:<?php echo $inq['email']; ?>" style="color: #0A2540;"><?php echo htmlspecialchars($inq['email']); ?></a></p>
                                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($inq['phone'] ?: 'N/A'); ?></p>
                                            <p><strong>Subject:</strong> <?php echo htmlspecialchars($inq['subject'] ?: 'N/A'); ?></p>
                                            <p><strong>Date:</strong> <?php echo date('F d, Y \a\t h:i A', strtotime($inq['created_at'])); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Message:</strong></p>
                                            <p style="background: #f8f9fa; padding: 1rem; border-radius: 8px;"><?php echo nl2br(htmlspecialchars($inq['message'])); ?></p>
                                        </div>
                                    </div>

                                    <hr>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6 class="text-primary"><i class="fas fa-reply mr-2"></i>Send Email Reply</h6>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="send_reply">
                                                <input type="hidden" name="inq_id" value="<?php echo $inq['id']; ?>">
                                                <div class="form-group">
                                                    <label><strong>Template</strong></label>
                                                    <select class="form-control" onchange="fillTemplate(this, <?php echo $inq['id']; ?>)">
                                                        <option value="">-- Select a template --</option>
                                                        <option value="thank_you">Thank You - Getting back soon</option>
                                                        <option value="more_info">Request More Information</option>
                                                        <option value="quote_sent">Quote / Itinerary Sent</option>
                                                        <option value="booking_confirmed">Booking Confirmed</option>
                                                        <option value="custom">Custom (blank)</option>
                                                    </select>
                                                </div>
                                                <div class="form-group">
                                                    <label><strong>Reply Subject</strong></label>
                                                    <input type="text" class="form-control" name="reply_subject" id="reply_subject_<?php echo $inq['id']; ?>" value="Re: <?php echo htmlspecialchars($inq['subject'] ?: 'Your Inquiry'); ?>" required>
                                                </div>
                                                <div class="form-group">
                                                    <label><strong>Reply Message</strong></label>
                                                    <textarea class="form-control" name="reply_message" id="reply_message_<?php echo $inq['id']; ?>" rows="6" placeholder="Type your reply..." required></textarea>
                                                </div>
                                                <button type="submit" class="btn btn-gold text-white" style="background-color: #D4AF37; border: none;"><i class="fas fa-paper-plane"></i> Send Reply via Email</button>
                                            </form>
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
                                                            <button class="btn btn-sm btn-outline-primary" onclick="openQuoteEditor(<?php echo $inq['id']; ?>, <?php echo $quoteData['id']; ?>)"><i class="fas fa-edit"></i> Edit Quote</button>
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
                                                            <button class="btn btn-sm btn-outline-primary" onclick="openQuoteEditor(<?php echo $inq['id']; ?>, <?php echo $quoteData['id']; ?>)"><i class="fas fa-edit"></i> Edit</button>
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
                                                            <button class="btn btn-sm btn-outline-primary" onclick="openQuoteEditor(<?php echo $inq['id']; ?>, <?php echo $quoteData['id']; ?>)"><i class="fas fa-edit"></i> Edit</button>
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
                                                    <p class="text-muted mb-2">No quote has been prepared for this inquiry yet.</p>
                                                    <button class="btn btn-sm btn-success" onclick="openQuoteEditor(<?php echo $inq['id']; ?>, 0)"><i class="fas fa-plus"></i> Prepare Quote</button>
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
                    <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
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
    <div class="modal fade" id="quoteEditorModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-file-invoice text-success mr-2"></i>Prepare Quote</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <form method="POST" id="quoteForm">
                    <input type="hidden" name="action" value="save_quote">
                    <input type="hidden" name="inq_id" id="qInqId" value="0">
                    <input type="hidden" name="quote_id" id="qQuoteId" value="0">
                    <div class="modal-body">
                        <p class="text-muted mb-3">Add items, set pricing, and prepare a professional quote for this inquiry.</p>

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
        function filterTable(val) {
            var rows = document.querySelectorAll('#dataTable tbody tr');
            rows.forEach(function(row) { row.style.display = row.textContent.toLowerCase().indexOf(val.toLowerCase()) > -1 ? '' : 'none'; });
        }

        var templates = {
            thank_you: {
                subject: 'Thank you for contacting Kizza Tours & Safaris',
                message: 'Dear [Name],\n\nThank you for reaching out to Kizza Tours & Safaris!\n\nWe have received your inquiry and our team is reviewing it. We will get back to you within 24 hours with a personalized response.\n\nIn the meantime, if you have any urgent questions, please feel free to contact us via WhatsApp or phone.\n\nWarm regards,\nKizza Tours & Safaris Team'
            },
            more_info: {
                subject: 'More information needed for your inquiry',
                message: 'Dear [Name],\n\nThank you for your interest in Kizza Tours & Safaris!\n\nTo provide you with the best possible quote and itinerary, could you please share a few more details?\n\n- Your preferred travel dates\n- Number of travelers\n- Any specific destinations or activities you are interested in\n- Your approximate budget range\n\nOnce we have these details, we will send you a customized proposal.\n\nLooking forward to helping you plan your adventure!\n\nBest regards,\nKizza Tours & Safaris Team'
            },
            quote_sent: {
                subject: 'Your custom itinerary and quote from Kizza Tours & Safaris',
                message: 'Dear [Name],\n\nThank you for choosing Kizza Tours & Safaris!\n\nWe are pleased to attach your personalized itinerary and quotation. This package has been carefully designed to give you the best experience of East Africa.\n\nPlease review the details and let us know if you would like to make any adjustments. To confirm your booking, simply reply to this email or contact us via WhatsApp.\n\nWe look forward to welcoming you to East Africa!\n\nWarm regards,\nKizza Tours & Safaris Team'
            },
            booking_confirmed: {
                subject: 'Your booking is confirmed with Kizza Tours & Safaris!',
                message: 'Dear [Name],\n\nGreat news! Your booking with Kizza Tours & Safaris has been confirmed.\n\nWe are excited to host you on this amazing journey. Our team will be in touch closer to your travel date with all the necessary details, including your pick-up information and travel checklist.\n\nIf you have any questions in the meantime, please do not hesitate to reach out.\n\nSafe travels, and see you soon!\n\nBest regards,\nKizza Tours & Safaris Team'
            },
            custom: {
                subject: '',
                message: ''
            }
        };

        function fillTemplate(sel, id) {
            var val = sel.value;
            if (!val || !templates[val]) return;
            var inquirerName = sel.closest('.modal').querySelector('.modal-title').textContent.replace('Inquiry from ', '').trim();
            var tpl = templates[val];
            if (tpl.subject) document.getElementById('reply_subject_' + id).value = tpl.subject;
            if (tpl.message) document.getElementById('reply_message_' + id).value = tpl.message.replace(/\[Name\]/g, inquirerName);
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

        function openQuoteEditor(inqId, quoteId) {
            document.getElementById('qInqId').value = inqId;
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
                var modal = document.getElementById('inqModal' + inqId);
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

            $('#quoteEditorModal').modal('show');
        }

        $('#quoteForm').on('submit', function() {
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

        $('#quoteEditorModal').on('hidden.bs.modal', function() {
            location.reload();
        });
    </script>
</body>
</html>
