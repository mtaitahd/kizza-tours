<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
$pageSeo = seoPageMeta('about');
$sitePhone = getSetting('site_phone', SITE_PHONE);
$siteEmail = getSetting('site_email', SITE_EMAIL);
$siteWhatsapp = getSetting('site_whatsapp', SITE_WHATSAPP);
$aboutImg = getMediaUrl('about_image', 'images/about-hero.jpg');
?>
<?php include 'includes/header.php'; ?>
<script type="application/ld+json"><?php echo json_encode(seoBreadcrumbSchema([
    ['name' => 'Home', 'url' => SITE_URL . '/'],
    ['name' => 'About Us', 'url' => SITE_URL . '/about-us'],
]), JSON_UNESCAPED_SLASHES); ?></script>

<section class="inner-hero" style="background: linear-gradient(135deg, var(--primary) 0%, #0D2E4A 100%); padding: 140px 0 80px;">
    <div class="container text-center">
        <span class="section-subtitle"><?php echo __('about_page_subtitle'); ?></span>
        <h1 style="color: var(--white); font-size: clamp(2.5rem, 5vw, 4rem);"><?php echo __('about_page_title'); ?></h1>
        <p style="color: rgba(255,255,255,0.8); max-width: 700px; margin: 1rem auto 0; font-size: 1.1rem;">
            <?php echo __('about_page_desc'); ?>
        </p>
    </div>
</section>

<section class="section-padding" style="background: var(--white);">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6" data-aos="fade-right">
                <img src="<?php echo $aboutImg; ?>" alt="About Kizza Tours & Safaris - East Africa Tour Operator" class="img-fluid rounded-4 shadow-lg" loading="lazy" onerror="this.src='assets/images/placeholder.svg'">
            </div>
            <div class="col-lg-6" data-aos="fade-left">
                <h2><?php echo __('about_story_title'); ?></h2>
                <p style="color: var(--text-light); font-size: 1.1rem; line-height: 1.9;"><?php echo __('about_story_p1'); ?></p>
                <p style="color: var(--text-light); font-size: 1.1rem; line-height: 1.9;"><?php echo __('about_story_p2'); ?></p>
                <p style="color: var(--text-light); font-size: 1.1rem; line-height: 1.9;"><?php echo __('about_story_p3'); ?></p>
            </div>
        </div>
    </div>
</section>

