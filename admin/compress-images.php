<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once __DIR__ . '/../includes/image-compressor.php';

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

$compressor = new ImageCompressor(BASE_PATH);
$compressor->setScanDirs($scanDirs);
$compressor->setParams(70, 1920);
$hasLibrary = ImageCompressor::hasLibrary();

// ------------------------------------------------ JSON job endpoints
$isAjax = ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax'] ?? '') === '1');
if ($isAjax) {
    verify_csrf();
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? '';

    $respond = function (array $payload) {
        echo json_encode($payload);
        exit;
    };

    try {
        switch ($action) {
            case 'start':
                if (!$compressor->acquireLock()) {
                    $respond(['ok' => false, 'error' => 'Another compression job is already running. Please wait for it to finish.']);
                }
                $selection = isset($_POST['paths']) ? array_values(array_filter(array_map('trim', (array)$_POST['paths']))) : null;
                $state = $compressor->startJob($selection);
                $compressor->releaseLock();
                $respond(['ok' => true, 'state' => compressorTotals($state)]);
            case 'batch':
                if (!$compressor->acquireLock()) {
                    $respond(['ok' => false, 'error' => 'Another compression job is already running. Please wait for it to finish.']);
                }
                $state = $compressor->processBatch(8000, 4);
                $compressor->releaseLock();
                $respond(['ok' => true, 'state' => compressorTotals($state)]);
            case 'status':
                $respond(['ok' => true, 'state' => compressorTotals($compressor->readState())]);
            case 'reset':
                $compressor->resetState();
                $respond(['ok' => true]);
        }
        $respond(['ok' => false, 'error' => 'Unknown action.']);
    } catch (\Throwable $e) {
        $compressor->releaseLock();
        error_log("Compress images error: " . $e->getMessage());
        $respond(['ok' => false, 'error' => $e->getMessage()]);
    }
}

// ------------------------------------------------ Replace (legacy page POST)
$replaceMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'replace') {
    verify_csrf();
    $relPath = trim($_POST['rel_path'] ?? '');
    $absPath = realpath(__DIR__ . '/../' . $relPath);
    $baseDir = realpath(__DIR__ . '/..');
    if ($absPath && $baseDir && strpos($absPath, $baseDir) === 0 && file_exists($absPath) && isset($_FILES['replace_image'])) {
        $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $tmp = $_FILES['replace_image']['tmp_name'];
            if (is_uploaded_file($tmp) && $_FILES['replace_image']['error'] === UPLOAD_ERR_OK) {
                if (move_uploaded_file($tmp, $absPath)) {
                    $replaceMessage = 'Image replaced successfully: ' . htmlspecialchars($relPath);
                }
            }
        }
    }
}

function compressorTotals($state)
{
    if (!is_array($state)) {
        return ['running' => false, 'files' => [], 'total' => 0, 'index' => 0, 'done' => 0, 'skipped' => 0, 'errors' => 0, 'saved' => 0, 'result' => null];
    }
    $files = $state['files'] ?? [];
    $done = 0;
    $skipped = 0;
    $errors = 0;
    $saved = 0;
    foreach ($files as $f) {
        $st = $f['status'] ?? 'pending';
        if ($st === 'done') {
            $done++;
            $saved += max(0, (int)($f['orig'] ?? 0) - (int)($f['new'] ?? 0));
        } elseif ($st === 'skipped') {
            $skipped++;
        } elseif ($st === 'error') {
            $errors++;
        }
    }
    return [
        'running' => !($state['done'] ?? false),
        'files'   => $files,
        'total'   => count($files),
        'index'   => (int)($state['index'] ?? 0),
        'done'    => $done,
        'skipped' => $skipped,
        'errors'  => $errors,
        'saved'   => $saved,
        'result'  => ($state['done'] ?? false) ? ['saved_bytes' => (int)($state['total_saved'] ?? $saved), 'total_orig' => (int)($state['total_orig'] ?? 0)] : null,
    ];
}

