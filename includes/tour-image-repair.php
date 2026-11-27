<?php
require_once __DIR__ . '/tour-image-paths.php';

class TourImageRepair
{
    private $root;
    private $paths;
    private $results = [];
    public function __construct($root) { $this->root = rtrim($root, '/\\') . '/'; $this->paths = new TourImagePaths($root); }
    public function repair($value)
    {
        $value = trim((string)$value);
        if ($value === '') return ['path' => '', 'status' => 'empty'];
        if (isset($this->results[$value])) return $this->results[$value];
        $path = $this->paths->find($value);
        if ($path === '') return ['path' => $value, 'status' => preg_match('~^(?:https?:)?//~i', $value) ? 'remote' : 'missing'];
        $absolute = $this->root . $path;
        $info = @getimagesize($absolute);
        if (!$info) return ['path' => $path, 'status' => strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'svg' ? 'unchanged' : 'unsupported'];
        if (($info['mime'] ?? '') === 'image/webp') return $this->results[$value] = ['path' => $path, 'status' => $path === $value ? 'unchanged' : 'repaired'];
        if (!function_exists('imagewebp') || !function_exists('imagecreatefromstring')) return ['path' => $path, 'status' => 'unsupported'];
        // Keep animated GIFs intact and avoid exhausting shared-host memory.
        if (($info['mime'] ?? '') === 'image/gif') return ['path' => $path, 'status' => 'preserved'];
        $limit = ini_get('memory_limit');
        $bytes = (int)$limit;
        if (strtolower(substr($limit, -1)) === 'g') $bytes *= 1024 * 1024 * 1024;
        elseif (strtolower(substr($limit, -1)) === 'm') $bytes *= 1024 * 1024;
        elseif (strtolower(substr($limit, -1)) === 'k') $bytes *= 1024;
        if ($info[0] * $info[1] > 24000000 || ($bytes > 0 && memory_get_usage(true) + $info[0] * $info[1] * 8 + filesize($absolute) + 16777216 > $bytes)) return ['path' => $path, 'status' => 'too_large'];
        $hash = substr(hash('sha256', $path . ':' . filemtime($absolute) . ':' . filesize($absolute)), 0, 12);
        $target = 'uploads/repaired/' . pathinfo($path, PATHINFO_FILENAME) . '-' . $hash . '.webp';
        if (is_file($this->root . $target) && @getimagesize($this->root . $target)) return $this->results[$value] = ['path' => $target, 'status' => 'converted'];
        if (!is_dir($this->root . 'uploads/repaired') && !mkdir($this->root . 'uploads/repaired', 0755, true)) throw new RuntimeException('Cannot create uploads/repaired. Check folder permissions.');
        $source = @imagecreatefromstring(file_get_contents($absolute));
        if (!$source) return ['path' => $path, 'status' => 'unsupported'];
        $output = $source;
        $scale = min(1, 1920 / max($info[0], $info[1]));
        if ($scale < 1) {
            $w = max(1, (int)round($info[0] * $scale)); $h = max(1, (int)round($info[1] * $scale));
            $output = imagecreatetruecolor($w, $h);
            imagealphablending($output, false); imagesavealpha($output, true);
            imagecopyresampled($output, $source, 0, 0, 0, 0, $w, $h, $info[0], $info[1]);
        } elseif (!imageistruecolor($output)) { imagepalettetotruecolor($output); }
        imagealphablending($output, false); imagesavealpha($output, true);
        $temp = tempnam($this->root . 'uploads/repaired', 'repair-');
        try {
            if (!$temp || !imagewebp($output, $temp, 80) || !@getimagesize($temp) || !rename($temp, $this->root . $target)) throw new RuntimeException('WebP conversion failed. Original preserved.');
            chmod($this->root . $target, 0644);
        } finally {
            if ($output !== $source) imagedestroy($output);
            imagedestroy($source);
            if ($temp && is_file($temp)) unlink($temp);
        }
        return $this->results[$value] = ['path' => $target, 'status' => 'converted'];
    }
    public function repairRow($db, $table, array $row, array $fields, $backup)
    {
        $report = ['checked' => 0, 'updated' => 0, 'converted' => 0, 'issues' => []];
        foreach ($fields as $field) {
            if (!isset($row[$field]) || trim((string)$row[$field]) === '') continue;
            $old = $row[$field];
            $values = $field === 'gallery' ? tourListItems($old) : [$old];
            $new = [];
            foreach ($values as $value) {
                $result = $this->repair($value); $report['checked']++;
                $new[] = $result['path'];
                if ($result['status'] === 'converted') $report['converted']++;
                if (in_array($result['status'], ['missing', 'remote', 'unsupported', 'preserved', 'too_large'], true)) $report['issues'][] = "$table #{$row['id']} $field: {$result['status']} - $value";
            }
            $replacement = $field === 'gallery' ? (str_starts_with(trim($old), '[') ? json_encode($new, JSON_UNESCAPED_SLASHES) : implode(',', $new)) : $new[0];
            if ($replacement === $old) continue;
            // Record every previous value before updating; originals are never deleted.
            $record = json_encode(['time' => gmdate('c'), 'table' => $table, 'id' => $row['id'], 'field' => $field, 'old' => $old, 'new' => $replacement], JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
            if (file_put_contents($backup, $record, FILE_APPEND | LOCK_EX) === false) throw new RuntimeException('Cannot save image path backup. No database update performed.');
            $statement = $db->query("UPDATE `$table` SET `$field` = ? WHERE id = ? AND `$field` = ?", [$replacement, $row['id'], $old]);
            $report['updated'] += $statement->rowCount();
        }
        return $report;
    }
}
