<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/seo.php';
$siteName = getSetting('site_name', 'Kizza Tours & Safaris');
$siteEmail = getSetting('site_email', SITE_EMAIL);
$sitePhone = getSetting('site_phone', SITE_PHONE);
$siteWhatsapp = getSetting('site_whatsapp', SITE_WHATSAPP);
$ogImage = getMediaUrl('og_image', 'https://kizzatoursandsafaris.com/assets/images/log.png');
$favicon = getMediaUrl('site_favicon', 'images/log.png');

if (!isset($pageSeo) || !is_array($pageSeo)) {
    $pageSeo = seoPageMeta('home');
}
$pageSeo['pageKey'] = $pageSeo['pageKey'] ?? 'home';
?>
<!DOCTYPE html>
<html lang="<?php echo get_current_lang(); ?>" dir="<?php echo get_lang_dir(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php seoRenderMetaTags($pageSeo); ?>
    <meta name="keywords" content="Kizza Tours, East Africa safaris, Tanzania tours, Kenya safaris, gorilla trekking, Kilimanjaro climbing, luxury safari, Zanzibar holidays, Uganda tours, Rwanda tours">
    <meta name="author" content="<?php echo $siteName; ?>">
    <meta name="robots" content="<?php echo htmlspecialchars($pageSeo['robots'] ?? 'index, follow', ENT_QUOTES, 'UTF-8'); ?>">

    <!-- Open Graph -->
    <meta property="og:image" content="<?php echo $ogImage; ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($pageSeo['canonical'] ?? SITE_URL, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:type" content="<?php echo ($pageSeo['schema'] ?? '') === 'Article' ? 'article' : 'website'; ?>">
    <meta property="og:site_name" content="<?php echo $siteName; ?>">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="<?php echo $ogImage; ?>">

    <!-- Hreflang Tags (indexable pages only) -->
    <?php if (!isset($pageSeo['robots']) || strpos($pageSeo['robots'], 'noindex') === false):
        $hreflangPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
        $hreflangQuery = $_GET;
        foreach (['lang', 'slug', 'destination_id', 'page', 'id', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid'] as $ep) {
            unset($hreflangQuery[$ep]);
        }
        $siteParts = parse_url(SITE_URL);
        $hreflangBase = $siteParts['scheme'] . '://' . $siteParts['host'] . $hreflangPath;
    ?>
    <?php foreach (get_available_languages() as $code => $lang_info):
        $hreflangUrl = $hreflangBase;
        $hq = $hreflangQuery;
        if ($code !== 'en') {
            $hq['lang'] = $code;
        }
        if (!empty($hq)) {
            $hreflangUrl .= '?' . http_build_query($hq);
        }
    ?>
    <link rel="alternate" hreflang="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars($hreflangUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endforeach; ?>
    <link rel="alternate" hreflang="x-default" href="<?php echo htmlspecialchars($hreflangBase . (!empty($hreflangQuery) ? '?' . http_build_query($hreflangQuery) : ''), ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>

    <!-- Schema Markup (consolidated @graph) -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@graph": [
            {
                "@type": "TravelAgency",
                "name": "<?php echo $siteName; ?>",
                "description": "Discover East Africa Beyond Expectations. Premium luxury safaris, gorilla trekking, Kilimanjaro expeditions, and tailor-made adventures.",
                "url": "<?php echo SITE_URL; ?>",
                "logo": "<?php echo $ogImage; ?>",
                "image": "<?php echo $ogImage; ?>",
                "telephone": "<?php echo $sitePhone; ?>",
                "email": "<?php echo $siteEmail; ?>",
                "address": {
                    "@type": "PostalAddress",
                    "addressLocality": "Arusha",
                    "addressCountry": "TZ"
                },
                "priceRange": "$$$",
                "openingHours": "Mo-Su 08:00-18:00",
                "currenciesAccepted": "USD, EUR, GBP, TZS",
                "paymentAccepted": "Bank Transfer, Credit Card, Cash",
                "sameAs": [
                    "<?php echo getSetting('facebook_url', '#'); ?>",
                    "<?php echo getSetting('instagram_url', '#'); ?>",
                    "<?php echo getSetting('twitter_url', '#'); ?>",
                    "<?php echo getSetting('youtube_url', '#'); ?>",
                    "<?php echo getSetting('tripadvisor_url', '#'); ?>"
                ],
                "areaServed": ["Tanzania", "Kenya", "Uganda", "Rwanda", "Zanzibar", "Burundi"],
                "hasOfferCatalog": {
                    "@type": "OfferCatalog",
                    "name": "East Africa Safari Tours",
                    "itemListElement": [
                        {"@type": "Offer", "itemOffered": {"@type": "Tour", "name": "Luxury Safaris"}},
                        {"@type": "Offer", "itemOffered": {"@type": "Tour", "name": "Great Migration Safaris"}},
                        {"@type": "Offer", "itemOffered": {"@type": "Tour", "name": "Gorilla Trekking"}},
                        {"@type": "Offer", "itemOffered": {"@type": "Tour", "name": "Kilimanjaro Expeditions"}},
                        {"@type": "Offer", "itemOffered": {"@type": "Tour", "name": "Zanzibar Beach Holidays"}},
                        {"@type": "Offer", "itemOffered": {"@type": "Tour", "name": "Mount Kenya Climbing"}}
                    ]
                }
            },
            {
                "@type": "WebSite",
                "name": "<?php echo $siteName; ?>",
                "url": "<?php echo SITE_URL; ?>",
                "potentialAction": {
                    "@type": "SearchAction",
                    "target": "<?php echo SITE_URL; ?>/?s={search_term_string}",
                    "query-input": "required name=search_term_string"
                }
            }
        ]
    }
    </script>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo ASSETS_PATH; ?>images/log.png">
    <link rel="apple-touch-icon" href="<?php echo ASSETS_PATH; ?>images/log.png">

    <!-- Preconnect (limited to 4 most critical) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">

    <!-- Preload LCP hero poster (with responsive srcset) -->
    <link rel="preload" fetchpriority="high" as="image" href="<?php echo getMediaUrl('hero_poster', 'images/hero-poster.jpg'); ?>" imagesrcset="<?php
        $posterUrl = getMediaUrl('hero_poster', 'images/hero-poster.jpg');
        $posterBase = preg_replace('/\.(webp|jpg|jpeg|png)$/', '', $posterUrl);
        echo "$posterBase-480w.webp 480w, $posterBase-768w.webp 768w, $posterBase-1200w.webp 1200w, $posterBase-1920w.webp 1920w";
    ?>">

    <!-- Fonts (non-blocking) -->
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Inter:wght@300;400;500;600;700&family=Montserrat:wght@400;500;600;700;800&display=swap" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Inter:wght@300;400;500;600;700&family=Montserrat:wght@400;500;600;700;800&display=swap"></noscript>

    <!-- Critical Inline Styles (truly above-the-fold only) -->
    <style><?php
        echo ':root{--primary:#0A2540;--primary-light:#1A3A5C;--secondary:#D4AF37;--accent:#C9A227;--white:#FFF;--text:#1A1A1A;--text-light:#6B7280;--shadow-gold:0 8px 32px rgba(212,175,55,0.3);--radius-md:16px;--radius-lg:24px;--transition:all 0.4s cubic-bezier(0.25,0.46,0.45,0.94);--font-primary:\'Cormorant Garamond\',Georgia,serif;--font-secondary:\'Montserrat\',-apple-system,BlinkMacSystemFont,sans-serif;--font-body:\'Inter\',-apple-system,BlinkMacSystemFont,sans-serif}
        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
        html{scroll-behavior:smooth;overflow-x:hidden}
        body{font-family:var(--font-body);color:var(--text);line-height:1.7;-webkit-font-smoothing:antialiased;overflow-x:hidden;background:var(--primary)}
        img{max-width:100%;height:auto}
        a{color:var(--secondary);text-decoration:none;transition:var(--transition)}
        h1{font-size:clamp(2.5rem,6vw,5rem);font-family:var(--font-primary);font-weight:600;line-height:1.2;color:var(--primary)}
        #preloader{position:fixed;top:0;left:0;width:100%;height:100%;background:var(--primary);display:flex;align-items:center;justify-content:center;z-index:99999;transition:opacity .5s ease,visibility .5s ease;opacity:1}
        #preloader.hidden{opacity:0;visibility:hidden}
        .preloader-content{text-align:center}
        .preloader-logo{font-family:var(--font-primary);font-size:2.5rem;color:var(--secondary);margin-bottom:1.5rem;font-weight:700}'; 
    ?></style>

    <script>(function(){var p=document.getElementById('preloader');if(p){document.addEventListener('DOMContentLoaded',function(){p.classList.add('hidden')});setTimeout(function(){p.classList.add('hidden')},2000)}})();</script>

    <!-- Bootstrap 5 (non-blocking — critical Bootstrap grid styles inlined above) -->
    <link rel="preload" as="style" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"></noscript>

    <!-- Full Stylesheet (non-blocking — critical styles already inlined above) -->
    <link rel="preload" as="style" href="<?php echo ASSETS_PATH; ?>css/style.min.css?v=2" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="<?php echo ASSETS_PATH; ?>css/style.min.css?v=2"></noscript>

    <!-- Font Awesome (non-blocking) -->
    <link rel="preload" as="style" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"></noscript>

    <!-- Flag Icons (non-blocking) -->
    <link rel="preload" as="style" href="https://cdn.jsdelivr.net/gh/lipis/flag-icons@7.2.3/css/flag-icons.min.css" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/lipis/flag-icons@7.2.3/css/flag-icons.min.css"></noscript>
    
    <!-- AOS (non-blocking) -->
    <link rel="preload" as="style" href="https://unpkg.com/aos@2.3.1/dist/aos.css" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://unpkg.com/aos@2.3.1/dist/aos.css"></noscript>
    
    <!-- SwiperJS (non-blocking) -->
    <link rel="preload" as="style" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"></noscript>
    
    <!-- Lightbox (non-blocking) -->
    <link rel="preload" as="style" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/css/lightbox.min.css" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/css/lightbox.min.css"></noscript>
