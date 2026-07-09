<?php
ob_start();
require_once '../includes/config.php';
require_once '../includes/db.php';

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

// Individual image upload via AJAX
$fileFields = [
    'hero_poster' => ['dir' => 'uploads/', 'prefix' => 'hero_poster'],
    'about_image' => ['dir' => 'uploads/', 'prefix' => 'about'],
    'cta_background' => ['dir' => 'uploads/', 'prefix' => 'cta'],
    'og_image' => ['dir' => 'uploads/', 'prefix' => 'og'],
    'site_favicon' => ['dir' => 'uploads/', 'prefix' => 'favicon'],
    'tanzania_safari_image' => ['dir' => 'uploads/', 'prefix' => 'tanzania'],
    'kenya_tanzania_image' => ['dir' => 'uploads/', 'prefix' => 'kenya_tz'],
    'rwanda_gorilla_image' => ['dir' => 'uploads/', 'prefix' => 'rwanda'],
    'uganda_tours_image' => ['dir' => 'uploads/', 'prefix' => 'uganda'],
    'zanzibar_holidays_image' => ['dir' => 'uploads/', 'prefix' => 'zanzibar'],
    'burundi_tours_image' => ['dir' => 'uploads/', 'prefix' => 'burundi'],
    'mount_kenya_image' => ['dir' => 'uploads/', 'prefix' => 'mount_kenya'],
    'maasai_mara_image' => ['dir' => 'uploads/', 'prefix' => 'maasai_mara'],
    'uganda_gorilla_adventure_image' => ['dir' => 'uploads/', 'prefix' => 'uganda_gorilla_adv'],
    'rwanda_luxury_gorilla_image' => ['dir' => 'uploads/', 'prefix' => 'rwanda_luxury'],
    'amboseli_kilimanjaro_image' => ['dir' => 'uploads/', 'prefix' => 'amboseli'],
];

