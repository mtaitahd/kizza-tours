<?php
require_once __DIR__ . '/../includes/media-file-policy.php';
require_once __DIR__ . '/../includes/itinerary-days-save.php';
$root = sys_get_temp_dir() . '/kizza_retention_' . bin2hex(random_bytes(6));
mkdir($root . '/uploads/tours', 0755, true); mkdir($root . '/uploads/quotes', 0755, true);
$deletions = []; $checks = 0;
function retentionCheck($condition, $message) { global $checks; if (!$condition) throw new RuntimeException($message); $checks++; echo "ok - $message\n"; }
function db() { global $testDb; return $testDb; }
function deleteFile($path) { global $deletions, $root; $deletions[] = $path; if (is_file($root . '/' . $path)) unlink($root . '/' . $path); }
try {
    file_put_contents($root . '/uploads/tours/shared.webp', 'shared-photo');
    file_put_contents($root . '/uploads/quotes/quote.pdf', 'test-pdf');
    retentionCheck(automaticDeletionPath('uploads/tours/shared.webp', $root) === '', 'Shared tour image cannot be automatically deleted');
    retentionCheck(automaticDeletionPath('uploads/quotes/quote.pdf', $root) !== '', 'Non-image quote cleanup remains available');
    retentionCheck(automaticDeletionPath('uploads/../../includes/config.php', $root) === '', 'Cleanup cannot escape uploads');
    retentionCheck(automaticDeletionPath('includes/config.php', $root) === '', 'Cleanup cannot delete application code');
    if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) throw new RuntimeException('SQLite is required for retention regression tests.');
    $testDb = new class {
        public $pdo; public $fail = false;
        public function __construct() { $this->pdo = new PDO('sqlite::memory:'); $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); }
        public function getConnection() { return $this->pdo; }
        public function beginTransaction() { $this->pdo->beginTransaction(); }
        public function commit() { $this->pdo->commit(); }
        public function rollback() { $this->pdo->rollBack(); }
        public function query($sql, $params = []) { if ($this->fail && str_starts_with($sql, 'UPDATE')) throw new RuntimeException('Simulated tour save failure'); $stmt = $this->pdo->prepare($sql); $stmt->execute($params); return $stmt; }
        public function fetchAll($sql, $params = []) { return $this->query($sql, $params)->fetchAll(PDO::FETCH_ASSOC); }
        public function insert($sql, $params = []) { $this->query($sql, $params); return $this->pdo->lastInsertId(); }
    };
    $testDb->query('CREATE TABLE itinerary_days (id INTEGER PRIMARY KEY, tour_id INTEGER, day_number INTEGER, title TEXT, description TEXT, drive_time TEXT, meals TEXT, accommodation TEXT, location_name TEXT, lat REAL, lng REAL, image_path TEXT, image_alt TEXT, sort_order INTEGER)');
    $testDb->query('INSERT INTO itinerary_days (id, tour_id, image_path) VALUES (1, 1, ?), (2, 2, ?)', ['uploads/tours/shared.webp', 'uploads/tours/shared.webp']);
    saveItineraryDays(1, [['day_id'=>1, 'title'=>'New day', 'final_image'=>'uploads/tours/replacement.webp']], []);
    retentionCheck(is_file($root . '/uploads/tours/shared.webp') && $deletions === [], 'Editing one tour leaves another tour shared photo intact');
    saveItineraryDays(1, [], []);
    retentionCheck(count($testDb->fetchAll('SELECT * FROM itinerary_days WHERE tour_id = 1')) === 0 && count($testDb->fetchAll('SELECT * FROM itinerary_days WHERE tour_id = 2')) === 1 && $deletions === [], 'Deleting itinerary day removes record without deleting shared file');
    $testDb->fail = true;
    try { saveItineraryDays(2, [['day_id'=>2, 'title'=>'Fails', 'final_image'=>'uploads/tours/shared.webp']], ['uploads/tours/shared.webp']); } catch (RuntimeException $e) { retentionCheck($e->getMessage() === 'Simulated tour save failure', 'Simulated rollback reached'); }
    retentionCheck(is_file($root . '/uploads/tours/shared.webp') && $deletions === [], 'Failed save does not delete a gallery-picked shared photo');
    echo "$checks media retention checks passed.\n";
} finally {
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) { if ($item->isDir()) rmdir($item->getPathname()); else unlink($item->getPathname()); } rmdir($root);
}
