<?php
// KIZZA TOURS & SAFARIS - Dynamic Sitemap
header('Content-Type: application/xml; charset=utf-8');
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/seo.php';

$url = SITE_URL;
$today = date('Y-m-d');

// Determine the most recent date among all pages
$fileDates = [];
foreach (['index.php', 'about-us.php', 'contact-us.php', 'book-tour.php', 'tanzania-safari.php', 'kenya-tanzania-safari.php', 'uganda-tours.php', 'zanzibar-holidays.php', 'burundi-tours.php', 'rwanda-gorilla.php', 'mount-kenya.php'] as $f) {
    $fp = __DIR__ . '/' . $f;
    if (file_exists($fp)) {
        $fileDates[$f] = date('Y-m-d', filemtime($fp));
    }
}

$pages = [
    ['loc' => $url . '/', 'lastmod' => $fileDates['index.php'] ?? $today, 'priority' => '1.0', 'changefreq' => 'weekly'],
    ['loc' => $url . '/about-us', 'lastmod' => $fileDates['about-us.php'] ?? $today, 'priority' => '0.9', 'changefreq' => 'monthly'],
    ['loc' => $url . '/contact-us', 'lastmod' => $fileDates['contact-us.php'] ?? $today, 'priority' => '0.8', 'changefreq' => 'monthly'],
    ['loc' => $url . '/book-tour', 'lastmod' => $fileDates['book-tour.php'] ?? $today, 'priority' => '0.9', 'changefreq' => 'monthly'],
    ['loc' => $url . '/tanzania-safari', 'lastmod' => $fileDates['tanzania-safari.php'] ?? $today, 'priority' => '0.9', 'changefreq' => 'weekly'],
    ['loc' => $url . '/kenya-tanzania-safari', 'lastmod' => $fileDates['kenya-tanzania-safari.php'] ?? $today, 'priority' => '0.9', 'changefreq' => 'weekly'],
    ['loc' => $url . '/rwanda-gorilla-trekking', 'lastmod' => $fileDates['rwanda-gorilla.php'] ?? $today, 'priority' => '0.8', 'changefreq' => 'weekly'],
    ['loc' => $url . '/uganda-tours', 'lastmod' => $fileDates['uganda-tours.php'] ?? $today, 'priority' => '0.8', 'changefreq' => 'weekly'],
    ['loc' => $url . '/zanzibar-holidays', 'lastmod' => $fileDates['zanzibar-holidays.php'] ?? $today, 'priority' => '0.8', 'changefreq' => 'weekly'],
    ['loc' => $url . '/burundi-tours', 'lastmod' => $fileDates['burundi-tours.php'] ?? $today, 'priority' => '0.7', 'changefreq' => 'monthly'],
    ['loc' => $url . '/mount-kenya-climbing', 'lastmod' => $fileDates['mount-kenya.php'] ?? $today, 'priority' => '0.8', 'changefreq' => 'weekly'],
];

// Get dynamic tours/packages from DB
try {
    $db = Database::getInstance();
    $tours = $db->fetchAll("SELECT slug, updated_at FROM tour_packages WHERE status = 'active' AND slug IS NOT NULL AND slug != ''");
    foreach ($tours as $tour) {
        $pages[] = [
            'loc' => $url . '/safari/' . urlencode($tour['slug']),
            'lastmod' => !empty($tour['updated_at']) ? date('Y-m-d', strtotime($tour['updated_at'])) : $today,
            'priority' => '0.7',
            'changefreq' => 'monthly',
        ];
    }
    $dests = $db->fetchAll("SELECT slug, updated_at FROM destinations WHERE status = 'active' AND slug IS NOT NULL AND slug != ''");
    foreach ($dests as $dest) {
        $pages[] = [
            'loc' => $url . '/destination/' . urlencode($dest['slug']),
            'lastmod' => !empty($dest['updated_at']) ? date('Y-m-d', strtotime($dest['updated_at'])) : $today,
            'priority' => '0.6',
            'changefreq' => 'monthly',
        ];
    }
    $dbPages = $db->fetchAll("SELECT slug, updated_at FROM pages WHERE status = 'active' AND slug IS NOT NULL AND slug != ''");
    foreach ($dbPages as $p) {
        $pages[] = [
            'loc' => $url . '/' . urlencode($p['slug']),
            'lastmod' => !empty($p['updated_at']) ? date('Y-m-d', strtotime($p['updated_at'])) : $today,
            'priority' => '0.6',
            'changefreq' => 'monthly',
        ];
    }
} catch (Exception $e) {
    // DB not available, sitemap with static pages only
}

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($pages as $p): ?>
    <url>
        <loc><?php echo htmlspecialchars($p['loc'], ENT_XML1, 'UTF-8'); ?></loc>
        <lastmod><?php echo htmlspecialchars($p['lastmod'], ENT_XML1, 'UTF-8'); ?></lastmod>
        <changefreq><?php echo $p['changefreq']; ?></changefreq>
        <priority><?php echo $p['priority']; ?></priority>
    </url>
<?php endforeach; ?>
</urlset>
