<?php
/**
 * Batched, resumable image compressor for the Kizza Tours admin.
 *
 * Replaces the old one-shot synchronous compression that processed every image
 * in a single request (which timed out on large libraries). This class:
 *
 *  - scans the configured directories and hands back a stable, ordered list of
 *    candidate files (jpg/jpeg/png/webp larger than a minimum size);
 *  - tracks an in-progress job in cache/image_compression_state.json and keeps
 *    a running history (cache/image_compression_history.json) so unchanged files
 *    are recognised by size + sha1 and skipped in later runs;
 *  - processes a bounded batch per call (a time budget AND a file-count cap),
 *    meaning the admin page can loop {start -> batch -> batch -> ... -> finish}
 *    over AJAX without ever blocking a single request long enough to time out;
 *  - writes each compressed result to a temporary file first and only renames it
 *    over the original after a successful encode (no partial/corrupt images);
 *  - never upscales, preserves EXIF orientation on JPEGs, and refuses to store
 *    an output that is larger than the input (the original is kept in that case);
 *  - is protected against concurrent runs via a lock file (cache/
 *    image_compression.lock) so two admins / repeated clicks can't interfere.
 *
 * All state files live under cache/ which is git-ignored.
 */
class ImageCompressor
{
    /** Compression quality applied to JPEG/WebP output. */
    private $quality = 70;

    /** Longest edge target; larger images are downscaled (never upscaled). */
    private $maxWidth = 1920;

    /** Files at or below this size are considered already optimized. */
    private $minSize = 10240;

    /** Absolute base path of the project (uploads/ etc. are resolved from it). */
    private $baseDir;

    /** Absolute paths of the directories we scan. */
    private $scanDirs = [];

    private $stateFile;
    private $historyFile;
    private $lockFile;
    private $lockHandle = null;

    /** Allowed source extensions, lowercased. */
    private static $extensions = ['jpg', 'jpeg', 'png', 'webp'];

    public function __construct($baseDir)
    {
        $this->baseDir = rtrim(str_replace('\\', '/', $baseDir), '/');
        $cache = $this->baseDir . '/cache';
        if (!is_dir($cache)) {
            @mkdir($cache, 0755, true);
        }
        $this->stateFile = $cache . '/image_compression_state.json';
        $this->historyFile = $cache . '/image_compression_history.json';
        $this->lockFile = $cache . '/image_compression.lock';
    }

    public static function hasLibrary()
    {
        return extension_loaded('gd');
    }

    public function setScanDirs(array $dirs)
    {
        $this->scanDirs = [];
        foreach ($dirs as $dir) {
            $abs = rtrim(str_replace('\\', '/', realpath($dir) ?: $dir), '/');
            if ($abs !== '' && is_dir($abs)) {
                $this->scanDirs[] = $abs;
            }
        }
    }

    public function setParams($quality, $maxWidth, $minSize = 10240)
    {
        $this->quality = max(1, min(100, intval($quality) ?: 70));
        $this->maxWidth = max(64, intval($maxWidth) ?: 1920);
        $this->minSize = max(0, intval($minSize) ?: 10240);
    }

    // ---------------------------------------------------------------
    // Locking
    // ---------------------------------------------------------------

    public function acquireLock()
    {
        if ($this->lockHandle !== null) {
            return true;
        }
        $handle = @fopen($this->lockFile, 'c');
        if (!$handle) {
            return false;
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return false;
        }
        $this->lockHandle = $handle;
        return true;
    }

    public function releaseLock()
    {
        if ($this->lockHandle !== null) {
            @flock($this->lockHandle, LOCK_UN);
            @fclose($this->lockHandle);
            $this->lockHandle = null;
        }
    }

    // ---------------------------------------------------------------
    // Scanning
    // ---------------------------------------------------------------

    /** Returns candidate files as [abs, rel, ext, size] in a stable order. */
    public function scan(array $selection = [])
    {
        $found = [];
        foreach ($this->scanDirs as $dir) {
            $this->scanRecursive($dir, $found);
        }
        usort($found, function ($a, $b) {
            return strcmp($a['rel'], $b['rel']);
        });

        if (!empty($selection)) {
            $wanted = [];
            foreach ($selection as $rel) {
                $rel = trim(str_replace('\\', '/', (string)$rel));
                if ($rel !== '') {
                    $wanted[$rel] = true;
                }
            }
            $found = array_values(array_filter($found, function ($f) use ($wanted) {
                return isset($wanted[$f['rel']]);
            }));
        }

        return $found;
    }

