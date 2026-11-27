<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/admin-auth.php';
adminRequireLogin();
requireAdminPermission('manage_tours');
requireAdminPermission('manage_media');
require_once '../includes/tour-image-repair.php';
require_once '../includes/tour-image-audit.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    header('Content-Type: application/json; charset=utf-8');
    $lock = null;
    try {
        $db = db();
        $action = $_POST['action'] ?? '';
        $cursor = max(0, (int)($_POST['cursor'] ?? 0));
        if ($action === 'audit') {
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
<p>Featured, hero, overview, gallery and itinerary photos are included. Original files are preserved. Missing or ambiguous files require selecting or uploading the correct photo. Remote image URLs are marked for review; this scan checks local files, not external HTTP availability.</p>
<button class="btn btn-outline-primary" id="scan">Rescan Images</button>
<button class="btn btn-success" id="start" disabled>Fix All Tour Images</button>
<button class="btn btn-outline-secondary" id="stop" disabled>Pause</button>
<p class="mt-3 mb-1" id="status" role="status" aria-live="polite">Starting image scan...</p>
<p id="totals" class="mb-1"></p><p id="backup" class="small text-muted mb-0"></p>
</div></div>
<div class="card mb-3"><div class="card-header d-flex justify-content-between"><strong>Tour image problems</strong><label class="mb-0 small"><input type="checkbox" id="showAll"> Show healthy tours too</label></div><div class="table-responsive"><table class="table image-table mb-0"><thead><tr><th>Tour</th><th>Image status and reason</th><th>Action</th></tr></thead><tbody id="auditRows"></tbody></table></div><p id="emptyAudit" class="p-3 mb-0">Scanning...</p></div>
<div class="card"><div class="card-header"><strong>Repair results</strong> <select id="resultFilter" class="ml-2"><option value="all">All results</option><option value="failed">Failed</option><option value="success">Success</option><option value="review">Needs review</option></select></div><div class="table-responsive"><table class="table image-table mb-0"><thead><tr><th>Record / Image</th><th>Result</th><th>Details</th></tr></thead><tbody id="resultRows"></tbody></table></div><p id="emptyResults" class="p-3 mb-0">Run Fix to see successes and failures here.</p></div>
</main></div>
<script>
window.TOUR_IMAGE_HEALTH = <?php echo json_encode(['token' => $_SESSION['csrf_token'], 'siteUrl' => SITE_URL], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script><script src="js/tour-image-health.js?v=1" defer></script></body></html>
