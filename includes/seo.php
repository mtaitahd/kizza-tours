<?php
// KIZZA TOURS & SAFARIS - SEO Enhancement Module
// Adds canonical URLs, additional schema types, and page-specific SEO

function seoPageData($pageKey) {
    $siteName = getSetting('site_name', SITE_NAME);
    $sitePhone = getSetting('site_phone', SITE_PHONE);
    $siteEmail = getSetting('site_email', SITE_EMAIL);
    $siteWhatsapp = getSetting('site_whatsapp', SITE_WHATSAPP);
    $ogImage = getMediaUrl('og_image', 'images/log.png');
    $url = SITE_URL;
    $suffix = ' | Kizza Tours';

    $pages = [
        'home' => [
            'title' => $siteName . ' - Premium East Africa Safari Tours',
            'description' => 'Kizza Tours & Safaris offers premium luxury safaris, gorilla trekking, Kilimanjaro climbing, and tailor-made adventures across Tanzania, Kenya, Uganda, Rwanda & Zanzibar.',
            'canonical' => $url . '/',
            'ogTitle' => $siteName . ' - Premium East Africa Safari Tours',
            'ogDesc' => 'Premium luxury safaris, gorilla trekking, Kilimanjaro expeditions, and tailor-made adventures across East Africa.',
            'h1' => 'Experience East Africa Like Never Before',
            'schema' => 'TravelAgency',
        ],
        'about' => [
            'title' => 'About Kizza Tours & Safaris' . $suffix,
            'description' => 'Learn about Kizza Tours & Safaris, a premier East Africa tour operator. Discover our story, mission, vision, and why we are the trusted Tanzania safari company for luxury African adventures.',
            'canonical' => $url . '/about-us',
            'ogTitle' => 'About Kizza Tours & Safaris - East Africa Safari Experts',
            'ogDesc' => 'Discover the Kizza Tours story. Premium East Africa tour operator with 10+ years of experience creating extraordinary safaris across Tanzania, Kenya, Uganda, Rwanda & Zanzibar.',
            'h1' => 'About Kizza Tours & Safaris',
            'schema' => 'AboutPage',
        ],
        'contact' => [
            'title' => 'Contact Kizza Tours - Safari Booking' . $suffix,
            'description' => 'Contact Kizza Tours & Safaris for safari booking inquiries. Reach our Tanzania tour operator team via phone, email, WhatsApp, or our contact form. Start planning your East Africa adventure today.',
            'canonical' => $url . '/contact-us',
            'ogTitle' => 'Contact Kizza Tours - Plan Your Safari',
            'ogDesc' => 'Get in touch with Kizza Tours & Safaris. Our team is ready to help you plan the perfect East Africa safari experience.',
            'h1' => 'Contact Kizza Tours & Safaris',
            'schema' => 'ContactPage',
        ],
        'book' => [
            'title' => 'Book Tanzania Safari - Reserve Your Adventure' . $suffix,
            'description' => 'Book your Tanzania safari with Kizza Tours & Safaris. Secure your East Africa adventure with our easy reservation system. Custom itineraries, best price guarantee, and 24/7 support.',
            'canonical' => $url . '/book-tour',
            'ogTitle' => 'Book Your Safari - Kizza Tours',
            'ogDesc' => 'Reserve your dream East Africa safari. Choose from luxury safaris, gorilla trekking, Kilimanjaro climbs, and beach holidays.',
            'h1' => 'Book Your Safari Adventure',
            'schema' => 'Product',
        ],
        'tanzania-safari' => [
            'title' => 'Tanzania Safari Packages - Wildlife Tours' . $suffix,
            'description' => 'Experience the best Tanzania safari packages with Kizza Tours & Safaris. Serengeti wildlife safaris, Ngorongoro crater tours, luxury lodges, and budget-friendly options. Book your Tanzania safari today.',
            'canonical' => $url . '/tanzania-safari',
            'ogTitle' => 'Tanzania Safari Packages - Serengeti Wildlife Tours',
            'ogDesc' => 'Discover premium Tanzania safari packages. Serengeti, Ngorongoro, Tarangire & more. Luxury & budget options available.',
            'h1' => 'Tanzania Safari Packages',
            'schema' => 'Tour',
        ],
        'kenya-tanzania-safari' => [
            'title' => 'Kenya & Tanzania Safari - East Africa Tours' . $suffix,
            'description' => 'Book the ultimate Kenya and Tanzania safari package. Experience Serengeti and Maasai Mara on a combined East Africa wildlife tour. Luxury lodges, great migration viewing, and expert guides.',
            'canonical' => $url . '/kenya-tanzania-safari',
            'ogTitle' => 'Kenya Tanzania Safari - Ultimate East Africa Tour',
            'ogDesc' => 'Combine Kenya and Tanzania on one epic safari. Serengeti, Maasai Mara, great migration, luxury lodges.',
            'h1' => 'Kenya & Tanzania Safari Packages',
            'schema' => 'Tour',
        ],
        'rwanda-gorilla' => [
            'title' => 'Rwanda Gorilla Trekking - Safari Packages' . $suffix,
            'description' => 'Go gorilla trekking in Rwanda with Kizza Tours & Safaris. Volcanoes National Park gorilla permits, luxury lodges, and all-inclusive Rwanda safari packages. Book your gorilla tour today.',
            'canonical' => $url . '/rwanda-gorilla-trekking',
            'ogTitle' => 'Rwanda Gorilla Trekking - Gorilla Safari Tours',
            'ogDesc' => 'Trek mountain gorillas in Volcanoes National Park, Rwanda. Luxury gorilla safari packages with premium lodges.',
            'h1' => 'Rwanda Gorilla Trekking Safaris',
            'schema' => 'Tour',
        ],
        'uganda-tours' => [
            'title' => 'Uganda Tours & Safari Packages' . $suffix,
            'description' => 'Book Uganda safari packages with Kizza Tours & Safaris. Bwindi gorilla trekking, primate safaris, wildlife tours, and luxury Uganda holiday packages. Best Uganda safari company.',
            'canonical' => $url . '/uganda-tours',
            'ogTitle' => 'Uganda Tours & Safari Packages',
            'ogDesc' => 'Discover Uganda - the Pearl of Africa. Gorilla trekking, primate safaris, and wildlife tours with expert guides.',
            'h1' => 'Uganda Tour Packages',
            'schema' => 'Tour',
        ],
        'zanzibar-holidays' => [
            'title' => 'Zanzibar Beach Holidays - Tour Packages' . $suffix,
            'description' => 'Book Zanzibar beach holidays with Kizza Tours & Safaris. Zanzibar honeymoon packages, luxury beach resorts, all-inclusive vacations, and spice tours. Best Zanzibar travel packages.',
            'canonical' => $url . '/zanzibar-holidays',
            'ogTitle' => 'Zanzibar Beach Holidays & Tour Packages',
            'ogDesc' => 'Relax on Zanzibar\'s pristine beaches. Honeymoon packages, luxury resorts, spice tours, and all-inclusive deals.',
            'h1' => 'Zanzibar Beach Holidays',
            'schema' => 'Tour',
        ],
        'burundi-tours' => [
            'title' => 'Burundi Tours & Travel Packages' . $suffix,
            'description' => 'Explore Burundi tour packages with Kizza Tours & Safaris. Burundi wildlife tours, cultural experiences, travel guides, and adventure packages. Visit Burundi with expert guides.',
            'canonical' => $url . '/burundi-tours',
            'ogTitle' => 'Burundi Tours & Travel Packages',
            'ogDesc' => 'Discover Burundi - a hidden gem in East Africa. Wildlife tours, cultural experiences, and adventure travel.',
            'h1' => 'Burundi Tour Packages',
            'schema' => 'Tour',
        ],
        'mount-kenya' => [
            'title' => 'Mount Kenya Climbing - Trekking Tours' . $suffix,
            'description' => 'Climb Mount Kenya with Kizza Tours & Safaris. Sirimon, Chogoria, and Naro Moru routes. Guided Mount Kenya trekking packages. Best routes, expert guides, and all-inclusive climbing tours.',
            'canonical' => $url . '/mount-kenya-climbing',
            'ogTitle' => 'Mount Kenya Climbing & Trekking Packages',
            'ogDesc' => 'Conquer Mount Kenya via Sirimon, Chogoria, or Naro Moru routes. Expert guides, premium equipment.',
            'h1' => 'Mount Kenya Climbing Tours',
            'schema' => 'Tour',
        ],
    ];

    return $pages[$pageKey] ?? $pages['home'];
}

