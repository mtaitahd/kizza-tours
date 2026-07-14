<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header('Location: ' . SITE_URL);
    exit;
}

try {
    $db = Database::getInstance();
    $tour = $db->fetchOne("SELECT p.*, d.name as destination_name, d.country as destination_country FROM tour_packages p LEFT JOIN destinations d ON p.destination_id = d.id WHERE p.slug = ? AND p.status = 'active' LIMIT 1", [$slug]);
} catch (Exception $e) {
    $tour = null;
}

if (!$tour) {
    header('HTTP/1.0 404 Not Found');
    require_once '404.php';
    exit;
}

$pageSeo = seoPageMeta($tour['country'] ? strtolower($tour['country']) . '-safari' : 'home');
$pageSeo['title'] = !empty($tour['meta_title']) ? htmlspecialchars($tour['meta_title']) : htmlspecialchars($tour['title']) . ' | Kizza Tours';
$pageSeo['description'] = !empty($tour['meta_description']) ? htmlspecialchars($tour['meta_description']) : htmlspecialchars(substr($tour['description'] ?? 'Book ' . $tour['title'] . ' with Kizza Tours & Safaris. ' . ($tour['duration'] ?? '') . ' package from $' . number_format($tour['price'] ?? 0, 0) . '.', 0, 160));
$pageSeo['canonical'] = SITE_URL . '/safari/' . urlencode($tour['slug']);
$pageSeo['ogTitle'] = !empty($tour['meta_title']) ? htmlspecialchars($tour['meta_title']) : htmlspecialchars($tour['title']) . ' - Kizza Tours';
$pageSeo['ogDesc'] = !empty($tour['meta_description']) ? htmlspecialchars(substr($tour['meta_description'], 0, 200)) : htmlspecialchars(substr($tour['description'] ?? '', 0, 200));
$pageSeo['h1'] = htmlspecialchars($tour['title']);
if (!empty($tour['meta_keywords'])) {
    $pageSeo['keywords'] = htmlspecialchars($tour['meta_keywords']);
}
if (!empty($tour['no_robots'])) {
    $pageSeo['robots'] = 'noindex, follow';
}

$siteWhatsapp = getSetting('site_whatsapp', SITE_WHATSAPP);
$sitePhone = getSetting('site_phone', SITE_PHONE);
$countrySlug = strtolower($tour['country'] ?? '');
$relatedTours = getTourPackages([], 4);

$img = !empty($tour['image']) && file_exists(BASE_PATH . $tour['image']) ? SITE_URL . '/' . $tour['image'] : '';
if (empty($img)) {
    $fallback = 'assets/images/placeholder.svg';
    $img = file_exists(BASE_PATH . $fallback) ? SITE_URL . '/' . $fallback : 'assets/images/placeholder.svg';
}

$highlightsArr = array_filter(array_map('trim', explode(',', $tour['highlights'] ?? '')));
$includesArr = array_filter(array_map('trim', explode(',', $tour['includes'] ?? '')));
$excludesArr = array_filter(array_map('trim', explode(',', $tour['excludes'] ?? '')));
$itineraryLines = array_filter(array_map('trim', explode("\n", $tour['itinerary'] ?? '')));

