<?php
require_once __DIR__ . '/tour-image-paths.php';

function tourImageTableExists($db, $table)
{
    $row = $db->fetchOne('SELECT COUNT(*) AS found FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$table]);
    return (int)($row['found'] ?? 0) > 0;
}

class TourImageAudit
{
    private $root;
    private $paths;
    public function __construct($root) { $this->root = rtrim($root, '/\\') . '/'; $this->paths = new TourImagePaths($root); }
    public function inspect($value, $field)
    {
        $value = trim((string)$value);
        $entry = ['field' => $field, 'path' => $value, 'resolved' => '', 'status' => 'healthy', 'message' => 'Image is available.'];
        if ($value === '') return array_replace($entry, ['status' => 'failed', 'message' => 'No featured image selected. Choose an image in Edit Tour.']);
        $found = $this->paths->find($value);
        if ($found === '') {
            if (preg_match('~^(?:https?:)?//~i', $value)) return array_replace($entry, ['status' => 'review', 'message' => 'Remote image: availability needs browser verification.']);
            return array_replace($entry, ['status' => 'failed', 'message' => 'File not found, or more than one matching filename exists. Upload or select the correct image.']);
        }
        $entry['resolved'] = $found;
        if (!is_readable($this->root . $found)) return array_replace($entry, ['status' => 'failed', 'message' => 'File exists but PHP cannot read it. Check file permissions.']);
        $info = @getimagesize($this->root . $found);
        if (!$info && strtolower(pathinfo($found, PATHINFO_EXTENSION)) !== 'svg') return array_replace($entry, ['status' => 'failed', 'message' => 'Unreadable, damaged or unsupported image format. Replace or convert the file.']);
        if ($this->paths->normalize($value) !== $found) return array_replace($entry, ['status' => 'recoverable', 'message' => 'Stored path differs from the existing image. Fix can restore this reference.']);
        if ($info && !in_array($info['mime'] ?? '', ['image/webp', 'image/gif', 'image/svg+xml'], true)) return array_replace($entry, ['status' => 'optimize', 'message' => 'Image is available; Fix can attempt WebP conversion.']);
        return $entry;
    }
    public function tour(array $tour, array $days = [])
    {
        $entries = [];
        foreach (['image', 'hero_image', 'overview_image_1', 'overview_image_2', 'overview_image_3'] as $field) {
            if ($field !== 'image' && empty($tour[$field])) continue;
            $entries[] = $this->inspect($tour[$field] ?? '', $field);
        }
        foreach (tourListItems($tour['gallery'] ?? '') as $i => $path) $entries[] = $this->inspect($path, 'gallery #' . ($i + 1));
        foreach ($days as $day) {
            if (!empty($day['image_path'])) $entries[] = $this->inspect($day['image_path'], 'Day ' . ($day['day_number'] ?? $day['id']) . ' image');
        }
        $counts = array_fill_keys(['healthy', 'failed', 'recoverable', 'optimize', 'review'], 0);
        foreach ($entries as $entry) $counts[$entry['status']]++;
        return ['id' => (int)$tour['id'], 'title' => $tour['title'] ?? ('Tour #' . $tour['id']), 'counts' => $counts, 'entries' => $entries];
    }
}