</head>
<body>

<!-- Preloader -->
<div id="preloader">
    <div class="preloader-content">
        <div class="preloader-logo"><?php echo $siteName; ?></div>
        <div class="preloader-bar"><div class="preloader-bar-inner"></div></div>
    </div>
</div>

<!-- WhatsApp Float -->
<a href="https://wa.me/<?php echo $siteWhatsapp; ?>" class="whatsapp-float" target="_blank" rel="noopener noreferrer" aria-label="Chat on WhatsApp">
    <i class="fab fa-whatsapp"></i>
</a>

<!-- Toast Container (Bootstrap) -->
<div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer" style="z-index: 99999;"></div>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg fixed-top" id="mainNav">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <img src="<?php echo ASSETS_PATH; ?>images/log.png" alt="Kizza Tours &amp; Safaris" fetchpriority="high">
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <li class="nav-item"><a class="nav-link active" href="index.php"><?php echo __('nav_home'); ?></a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo SITE_URL; ?>/about-us"><?php echo __('nav_about'); ?></a></li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="toursDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false"><?php echo __('nav_tours'); ?></a>
                    <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="toursDropdown" style="background: var(--primary); border: 1px solid rgba(255,255,255,0.1);">
                        <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/tanzania-safari"><?php echo __('nav_tour_tz'); ?></a></li>
                        <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/kenya-tanzania-safari"><?php echo __('nav_tour_ke_tz'); ?></a></li>
                        <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/uganda-tours"><?php echo __('nav_tour_ug'); ?></a></li>
                        <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/zanzibar-holidays"><?php echo __('nav_tour_zanzibar'); ?></a></li>
                        <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/burundi-tours"><?php echo __('nav_tour_bi'); ?></a></li>
                        <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/rwanda-gorilla-trekking"><?php echo __('nav_tour_rw'); ?></a></li>
                        <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/mount-kenya-climbing"><?php echo __('nav_tour_kenya'); ?></a></li>
                    </ul>
                </li>
                <li class="nav-item"><a class="nav-link" href="index.php#destinations"><?php echo __('nav_destinations'); ?></a></li>
                <li class="nav-item"><a class="nav-link" href="index.php#gallery"><?php echo __('nav_gallery'); ?></a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo SITE_URL; ?>/contact-us"><?php echo __('nav_contact'); ?></a></li>
                <li class="nav-item dropdown ms-lg-2">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-1" href="#" id="langDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="fi fi-<?php echo get_lang_flag(); ?> fis"></span>
                        <span class="d-none d-lg-inline"><?php echo get_lang_name(); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark" aria-labelledby="langDropdown" style="background: var(--primary); border: 1px solid rgba(255,255,255,0.1); min-width: 180px;">
                        <?php foreach (get_available_languages() as $code => $lang_info): ?>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 <?php echo $code === get_current_lang() ? 'active' : ''; ?>" href="<?php echo language_switcher_link($code); ?>">
                                <span class="fi fi-<?php echo $lang_info['flag']; ?> fis"></span>
                                <span><?php echo $lang_info['name']; ?></span>
                                <?php if ($code === get_current_lang()): ?>
                                <i class="fas fa-check ms-auto text-gold" style="color: var(--secondary);"></i>
                                <?php endif; ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
                <li class="nav-item ms-lg-2">
                    <a class="btn btn-premium btn-gold btn-sm btn-nav" href="<?php echo SITE_URL; ?>/book-tour">
                        <i class="fas fa-calendar-check me-1"></i> <?php echo __('nav_book'); ?>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
