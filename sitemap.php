<?php
// KIZZA TOURS & SAFARIS - Dynamic Sitemap
header('Content-Type: application/xml; charset=utf-8');
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/seo.php';

$url = SITE_URL;
$today = date('Y-m-d');

$staticPages = [
    ['file' => 'index.php', 'loc' => '/', 'priority' => '1.0', 'changefreq' => 'weekly'],
    ['file' => 'about-us.php', 'loc' => '/about-us', 'priority' => '0.9', 'changefreq' => 'monthly'],
    ['file' => 'contact-us.php', 'loc' => '/contact-us', 'priority' => '0.8', 'changefreq' => 'monthly'],
    ['file' => 'book-tour.php', 'loc' => '/book-tour', 'priority' => '0.9', 'changefreq' => 'monthly'],
    ['file' => 'tanzania-safari.php', 'loc' => '/tanzania-safari', 'priority' => '0.9', 'changefreq' => 'weekly'],
    ['file' => 'kenya-tanzania-safari.php', 'loc' => '/kenya-tanzania-safari', 'priority' => '0.9', 'changefreq' => 'weekly'],
    ['file' => 'rwanda-gorilla.php', 'loc' => '/rwanda-gorilla-trekking', 'priority' => '0.8', 'changefreq' => 'weekly'],
    ['file' => 'uganda-tours.php', 'loc' => '/uganda-tours', 'priority' => '0.8', 'changefreq' => 'weekly'],
    ['file' => 'zanzibar-holidays.php', 'loc' => '/zanzibar-holidays', 'priority' => '0.8', 'changefreq' => 'weekly'],
    ['file' => 'burundi-tours.php', 'loc' => '/burundi-tours', 'priority' => '0.7', 'changefreq' => 'monthly'],
    ['file' => 'mount-kenya.php', 'loc' => '/mount-kenya-climbing', 'priority' => '0.8', 'changefreq' => 'weekly'],
];

$pages = [];
foreach ($staticPages as $sp) {
    $fp = __DIR__ . '/' . $sp['file'];
    if (!file_exists($fp)) continue;
    $pages[] = [
        'loc' => $url . $sp['loc'],
        'lastmod' => date('Y-m-d', filemtime($fp)),
        'priority' => $sp['priority'],
        'changefreq' => $sp['changefreq'],
    ];
}

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
