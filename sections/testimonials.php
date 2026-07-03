<?php
$testimonials = getTestimonials(6);
if (empty($testimonials)) {
    $testimonials = [
        ['customer_name' => 'Sarah & Michael Johnson', 'customer_photo' => '', 'customer_title' => 'United States - Serengeti Safari', 'review' => 'The most incredible experience of our lives! Kizza Tours &amp; Safaris organized an unforgettable safari. Every detail was perfect. Our guide was knowledgeable, kind, and made sure we saw everything we dreamed of.', 'rating' => 5.0],
        ['customer_name' => 'David & Lisa Chen', 'customer_photo' => '', 'customer_title' => 'Canada - Gorilla Trekking', 'review' => 'Gorilla trekking with Kizza Tours was a life-changing experience. Coming face to face with mountain gorillas in Bwindi was something we will never forget.', 'rating' => 5.0],
        ['customer_name' => 'Emma & James Wilson', 'customer_photo' => '', 'customer_title' => 'United Kingdom - Kilimanjaro Climb', 'review' => 'We climbed Kilimanjaro and it was the most challenging and rewarding experience. The guides were professional and incredibly experienced. We made it to the summit!', 'rating' => 5.0],
        ['customer_name' => 'Maria & Carlos Garcia', 'customer_photo' => '', 'customer_title' => 'Spain - Zanzibar Honeymoon', 'review' => 'Our honeymoon in Zanzibar was pure magic. The most romantic beachfront resort, sunset dhow cruise, and spice tour. Perfect blend of relaxation and adventure.', 'rating' => 5.0]
    ];
}
?>
<section class="section-padding" id="testimonials">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-subtitle"><?php echo __('testimonials_subtitle'); ?></span>
            <h2 class="section-title"><?php echo __('testimonials_title'); ?></h2>
            <p class="section-description mx-auto"><?php echo __('testimonials_desc'); ?></p>
        </div>
        <div class="swiper testimonialSwiper" data-aos="fade-up">
            <div class="swiper-wrapper">
                <?php foreach ($testimonials as $t): 
                    $photo = !empty($t['customer_photo']) && file_exists(BASE_PATH . $t['customer_photo']) ? SITE_URL . '/' . $t['customer_photo'] : 'https://placehold.co/100x100/0A2540/D4AF37?text=' . urlencode(substr($t['customer_name'], 0, 1));
                    $title = htmlspecialchars($t['customer_title'] ?: '');
                    $rating = intval($t['rating'] ?? 5);
                ?>
                <div class="swiper-slide">
                    <div class="testimonial-card">
                        <div class="testimonial-stars">
                            <?php for ($s = 0; $s < 5; $s++): ?>
                                <i class="fas fa-star" style="color: <?php echo $s < $rating ? 'var(--secondary)' : '#ddd'; ?>;"></i>
                            <?php endfor; ?>
                        </div>
                        <div class="testimonial-text"><?php echo $t['review']; ?></div>
                        <div class="testimonial-author">
                            <img src="<?php echo $photo; ?>" alt="<?php echo htmlspecialchars($t['customer_name']); ?>" onerror="this.src='https://placehold.co/100x100/0A2540/D4AF37?text=<?php echo urlencode(substr($t['customer_name'], 0, 1)); ?>'">
                            <div class="testimonial-author-info">
                                <h6><?php echo htmlspecialchars($t['customer_name']); ?></h6>
                                <span><?php echo $title; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="swiper-pagination mt-4"></div>
        </div>
    </div>
</section>
