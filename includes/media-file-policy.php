<?php
/** Automatic cleanup must never remove reusable media. Media removal is separate from record removal. */
function automaticDeletionPath($filePath, $root)
{
    $root = rtrim(str_replace('\\', '/', realpath($root) ?: $root), '/') . '/';
    $rel = trim(str_replace('\\', '/', (string)$filePath), '/');
    if (!str_starts_with($rel, 'uploads/') || preg_match('~(?:^|/)\.\.(?:/|$)~', $rel)) return '';
    $extension = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
    if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'bmp', 'svg', 'tif', 'tiff', 'heic', 'heif', 'mp4', 'webm', 'ogg'], true)) return '';
    foreach (['uploads/tours/', 'uploads/gallery/', 'uploads/repaired/', 'uploads/recovery/'] as $folder) {
        if (str_starts_with(strtolower($rel), $folder)) return '';
    }
    $absolute = realpath($root . $rel);
    if (!$absolute || !is_file($absolute) || !str_starts_with(str_replace('\\', '/', $absolute), $root . 'uploads/')) return '';
    return $absolute;
}
