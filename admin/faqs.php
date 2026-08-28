<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
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

// Ensure tour_id column exists (safe migration on first load)
function ensureFaqTourColumn() {
    try {
        $db = db();
        $cols = $db->fetchAll("SHOW COLUMNS FROM faq");
        foreach ($cols as $c) {
            if (strtolower($c['Field']) === 'tour_id') return true;
        }
        $db->query("ALTER TABLE faq ADD COLUMN tour_id INT DEFAULT NULL AFTER id");
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}
ensureFaqTourColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['add', 'edit'])) {
        $id = intval($_POST['id'] ?? 0);
        $question = trim($_POST['question'] ?? '');
        $answer = trim($_POST['answer'] ?? '');
        $tour_id = intval($_POST['tour_id'] ?? 0) ?: null;
        $category = trim($_POST['category'] ?? '');
        $sort_order = intval($_POST['sort_order'] ?? 0);
        $status = trim($_POST['status'] ?? 'active');

        if ($question === '' || $answer === '') {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Question and Answer are required'];
            header('Location: faqs');
            exit;
        }

        if ($action === 'add') {
            $db->insert(
                "INSERT INTO faq (tour_id, question, answer, category, sort_order, status) VALUES (?, ?, ?, ?, ?, ?)",
                [$tour_id, $question, $answer, $category, $sort_order, $status]
            );
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'FAQ added successfully'];
        } else {
            $db->query(
                "UPDATE faq SET tour_id=?, question=?, answer=?, category=?, sort_order=?, status=? WHERE id=?",
                [$tour_id, $question, $answer, $category, $sort_order, $status, $id]
            );
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'FAQ updated successfully'];
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $db->query("DELETE FROM faq WHERE id = ?", [$id]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'FAQ deleted'];
    } elseif ($action === 'toggle') {
        $id = intval($_POST['id'] ?? 0);
        $f = $db->fetchOne("SELECT status FROM faq WHERE id = ?", [$id]);
        if ($f) {
            $newStatus = $f['status'] === 'active' ? 'inactive' : 'active';
            $db->query("UPDATE faq SET status=? WHERE id=?", [$newStatus, $id]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'FAQ ' . ($newStatus === 'active' ? 'published' : 'unpublished')];
        }
    } elseif ($action === 'copy_to_tour') {
        $tourId = intval($_POST['tour_id'] ?? 0);
        if ($tourId > 0) {
            $source = $db->fetchAll("SELECT * FROM faq WHERE status = 'active'");
            $copied = 0;
            $skipped = 0;
            $existing = [];
            $rows = $db->fetchAll("SELECT LOWER(question) AS q FROM faq WHERE tour_id = ?", [$tourId]);
            foreach ($rows as $r) { $existing[strtolower(trim($r['q']))] = true; }
            foreach ($source as $s) {
                $qKey = strtolower(trim($s['question']));
                if (isset($existing[$qKey])) { $skipped++; continue; }
                $db->insert(
                    "INSERT INTO faq (tour_id, question, answer, category, sort_order, status) VALUES (?, ?, ?, ?, ?, ?)",
                    [$tourId, $s['question'], $s['answer'], $s['category'], $s['sort_order'], $s['status']]
                );
                $existing[$qKey] = true;
                $copied++;
            }
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Copied {$copied} FAQs to tour (skipped {$skipped} duplicates)"];
        } else {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Please select a tour'];
        }
    }
    header('Location: faqs');
    exit;
}