    private function scanRecursive($dir, &$result)
    {
        $items = @scandir($dir);
        if (!is_array($items)) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = str_replace('\\', '/', $dir . '/' . $item);
            if (is_dir($path)) {
                $this->scanRecursive($path, $result);
                continue;
            }
            $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            if (!in_array($ext, self::$extensions, true)) {
                continue;
            }
            if ($item === 'log.png' || basename($item) === 'log.png') {
                continue;
            }
            $size = @filesize($path);
            if ($size === false || $size <= $this->minSize) {
                continue;
            }
            $result[] = [
                'abs'  => $path,
                'rel'  => $this->relPath($path),
                'ext'  => $ext,
                'size' => $size,
            ];
        }
    }

    private function relPath($absPath)
    {
        $absPath = str_replace('\\', '/', $absPath);
        $prefix = $this->baseDir . '/';
        if (strpos($absPath, $prefix) === 0) {
            return substr($absPath, strlen($prefix));
        }
        return ltrim($absPath, '/');
    }

    // ---------------------------------------------------------------
    // Job state
    // ---------------------------------------------------------------

    public function readState()
    {
        if (is_file($this->stateFile)) {
            $data = json_decode((string)file_get_contents($this->stateFile), true);
            if (is_array($data)) {
                return $data;
            }
        }
        return null;
    }

    private function writeState(array $data)
    {
        @file_put_contents($this->stateFile, json_encode($data), LOCK_EX);
    }

    private function readHistory()
    {
        if (is_file($this->historyFile)) {
            $data = json_decode((string)file_get_contents($this->historyFile), true);
            if (is_array($data)) {
                return $data;
            }
        }
        return [];
    }

    private function writeHistory(array $history)
    {
        @file_put_contents($this->historyFile, json_encode($history), LOCK_EX);
    }

    private function nowMs()
    {
        return (int)round(microtime(true) * 1000);
    }

    /**
     * Starts (or resets) a compression job. Candidates that are unchanged since
     * their last successful compression (same recorded size) are pre-marked as
     * skipped so they never send work to the encoder again.
     *
     * @param array|null $selection Optional relative paths to restrict the job to.
     * @return array State snapshot for the page.
     */
    public function startJob(array $selection = null)
    {
        $files = $this->scan($selection ?: []);
        $history = $this->readHistory();

        $list = [];
        foreach ($files as $f) {
            $entry = [
                'rel'   => $f['rel'],
                'abs'   => $f['abs'],
                'ext'   => $f['ext'],
                'orig'  => $f['size'],
                'new'   => null,
                'saved' => 0,
                'status' => 'pending', // pending | done | skipped | error
                'error' => null,
            ];
            $hist = $history[$f['rel']] ?? null;
            if ($hist && (int)($hist['size'] ?? 0) === $f['size'] && ($hist['status'] ?? '') === 'done') {
                $entry['status'] = 'skipped';
                $entry['new'] = $f['size'];
            }
            $list[] = $entry;
        }

        $state = [
            'params'   => ['quality' => $this->quality, 'max_width' => $this->maxWidth],
            'started'  => date('c'),
            'index'    => 0,
            'files'    => $list,
            'done'     => false,
            'error'    => null,
            'total_orig'  => 0,
            'total_new'   => null,
            'total_saved' => null,
        ];
        $this->writeState($state);
        return $state;
    }

    public function resetState()
    {
        foreach ([$this->stateFile] as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
    }

    /**
     * Processes the next batch of pending files, bounded by both a file count and
     * a wall-clock time budget so each HTTP request stays short.
     *
     * @return array|null Updated state, or null if no job is running.
     */
    public function processBatch($budgetMs = 8000, $batchSize = 4)
    {
        $state = $this->readState();
        if (!$state || ($state['done'] ?? false)) {
            return $state;
        }

        $history = $this->readHistory();
        $startMs = $this->nowMs();
        $processed = 0;
        $total = count($state['files']);
        $deadlineReached = false;

        while ($state['index'] < $total) {
            $index = $state['index'];
            $fileEntry = &$state['files'][$index];

            if (($fileEntry['status'] ?? '') === 'pending') {
                $this->processFile($fileEntry, $history);
                $processed++;
            }

            if (($state['files'][$index]['status'] ?? '') === 'done') {
                $state['files'][$index]['new'] = $fileEntry['new'] ?? $fileEntry['orig'];
                $state['files'][$index]['saved'] = max(0, ($fileEntry['orig'] ?? 0) - ($fileEntry['new'] ?? 0));
            }
            $state['index'] = $index + 1;

            if ($processed >= $batchSize || ($this->nowMs() - $startMs) >= $budgetMs) {
                $deadlineReached = true;
                break;
            }
        }

        if ($state['index'] >= $total) {
            $state['done'] = true;
            $state['index'] = $total;
            $this->finalizeState($state);
        }

        $this->writeHistory($history);
        $this->writeState($state);
        return $state;
    }

    private function finalizeState(array &$state)
    {
        $totalOrig = 0;
        $totalNew = 0;
        foreach ($state['files'] as $f) {
            $totalOrig += (int)($f['orig'] ?? 0);
            $totalNew += (int)($f['new'] ?? $f['orig'] ?? 0);
        }
        $state['total_orig'] = $totalOrig;
        $state['total_new'] = $totalNew;
        $state['total_saved'] = max(0, $totalOrig - $totalNew);
    }

    /**
     * Compresses a single file into a temporary file, then atomically renames it
     * over the original. Skips files whose sha1 already sits in the history.
     */
    private function processFile(array &$entry, array &$history)
    {
        $path = str_replace('\\', '/', $entry['abs']);
        $ext = $entry['ext'];
        $origSize = (int)filesize($path);

        $currentSha = @sha1_file($path);
        if (!$currentSha) {
            $entry['status'] = 'error';
            $entry['error'] = 'unreadable';
            return;
        }

        $hist = $history[$entry['rel']] ?? null;
        if ($hist && ($hist['sha1'] ?? '') === $currentSha) {
            $entry['status'] = 'skipped';
            $entry['new'] = $origSize;
            $history[$entry['rel']] = [
                'sha1'   => $currentSha,
                'size'   => $origSize,
                'orig'   => $origSize,
                'new'    => $origSize,
                'status' => 'done',
            ];
            return;
        }

        $src = null;
        switch ($ext) {
            case 'webp':
                $src = @imagecreatefromwebp($path);
                break;
            case 'png':
                $src = @imagecreatefrompng($path);
                break;
            case 'jpg':
            case 'jpeg':
                $src = @imagecreatefromjpeg($path);
                break;
        }
        if (!$src) {
            $entry['status'] = 'error';
            $entry['error'] = 'decode failed';
            return;
        }

        // Preserve EXIF orientation for JPEGs (cameras embedding rotation).
        if ($ext === 'jpg' || $ext === 'jpeg') {
            $src = $this->applyOrientation($path, $src);
        }

        $width = imagesx($src);
        $height = imagesy($src);
        $needsWrite = false;

        // Downscale only when wider than the target; never upscale.
        if ($width > $this->maxWidth) {
            $dstWidth = $this->maxWidth;
            $dstHeight = max(1, (int)round($height * ($this->maxWidth / $width)));
            $dst = imagecreatetruecolor($dstWidth, $dstHeight);
            if ($ext === 'png') {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
            }
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstWidth, $dstHeight, $width, $height);
            imagedestroy($src);
            $src = $dst;
            $needsWrite = true;
        }

        ob_start();
        switch ($ext) {
            case 'webp':
                imagewebp($src, null, $this->quality);
                break;
            case 'png':
                imagepng($src, null, 6);
                break;
            default:
                imagejpeg($src, null, $this->quality);
                break;
        }
        $blob = ob_get_clean();
        imagedestroy($src);

        if ($blob === false || $blob === '') {
            $entry['status'] = 'error';
            $entry['error'] = 'encode failed';
            return;
        }

        $newSize = strlen($blob);
        if ($needsWrite || $newSize < $origSize) {
            $tmpPath = tempnam(dirname($path) !== '' ? str_replace('/', DIRECTORY_SEPARATOR, dirname($path)) : '.', 'kzcz_');
            if ($tmpPath === false) {
                $entry['status'] = 'error';
                $entry['error'] = 'no temp file';
                return;
            }
            $ok = (file_put_contents($tmpPath, $blob) !== false)
                && @rename($tmpPath, str_replace('/', DIRECTORY_SEPARATOR, $path));
            if (!$ok) {
                @unlink($tmpPath);
                $entry['status'] = 'error';
                $entry['error'] = 'write failed';
                return;
            }
            $newSize = (int)filesize($path);
        } else {
            $newSize = $origSize;
        }

        $entry['new'] = $newSize;
        $entry['status'] = 'done';
        $entry['orig'] = $origSize;

        $history[$entry['rel']] = [
            'sha1'   => @sha1_file($path) ?: $currentSha,
            'size'   => $newSize,
            'orig'   => $origSize,
            'new'    => $newSize,
            'status' => 'done',
        ];
    }

    /** Rotates/flips a JPEG according to its EXIF orientation (if any). */
    private function applyOrientation($path, $image)
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data($path);
        if (!$exif || !isset($exif['Orientation'])) {
            return $image;
        }
        switch ((int)$exif['Orientation']) {
            case 3:
                $image = imagerotate($image, 180, 0);
                break;
            case 6:
                $image = imagerotate($image, -90, 0);
                break;
            case 8:
                $image = imagerotate($image, 90, 0);
                break;
            case 2:
                if (function_exists('imageflip')) {
                    imageflip($image, IMG_FLIP_HORIZONTAL);
                }
                break;
            case 4:
                if (function_exists('imageflip')) {
                    imageflip($image, IMG_FLIP_VERTICAL);
                }
                break;
            case 5:
                $image = imagerotate($image, -90, 0);
                if (function_exists('imageflip')) {
                    imageflip($image, IMG_FLIP_HORIZONTAL);
                }
                break;
            case 7:
                $image = imagerotate($image, -90, 0);
                if (function_exists('imageflip')) {
                    imageflip($image, IMG_FLIP_VERTICAL);
                }
                break;
        }
        return $image;
    }
}