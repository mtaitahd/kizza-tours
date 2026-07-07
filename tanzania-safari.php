<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
$pageSeo = seoPageMeta('tanzania-safari');
$siteWhatsapp = getSetting('site_whatsapp', SITE_WHATSAPP);
$sitePhone = getSetting('site_phone', SITE_PHONE);
$packages = getTourPackages(['destination' => 'tanzania'], 6);
if (empty($packages)) {
    $packages = array_filter(getTourPackages([], 10), function($p) {
        return in_array(strtolower($p['country']), ['tanzania', 'zanzibar']);
    });
}
$faqs = [
    ['question' => __('tour_tz_faq_q1'), 'answer' => __('tour_tz_faq_a1')],
    ['question' => __('tour_tz_faq_q2'), 'answer' => __('tour_tz_faq_a2')],
    ['question' => __('tour_tz_faq_q3'), 'answer' => __('tour_tz_faq_a3')],
    ['question' => __('tour_tz_faq_q4'), 'answer' => __('tour_tz_faq_a4')],
    ['question' => __('tour_tz_faq_q5'), 'answer' => __('tour_tz_faq_a5')],
    ['question' => __('tour_tz_faq_q6'), 'answer' => __('tour_tz_faq_a6')],
];
?>
<?php include 'includes/header.php'; ?>
<script type="application/ld+json"><?php echo json_encode(seoBreadcrumbSchema([
    ['name' => 'Home', 'url' => SITE_URL . '/'],
    ['name' => 'Tanzania Safari', 'url' => SITE_URL . '/tanzania-safari'],
]), JSON_UNESCAPED_SLASHES); ?></script>
<script type="application/ld+json"><?php echo json_encode(seoFaqSchema($faqs), JSON_UNESCAPED_SLASHES); ?></script>

<section class="inner-hero" style="background: linear-gradient(135deg, var(--primary) 0%, #0D2E4A 100%); padding: 140px 0 80px;">
    <div class="container text-center">
        <span class="section-subtitle"><?php echo __('tour_tz_subtitle'); ?></span>
        <h1 style="color: var(--white); font-size: clamp(2.5rem, 5vw, 4rem);"><?php echo __('tour_tz_title'); ?></h1>
        <p style="color: rgba(255,255,255,0.8); max-width: 700px; margin: 1rem auto 0; font-size: 1.1rem;">
            <?php echo __('tour_tz_desc'); ?>
        </p>
    </div>
</section>

