<?php
require_once __DIR__ . '/../includes/tour-image-paths.php';
$root = sys_get_temp_dir() . '/kizza_lookup_perf_' . bin2hex(random_bytes(6));
mkdir($root . '/uploads/gallery', 0755, true);
$checks = 0;
function lookupCheck($condition, $message) { global $checks; if (!$condition) throw new RuntimeException($message); $checks++; echo "ok - $message\n"; }
try {
    for ($i=0; $i<600; $i++) file_put_contents($root . '/uploads/gallery/photo-' . $i . '.webp', 'fixture');
    $visitor = new TourImagePaths($root, false);
    lookupCheck($visitor->find('uploads/tours/photo-1.webp') === '', 'Cold visitor lookup does not scan folders to recover another-directory image');
    lookupCheck(!file_exists($root . '/cache/tour-image-index.json'), 'Visitor cache miss does not build an image index');
    lookupCheck($visitor->find('uploads/gallery/photo-1.webp') === 'uploads/gallery/photo-1.webp', 'Existing image works with no cache');
    $began = hrtime(true);
    for ($i=0; $i<100; $i++) (new TourImagePaths($root, false))->find('uploads/tours/missing-' . $i . '.webp');
    $coldMs = (hrtime(true)-$began)/1000000;
    lookupCheck(!file_exists($root . '/cache/tour-image-index.json'), 'Repeated uncached visitor requests never start a recursive scan');
    lookupCheck((new TourImagePaths($root))->find('uploads/tours/photo-1.webp') === 'uploads/gallery/photo-1.webp', 'Admin recovery still finds moved images');
    lookupCheck(is_file($root . '/cache/tour-image-index.json'), 'Admin recovery publishes persistent image lookup index');
    lookupCheck((new TourImagePaths($root, false))->find('uploads/tours/photo-1.webp') === 'uploads/gallery/photo-1.webp', 'Visitor uses published index to recover image without scanning');
    unlink($root . '/uploads/gallery/photo-1.webp');
    lookupCheck((new TourImagePaths($root, false))->find('uploads/tours/photo-1.webp') === '', 'Do not return deleted paths from stale index');
    file_put_contents($root . '/cache/tour-image-index.json', 'invalid-json');
    lookupCheck((new TourImagePaths($root, false))->find('uploads/tours/photo-2.webp') === '', 'Corrupt cache never triggers a visitor folder scan');
    lookupCheck(TourImagePaths::publish($root, ['uploads/gallery/photo-2.webp']), 'Server repair can publish its existing file manifest');
    lookupCheck((new TourImagePaths($root, false))->find('uploads/tours/photo-2.webp') === 'uploads/gallery/photo-2.webp', 'Published server manifest supports fast visitor recovery');
    TourImagePaths::invalidate($root);
    lookupCheck(!is_file($root . '/cache/tour-image-index.json'), 'Backup recovery can invalidate stale image index');
    printf("%d lookup checks passed; 100 cold visitor lookups with 600 uploaded files: %.2f ms locally.\n", $checks, $coldMs);
} finally {
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) { if ($item->isDir()) rmdir($item->getPathname()); else unlink($item->getPathname()); } rmdir($root);
}
