<?php
/**
 * Image Compression Tool
 * Run this file via browser or CLI to compress all large images in uploads/
 * Quality: 70%, Max Width: 1920px
 */

// Security: admin only
session_start();
if (php_sapi_name() !== 'cli') {
    if (empty($_SESSION['admin_id'])) {
        die('Access denied. Admin login required.');
    }
}

$scanDirs = [
    __DIR__ . '/../uploads',
    __DIR__ . '/../assets/images',
    __DIR__ . '/../templates/assets/img',
];
$quality = 70;
$maxWidth = 1920;

$extensions = ['jpg', 'jpeg', 'png', 'webp'];
$processed = 0;
$saved = 0;

// Check if Imagick or GD is available
if (!extension_loaded('imagick') && !extension_loaded('gd')) {
    die("ERROR: No image library found. Install Imagick or GD extension.\n");
}

$useImagick = extension_loaded('imagick');

function compressImage($path, $quality, $maxWidth, $useImagick) {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $origSize = filesize($path);

    if ($origSize < 10240) return null; // skip files < 10KB

    if ($useImagick) {
        $img = new Imagick($path);

        $w = $img->getImageWidth();
        if ($w > $maxWidth) {
            $img->resizeImage($maxWidth, 0, Imagick::FILTER_LANCZOS, 1);
        }

        if ($ext === 'webp') {
            $img->setImageFormat('webp');
            $img->setImageCompressionQuality($quality);
        } elseif ($ext === 'png') {
            $img->setImageFormat('png');
            $img->setImageCompressionQuality($quality);
        } else {
            $img->setImageFormat('jpg');
            $img->setImageCompressionQuality($quality);
        }

        $blob = $img->getImageBlob();
        $img->destroy();
    } else {
        // GD fallback
        switch ($ext) {
            case 'webp':
                $src = @imagecreatefromwebp($path);
                if (!$src) return null;
                break;
            case 'png':
                $src = @imagecreatefrompng($path);
                if (!$src) return null;
                break;
            case 'jpg':
            case 'jpeg':
                $src = @imagecreatefromjpeg($path);
                if (!$src) return null;
                break;
            default:
                return null;
        }

        $w = imagesx($src);
        $h = imagesy($src);

        if ($w > $maxWidth) {
            $ratio = $maxWidth / $w;
            $nw = $maxWidth;
            $nh = (int)($h * $ratio);
            $dst = imagecreatetruecolor($nw, $nh);
            if ($ext === 'png') {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
            }
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($src);
            $src = $dst;
        }

        ob_start();
        if ($ext === 'webp') {
            imagewebp($src, null, $quality);
        } elseif ($ext === 'png') {
            imagepng($src, null, 9);
        } else {
            imagejpeg($src, null, $quality);
        }
        $blob = ob_get_clean();
        imagedestroy($src);
    }

    $newSize = strlen($blob);
    if ($newSize < $origSize) {
        file_put_contents($path, $blob);
        return ['orig' => $origSize, 'new' => $newSize];
    }
    return ['orig' => $origSize, 'new' => $origSize]; // already optimal
}

function scanDir($dir, &$results = []) {
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            scanDir($path, $results);
        } else {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
        if (filesize($path) > 10240 && basename($path) !== 'log.png') {
            $results[] = $path;
        }
    }
        }
    }
    return $results;
}

$files = [];
foreach ($scanDirs as $dir) {
    if (is_dir($dir)) {
        scanDir($dir, $files);
    }
}
echo "Found " . count($files) . " images to process\n\n";
$totalSaved = 0;

foreach ($files as $file) {
    $result = compressImage($file, $quality, $maxWidth, $useImagick);
    if ($result === null) {
        echo "  SKIP: " . str_replace([__DIR__ . '/../', '\\'], ['', '/'], $file) . " (unsupported)\n";
        continue;
    }
    $savings = (1 - $result['new'] / $result['orig']) * 100;
    $totalSaved += ($result['orig'] - $result['new']);
    $rel = str_replace([__DIR__ . '/../', '\\'], ['', '/'], $file);
    if ($result['new'] < $result['orig']) {
        echo "  OK:   {$rel} - " . round($result['orig']/1024) . "KB -> " . round($result['new']/1024) . "KB (" . round($savings) . "% saved)\n";
    } else {
        echo "  -:    {$rel} - already optimal (" . round($result['orig']/1024) . "KB)\n";
    }
}

echo "\nDone! Total saved: " . round($totalSaved/1024) . " KB\n";
echo "Use Imagick: " . ($useImagick ? "Yes" : "No (GD fallback)") . "\n";