$faqs = $db->fetchAll("SELECT f.*, p.title as tour_title FROM faq f LEFT JOIN tour_packages p ON f.tour_id = p.id ORDER BY f.tour_id IS NOT NULL, f.tour_id, f.sort_order ASC");
$tours = $db->fetchAll("SELECT id, title FROM tour_packages ORDER BY title");
$destinations = $db->fetchAll("SELECT id, name, slug FROM destinations WHERE status = 'active' ORDER BY name");
$siteUrl = defined('SITE_URL') ? SITE_URL : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQs - Kizza Tours Admin</title>
    <link rel="icon" href="../assets/images/log.png" type="image/png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../templates/assets/css/ruang-admin.min.css" rel="stylesheet">
    <link href="css/admin.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .sidebar-light .sidebar-brand { background-color: #0A2540 !important; }
        .bg-navbar { background-color: #0A2540 !important; }
        #accordionSidebar { position: fixed; top: 0; left: 0; height: 100vh; z-index: 1030; overflow-y: auto; }
        #content-wrapper { margin-left: 14rem; transition: margin-left 0.3s ease-in-out; }
        body.sidebar-toggled #content-wrapper { margin-left: 6.5rem; }
        .topbar { position: fixed; top: 0; right: 0; left: 14rem; z-index: 1020; transition: left 0.3s ease-in-out; }
        body.sidebar-toggled .topbar { left: 6.5rem; }
        #content { padding-top: 70px; }
        #faqPreview { max-height: 240px; overflow-y: auto; background: var(--off-white, #F8F6F3); }
        .faq-preview-row { padding: 0.9rem 0; border-bottom: 1px solid rgba(10,37,64,0.08); display: flex; align-items: flex-start; justify-content: space-between; cursor: pointer; }
        .faq-preview-row:last-child { border-bottom: none; }
        .faq-preview-q { font-weight: 600; color: #1A1A1A; }
        .faq-preview-a { color: #6B7280; font-size: 0.9rem; line-height: 1.7; margin-top: 0.4rem; }
        .faq-preview-icon { color: var(--accent-dark, #B8921E); font-size: 1.2rem; margin-left: 1rem; flex-shrink: 0; }
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
            <li class="nav-item active"><a class="nav-link" href="faqs"><i class="fas fa-fw fa-question-circle"></i><span>FAQs</span></a></li>
            <li class="nav-item"><a class="nav-link" href="inquiries"><i class="fas fa-fw fa-envelope"></i><span>Inquiries</span></a></li>
            <li class="nav-item"><a class="nav-link" href="quotes"><i class="fas fa-fw fa-file-invoice"></i><span>Quotes</span></a></li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Tools</div>
            <li class="nav-item"><a class="nav-link" href="compress-images"><i class="fas fa-fw fa-compress-alt"></i><span>Compress Images</span></a></li>
            <li class="nav-item"><a class="nav-link" href="sitemap"><i class="fas fa-fw fa-sitemap"></i><span>Sitemap</span></a></li>
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
                        <h4 class="mb-0 text-gray-800"><img src="../assets/images/log.png" alt="" height="32" class="mr-2"> Manage FAQs</h4>
                        <button class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#faqModal">
                            <i class="fas fa-plus"></i> Add FAQ
                        </button>
                    </div>
                    
                    <?php if (isset($_SESSION['flash'])): ?>
                        <div class="alert alert-<?php echo $_SESSION['flash']['type'] === 'danger' ? 'danger' : 'success'; ?> alert-dismissible fade show">
                            <?php echo htmlspecialchars($_SESSION['flash']['message']); unset($_SESSION['flash']); ?>
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                    <?php endif; ?>

                    <div class="card mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <input type="text" id="tableSearch" class="form-control form-control-sm" style="max-width: 300px;" placeholder="Filter FAQs..." onkeyup="filterTable(this.value)">
                            <span class="text-muted small">FAQs assigned to a tour appear only on that tour's page.</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0" id="dataTable">
                                    <thead>
                                        <tr>
                                            <th>Question</th>
                                            <th>Assigned Tour</th>
                                            <th>Category</th>
                                            <th>Order</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($faqs as $f): ?>
                                        <tr>
                                            <td style="max-width: 320px;"><strong><?php echo htmlspecialchars($f['question']); ?></strong></td>
                                            <td>
                                                <?php if (!empty($f['tour_title'])): ?>
                                                    <span class="badge badge-info" title="Shows on this tour's page only"><?php echo htmlspecialchars($f['tour_title']); ?></span>
                                                <?php else: ?>
                                                    <span class="badge badge-light text-muted">Global</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($f['category'] ?: '-'); ?></td>
                                            <td><?php echo (int)$f['sort_order']; ?></td>
                                            <td><span class="badge badge-<?php echo $f['status'] === 'active' ? 'success' : 'warning'; ?>"><?php echo $f['status'] === 'active' ? 'Published' : 'Unpublished'; ?></span></td>
                                            <td>
                                                <div class="d-flex">
                                                    <?php if (!empty($f['tour_id']) && !empty($f['tour_title'])): ?>
                                                    <?php
                                                        $tourSlug = '';
                                                        $tp = $db->fetchOne("SELECT slug FROM tour_packages WHERE id = ?", [$f['tour_id']]);
                                                        $tourSlug = $tp['slug'] ?? '';
                                                    ?>
                                                    <a href="../safari/<?php echo htmlspecialchars($tourSlug); ?>#faq-section" target="_blank" class="btn btn-sm btn-outline-info mr-1" title="View on Tour Page">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                    <button class="btn btn-sm btn-outline-secondary mr-1" onclick='editFaq(<?php echo htmlspecialchars(json_encode(array_merge($f, ['tour_title' => $f['tour_title'] ?? '', 'answer' => $f['answer']]))); ?>)'>
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('<?php echo $f['status'] === 'active' ? 'Unpublish' : 'Publish'; ?> this FAQ?');">
                                                        <?php csrf_field(); ?>
                                                        <input type="hidden" name="action" value="toggle">
                                                        <input type="hidden" name="id" value="<?php echo $f['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-<?php echo $f['status'] === 'active' ? 'warning' : 'success'; ?>" title="<?php echo $f['status'] === 'active' ? 'Unpublish' : 'Publish'; ?>">
                                                            <i class="fas fa-<?php echo $f['status'] === 'active' ? 'eye-slash' : 'eye'; ?>"></i>
                                                        </button>
                                                    </form>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this FAQ?');">
                                                        <?php csrf_field(); ?>
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo $f['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($faqs)): ?>
                                        <tr><td colspan="6" class="text-center py-4 text-muted">No FAQs yet. Add your first FAQ!</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold"><i class="fas fa-eye mr-1"></i> Live Preview <small class="text-muted">(frontend accordion style)</small></h6>
                        </div>
                        <div class="card-body">
                            <div id="faqPreview">
                                <?php
                                $previewFaqs = array_filter($faqs, function($f) { return $f['status'] === 'active'; });
                                if ($previewFaqs): ?>
                                    <?php foreach ($previewFaqs as $pf): ?>
                                    <div class="faq-preview-row">
                                        <div class="w-100">
                                            <div class="faq-preview-q"><?php echo htmlspecialchars($pf['question']); ?></div>
                                            <div class="faq-preview-a"><?php echo htmlspecialchars($pf['answer']); ?></div>
                                        </div>
                                        <div class="faq-preview-icon"><i class="fas fa-plus"></i></div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted mb-0 py-2">No published FAQs yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($destinations)): ?>
                    <div class="card mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold"><i class="fas fa-map-marked-alt mr-1"></i> Copy Global FAQs to a Tour <small class="text-muted">(quick start)</small></h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" class="d-flex flex-wrap align-items-end">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action" value="copy_to_tour">
                                <div class="form-group mr-2 mb-0 flex-grow-1" style="min-width: 240px;">
                                    <label class="small">Destination Tour</label>
                                    <select name="tour_id" class="form-control" required>
                                        <option value="">Select tour...</option>
                                        <?php foreach ($tours as $t): ?>
                                        <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['title']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button class="btn btn-sm btn-outline-primary" type="submit"><i class="fas fa-copy mr-1"></i> Copy all published global FAQs to this tour</button>
                            </form>
                            <small class="text-muted d-block mt-2">Duplicates existing tour FAQs are skipped. Each tour starts with a copy so you can customize per-destination.</small>
                        </div>
                    </div>
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

    <!-- FAQ Modal -->
    <div class="modal fade" id="faqModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="faqModalTitle">Add FAQ</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <form method="POST">
                    <?php csrf_field(); ?>
                    <div class="modal-body">
                        <input type="hidden" name="action" id="faqAction" value="add">
                        <input type="hidden" name="id" id="faqId" value="0">
                        <div class="form-group">
                            <label>Question</label>
                            <input type="text" class="form-control" name="question" id="faqQuestion" required>
                        </div>
                        <div class="form-group">
                            <label>Answer</label>
                            <textarea class="form-control" name="answer" id="faqAnswer" rows="5" required></textarea>
                            <small class="text-muted">Supports plain text. Blank lines create paragraph breaks.</small>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Assigned Tour</label>
                                    <select class="form-control" name="tour_id" id="faqTour">
                                        <option value="0">Global (all pages)</option>
                                        <?php foreach ($tours as $t): ?>
                                        <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['title']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Assign to a tour to show it only on that tour's page.</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Display Order</label>
                                    <input type="number" class="form-control" name="sort_order" id="faqOrder" value="0" min="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Status</label>
                                    <select class="form-control" name="status" id="faqStatus">
                                        <option value="active">Published</option>
                                        <option value="inactive">Unpublished</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Category <small class="text-muted">(optional)</small></label>
                            <input type="text" class="form-control" name="category" id="faqCategory" placeholder="e.g., Booking, Travel">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-outline-secondary">Save FAQ</button>
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
        function editFaq(f) {
            document.getElementById('faqAction').value = 'edit';
            document.getElementById('faqId').value = f.id;
            document.getElementById('faqQuestion').value = f.question;
            document.getElementById('faqAnswer').value = f.answer || '';
            document.getElementById('faqTour').value = f.tour_id || 0;
            document.getElementById('faqOrder').value = f.sort_order || 0;
            document.getElementById('faqStatus').value = f.status || 'active';
            document.getElementById('faqCategory').value = f.category || '';
            document.getElementById('faqModalTitle').textContent = 'Edit FAQ';
            $('#faqModal').modal('show');
        }
    </script>
    <script>
        function filterTable(val) {
            var rows = document.querySelectorAll('#dataTable tbody tr');
            rows.forEach(function(row) { row.style.display = row.textContent.toLowerCase().indexOf(val.toLowerCase()) > -1 ? '' : 'none'; });
        }
    </script>
</body>
</html>
