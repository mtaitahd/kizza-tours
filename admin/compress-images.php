<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: ./');
    exit;
}

$db = db();

$scanDirs = [
    __DIR__ . '/../uploads',
    __DIR__ . '/../assets/images',
    __DIR__ . '/../templates/assets/img',
];
$quality = 70;
$maxWidth = 1920;

$useImagick = extension_loaded('imagick');
$hasLibrary = extension_loaded('imagick') || extension_loaded('gd');
$results = [];
$totalSaved = 0;
$filesFound = 0;

$allImageFiles = [];

function scanImageDir($dir, &$res = []) {
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        if (is_dir($path)) { scanImageDir($path, $res); }
        else {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp']) && filesize($path) > 10240 && basename($path) !== 'log.png') {
                $res[] = $path;
            }
        }
    }
    return $res;
}

foreach ($scanDirs as $dir) {
    if (is_dir($dir)) scanImageDir($dir, $allImageFiles);
}

// Handle replace
$replaceMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'replace') {
    $relPath = trim($_POST['rel_path'] ?? '');
    $absPath = realpath(__DIR__ . '/../' . $relPath);
    $baseDir = realpath(__DIR__ . '/..');
    if ($absPath && str_starts_with($absPath, $baseDir) && file_exists($absPath) && isset($_FILES['replace_image'])) {
        $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($ext, $allowed)) {
            $tmp = $_FILES['replace_image']['tmp_name'];
            if (is_uploaded_file($tmp) && $_FILES['replace_image']['error'] === UPLOAD_ERR_OK) {
                move_uploaded_file($tmp, $absPath);
                $replaceMessage = 'Image replaced successfully: ' . htmlspecialchars($relPath);
            }
        }
    }
}

// Handle compression
if (isset($_POST['compress']) && $hasLibrary) {
    function compressImage($path, $quality, $maxWidth, $useImagick) {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $origSize = filesize($path);
        if ($origSize < 10240) return null;

        if ($useImagick) {
            $img = new Imagick($path);
            $w = $img->getImageWidth();
            if ($w > $maxWidth) $img->resizeImage($maxWidth, 0, Imagick::FILTER_LANCZOS, 1);
            if ($ext === 'webp') { $img->setImageFormat('webp'); $img->setImageCompressionQuality($quality); }
            elseif ($ext === 'png') { $img->setImageFormat('png'); $img->setImageCompressionQuality($quality); }
            else { $img->setImageFormat('jpg'); $img->setImageCompressionQuality($quality); }
            $blob = $img->getImageBlob();
            $img->destroy();
        } else {
            switch ($ext) {
                case 'webp': $src = @imagecreatefromwebp($path); break;
                case 'png': $src = @imagecreatefrompng($path); break;
                case 'jpg': case 'jpeg': $src = @imagecreatefromjpeg($path); break;
                default: return null;
            }
            if (!$src) return null;
            $w = imagesx($src); $h = imagesy($src);
            if ($w > $maxWidth) {
                $ratio = $maxWidth / $w;
                $dst = imagecreatetruecolor($maxWidth, (int)($h * $ratio));
                if ($ext === 'png') { imagealphablending($dst, false); imagesavealpha($dst, true); }
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $maxWidth, (int)($h * $ratio), $w, $h);
                imagedestroy($src); $src = $dst;
            }
            ob_start();
            if ($ext === 'webp') imagewebp($src, null, $quality);
            elseif ($ext === 'png') imagepng($src, null, 9);
            else imagejpeg($src, null, $quality);
            $blob = ob_get_clean();
            imagedestroy($src);
        }

        $newSize = strlen($blob);
        if ($newSize < $origSize) {
            file_put_contents($path, $blob);
            return ['orig' => $origSize, 'new' => $newSize];
        }
        return ['orig' => $origSize, 'new' => $origSize];
    }

    $allFiles = $allImageFiles;
    $filesFound = count($allFiles);

    foreach ($allFiles as $file) {
        $res = compressImage($file, $quality, $maxWidth, $useImagick);
        if ($res === null) continue;
        $savings = (1 - $res['new'] / $res['orig']) * 100;
        $totalSaved += ($res['orig'] - $res['new']);
        $results[] = [
            'path' => str_replace([__DIR__ . '/../', '\\'], ['', '/'], $file),
            'orig' => $res['orig'],
            'new' => $res['new'],
            'savings' => $savings,
            'optimal' => $res['new'] >= $res['orig'],
        ];
    }

    // Re-scan after compression
    $allImageFiles = [];
    foreach ($scanDirs as $dir) {
        if (is_dir($dir)) scanImageDir($dir, $allImageFiles);
    }
}

