<?php
header('Content-Type: text/plain; charset=utf-8');

// Minimal .env loader - no sessions, no cookies
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
?>
# KIZZA TOURS & SAFARIS - Robots.txt
# Premium East Africa Tourism Platform

User-agent: *
Allow: /

# Disallow admin and private areas
Disallow: /admin/
Disallow: /api/
Disallow: /includes/
Disallow: /templates/
Disallow: /database/
Disallow: /vendor/
Disallow: /uploads/private/

# Sitemap
Sitemap: <?php echo $baseUrl; ?>/sitemap.xml
