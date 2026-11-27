<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/admin-auth.php';
adminRequireLogin();
requireAdminPermission('manage_tours');
requireAdminPermission('manage_media');
require_once '../includes/tour-image-repair.php';
require_once '../includes/tour-image-audit.php';
require_once '../includes/tour-image-restore.php';
require_once '../includes/tour-image-disk-repair.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    header('Content-Type: application/json; charset=utf-8');
    $lock = null;
    try {
        $db = db();
        $action = $_POST['action'] ?? '';
        $cursor = max(0, (int)($_POST['cursor'] ?? 0));
        if (in_array($action, ['server_start', 'server_batch'], true)) {
            if (!is_dir(BASE_PATH . 'cache') && !mkdir(BASE_PATH . 'cache', 0755, true)) throw new RuntimeException('Cannot create cache folder.');
            $lock = fopen(BASE_PATH . 'cache/tour-image-repair.lock', 'c');
            if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('Another image job is running. Retry shortly.');
            $disk = new TourImageDiskRepair(BASE_PATH);
            if ($action === 'server_start') {
                $job = bin2hex(random_bytes(16));
                $state = $disk->start($_SERVER['DOCUMENT_ROOT'] ?? '');
                $state['cursor'] = 0; $state['done'] = false;
                $state['totals'] = ['permissions_fixed'=>0,'valid'=>0,'failed'=>count($state['entries'])];
                if (file_put_contents(BASE_PATH . 'cache/server-image-job-' . $job . '.json', json_encode($state, JSON_INVALID_UTF8_SUBSTITUTE), LOCK_EX) === false) throw new RuntimeException('Cannot save server image job.');
                $_SESSION['tour_image_disk_job'] = $job; $_SESSION['tour_image_disk_done'] = false;
                adminLogActivity('started', 'media', null, 'Repair existing server images');
                echo json_encode(['ok'=>true,'job'=>$job,'diagnostics'=>$state['diagnostics'],'entries'=>$state['entries']], JSON_INVALID_UTF8_SUBSTITUTE);
            } else {
                $job = $_SESSION['tour_image_disk_job'] ?? '';
                if (!preg_match('/^[a-f0-9]{32}$/', $job) || !hash_equals($job, (string)($_POST['job'] ?? ''))) throw new RuntimeException('Server image job expired. Start it again; existing photos are preserved.');
                $stateFile = BASE_PATH . 'cache/server-image-job-' . $job . '.json';
                $state = json_decode(file_get_contents($stateFile), true);
                if (!is_array($state)) throw new RuntimeException('Cannot read server image job. Start a new scan.');
                $result = $disk->batch($state['files'], $state['cursor']);
                $state['cursor'] = $result['cursor']; $state['done'] = $result['done'];
                foreach (array_keys($state['totals']) as $key) $state['totals'][$key] += $result[$key];
                if (file_put_contents($stateFile, json_encode($state, JSON_INVALID_UTF8_SUBSTITUTE), LOCK_EX) === false) throw new RuntimeException('Cannot save image job progress. Retry the scan.');
                $_SESSION['tour_image_disk_done'] = $result['done'];
                echo json_encode(['ok'=>true,'result'=>$result,'totals'=>$state['totals'],'diagnostics'=>$state['diagnostics']], JSON_INVALID_UTF8_SUBSTITUTE);
            }
        } elseif (in_array($action, ['restore_upload', 'restore_batch'], true)) {
            if (!class_exists('ZipArchive')) throw new RuntimeException('PHP ZIP extension is unavailable. Ask hosting to enable it, or extract the backup uploads folder with the hosting file manager.');
            if (!is_dir(BASE_PATH . 'cache') && !mkdir(BASE_PATH . 'cache', 0755, true)) throw new RuntimeException('Cannot create cache folder.');
            $lock = fopen(BASE_PATH . 'cache/tour-image-repair.lock', 'c');
            if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('Another image job is running. Retry shortly.');
            if ($action === 'restore_upload') {
                $upload = $_FILES['backup'] ?? null;
                if (!$upload || (int)$upload['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('ZIP upload failed. Check the server upload size limit or use a smaller archive containing images only.');
                if (strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION)) !== 'zip' || $upload['size'] > 67108864) throw new RuntimeException('Choose an image backup ZIP smaller than 64 MB, or split it into smaller archives.');
                $restoreJob = bin2hex(random_bytes(16));
                $archive = BASE_PATH . 'cache/image-backup-' . $restoreJob . '.zip';
                if (!is_uploaded_file($upload['tmp_name']) || !move_uploaded_file($upload['tmp_name'], $archive)) throw new RuntimeException('Cannot save uploaded backup ZIP.');
                $probe = new ZipArchive();
                if ($probe->open($archive) !== true) { unlink($archive); throw new RuntimeException('Invalid ZIP archive.'); }
                $total = $probe->numFiles; $probe->close();
                if ($total > 25000) { unlink($archive); throw new RuntimeException('Too many archive entries. Upload an images-only ZIP.'); }
                $_SESSION['tour_image_restore'] = ['job' => $restoreJob, 'cursor' => 0, 'done' => false, 'totals' => ['restored' => 0, 'kept' => 0, 'failed' => 0, 'ignored' => 0]];
                adminLogActivity('started', 'media', null, 'Restore images from backup ZIP');
                echo json_encode(['ok' => true, 'job' => $restoreJob, 'total' => $total]);
            } else {
                $restoreState = $_SESSION['tour_image_restore'] ?? null;
                if (!$restoreState || !hash_equals($restoreState['job'], (string)($_POST['job'] ?? ''))) throw new RuntimeException('Backup restore session expired. Upload the ZIP again; existing files are kept.');
                if ($restoreState['done']) {
                    echo json_encode(['ok' => true, 'result' => $restoreState['last'], 'totals' => $restoreState['totals']], JSON_INVALID_UTF8_SUBSTITUTE);
                } else {
                    $archive = BASE_PATH . 'cache/image-backup-' . $restoreState['job'] . '.zip';
                    $result = (new TourImageRestore(BASE_PATH))->batch($archive, $restoreState['cursor']);
                    $restoreState['cursor'] = $result['cursor']; $restoreState['done'] = $result['done']; $restoreState['last'] = $result;
                    foreach (array_keys($restoreState['totals']) as $key) $restoreState['totals'][$key] += $result[$key];
                    $_SESSION['tour_image_restore'] = $restoreState;
                    if ($result['done']) unlink($archive);
                    echo json_encode(['ok' => true, 'result' => $result, 'totals' => $restoreState['totals']], JSON_INVALID_UTF8_SUBSTITUTE);
                }
            }
        } elseif ($action === 'audit') {
            session_write_close();
            $rows = $db->fetchAll('SELECT * FROM tour_packages WHERE id > ? ORDER BY id LIMIT 10', [$cursor]);
            $days = [];
            if ($rows && tourImageTableExists($db, 'itinerary_days')) {
                $ids = array_column($rows, 'id');
                $marks = implode(',', array_fill(0, count($ids), '?'));
                foreach ($db->fetchAll("SELECT * FROM itinerary_days WHERE tour_id IN ($marks) ORDER BY tour_id, day_number", $ids) as $day) $days[$day['tour_id']][] = $day;
            }
            $audit = new TourImageAudit(BASE_PATH); $results = [];
            foreach ($rows as $row) {
                $results[] = $audit->tour($row, $days[$row['id']] ?? []);
                $cursor = (int)$row['id'];
            }
            echo json_encode(['ok' => true, 'rows' => $results, 'cursor' => $cursor, 'done' => count($rows) < 10], JSON_INVALID_UTF8_SUBSTITUTE);
        } else {
            if (!in_array($action, ['start', 'batch'], true)) throw new RuntimeException('Unknown image repair action.');
            if (!is_dir(BASE_PATH . 'cache') && !mkdir(BASE_PATH . 'cache', 0755, true)) throw new RuntimeException('Cannot create cache folder.');
            $lock = fopen(BASE_PATH . 'cache/tour-image-repair.lock', 'c');
            if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('Another image repair batch is running. Try again shortly.');
            $stages = [
                'tours' => ['tour_packages', ['image', 'hero_image', 'overview_image_1', 'overview_image_2', 'overview_image_3', 'gallery']],
                'itinerary' => ['itinerary_days', ['image_path']],
                'gallery' => ['gallery', ['image']],
            ];
            $stage = $_POST['stage'] ?? 'tours';
            if (!isset($stages[$stage])) throw new RuntimeException('Invalid repair stage.');
            $job = $_SESSION['tour_image_repair_job'] ?? null;
            if ($action === 'start') {
                $job = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(6));
                $_SESSION['tour_image_repair_job'] = $job;
                $_SESSION['tour_image_repair_target'] = max(0, (int)($_POST['tour_id'] ?? 0));
                adminLogActivity('started', 'media', $_SESSION['tour_image_repair_target'] ?: null, 'Fix tour images');
                $stage = 'tours'; $cursor = 0;
            }
            if (!$job || !preg_match('/^[0-9]{8}-[0-9]{6}-[a-f0-9]{12}$/', $job)) throw new RuntimeException('Start a repair job first.');
            if (($_POST['job'] ?? $job) !== $job) throw new RuntimeException('Another repair was started in your session. Reload to see current images.');
            $target = (int)($_SESSION['tour_image_repair_target'] ?? 0);
            session_write_close();
            [$table, $fields] = $stages[$stage];
            $where = 'id > ?'; $params = [$cursor];
            if ($target) {
                if ($stage === 'tours') { $where .= ' AND id = ?'; $params[] = $target; }
                elseif ($stage === 'itinerary') { $where .= ' AND tour_id = ?'; $params[] = $target; }
            }
            $rows = ($target && $stage === 'gallery') || !tourImageTableExists($db, $table) ? [] : $db->fetchAll("SELECT * FROM `$table` WHERE $where ORDER BY id LIMIT 2", $params);
            $repair = new TourImageRepair(BASE_PATH);
            $totals = ['checked' => 0, 'updated' => 0, 'converted' => 0, 'success' => 0, 'failed' => 0, 'review' => 0, 'issues' => [], 'entries' => []];
            foreach ($rows as $row) {
                $result = $repair->repairRow($db, $table, $row, $fields, BASE_PATH . 'cache/tour-image-repair-' . $job . '.jsonl');
                foreach (['checked', 'updated', 'converted', 'success', 'failed', 'review'] as $key) $totals[$key] += $result[$key];
                foreach (['issues', 'entries'] as $key) $totals[$key] = array_merge($totals[$key], $result[$key]);
                $cursor = (int)$row['id'];
            }
            $done = false;
            if (count($rows) < 2) {
                $keys = array_keys($stages); $next = array_search($stage, $keys, true) + 1;
                if ($next >= count($keys)) $done = true;
                else { $stage = $keys[$next]; $cursor = 0; }
            }
            echo json_encode(['ok' => true, 'stage' => $stage, 'cursor' => $cursor, 'done' => $done, 'result' => $totals, 'job' => $job, 'backup' => 'tour-image-repair-' . $job . '.jsonl'], JSON_INVALID_UTF8_SUBSTITUTE);
        }
    } catch (Throwable $e) {
        error_log('Tour image repair: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE);
    } finally {
        if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
    }
    exit;
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Tour Image Health - Admin</title>
<link rel="stylesheet" href="../templates/assets/vendor/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="../templates/assets/vendor/fontawesome-free/css/all.min.css">
<link rel="stylesheet" href="../templates/assets/css/ruang-admin.min.css">
<style>
body{background:#f3f7f5;color:#23352d}.image-layout{display:flex;min-height:100vh}.image-sidebar{width:230px;background:#fff;border-right:1px solid #dce6df;padding:24px 12px;flex-shrink:0}.image-main{padding:28px;flex:1;min-width:0}.health-cards{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}.health-card{background:#fff;border:1px solid #dce6df;border-radius:8px;padding:16px}.health-card strong{display:block;font-size:28px;color:#0b6b3a}.health-card span{font-size:13px}.image-preview{width:80px;height:55px;object-fit:cover;border-radius:5px}.image-note{font-size:12px;overflow-wrap:anywhere}.image-table td{vertical-align:top}.image-sidebar .nav-link{color:#23352d}.image-sidebar ul{padding:0;list-style:none}.image-sidebar .nav-item{list-style:none}.status-failed{color:#b42318}.status-success{color:#087443}.status-review{color:#946200}@media(max-width:900px){.health-cards{grid-template-columns:repeat(2,1fr)}.image-sidebar{width:180px}.image-main{padding:16px}}@media(max-width:600px){.image-layout{display:block}.image-sidebar{width:100%;border-bottom:1px solid #dce6df;padding:12px}.image-main{padding:12px}}
</style></head>
<body><div class="image-layout"><aside class="image-sidebar"><a href="dashboard" class="d-block mb-3 font-weight-bold">Kizza Tours Admin</a><ul><?php echo adminToursSidebarMenu(); ?></ul><a class="nav-link" href="dashboard">Dashboard</a><a class="nav-link" href="compress-images">Compress Images</a></aside>
<main class="image-main">
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3"><div><h1 class="h3 mb-1">Tour Image Health</h1><p class="text-muted mb-0">Check tour photos, repair paths and prepare WebP images.</p></div><a href="tours" class="btn btn-outline-secondary mt-2">Back to Tours</a></div>
<div class="health-cards mb-3">
<div class="health-card"><strong id="tourTotal">0</strong><span>Tours checked</span></div>
<div class="health-card"><strong id="tourFailed">0</strong><span>Tours with image problems</span></div>
<div class="health-card"><strong id="imageFailed">0</strong><span>Broken image references</span></div>
<div class="health-card"><strong id="imageRecoverable">0</strong><span>Paths ready to repair</span></div>
<div class="health-card"><strong id="imageOptimize">0</strong><span>Images to optimize</span></div></div>
<div class="card mb-3"><div class="card-body">
<div class="alert alert-success">Shared image protection is enabled: editing or deleting a tour no longer removes its reusable photos.</div>
<p><strong>Photos already exist in your hosting folders?</strong> Use the server repair below. There is no need to download or upload them.</p>
<button class="btn btn-success mb-2" id="serverStart">Fix Images Already on Server</button>
<p id="serverStatus" class="small mb-2" role="status" aria-live="polite"></p>
<details class="mb-3"><summary>Server folder diagnostics</summary><pre id="serverDiagnostics" class="small" style="white-space:pre-wrap;overflow-wrap:anywhere"></pre></details>
<p>Featured, hero, overview, gallery and itinerary photos are included. Original files are preserved. Missing or ambiguous files require selecting or uploading the correct photo. Remote image URLs are marked for review; this scan checks local files, not external HTTP availability.</p>
<button class="btn btn-outline-primary" id="scan">Rescan Images</button>
<button class="btn btn-success" id="start" disabled>Fix All Tour Images</button>
<button class="btn btn-outline-secondary" id="stop" disabled>Pause</button>
<p class="mt-3 mb-1" id="status" role="status" aria-live="polite">Starting image scan...</p>
<p id="totals" class="mb-1"></p><p id="backup" class="small text-muted mb-0"></p>
</div></div>
<details class="card mb-3"><summary class="card-header"><strong>Optional: restore genuinely missing images from backup</strong></summary><div class="card-body">
<p>Upload one ZIP containing images from your hosting or local backup, with the original filenames. The system restores missing files in batches and keeps existing files. A full backup with <code>uploads/tours</code> or <code>uploads/gallery</code> folders restores the original paths. A photos-only ZIP is searched by original filename during Fix All.</p>
<input type="file" id="restoreFile" accept=".zip,application/zip" class="form-control-file mb-2">
<button class="btn btn-outline-success" id="restoreStart">Restore Images from ZIP</button>
<p class="small text-muted mt-2 mb-0">Maximum 64 MB per ZIP; your hosting upload limit may be lower. Use several archives if needed. Files that have no backup cannot be reconstructed by conversion.</p>
<p id="restoreStatus" class="mt-2 mb-0" role="status" aria-live="polite"></p>
</div></details>
<div class="card mb-3"><div class="card-header d-flex justify-content-between"><strong>Tour image problems</strong><label class="mb-0 small"><input type="checkbox" id="showAll"> Show healthy tours too</label></div><div class="table-responsive"><table class="table image-table mb-0"><thead><tr><th>Tour</th><th>Image status and reason</th><th>Action</th></tr></thead><tbody id="auditRows"></tbody></table></div><p id="emptyAudit" class="p-3 mb-0">Scanning...</p></div>
<div class="card"><div class="card-header"><strong>Repair results</strong> <select id="resultFilter" class="ml-2"><option value="all">All results</option><option value="failed">Failed</option><option value="success">Success</option><option value="review">Needs review</option></select></div><div class="table-responsive"><table class="table image-table mb-0"><thead><tr><th>Record / Image</th><th>Result</th><th>Details</th></tr></thead><tbody id="resultRows"></tbody></table></div><p id="emptyResults" class="p-3 mb-0">Run Fix to see successes and failures here.</p></div>
</main></div>
<script>
window.TOUR_IMAGE_HEALTH = <?php echo json_encode(['token' => $_SESSION['csrf_token'], 'siteUrl' => SITE_URL, 'restoreJob' => empty($_SESSION['tour_image_restore']['done']) ? ($_SESSION['tour_image_restore']['job'] ?? '') : '', 'serverJob' => empty($_SESSION['tour_image_disk_done']) ? ($_SESSION['tour_image_disk_job'] ?? '') : ''], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script><script src="js/tour-image-health.js?v=3" defer></script></body></html>