$countryPageMap = [
    'tanzania' => 'tanzania-safari',
    'kenya' => 'kenya-tanzania-safari',
    'uganda' => 'uganda-tours',
    'rwanda' => 'rwanda-gorilla-trekking',
    'zanzibar' => 'zanzibar-holidays',
    'burundi' => 'burundi-tours',
];
$countryPage = $countryPageMap[$countrySlug] ?? 'tanzania-safari';
?>
<?php include 'includes/header.php'; ?>
<script type="application/ld+json"><?php echo json_encode(seoBreadcrumbSchema([
    ['name' => 'Home', 'url' => SITE_URL . '/'],
    ['name' => htmlspecialchars($tour['country'] ?? '') . ' Safaris', 'url' => SITE_URL . '/' . $countryPage],
    ['name' => htmlspecialchars($tour['title']), 'url' => SITE_URL . '/safari/' . urlencode($tour['slug'])],
]), JSON_UNESCAPED_SLASHES); ?></script>
<script type="application/ld+json"><?php echo json_encode(seoTouristTripSchema([
    'name' => htmlspecialchars($tour['title']),
    'description' => htmlspecialchars(substr($tour['description'] ?? '', 0, 200)),
    'price' => $tour['price'] ?? null,
    'currency' => 'USD',
    'duration' => !empty($tour['duration']) ? 'P' . preg_replace('/[^0-9]/', '', $tour['duration']) . 'D' : null,
    'itinerary' => $itineraryLines ? array_slice(array_values($itineraryLines), 0, 10) : [],
]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>

<?php
$heroBgCandidates = [
    !empty($tour['hero_image']) ? $tour['hero_image'] : null,
    !empty($tour['image']) ? $tour['image'] : null,
];
$heroBgUrl = '';
foreach ($heroBgCandidates as $candidate) {
    if ($candidate && file_exists(BASE_PATH . $candidate)) {
        $heroBgUrl = SITE_URL . '/' . htmlspecialchars($candidate);
        break;
    }
}
$tourHeroBg = $heroBgUrl
    ? 'background: linear-gradient(rgba(10,37,64,0.7), rgba(10,37,64,0.7)), url(' . $heroBgUrl . ') center/cover no-repeat;'
    : 'background: linear-gradient(135deg, var(--primary) 0%, #0D2E4A 100%);';
?>
<section class="inner-hero" style="<?= $tourHeroBg ?> padding: 140px 0 80px;">
    <div class="container text-center">
        <span class="section-subtitle"><?php echo htmlspecialchars($tour['country'] ?? ''); ?> Safari</span>
        <h1><?php echo htmlspecialchars($tour['title']); ?></h1>
        <p style="max-width: 700px; margin: 1rem auto 0;">
            <?php echo htmlspecialchars($tour['duration'] ?? ''); ?> &bull; From $<?php echo number_format($tour['price'] ?? 0, 0); ?> per person
        </p>
    </div>
</section>

<section class="section-padding">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-8">
                <img src="<?php echo $img; ?>" alt="<?php echo htmlspecialchars($tour['title']); ?>" class="img-fluid rounded-4 shadow-lg mb-4 w-100" style="max-height: 450px; object-fit: cover;" onerror="this.src='assets/images/placeholder.svg'">
                
                <?php
                $galleryImages = array_filter(array_map('trim', explode(',', $tour['gallery'] ?? '')));
                if (!empty($galleryImages)): ?>
                <div class="row g-2 mb-4">
                    <?php foreach ($galleryImages as $gi):
                        $giPath = file_exists(BASE_PATH . $gi) ? SITE_URL . '/' . $gi : '';
                        if (empty($giPath)) continue;
                    ?>
                    <div class="col-4 col-md-3">
                        <a href="<?php echo $giPath; ?>" data-lightbox="tour-gallery" data-title="<?php echo htmlspecialchars($tour['title']); ?>">
                            <img src="<?php echo $giPath; ?>" alt="" class="img-fluid rounded-3 w-100" style="height: 120px; object-fit: cover;" loading="lazy">
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div class="d-flex flex-wrap gap-3 mb-4">
                    <span class="badge bg-gold text-dark px-3 py-2"><i class="fas fa-clock me-1"></i> <?php echo htmlspecialchars($tour['duration'] ?? 'N/A'); ?></span>
                    <span class="badge bg-primary px-3 py-2"><i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($tour['country'] ?? ''); ?></span>
                    <span class="badge bg-success px-3 py-2"><i class="fas fa-tag me-1"></i> $<?php echo number_format($tour['price'] ?? 0, 0); ?>/person</span>
                </div>

                <h2><?php echo __('tour_details_overview'); ?></h2>
                <p style="color: var(--text-light); font-size: 1.05rem; line-height: 1.8;"><?php echo nl2br(htmlspecialchars($tour['description'] ?? '')); ?></p>

                <?php if (!empty($highlightsArr)): ?>
                <h3 class="mt-4"><?php echo __('tour_details_highlights'); ?></h3>
                <div class="row g-2 mt-2">
                    <?php foreach ($highlightsArr as $hl): ?>
                    <div class="col-md-6">
                        <div class="d-flex align-items-center gap-2 p-2">
                            <i class="fas fa-check-circle" style="color: var(--secondary);"></i>
                            <span><?php echo htmlspecialchars($hl); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($itineraryLines)): ?>
                <h3 class="mt-4"><?php echo __('tour_details_itinerary'); ?></h3>
                <div class="mt-3">
                    <?php foreach ($itineraryLines as $line): ?>
                    <div class="d-flex align-items-start gap-3 mb-2 p-2" style="border-left: 3px solid var(--secondary);">
                        <i class="fas fa-route mt-1" style="color: var(--secondary);"></i>
                        <span><?php echo htmlspecialchars($line); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm border-0 rounded-0 p-4 sticky-top" style="top: 100px;">
                    <h4 class="mb-3"><?php echo __('tour_details_price'); ?></h4>
                    <div class="mb-3">
                        <span style="font-size: 2rem; font-weight: 700; color: var(--secondary);">$<?php echo number_format($tour['price'] ?? 0, 0); ?></span>
                        <small class="text-muted"> / <?php echo __('pkg_per_person'); ?></small>
                    </div>
                    <a href="<?php echo SITE_URL; ?>/book-tour" class="btn btn-premium btn-gold btn-lg w-100 mb-3">
                        <i class="fas fa-calendar-check"></i> <?php echo __('tour_details_book_now'); ?>
                    </a>
                    <a href="https://wa.me/<?php echo $siteWhatsapp; ?>" class="btn btn-premium btn-outline-success btn-lg w-100 mb-4" target="_blank">
                        <i class="fab fa-whatsapp"></i> <?php echo __('tour_details_chat'); ?>
                    </a>

                    <?php if (!empty($includesArr)): ?>
                    <h5 class="mt-3"><?php echo __('tour_details_includes'); ?></h5>
                    <ul class="list-unstyled">
                        <?php foreach ($includesArr as $inc): ?>
                        <li class="mb-1"><i class="fas fa-check text-success me-2"></i> <?php echo htmlspecialchars($inc); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>

                    <?php if (!empty($excludesArr)): ?>
                    <h5 class="mt-3"><?php echo __('tour_details_excludes'); ?></h5>
                    <ul class="list-unstyled">
                        <?php foreach ($excludesArr as $exc): ?>
                        <li class="mb-1"><i class="fas fa-times text-danger me-2"></i> <?php echo htmlspecialchars($exc); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>

                    <hr>
                    <a href="<?php echo SITE_URL; ?>/<?php echo $countryPage; ?>" class="btn btn-outline-gold w-100">
                        <i class="fas fa-arrow-left"></i> <?php echo __('tour_details_view_all'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Related Tours -->
<?php if (!empty($relatedTours)): ?>
<section class="section-padding" style="background: var(--off-white);">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-subtitle"><?php echo __('tour_details_related_subtitle'); ?></span>
            <h2 class="section-title"><?php echo __('tour_details_related_title'); ?></h2>
        </div>
        <div class="row g-4">
            <?php foreach ($relatedTours as $rt): if (($rt['slug'] ?? '') === $slug) continue; ?>
            <div class="col-lg-3 col-md-6">
                <div class="package-card" style="height: 100%;">
                    <div class="package-card-image">
                        <?php 
                        $rImg = !empty($rt['image']) && file_exists(BASE_PATH . $rt['image']) ? SITE_URL . '/' . $rt['image'] : 'assets/images/placeholder.svg';
                        ?>
                        <img src="<?php echo $rImg; ?>" alt="<?php echo htmlspecialchars($rt['title'] ?? ''); ?>" loading="lazy" onerror="this.src='assets/images/placeholder.svg'">
                    </div>
                    <div class="package-card-body">
                        <h3 class="package-card-title" style="font-size: 1rem;"><?php echo htmlspecialchars($rt['title'] ?? ''); ?></h3>
                        <p style="color: var(--text-light); font-size: 0.85rem;"><?php echo htmlspecialchars(substr($rt['description'] ?? '', 0, 80)); ?>...</p>
                        <a href="<?php echo SITE_URL; ?>/safari/<?php echo htmlspecialchars($rt['slug'] ?? ''); ?>" class="btn btn-premium btn-outline-gold btn-sm w-100"><?php echo __('tour_details_view'); ?></a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- FAQ Section -->
<?php $tourFaqs = getFAQs(6); ?>
<?php if (!empty($tourFaqs)): ?>
<script type="application/ld+json"><?php echo json_encode(seoFaqSchema(array_map(function($f) {
    return ['question' => $f['question'], 'answer' => $f['answer']];
}, $tourFaqs)), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
<section class="section-padding" style="background: var(--off-white);">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-subtitle"><?php echo __('faq_subtitle'); ?></span>
            <h2 class="section-title"><?php echo __('tour_details_overview'); ?> FAQs</h2>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-8" data-aos="fade-up">
                <div class="accordion faq-accordion" id="faqAccordion">
                    <?php foreach ($tourFaqs as $idx => $faq): ?>
                    <div class="accordion-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button <?php echo $idx > 0 ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?php echo $faq['id']; ?>">
                                <?php echo htmlspecialchars($faq['question']); ?>
                            </button>
                        </h3>
                        <div id="faq<?php echo $faq['id']; ?>" class="accordion-collapse collapse <?php echo $idx === 0 ? 'show' : ''; ?>" data-bs-parent="#faqAccordion">
                            <div class="accordion-body"><?php echo nl2br(htmlspecialchars($faq['answer'])); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
