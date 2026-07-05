<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: ./');
    exit;
}

$msg = '';
$msgType = '';

function formatSize($bytes) {
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

function compressImage($source, $quality = 75) {
    $info = getimagesize($source);
    if (!$info) return false;

    $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION));
    $mime = $info['mime'];

    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $image = imagecreatefrompng($source);
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($source);
            break;
        default:
            return false;
    }

    if (!$image) return false;

    $tempFile = $source . '.tmp';
    imagejpeg($image, $tempFile, $quality);
    imagedestroy($image);

    if (!file_exists($tempFile)) return false;

    $origSize = filesize($source);
    $newSize = filesize($tempFile);

    if ($newSize < $origSize) {
        rename($tempFile, $source);
        return ['original' => $origSize, 'compressed' => $newSize];
    } else {
        unlink($tempFile);
        return ['original' => $origSize, 'compressed' => $origSize];
    }
}

$images = [];
$dirs = [
    BASE_PATH . 'assets/images/',
    BASE_PATH . 'uploads/'
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) continue;
    $files = glob($dir . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
    foreach ($files as $file) {
        $images[] = [
            'path' => $file,
            'name' => basename($file),
            'size' => filesize($file),
            'dir' => str_replace(BASE_PATH, '', dirname($file)) . '/'
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['compress'])) {
    $selected = $_POST['images'] ?? [];
    $quality = (int)($_POST['quality'] ?? 75);
    $count = 0;
    $saved = 0;

    if (in_array('__all__', $selected)) {
        $selected = array_column($images, 'path');
    }

    foreach ($selected as $imgPath) {
        if (!file_exists($imgPath)) continue;
        $result = compressImage($imgPath, $quality);
        if ($result) {
            $count++;
            $saved += ($result['original'] - $result['compressed']);
        }
    }

    if ($count > 0) {
        $msg = "Compressed $count image(s). Saved " . formatSize($saved) . ".";
        $msgType = 'success';
    } else {
        $msg = 'No images were compressed.';
        $msgType = 'warning';
    }

    // Re-scan to show updated sizes
    $images = [];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) continue;
        $files = glob($dir . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
        foreach ($files as $file) {
            $images[] = [
                'path' => $file,
                'name' => basename($file),
                'size' => filesize($file),
                'dir' => str_replace(BASE_PATH, '', dirname($file)) . '/'
            ];
        }
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
    <link href="css/admin.css" rel="stylesheet">
    <style>
        .sidebar-brand { background-color: #0A2540 !important; }
        .bg-navbar { background-color: #0A2540 !important; }
        #accordionSidebar { position: fixed; top: 0; left: 0; height: 100vh; z-index: 1030; overflow-y: auto; }
        #content-wrapper { margin-left: 14rem; }
        .topbar { position: fixed; top: 0; right: 0; left: 14rem; z-index: 1020; }
        #content { padding-top: 70px; }
        @media (max-width: 768px) { #accordionSidebar { width: 0; } #content-wrapper { margin-left: 0; } .topbar { left: 0; } }
        .img-preview { width: 60px; height: 40px; object-fit: cover; border-radius: 4px; }
        .savings-bar { height: 6px; border-radius: 3px; background: #e9ecef; overflow: hidden; }
        .savings-bar-inner { height: 100%; border-radius: 3px; background: #10B981; transition: width 0.5s; }
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
        <li class="nav-item"><a class="nav-link" href="inquiries"><i class="fas fa-fw fa-envelope"></i><span>Inquiries</span></a></li>
        <hr class="sidebar-divider">
        <div class="sidebar-heading">Tools</div>
        <li class="nav-item active"><a class="nav-link" href="compress-images"><i class="fas fa-fw fa-compress-alt"></i><span>Compress Images</span></a></li>
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
                    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-compress-alt mr-2"></i>Compress Images</h1>
                </div>

                <?php if ($msg): ?>
                <div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?= $msgType === 'success' ? 'check-circle' : 'exclamation-circle' ?> mr-2"></i><?= htmlspecialchars($msg) ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <?php endif; ?>

                <?php if (empty($images)): ?>
                <div class="alert alert-info">No images found in assets/images/ or uploads/.</div>
                <?php else: ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold">Select images to compress</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="compressForm">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="font-weight-bold">Quality</label>
                                    <select name="quality" class="form-control">
                                        <option value="85">High (85%) — recommended</option>
                                        <option value="75" selected>Medium (75%)</option>
                                        <option value="60">Low (60%) — smaller file</option>
                                    </select>
                                </div>
                                <div class="col-md-8 d-flex align-items-end justify-content-end">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="document.querySelectorAll('.img-check').forEach(c=>c.checked=true)"><i class="fas fa-check-double mr-1"></i>Select All</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.querySelectorAll('.img-check').forEach(c=>c.checked=false)"><i class="fas fa-times mr-1"></i>Deselect All</button>
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="thead-light">
                                        <tr>
                                            <th style="width:40px"><input type="checkbox" id="checkAll"></th>
                                            <th>Preview</th>
                                            <th>File</th>
                                            <th>Folder</th>
                                            <th>Size</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($images as $img): ?>
                                        <tr>
                                            <td><input type="checkbox" name="images[]" value="<?= htmlspecialchars($img['path']) ?>" class="img-check"></td>
                                            <td><img src="../<?= htmlspecialchars($img['dir'] . $img['name']) ?>" class="img-preview" alt="" onerror="this.style.display='none'"></td>
                                            <td><?= htmlspecialchars($img['name']) ?></td>
                                            <td><code><?= htmlspecialchars($img['dir']) ?></code></td>
                                            <td><?= formatSize($img['size']) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <hr>
                            <button type="submit" name="compress" value="1" class="btn btn-success" onclick="return confirm('Compress selected images? This cannot be undone.');">
                                <i class="fas fa-compress-alt mr-1"></i> Compress Selected
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <footer class="sticky-footer bg-white"><div class="container my-auto"><div class="copyright text-center my-auto"><span>&copy; <?= date('Y') ?> Kizza Tours & Safaris</span></div></div></footer>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('checkAll')?.addEventListener('change', function() {
    document.querySelectorAll('.img-check').forEach(c => c.checked = this.checked);
});
</script>
</body>
</html>