// Group images by directory for display
$grouped = [];
foreach ($allImageFiles as $file) {
    $rel = str_replace([__DIR__ . '/../', '\\'], ['', '/'], $file);
    $dirName = dirname($rel);
    if ($dirName === '.') $dirName = 'root';
    $grouped[$dirName][] = [
        'rel' => $rel,
        'abs' => $file,
        'size' => filesize($file),
        'ext' => strtolower(pathinfo($file, PATHINFO_EXTENSION)),
        'name' => basename($file),
    ];
}
$totalImages = count($allImageFiles);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compress Images - Kizza Tours Admin</title>
    <link rel="icon" href="../assets/images/log.png" type="image/png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../templates/assets/css/ruang-admin.min.css" rel="stylesheet">
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
        @media (max-width: 768px) {
            #accordionSidebar { width: 0; }
            #content-wrapper { margin-left: 0; }
            body.sidebar-toggled #content-wrapper { margin-left: 0; }
            .topbar { left: 0; }
            body.sidebar-toggled .topbar { left: 0; }
        }
        .compress-card { border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .compress-card .card-header { background: #fff; border-bottom: 1px solid #e9ecef; }
        .result-log { background: #1a1a2e; color: #00ff88; font-family: 'Courier New', monospace; font-size: 0.78rem; padding: 1rem; border-radius: 8px; max-height: 500px; overflow-y: auto; line-height: 1.6; }
        .result-log .ok { color: #00ff88; }
        .result-log .optimal { color: #ffd700; }
        .result-log .done { color: #00bfff; font-weight: bold; }
        .img-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px; }
        .img-card { border: 1px solid #e9ecef; border-radius: 10px; overflow: hidden; background: #fff; transition: box-shadow 0.2s; position: relative; }
        .img-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .img-card .thumb-wrap { height: 140px; background: #f8f9fa; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; }
        .img-card .thumb-wrap img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .img-card .img-info { padding: 8px 10px; font-size: 0.75rem; }
        .img-card .img-info .filename { font-weight: 600; color: #333; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .img-card .img-info .filesize { color: #888; }
        .img-card .img-actions { padding: 6px 10px 10px; display: flex; gap: 6px; align-items: center; }
        .img-card .img-check { position: absolute; top: 8px; left: 8px; z-index: 2; transform: scale(1.2); }
        .img-card .replace-btn { font-size: 0.7rem; padding: 2px 10px; }
        .img-card.selected { border-color: #0A2540; box-shadow: 0 0 0 2px rgba(10,37,64,0.25); }
        #toolbar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .dir-badge { font-size: 0.7rem; padding: 2px 10px; border-radius: 20px; background: #e9ecef; color: #555; display: inline-block; margin-bottom: 4px; }
        @media (max-width: 640px) {
            .img-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; }
            .img-card .thumb-wrap { height: 100px; }
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
            <li class="nav-item"><a class="nav-link" href="testimonials"><i class="fas fa-fw fa-star"></i><span>Testimonials</span></a></li>
            <li class="nav-item"><a class="nav-link" href="gallery"><i class="fas fa-fw fa-images"></i><span>Gallery</span></a></li>
            <li class="nav-item"><a class="nav-link" href="quotes"><i class="fas fa-fw fa-file-invoice"></i><span>Quotes</span></a></li>
            <li class="nav-item"><a class="nav-link" href="inquiries"><i class="fas fa-fw fa-envelope"></i><span>Inquiries</span></a></li>
            <li class="nav-item"><a class="nav-link" href="pages"><i class="fas fa-fw fa-file-alt"></i><span>Pages</span></a></li>
            <li class="nav-item"><a class="nav-link" href="search"><i class="fas fa-fw fa-search"></i><span>Search</span></a></li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Tools</div>
            <li class="nav-item active"><a class="nav-link" href="compress-images"><i class="fas fa-fw fa-compress-alt"></i><span>Compress Images</span></a></li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Account</div>
            <li class="nav-item"><a class="nav-link" href="profile"><i class="fas fa-fw fa-user"></i><span>My Profile</span></a></li>
            <li class="nav-item"><a class="nav-link" href="settings"><i class="fas fa-fw fa-cog"></i><span>Settings</span></a></li>
            <li class="nav-item"><a class="nav-link" href="logout"><i class="fas fa-fw fa-sign-out-alt"></i><span>Logout</span></a></li>
            <hr class="sidebar-divider">
            <div class="text-center d-none d-md-inline">
                <button class="rounded-circle border-0" id="sidebarToggle"></button>
            </div>
        </ul>

        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <nav class="navbar navbar-expand navbar-light bg-navbar topbar mb-4 static-top">
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3"><i class="fa fa-bars text-white"></i></button>
                    <span class="text-white font-weight-bold" style="font-size:1.1rem;"><i class="fas fa-compress-alt mr-2"></i>Image Manager</span>
                </nav>

                <div class="container-fluid" id="container-wrapper">
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h4 class="mb-0 text-gray-800"><img src="../assets/images/log.png" alt="" height="32" class="mr-2"> Image Manager</h4>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard">Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Image Manager</li>
                        </ol>
                    </div>

                    <?php if ($replaceMessage): ?>
                        <div class="alert alert-success alert-dismissible fade show"><?php echo $replaceMessage; ?><button type="button" class="close" data-dismiss="alert">&times;</button></div>
                    <?php endif; ?>

                    <?php if (!$hasLibrary): ?>
                        <div class="alert alert-danger"><i class="fas fa-exclamation-triangle mr-2"></i>No image library found (Imagick or GD). Install one to use compression.</div>
                    <?php endif; ?>

                    <!-- Toolbar -->
                    <div class="card compress-card mb-4">
                        <div class="card-header py-3 d-flex align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold" style="color:#0A2540;"><i class="fas fa-images mr-2"></i>All Images <span class="badge badge-secondary ml-2"><?php echo $totalImages; ?></span></h6>
                            <div id="toolbar">
                                <button class="btn btn-sm btn-outline-primary" id="selectAllBtn"><i class="fas fa-check-square mr-1"></i>Select All</button>
                                <button class="btn btn-sm btn-outline-secondary" id="deselectAllBtn"><i class="fas fa-square mr-1"></i>Deselect All</button>
                                <span id="selectedCount" class="text-muted" style="font-size:0.85rem;">0 selected</span>
                                <button class="btn btn-sm btn-outline-info" id="compressBtn"><i class="fas fa-compress-alt mr-1"></i>Compress Selected</button>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if ($totalImages === 0): ?>
                                <p class="text-muted text-center py-3">No images found in scanned directories.</p>
                            <?php else: ?>
                                <?php foreach ($grouped as $dirName => $images): ?>
                                <div class="mb-4">
                                    <span class="dir-badge"><i class="far fa-folder mr-1"></i><?php echo htmlspecialchars($dirName); ?></span>
                                    <div class="img-grid mt-2">
                                        <?php foreach ($images as $img): ?>
                                        <div class="img-card" data-path="<?php echo htmlspecialchars($img['rel']); ?>">
                                            <input type="checkbox" class="img-check" value="<?php echo htmlspecialchars($img['rel']); ?>" onchange="this.closest('.img-card').classList.toggle('selected', this.checked); updateCount();">
                                            <div class="thumb-wrap">
                                                <img src="../<?php echo htmlspecialchars($img['rel']); ?>" alt="<?php echo htmlspecialchars($img['name']); ?>" loading="lazy" onerror="this.closest('.thumb-wrap').innerHTML = '<i class=\'fas fa-image\' style=\'font-size:3rem;color:#ccc;\'></i>'">
                                            </div>
                                            <div class="img-info">
                                                <div class="filename" title="<?php echo htmlspecialchars($img['name']); ?>"><?php echo htmlspecialchars($img['name']); ?></div>
                                                <div class="filesize"><?php echo round($img['size'] / 1024); ?> KB</div>
                                            </div>
                                            <div class="img-actions">
                                                <button class="btn btn-outline-secondary replace-btn" onclick="openReplaceModal('<?php echo htmlspecialchars($img['rel']); ?>')"><i class="fas fa-upload mr-1"></i>Replace</button>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Compression Tool -->
                    <div class="card compress-card mb-4">
                        <div class="card-header py-3 d-flex align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold" style="color:#0A2540;"><i class="fas fa-compress-alt mr-2"></i>Compression Tool</h6>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">Compresses all images (<strong>70% quality</strong>, max width <strong>1920px</strong>) across <code>uploads/</code>, <code>assets/images/</code>, and <code>templates/assets/img/</code>.</p>
                            <form method="post" onsubmit="document.getElementById('compressSubmitBtn').disabled = true; document.getElementById('compressSubmitBtn').innerHTML = '<i class=\'fas fa-spinner fa-spin mr-2\'></i>Processing...';">
                                <button type="submit" name="compress" id="compressSubmitBtn" class="btn btn-primary" <?php echo !$hasLibrary ? 'disabled' : ''; ?>>
                                    <i class="fas fa-compress-alt mr-2"></i>Compress All Images
                                </button>
                            </form>
                        </div>
                    </div>

                    <?php if (isset($_POST['compress']) && $hasLibrary): ?>
                    <div class="row">
                        <div class="col-12">
                            <div class="card compress-card mb-4">
                                <div class="card-header py-3 d-flex align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold" style="color:#0A2540;"><i class="fas fa-list mr-2"></i>Results</h6>
                                    <span class="badge badge-primary" style="font-size:0.85rem;"><?php echo $filesFound; ?> images processed</span>
                                </div>
                                <div class="card-body">
                                    <div class="result-log">
                                        <?php foreach ($results as $r): ?>
                                            <?php if ($r['optimal']): ?>
                                                <div class="optimal">-: <?php echo $r['path']; ?> - already optimal (<?php echo round($r['orig']/1024); ?>KB)</div>
                                            <?php else: ?>
                                                <div class="ok">OK: <?php echo $r['path']; ?> - <?php echo round($r['orig']/1024); ?>KB -> <?php echo round($r['new']/1024); ?>KB (<?php echo round($r['savings']); ?>% saved)</div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                        <div class="done mt-2">Done! Total saved: <?php echo round($totalSaved/1024); ?> KB</div>
                                        <div class="done">Library: <?php echo $useImagick ? 'Imagick' : 'GD'; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <footer class="sticky-footer">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>&copy; <?php echo date('Y'); ?> Kizza Tours &amp; Safaris</span>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- Replace Modal -->
    <div class="modal fade" id="replaceModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-upload mr-2"></i>Replace Image</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <form method="post" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="replace">
                        <input type="hidden" name="rel_path" id="replacePath">
                        <p>Replacing: <strong id="replaceFileName"></strong></p>
                        <div class="form-group">
                            <label>New Image</label>
                            <input type="file" class="form-control-file" name="replace_image" accept="image/*" required>
                        </div>
                        <p class="text-muted small">The new file will completely overwrite the existing image.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-upload mr-1"></i>Replace</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../templates/assets/js/ruang-admin.min.js"></script>
    <script>
        function updateCount() {
            var checked = document.querySelectorAll('.img-check:checked').length;
            document.getElementById('selectedCount').textContent = checked + ' selected';
        }

        document.getElementById('selectAllBtn').addEventListener('click', function() {
            document.querySelectorAll('.img-check').forEach(function(cb) {
                cb.checked = true;
                cb.closest('.img-card').classList.add('selected');
            });
            updateCount();
        });

        document.getElementById('deselectAllBtn').addEventListener('click', function() {
            document.querySelectorAll('.img-check').forEach(function(cb) {
                cb.checked = false;
                cb.closest('.img-card').classList.remove('selected');
            });
            updateCount();
        });

        function openReplaceModal(path) {
            document.getElementById('replacePath').value = path;
            document.getElementById('replaceFileName').textContent = path.split('/').pop();
            $('#replaceModal').modal('show');
        }

        document.getElementById('compressBtn').addEventListener('click', function() {
            var checked = document.querySelectorAll('.img-check:checked');
            var paths = [];
            checked.forEach(function(cb) { paths.push(cb.value); });
            if (paths.length === 0) { alert('Select at least one image to compress.'); return; }
            // We'll just submit the whole form for the full compression for now
            // Could later implement per-selection compression
            alert('Compression processes all images in scanned directories. Use "Compress All Images" below.');
        });
    </script>
</body>
</html>
