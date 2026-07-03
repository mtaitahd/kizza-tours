<?php $faqs = getFAQs(); ?>
<section class="section-padding section-cream" id="faq">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-subtitle"><?php echo __('faq_subtitle'); ?></span>
            <h2 class="section-title"><?php echo __('faq_title'); ?></h2>
            <p class="section-description mx-auto"><?php echo __('faq_desc'); ?></p>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-8" data-aos="fade-up">
                <div class="accordion faq-accordion" id="faqAccordion">
                    <?php if (!empty($faqs)): ?>
                        <?php foreach ($faqs as $idx => $faq): ?>
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
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <p><?php echo __('faq_empty'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
