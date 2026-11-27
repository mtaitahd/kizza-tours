<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/tour-image-disk-repair.php';
$root = sys_get_temp_dir() . '/kizza_disk_images_' . bin2hex(random_bytes(6));
mkdir($root . '/uploads/tours/nested', 0755, true); mkdir($root . '/uploads/quotes', 0755, true);
$checks = 0;
function diskCheck($condition, $message) { global $checks; if (!$condition) throw new RuntimeException($message); $checks++; echo "ok - $message\n"; }
try {
    $img = imagecreatetruecolor(45, 30); imagefill($img, 0, 0, imagecolorallocate($img, 30, 110, 60));
    imagewebp($img, $root . '/uploads/tours/photo.webp', 80);
    imagejpeg($img, $root . '/uploads/tours/mislabeled.webp', 85);
    imagewebp($img, $root . '/uploads/tours/nested/second.webp', 80); imagedestroy($img);
    file_put_contents($root . '/uploads/tours/broken.webp', 'unreadable image bytes');
    file_put_contents($root . '/uploads/quotes/private.pdf', 'private quote');
    chmod($root . '/uploads/tours/photo.webp', 0600);
    $beforeHash = hash_file('sha256', $root . '/uploads/tours/photo.webp');
    $service = new TourImageDiskRepair($root);
    $start = $service->start($root);
    diskCheck(count($start['files']) === 4, 'Discover existing images without a ZIP upload');
    diskCheck($start['diagnostics']['folders_checked'] === 3, 'Check uploads and nested image folder permissions');
    diskCheck(!in_array('uploads/quotes/private.pdf', $start['files'], true), 'Do not process private quote documents');
    $cursor = 0; $valid = 0; $failed = 0; $entries = [];
    do {
        $batch = $service->batch($start['files'], $cursor, 2);
        $cursor = $batch['cursor']; $valid += $batch['valid']; $failed += $batch['failed']; $entries = array_merge($entries, $batch['entries']);
    } while (!$batch['done']);
    diskCheck($valid === 3 && $failed === 1, 'Distinguish valid formats from damaged existing files');
    diskCheck(hash_file('sha256', $root . '/uploads/tours/photo.webp') === $beforeHash, 'Permission repair preserves original image content');
    $converted = array_values(array_filter($entries, fn($entry) => $entry['path'] === 'uploads/tours/mislabeled.webp'))[0];
    diskCheck($converted['new_path'] !== $converted['path'] && getimagesize($root . '/' . $converted['new_path'])['mime'] === 'image/webp', 'Convert JPEG bytes mislabeled as WebP to a valid WebP copy');
    diskCheck(is_file($root . '/uploads/tours/mislabeled.webp') && is_file($root . '/uploads/tours/broken.webp'), 'Keep original and damaged files for investigation');
    try { $service->publicPermissions($root . '/backup.zip'); diskCheck(false, 'Outside target should be rejected'); }
    catch (RuntimeException $e) { diskCheck(str_contains($e->getMessage(), 'outside'), 'Permission changes are restricted to uploaded media'); }
    if (PHP_OS_FAMILY !== 'Windows') {
        clearstatcache();
        diskCheck((fileperms($root . '/uploads/tours/photo.webp') & 0777) === 0644, 'Repair 0600 file to public-readable 0644');
        diskCheck((fileperms($root . '/uploads/tours/nested') & 0777) === 0755, 'Image folders use 0755');
    } else { echo "POSIX mode assertions skipped on Windows; production POSIX checks remain enabled.\n"; }
    $publicRoot = $root . '/public'; mkdir($publicRoot . '/uploads', 0755, true);
    $diagnostics = $service->start($publicRoot)['diagnostics'];
    diskCheck(isset($diagnostics['warning']), 'Report application/document-root mismatch');
    echo "$checks existing-server-image checks passed.\n";
} finally {
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) { if ($item->isDir()) rmdir($item->getPathname()); else { @chmod($item->getPathname(), 0644); unlink($item->getPathname()); } } rmdir($root);
}
