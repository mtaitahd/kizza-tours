<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
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

function ensurePagesTable() {
    try {
        $db = db();
        try {
            $db->query("ALTER TABLE pages ADD COLUMN hero_image VARCHAR(255) DEFAULT NULL AFTER image");
        } catch (\Throwable $e) {
        }
        try {
            $db->query("ALTER TABLE pages ADD COLUMN image_2 VARCHAR(255) DEFAULT NULL AFTER hero_image");
        } catch (\Throwable $e) {
        }
        return true;
    } catch (\Throwable $e) {
        error_log("Pages table ensure error: " . $e->getMessage());
        return false;
    }
}
ensurePagesTable();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['add', 'edit'])) {
        $pageId = intval($_POST['page_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $rawSlug = trim($_POST['slug'] ?? '');
        $slug = !empty($rawSlug) ? strtolower(preg_replace('/[^a-z0-9]+/i', '-', $rawSlug)) : slugify($title);
        $slug = trim($slug, '-');
        $content = $_POST['content'] ?? '';
        $meta_title = trim($_POST['meta_title'] ?? '');
        $meta_description = trim($_POST['meta_description'] ?? '');
        $meta_keywords = trim($_POST['meta_keywords'] ?? '');
        $status = trim($_POST['status'] ?? 'active');
        $sort_order = intval($_POST['sort_order'] ?? 0);

        if (empty($slug)) {
            $slug = slugify($title);
        }

        $existingSlug = $db->fetchOne("SELECT id FROM pages WHERE slug = ? AND id != ?", [$slug, $pageId]);
        if ($existingSlug) {
            $base = $slug;
            $counter = 2;
            while (true) {
                $candidate = $base . '-' . $counter;
                $taken = $db->fetchOne("SELECT id FROM pages WHERE slug = ? AND id != ?", [$candidate, $pageId]);
                if (!$taken) {
                    $slug = $candidate;
                    break;
                }
                $counter++;
            }
        }

        $image = '';
        $hasNewImage = false;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploaded = uploadFile($_FILES['image'], BASE_PATH . 'uploads/pages/', 'page_' . $slug);
            if ($uploaded) {
                $image = $uploaded;
                $hasNewImage = true;
            }
        }

        $heroImage = '';
        $hasNewHero = false;
        if (isset($_FILES['hero_image']) && $_FILES['hero_image']['error'] === UPLOAD_ERR_OK) {
            $uploaded = uploadFile($_FILES['hero_image'], BASE_PATH . 'uploads/pages/', 'hero_' . $slug);
            if ($uploaded) {
                $heroImage = $uploaded;
                $hasNewHero = true;
            }
        }

        $image2 = '';
        $hasNewImage2 = false;
        if (isset($_FILES['image_2']) && $_FILES['image_2']['error'] === UPLOAD_ERR_OK) {
            $uploaded = uploadFile($_FILES['image_2'], BASE_PATH . 'uploads/pages/', 'page2_' . $slug);
            if ($uploaded) {
                $image2 = $uploaded;
                $hasNewImage2 = true;
            }
        }

        if ($action === 'add') {
            $db->insert(
                "INSERT INTO pages (title, slug, content, meta_title, meta_description, meta_keywords, image, hero_image, image_2, status, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$title, $slug, $content, $meta_title, $meta_description, $meta_keywords, $image, $heroImage, $image2, $status, $sort_order]
            );
            try { seoGenerateSitemap(); } catch (\Throwable $e) { error_log("Sitemap gen error: " . $e->getMessage()); }
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Page added successfully', 'preview_url' => SITE_URL . '/' . $slug];
        } else {
            if ($hasNewImage) {
                $old = $db->fetchOne("SELECT image FROM pages WHERE id = ?", [$pageId]);
                if ($old && $old['image']) deleteFile($old['image']);
            }
            if ($hasNewHero) {
                $old = $db->fetchOne("SELECT hero_image FROM pages WHERE id = ?", [$pageId]);
                if ($old && $old['hero_image']) deleteFile($old['hero_image']);
            }
            if ($hasNewImage2) {
                $old = $db->fetchOne("SELECT image_2 FROM pages WHERE id = ?", [$pageId]);
                if ($old && $old['image_2']) deleteFile($old['image_2']);
            }
            $sql = "UPDATE pages SET title=?, slug=?, content=?, meta_title=?, meta_description=?, meta_keywords=?, status=?, sort_order=?";
            $params = [$title, $slug, $content, $meta_title, $meta_description, $meta_keywords, $status, $sort_order];
            if ($hasNewImage) { $sql .= ", image=?"; $params[] = $image; }
            if ($hasNewHero) { $sql .= ", hero_image=?"; $params[] = $heroImage; }
            if ($hasNewImage2) { $sql .= ", image_2=?"; $params[] = $image2; }
            $sql .= " WHERE id=?";
            $params[] = $pageId;
            $db->query($sql, $params);
            try { seoGenerateSitemap(); } catch (\Throwable $e) { error_log("Sitemap gen error: " . $e->getMessage()); }
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Page updated successfully', 'preview_url' => SITE_URL . '/' . $slug];
        }
    } elseif ($action === 'generate_sitemap') {
        try {
            $ok = seoGenerateSitemap();
        } catch (\Throwable $e) {
            error_log("Sitemap gen error: " . $e->getMessage());
            $ok = false;
        }
        $_SESSION['flash'] = ['type' => $ok ? 'success' : 'danger', 'message' => $ok ? 'Sitemap generated successfully' : 'Sitemap generation failed'];
    } elseif ($action === 'delete') {
        $pageId = intval($_POST['page_id'] ?? 0);
        $page = $db->fetchOne("SELECT image, hero_image, image_2 FROM pages WHERE id = ?", [$pageId]);
        if ($page && $page['image']) deleteFile($page['image']);
        if ($page && $page['hero_image']) deleteFile($page['hero_image']);
        if ($page && $page['image_2']) deleteFile($page['image_2']);
        $db->query("DELETE FROM pages WHERE id = ?", [$pageId]);
        try { seoGenerateSitemap(); } catch (\Throwable $e) { error_log("Sitemap gen error: " . $e->getMessage()); }
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Page deleted successfully'];
    } elseif ($_POST['action'] === 'remove_image') {
        $pageId = intval($_POST['page_id'] ?? 0);
        $page = $db->fetchOne("SELECT image FROM pages WHERE id = ?", [$pageId]);
        if ($page && $page['image']) deleteFile($page['image']);
        $db->query("UPDATE pages SET image = NULL WHERE id = ?", [$pageId]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Image removed'];
    } elseif ($_POST['action'] === 'remove_hero') {
        $pageId = intval($_POST['page_id'] ?? 0);
        $page = $db->fetchOne("SELECT hero_image FROM pages WHERE id = ?", [$pageId]);
        if ($page && $page['hero_image']) deleteFile($page['hero_image']);
        $db->query("UPDATE pages SET hero_image = NULL WHERE id = ?", [$pageId]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Hero image removed'];
    } elseif ($_POST['action'] === 'remove_image_2') {
        $pageId = intval($_POST['page_id'] ?? 0);
        $page = $db->fetchOne("SELECT image_2 FROM pages WHERE id = ?", [$pageId]);
        if ($page && $page['image_2']) deleteFile($page['image_2']);
        $db->query("UPDATE pages SET image_2 = NULL WHERE id = ?", [$pageId]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Second image removed'];
    }

    header('Location: pages');
    exit;
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$pages = $db->fetchAll("SELECT * FROM pages ORDER BY sort_order ASC, title ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pages - Kizza Tours Admin</title>
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
        @media (max-width: 768px) { #accordionSidebar { width: 0; } #content-wrapper { margin-left: 0; } .topbar { left: 0; } }
        .table thead th { border-top: none; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: #6c757d; font-weight: 700; }
        .img-preview { width: 60px; height: 40px; object-fit: cover; border-radius: 4px; }
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
        <li class="nav-item"><a class="nav-link" href="quotes"><i class="fas fa-fw fa-file-invoice"></i><span>Quotes</span></a></li>
        <li class="nav-item active"><a class="nav-link" href="pages"><i class="fas fa-fw fa-file-alt"></i><span>Pages</span></a></li>
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
    </ul>

    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            <nav class="navbar navbar-expand navbar-light bg-navbar topbar mb-4 static-top" style="background-color: #0A2540;">
                <button id="sidebarToggleTop" class="btn btn-link rounded-circle mr-3"><i class="fa fa-bars text-white"></i></button>
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item dropdown no-arrow">
                        <a class="nav-link" href="profile"><i class="fas fa-user-circle text-white"></i><span class="ml-2 text-white"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></span></a>
                    </li>
                </ul>
            </nav>

            <div class="container-fluid" id="container-wrapper">
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-file-alt mr-2"></i>Pages</h1>
                    <div>
                        <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#pageModal" onclick="openAdd()"><i class="fas fa-plus mr-1"></i> Add Page</button>
                        <form method="POST" action="" class="d-inline">
                            <input type="hidden" name="action" value="generate_sitemap">
                            <button type="submit" class="btn btn-primary btn-sm ml-1"><i class="fas fa-sitemap mr-1"></i> Generate Sitemap</button>
                        </form>
                    </div>
                </div>

                <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?> mr-2"></i><?= htmlspecialchars($flash['message']) ?>
                    <?php if (!empty($flash['preview_url'])): ?>
                    <a href="<?= htmlspecialchars($flash['preview_url']) ?>" target="_blank" class="btn btn-sm btn-outline-dark ml-3"><i class="fas fa-external-link-alt mr-1"></i>Preview</a>
                    <?php endif; ?>
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-header py-3 d-flex align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold">All Pages</h6>
                        <input type="text" class="form-control form-control-sm" style="width:250px" placeholder="Filter pages..." onkeyup="filterTable(this.value)">
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" id="pagesTable">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width:50px">#</th>
                                        <th style="width:60px">Image</th>
                                        <th>Title</th>
                                        <th>Slug</th>
                                        <th>Status</th>
                                        <th>Order</th>
                                        <th style="width:180px">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($pages)): ?>
                                    <tr><td colspan="7" class="text-center text-muted">No pages yet.</td></tr>
                                    <?php else: ?>
                                    <?php foreach ($pages as $p): ?>
                                    <tr>
                                        <td><?= $p['id'] ?></td>
                                        <td>
                                            <?php if ($p['image'] && file_exists(BASE_PATH . $p['image'])): ?>
                                                <img src="../<?= $p['image'] ?>" class="img-preview" alt="">
                                            <?php else: ?>
                                                <span class="text-muted"><i class="fas fa-image"></i></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><a href="../<?= htmlspecialchars($p['slug']) ?>" target="_blank" class="font-weight-bold"><?= htmlspecialchars($p['title']) ?></a></td>
                                        <td><code>/<?= htmlspecialchars($p['slug']) ?></code></td>
                                        <td><?= $p['status'] === 'active' ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Draft</span>' ?></td>
                                        <td><?= $p['sort_order'] ?></td>
                                        <td>
                                            <a href="../<?= htmlspecialchars($p['slug']) ?>" target="_blank" class="btn btn-secondary btn-sm" title="Preview"><i class="fas fa-eye"></i></a>
                                            <button class="btn btn-info btn-sm" onclick='editPage(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)'><i class="fas fa-edit"></i></button>
                                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('Delete this page?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="page_id" value="<?= $p['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <footer class="sticky-footer bg-white"><div class="container my-auto"><div class="copyright text-center my-auto"><span>&copy; <?= date('Y') ?> Kizza Tours & Safaris</span></div></div></footer>
    </div>
</div>

<div class="modal fade" id="pageModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" id="pageAction" value="add">
                <input type="hidden" name="page_id" id="pageId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle"><i class="fas fa-file-alt mr-2"></i>Add Page</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                <div class="form-group">
                    <label>Page Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" id="pageTitle" class="form-control" maxlength="255" required oninput="autoSlug(this.value)">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Slug <span class="text-danger">*</span></label>
                    <input type="text" name="slug" id="pageSlug" class="form-control" maxlength="255" required>
                </div>
            </div>
        </div>
        <div class="form-group">
            <label>Content</label>
            <textarea name="content" id="pageContent" rows="12" class="form-control" style="font-family:monospace;"></textarea>
            <small class="text-muted">HTML content supported. Use full HTML or plain text.</small>
        </div>
        <hr>
        <h6 class="font-weight-bold">SEO & Meta</h6>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Meta Title <small class="text-muted">(max 255 chars)</small></label>
                    <input type="text" name="meta_title" id="pageMetaTitle" class="form-control" maxlength="255">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Meta Keywords <small class="text-muted">(max 255 chars)</small></label>
                    <input type="text" name="meta_keywords" id="pageMetaKeywords" class="form-control" maxlength="255">
                </div>
            </div>
        </div>
        <div class="form-group">
            <label>Meta Description <small class="text-muted">(recommended max 160 chars for Google)</small></label>
            <textarea name="meta_description" id="pageMetaDesc" rows="3" class="form-control" maxlength="500"></textarea>
        </div>
        <hr>
        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    <label>Featured Image 1</label>
                    <input type="file" name="image" class="form-control-file" accept="image/*">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Featured Image 2</label>
                    <input type="file" name="image_2" class="form-control-file" accept="image/*">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Hero Image <small class="text-muted">(banner)</small></label>
                    <input type="file" name="hero_image" class="form-control-file" accept="image/*">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="pageStatus" class="form-control">
                        <option value="active">Active</option>
                        <option value="draft">Draft</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" id="pageSortOrder" class="form-control" value="0">
                </div>
            </div>
        </div>
        <div id="editImageSection" style="display:none;">
            <div class="form-group">
                <label>Current Image:</label>
                <div>
                    <img id="editImagePreview" src="" alt="" style="max-height:80px;border-radius:4px;">
                    <label class="ml-3 text-muted"><input type="checkbox" name="remove_image" value="1"> Remove current image</label>
                </div>
            </div>
        </div>
        <div id="editHeroSection" style="display:none;">
            <div class="form-group">
                <label>Current Hero Image:</label>
                <div>
                    <img id="editHeroPreview" src="" alt="" style="max-height:80px;border-radius:4px;">
                    <label class="ml-3 text-muted"><input type="checkbox" name="remove_hero" value="1"> Remove hero image</label>
                </div>
            </div>
        </div>
        <div id="editImage2Section" style="display:none;">
            <div class="form-group">
                <label>Current Featured Image 2:</label>
                <div>
                    <img id="editImage2Preview" src="" alt="" style="max-height:80px;border-radius:4px;">
                    <label class="ml-3 text-muted"><input type="checkbox" name="remove_image_2" value="1"> Remove image</label>
                </div>
            </div>
        </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save mr-1"></i> Save Page</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/js/bootstrap.bundle.min.js"></script>
<script src="../templates/assets/js/ruang-admin.min.js"></script>
<script>
function autoSlug(val) {
    var action = document.getElementById('pageAction').value;
    var slugField = document.getElementById('pageSlug');
    if (action === 'add' || slugField.value === '') {
        slugField.value = val.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    }
}

function openAdd() {
    document.getElementById('modalTitle').textContent = 'Add Page';
    document.getElementById('pageAction').value = 'add';
    document.getElementById('pageId').value = '0';
    document.getElementById('pageTitle').value = '';
    document.getElementById('pageSlug').value = '';
    document.getElementById('pageSlug').readOnly = true;
    document.getElementById('pageSlug').placeholder = 'Auto-generated from title';
    document.getElementById('pageContent').value = '';
    document.getElementById('pageMetaTitle').value = '';
    document.getElementById('pageMetaDesc').value = '';
    document.getElementById('pageMetaKeywords').value = '';
    document.getElementById('pageStatus').value = 'active';
    document.getElementById('pageSortOrder').value = '0';
    document.getElementById('editImageSection').style.display = 'none';
    document.getElementById('editHeroSection').style.display = 'none';
    document.getElementById('editImage2Section').style.display = 'none';
}

function editPage(p) {
    document.getElementById('modalTitle').textContent = 'Edit Page: ' + p.title;
    document.getElementById('pageAction').value = 'edit';
    document.getElementById('pageId').value = p.id;
    document.getElementById('pageTitle').value = p.title;
    document.getElementById('pageSlug').value = p.slug;
    document.getElementById('pageSlug').readOnly = false;
    document.getElementById('pageSlug').placeholder = '';
    document.getElementById('pageContent').value = p.content || '';
    document.getElementById('pageMetaTitle').value = p.meta_title || '';
    document.getElementById('pageMetaDesc').value = p.meta_description || '';
    document.getElementById('pageMetaKeywords').value = p.meta_keywords || '';
    document.getElementById('pageStatus').value = p.status;
    document.getElementById('pageSortOrder').value = p.sort_order;

    var imgSection = document.getElementById('editImageSection');
    if (p.image) {
        imgSection.style.display = 'block';
        document.getElementById('editImagePreview').src = '../' + p.image;
    } else {
        imgSection.style.display = 'none';
    }

    var heroSection = document.getElementById('editHeroSection');
    if (p.hero_image) {
        heroSection.style.display = 'block';
        document.getElementById('editHeroPreview').src = '../' + p.hero_image;
    } else {
        heroSection.style.display = 'none';
    }

    var img2Section = document.getElementById('editImage2Section');
    if (p.image_2) {
        img2Section.style.display = 'block';
        document.getElementById('editImage2Preview').src = '../' + p.image_2;
    } else {
        img2Section.style.display = 'none';
    }

    $('#pageModal').modal('show');
}

function filterTable(val) {
    var rows = document.querySelectorAll('#pagesTable tbody tr');
    rows.forEach(function(row) {
        var text = row.textContent.toLowerCase();
        row.style.display = text.indexOf(val.toLowerCase()) > -1 ? '' : 'none';
    });
}
</script>
</body>
</html>