if (isset($_POST['ajax_upload']) && isset($_POST['field_key'])) {
    ob_clean();
    header('Content-Type: application/json');
    try {
        $key = $_POST['field_key'];
        if (!isset($fileFields[$key]) || !isset($_FILES[$key]) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) {
            $errMsg = 'No file uploaded or invalid field';
            if (isset($_FILES[$key])) {
                $errMsg .= ' (error code: ' . $_FILES[$key]['error'] . ')';
            }
            echo json_encode(['success' => false, 'message' => $errMsg]);
            exit;
        }
        $cfg = $fileFields[$key];
        $uploaded = uploadFile($_FILES[$key], BASE_PATH . $cfg['dir'], $cfg['prefix']);
        if ($uploaded) {
            $oldFile = getSetting($key);
            if ($oldFile) deleteFile($oldFile);
            updateSetting($key, $uploaded);
            echo json_encode(['success' => true, 'url' => SITE_URL . '/' . $uploaded, 'file' => basename($uploaded)]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Upload failed. Check file type (jpg, png, webp, gif, svg, avif) and size (max 10MB).']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Text settings
    $textFields = [
        'site_name', 'site_tagline', 'site_email', 'site_phone', 
        'site_whatsapp', 'site_address', 'about_content_1', 'about_content_2',
        'about_content_3', 'vision_text', 'about_stat_years', 'about_stat_years_label',
        'about_stat_travelers', 'about_stat_travelers_label',
        'facebook_url', 'instagram_url',
        'twitter_url', 'youtube_url', 'tripadvisor_url',
        'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_encryption'
    ];
    
    foreach ($textFields as $field) {
        if (isset($_POST[$field])) {
            updateSetting($field, trim($_POST[$field]));
        }
    }
    
    // File uploads (uses $fileFields defined above for AJAX)
    $uploadErrors = [];
    foreach ($fileFields as $key => $cfg) {
        if (isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK) {
            $uploaded = uploadFile($_FILES[$key], BASE_PATH . $cfg['dir'], $cfg['prefix']);
            if ($uploaded) {
                $oldFile = getSetting($key);
                if ($oldFile) {
                    deleteFile($oldFile);
                }
                updateSetting($key, $uploaded);
            } else {
                $uploadErrors[] = ucfirst(str_replace('_', ' ', $key)) . ' upload failed';
            }
        }
    }
    
    // Remove file actions
    $removeKeys = ['hero_poster', 'about_image', 'cta_background', 'og_image', 'site_favicon',
        'tanzania_safari_image', 'kenya_tanzania_image', 'rwanda_gorilla_image', 'uganda_tours_image',
        'zanzibar_holidays_image', 'burundi_tours_image', 'mount_kenya_image',
        'maasai_mara_image', 'uganda_gorilla_adventure_image', 'rwanda_luxury_gorilla_image', 'amboseli_kilimanjaro_image'
    ];
    foreach ($removeKeys as $rk) {
        if (isset($_POST['remove_' . $rk])) {
            $oldFile = getSetting($rk);
            if ($oldFile) deleteFile($oldFile);
            updateSetting($rk, '');
        }
    }
    
    if (!empty($uploadErrors)) {
        $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Settings saved with warnings: ' . implode('; ', $uploadErrors)];
    } else {
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Settings saved successfully!'];
    }
    header('Location: settings');
    exit;
}

// Get current settings
$textSettings = [
    'site_name', 'site_tagline', 'site_email', 'site_phone', 'site_whatsapp', 'site_address',
    'about_content_1', 'about_content_2', 'about_content_3', 'vision_text',
    'about_stat_years', 'about_stat_years_label', 'about_stat_travelers', 'about_stat_travelers_label',
    'facebook_url', 'instagram_url', 'twitter_url', 'youtube_url', 'tripadvisor_url',
    'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_encryption'
];

$settings = [];
foreach ($textSettings as $key) {
    $settings[$key] = getSetting($key, '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Kizza Tours Admin</title>
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
        .form-label { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; color: #6c757d; font-weight: 600; }
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
        .gap-2 { gap: 0.5rem; }
        .upload-status { display: block; min-height: 1.2rem; margin-top: 0.25rem; }
    </style>
</head>
<body id="page-top">
    <div id="wrapper">
        <ul class="navbar-nav sidebar sidebar-light accordion" id="accordionSidebar">
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="#">
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
            <li class="nav-item"><a class="nav-link" href="quotes"><i class="fas fa-fw fa-file-invoice"></i><span>Quotes</span></a></li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Account</div>
            <li class="nav-item"><a class="nav-link" href="profile"><i class="fas fa-fw fa-user"></i><span>My Profile</span></a></li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">System</div>
            <li class="nav-item active"><a class="nav-link" href="settings"><i class="fas fa-fw fa-cog"></i><span>Settings</span></a></li>
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
                        <h4 class="mb-0 text-gray-800"><img src="../assets/images/log.png" alt="" height="32" class="mr-2" style="border-radius: 6px;"> Website Settings</h4>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard">Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Settings</li>
                        </ol>
                    </div>
                    
                    <?php if (isset($_SESSION['flash'])): ?>
                        <div class="alert alert-<?php echo $_SESSION['flash']['type']; ?> alert-dismissible fade show">
                            <?php echo htmlspecialchars($_SESSION['flash']['message']); unset($_SESSION['flash']); ?>
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                    <?php endif; ?>

                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <!-- Tabs -->
                                <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" id="general-tab" data-toggle="tab" href="#general" role="tab">General</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="media-tab" data-toggle="tab" href="#media" role="tab">Media</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="content-tab" data-toggle="tab" href="#content" role="tab">Content</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="social-tab" data-toggle="tab" href="#social" role="tab">Social</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="tourpages-tab" data-toggle="tab" href="#tourpages" role="tab">Tour Pages</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="seo-tab" data-toggle="tab" href="#seo" role="tab">SEO Pages</a>
                                    </li>
                                </ul>
                                
                                <div class="tab-content">
                                    <!-- General Settings -->
                                    <div class="tab-pane fade show active" id="general">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">Site Name</label>
                                                    <input type="text" class="form-control" name="site_name" value="<?php echo htmlspecialchars($settings['site_name']); ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">Tagline</label>
                                                    <input type="text" class="form-control" name="site_tagline" value="<?php echo htmlspecialchars($settings['site_tagline']); ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label">Email</label>
                                                    <input type="email" class="form-control" name="site_email" value="<?php echo htmlspecialchars($settings['site_email']); ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label">Phone</label>
                                                    <input type="text" class="form-control" name="site_phone" value="<?php echo htmlspecialchars($settings['site_phone']); ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label">WhatsApp Number</label>
                                                    <input type="text" class="form-control" name="site_whatsapp" value="<?php echo htmlspecialchars($settings['site_whatsapp']); ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Address</label>
                                            <input type="text" class="form-control" name="site_address" value="<?php echo htmlspecialchars($settings['site_address']); ?>">
                                        </div>
                                        <hr>
                                        <h6 class="text-white mb-3">SMTP Settings <small class="text-muted">(for email delivery)</small></h6>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label">SMTP Host</label>
                                                    <input type="text" class="form-control" name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>" placeholder="smtp.gmail.com">
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label class="form-label">Port</label>
                                                    <input type="text" class="form-control" name="smtp_port" value="<?php echo htmlspecialchars($settings['smtp_port'] ?? '587'); ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label class="form-label">Encryption</label>
                                                    <select class="form-control" name="smtp_encryption">
                                                        <option value="tls" <?php echo ($settings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                                        <option value="ssl" <?php echo ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">SMTP Username</label>
                                                    <input type="text" class="form-control" name="smtp_user" value="<?php echo htmlspecialchars($settings['smtp_user'] ?? ''); ?>" placeholder="your@email.com">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">SMTP Password</label>
                                                    <div class="input-group">
                                                        <input type="password" class="form-control" name="smtp_pass" id="smtp_pass" value="<?php echo htmlspecialchars($settings['smtp_pass'] ?? ''); ?>" placeholder="App password">
                                                        <button type="button" class="btn btn-outline-secondary" onclick="toggleSmtpPass()" title="Toggle visibility"><i class="fas fa-eye" id="smtpPassToggleIcon"></i></button>
                                                        <button type="button" class="btn btn-outline-secondary" onclick="copySmtpPass()" title="Copy password"><i class="fas fa-copy"></i></button>
                                                    </div>
                                                </div>
                                            </div>
                                            <script>
                                            function toggleSmtpPass() {
                                                const el = document.getElementById('smtp_pass');
                                                const icon = document.getElementById('smtpPassToggleIcon');
                                                if (el.type === 'password') {
                                                    el.type = 'text';
                                                    icon.className = 'fas fa-eye-slash';
                                                } else {
                                                    el.type = 'password';
                                                    icon.className = 'fas fa-eye';
                                                }
                                            }
                                            function copySmtpPass() {
                                                const el = document.getElementById('smtp_pass');
                                                el.type = 'text';
                                                el.select();
                                                el.setSelectionRange(0, 99999);
                                                navigator.clipboard.writeText(el.value).then(() => {
                                                    const btn = event.currentTarget;
                                                    const orig = btn.innerHTML;
                                                    btn.innerHTML = '<i class="fas fa-check"></i>';
                                                    setTimeout(() => btn.innerHTML = orig, 1500);
                                                }).catch(() => {});
                                                el.type = 'password';
                                            }
                                            </script>
                                        </div>
                                    </div>
                                    
                                    <!-- Media Settings -->
                                    <div class="tab-pane fade" id="media">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">Hero Video (MP4)</label>
                                                    <input type="file" class="form-control-file" name="hero_video" accept="video/mp4,video/webm">
                                                    <?php $v = getSetting('hero_video'); if ($v): ?>
                                                    <div class="mt-2 d-flex align-items-center flex-wrap">
                                                        <video width="120" height="80" style="object-fit:cover; border-radius:4px;" muted>
                                                            <source src="<?php echo SITE_URL . '/' . $v; ?>" type="video/mp4">
                                                        </video>
                                                        <span class="text-muted small ml-2"><?php echo basename($v); ?></span>
                                                        <button type="submit" name="remove_hero_video" value="1" class="btn btn-sm btn-outline-danger ml-2" onclick="return confirm('Remove?');"><i class="fas fa-times"></i> Remove</button>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">Hero Poster Image</label>
                                                    <input type="file" class="form-control-file" name="hero_poster" accept="image/*">
                                                    <?php $v = getSetting('hero_poster'); if ($v): ?>
                                                    <div class="mt-2 d-flex align-items-center flex-wrap">
                                                        <?php if (file_exists(BASE_PATH . $v)): ?>
                                                        <img src="<?php echo SITE_URL . '/' . $v; ?>" class="preview-thumb">
                                                        <span class="text-muted small ml-2"><?php echo basename($v); ?></span>
                                                        <?php else: ?>
                                                        <span class="text-muted small">File missing — re-upload</span>
                                                        <?php endif; ?>
                                                        <button type="submit" name="remove_hero_poster" value="1" class="btn btn-sm btn-outline-danger ml-2" onclick="return confirm('Remove?');"><i class="fas fa-times"></i> Remove</button>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">About Section Image</label>
                                                    <input type="file" class="form-control-file" name="about_image" accept="image/*">
                                                    <?php $v = getSetting('about_image'); if ($v): ?>
                                                    <div class="mt-2 d-flex align-items-center flex-wrap">
                                                        <?php if (file_exists(BASE_PATH . $v)): ?>
                                                        <img src="<?php echo SITE_URL . '/' . $v; ?>" class="preview-thumb">
                                                        <span class="text-muted small ml-2"><?php echo basename($v); ?></span>
                                                        <?php else: ?>
                                                        <span class="text-muted small">File missing — re-upload</span>
                                                        <?php endif; ?>
                                                        <button type="submit" name="remove_about_image" value="1" class="btn btn-sm btn-outline-danger ml-2" onclick="return confirm('Remove?');"><i class="fas fa-times"></i> Remove</button>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">CTA Background</label>
                                                    <input type="file" class="form-control-file" name="cta_background" accept="image/*">
                                                    <?php $v = getSetting('cta_background'); if ($v): ?>
                                                    <div class="mt-2 d-flex align-items-center flex-wrap">
                                                        <?php if (file_exists(BASE_PATH . $v)): ?>
                                                        <img src="<?php echo SITE_URL . '/' . $v; ?>" class="preview-thumb">
                                                        <span class="text-muted small ml-2"><?php echo basename($v); ?></span>
                                                        <?php else: ?>
                                                        <span class="text-muted small">File missing — re-upload</span>
                                                        <?php endif; ?>
                                                        <button type="submit" name="remove_cta_background" value="1" class="btn btn-sm btn-outline-danger ml-2" onclick="return confirm('Remove?');"><i class="fas fa-times"></i> Remove</button>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label">OG Image (Social Share)</label>
                                                    <input type="file" class="form-control-file" name="og_image" accept="image/*">
                                                    <?php $v = getSetting('og_image'); if ($v): ?>
                                                    <div class="mt-2 d-flex align-items-center flex-wrap">
                                                        <?php if (file_exists(BASE_PATH . $v)): ?>
                                                        <img src="<?php echo SITE_URL . '/' . $v; ?>" class="preview-thumb-sm">
                                                        <span class="text-muted small ml-2"><?php echo basename($v); ?></span>
                                                        <?php else: ?>
                                                        <span class="text-muted small">File missing — re-upload</span>
                                                        <?php endif; ?>
                                                        <button type="submit" name="remove_og_image" value="1" class="btn btn-sm btn-outline-danger ml-2" onclick="return confirm('Remove?');"><i class="fas fa-times"></i> Remove</button>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label">Favicon</label>
                                                    <input type="file" class="form-control-file" name="site_favicon" accept="image/*">
                                                    <?php $v = getSetting('site_favicon'); if ($v): ?>
                                                    <div class="mt-2 d-flex align-items-center flex-wrap">
                                                        <?php if (file_exists(BASE_PATH . $v)): ?>
                                                        <img src="<?php echo SITE_URL . '/' . $v; ?>" class="preview-thumb-sm">
                                                        <span class="text-muted small ml-2"><?php echo basename($v); ?></span>
                                                        <?php else: ?>
                                                        <span class="text-muted small">File missing — re-upload</span>
                                                        <?php endif; ?>
                                                        <button type="submit" name="remove_site_favicon" value="1" class="btn btn-sm btn-outline-danger ml-2" onclick="return confirm('Remove?');"><i class="fas fa-times"></i> Remove</button>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Content Settings -->
                                    <div class="tab-pane fade" id="content">
                                        <div class="form-group">
                                            <label class="form-label">About Paragraph 1</label>
                                            <textarea class="form-control" name="about_content_1" rows="3"><?php echo htmlspecialchars($settings['about_content_1']); ?></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">About Paragraph 2</label>
                                            <textarea class="form-control" name="about_content_2" rows="3"><?php echo htmlspecialchars($settings['about_content_2']); ?></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">About Paragraph 3</label>
                                            <textarea class="form-control" name="about_content_3" rows="3"><?php echo htmlspecialchars($settings['about_content_3']); ?></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Vision Statement</label>
                                            <textarea class="form-control" name="vision_text" rows="4"><?php echo htmlspecialchars($settings['vision_text']); ?></textarea>
                                        </div>
                                        <hr>
                                        <h6 class="mb-3">About Section - Stats Card</h6>
                                        <div class="row">
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label class="form-label">Years Number</label>
                                                    <input type="text" class="form-control" name="about_stat_years" value="<?php echo htmlspecialchars($settings['about_stat_years']); ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label class="form-label">Years Label</label>
                                                    <input type="text" class="form-control" name="about_stat_years_label" value="<?php echo htmlspecialchars($settings['about_stat_years_label']); ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label class="form-label">Travelers Number</label>
                                                    <input type="text" class="form-control" name="about_stat_travelers" value="<?php echo htmlspecialchars($settings['about_stat_travelers']); ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label class="form-label">Travelers Label</label>
                                                    <input type="text" class="form-control" name="about_stat_travelers_label" value="<?php echo htmlspecialchars($settings['about_stat_travelers_label']); ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Tour Pages Settings -->
                                    <div class="tab-pane fade" id="tourpages">
                                        <p class="text-muted mb-4">Upload hero background images for each tour page. Recommended size: 1920x600px. Accepted formats: JPG, PNG, WebP, GIF, SVG, AVIF. <span class="text-warning"><i class="fas fa-info-circle"></i> Max upload size: <?= ini_get('upload_max_filesize') ?>B</span></p>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">Tanzania Safari Page Image</label>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <input type="file" class="form-control-file" name="tanzania_safari_image" accept="image/*" id="file_tanzania_safari_image" style="flex:1;">
                                                        <button type="button" class="btn btn-sm btn-success upload-btn" data-key="tanzania_safari_image"><i class="fas fa-upload"></i> Upload</button>
                                                    </div>
                                                    <span class="upload-status text-muted small" id="status_tanzania_safari_image"></span>
                                                    <?php $v = getSetting('tanzania_safari_image'); if ($v): ?>
                                                    <div class="mt-2 d-flex align-items-center flex-wrap">
                                                        <?php if (file_exists(BASE_PATH . $v)): ?>
                                                        <img src="<?php echo SITE_URL . '/' . $v; ?>" class="preview-thumb" id="preview_tanzania_safari_image">
                                                        <span class="text-muted small ml-2"><?php echo basename($v); ?></span>
                                                        <?php else: ?>
                                                        <span class="text-muted small">File missing — re-upload</span>
                                                        <?php endif; ?>
                                                        <button type="submit" name="remove_tanzania_safari_image" value="1" class="btn btn-sm btn-outline-danger ml-2" onclick="return confirm('Remove?');"><i class="fas fa-times"></i> Remove</button>
                                                    </div>
                                                    <?php else: ?>
                                                    <div class="mt-2 d-flex align-items-center flex-wrap" id="preview_wrap_tanzania_safari_image" style="display:none;"></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">Kenya Tanzania Safari Page Image</label>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <input type="file" class="form-control-file" name="kenya_tanzania_image" accept="image/*" id="file_kenya_tanzania_image" style="flex:1;">
                                                        <button type="button" class="btn btn-sm btn-success upload-btn" data-key="kenya_tanzania_image"><i class="fas fa-upload"></i> Upload</button>
                                                    </div>
                                                    <span class="upload-status text-muted small" id="status_kenya_tanzania_image"></span>
                                                    <?php $v = getSetting('kenya_tanzania_image'); if ($v): ?>
                                                    <div class="mt-2 d-flex align-items-center flex-wrap">
                                                        <?php if (file_exists(BASE_PATH . $v)): ?>
                                                        <img src="<?php echo SITE_URL . '/' . $v; ?>" class="preview-thumb" id="preview_kenya_tanzania_image">
                                                        <span class="text-muted small ml-2"><?php echo basename($v); ?></span>
                                                        <?php else: ?>
                                                        <span class="text-muted small">File missing — re-upload</span>
                                                        <?php endif; ?>
                                                        <button type="submit" name="remove_kenya_tanzania_image" value="1" class="btn btn-sm btn-outline-danger ml-2" onclick="return confirm('Remove?');"><i class="fas fa-times"></i> Remove</button>
                                                    </div>
                                                    <?php else: ?>
                                                    <div class="mt-2 d-flex align-items-center flex-wrap" id="preview_wrap_kenya_tanzania_image" style="display:none;"></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">Rwanda Gorilla Trekking Page Image</label>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <input type="file" class="form-control-file" name="rwanda_gorilla_image" accept="image/*" id="file_rwanda_gorilla_image" style="flex:1;">
                                                        <button type="button" class="btn btn-sm btn-success upload-btn" data-key="rwanda_gorilla_image"><i class="fas fa-upload"></i> Upload</button>
                                                    </div>
                                                    <span class="upload-status text-muted small" id="status_rwanda_gorilla_image"></span>
                                                    <?php $v = getSetting('rwanda_gorilla_image'); if ($v): ?>
                                                    <div class="mt-2 d-flex align-items-center flex-wrap">
                                                        <?php if (file_exists(BASE_PATH . $v)): ?>
                                                        <img src="<?php echo SITE_URL . '/' . $v; ?>" class="preview-thumb" id="preview_rwanda_gorilla_image">
                                                        <span class="text-muted small ml-2"><?php echo basename($v); ?></span>
                                                        <?php else: ?>
                                                        <span class="text-muted small">File missing — re-upload</span>
                                                        <?php endif; ?>
                                                        <button type="submit" name="remove_rwanda_gorilla_image" value="1" class="btn btn-sm btn-outline-danger ml-2" onclick="return confirm('Remove?');"><i class="fas fa-times"></i> Remove</button>
                                                    </div>
                                                    <?php else: ?>
                                                    <div class="mt-2 d-flex align-items-center flex-wrap" id="preview_wrap_rwanda_gorilla_image" style="display:none;"></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">Uganda Tours Page Image</label>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <input type="file" class="form-control-file" name="uganda_tours_image" accept="image/*" id="file_uganda_tours_image" style="flex:1;">
                                                        <button type="button" class="btn btn-sm btn-success upload-btn" data-key="uganda_tours_image"><i class="fas fa-upload"></i> Upload</button>
                                                    </div>
                                                    <span class="upload-status text-muted small" id="status_uganda_tours_image"></span>
                                                    <?php $v = getSetting('uganda_tours_image'); if ($v): ?>
                                                    <div class="mt-2 d-flex align-items-center flex-wrap">
                                                        <?php if (file_exists(BASE_PATH . $v)): ?>
                                                        <img src="<?php echo SITE_URL . '/' . $v; ?>" class="preview-thumb" id="preview_uganda_tours_image">
                                                        <span class="text-muted small ml-2"><?php echo basename($v); ?></span>
                                                        <?php else: ?>
                                                        <span class="text-muted small">File missing — re-upload</span>
                                                        <?php endif; ?>
                                                        <button type="submit" name="remove_uganda_tours_image" value="1" class="btn btn-sm btn-outline-danger ml-2" onclick="return confirm('Remove?');"><i class="fas fa-times"></i> Remove</button>
                                                    </div>
                                                    <?php else: ?>
                                                    <div class="mt-2 d-flex align-items-center flex-wrap" id="preview_wrap_uganda_tours_image" style="display:none;"></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">Zanzibar Holidays Page Image</label>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <input type="file" class="form-control-file" name="zanzibar_holidays_image" accept="image/*" id="file_zanzibar_holidays_image" style="flex:1;">
                                                        <button type="button" class="btn btn-sm btn-success upload-btn" data-key="zanzibar_holidays_image"><i class="fas fa-upload"></i> Upload</button>
                                                    </div>
                                                    <span class="upload-status text-muted small" id="status_zanzibar_holidays_image"></span>
                                                    <?php $v = getSetting('zanzibar_holidays_image'); if ($v): ?>
                                                    <div class="mt-2 d-flex align-items-center flex-wrap">
                                                        <?php if (file_exists(BASE_PATH . $v)): ?>
                                                        <img src="<?php echo SITE_URL . '/' . $v; ?>" class="preview-thumb" id="preview_zanzibar_holidays_image">
                                                        <span class="text-muted small ml-2"><?php echo basename($v); ?></span>
                                                        <?php else: ?>
                                                        <span class="text-muted small">File missing — re-upload</span>
                                                        <?php endif; ?>
                                                        <button type="submit" name="remove_zanzibar_holidays_image" value="1" class="btn btn-sm btn-outline-danger ml-2" onclick="return confirm('Remove?');"><i class="fas fa-times"></i> Remove</button>
                                                    </div>
                                                    <?php else: ?>
                                                    <div class="mt-2 d-flex align-items-center flex-wrap" id="preview_wrap_zanzibar_holidays_image" style="display:none;"></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">Burundi Tours Page Image</label>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <input type="file" class="form-control-file" name="burundi_tours_image" accept="image/*" id="file_burundi_tours_image" style="flex:1;">
                                                        <button type="button" class="btn btn-sm btn-success upload-btn" data-key="burundi_tours_image"><i class="fas fa-upload"></i> Upload</button>
                                                    </div>
                                                    <span class="upload-status text-muted small" id="status_burundi_tours_image"></span>
                                                    <?php $v = getSetting('burundi_tours_image'); if ($v): ?>
                                                    <div class="mt-2 d-flex align-items-center flex-wrap">
                                                        <?php if (file_exists(BASE_PATH . $v)): ?>
                                                        <img src="<?php echo SITE_URL . '/' . $v; ?>" class="preview-thumb" id="preview_burundi_tours_image">
                                                        <span class="text-muted small ml-2"><?php echo basename($v); ?></span>
                                                        <?php else: ?>
                                                        <span class="text-muted small">File missing — re-upload</span>
                                                        <?php endif; ?>
                                                        <button type="submit" name="remove_burundi_tours_image" value="1" class="btn btn-sm btn-outline-danger ml-2" onclick="return confirm('Remove?');"><i class="fas fa-times"></i> Remove</button>
                                                    </div>
                                                    <?php else: ?>
                                                    <div class="mt-2 d-flex align-items-center flex-wrap" id="preview_wrap_burundi_tours_image" style="display:none;"></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">Mount Kenya Climbing Page Image</label>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <input type="file" class="form-control-file" name="mount_kenya_image" accept="image/*" id="file_mount_kenya_image" style="flex:1;">
                                                        <button type="button" class="btn btn-sm btn-success upload-btn" data-key="mount_kenya_image"><i class="fas fa-upload"></i> Upload</button>
                                                    </div>
                                                    <span class="upload-status text-muted small" id="status_mount_kenya_image"></span>
                                                    <?php $v = getSetting('mount_kenya_image'); if ($v): ?>
                                                    <div class="mt-2 d-flex align-items-center flex-wrap">
                                                        <?php if (file_exists(BASE_PATH . $v)): ?>
                                                        <img src="<?php echo SITE_URL . '/' . $v; ?>" class="preview-thumb" id="preview_mount_kenya_image">
                                                        <span class="text-muted small ml-2"><?php echo basename($v); ?></span>
                                                        <?php else: ?>
                                                        <span class="text-muted small">File missing — re-upload</span>
                                                        <?php endif; ?>
                                                        <button type="submit" name="remove_mount_kenya_image" value="1" class="btn btn-sm btn-outline-danger ml-2" onclick="return confirm('Remove?');"><i class="fas fa-times"></i> Remove</button>
                                                    </div>
                                                    <?php else: ?>
                                                    <div class="mt-2 d-flex align-items-center flex-wrap" id="preview_wrap_mount_kenya_image" style="display:none;"></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <hr class="mt-4">
                                        <h6 class="mb-3">Tour Package Images (Homepage & Tour Pages)</h6>
                                        <p class="text-muted mb-3">Upload images for the featured tour packages shown across the site. Recommended size: 600x400px.</p>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">Maasai Mara Great Migration Safari</label>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <input type="file" class="form-control-file" name="maasai_mara_image" accept="image/*" id="file_maasai_mara_image" style="flex:1;">
                                                        <button type="button" class="btn btn-sm btn-success upload-btn" data-key="maasai_mara_image"><i class="fas fa-upload"></i> Upload</button>
                                                    </div>
                                                    <span class="upload-status text-muted small" id="status_maasai_mara_image"></span>
                                                    <?php $v = getSetting('maasai_mara_image'); if ($v): ?>
                                                    <div class="mt-2 d-flex align-items-center flex-wrap">
                                                        <?php if (file_exists(BASE_PATH . $v)): ?>
                                                        <img src="<?php echo SITE_URL . '/' . $v; ?>" class="preview-thumb" id="preview_maasai_mara_image">
                                                        <span class="text-muted small ml-2"><?php echo basename($v); ?></span>
                                                        <?php else: ?>
                                                        <span class="text-muted small">File missing — re-upload</span>
                                                        <?php endif; ?>
                                                        <button type="submit" name="remove_maasai_mara_image" value="1" class="btn btn-sm btn-outline-danger ml-2" onclick="return confirm('Remove?');"><i class="fas fa-times"></i> Remove</button>
                                                    </div>
                                                    <?php else: ?>
                                                    <div class="mt-2 d-flex align-items-center flex-wrap" id="preview_wrap_maasai_mara_image" style="display:none;"></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">Uganda Gorilla Trekking Adventure</label>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <input type="file" class="form-control-file" name="uganda_gorilla_adventure_image" accept="image/*" id="file_uganda_gorilla_adventure_image" style="flex:1;">
                                                        <button type="button" class="btn btn-sm btn-success upload-btn" data-key="uganda_gorilla_adventure_image"><i class="fas fa-upload"></i> Upload</button>
                                                    </div>
                                                    <span class="upload-status text-muted small" id="status_uganda_gorilla_adventure_image"></span>
                                                    <?php $v = getSetting('uganda_gorilla_adventure_image'); if ($v): ?>
                                                    <div class="mt-2 d-flex align-items-center flex-wrap">
                                                        <?php if (file_exists(BASE_PATH . $v)): ?>
                                                        <img src="<?php echo SITE_URL . '/' . $v; ?>" class="preview-thumb" id="preview_uganda_gorilla_adventure_image">
                                                        <span class="text-muted small ml-2"><?php echo basename($v); ?></span>
                                                        <?php else: ?>
                                                        <span class="text-muted small">File missing — re-upload</span>
                                                        <?php endif; ?>
                                                        <button type="submit" name="remove_uganda_gorilla_adventure_image" value="1" class="btn btn-sm btn-outline-danger ml-2" onclick="return confirm('Remove?');"><i class="fas fa-times"></i> Remove</button>
                                                    </div>
                                                    <?php else: ?>
                                                    <div class="mt-2 d-flex align-items-center flex-wrap" id="preview_wrap_uganda_gorilla_adventure_image" style="display:none;"></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">Rwanda Luxury Gorilla Safari</label>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <input type="file" class="form-control-file" name="rwanda_luxury_gorilla_image" accept="image/*" id="file_rwanda_luxury_gorilla_image" style="flex:1;">
                                                        <button type="button" class="btn btn-sm btn-success upload-btn" data-key="rwanda_luxury_gorilla_image"><i class="fas fa-upload"></i> Upload</button>
                                                    </div>
                                                    <span class="upload-status text-muted small" id="status_rwanda_luxury_gorilla_image"></span>
                                                    <?php $v = getSetting('rwanda_luxury_gorilla_image'); if ($v): ?>
                                                    <div class="mt-2 d-flex align-items-center flex-wrap">
                                                        <?php if (file_exists(BASE_PATH . $v)): ?>
                                                        <img src="<?php echo SITE_URL . '/' . $v; ?>" class="preview-thumb" id="preview_rwanda_luxury_gorilla_image">
                                                        <span class="text-muted small ml-2"><?php echo basename($v); ?></span>
                                                        <?php else: ?>
                                                        <span class="text-muted small">File missing — re-upload</span>
                                                        <?php endif; ?>
                                                        <button type="submit" name="remove_rwanda_luxury_gorilla_image" value="1" class="btn btn-sm btn-outline-danger ml-2" onclick="return confirm('Remove?');"><i class="fas fa-times"></i> Remove</button>
                                                    </div>
                                                    <?php else: ?>
                                                    <div class="mt-2 d-flex align-items-center flex-wrap" id="preview_wrap_rwanda_luxury_gorilla_image" style="display:none;"></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">Amboseli & Kilimanjaro Views Safari</label>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <input type="file" class="form-control-file" name="amboseli_kilimanjaro_image" accept="image/*" id="file_amboseli_kilimanjaro_image" style="flex:1;">
                                                        <button type="button" class="btn btn-sm btn-success upload-btn" data-key="amboseli_kilimanjaro_image"><i class="fas fa-upload"></i> Upload</button>
                                                    </div>
                                                    <span class="upload-status text-muted small" id="status_amboseli_kilimanjaro_image"></span>
                                                    <?php $v = getSetting('amboseli_kilimanjaro_image'); if ($v): ?>
                                                    <div class="mt-2 d-flex align-items-center flex-wrap">
                                                        <?php if (file_exists(BASE_PATH . $v)): ?>
                                                        <img src="<?php echo SITE_URL . '/' . $v; ?>" class="preview-thumb" id="preview_amboseli_kilimanjaro_image">
                                                        <span class="text-muted small ml-2"><?php echo basename($v); ?></span>
                                                        <?php else: ?>
                                                        <span class="text-muted small">File missing — re-upload</span>
                                                        <?php endif; ?>
                                                        <button type="submit" name="remove_amboseli_kilimanjaro_image" value="1" class="btn btn-sm btn-outline-danger ml-2" onclick="return confirm('Remove?');"><i class="fas fa-times"></i> Remove</button>
                                                    </div>
                                                    <?php else: ?>
                                                    <div class="mt-2 d-flex align-items-center flex-wrap" id="preview_wrap_amboseli_kilimanjaro_image" style="display:none;"></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- SEO Pages Settings -->
                                    <div class="tab-pane fade" id="seo">
                                        <p class="text-muted">Tour page hero images and package card images have been moved to the <strong>Tour Pages</strong> tab.</p>
                                    </div>

                                    <!-- Social Settings -->
                                    <div class="tab-pane fade" id="social">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">Facebook URL</label>
                                                    <input type="url" class="form-control" name="facebook_url" value="<?php echo htmlspecialchars($settings['facebook_url']); ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">Instagram URL</label>
                                                    <input type="url" class="form-control" name="instagram_url" value="<?php echo htmlspecialchars($settings['instagram_url']); ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">Twitter URL</label>
                                                    <input type="url" class="form-control" name="twitter_url" value="<?php echo htmlspecialchars($settings['twitter_url']); ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">YouTube URL</label>
                                                    <input type="url" class="form-control" name="youtube_url" value="<?php echo htmlspecialchars($settings['youtube_url']); ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">TripAdvisor URL</label>
                                            <input type="url" class="form-control" name="tripadvisor_url" value="<?php echo htmlspecialchars($settings['tripadvisor_url']); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-4 pt-3 border-top">
                                    <button type="submit" class="btn btn-outline-secondary">
                                        <i class="fas fa-save"></i> Save All Settings
                                    </button>
                                </div>
                            </form>
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

    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-easing/1.4.1/jquery.easing.min.js"></script>
    <script src="../templates/assets/js/ruang-admin.min.js"></script>
    <script>
    var PHP_UPLOAD_MAX = <?= (int)(ini_get('upload_max_filesize')) * 1024 * 1024; ?>; // bytes
    
    function formatBytes(bytes) {
        if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
        if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return bytes + ' B';
    }
    
    $(function() {
        $('.upload-btn').on('click', function() {
            var key = $(this).data('key');
            var fileInput = $('#file_' + key)[0];
            var statusEl = $('#status_' + key);
            
            if (!fileInput.files || !fileInput.files[0]) {
                statusEl.html('<span class="text-danger">Select a file first</span>');
                return;
            }
            
            var file = fileInput.files[0];
            if (file.size > PHP_UPLOAD_MAX) {
                statusEl.html('<span class="text-danger"><i class="fas fa-times"></i> File too large (' + formatBytes(file.size) + '). Max allowed: ' + formatBytes(PHP_UPLOAD_MAX) + '. Resize image and try again.</span>');
                return;
            }
            
            var btn = $(this);
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Uploading...');
            statusEl.html('<span class="text-info">Uploading ' + formatBytes(file.size) + ', please wait...</span>');
            
            var formData = new FormData();
            formData.append('ajax_upload', '1');
            formData.append('field_key', key);
            formData.append(key, file);
            
            $.ajax({
                url: '',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(resp) {
                    if (resp.success) {
                        statusEl.html('<span class="text-success"><i class="fas fa-check"></i> Uploaded: ' + resp.file + '</span>');
                        var wrap = $('#preview_wrap_' + key);
                        if (wrap.length) {
                            wrap.show().html('<img src="' + resp.url + '" class="preview-thumb"> <span class="text-muted small ml-2">' + resp.file + '</span>');
                        }
                        $('#preview_' + key).attr('src', resp.url);
                    } else {
                        statusEl.html('<span class="text-danger"><i class="fas fa-times"></i> ' + resp.message + '</span>');
                    }
                },
                error: function(jqXHR) {
                    var txt = jqXHR.responseText || 'Server error (no response)';
                    statusEl.html('<span class="text-danger"><i class="fas fa-times"></i> ' + txt.substring(0, 200) + '</span>');
                },
                complete: function() {
                    btn.prop('disabled', false).html('<i class="fas fa-upload"></i> Upload');
                    fileInput.value = '';
                }
            });
        });
    });
    </script>
</body>
</html>
