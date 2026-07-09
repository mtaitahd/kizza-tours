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

if (empty($_SESSION['admin_image']) && isset($_SESSION['admin_id'])) {
    $row = $db->fetchOne("SELECT profile_image FROM admin_users WHERE id = ?", [$_SESSION['admin_id']]);
    $_SESSION['admin_image'] = $row['profile_image'] ?? null;
}

$currentPage = 'quotes';

function ensureQuoteTables() {
    try {
        $db = db();
        $db->fetchOne("SELECT 1 FROM quotes LIMIT 1");
        try {
            $db->query("ALTER TABLE quotes ADD COLUMN booking_id INT DEFAULT NULL AFTER inquiry_id");
        } catch (\Throwable $e) {}
        try {
            $db->query("ALTER TABLE quotes MODIFY COLUMN inquiry_id INT DEFAULT NULL");
        } catch (\Throwable $e) {}
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
    if ($_POST['action'] === 'save_quote') {
        $sourceType = $_POST['source_type'] ?? '';
        $sourceId = intval($_POST['source_id'] ?? 0);
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
            if ($sourceType === 'booking') {
                $quoteId = $db->insert("INSERT INTO quotes (booking_id, quote_number, status, tax_percent, discount, notes, terms, valid_until, created_by) VALUES (?, ?, 'draft', ?, ?, ?, ?, ?, ?)",
                    [$sourceId, $quoteNumber, $taxPercent, $discount, $notes, $terms, $validUntil, $_SESSION['admin_id']]);
            } else {
                $quoteId = $db->insert("INSERT INTO quotes (inquiry_id, quote_number, status, tax_percent, discount, notes, terms, valid_until, created_by) VALUES (?, ?, 'draft', ?, ?, ?, ?, ?, ?)",
                    [$sourceId, $quoteNumber, $taxPercent, $discount, $notes, $terms, $validUntil, $_SESSION['admin_id']]);
            }
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

    header('Location: quotes');
    exit;
}

// Fetch all quotes with customer info
$quotes = $db->fetchAll("
    SELECT q.*,
        COALESCE(i.full_name, b.full_name) AS customer_name,
        COALESCE(i.email, b.email) AS customer_email,
        COALESCE(i.phone, b.phone) AS customer_phone,
        CASE WHEN q.inquiry_id IS NOT NULL THEN 'Inquiry' ELSE 'Booking' END AS source_type,
        CASE WHEN q.inquiry_id IS NOT NULL THEN i.subject ELSE b.booking_reference END AS source_ref,
        a.username AS created_by_name
    FROM quotes q
    LEFT JOIN inquiries i ON q.inquiry_id = i.id
    LEFT JOIN bookings b ON q.booking_id = b.id
    LEFT JOIN admin_users a ON q.created_by = a.id
    ORDER BY q.created_at DESC
");

$quoteIds = array_column($quotes, 'id');
$quoteItems = [];
if (!empty($quoteIds)) {
    $placeholders = implode(',', array_fill(0, count($quoteIds), '?'));
    $rows = $db->fetchAll(
        "SELECT * FROM quote_items WHERE quote_id IN ($placeholders) ORDER BY sort_order ASC",
        $quoteIds
    );
    foreach ($rows as $item) {
        $quoteItems[$item['quote_id']][] = $item;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotes - Kizza Tours Admin</title>
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
        .quote-section { background: #f8f9fa; border-radius: 8px; padding: 15px; border: 1px solid #e9ecef; }
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
        .source-badge { font-size: 0.7rem; padding: 2px 6px; }
        .status-filter { max-width: 160px; }
        .empty-state { padding: 60px 20px; text-align: center; color: #adb5bd; }
        .empty-state i { font-size: 3rem; margin-bottom: 15px; display: block; }
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
            <li class="nav-item"><a class="nav-link" href="inquiries"><i class="fas fa-fw fa-envelope"></i><span>Inquiries</span></a></li>
            <li class="nav-item active"><a class="nav-link" href="quotes"><i class="fas fa-fw fa-file-invoice"></i><span>Quotes</span></a></li>
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
                        <h4 class="mb-0 text-gray-800"><img src="../assets/images/log.png" alt="" height="32" class="mr-2"> Manage Quotes</h4>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard">Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Quotes</li>
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
                            <div class="d-flex align-items-center">
                                <input type="text" id="tableSearch" class="form-control form-control-sm" style="max-width: 300px;" placeholder="Search quotes..." onkeyup="filterTable(this.value)">
                                <select id="statusFilter" class="form-control form-control-sm ml-2 status-filter" onchange="filterStatus(this.value)">
                                    <option value="">All Statuses</option>
                                    <option value="draft">Draft</option>
                                    <option value="prepared">Prepared</option>
                                    <option value="confirmed">Confirmed</option>
                                    <option value="sent">Sent</option>
                                </select>
                                <select id="sourceFilter" class="form-control form-control-sm ml-2 status-filter" onchange="filterSource(this.value)">
                                    <option value="">All Sources</option>
                                    <option value="Inquiry">Inquiry</option>
                                    <option value="Booking">Booking</option>
                                </select>
                            </div>
                            <span class="text-muted small"><?php echo count($quotes); ?> total</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0" id="dataTable">
                                    <thead>
                                        <tr>
                                            <th style="width:16%">Quote #</th>
                                            <th style="width:25%">Customer</th>
                                            <th style="width:14%">Source</th>
                                            <th style="width:10%">Amount</th>
                                            <th style="width:10%">Status</th>
                                            <th style="width:12%">Created</th>
                                            <th class="actions-cell">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($quotes as $q):
                                            $items = $quoteItems[$q['id']] ?? [];
                                        ?>
                                        <tr data-status="<?php echo $q['status']; ?>" data-source="<?php echo $q['source_type']; ?>">
                                            <td><strong><?php echo htmlspecialchars($q['quote_number']); ?></strong></td>
                                            <td>
                                                <div><?php echo htmlspecialchars($q['customer_name'] ?? 'Unknown'); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($q['customer_email'] ?? ''); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $q['source_type'] === 'Inquiry' ? 'info' : 'primary'; ?> source-badge">
                                                    <i class="fas fa-<?php echo $q['source_type'] === 'Inquiry' ? 'envelope' : 'calendar-check'; ?> mr-1"></i>
                                                    <?php echo $q['source_type']; ?>
                                                </span>
                                                <small class="d-block text-muted"><?php echo htmlspecialchars($q['source_ref'] ?? ''); ?></small>
                                            </td>
                                            <td><strong>$<?php echo number_format($q['total'], 2); ?></strong></td>
                                            <td>
                                                <span class="badge badge-<?php
                                                    $s = $q['status'];
                                                    echo $s === 'draft' ? 'secondary' : ($s === 'prepared' ? 'info' : ($s === 'confirmed' ? 'primary' : 'success'));
                                                ?>"><?php echo ucfirst($q['status']); ?></span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($q['created_at'])); ?></td>
                                            <td class="actions-cell">
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-secondary" data-toggle="modal" data-target="#quoteModal<?php echo $q['id']; ?>" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-outline-primary" onclick="openQuoteEditor(<?php echo $q['id']; ?>)" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if (in_array($q['status'], ['confirmed', 'sent'])): ?>
                                                        <?php if (empty($q['pdf_path'])): ?>
                                                            <form method="POST" style="display:inline;">
                                                                <input type="hidden" name="action" value="generate_pdf">
                                                                <input type="hidden" name="quote_id" value="<?php echo $q['id']; ?>">
                                                                <button type="submit" class="btn btn-outline-primary" title="Generate PDF"><i class="fas fa-file-pdf"></i></button>
                                                            </form>
                                                        <?php else: ?>
                                                            <a href="../<?php echo $q['pdf_path']; ?>" class="btn btn-outline-primary" target="_blank" title="View PDF"><i class="fas fa-file-pdf"></i></a>
                                                        <?php endif; ?>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="action" value="send_quote_email">
                                                            <input type="hidden" name="quote_id" value="<?php echo $q['id']; ?>">
                                                            <button type="submit" class="btn btn-outline-success" title="Send Email"><i class="fas fa-envelope"></i></button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <?php if ($q['status'] === 'draft' || $q['status'] === 'prepared'): ?>
                                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this quote?');">
                                                            <input type="hidden" name="action" value="delete_quote">
                                                            <input type="hidden" name="quote_id" value="<?php echo $q['id']; ?>">
                                                            <button type="submit" class="btn btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($quotes)): ?>
                                        <tr>
                                            <td colspan="7">
                                                <div class="empty-state">
                                                    <i class="fas fa-file-invoice text-muted"></i>
                                                    <h5 class="text-muted">No quotes yet</h5>
                                                    <p class="text-muted">Quotes will appear here once you create them from inquiries or bookings.</p>
                                                    <a href="inquiries" class="btn btn-sm btn-outline-primary mr-1"><i class="fas fa-envelope"></i> Go to Inquiries</a>
                                                    <a href="bookings" class="btn btn-sm btn-outline-primary"><i class="fas fa-calendar-check"></i> Go to Bookings</a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- View Modals (outside table) -->
                    <?php foreach ($quotes as $q): ?>
                    <div class="modal fade" id="quoteModal<?php echo $q['id']; ?>" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Quote #<?php echo htmlspecialchars($q['quote_number']); ?></h5>
                                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                                </div>
                                <div class="modal-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>Customer:</strong> <?php echo htmlspecialchars($q['customer_name'] ?? 'N/A'); ?></p>
                                            <p><strong>Email:</strong> <a href="mailto:<?php echo $q['customer_email']; ?>" style="color:#0A2540;"><?php echo htmlspecialchars($q['customer_email'] ?? 'N/A'); ?></a></p>
                                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($q['customer_phone'] ?? 'N/A'); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Source:</strong> <?php echo $q['source_type']; ?> (<?php echo htmlspecialchars($q['source_ref'] ?? 'N/A'); ?>)</p>
                                            <p><strong>Status:</strong> <span class="badge badge-<?php
                                                $s = $q['status'];
                                                echo $s === 'draft' ? 'secondary' : ($s === 'prepared' ? 'info' : ($s === 'confirmed' ? 'primary' : 'success'));
                                            ?>"><?php echo ucfirst($s); ?></span></p>
                                            <p><strong>Amount:</strong> <strong>$<?php echo number_format($q['total'], 2); ?></strong></p>
                                            <p><strong>Created By:</strong> <?php echo htmlspecialchars($q['created_by_name'] ?? 'N/A'); ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                    <button type="button" class="btn btn-outline-primary" onclick="openQuoteEditor(<?php echo $q['id']; ?>)"><i class="fas fa-edit"></i> Edit</button>
                                    <?php if ($q['status'] === 'draft'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="prepare_quote">
                                            <input type="hidden" name="quote_id" value="<?php echo $q['id']; ?>">
                                            <button type="submit" class="btn btn-info"><i class="fas fa-check"></i> Mark Prepared</button>
                                        </form>
                                    <?php elseif ($q['status'] === 'prepared'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="confirm_quote">
                                            <input type="hidden" name="quote_id" value="<?php echo $q['id']; ?>">
                                            <button type="submit" class="btn btn-success"><i class="fas fa-check-double"></i> Confirm Quote</button>
                                        </form>
                                    <?php elseif (in_array($q['status'], ['confirmed', 'sent'])): ?>
                                        <?php if (empty($q['pdf_path'])): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="generate_pdf">
                                                <input type="hidden" name="quote_id" value="<?php echo $q['id']; ?>">
                                                <button type="submit" class="btn btn-primary"><i class="fas fa-file-pdf"></i> Generate PDF</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="generate_pdf">
                                                <input type="hidden" name="quote_id" value="<?php echo $q['id']; ?>">
                                                <button type="submit" class="btn btn-primary"><i class="fas fa-file-pdf"></i> Regenerate PDF</button>
                                            </form>
                                            <a href="../<?php echo $q['pdf_path']; ?>" class="btn btn-outline-primary" target="_blank"><i class="fas fa-file-pdf"></i> View PDF</a>
                                        <?php endif; ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="send_quote_email">
                                            <input type="hidden" name="quote_id" value="<?php echo $q['id']; ?>">
                                            <button type="submit" class="btn btn-success"><i class="fas fa-envelope"></i> <?php echo $q['status'] === 'sent' ? 'Resend' : 'Send via'; ?> Email</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
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
                    <h5 class="modal-title"><i class="fas fa-file-invoice text-success mr-2"></i>Edit Quote</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <form method="POST" id="quoteForm">
                    <input type="hidden" name="action" value="save_quote">
                    <input type="hidden" name="source_type" id="qSourceType" value="">
                    <input type="hidden" name="source_id" id="qSourceId" value="0">
                    <input type="hidden" name="quote_id" id="qQuoteId" value="0">
                    <div class="modal-body">
                        <p class="text-muted mb-3">Edit quote items and pricing.</p>

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
                        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Quote</button>
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
            var rows = document.querySelectorAll('#dataTable tbody tr[data-status]');
            rows.forEach(function(row) {
                var text = row.textContent.toLowerCase();
                var searchMatch = text.indexOf(val.toLowerCase()) > -1;
                var statusMatch = true;
                var sourceMatch = true;
                if (window._statusFilter) {
                    statusMatch = row.getAttribute('data-status') === window._statusFilter;
                }
                if (window._sourceFilter) {
                    sourceMatch = row.getAttribute('data-source') === window._sourceFilter;
                }
                row.style.display = searchMatch && statusMatch && sourceMatch ? '' : 'none';
            });
        }

        function filterStatus(val) {
            window._statusFilter = val || '';
            filterTable(document.getElementById('tableSearch').value);
        }

        function filterSource(val) {
            window._sourceFilter = val || '';
            filterTable(document.getElementById('tableSearch').value);
        }

        var itemRowIndex = 0;

        function addItemRow(desc, qty, price) {
            var tbody = document.getElementById('itemsBody');
            var index = itemRowIndex++;
            var tr = document.createElement('tr');
            tr.className = 'item-row';
            tr.id = 'itemRow_' + index;
            tr.innerHTML = [
                '<td class="text-center item-num">' + (tbody.children.length + 1) + '</td>',
                '<td><input type="text" class="form-control form-control-sm item-desc" placeholder="e.g. Safari Package - 3 Days" value="' + (desc || '') + '" required></td>',
                '<td><input type="number" class="form-control form-control-sm item-qty" value="' + (qty || 1) + '" min="1" onchange="calcRowTotal(this)" onkeyup="calcRowTotal(this)"></td>',
                '<td><input type="number" class="form-control form-control-sm item-price" value="' + (price || 0) + '" min="0" step="0.01" onchange="calcRowTotal(this)" onkeyup="calcRowTotal(this)"></td>',
                '<td class="text-right"><span class="item-total">$' + ((parseFloat(qty) * parseFloat(price)) || 0).toFixed(2) + '</span></td>',
                '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeItemRow(this)"><i class="fas fa-times"></i></button></td>'
            ].join('');
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

        var quotesData = <?php
            $data = [];
            foreach ($quotes as $q) {
                $items = $quoteItems[$q['id']] ?? [];
                $itemData = [];
                foreach ($items as $item) {
                    $itemData[] = [
                        'description' => $item['description'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price']
                    ];
                }
                $data[$q['id']] = [
                    'source_type' => $q['source_type'] === 'Inquiry' ? 'inquiry' : 'booking',
                    'source_id' => $q['inquiry_id'] ?? $q['booking_id'] ?? 0,
                    'tax_percent' => $q['tax_percent'],
                    'discount' => $q['discount'],
                    'valid_until' => $q['valid_until'],
                    'notes' => $q['notes'],
                    'terms' => $q['terms'],
                    'items' => $itemData
                ];
            }
            echo json_encode($data);
        ?>;

        function openQuoteEditor(quoteId) {
            document.getElementById('qQuoteId').value = quoteId;
            document.getElementById('itemsBody').innerHTML = '';
            itemRowIndex = 0;

            var d = quotesData[quoteId];
            if (d) {
                document.getElementById('qSourceType').value = d.source_type;
                document.getElementById('qSourceId').value = d.source_id;
                document.getElementById('qTaxPercent').value = d.tax_percent || '0';
                document.getElementById('qDiscount').value = d.discount || '0';
                document.getElementById('qValidUntil').value = d.valid_until || '';
                document.getElementById('qNotes').value = d.notes || '';
                document.getElementById('qTerms').value = d.terms || '';

                if (d.items && d.items.length > 0) {
                    d.items.forEach(function(item) {
                        addItemRow(item.description, item.quantity, item.unit_price);
                    });
                } else {
                    addItemRow('', 1, 0);
                }
            } else {
                addItemRow('', 1, 0);
            }

            calcTotals();
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
