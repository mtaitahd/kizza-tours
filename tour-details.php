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
} else {
    $pageSeo['robots'] = 'index, follow';
}

$relatedTours = getTourPackages([], 4);

$highlightsArr = tourListItems($tour['highlights'] ?? '');
$includesArr = tourListItems($tour['includes'] ?? '');
$excludesArr = tourListItems($tour['excludes'] ?? '');
$itineraryLines = array_filter(array_map('trim', explode("\n", $tour['itinerary'] ?? '')));
$itineraryDays = getItineraryDays($tour['id'] ?? 0);

$countryPage = '#destinations';
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
    'duration' => !empty($tour['duration']) && preg_match('/(\d+)/', $tour['duration'], $m) ? 'P' . $m[1] . 'D' : null,
    'itinerary' => !empty($itineraryDays)
        ? array_slice(array_values(array_column($itineraryDays, 'title')), 0, 10)
        : ($itineraryLines ? array_slice(array_values($itineraryLines), 0, 10) : []),
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
        <?php include 'includes/tour-overview.php'; ?>
    </div>
</section>

<!-- FAQ Section (after overview, before itinerary) -->
<?php
$tourFaqs = getFAQsByTour($tour['id'] ?? 0);
$seenQuestions = [];
$tourFaqs = array_filter($tourFaqs, function($f) use (&$seenQuestions) {
    $key = strtolower(trim($f['question']));
    if (isset($seenQuestions[$key])) return false;
    $seenQuestions[$key] = true;
    return true;
});
$tourFaqs = array_values($tourFaqs);
$faqs = $tourFaqs;
?>
<?php if (!empty($tourFaqs)): ?>
<script type="application/ld+json"><?php echo json_encode(seoFaqSchema(array_map(function($f) {
    return ['question' => $f['question'], 'answer' => $f['answer']];
}, $tourFaqs)), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
<section class="section-padding" style="background: var(--off-white);" id="faq-section">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <h2 class="section-title"><?php echo __('faq_title'); ?></h2>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-10" data-aos="fade-up">
                <div class="faq-accordion" id="faqAccordion">
                    <?php $n = 'faq'; include __DIR__ . '/includes/faq-accordion.php'; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Tour Itinerary (full-width editorial section) -->
<?php if (!empty($itineraryDays) || !empty($itineraryLines)): ?>
<section class="tour-itinerary-section" id="tour-itinerary" data-aos="fade-up">
    <div class="tour-itinerary-inner">
        <header class="tour-itinerary-header">
            <div class="tour-itinerary-eyebrow">
                <span>YOUR JOURNEY</span>
                <span class="tour-itinerary-eyebrow-line"></span>
            </div>
            <h2 class="tour-itinerary-title"><?php echo __('tour_details_itinerary'); ?></h2>
        </header>

        <?php if (!empty($itineraryDays)): ?>
            <?php include 'includes/itinerary-days.php'; ?>
        <?php else: ?>
            <div class="itinerary-legacy">
                <?php foreach ($itineraryLines as $line): ?>
                <p><?php echo htmlspecialchars($line); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- Highlights -->
<?php if (!empty($highlightsArr)): ?>
<section class="section-padding tour-highlights-section" id="tour-highlights" data-aos="fade-up">
    <div class="container">
        <div class="text-center mb-5">
            <span class="section-subtitle"><?php echo __('tour_details_highlights_subtitle'); ?></span>
            <h2 class="section-title"><?php echo __('tour_details_highlights'); ?></h2>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="tour-highlights-grid">
                    <?php foreach ($highlightsArr as $hl): ?>
                    <div class="tour-highlight-card">
                        <div class="tour-highlight-card__icon"><i class="fas fa-check-circle" aria-hidden="true"></i></div>
                        <div class="tour-highlight-card__text">
                            <span class="tour-list__text"><?php echo htmlspecialchars($hl); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Includes & Excludes -->
<?php if (!empty($includesArr) || !empty($excludesArr)): ?>
<section class="section-padding tour-inc-exc-section" style="background: var(--off-white);" id="tour-includes-excludes" data-aos="fade-up">
    <div class="container">
        <div class="text-center mb-5">
            <span class="section-subtitle"><?php echo __('tour_details_inc_exc_subtitle'); ?></span>
            <h2 class="section-title"><?php echo __('tour_details_inc_exc_title'); ?></h2>
        </div>
        <div class="row g-4">
            <?php if (!empty($includesArr)): ?>
            <div class="<?php echo (!empty($excludesArr)) ? 'col-lg-6' : 'col-lg-8 mx-auto'; ?>">
                <div class="tour-inc-exc-card tour-inc-exc-card--include">
                    <div class="tour-inc-exc-card__header">
                        <i class="fas fa-check-circle"></i>
                        <h3><?php echo __('tour_details_includes'); ?></h3>
                    </div>
                    <ul class="tour-list tour-list--includes">
                        <?php foreach ($includesArr as $inc): ?>
                        <li class="tour-list__item">
                            <i class="fas fa-check tour-list__icon tour-list__icon--include" aria-hidden="true"></i>
                            <span class="tour-list__text"><?php echo htmlspecialchars($inc); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($excludesArr)): ?>
            <div class="<?php echo (!empty($includesArr)) ? 'col-lg-6' : 'col-lg-8 mx-auto'; ?>">
                <div class="tour-inc-exc-card tour-inc-exc-card--exclude">
                    <div class="tour-inc-exc-card__header">
                        <i class="fas fa-times-circle"></i>
                        <h3><?php echo __('tour_details_excludes'); ?></h3>
                    </div>
                    <ul class="tour-list tour-list--excludes">
                        <?php foreach ($excludesArr as $exc): ?>
                        <li class="tour-list__item">
                            <i class="fas fa-times tour-list__icon tour-list__icon--exclude" aria-hidden="true"></i>
                            <span class="tour-list__text"><?php echo htmlspecialchars($exc); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Related Tours -->
<?php if (!empty($relatedTours)): ?>
<section class="section-padding tour-details-related" style="background: var(--off-white);">
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
                        <h3 class="package-card-title"><?php echo htmlspecialchars($rt['title'] ?? ''); ?></h3>
                        <p class="tour-details-related__desc"><?php echo htmlspecialchars($rt['description'] ?? ''); ?></p>
                        <a href="<?php echo SITE_URL; ?>/safari/<?php echo htmlspecialchars($rt['slug'] ?? ''); ?>" class="btn btn-premium btn-outline-gold btn-sm w-100"><?php echo __('tour_details_view'); ?></a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