function seoBreadcrumbSchema($items) {
    $itemList = [];
    $position = 1;
    foreach ($items as $item) {
        $itemList[] = [
            '@type' => 'ListItem',
            'position' => $position,
            'name' => $item['name'],
            'item' => $item['url'],
        ];
        $position++;
    }
    return [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $itemList,
    ];
}

function seoFaqSchema($faqs) {
    $items = [];
    foreach ($faqs as $faq) {
        $items[] = [
            '@type' => 'Question',
            'name' => $faq['question'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $faq['answer'],
            ],
        ];
    }
    return [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $items,
    ];
}

function seoOrganizationSchema() {
    $siteName = getSetting('site_name', SITE_NAME);
    $sitePhone = getSetting('site_phone', SITE_PHONE);
    $siteEmail = getSetting('site_email', SITE_EMAIL);
    $ogImage = getMediaUrl('og_image', 'images/log.png');
    return [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => $siteName,
        'url' => SITE_URL,
        'logo' => $ogImage,
        'telephone' => $sitePhone,
        'email' => $siteEmail,
        'address' => [
            '@type' => 'PostalAddress',
            'addressLocality' => 'Arusha',
            'addressCountry' => 'TZ',
        ],
        'sameAs' => [
            getSetting('facebook_url', '#'),
            getSetting('instagram_url', '#'),
            getSetting('twitter_url', '#'),
            getSetting('youtube_url', '#'),
            getSetting('tripadvisor_url', '#'),
        ],
    ];
}

