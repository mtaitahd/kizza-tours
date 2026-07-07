<?php
$ctaBg = getMediaUrl('cta_background', 'images/african-sunset.jpg');
$sitePhone = getSetting('site_phone', SITE_PHONE);
$siteEmail = getSetting('site_email', SITE_EMAIL);
$siteAddress = getSetting('site_address', SITE_ADDRESS);
$siteWhatsapp = getSetting('site_whatsapp', SITE_WHATSAPP);
?>
<section class="section-padding" id="contact">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-5" data-aos="fade-right">
                <span class="section-subtitle"><?php echo __('contact_subtitle'); ?></span>
                <h2 class="section-title"><?php echo __('contact_title'); ?></h2>
                <p class="text-muted mb-4"><?php echo __('contact_desc'); ?></p>
                
                <div class="d-flex align-items-start gap-3 mb-4">
                    <div class="value-card-icon" style="width: 50px; height: 50px; font-size: 1.2rem; margin: 0; flex-shrink: 0;"><i class="fas fa-map-marker-alt"></i></div>
                    <div><h6 class="mb-1 fw-bold"><?php echo __('contact_visit_us'); ?></h6><p class="text-muted mb-0"><?php echo htmlspecialchars($siteAddress); ?></p></div>
                </div>
                <div class="d-flex align-items-start gap-3 mb-4">
                    <div class="value-card-icon" style="width: 50px; height: 50px; font-size: 1.2rem; margin: 0; flex-shrink: 0;"><i class="fas fa-phone-alt"></i></div>
                    <div><h6 class="mb-1 fw-bold"><?php echo __('contact_call_us'); ?></h6><p class="text-muted mb-0"><a href="tel:<?php echo $sitePhone; ?>" style="color: var(--text-light);"><?php echo htmlspecialchars($sitePhone); ?></a></p></div>
                </div>
                <div class="d-flex align-items-start gap-3 mb-4">
                    <div class="value-card-icon" style="width: 50px; height: 50px; font-size: 1.2rem; margin: 0; flex-shrink: 0;"><i class="fas fa-envelope"></i></div>
                    <div><h6 class="mb-1 fw-bold"><?php echo __('contact_email_us'); ?></h6><p class="text-muted mb-0"><a href="mailto:<?php echo $siteEmail; ?>" style="color: var(--text-light);"><?php echo htmlspecialchars($siteEmail); ?></a></p></div>
                </div>
                <div class="d-flex align-items-start gap-3 mb-4">
                    <div class="value-card-icon" style="width: 50px; height: 50px; font-size: 1.2rem; margin: 0; flex-shrink: 0;"><i class="fab fa-whatsapp"></i></div>
                    <div><h6 class="mb-1 fw-bold"><?php echo __('contact_whatsapp'); ?></h6><p class="text-muted mb-0"><a href="https://wa.me/<?php echo $siteWhatsapp; ?>" target="_blank" style="color: var(--text-light);"><?php echo __('contact_chat_now'); ?></a></p></div>
                </div>
            </div>
            <div class="col-lg-7" data-aos="fade-left">
                <div class="bg-light rounded-0 p-4 p-lg-5">
                    <h4 class="mb-4"><?php echo __('contact_form_title'); ?></h4>
                    <form id="contactForm" method="POST">
                        <div class="row g-3">
                            <div class="col-md-6"><input type="text" class="form-control form-control-lg" name="full_name" placeholder="<?php echo __('contact_placeholder_name'); ?>" required></div>
                            <div class="col-md-6"><input type="email" class="form-control form-control-lg" name="email" placeholder="<?php echo __('contact_placeholder_email'); ?>" required></div>
                            <div class="col-md-6"><input type="tel" class="form-control form-control-lg" name="phone" placeholder="<?php echo __('contact_placeholder_phone'); ?>"></div>
                            <div class="col-md-6"><input type="text" class="form-control form-control-lg" name="subject" placeholder="<?php echo __('contact_placeholder_subject'); ?>"></div>
                            <div class="col-12"><textarea class="form-control form-control-lg" name="message" rows="5" placeholder="<?php echo __('contact_placeholder_message'); ?>" required></textarea></div>
                            <div class="col-12"><button type="submit" class="btn btn-premium btn-gold btn-lg w-100" id="contactSubmit"><i class="fas fa-paper-plane"></i> <?php echo __('contact_send_btn'); ?></button></div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="cta-section" id="final-cta">
    <img src="<?php echo $ctaBg; ?>" alt="African Sunset" class="cta-bg" loading="lazy" onerror="this.src='assets/images/placeholder.svg'">
    <div class="cta-overlay"></div>
    <div class="cta-content" data-aos="zoom-in">
        <span class="section-subtitle"><?php echo __('cta_subtitle'); ?></span>
        <h2><?php echo __('cta_title'); ?></h2>
        <p><?php echo __('cta_desc'); ?></p>
        <div class="cta-buttons">
            <a href="#booking" class="btn btn-premium btn-gold btn-lg"><i class="fas fa-calendar-check"></i> <?php echo __('cta_btn_book'); ?></a>
            <a href="#booking" class="btn btn-premium btn-outline btn-lg"><i class="fas fa-file-invoice"></i> <?php echo __('cta_btn_quote'); ?></a>
            <a href="https://wa.me/<?php echo $siteWhatsapp; ?>" class="btn btn-premium btn-whatsapp btn-lg" target="_blank"><i class="fab fa-whatsapp"></i> <?php echo __('cta_btn_whatsapp'); ?></a>
        </div>
    </div>
</section>
