<?php
$aboutImg = getMediaUrl('about_image', 'images/about-hero.jpg');
$about1 = getSetting('about_content_1', 'Kizza Tours &amp; Safaris is dedicated to providing unforgettable travel experiences across East Africa while delivering exceptional service from the moment guests inquire until they return home with unforgettable memories.');
$about2 = getSetting('about_content_2', 'Kizza Tours &amp; Safaris specializes in safaris, gorilla trekking, Kilimanjaro expeditions, cultural journeys, luxury escapes, and tailor-made adventures that showcase the very best of East Africa.');
$about3 = getSetting('about_content_3', 'Kizza Tours &amp; Safaris creates meaningful adventures, lifelong memories, and authentic connections with Africa. Every journey is crafted with passion, expertise, and an unwavering commitment to excellence.');
$statYears = getSetting('about_stat_years', '10+');
$statYearsLabel = getSetting('about_stat_years_label', 'Years Experience');
$statTravelers = getSetting('about_stat_travelers', '5000+');
$statTravelersLabel = getSetting('about_stat_travelers_label', 'Happy Travelers');
?>
<section class="about-section section-padding" id="about">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6" data-aos="fade-right">
                <img src="<?php echo $aboutImg; ?>" alt="Kizza Tours & Safaris" class="img-fluid rounded-4 shadow-lg" loading="lazy" onerror="this.src='https://placehold.co/800x600/0A2540/D4AF37?text=About+Kizza+Tours'">
                <div class="glass-gold rounded-4 p-4 mt-4 text-center">
                    <div class="d-flex align-items-center justify-content-center gap-5">
                        <div>
                            <div class="counter-number"><?php echo htmlspecialchars($statYears); ?></div>
                            <div class="counter-label"><?php echo htmlspecialchars($statYearsLabel); ?></div>
                        </div>
                        <div class="border-start border-secondary ps-5">
                            <div class="counter-number"><?php echo htmlspecialchars($statTravelers); ?></div>
                            <div class="counter-label"><?php echo htmlspecialchars($statTravelersLabel); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6" data-aos="fade-left">
                <span class="section-subtitle"><?php echo __('about_subtitle'); ?></span>
                <h2 class="section-title"><?php echo __('about_title'); ?></h2>
                <div class="about-content">
                    <p><span class="brand-name">Kizza Tours &amp; Safaris</span> <?php echo $about1; ?></p>
                    <p><span class="brand-name">Kizza Tours &amp; Safaris</span> <?php echo $about2; ?></p>
                    <p><span class="brand-name">Kizza Tours &amp; Safaris</span> <?php echo $about3; ?></p>
                </div>
                <div class="mt-4">
                    <a href="#packages" class="btn btn-premium btn-outline-gold"><i class="fas fa-safari me-1"></i> <?php echo __('about_btn'); ?></a>
                </div>
            </div>
        </div>
    </div>
</section>
