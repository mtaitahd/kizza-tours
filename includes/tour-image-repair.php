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
        $allowed = ['tour_packages', 'itinerary_days', 'gallery'];
        if (!in_array($table, $allowed, true)) throw new InvalidArgumentException('Invalid image table.');
        $report = ['checked' => 0, 'updated' => 0, 'converted' => 0, 'success' => 0, 'failed' => 0, 'review' => 0, 'issues' => [], 'entries' => []];
        foreach ($fields as $field) {
            if (!preg_match('/^[a-z0-9_]+$/', $field)) throw new InvalidArgumentException('Invalid image field.');
            if (!isset($row[$field]) || trim((string)$row[$field]) === '') {
                if ($table === 'tour_packages' && $field === 'image') {
                    $report['failed']++;
                    $entry = ['table' => $table, 'id' => (int)$row['id'], 'title' => $row['title'] ?? '', 'field' => $field, 'path' => '', 'new_path' => '', 'status' => 'failed', 'message' => 'No featured image selected. Open Edit Tour and choose a photo.'];
                    $report['entries'][] = $entry;
                    $report['issues'][] = "Tour #{$row['id']}: " . $entry['message'];
                }
                continue;
            }
            $old = $row[$field];
            $values = $field === 'gallery' ? tourListItems($old) : [$old];
            $new = []; $entries = []; $converted = 0;
            foreach ($values as $value) {
                $report['checked']++;
                try {
                    $result = $this->repair($value);
                    $reason = $result['status'];
                    $status = in_array($reason, ['missing', 'unsupported', 'too_large'], true) ? 'failed' : ($reason === 'remote' ? 'review' : 'success');
                    $messages = [
                        'missing' => 'File not found or filename is ambiguous. Upload or select the correct photo.',
                        'remote' => 'Remote URL preserved; browser verification required.',
                        'unsupported' => 'File is unreadable, damaged, or this server cannot convert its format. Original path is retained when possible.',
                        'too_large' => 'Conversion exceeds the server memory budget. Resize the original first.',
                        'preserved' => 'GIF preserved to keep animation.',
                        'converted' => 'Valid WebP prepared; original file preserved.',
                        'repaired' => 'Matching existing image found and path repaired.',
                        'unchanged' => 'Image already available; no conversion required.',
                    ];
                    $message = $messages[$reason] ?? $reason;
                    if ($reason === 'converted') $converted++;
                } catch (Throwable $e) {
                    $result = ['path' => $value]; $status = 'failed'; $message = $e->getMessage();
                }
                $new[] = $result['path'];
                $entries[] = ['table' => $table, 'id' => (int)$row['id'], 'title' => $row['title'] ?? ('Tour #' . ($row['tour_id'] ?? $row['id'])), 'field' => $field, 'path' => $value, 'new_path' => $result['path'], 'status' => $status, 'message' => $message];
            }
            $replacement = $field === 'gallery' ? (str_starts_with(trim($old), '[') ? json_encode($new, JSON_UNESCAPED_SLASHES) : implode(',', $new)) : $new[0];
            try {
                if ($replacement !== $old) {
                    $record = json_encode(['time' => gmdate('c'), 'table' => $table, 'id' => $row['id'], 'field' => $field, 'old' => $old, 'new' => $replacement], JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
                    if (file_put_contents($backup, $record, FILE_APPEND | LOCK_EX) === false) throw new RuntimeException('Cannot save path backup; database field was not updated.');
                    $statement = $db->query("UPDATE `$table` SET `$field` = ? WHERE id = ? AND `$field` = ?", [$replacement, $row['id'], $old]);
                    if ($statement->rowCount() === 0) throw new RuntimeException('Record changed while repairing. Rescan and retry this tour.');
                    $report['updated'] += $statement->rowCount();
                }
                $report['converted'] += $converted;
            } catch (Throwable $e) {
                foreach ($entries as &$entry) { $entry['status'] = 'failed'; $entry['message'] = 'Database/path update failed: ' . $e->getMessage(); }
                unset($entry);
            }
            foreach ($entries as $entry) {
                $report[$entry['status']]++;
                $report['entries'][] = $entry;
                if ($entry['status'] !== 'success') $report['issues'][] = "$table #{$row['id']} $field: " . $entry['message'] . ' - ' . $entry['path'];
            }
        }
        return $report;
    }
}
