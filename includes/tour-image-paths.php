<?php
/** Resolve legacy image references without substituting unrelated photos. */
class TourImagePaths
{
    private $root;
    private $index;
    private $allowScan;
    private const EXTENSIONS = ['webp', 'jpg', 'jpeg', 'png', 'gif', 'avif', 'bmp', 'tif', 'tiff', 'heic', 'heif', 'svg'];

    public function __construct($root, $allowScan = true) {
        $this->root = rtrim(str_replace('\\', '/', realpath($root) ?: $root), '/') . '/';
        $this->allowScan = $allowScan;
    }
    public static function invalidate($root) {
        $path = rtrim($root, '/\\') . '/cache/tour-image-index.json';
        if (is_file($path)) @unlink($path);
    }
    public static function publish($root, array $files) {
        $paths = new self($root, false);
        $paths->index = ['path'=>[], 'name'=>[], 'stem'=>[]];
        foreach (array_unique($files) as $path) {
            if (!is_string($path) || !$paths->safe($path)) continue;
            $paths->index['path'][strtolower($path)][] = $path;
            $paths->index['name'][strtolower(basename($path))][] = $path;
            $paths->index['stem'][strtolower(pathinfo($path, PATHINFO_FILENAME))][] = $path;
        }
        return $paths->saveIndex();
    }
    private function saveIndex() {
        $directory = $this->root . 'cache';
        if (!is_dir($directory) && !@mkdir($directory, 0755, true)) return false;
        $temp = @tempnam($directory, 'image-index-');
        if (!$temp) return false;
        $json = json_encode(['root'=>$this->root, 'index'=>$this->index], JSON_INVALID_UTF8_SUBSTITUTE);
        $ok = $json !== false && @file_put_contents($temp, $json) !== false && @rename($temp, $directory . '/tour-image-index.json');
        if (is_file($temp)) @unlink($temp);
        return $ok;
    }
    public function normalize($value)
    {
        $value = trim(html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8'));
        if (preg_match('~^(?:https?:)?//~i', $value)) $value = parse_url($value, PHP_URL_PATH) ?: '';
        $value = str_replace('\\', '/', rawurldecode(explode('?', explode('#', $value, 2)[0], 2)[0]));
        if (preg_match('~(?:^|/)((?:uploads|assets)/.*)$~i', $value, $m)) $value = $m[1];
        $value = preg_replace('~^(?:\./|\.\./)+~', '', $value);
        return ltrim($value, '/');
    }
    private function safe($path)
    {
        if (!in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::EXTENSIONS, true)) return false;
        $real = realpath($this->root . $path);
        return $real && str_starts_with(str_replace('\\', '/', $real), $this->root) && is_file($real);
    }
    private function buildIndex()
    {
        if ($this->index !== null) return;
        $this->index = ['path' => [], 'name' => [], 'stem' => []];
        $cacheFile = $this->root . 'cache/tour-image-index.json';
        if (is_file($cacheFile) && (!$this->allowScan || time() - filemtime($cacheFile) < 300)) {
            $cached = json_decode(@file_get_contents($cacheFile) ?: '', true);
            if (($cached['root'] ?? '') === $this->root && is_array($cached['index']['path'] ?? null) && is_array($cached['index']['name'] ?? null) && is_array($cached['index']['stem'] ?? null)) {
                $this->index = $cached['index'];
                return;
            }
        }
        // Visitor requests never walk the upload tree, including on cache miss.
        if (!$this->allowScan) return;
        foreach (['uploads', 'assets/images'] as $folder) {
            if (!is_dir($this->root . $folder)) continue;
            try { $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root . $folder, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD); }
            catch (UnexpectedValueException $e) { continue; }
            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->isLink() || !in_array(strtolower($file->getExtension()), self::EXTENSIONS, true)) continue;
                $path = substr(str_replace('\\', '/', $file->getPathname()), strlen($this->root));
                if (!$this->safe($path)) continue;
                $this->index['path'][strtolower($path)][] = $path;
                $this->index['name'][strtolower(basename($path))][] = $path;
                $this->index['stem'][strtolower(pathinfo($path, PATHINFO_FILENAME))][] = $path;
            }
        }
        $this->saveIndex();
    }
    private function candidates($paths) {
        return array_values(array_filter((array)$paths, function ($path) { return is_string($path) && $this->safe($path); }));
    }
    public function find($value)
    {
        // Only remap old site URLs that explicitly refer to our upload/assets folders.
        if (preg_match('~^(?:https?:)?//~i', trim((string)$value)) && !preg_match('~/(?:uploads|assets)/~i', parse_url($value, PHP_URL_PATH) ?: '')) return '';
        $path = $this->normalize($value);
        if ($path === '' || preg_match('~(?:^|/)\.\.(?:/|$)~', $path)) return '';
        if ($this->safe($path)) return $path;
        // A previous upload conversion may have removed the JPEG/PNG original.
        $webp = preg_replace('~\.[^/.]+$~', '.webp', $path);
        if ($webp !== $path && $this->safe($webp)) return $webp;
        $small = preg_replace('~\.[^/.]+$~', '-1200w.webp', $path);
        if ($small !== $path && $this->safe($small)) return $small;
        $this->buildIndex();
        $exact = $this->candidates($this->index['path'][strtolower($path)] ?? []);
        if (count($exact) === 1) return $exact[0];
        $stem = strtolower(pathinfo($path, PATHINFO_FILENAME));
        $sameStem = $this->candidates($this->index['stem'][$stem] ?? []);
        $sameDir = array_values(array_filter($sameStem, function ($p) use ($path) { return strcasecmp(dirname($p), dirname($path)) === 0; }));
        if (count($sameDir) === 1) return $sameDir[0];
        $names = $this->candidates($this->index['name'][strtolower(basename($path))] ?? []);
        if (count($names) === 1) return $names[0];
        return count($sameStem) === 1 ? $sameStem[0] : '';
    }
}
