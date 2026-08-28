<?php $faqs = getFAQs(); ?>
<section class="section-padding section-cream" id="faq-section">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-subtitle"><?php echo __('faq_subtitle'); ?></span>
            <h2 class="section-title"><?php echo __('faq_title'); ?></h2>
            <p class="section-description mx-auto"><?php echo __('faq_desc'); ?></p>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-10" data-aos="fade-up">
                <?php if (!empty($faqs)): ?>
                <div class="faq-accordion" id="faqAccordion">
                    <?php $n = 'faq'; include __DIR__ . '/../includes/faq-accordion.php'; ?>
                </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        <p><?php echo __('faq_empty'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
