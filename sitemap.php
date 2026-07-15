<?php
// Sitemap generator - no sessions, no cookies, no output before XML
header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: index, follow');
header('Cache-Control: public, max-age=3600');

// Suppress any PHP errors from polluting XML output
error_reporting(0);
ini_set('display_errors', '0');

// Minimal .env loader
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim(trim($value), '"\''));
        }
    }
}

$siteProtocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$siteHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$prodDomains = array_map('trim', explode(',', getenv('PRODUCTION_DOMAINS') ?: 'kizzatoursandsafaris.com,www.kizzatoursandsafaris.com'));
$baseUrl = in_array($siteHost, $prodDomains)
    ? $siteProtocol . '://' . $siteHost
    : $siteProtocol . '://' . $siteHost . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');

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
        'loc' => $baseUrl . $sp['loc'],
        'lastmod' => date('Y-m-d', filemtime($fp)),
        'priority' => $sp['priority'],
        'changefreq' => $sp['changefreq'],
    ];
}

try {
    $dbHost = getenv('DB_HOST') ?: 'localhost';
    $dbName = getenv('DB_NAME') ?: 'kizza_tours';
    $dbUser = getenv('DB_USER') ?: 'root';
    $dbPass = getenv('DB_PASS') ?: '';
    $dbCharset = getenv('DB_CHARSET') ?: 'utf8mb4';

    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset={$dbCharset}",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

    $tours = $pdo->query("SELECT slug, updated_at FROM tour_packages WHERE status = 'active' AND (no_robots IS NULL OR no_robots = 0) AND slug IS NOT NULL AND slug != ''")->fetchAll();
    foreach ($tours as $tour) {
        $pages[] = [
            'loc' => $baseUrl . '/safari/' . $tour['slug'],
            'lastmod' => !empty($tour['updated_at']) ? date('Y-m-d', strtotime($tour['updated_at'])) : $today,
            'priority' => '0.7',
            'changefreq' => 'monthly',
        ];
    }

    $dests = $pdo->query("SELECT slug, updated_at FROM destinations WHERE status = 'active' AND slug IS NOT NULL AND slug != ''")->fetchAll();
    foreach ($dests as $dest) {
        $pages[] = [
            'loc' => $baseUrl . '/destination/' . $dest['slug'],
            'lastmod' => !empty($dest['updated_at']) ? date('Y-m-d', strtotime($dest['updated_at'])) : $today,
            'priority' => '0.6',
            'changefreq' => 'monthly',
        ];
    }

    $dbPages = $pdo->query("SELECT slug, updated_at FROM pages WHERE status = 'active' AND slug IS NOT NULL AND slug != ''")->fetchAll();
    foreach ($dbPages as $p) {
        $pages[] = [
            'loc' => $baseUrl . '/' . $p['slug'],
            'lastmod' => !empty($p['updated_at']) ? date('Y-m-d', strtotime($p['updated_at'])) : $today,
            'priority' => '0.6',
            'changefreq' => 'monthly',
        ];
    }
    $pdo = null;
} catch (Exception $e) {
    // DB error - just serve static pages, don't corrupt XML with error output
}

$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($pages as $p) {
    $xml .= "  <url>\n";
    $xml .= "    <loc>" . htmlspecialchars($p['loc'], ENT_XML1, 'UTF-8') . "</loc>\n";
    $xml .= "    <lastmod>" . htmlspecialchars($p['lastmod'], ENT_XML1, 'UTF-8') . "</lastmod>\n";
    $xml .= "    <changefreq>" . $p['changefreq'] . "</changefreq>\n";
    $xml .= "    <priority>" . $p['priority'] . "</priority>\n";
    $xml .= "  </url>\n";
}
$xml .= "</urlset>\n";

echo $xml;
