<?php
require_once __DIR__ . '/seo.php';
$siteName = getSetting('site_name', 'Kizza Tours & Safaris');
$siteEmail = getSetting('site_email', SITE_EMAIL);
$sitePhone = getSetting('site_phone', SITE_PHONE);
$siteWhatsapp = getSetting('site_whatsapp', SITE_WHATSAPP);
$ogImage = getMediaUrl('og_image', 'images/og-image.jpg');
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
    <link rel="icon" type="image/png" href="<?php echo $favicon; ?>">
    <link rel="apple-touch-icon" href="<?php echo $favicon; ?>">

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

    <!-- Critical Inline Styles (above-the-fold) -->
    <style><?php
        // Minimal critical CSS for above-the-fold rendering
        echo ':root{--primary:#0A2540;--primary-light:#1A3A5C;--secondary:#D4AF37;--secondary-light:#E8C84A;--accent:#C9A227;--accent-dark:#B8921E;--white:#FFF;--off-white:#F8F6F3;--cream:#F5F0E8;--light-gold:#F0E6C8;--text:#1A1A1A;--text-light:#6B7280;--text-muted:#9CA3AF;--dark-overlay:rgba(10,37,64,0.85);--glass-bg:rgba(255,255,255,0.08);--glass-border:rgba(255,255,255,0.15);--shadow-sm:0 2px 8px rgba(10,37,64,0.06);--shadow-md:0 8px 32px rgba(10,37,64,0.1);--shadow-lg:0 16px 48px rgba(10,37,64,0.15);--shadow-xl:0 24px 64px rgba(10,37,64,0.2);--shadow-gold:0 8px 32px rgba(212,175,55,0.3);--radius-sm:8px;--radius-md:16px;--radius-lg:24px;--radius-xl:32px;--transition:all 0.4s cubic-bezier(0.25,0.46,0.45,0.94);--transition-slow:all 0.8s cubic-bezier(0.25,0.46,0.45,0.94);--font-primary:\'Cormorant Garamond\',Georgia,serif;--font-secondary:\'Montserrat\',-apple-system,BlinkMacSystemFont,sans-serif;--font-body:\'Inter\',-apple-system,BlinkMacSystemFont,sans-serif}
        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
        html{scroll-behavior:smooth;overflow-x:hidden}
        body{font-family:var(--font-body);color:var(--text);background:var(--white);line-height:1.7;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;overflow-x:hidden}
        img{max-width:100%;height:auto}
        a{color:var(--secondary);text-decoration:none;transition:var(--transition)}
        a:hover{color:var(--accent)}
        h1,h2,h3,h4,h5,h6{font-family:var(--font-primary);font-weight:600;line-height:1.2;color:var(--primary)}
        h1{font-size:clamp(2.5rem,6vw,5rem)}h2{font-size:clamp(2rem,4vw,3.5rem)}h3{font-size:clamp(1.5rem,3vw,2.5rem)}h4{font-size:clamp(1.25rem,2vw,1.75rem)}
        .section-subtitle{font-family:var(--font-secondary);font-size:.85rem;font-weight:600;text-transform:uppercase;letter-spacing:4px;color:var(--secondary);margin-bottom:1rem;display:inline-block;position:relative}
        .section-subtitle::after{content:\'\';display:block;width:40px;height:2px;background:var(--secondary);margin-top:.5rem}
        .section-title{margin-bottom:1.5rem}
        .section-padding{padding:100px 0}
        .section-padding-lg{padding:140px 0}
        .section-dark{background:var(--primary);color:var(--white)}
        .section-dark h2,.section-dark h3,.section-dark h4{color:var(--white)}
        .btn-premium{display:inline-flex;align-items:center;gap:.75rem;padding:1rem 2.5rem;font-family:var(--font-secondary);font-size:.85rem;font-weight:600;text-transform:uppercase;letter-spacing:2px;border:none;border-radius:50px;cursor:pointer;transition:var(--transition);position:relative;overflow:hidden;text-decoration:none}
        .btn-premium::before{content:\'\';position:absolute;top:0;left:-100%;width:100%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,0.2),transparent);transition:.5s}
        .btn-premium:hover::before{left:100%}
        .btn-gold{background:linear-gradient(135deg,var(--secondary),var(--accent));color:var(--white);box-shadow:var(--shadow-gold)}
        .btn-gold:hover{background:linear-gradient(135deg,var(--accent),var(--secondary));color:var(--white);transform:translateY(-2px);box-shadow:0 12px 40px rgba(212,175,55,0.4)}
        .btn-outline{background:transparent;color:var(--white);border:2px solid rgba(255,255,255,0.4)}
        .btn-outline:hover{background:var(--white);color:var(--primary);border-color:var(--white);transform:translateY(-2px)}
        .btn-outline-gold{background:transparent;color:var(--secondary);border:2px solid var(--secondary)}
        .btn-outline-gold:hover{background:var(--secondary);color:var(--white);transform:translateY(-2px)}
        .btn-dark{background:var(--primary);color:var(--white)}
        .btn-dark:hover{background:var(--primary-light);color:var(--white);transform:translateY(-2px)}
        .btn-sm{padding:.7rem 1.5rem;font-size:.75rem}
        .btn-lg{padding:1.2rem 3rem;font-size:.9rem}
        .hero-section{position:relative;height:100vh;min-height:700px;overflow:hidden;display:flex;align-items:center;justify-content:center}
        .hero-video{position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;z-index:0}
        .hero-overlay{position:absolute;top:0;left:0;width:100%;height:100%;background:linear-gradient(135deg,rgba(10,37,64,0.85) 0%,rgba(10,37,64,0.4) 50%,rgba(10,37,64,0.7) 100%);z-index:1}
        .hero-overlay::after{content:\'\';position:absolute;bottom:0;left:0;width:100%;height:200px;background:linear-gradient(to top,var(--white),transparent)}
        .hero-content{position:relative;z-index:2;text-align:center;max-width:900px;padding:0 20px}
        .hero-title{font-size:clamp(2.8rem,7vw,6rem);font-weight:700;color:var(--white);line-height:1.1;margin-bottom:1.5rem;letter-spacing:-1px}
        .hero-title .gold-text{color:var(--secondary)}
        .hero-subtitle{font-size:clamp(1rem,1.5vw,1.2rem);color:rgba(255,255,255,0.85);max-width:650px;margin:0 auto 2.5rem;line-height:1.8;font-family:var(--font-body)}
        .hero-buttons{display:flex;gap:1rem;justify-content:center;flex-wrap:wrap}
        .navbar{padding:1rem 0;transition:var(--transition);z-index:1000}
        .navbar.scrolled{background:rgba(10,37,64,0.95);backdrop-filter:blur(20px);box-shadow:0 4px 30px rgba(0,0,0,0.1);padding:.5rem 0}
        .navbar-brand{font-family:var(--font-primary);font-size:1.5rem;font-weight:700;color:var(--white)!important;letter-spacing:1px}
        .navbar-brand img{height:80px;width:auto;transition:all 0.3s ease}
        .navbar.scrolled .navbar-brand img{height:56px}
        .navbar .nav-link{font-family:var(--font-secondary);font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:2px;color:rgba(255,255,255,0.8)!important;padding:.5rem 1.2rem!important;transition:var(--transition);position:relative}
        .navbar .nav-link:hover,.navbar .nav-link.active{color:var(--secondary)!important}
        .navbar .nav-link::after{content:\'\';position:absolute;bottom:0;left:50%;transform:translateX(-50%) scaleX(0);width:60%;height:2px;background:var(--secondary);transition:var(--transition)}
        .navbar .nav-link:hover::after,.navbar .nav-link.active::after{transform:translateX(-50%) scaleX(1)}
        .navbar .btn-nav{padding:.5rem 1.5rem;font-size:.7rem}
        .navbar-toggler{border-color:rgba(255,255,255,0.3)}
        .navbar-toggler-icon{background-image:url(\"data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 30 30\'%3e%3cpath stroke=\'rgba%28255, 255, 255, 0.9%29\' stroke-linecap=\'round\' stroke-miterlimit=\'10\' stroke-width=\'2\' d=\'M4 7h22M4 15h22M4 23h22\'/%3e%3c/svg%3e\")!important}
        .navbar-toggler:focus{box-shadow:0 0 0 0.15rem rgba(255,255,255,0.25)}
        @media(max-width:991.98px){.navbar-brand img{height:70px}.navbar-collapse{background:rgba(10,37,64,0.98);padding:1rem;border-radius:var(--radius-md);margin-top:.5rem}}@media(max-width:768px){.hero-title{font-size:2.5rem}.hero-buttons{flex-direction:column;align-items:center}.hero-buttons .btn-premium{width:100%;max-width:280px;justify-content:center}.section-padding{padding:60px 0}.section-padding-lg{padding:80px 0}}
        @media(hover:hover){.navbar .dropdown:hover .dropdown-menu{display:block}}.navbar .dropdown-menu{margin-top:0}
        #preloader{position:fixed;top:0;left:0;width:100%;height:100%;background:var(--primary);display:flex;align-items:center;justify-content:center;z-index:99999;transition:opacity .8s ease,visibility .8s ease}
        #preloader.hidden{opacity:0;visibility:hidden}
        .preloader-content{text-align:center}
        .preloader-logo{font-family:var(--font-primary);font-size:2.5rem;color:var(--secondary);margin-bottom:1.5rem;font-weight:700}
        .preloader-bar{width:200px;height:2px;background:rgba(255,255,255,0.1);border-radius:2px;overflow:hidden;margin:0 auto}
        .preloader-bar-inner{width:0%;height:100%;background:var(--secondary);animation:preloaderLoad 2s ease-in-out forwards}
        @keyframes preloaderLoad{0%{width:0%}50%{width:70%}100%{width:100%}}
        .whatsapp-float{position:fixed;bottom:30px;right:30px;width:60px;height:60px;background:#25D366;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:var(--white);box-shadow:0 4px 20px rgba(37,211,102,0.4);z-index:999;transition:var(--transition);cursor:pointer;text-decoration:none}
        .whatsapp-float:hover{transform:scale(1.1);color:var(--white);box-shadow:0 8px 30px rgba(37,211,102,0.5)}
        .toast-container .toast{min-width:320px;border:none}
        .toast.success .toast-header{background:#10B981;color:#fff}
        .toast.success .toast-body{background:#D1FAE5;color:#065F46}
        .toast.error .toast-header{background:#EF4444;color:#fff}
        .toast.error .toast-body{background:#FEE2E2;color:#991B1B}
        .toast .btn-close{filter:brightness(0) invert(1)}
        .glass{background:var(--glass-bg);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid var(--glass-border);border-radius:var(--radius-lg)}
        @font-face{font-family:\'Font Awesome 6 Free\';font-display:swap}
        @font-face{font-family:\'Font Awesome 6 Brands\';font-display:swap}
        @font-face{font-family:\'Cormorant Garamond\';font-display:swap}
        @font-face{font-family:\'Inter\';font-display:swap}
        @font-face{font-family:\'Montserrat\';font-display:swap}
        '; // end critical CSS string
    ?></style>

    <script>function _cssLoaded(){var el=document.getElementById('preloader');if(el)el.classList.add('hidden')}setTimeout(function(){var el=document.getElementById('preloader');if(el)el.classList.add('hidden')},5000);</script>

    <!-- Bootstrap 5 (render-blocking — needed for grid/layout) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

    <!-- Full Stylesheet (non-blocking — critical styles already inlined above) -->
    <link rel="preload" as="style" href="<?php echo ASSETS_PATH; ?>css/style.min.css?v=2" onload="this.onload=null;this.rel='stylesheet';_cssLoaded()">
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
            <img src="<?php echo ASSETS_PATH; ?>images/log.png" alt="Kizza Tours &amp; Safaris">
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
