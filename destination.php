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
    $dest = $db->fetchOne("SELECT * FROM destinations WHERE slug = ? AND status = 'active' LIMIT 1", [$slug]);
} catch (Exception $e) {
    $dest = null;
}

if (!$dest) {
    $destinations = getDestinations();
    foreach ($destinations as $d) {
        if ($d['slug'] === $slug) {
            $dest = $d;
            break;
        }
    }
}

if (!$dest) {
    header('HTTP/1.0 404 Not Found');
    require_once '404.php';
    exit;
}

$pageSeo = seoPageMeta('home');
$pageSeo['title'] = htmlspecialchars($dest['name']) . ' Safari Tours | Kizza Tours';
$pageSeo['description'] = htmlspecialchars(substr($dest['description'] ?: $dest['short_description'] ?: 'Explore ' . $dest['name'] . ' with Kizza Tours. ' . ($dest['country'] ?? '') . ' safari packages, tours, and travel experiences.', 0, 160));
$pageSeo['canonical'] = SITE_URL . '/destination/' . urlencode($dest['slug']);
$pageSeo['ogTitle'] = htmlspecialchars($dest['name']) . ' - Kizza Tours';
$pageSeo['ogDesc'] = htmlspecialchars(substr($dest['description'] ?: $dest['short_description'] ?: '', 0, 200));
$pageSeo['h1'] = htmlspecialchars($dest['name']);

$siteWhatsapp = getSetting('site_whatsapp', SITE_WHATSAPP);
$sitePhone = getSetting('site_phone', SITE_PHONE);
$countrySlug = strtolower($dest['country'] ?? '');
$tours = getTourPackages(['destination' => $dest['slug']], 6);
if (empty($tours)) {
    $tours = array_filter(getTourPackages([], 10), function($p) use ($countrySlug) {
        return strtolower($p['country'] ?? '') === $countrySlug;
    });
}

$img = !empty($dest['image']) && file_exists(BASE_PATH . $dest['image']) ? SITE_URL . '/' . $dest['image'] : 'assets/images/placeholder.svg';
if (!file_exists(BASE_PATH . ltrim(parse_url($img, PHP_URL_PATH), '/'))) {
    $img = 'assets/images/placeholder.svg';
}