<section class="section-padding" style="background: var(--off-white);">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-subtitle"><?php echo __('about_mission'); ?></span>
            <h2 class="section-title"><?php echo __('about_mission_title'); ?></h2>
        </div>
        <div class="row g-4">
            <div class="col-md-4" data-aos="fade-up">
                <div class="story-card">
                    <div class="story-card-icon"><i class="fas fa-bullseye"></i></div>
                    <h3><?php echo __('about_mission'); ?></h3>
                    <p><?php echo __('about_mission_desc'); ?></p>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                <div class="story-card">
                    <div class="story-card-icon"><i class="fas fa-eye"></i></div>
                    <h3><?php echo __('about_vision'); ?></h3>
                    <p><?php echo __('about_vision_desc'); ?></p>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                <div class="story-card">
                    <div class="story-card-icon"><i class="fas fa-star"></i></div>
                    <h3><?php echo __('about_values'); ?></h3>
                    <p><?php echo __('about_values_desc'); ?></p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-padding">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-subtitle"><?php echo __('about_why_subtitle'); ?></span>
            <h2 class="section-title"><?php echo __('about_why_title'); ?></h2>
        </div>
        <div class="row g-4">
            <div class="col-md-6 col-lg-4" data-aos="fade-up">
                <div class="value-card">
                    <div class="value-card-icon"><i class="fas fa-certificate"></i></div>
                    <h4><?php echo __('about_why_1_title'); ?></h4>
                    <p><?php echo __('about_why_1_desc'); ?></p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="50">
                <div class="value-card">
                    <div class="value-card-icon"><i class="fas fa-users"></i></div>
                    <h4><?php echo __('about_why_2_title'); ?></h4>
                    <p><?php echo __('about_why_2_desc'); ?></p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="100">
                <div class="value-card">
                    <div class="value-card-icon"><i class="fas fa-shield-alt"></i></div>
                    <h4><?php echo __('about_why_3_title'); ?></h4>
                    <p><?php echo __('about_why_3_desc'); ?></p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="150">
                <div class="value-card">
                    <div class="value-card-icon"><i class="fas fa-leaf"></i></div>
                    <h4><?php echo __('about_why_4_title'); ?></h4>
                    <p><?php echo __('about_why_4_desc'); ?></p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="200">
                <div class="value-card">
                    <div class="value-card-icon"><i class="fas fa-crown"></i></div>
                    <h4><?php echo __('about_why_5_title'); ?></h4>
                    <p><?php echo __('about_why_5_desc'); ?></p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="250">
                <div class="value-card">
                    <div class="value-card-icon"><i class="fas fa-hand-holding-heart"></i></div>
                    <h4><?php echo __('about_why_6_title'); ?></h4>
                    <p><?php echo __('about_why_6_desc'); ?></p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-padding">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-subtitle"><?php echo __('about_safety_subtitle'); ?></span>
            <h2 class="section-title"><?php echo __('about_safety_title'); ?></h2>
        </div>
        <div class="row g-4">
            <div class="col-md-6" data-aos="fade-up">
                <div class="story-card">
                    <div class="d-flex align-items-start gap-3">
                        <div class="story-card-icon" style="width: 50px; height: 50px; font-size: 1.2rem; margin: 0; flex-shrink: 0;"><i class="fas fa-car-side"></i></div>
                        <div><h5 style="font-family: var(--font-secondary);"><?php echo __('about_safety_1_title'); ?></h5><p style="margin: 0;"><?php echo __('about_safety_1_desc'); ?></p></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6" data-aos="fade-up" data-aos-delay="50">
                <div class="story-card">
                    <div class="d-flex align-items-start gap-3">
                        <div class="story-card-icon" style="width: 50px; height: 50px; font-size: 1.2rem; margin: 0; flex-shrink: 0;"><i class="fas fa-kit-medical"></i></div>
                        <div><h5 style="font-family: var(--font-secondary);"><?php echo __('about_safety_2_title'); ?></h5><p style="margin: 0;"><?php echo __('about_safety_2_desc'); ?></p></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6" data-aos="fade-up" data-aos-delay="100">
                <div class="story-card">
                    <div class="d-flex align-items-start gap-3">
                        <div class="story-card-icon" style="width: 50px; height: 50px; font-size: 1.2rem; margin: 0; flex-shrink: 0;"><i class="fas fa-id-card"></i></div>
                        <div><h5 style="font-family: var(--font-secondary);"><?php echo __('about_safety_3_title'); ?></h5><p style="margin: 0;"><?php echo __('about_safety_3_desc'); ?></p></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6" data-aos="fade-up" data-aos-delay="150">
                <div class="story-card">
                    <div class="d-flex align-items-start gap-3">
                        <div class="story-card-icon" style="width: 50px; height: 50px; font-size: 1.2rem; margin: 0; flex-shrink: 0;"><i class="fas fa-shield-virus"></i></div>
                        <div><h5 style="font-family: var(--font-secondary);"><?php echo __('about_safety_4_title'); ?></h5><p style="margin: 0;"><?php echo __('about_safety_4_desc'); ?></p></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-padding" style="background: var(--off-white);">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-subtitle"><?php echo __('about_testimonial_subtitle'); ?></span>
            <h2 class="section-title"><?php echo __('about_testimonial_title'); ?></h2>
        </div>
        <div class="row g-4">
            <?php $reviews = getTestimonials(3); ?>
            <?php if (!empty($reviews)): ?>
                <?php foreach ($reviews as $r): ?>
                <div class="col-md-4" data-aos="fade-up">
                    <div class="story-card" style="text-align: center;">
                        <div style="font-size: 2rem; color: var(--secondary); margin-bottom: 1rem;"><i class="fas fa-quote-left"></i></div>
                        <p style="font-style: italic;">"<?php echo htmlspecialchars($r['review'] ?? ''); ?>"</p>
                        <h6 style="font-family: var(--font-secondary); margin-top: 1rem;">- <?php echo htmlspecialchars($r['customer_name'] ?? __('about_guest')); ?></h6>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-md-4" data-aos="fade-up">
                    <div class="story-card" style="text-align: center;">
                        <div style="font-size: 2rem; color: var(--secondary); margin-bottom: 1rem;"><i class="fas fa-quote-left"></i></div>
                        <p style="font-style: italic;"><?php echo __('about_testimonial_fallback_1'); ?></p>
                        <h6 style="font-family: var(--font-secondary); margin-top: 1rem;"><?php echo __('about_testimonial_fallback_1_author'); ?></h6>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="story-card" style="text-align: center;">
                        <div style="font-size: 2rem; color: var(--secondary); margin-bottom: 1rem;"><i class="fas fa-quote-left"></i></div>
                        <p style="font-style: italic;"><?php echo __('about_testimonial_fallback_2'); ?></p>
                        <h6 style="font-family: var(--font-secondary); margin-top: 1rem;"><?php echo __('about_testimonial_fallback_2_author'); ?></h6>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="story-card" style="text-align: center;">
                        <div style="font-size: 2rem; color: var(--secondary); margin-bottom: 1rem;"><i class="fas fa-quote-left"></i></div>
                        <p style="font-style: italic;"><?php echo __('about_testimonial_fallback_3'); ?></p>
                        <h6 style="font-family: var(--font-secondary); margin-top: 1rem;"><?php echo __('about_testimonial_fallback_3_author'); ?></h6>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="section-padding section-dark text-center">
    <div class="container">
        <h2 style="color: var(--white);"><?php echo __('about_cta_title'); ?></h2>
        <p style="color: rgba(255,255,255,0.8); max-width: 600px; margin: 1rem auto 2rem;"><?php echo __('about_cta_desc'); ?></p>
        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
            <a href="<?php echo SITE_URL; ?>/book-tour" class="btn btn-premium btn-gold btn-lg"><i class="fas fa-calendar-check"></i> <?php echo __('about_cta_book'); ?></a>
            <a href="<?php echo SITE_URL; ?>/contact-us" class="btn btn-premium btn-outline btn-lg"><i class="fas fa-envelope"></i> <?php echo __('about_cta_contact'); ?></a>
            <a href="https://wa.me/<?php echo $siteWhatsapp; ?>" class="btn btn-premium btn-whatsapp btn-lg" target="_blank"><i class="fab fa-whatsapp"></i> <?php echo __('about_cta_whatsapp'); ?></a>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
