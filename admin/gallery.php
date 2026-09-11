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

// Handle upload
$redirectAnchor = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf();
    if ($_POST['action'] === 'upload' && isset($_FILES['image'])) {
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $location = trim($_POST['location'] ?? '');
        
        $file = $_FILES['image'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($ext, $allowed)) {
            $filename = uniqid('gallery_') . '.' . $ext;
            $uploadPath = realpath(__DIR__ . '/..') . '/uploads/gallery/' . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                $converted = convertToWebp($uploadPath);
                $dbPath = 'uploads/gallery/' . basename($converted);
                $db->insert(
                    "INSERT INTO gallery (title, image, category, location, status) VALUES (?, ?, ?, ?, 'active')",
                    [$title, $dbPath, $category, $location]
                );
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Image uploaded successfully'];
                $redirectAnchor = '#recent';
            }
        }
    } elseif ($_POST['action'] === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $item = $db->fetchOne("SELECT * FROM gallery WHERE id = ?", [$id]);
        if ($item && file_exists('../' . $item['image'])) {
            unlink('../' . $item['image']);
        }
        $db->query("DELETE FROM gallery WHERE id = ?", [$id]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Image deleted successfully'];
    }
    header('Location: gallery' . $redirectAnchor);
    exit;
}

$images = $db->fetchAll("SELECT * FROM gallery ORDER BY sort_order ASC, created_at DESC");
$categories = $db->fetchAll("SELECT * FROM gallery_categories ORDER BY sort_order ASC");

function galleryFolderFiles($dir) {
    $out = [];
    $abs = BASE_PATH . trim($dir, '/');
    if (!is_dir($abs)) return $out;
    foreach (new FilesystemIterator($abs) as $f) {
        if (!$f->isFile()) continue;
        $ext = strtolower($f->getExtension());
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true)) continue;
        $out[] = trim($dir, '/') . '/' . $f->getFilename();
    }
    sort($out);
    return $out;
}

// Group gallery-table images by category (gallery_categories order first, then
// any extra slugs still referenced by older images). The most recent uploads
// are kept aside for their own "recent" section at the top of the page.
$galleryByCat = [];
foreach ($categories as $cat) {
    $galleryByCat[$cat['slug']] = ['name' => $cat['name'], 'items' => []];
}
$knownPaths = [];
$recentItems = [];
foreach ($images as $img) {
    $slug = trim($img['category'] ?? '');
    if ($slug === '') $slug = 'general';
    if (!isset($galleryByCat[$slug])) {
        $galleryByCat[$slug] = ['name' => ucwords(str_replace(['_', '-'], ' ', $slug)), 'items' => []];
    }
    $galleryByCat[$slug]['items'][] = $img;
    $knownPaths[$img['image']] = true;
    $recentItems[] = $img;
}
$recentItems = array_slice($recentItems, 0, 8);

// Include every image file still on disk that is NOT already in the gallery
// table — e.g. images picked/saved while editing tours, plus destination and
// page images — so the page truly shows all uploaded photos.
$extraGroups = [
    'Tour Images'    => 'uploads/tours',
    'Destination Images' => 'uploads/destinations',
    'Page Images'    => 'uploads/pages',
];
$hasDiskImages = false;
foreach ($extraGroups as $groupName => $dir) {
    $items = [];
    foreach (galleryFolderFiles($dir) as $path) {
        if (isset($knownPaths[$path])) continue;
        $knownPaths[$path] = true;
        $items[] = [
            'id'       => 0,
            'image'    => $path,
            'title'    => basename($path),
            'category' => $groupName,
            'location' => $dir,
            'status'   => 'active',
        ];
    }
    if ($items) {
        $hasDiskImages = true;
        $galleryByCat['disk-' . $groupName] = ['name' => $groupName, 'items' => $items];
    }
}

