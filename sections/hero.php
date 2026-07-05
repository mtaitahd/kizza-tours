<?php
$heroPoster = getMediaUrl('hero_poster', 'images/hero-poster.jpg');
?>
<section class="hero-section" id="home" style="background: url('<?php echo $heroPoster; ?>') center center / cover no-repeat;">
    <div class="hero-overlay"></div>
    <div class="particles-container" id="particlesContainer"></div>
    <div class="hero-content">
        <div class="hero-badge" data-aos="fade-down" data-aos-duration="1000">
            <i class="fas fa-star"></i>
            <?php echo __('hero_badge'); ?>
        </div>
        <h1 class="hero-title" data-aos="fade-up" data-aos-duration="1200" data-aos-delay="200">
            <?php echo __('hero_title'); ?><br>
            <span class="gold-text"><?php echo __('hero_title_span'); ?></span>
        </h1>
        <p class="hero-subtitle" data-aos="fade-up" data-aos-duration="1200" data-aos-delay="400">
            <?php echo __('hero_subtitle'); ?>
        </p>
        <div class="hero-buttons" data-aos="fade-up" data-aos-duration="1200" data-aos-delay="600">
            <a href="#booking" class="btn btn-premium btn-gold btn-lg">
                <i class="fas fa-calendar-check"></i> <?php echo __('hero_btn_book'); ?>
            </a>
            <a href="#story" class="btn btn-premium btn-outline btn-lg">
                <i class="fas fa-play"></i> <?php echo __('hero_btn_watch'); ?>
            </a>
        </div>
    </div>
    <div class="scroll-indicator" id="scrollIndicator">
        <span><?php echo __('hero_scroll'); ?></span>
        <div class="scroll-chevron"></div>
        <div class="scroll-line"></div>
    </div>
</section>
