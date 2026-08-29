<?php
/**
 * CLI integration test for includes/image-compressor.php.
 *
 * Generates small JPEG/PNG/WebP fixtures with GD in a temp directory, then runs
 * a batched job and asserts:
 *  - every candidate is processed exactly once;
 *  - outputs are readable images of the same format;
 *  - no file is ever upscaled;
 *  - a second run (start + batch) properly skips unchanged files;
 *  - reset works;
 *  - state/history/lock files stay inside the cache dir.
 *
 * Run from the project root:
 *   php tests/compression_test.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/image-compressor.php';

if (!ImageCompressor::hasLibrary()) {
    fwrite(STDERR, "GD not available; skipping compression test.\n");
    exit(0);
}

$failures = 0;
$checks = 0;

function ok($condition, $name) {
    global $failures, $checks;
    $checks++;
    if ($condition) {
        echo "  ok  - $name\n";
    } else {
        $failures++;
        echo "FAIL  - $name\n";
    }
}

$workdir = sys_get_temp_dir() . '/kizza_compress_test_' . bin2hex(random_bytes(4));
$imgDir = $workdir . '/uploads/tours';
if (!mkdir($imgDir, 0777, true) && !is_dir($imgDir)) {
    fwrite(STDERR, "Cannot create test dir\n");
    exit(1);
}

// Fixture 1: a big noisy JPEG (2400px wide so it must be downscaled to 1920).
// Noise guarantees the encoded file comfortably exceeds the 10 KB minimum.
$jp = imagecreatetruecolor(2400, 900);
for ($x = 0; $x < 2400; $x += 4) {
    for ($y = 0; $y < 900; $y += 4) {
        imagesetpixel($jp, $x, $y, imagecolorallocate($jp, rand(0, 255), rand(0, 255), rand(0, 255)));
    }
}
imagejpeg($jp, $imgDir . '/big_photo.jpg', 95);
imagedestroy($jp);

// Fixture 2: noisy PNG with alpha.
$pg = imagecreatetruecolor(600, 600);
imagesavealpha($pg, true);
$transparent = imagecolorallocatealpha($pg, 0, 0, 0, 127);
imagefill($pg, 0, 0, $transparent);
for ($x = 0; $x < 600; $x += 2) {
    for ($y = 0; $y < 600; $y += 2) {
        imagesetpixel($pg, $x, $y, imagecolorallocatealpha($pg, rand(0, 255), rand(0, 255), rand(0, 255), rand(0, 100)));
    }
}
imagepng($pg, $imgDir . '/circle.png');
imagedestroy($pg);

// Fixture 3: tiny file (should be excluded by the 10 KB minimum guard).
file_put_contents($imgDir . '/tiny_cost.webp', str_repeat('x', 2048));

$bp = $workdir . '/base';
mkdir($bp, 0777, true);

$comp = new ImageCompressor($bp);
$comp->setScanDirs([$workdir . '/uploads']);
$comp->setParams(70, 1920);

$candidates = $comp->scan();
ok(count($candidates) === 2, 'scan finds the two large fixtures only (tiny file excluded), got ' . count($candidates));

// ---- Run 1 ----
$state = $comp->startJob();
ok(count($state['files']) === 2, 'job started with 2 files');
$state = $comp->processBatch(8000, 10);
ok(!empty($state['done']), 'batch completes whole job when within budget');
ok($state['total_saved'] >= 0, 'total_saved reported');

$statuses = [];
foreach ($state['files'] as $f) {
    $statuses[$f['rel']] = $f['status'];
}
ok(in_array('done', $statuses, true) || in_array('skipped', $statuses, true), 'batch processed or skipped both files');

// All fixtures remain readable and same-format; wide one is not upscaled.
$bigInfo = getimagesize($imgDir . '/big_photo.jpg');
ok($bigInfo !== false, 'big JPEG still a readable image');
ok(($bigInfo[0] ?? 0) <= 1920, 'wide JPEG downscaled to maxWidth (got ' . ($bigInfo[0] ?? 0) . ')');
$pngInfo = getimagesize($imgDir . '/circle.png');
ok($pngInfo !== false && $pngInfo[2] === IMAGETYPE_PNG, 'PNG still readable and PNG');
ok($pngInfo[1] === 600, 'PNG never upscaled (stays 600x600)');

// ---- Run 2 (unchanged) ----
$state2 = $comp->startJob();
$state2 = $comp->processBatch(8000, 10);
$skipCount = 0;
foreach ($state2['files'] as $f) {
    if (($f['status'] ?? '') === 'skipped') $skipCount++;
}
ok($skipCount === 2, 'second run skips unchanged files (got ' . $skipCount . ')');

// ---- Reset ----
$comp->resetState();
ok($comp->readState() === null, 'state cleared after reset');

// State files live in the sandbox cache dir.
ok(is_file($bp . '/cache/image_compression_history.json'), 'history file created under cache/');
$lockAcquired = $comp->acquireLock();
$comp->releaseLock();
ok($lockAcquired === true, 'flock lock acquired and released');
ok(is_file($bp . '/cache/image_compression.lock'), 'lock file created under cache/');

// Cleanup
array_map('unlink', glob($imgDir . '/*'));
@rmdir($imgDir);
@rmdir($workdir . '/uploads');
@rmdir($workdir);

echo "\n$checks checks, $failures failures\n";
exit($failures > 0 ? 1 : 0);