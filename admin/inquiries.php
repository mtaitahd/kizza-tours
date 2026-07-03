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

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = intval($_POST['id'] ?? 0);
    if ($_POST['action'] === 'mark_read') {
        $db->query("UPDATE inquiries SET status = 'read' WHERE id = ?", [$id]);
    } elseif ($_POST['action'] === 'mark_replied') {
        $db->query("UPDATE inquiries SET status = 'replied' WHERE id = ?", [$id]);
    } elseif ($_POST['action'] === 'delete') {
        $db->query("DELETE FROM inquiries WHERE id = ?", [$id]);
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
        header('Location: inquiries');
        exit;
    }
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Updated successfully'];
    header('Location: inquiries');
    exit;
}

$inquiries = $db->fetchAll("SELECT * FROM inquiries ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inquiries - Kizza Tours Admin</title>
    <link rel="icon" href="../assets/images/log.png" type="image/png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="../templates/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../templates/assets/css/ruang-admin.min.css" rel="stylesheet">
    <link href="css/admin.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
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
        <ul class="navbar-nav sidebar sidebar-light accordion" id="accordionSidebar">
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="#" style="background-color: #0A2540;">
                <div class="sidebar-brand-icon"><img src="../assets/images/log.png" alt="Kizza Tours" height="35" style="border-radius: 50%;"></div>
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
                        <h4 class="mb-0 text-gray-800"><img src="../assets/images/log.png" alt="" height="32" class="mr-2" style="border-radius:6px;"> Manage Inquiries</h4>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard">Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Inquiries</li>
                        </ol>
                    </div>
                    
                    <?php if (isset($_SESSION['flash'])): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <?php echo htmlspecialchars($_SESSION['flash']['message']); unset($_SESSION['flash']); ?>
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
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Subject</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($inquiries as $inq): ?>
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
                                        
                                        <!-- View Modal -->
                                        <div class="modal fade" id="inqModal<?php echo $inq['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Inquiry from <?php echo htmlspecialchars($inq['full_name']); ?></h5>
                                                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p><strong>Email:</strong> <a href="mailto:<?php echo $inq['email']; ?>" style="color: #0A2540;"><?php echo htmlspecialchars($inq['email']); ?></a></p>
                                                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($inq['phone'] ?: 'N/A'); ?></p>
                                                        <p><strong>Subject:</strong> <?php echo htmlspecialchars($inq['subject'] ?: 'N/A'); ?></p>
                                                        <p><strong>Date:</strong> <?php echo date('F d, Y \a\t h:i A', strtotime($inq['created_at'])); ?></p>
                                                        <hr>
                                                        <p><strong>Message:</strong></p>
                                                        <p style="background: #f8f9fa; padding: 1rem; border-radius: 8px;"><?php echo nl2br(htmlspecialchars($inq['message'])); ?></p>
                                                        <hr>
                                                        <form method="POST">
                                                            <input type="hidden" name="action" value="send_reply">
                                                            <input type="hidden" name="inq_id" value="<?php echo $inq['id']; ?>">
                                                            <div class="form-group">
                                                                <label><strong>Reply To:</strong> <?php echo htmlspecialchars($inq['email']); ?></label>
                                                            </div>
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
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                        <?php if (empty($inquiries)): ?>
                                        <tr><td colspan="7" class="text-center py-4 text-muted">No inquiries yet</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
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

    <script src="../templates/assets/vendor/jquery/jquery.min.js"></script>
    <script src="../templates/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../templates/assets/vendor/jquery-easing/jquery.easing.min.js"></script>
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

            if (tpl.subject) {
                document.getElementById('reply_subject_' + id).value = tpl.subject;
            }

            if (tpl.message) {
                document.getElementById('reply_message_' + id).value = tpl.message.replace(/\[Name\]/g, inquirerName);
            }
        }
    </script>
</body>
</html>
