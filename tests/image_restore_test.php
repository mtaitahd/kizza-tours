<?php
require_once __DIR__ . '/../includes/tour-image-restore.php';
require_once __DIR__ . '/../includes/tour-image-paths.php';
$root = sys_get_temp_dir() . '/kizza_restore_' . bin2hex(random_bytes(6));
mkdir($root . '/uploads/tours', 0755, true);
$checks = 0;
function restoreCheck($condition, $message) { global $checks; if (!$condition) throw new RuntimeException($message); $checks++; echo "ok - $message\n"; }
try {
    if (!class_exists('ZipArchive') || !extension_loaded('gd')) throw new RuntimeException('ZIP and GD are required for restore tests.');
    $image = imagecreatetruecolor(30, 20); imagefill($image, 0, 0, imagecolorallocate($image, 35, 120, 50));
    ob_start(); imagewebp($image, null, 80); $photo = ob_get_clean(); imagedestroy($image);
    file_put_contents($root . '/uploads/tours/existing.webp', 'preserved-existing-file');
    $archive = $root . '/backup.zip'; $zip = new ZipArchive(); $zip->open($archive, ZipArchive::CREATE);
    $zip->addFromString('backup/public_html/uploads/tours/restored.webp', $photo);
    $zip->addFromString('uploads/tours/existing.webp', $photo);
    $zip->addFromString('photos/loose-photo.webp', $photo);
    $zip->addFromString('uploads/tours/broken.webp', 'not an image');
    $zip->addFromString('../uploads/tours/escape.webp', $photo);
    $zip->addFromString('uploads/tours/executable.php.jpg', $photo);
    $zip->addFromString('uploads/tours/link.webp', 'outside.webp');
    $zip->setExternalAttributesName('uploads/tours/link.webp', 3, 0120777 << 16);
    $zip->close();
    $restore = new TourImageRestore($root); $cursor = 0; $totals = ['restored'=>0,'kept'=>0,'failed'=>0,'ignored'=>0];
    do {
        $result = $restore->batch($archive, $cursor, 2);
        restoreCheck($result['cursor'] > $cursor, 'Restore batch advances cursor');
        $cursor = $result['cursor']; foreach (array_keys($totals) as $key) $totals[$key] += $result[$key];
    } while (!$result['done']);
    restoreCheck($totals === ['restored'=>2,'kept'=>1,'failed'=>2,'ignored'=>2], 'Restore reports successful, kept, rejected and ignored entries separately');
    restoreCheck(file_get_contents($root . '/uploads/tours/restored.webp') === $photo, 'Full hosting backup restores original image path');
    restoreCheck(file_get_contents($root . '/uploads/tours/existing.webp') === 'preserved-existing-file', 'Existing files are never overwritten');
    restoreCheck((new TourImagePaths($root))->find('uploads/tours/loose-photo.webp') === 'uploads/recovery/loose-photo.webp', 'Photos-only backup is discoverable for bulk reference repair');
    restoreCheck(!file_exists($root . '/uploads/tours/broken.webp') && !file_exists($root . '/uploads/tours/link.webp'), 'Unreadable files and symlinks are not installed');
    restoreCheck($restore->destination('../uploads/tours/escape.webp') === '' && $restore->destination('uploads/tours/executable.php.jpg') === '', 'Reject traversal and executable filenames');
    $again = $restore->batch($archive, 0, 10);
    restoreCheck($again['restored'] === 0 && $again['kept'] === 3, 'Running restore again preserves existing photos');
    echo "$checks backup restore checks passed.\n";
} finally {
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) { if ($item->isDir()) rmdir($item->getPathname()); else unlink($item->getPathname()); } rmdir($root);
}