$galleryHasAny = !empty($images) || $hasDiskImages;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery - Kizza Tours Admin</title>
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
        .gallery-cat-block { margin-bottom: 1.75rem; }
        .gallery-cat-title {
            display: flex; align-items: center; gap: .5rem;
            font-size: 1rem; font-weight: 700; color: #0A2540;
            border-bottom: 2px solid #e8edf2; padding-bottom: .5rem; margin-bottom: 1rem;
        }
        .gallery-cat-label {
            color: rgba(255,255,255,0.85);
            font-size: 0.7rem;
            text-transform: capitalize;
        }
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
            <li class="nav-item active"><a class="nav-link" href="gallery"><i class="fas fa-fw fa-images"></i><span>Gallery</span></a></li>
            <li class="nav-item"><a class="nav-link" href="testimonials"><i class="fas fa-fw fa-star"></i><span>Testimonials</span></a></li>
            <li class="nav-item"><a class="nav-link" href="faqs"><i class="fas fa-fw fa-question-circle"></i><span>FAQs</span></a></li>
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
                        <h4 class="mb-0 text-gray-800"><img src="../assets/images/log.png" alt="" height="32" class="mr-2"> Photo Gallery</h4>
                        <button class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#uploadModal">
                            <i class="fas fa-upload"></i> Upload Image
                        </button>
                    </div>
                    
                    <?php if (isset($_SESSION['flash'])): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <?php echo htmlspecialchars($_SESSION['flash']['message']); unset($_SESSION['flash']); ?>
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                    <?php endif; ?>

                    <?php if (!$galleryHasAny): ?>
                        <p class="text-muted text-center py-4">No images in gallery yet. Upload your first image!</p>
                    <?php else: ?>
                        <?php if ($recentItems): ?>
                        <div class="gallery-cat-block" id="recent">
                            <h5 class="gallery-cat-title"><i class="fas fa-clock text-muted mr-1"></i>Recently Uploaded</h5>
                            <div class="gallery-grid">
                                <?php foreach ($recentItems as $item): ?>
                                <div class="gallery-item">
                                    <img src="../<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['title'] ?: 'Untitled'); ?>" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='../assets/images/log.png';">
                                    <div class="overlay">
                                        <h6><?php echo htmlspecialchars($item['title'] ?: 'Untitled'); ?></h6>
                                    </div>
                                    <?php if ($item['id']): ?>
                                    <form method="POST" onsubmit="return confirm('Delete this image?');">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" class="delete-btn"><i class="fas fa-times"></i></button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php foreach ($galleryByCat as $slug => $group): ?>
                        <div class="gallery-cat-block">
                            <h5 class="gallery-cat-title">
                                <i class="fas fa-images text-muted mr-1"></i><?php echo htmlspecialchars($group['name']); ?>
                                <span class="badge badge-pill badge-dark ml-1"><?php echo count($group['items']); ?></span>
                                <?php if (strpos($slug, 'disk-') === 0): ?>
                                <span class="badge badge-secondary ml-1">on disk</span>
                                <?php endif; ?>
                            </h5>
                            <?php if (empty($group['items'])): ?>
                                <p class="text-muted small mb-0">No images in this category yet.</p>
                            <?php else: ?>
                            <div class="gallery-grid">
                                <?php foreach ($group['items'] as $item): ?>
                                <div class="gallery-item">
                                    <img src="../<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['title'] ?: 'Untitled'); ?>" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='../assets/images/log.png';">
                                    <div class="overlay">
                                        <h6><?php echo htmlspecialchars($item['title'] ?: 'Untitled'); ?></h6>
                                        <span class="gallery-cat-label"><?php echo htmlspecialchars($item['category'] ?: 'general'); ?></span>
                                    </div>
                                    <?php if ($item['id']): ?>
                                    <form method="POST" onsubmit="return confirm('Delete this image?');">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" class="delete-btn"><i class="fas fa-times"></i></button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
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

    <!-- Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Image</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <?php csrf_field(); ?>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="upload">
                        <div class="form-group">
                            <label>Image</label>
                            <input type="file" class="form-control-file" name="image" accept="image/*" required>
                            <small class="text-muted d-block">Large photos are resized and compressed in your browser before uploading, so uploads are quick.</small>
                        </div>
                        <div class="form-group">
                            <label>Title</label>
                            <input type="text" class="form-control" name="title" placeholder="Image title">
                        </div>
                        <div class="form-group">
                            <label>Category</label>
                            <select class="form-control" name="category">
                                <option value="">Select category</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['slug']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                                <option value="wildlife">Wildlife</option>
                                <option value="beaches">Beaches</option>
                                <option value="mountains">Mountains</option>
                                <option value="culture">Culture</option>
                                <option value="lodges">Lodges</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Location</label>
                            <input type="text" class="form-control" name="location" placeholder="e.g., Serengeti, Tanzania">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-outline-secondary" id="uploadBtn"><i class="fas fa-upload"></i> Upload</button>
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
    // Compress + resize photos in the browser before upload so large camera
    // images upload fast and never hit the server's full-size decode/resize.
    (function () {
        var form = document.getElementById('uploadForm');
        if (!form) return;
        var fileInput = form.querySelector('input[type=file]');
        var submitBtn = document.getElementById('uploadBtn');
        if (!fileInput || !window.createImageBitmap || !HTMLCanvasElement.prototype.toBlob) return;

        function compressToWebp(file, maxDim, quality) {
            return createImageBitmap(file, { imageOrientation: 'from-image' })
                .then(function (bmp) {
                    var scale = 1;
                    var longest = Math.max(bmp.width, bmp.height);
                    if (longest > maxDim) scale = maxDim / longest;
                    var w = Math.max(1, Math.round(bmp.width * scale));
                    var h = Math.max(1, Math.round(bmp.height * scale));
                    var canvas = document.createElement('canvas');
                    canvas.width = w; canvas.height = h;
                    var ctx = canvas.getContext('2d');
                    ctx.fillStyle = '#fff';
                    ctx.fillRect(0, 0, w, h);
                    ctx.drawImage(bmp, 0, 0, w, h);
                    if (typeof bmp.close === 'function') bmp.close();
                    return new Promise(function (resolve) {
                        canvas.toBlob(function (blob) {
                            resolve(blob ? new File([blob], file.name.replace(/\.[a-z0-9]+$/i, '.webp'), { type: 'image/webp' }) : file);
                        }, 'image/webp', quality);
                    });
                })
                .catch(function () { return file; });
        }

        form.addEventListener('submit', function (e) {
            var file = fileInput.files && fileInput.files[0];
            if (!file || !file.type || file.type === 'image/webp' || !window.DataTransfer) return;
            e.preventDefault();
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Compressing & uploading...';
            }
            compressToWebp(file, 1920, 0.8).then(function (outFile) {
                // Swap in the compressed WebP, then submit natively so the
                // normal POST -> redirect flow runs (success flash + #recent).
                try {
                    var dt = new DataTransfer();
                    dt.items.add(outFile);
                    fileInput.files = dt.files;
                } catch (err) { /* keep original file */ }
                form.submit();
            });
        });
    })();
    </script>
</body>
</html>