// ------------------------------------------------ Page listing
$allImageFiles = $compressor->scan();
$grouped = [];
foreach ($allImageFiles as $file) {
    $dirName = dirname($file['rel']);
    if ($dirName === '.') $dirName = 'root';
    $grouped[$dirName][] = [
        'rel'  => $file['rel'],
        'abs'  => $file['abs'],
        'size' => $file['size'],
        'ext'  => $file['ext'],
        'name' => basename($file['abs']),
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
        .result-log .error { color: #ff6b6b; }
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
        .progress { height: 22px; }
        .progress-label { font-size: 0.85rem; color: #555; }
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
            <li class="nav-item"><a class="nav-link" href="faqs"><i class="fas fa-fw fa-question-circle"></i><span>FAQs</span></a></li>
            <li class="nav-item"><a class="nav-link" href="gallery"><i class="fas fa-fw fa-images"></i><span>Gallery</span></a></li>
            <li class="nav-item"><a class="nav-link" href="quotes"><i class="fas fa-fw fa-file-invoice"></i><span>Quotes</span></a></li>
            <li class="nav-item"><a class="nav-link" href="inquiries"><i class="fas fa-fw fa-envelope"></i><span>Inquiries</span></a></li>
            <li class="nav-item"><a class="nav-link" href="pages"><i class="fas fa-fw fa-file-alt"></i><span>Pages</span></a></li>
            <li class="nav-item"><a class="nav-link" href="search"><i class="fas fa-fw fa-search"></i><span>Search</span></a></li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Tools</div>
            <li class="nav-item active"><a class="nav-link" href="compress-images"><i class="fas fa-fw fa-compress-alt"></i><span>Compress Images</span></a></li>
            <li class="nav-item"><a class="nav-link" href="sitemap"><i class="fas fa-fw fa-sitemap"></i><span>Sitemap</span></a></li>
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
                        <div class="alert alert-danger"><i class="fas fa-exclamation-triangle mr-2"></i>No image library found (GD). Install the GD PHP extension to use compression.</div>
                    <?php endif; ?>

                    <!-- Compression Tool -->
                    <div class="card compress-card mb-4">
                        <div class="card-header py-3 d-flex align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold" style="color:#0A2540;"><i class="fas fa-compress-alt mr-2"></i>Compression Tool</h6>
                            <div id="jobSummary" class="text-muted" style="font-size:0.85rem;"></div>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">Compresses images (<strong>70% quality</strong>, max width <strong>1920px</strong>) across <code>uploads/</code>, <code>assets/images/</code>, and <code>templates/assets/img/</code>. Images smaller than 10 KB and files that are already optimized are skipped automatically. Work runs in short batches, so large libraries never time out.</p>
                            <div id="jobControls">
                                <button type="button" id="compressAllBtn" class="btn btn-primary" <?php echo $hasLibrary ? '' : 'disabled'; ?>>
                                    <i class="fas fa-compress-alt mr-2"></i>Compress All Images
                                </button>
                                <button type="button" id="compressSelectedBtn" class="btn btn-outline-info" <?php echo $hasLibrary ? '' : 'disabled'; ?>>
                                    <i class="fas fa-compress-alt mr-2"></i>Compress Selected (<span id="selectedCount">0</span>)
                                </button>
                                <button type="button" id="resetHistoryBtn" class="btn btn-outline-secondary" title="Forget optimization history. Next run will recompress every image.">
                                    <i class="fas fa-undo mr-1"></i>Reset History
                                </button>
                            </div>
                            <div id="progressWrap" style="display:none;" class="mt-3">
                                <div class="d-flex justify-content-between progress-label mb-1">
                                    <span id="progressLabel">Starting…</span>
                                    <span id="progressPct">0%</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar" id="progressBar" role="progressbar" style="width:0%;background:#0A2540;"></div>
                                </div>
                            </div>
                            <div id="resultSection" style="display:none;" class="mt-3">
                                <div class="result-log" id="resultLog"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Image library -->
                    <div class="card compress-card mb-4">
                        <div class="card-header py-3 d-flex align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold" style="color:#0A2540;"><i class="fas fa-images mr-2"></i>All Images <span class="badge badge-secondary ml-2"><?php echo $totalImages; ?></span></h6>
                            <div id="toolbar">
                                <button class="btn btn-sm btn-outline-primary" id="selectAllBtn"><i class="fas fa-check-square mr-1"></i>Select All</button>
                                <button class="btn btn-sm btn-outline-secondary" id="deselectAllBtn"><i class="fas fa-square mr-1"></i>Deselect All</button>
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
                    <?php csrf_field(); ?>
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
        var CSRF = <?php echo json_encode(csrf_token()); ?>;
        var jobBusy = false;
        var seenPaths = {};
        var totalSavedBytes = 0;

        function updateCount() {
            var checked = document.querySelectorAll('.img-check:checked').length;
            document.getElementById('selectedCount').textContent = checked;
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

        function selectedPaths() {
            var paths = [];
            document.querySelectorAll('.img-check:checked').forEach(function(cb) { paths.push(cb.value); });
            return paths;
        }

        function kb(n) { return (n / 1024).toFixed(1) + ' KB'; }

        function appendLog(line, cls) {
            var log = document.getElementById('resultLog');
            var div = document.createElement('div');
            div.className = cls || '';
            div.textContent = line;
            log.appendChild(div);
            log.scrollTop = log.scrollHeight;
        }

        function renderNewProgress(state) {
            seenPaths = seenPaths || {};
            (state.files || []).forEach(function(f) {
                if (seenPaths[f.rel]) return;
                var st = f.status || 'pending';
                if (st === 'pending') return;
                seenPaths[f.rel] = true;
                if (st === 'done') {
                    var saved = Math.max(0, (f.orig || 0) - (f.new || 0));
                    totalSavedBytes += saved;
                    appendLog('OK: ' + f.rel + ' - ' + kb(f.orig) + ' -> ' + kb(f.new) + ' (' + Math.round((saved / (f.orig || 1)) * 100) + '% saved)', 'ok');
                } else if (st === 'skipped') {
                    appendLog('-: ' + f.rel + ' - already optimal (' + kb(f.orig || 0) + ')', 'optimal');
                } else if (st === 'error') {
                    appendLog('ERR: ' + f.rel + ' - ' + (f.error || 'failed'), 'error');
                }
            });
            var pct = state.total ? Math.round((state.index / state.total) * 100) : 100;
            document.getElementById('progressBar').style.width = pct + '%';
            document.getElementById('progressPct').textContent = pct + '%';
            document.getElementById('progressLabel').textContent =
                'Processed ' + state.index + ' of ' + state.total + ' — saved ' + kb(totalSavedBytes) + ' so far';
            document.getElementById('jobSummary').textContent =
                state.done + ' compressed, ' + state.skipped + ' already optimal, ' + state.errors + ' errors';
        }

        function renderFinished(state) {
            document.getElementById('progressBar').style.width = '100%';
            document.getElementById('progressPct').textContent = '100%';
            document.getElementById('progressLabel').textContent = 'Done — total saved ' + kb(totalSavedBytes);
            appendLog('Done! Total saved: ' + kb(totalSavedBytes), 'done');
            appendLog('Library: GD (batched)', 'done');
            jobBusy = false;
            document.getElementById('compressAllBtn').disabled = false;
            document.getElementById('compressSelectedBtn').disabled = false;
        }

        function api(action, body) {
            var payload = Object.assign({ ajax: '1', action: action, csrf_token: CSRF }, body || {});
            var fd = new FormData();
            Object.keys(payload).forEach(function(k) {
                var v = payload[k];
                if (k === 'paths') { v.forEach(function(p) { fd.append('paths[]', p); }); }
                else { fd.append(k, v); }
            });
            return fetch(window.location.pathname, { method: 'POST', body: fd })
                .then(function(r) { return r.json(); });
        }

        function runJob(paths) {
            if (jobBusy) { alert('A compression job is already running.'); return; }
            jobBusy = true;
            seenPaths = {};
            totalSavedBytes = 0;
            document.getElementById('compressAllBtn').disabled = true;
            document.getElementById('compressSelectedBtn').disabled = true;
            document.getElementById('resultLog').innerHTML = '';
            document.getElementById('resultSection').style.display = 'block';
            document.getElementById('progressWrap').style.display = 'block';
            document.getElementById('progressBar').style.width = '0%';
            document.getElementById('progressPct').textContent = '0%';
            document.getElementById('progressLabel').textContent = 'Starting…';

            api('start', { paths: paths })
                .then(function(res) {
                    if (!res.ok) { throw new Error(res.error || 'Could not start job.'); }
                    renderNewProgress(res.state);
                    return step();
                })
                .catch(function(err) {
                    jobBusy = false;
                    document.getElementById('compressAllBtn').disabled = false;
                    document.getElementById('compressSelectedBtn').disabled = false;
                    appendLog('Error: ' + err.message, 'error');
                });

            function step() {
                return api('batch').then(function(res) {
                    if (!res.ok) { throw new Error(res.error || 'Batch failed.'); }
                    renderNewProgress(res.state);
                    if (res.state.running) {
                        return step();
                    }
                    renderFinished(res.state);
                });
            }
        }

        document.getElementById('compressAllBtn').addEventListener('click', function() {
            runJob([]);
        });

        document.getElementById('compressSelectedBtn').addEventListener('click', function() {
            var paths = selectedPaths();
            if (paths.length === 0) { alert('Select at least one image to compress.'); return; }
            runJob(paths);
        });

        document.getElementById('resetHistoryBtn').addEventListener('click', function() {
            if (!confirm('Forget the optimization history? The next run will recompress every image (unchanged ones will be skipped as already optimal).')) return;
            api('reset').then(function() { alert('History reset.'); }).catch(function() { alert('Could not reset history.'); });
        });
    </script>
</body>
</html>