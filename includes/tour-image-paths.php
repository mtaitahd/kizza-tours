<?php
/** Resolve legacy image references without substituting unrelated photos. */
class TourImagePaths
{
    private $root;
    private $index;
    private const EXTENSIONS = ['webp', 'jpg', 'jpeg', 'png', 'gif', 'avif', 'bmp', 'tif', 'tiff', 'heic', 'heif', 'svg'];

    public function __construct($root) { $this->root = rtrim(str_replace('\\', '/', realpath($root) ?: $root), '/') . '/'; }
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
        foreach (['uploads', 'assets/images'] as $folder) {
            if (!is_dir($this->root . $folder)) continue;
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root . $folder, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->isLink() || !in_array(strtolower($file->getExtension()), self::EXTENSIONS, true)) continue;
                $path = substr(str_replace('\\', '/', $file->getPathname()), strlen($this->root));
                if (!$this->safe($path)) continue;
                $this->index['path'][strtolower($path)][] = $path;
                $this->index['name'][strtolower(basename($path))][] = $path;
                $this->index['stem'][strtolower(pathinfo($path, PATHINFO_FILENAME))][] = $path;
            }
        }
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
        $exact = $this->index['path'][strtolower($path)] ?? [];
        if (count($exact) === 1) return $exact[0];
        $stem = strtolower(pathinfo($path, PATHINFO_FILENAME));
        $sameStem = $this->index['stem'][$stem] ?? [];
        $sameDir = array_values(array_filter($sameStem, function ($p) use ($path) { return strcasecmp(dirname($p), dirname($path)) === 0; }));
        if (count($sameDir) === 1) return $sameDir[0];
        $names = $this->index['name'][strtolower(basename($path))] ?? [];
        if (count($names) === 1) return $names[0];
        return count($sameStem) === 1 ? $sameStem[0] : '';
    }
}
