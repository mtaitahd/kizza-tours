<?php
/**
 * Image Compression Tool
 * Quality: 70%, Max Width: 1920px
 */

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

    $allFiles = [];
    foreach ($scanDirs as $dir) { if (is_dir($dir)) scanImageDir($dir, $allFiles); }
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
}
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

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-light bg-navbar topbar mb-4 static-top">
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3"><i class="fa fa-bars text-white"></i></button>
                    <span class="text-white font-weight-bold" style="font-size:1.1rem;"><i class="fas fa-compress-alt mr-2"></i>Compress Images</span>
                </nav>

                <!-- Page Content -->
                <div class="container-fluid" id="container-wrapper">
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h4 class="mb-0 text-gray-800"><img src="../assets/images/log.png" alt="" height="32" class="mr-2"> Compress Images</h4>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard">Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Compress Images</li>
                        </ol>
                    </div>

                    <?php if (!$hasLibrary): ?>
                        <div class="alert alert-danger"><i class="fas fa-exclamation-triangle mr-2"></i>No image library found (Imagick or GD). Install one to use compression.</div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-12">
                            <div class="card compress-card mb-4">
                                <div class="card-header py-3 d-flex align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold" style="color:#0A2540;"><i class="fas fa-info-circle mr-2"></i>Image Compression Tool</h6>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted">Compresses all large images (&gt;10KB) in <code>uploads/</code>, <code>assets/images/</code>, and <code>templates/assets/img/</code> to <strong>70% quality</strong> with max width <strong>1920px</strong>.</p>
                                    <form method="post" onsubmit="document.getElementById('compressBtn').disabled = true; document.getElementById('compressBtn').innerHTML = '<i class=\'fas fa-spinner fa-spin mr-2\'></i>Processing...'; document.getElementById('results').style.display = 'block';">
                                        <button type="submit" name="compress" id="compressBtn" class="btn btn-primary btn-lg" <?php echo !$hasLibrary ? 'disabled' : ''; ?>>
                                            <i class="fas fa-compress-alt mr-2"></i>Start Compression
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (isset($_POST['compress']) && $hasLibrary): ?>
                    <div class="row" id="results">
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

    <script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../templates/assets/js/ruang-admin.min.js"></script>
</body>
</html>