<section class="section-padding">
    <div class="container">
        <div class="row align-items-center g-5 mb-5">
            <div class="col-lg-6" data-aos="fade-right">
                <span class="section-subtitle"><?php echo __('tour_tz_why_subtitle'); ?></span>
                <h2 class="section-title"><?php echo __('tour_tz_why_title'); ?></h2>
                <p style="color: var(--text-light); font-size: 1.1rem; line-height: 1.9;"><?php echo __('tour_tz_why_desc_1'); ?></p>
                <p style="color: var(--text-light); font-size: 1.1rem; line-height: 1.9;"><?php echo __('tour_tz_why_desc_2'); ?></p>
                <div class="mt-4">
                    <a href="#packages" class="btn btn-premium btn-gold"><i class="fas fa-safari"></i> <?php echo __('tour_tz_view_packages'); ?></a>
                    <a href="<?php echo SITE_URL; ?>/book-tour" class="btn btn-premium btn-outline-gold ms-2"><i class="fas fa-calendar-check"></i> <?php echo __('tour_tz_book_now'); ?></a>
                </div>
            </div>
            <div class="col-lg-6" data-aos="fade-left">
                <img src="<?php echo getMediaUrl('tanzania_safari_image', 'assets/images/placeholder.svg'); ?>" alt="Tanzania Safari - Serengeti Wildlife Experience" class="img-fluid rounded-4 shadow-lg" loading="lazy" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%27800%27 height=%27600%27%3E%3Crect width=%27800%27 height=%27600%27 fill=%27%230A2540%27/%3E%3Ctext x=%27400%27 y=%27300%27 text-anchor=%27middle%27 fill=%27%23D4AF37%27 font-size=%2724%27 font-family=%27sans-serif%27%3ETanzania+Safari%3C/text%3E%3C/svg%3E'">
            </div>
        </div>

        <div class="row g-4 mb-5" id="packages">
            <div class="col-12 text-center" data-aos="fade-up">
                <span class="section-subtitle"><?php echo __('tour_tz_packages_subtitle'); ?></span>
                <h2 class="section-title"><?php echo __('tour_tz_packages_title'); ?></h2>
            </div>
            <?php if (!empty($packages)): ?>
                <?php foreach ($packages as $i => $pkg): ?>
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?php echo $i * 100; ?>">
                    <div class="package-card">
                        <div class="package-card-image">
                        <?php
                            $pkgImg = '';
                            $pt = $pkg['title'] ?? '';
                            if (!empty($pkg['image']) && file_exists(BASE_PATH . $pkg['image'])) {
                                $pkgImg = SITE_URL . '/' . $pkg['image'];
                            }
                            if (empty($pkgImg)) {
                                $imgKey = '';
                                if (stripos($pt, 'maasai mara') !== false) $imgKey = 'maasai_mara_image';
                                elseif (stripos($pt, 'uganda gorilla trekking') !== false) $imgKey = 'uganda_gorilla_adventure_image';
                                elseif (stripos($pt, 'rwanda luxury gorilla') !== false) $imgKey = 'rwanda_luxury_gorilla_image';
                                elseif (stripos($pt, 'amboseli') !== false) $imgKey = 'amboseli_kilimanjaro_image';
                                if ($imgKey) $pkgImg = getMediaUrl($imgKey, '');
                            }
                            if (empty($pkgImg)) {
                                $cs = strtolower($pkg['country'] ?? '');
                                if ($cs) $pkgImg = 'assets/images/placeholder.svg';
                            }
                            if (empty($pkgImg)) $pkgImg = 'assets/images/placeholder.svg';
                        ?>
                            <img src="<?php echo $pkgImg; ?>" alt="<?php echo htmlspecialchars($pt); ?>" loading="lazy" onerror="this.src='assets/images/placeholder.svg'">
                        </div>
                        <div class="package-card-body">
                            <div class="package-card-meta">
                                <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($pkg['duration'] ?: __('tour_tz_na')); ?></span>
                                <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($pkg['country']); ?></span>
                            </div>
                            <h3 class="package-card-title"><?php echo htmlspecialchars($pkg['title']); ?></h3>
                            <p style="color: var(--text-light); font-size: 0.9rem;"><?php echo htmlspecialchars(substr($pkg['description'] ?? '', 0, 120)) . '...'; ?></p>
                            <div class="package-card-price">$<?php echo number_format($pkg['price'], 0); ?> <small><?php echo __('tour_tz_per_person'); ?></small></div>
                            <div class="package-card-actions mt-3">
                                <a href="<?php echo SITE_URL; ?>/book-tour" class="btn btn-premium btn-gold btn-sm w-100"><i class="fas fa-calendar-check"></i> <?php echo __('tour_tz_book_now'); ?></a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-md-4" data-aos="fade-up">
                    <div class="package-card">
                        <div class="package-card-image"><img src="assets/images/placeholder.svg" alt="<?php echo __('tour_tz_fallback_1_title'); ?>" loading="lazy"></div>
                        <div class="package-card-body">
                            <div class="package-card-meta"><span><i class="fas fa-clock"></i> <?php echo __('tour_tz_fallback_1_duration'); ?></span><span><i class="fas fa-map-marker-alt"></i> <?php echo __('tour_tz_fallback_1_country'); ?></span></div>
                            <h3 class="package-card-title"><?php echo __('tour_tz_fallback_1_title'); ?></h3>
                            <p style="color: var(--text-light); font-size: 0.9rem;"><?php echo __('tour_tz_fallback_1_desc'); ?></p>
                            <div class="package-card-price"><?php echo __('tour_tz_fallback_1_price'); ?> <small><?php echo __('tour_tz_per_person'); ?></small></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="package-card">
                        <div class="package-card-image"><img src="assets/images/placeholder.svg" alt="<?php echo __('tour_tz_fallback_2_title'); ?>" loading="lazy"></div>
                        <div class="package-card-body">
                            <div class="package-card-meta"><span><i class="fas fa-clock"></i> <?php echo __('tour_tz_fallback_2_duration'); ?></span><span><i class="fas fa-map-marker-alt"></i> <?php echo __('tour_tz_fallback_2_country'); ?></span></div>
                            <h3 class="package-card-title"><?php echo __('tour_tz_fallback_2_title'); ?></h3>
                            <p style="color: var(--text-light); font-size: 0.9rem;"><?php echo __('tour_tz_fallback_2_desc'); ?></p>
                            <div class="package-card-price"><?php echo __('tour_tz_fallback_2_price'); ?> <small><?php echo __('tour_tz_per_person'); ?></small></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="package-card">
                        <div class="package-card-image"><img src="assets/images/placeholder.svg" alt="<?php echo __('tour_tz_fallback_3_title'); ?>" loading="lazy"></div>
                        <div class="package-card-body">
                            <div class="package-card-meta"><span><i class="fas fa-clock"></i> <?php echo __('tour_tz_fallback_3_duration'); ?></span><span><i class="fas fa-map-marker-alt"></i> <?php echo __('tour_tz_fallback_3_country'); ?></span></div>
                            <h3 class="package-card-title"><?php echo __('tour_tz_fallback_3_title'); ?></h3>
                            <p style="color: var(--text-light); font-size: 0.9rem;"><?php echo __('tour_tz_fallback_3_desc'); ?></p>
                            <div class="package-card-price"><?php echo __('tour_tz_fallback_3_price'); ?> <small><?php echo __('tour_tz_per_person'); ?></small></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="section-padding" style="background: var(--off-white);">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-subtitle"><?php echo __('tour_tz_dest_subtitle'); ?></span>
            <h2 class="section-title"><?php echo __('tour_tz_dest_title'); ?></h2>
        </div>
        <div class="row g-4">
            <div class="col-md-6 col-lg-4" data-aos="fade-up">
                <div class="story-card text-center">
                    <div class="story-card-icon" style="margin: 0 auto 1rem;"><i class="fas fa-tree"></i></div>
                    <h3><?php echo __('tour_tz_dest_1_title'); ?></h3>
                    <p><?php echo __('tour_tz_dest_1_desc'); ?></p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="50">
                <div class="story-card text-center">
                    <div class="story-card-icon" style="margin: 0 auto 1rem;"><i class="fas fa-mountain"></i></div>
                    <h3><?php echo __('tour_tz_dest_2_title'); ?></h3>
                    <p><?php echo __('tour_tz_dest_2_desc'); ?></p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="100">
                <div class="story-card text-center">
                    <div class="story-card-icon" style="margin: 0 auto 1rem;"><i class="fas fa-water"></i></div>
                    <h3><?php echo __('tour_tz_dest_3_title'); ?></h3>
                    <p><?php echo __('tour_tz_dest_3_desc'); ?></p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="150">
                <div class="story-card text-center">
                    <div class="story-card-icon" style="margin: 0 auto 1rem;"><i class="fas fa-sun"></i></div>
                    <h3><?php echo __('tour_tz_dest_4_title'); ?></h3>
                    <p><?php echo __('tour_tz_dest_4_desc'); ?></p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="200">
                <div class="story-card text-center">
                    <div class="story-card-icon" style="margin: 0 auto 1rem;"><i class="fas fa-fire"></i></div>
                    <h3><?php echo __('tour_tz_dest_5_title'); ?></h3>
                    <p><?php echo __('tour_tz_dest_5_desc'); ?></p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="250">
                <div class="story-card text-center">
                    <div class="story-card-icon" style="margin: 0 auto 1rem;"><i class="fas fa-umbrella-beach"></i></div>
                    <h3><?php echo __('tour_tz_dest_6_title'); ?></h3>
                    <p><?php echo __('tour_tz_dest_6_desc'); ?></p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-padding" style="background: var(--off-white);">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-subtitle"><?php echo __('tour_tz_faq_subtitle'); ?></span>
            <h2 class="section-title"><?php echo __('tour_tz_faq_title'); ?></h2>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-8" data-aos="fade-up">
                <div class="accordion faq-accordion" id="faqAccordion">
                    <?php foreach ($faqs as $idx => $faq): ?>
                    <div class="accordion-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button <?php echo $idx > 0 ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?php echo $idx; ?>"><?php echo htmlspecialchars($faq['question']); ?></button>
                        </h3>
                        <div id="faq<?php echo $idx; ?>" class="accordion-collapse collapse <?php echo $idx === 0 ? 'show' : ''; ?>" data-bs-parent="#faqAccordion">
                            <div class="accordion-body"><?php echo htmlspecialchars($faq['answer']); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-padding">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-subtitle"><?php echo __('tour_tz_related_subtitle'); ?></span>
            <h2 class="section-title"><?php echo __('tour_tz_related_title'); ?></h2>
        </div>
        <div class="row g-4">
            <?php $related = seoRelatedTours('Tanzania', 4); ?>
            <?php foreach ($related as $r): ?>
            <div class="col-md-6 col-lg-3" data-aos="fade-up">
                <div class="package-card">
                    <div class="package-card-image">
                        <?php
                            $rImg = '';
                            $rt = $r['title'] ?? '';
                            if (!empty($r['image']) && file_exists(BASE_PATH . $r['image'])) {
                                $rImg = SITE_URL . '/' . $r['image'];
                            }
                            if (empty($rImg)) {
                                $ik = '';
                                if (stripos($rt, 'maasai mara') !== false) $ik = 'maasai_mara_image';
                                elseif (stripos($rt, 'uganda gorilla trekking') !== false) $ik = 'uganda_gorilla_adventure_image';
                                elseif (stripos($rt, 'rwanda luxury gorilla') !== false) $ik = 'rwanda_luxury_gorilla_image';
                                elseif (stripos($rt, 'amboseli') !== false) $ik = 'amboseli_kilimanjaro_image';
                                if ($ik) $rImg = getMediaUrl($ik, '');
                            }
                            if (empty($rImg)) {
                                $cs = strtolower($r['country'] ?? '');
                                if ($cs) $rImg = 'assets/images/placeholder.svg';
                            }
                            if (empty($rImg)) $rImg = 'assets/images/placeholder.svg';
                        ?>
                        <img src="<?php echo $rImg; ?>" alt="<?php echo htmlspecialchars($rt); ?>" loading="lazy" onerror="this.src='assets/images/placeholder.svg'">
                    </div>
                    <div class="package-card-body">
                        <h3 class="package-card-title" style="font-size: 1.1rem;"><?php echo htmlspecialchars($r['title']); ?></h3>
                        <p style="color: var(--text-light); font-size: 0.85rem;"><?php echo htmlspecialchars(substr($r['description'] ?? '', 0, 80)) . '...'; ?></p>
                        <a href="<?php echo SITE_URL; ?>/safari/<?php echo htmlspecialchars($r['slug'] ?? ''); ?>" class="btn btn-premium btn-outline-gold btn-sm w-100"><?php echo __('tour_tz_view_details'); ?></a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="cta-section" style="position: relative; padding: 100px 0; background: linear-gradient(135deg, var(--primary) 0%, #0D2E4A 100%); text-align: center;">
    <div class="container">
        <div class="cta-content">
            <span class="section-subtitle"><?php echo __('tour_tz_cta_subtitle'); ?></span>
            <h2 style="color: var(--white);"><?php echo __('tour_tz_cta_title'); ?></h2>
            <p style="color: rgba(255,255,255,0.8); max-width: 600px; margin: 1rem auto 2rem;"><?php echo __('tour_tz_cta_desc'); ?></p>
            <div class="cta-buttons">
                <a href="<?php echo SITE_URL; ?>/book-tour" class="btn btn-premium btn-gold btn-lg"><i class="fas fa-calendar-check"></i> <?php echo __('tour_tz_cta_book'); ?></a>
                <a href="https://wa.me/<?php echo $siteWhatsapp; ?>" class="btn btn-premium btn-whatsapp btn-lg" target="_blank"><i class="fab fa-whatsapp"></i> <?php echo __('tour_tz_cta_whatsapp'); ?></a>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
