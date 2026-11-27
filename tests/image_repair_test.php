<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/tour-image-repair.php';
require_once __DIR__ . '/../includes/tour-image-audit.php';
if (!extension_loaded('gd')) { fwrite(STDERR, "GD required for this test.\n"); exit(1); }
$root = sys_get_temp_dir() . '/kizza_image_repair_' . bin2hex(random_bytes(6));
mkdir($root . '/uploads/gallery', 0755, true);
mkdir($root . '/uploads/other', 0755, true);
$checks = 0;
function checkImageRepair($condition, $message) { global $checks; $checks++; if (!$condition) throw new RuntimeException($message); echo "ok - $message\n"; }
try {
    $image = imagecreatetruecolor(2200, 600);
    imagefill($image, 0, 0, imagecolorallocate($image, 25, 155, 70));
    imagejpeg($image, $root . '/uploads/gallery/Photo.JPG', 90);
    imagewebp($image, $root . '/uploads/gallery/converted.webp', 80);
    imagejpeg($image, $root . '/uploads/gallery/duplicate.jpg');
    imagejpeg($image, $root . '/uploads/other/duplicate.jpg');
    imagedestroy($image);
    file_put_contents($root . '/uploads/gallery/broken.jpg', 'not an image');
    $paths = new TourImagePaths($root);
    checkImageRepair($paths->find('uploads/gallery/converted.jpg') === 'uploads/gallery/converted.webp', 'Find WebP when original JPEG was removed');
    checkImageRepair($paths->find('https://old.example/kizza-tours/uploads/gallery/converted.jpg?v=1') === 'uploads/gallery/converted.webp', 'Recover old absolute URL');
    checkImageRepair(strcasecmp($paths->find('uploads/gallery/photo.jpg'), 'uploads/gallery/Photo.JPG') === 0, 'Recover filename case');
    checkImageRepair($paths->find('Photo.JPG') === 'uploads/gallery/Photo.JPG', 'Recover unique basename');
    checkImageRepair($paths->find('uploads/missing/duplicate.jpg') === '', 'Do not choose ambiguous filenames');
    checkImageRepair($paths->find('uploads/../../outside.jpg') === '', 'Reject directory traversal');
    $hash = hash_file('sha256', $root . '/uploads/gallery/Photo.JPG');
    $repair = new TourImageRepair($root);
    $result = $repair->repair('uploads/gallery/Photo.JPG');
    checkImageRepair($result['status'] === 'converted', 'JPEG converts to WebP');
    $info = getimagesize($root . '/' . $result['path']);
    checkImageRepair($info['mime'] === 'image/webp' && $info[0] === 1920, 'Output is valid resized WebP');
    checkImageRepair(hash_file('sha256', $root . '/uploads/gallery/Photo.JPG') === $hash, 'Original preserved byte for byte');
    $converted = convertToWebp($root . '/uploads/gallery/Photo.JPG');
    checkImageRepair(is_file($converted) && is_file($root . '/uploads/gallery/Photo.JPG'), 'Upload converter preserves original references');
    checkImageRepair(convertToWebp($root . '/uploads/gallery/Photo.JPG') === $converted, 'Upload converter reuses existing valid WebP');
    checkImageRepair($repair->repair($result['path'])['status'] === 'unchanged', 'Repair is repeatable');
    checkImageRepair($repair->repair('uploads/gallery/missing.jpg')['status'] === 'missing', 'Missing image reported');
    checkImageRepair($repair->repair('uploads/gallery/broken.jpg')['status'] === 'unsupported', 'Corrupt image reported');
    checkImageRepair($repair->repair('https://remote.example/photo.jpg')['status'] === 'remote', 'Remote reference preserved');
    $png = imagecreatetruecolor(32, 32); imagealphablending($png, false); imagesavealpha($png, true);
    imagefill($png, 0, 0, imagecolorallocatealpha($png, 0, 0, 0, 127));
    imagepng($png, $root . '/uploads/gallery/transparent.png'); imagedestroy($png);
    $result = $repair->repair('uploads/gallery/transparent.png');
    $webp = imagecreatefromwebp($root . '/' . $result['path']);
    checkImageRepair(((imagecolorat($webp, 0, 0) >> 24) & 127) === 127, 'PNG transparency preserved'); imagedestroy($webp);
    $audit = new TourImageAudit($root);
    checkImageRepair($audit->inspect('uploads/gallery/converted.jpg', 'image')['status'] === 'recoverable', 'Audit identifies repairable broken reference');
    checkImageRepair($audit->inspect('uploads/gallery/missing.jpg', 'image')['status'] === 'failed', 'Audit counts missing file as failed');
    checkImageRepair($audit->inspect('uploads/gallery/converted.webp', 'image')['status'] === 'healthy', 'Audit recognizes valid WebP');
    checkImageRepair($audit->inspect('uploads/gallery/Photo.JPG', 'image')['status'] === 'optimize', 'Existing JPEG needs optimization, not missing');
    checkImageRepair($audit->inspect('https://remote.example/photo.jpg', 'image')['status'] === 'review', 'Remote images require review rather than reported success');
    $tourAudit = $audit->tour(['id' => 1, 'title' => 'Sample', 'image' => '', 'hero_image' => '', 'gallery' => 'uploads/gallery/converted.webp'], [['id' => 3, 'day_number' => 1, 'image_path' => 'uploads/gallery/missing.jpg']]);
    checkImageRepair($tourAudit['counts']['failed'] === 2 && $tourAudit['counts']['healthy'] === 1, 'Tour audit includes missing featured image and itinerary, ignores optional blanks');
    $metadata = new class {
        public $sql; public $params;
        public function fetchOne($sql, $params) { $this->sql = $sql; $this->params = $params; return ['found' => 1]; }
    };
    checkImageRepair(tourImageTableExists($metadata, 'itinerary_days') && str_contains($metadata->sql, 'information_schema.tables') && $metadata->params === ['itinerary_days'], 'Metadata lookup binds table name in MariaDB-compatible SELECT');
    $failingDb = new class { public function query($sql, $params) { throw new RuntimeException('Simulated database write failure'); } };
    $failureReport = $repair->repairRow($failingDb, 'tour_packages', ['id' => 7, 'image' => 'uploads/gallery/converted.jpg', 'hero_image' => 'uploads/gallery/converted.webp'], ['image', 'hero_image'], $root . '/failure-backup.jsonl');
    checkImageRepair($failureReport['failed'] === 1 && $failureReport['success'] === 1, 'Database failure does not prevent checking remaining images');
    checkImageRepair(str_contains($failureReport['entries'][0]['message'], 'Simulated database write failure'), 'Failure report includes exact update error');
    checkImageRepair($failureReport['updated'] === 0 && $failureReport['entries'][1]['status'] === 'success', 'Success and failed outcomes are distinct');
    if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        $db = new class {
            public $pdo;
            public function __construct() { $this->pdo = new PDO('sqlite::memory:'); $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); }
            public function query($sql, $params = []) { $stmt = $this->pdo->prepare($sql); $stmt->execute($params); return $stmt; }
        };
        $db->query('CREATE TABLE tour_packages (id INTEGER PRIMARY KEY, image TEXT, gallery TEXT)');
        $oldGallery = '["uploads/gallery/converted.jpg","uploads/gallery/missing.jpg"]';
        $db->query('INSERT INTO tour_packages VALUES (1, ?, ?)', ['uploads/gallery/converted.jpg', $oldGallery]);
        $row = $db->query('SELECT * FROM tour_packages')->fetch(PDO::FETCH_ASSOC);
        $report = $repair->repairRow($db, 'tour_packages', $row, ['image', 'gallery'], $root . '/backup.jsonl');
        $row = $db->query('SELECT * FROM tour_packages')->fetch(PDO::FETCH_ASSOC);
        checkImageRepair($report['updated'] === 2 && $row['image'] === 'uploads/gallery/converted.webp', 'Update database paths');
        checkImageRepair(json_decode($row['gallery'], true) === ['uploads/gallery/converted.webp', 'uploads/gallery/missing.jpg'], 'Preserve missing gallery entries and JSON format');
        $backup = array_map('json_decode', file($root . '/backup.jsonl'));
        checkImageRepair(count($backup) === 2 && $backup[1]->old === $oldGallery, 'Back up previous database paths');
        $report = $repair->repairRow($db, 'tour_packages', $row, ['image', 'gallery'], $root . '/backup.jsonl');
        checkImageRepair($report['updated'] === 0, 'Second database repair makes no unnecessary changes');
    } else { echo "SQLite not available; database integration checks skipped.\n"; }
    echo "$checks image repair checks passed.\n";
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) { if ($file->isDir()) rmdir($file->getPathname()); else unlink($file->getPathname()); }
    rmdir($root);
}
