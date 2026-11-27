<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/admin-auth.php';
adminRequireLogin();
requireAdminPermission('manage_tours');
requireAdminPermission('manage_media');
require_once '../includes/tour-image-repair.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    header('Content-Type: application/json; charset=utf-8');
    $lock = null;
    try {
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
        $cursor = max(0, (int)($_POST['cursor'] ?? 0));
        $job = $_SESSION['tour_image_repair_job'] ?? null;
        if (($_POST['action'] ?? '') === 'start') {
            $job = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(6));
            $_SESSION['tour_image_repair_job'] = $job;
            adminLogActivity('started', 'media', null, 'Fix all tour images');
            $stage = 'tours'; $cursor = 0;
        }
        if (!$job || !preg_match('/^[0-9]{8}-[0-9]{6}-[a-f0-9]{12}$/', $job)) throw new RuntimeException('Start a repair job first.');
        session_write_close();
        [$table, $fields] = $stages[$stage];
        $db = db();
        $exists = $db->fetchOne('SHOW TABLES LIKE ?', [$table]);
        $rows = $exists ? $db->fetchAll("SELECT * FROM `$table` WHERE id > ? ORDER BY id LIMIT 2", [$cursor]) : [];
        $repair = new TourImageRepair(BASE_PATH);
        $totals = ['checked' => 0, 'updated' => 0, 'converted' => 0, 'issues' => []];
        foreach ($rows as $row) {
            $result = $repair->repairRow($db, $table, $row, $fields, BASE_PATH . 'cache/tour-image-repair-' . $job . '.jsonl');
            foreach (['checked', 'updated', 'converted'] as $key) $totals[$key] += $result[$key];
            $totals['issues'] = array_merge($totals['issues'], $result['issues']);
            $cursor = (int)$row['id'];
        }
        $done = false;
        if (count($rows) < 2) {
            $keys = array_keys($stages); $next = array_search($stage, $keys, true) + 1;
            if ($next >= count($keys)) $done = true;
            else { $stage = $keys[$next]; $cursor = 0; }
        }
        echo json_encode(['ok' => true, 'stage' => $stage, 'cursor' => $cursor, 'done' => $done, 'result' => $totals, 'backup' => 'tour-image-repair-' . $job . '.jsonl'], JSON_INVALID_UTF8_SUBSTITUTE);
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
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Fix Tour Images</title>
<link rel="stylesheet" href="../templates/assets/vendor/bootstrap/css/bootstrap.min.css"></head>
<body class="bg-light"><main class="container py-5" style="max-width:900px">
<a href="tours">&larr; Back to Tours</a>
<div class="card mt-3"><div class="card-body">
<h1 class="h3">Fix All Tour Images</h1>
<p>Repair featured, hero, overview, itinerary and gallery image paths. Convert supported local photos to WebP, up to 1920 pixels. Original files are kept and previous database paths are backed up.</p>
<p>Missing files, ambiguous filenames, remote images and unsupported formats are listed for review. Remote images are kept without downloading them. Animated GIFs remain unchanged.</p>
<button class="btn btn-success" id="start">Fix All Tour Images</button>
<button class="btn btn-outline-secondary" id="stop" disabled>Pause</button>
<a href="tours" class="btn btn-outline-primary" id="refresh" hidden>View updated tours</a>
<p class="mt-3" id="status" role="status" aria-live="polite">Ready. Keep this page open during repair.</p>
<p id="totals"></p><p id="backup" class="small text-muted"></p>
<details><summary id="issueCount">Review notes (0)</summary><pre id="issues" style="white-space:pre-wrap;max-height:400px;overflow:auto"></pre></details>
</div></div></main>
<script>
const token = <?php echo json_encode($_SESSION['csrf_token']); ?>;
let running = false, started = false, stage = 'tours', cursor = 0;
let totals = {checked:0, updated:0, converted:0}, issueCount = 0;
const el = id => document.getElementById(id);
async function run() {
    if (!started) {
        totals = {checked:0, updated:0, converted:0}; issueCount = 0;
        el('issues').textContent = ''; el('totals').textContent = ''; el('backup').textContent = '';
        el('refresh').hidden = true;
    }
    running = true; el('start').disabled = true; el('stop').disabled = false;
    try {
        while (running) {
            el('status').textContent = 'Processing ' + stage + ' after record #' + cursor + '...';
            const data = new URLSearchParams({csrf_token:token, action:started?'batch':'start', stage, cursor:String(cursor)});
            const response = await fetch('fix-tour-images.php', {method:'POST', body:data, credentials:'same-origin'});
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.error || 'Image repair request failed.');
            started = true; stage = result.stage; cursor = result.cursor;
            for (const key of Object.keys(totals)) totals[key] += result.result[key];
            issueCount += result.result.issues.length;
            el('totals').textContent = totals.checked + ' references checked; ' + totals.updated + ' fields updated; ' + totals.converted + ' WebP references prepared.';
            el('issueCount').textContent = 'Review notes (' + issueCount + ')';
            if (result.result.issues.length) el('issues').textContent += result.result.issues.join('\n') + '\n';
            el('backup').textContent = 'Previous path backup on server: cache/' + result.backup;
            if (result.done) {
                running = false; started = false; stage = 'tours'; cursor = 0;
                el('status').textContent = 'Repair complete. Review notes for files that need manual attention.';
                el('refresh').hidden = false; el('start').textContent = 'Run Again';
                break;
            }
        }
        if (started) { el('status').textContent = 'Paused. Resume to continue.'; el('start').textContent = 'Resume Repair'; }
    } catch (error) {
        running = false; el('status').textContent = error.message + ' You can retry; original files are preserved.';
        el('start').textContent = started ? 'Resume Repair' : 'Retry';
    } finally { el('start').disabled = false; el('stop').disabled = true; }
}
el('start').addEventListener('click', run);
el('stop').addEventListener('click', () => { running = false; el('stop').disabled = true; });
</script></body></html>