$countryPage = '#destinations';
$heroBgImg = getMediaUrl('hero_poster', '');
$heroBg = $heroBgImg ? "background: linear-gradient(135deg, rgba(10,37,64,0.85) 0%, rgba(13,46,74,0.7) 100%), url('{$heroBgImg}') center/cover no-repeat; padding: 140px 0 80px;" : "background: linear-gradient(135deg, var(--primary) 0%, #0D2E4A 100%); padding: 140px 0 80px;";
?>
<?php include 'includes/header.php'; ?>
<script type="application/ld+json"><?php echo json_encode(seoBreadcrumbSchema([
    ['name' => 'Home', 'url' => SITE_URL . '/'],
    ['name' => 'Destinations', 'url' => SITE_URL . '/#destinations'],
    ['name' => htmlspecialchars($dest['name']), 'url' => SITE_URL . '/destination/' . urlencode($dest['slug'])],
]), JSON_UNESCAPED_SLASHES); ?></script>
<script type="application/ld+json"><?php echo json_encode(seoTouristTripSchema([
    'name' => htmlspecialchars($dest['name']),
    'description' => htmlspecialchars(substr($dest['description'] ?: $dest['short_description'] ?: '', 0, 200)),
]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>

<section class="inner-hero" style="<?= $heroBg ?>">
    <div class="container text-center">
        <span class="section-subtitle"><?php echo htmlspecialchars($dest['country'] ?? ''); ?></span>
        <h1 style="color: var(--white); font-size: clamp(2.5rem, 5vw, 4rem);"><?php echo htmlspecialchars($dest['name']); ?></h1>
        <p style="color: rgba(255,255,255,0.8); max-width: 700px; margin: 1rem auto 0; font-size: 1.1rem;">
            <?php echo htmlspecialchars($dest['description'] ?: $dest['short_description'] ?: ''); ?>
        </p>
    </div>
</section>

<section class="section-padding">
    <div class="container">
        <div class="row align-items-center g-5 mb-5">
            <div class="col-lg-6" data-aos="fade-right">
                <img src="<?php echo $img; ?>" alt="<?php echo htmlspecialchars($dest['name']); ?>" class="img-fluid rounded-4 shadow-lg" onerror="this.src='assets/images/placeholder.svg'">
            </div>
            <div class="col-lg-6" data-aos="fade-left">
                <span class="section-subtitle"><?php echo __('dest_about_subtitle'); ?></span>
                <h2 class="section-title"><?php echo htmlspecialchars($dest['name']); ?></h2>
                <p style="color: var(--text-light); font-size: 1.1rem; line-height: 1.9;">
                    <?php echo htmlspecialchars($dest['description'] ?: $dest['short_description'] ?: ''); ?>
                </p>
                <div class="mt-4 d-flex flex-wrap gap-3">
                    <a href="<?php echo SITE_URL; ?>/book-tour" class="btn btn-premium btn-gold"><i class="fas fa-calendar-check"></i> <?php echo __('dest_book_now'); ?></a>
                    <a href="<?php echo SITE_URL; ?>/<?php echo $countryPage; ?>" class="btn btn-premium btn-outline"><i class="fas fa-globe-africa"></i> <?php echo __('dest_view_country'); ?></a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Tours in this destination -->
<?php if (!empty($tours)): ?>
<section class="section-padding" style="background: var(--off-white);">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-subtitle"><?php echo __('dest_tours_subtitle'); ?></span>
            <h2 class="section-title"><?php echo __('dest_tours_title'); ?> <?php echo htmlspecialchars($dest['name']); ?></h2>
        </div>
        <div class="row g-4">
            <?php foreach ($tours as $tour): 
                $tImg = !empty($tour['image']) && file_exists(BASE_PATH . $tour['image']) ? SITE_URL . '/' . $tour['image'] : 'assets/images/placeholder.svg';
            ?>
            <div class="col-lg-4 col-md-6">
                <div class="package-card" style="height: 100%;">
                    <div class="package-card-image">
                        <img src="<?php echo $tImg; ?>" alt="<?php echo htmlspecialchars($tour['title'] ?? ''); ?>" loading="lazy" onerror="this.src='assets/images/placeholder.svg'">
                    </div>
                    <div class="package-card-body">
                        <div class="package-card-meta">
                            <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($tour['duration'] ?: 'N/A'); ?></span>
                            <span>$<?php echo number_format($tour['price'] ?? 0, 0); ?>/person</span>
                        </div>
                        <h3 class="package-card-title" style="font-size: 1.1rem;"><?php echo htmlspecialchars($tour['title'] ?? ''); ?></h3>
                        <p style="color: var(--text-light); font-size: 0.85rem;"><?php echo htmlspecialchars(substr($tour['description'] ?? '', 0, 100)); ?>...</p>
                        <a href="<?php echo SITE_URL; ?>/safari/<?php echo htmlspecialchars($tour['slug'] ?? ''); ?>" class="btn btn-premium btn-outline-gold btn-sm w-100 mb-2"><?php echo __('dest_view_details'); ?></a>
                        <a href="<?php echo SITE_URL; ?>/book-tour" class="btn btn-premium btn-gold btn-sm w-100"><i class="fas fa-calendar-check"></i> <?php echo __('dest_book'); ?></a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-5">
            <a href="<?php echo SITE_URL; ?>/<?php echo $countryPage; ?>" class="btn btn-premium btn-outline-gold btn-lg"><?php echo __('dest_all_tours'); ?></a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- FAQ Section -->
<?php $destFaqs = getFAQs(6); ?>
<?php if (!empty($destFaqs)): ?>
<script type="application/ld+json"><?php echo json_encode(seoFaqSchema(array_map(function($f) {
    return ['question' => $f['question'], 'answer' => $f['answer']];
}, $destFaqs)), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
<section class="section-padding" style="background: var(--off-white);">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-subtitle"><?php echo __('faq_subtitle'); ?></span>
            <h2 class="section-title"><?php echo htmlspecialchars($dest['name']); ?> FAQs</h2>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-8" data-aos="fade-up">
                <div class="accordion faq-accordion" id="faqAccordion">
                    <?php foreach ($destFaqs as $idx => $faq): ?>
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