function seoLocalBusinessSchema() {
    $siteName = getSetting('site_name', SITE_NAME);
    $sitePhone = getSetting('site_phone', SITE_PHONE);
    $siteEmail = getSetting('site_email', SITE_EMAIL);
    $addr = getSetting('site_address', SITE_ADDRESS);
    $ogImage = getMediaUrl('og_image', 'images/log.png');
    return [
        '@context' => 'https://schema.org',
        '@type' => 'LocalBusiness',
        'name' => $siteName,
        'description' => 'Discover East Africa Beyond Expectations. Premium luxury safaris, gorilla trekking, Kilimanjaro expeditions, and tailor-made adventures.',
        'url' => SITE_URL,
        'telephone' => $sitePhone,
        'email' => $siteEmail,
        'image' => $ogImage,
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => $addr,
            'addressLocality' => 'Arusha',
            'addressCountry' => 'TZ',
        ],
        'priceRange' => '$$$',
        'openingHours' => 'Mo-Su 08:00-18:00',
        'currenciesAccepted' => 'USD, EUR, GBP, TZS',
        'paymentAccepted' => 'Bank Transfer, Credit Card, Cash',
    ];
}

function seoWebSiteSchema() {
    $siteName = getSetting('site_name', SITE_NAME);
    return [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => $siteName,
        'url' => SITE_URL,
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => SITE_URL . '/?s={search_term_string}',
            'query-input' => 'required name=search_term_string',
        ],
    ];
}

function seoPageMeta($pageKey) {
    $data = seoPageData($pageKey);
    $data['pageKey'] = $pageKey;
    return $data;
}

function seoRenderMetaTags($pageSeo) {
    if (!is_array($pageSeo)) return ''; ?>
    <meta name="description" content="<?php echo htmlspecialchars($pageSeo['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="canonical" href="<?php echo htmlspecialchars($pageSeo['canonical'] ?? SITE_URL, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($pageSeo['ogTitle'] ?? $pageSeo['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($pageSeo['ogDesc'] ?? $pageSeo['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($pageSeo['ogTitle'] ?? $pageSeo['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($pageSeo['ogDesc'] ?? $pageSeo['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo htmlspecialchars($pageSeo['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></title>
<?php }

function seoTouristTripSchema($data) {
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'TouristTrip',
        'name' => $data['name'] ?? '',
    ];
    if (!empty($data['description'])) {
        $schema['description'] = $data['description'];
    }
    if (!empty($data['itinerary'])) {
        $items = [];
        $pos = 1;
        foreach ($data['itinerary'] as $step) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $pos,
                'name' => $step,
            ];
            $pos++;
        }
        $schema['itinerary'] = [
            '@type' => 'ItemList',
            'itemListElement' => $items,
        ];
    }
    if (!empty($data['price'])) {
        $schema['offers'] = [
            '@type' => 'Offer',
            'price' => $data['price'],
            'priceCurrency' => $data['currency'] ?? 'USD',
        ];
    }
    if (!empty($data['duration'])) {
        $schema['duration'] = $data['duration'];
    }
    $schema['provider'] = [
        '@type' => 'TravelAgency',
        'name' => getSetting('site_name', SITE_NAME),
        'url' => SITE_URL,
    ];
    return $schema;
}

function seoRelatedTours($currentCountry = null, $limit = 4) {
    $packages = getTourPackages([], 20);
    if (!empty($packages) && $currentCountry) {
        $filtered = array_filter($packages, function($p) use ($currentCountry) {
            return strtolower($p['country']) !== strtolower($currentCountry);
        });
        if (count($filtered) >= $limit) {
            $packages = array_values($filtered);
        }
    }
    return array_slice($packages, 0, $limit);
}
