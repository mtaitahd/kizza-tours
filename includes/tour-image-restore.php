<?php
require_once __DIR__ . '/tour-image-paths.php';
/** Restore exact original image paths from an administrator-supplied ZIP, without overwriting files. */
class TourImageRestore
{
    private $root;
    public function __construct($root) { $this->root = rtrim(str_replace('\\', '/', realpath($root) ?: $root), '/') . '/'; }
    public function destination($name)
    {
        $name = str_replace('\\', '/', $name);
        if (str_contains($name, "\0") || preg_match('~(?:^|/)\.\.(?:/|$)~', $name) || preg_match('~\.(?:php\d*|phtml|phar|htaccess)(?:[./]|$)~i', $name)) return '';
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'bmp', 'tif', 'tiff'], true)) return '';
        if (preg_match('~(?:^|/)(uploads/(?:tours|gallery|repaired|destinations|pages|recovery)/.+)$~i', $name, $m)) {
            $relative = $m[1];
            $relative = preg_replace_callback('~^uploads/([^/]+)/~i', function ($match) { return 'uploads/' . strtolower($match[1]) . '/'; }, $relative);
        } else {
            // A ZIP of photos only can still be matched by unique original filename.
            $relative = 'uploads/recovery/' . basename($name);
        }
        foreach (explode('/', $relative) as $segment) if ($segment === '' || $segment === '.' || str_contains($segment, ':')) return '';
        return $relative;
    }
    public function batch($archive, $cursor, $limit = 10)
    {
        if (!class_exists('ZipArchive')) throw new RuntimeException('PHP ZIP extension is unavailable. Extract the backup uploads folder through your hosting file manager, then run Fix All.');
        $zip = new ZipArchive();
        if ($zip->open($archive) !== true) throw new RuntimeException('Cannot read the image backup ZIP.');
        try {
            if ($zip->numFiles > 25000) throw new RuntimeException('Archive has too many entries. Use a ZIP containing only the uploads images.');
            $report = ['cursor' => $cursor, 'total' => $zip->numFiles, 'restored' => 0, 'kept' => 0, 'failed' => 0, 'ignored' => 0, 'entries' => [], 'done' => false];
            $began = microtime(true);
            for ($i = $cursor; $i < min($cursor + $limit, $zip->numFiles); $i++) {
                $stat = $zip->statIndex($i);
                $report['cursor'] = $i + 1;
                $name = $stat['name'] ?? '';
                $relative = $this->destination($name);
                if ($relative === '' || str_ends_with($name, '/')) { $report['ignored']++; continue; }
                $entry = ['table' => 'backup', 'id' => $i + 1, 'title' => basename($name), 'field' => 'restore', 'path' => $name, 'new_path' => $relative, 'status' => 'success', 'message' => 'Original image restored.'];
                $temp = null;
                try {
                    $opsys = 0; $attributes = 0;
                    if ($zip->getExternalAttributesIndex($i, $opsys, $attributes) && (($attributes >> 16) & 0170000) === 0120000) throw new RuntimeException('Archive symlink rejected.');
                    if (($stat['size'] ?? 0) < 1 || $stat['size'] > 33554432) throw new RuntimeException('Image is empty or larger than the 32 MB restore limit.');
                    $absolute = $this->root . $relative;
                    // Reject any existing symlink component, including one that currently points inside the site.
                    $walk = $this->root;
                    foreach (explode('/', $relative) as $segment) { $walk .= $segment; if (is_link($walk)) throw new RuntimeException('Symlink destination rejected.'); $walk .= '/'; }
                    if (file_exists($absolute)) { $report['kept']++; $entry['message'] = 'Existing file kept; no overwrite performed.'; }
                    else {
                        $directory = dirname($absolute);
                        if (!is_dir($directory) && !mkdir($directory, 0755, true)) throw new RuntimeException('Cannot create image folder. Check permissions.');
                        $resolvedDirectory = str_replace('\\', '/', realpath($directory) ?: '');
                        if (!str_starts_with($resolvedDirectory . '/', $this->root . 'uploads/')) throw new RuntimeException('Destination is outside uploads.');
                        $temp = tempnam($directory, 'restore-');
                        $input = $zip->getStream($name);
                        if (!$temp || !$input) throw new RuntimeException('Cannot read image from backup.');
                        $output = fopen($temp, 'wb');
                        if (!$output) { fclose($input); throw new RuntimeException('Cannot write restored image.'); }
                        try { $written = stream_copy_to_stream($input, $output, 33554433); }
                        finally { fclose($input); fclose($output); }
                        if ($written !== (int)$stat['size'] || !@getimagesize($temp)) throw new RuntimeException('Backup entry is not a readable image.');
                        // Exclusive creation prevents overwriting a file created during this batch.
                        $output = @fopen($absolute, 'x+b');
                        if (!$output) throw new RuntimeException('Destination appeared during restore; rescan before retrying.');
                        $input = fopen($temp, 'rb');
                        try { $copied = stream_copy_to_stream($input, $output); }
                        finally { fclose($input); fclose($output); }
                        if ($copied !== $written) { @unlink($absolute); throw new RuntimeException('Incomplete image write; retry the restore.'); }
                        chmod($absolute, 0644);
                        $report['restored']++;
                    }
                } catch (Throwable $e) {
                    $report['failed']++; $entry['status'] = 'failed'; $entry['message'] = $e->getMessage();
                } finally { if ($temp && is_file($temp)) unlink($temp); }
                $report['entries'][] = $entry;
                if (microtime(true) - $began > 5) break;
            }
            $report['done'] = $report['cursor'] >= $zip->numFiles;
            if ($report['restored'] > 0) TourImagePaths::invalidate($this->root);
            return $report;
        } finally { $zip->close(); }
    }
}
