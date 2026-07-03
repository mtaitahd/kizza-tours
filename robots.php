<?php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/includes/config.php';
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
Sitemap: <?php echo SITE_URL; ?>/sitemap.xml
