<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
$pageSeo = seoPageMeta('contact');
$sitePhone = getSetting('site_phone', SITE_PHONE);
$siteEmail = getSetting('site_email', SITE_EMAIL);
$siteWhatsapp = getSetting('site_whatsapp', SITE_WHATSAPP);
$siteAddress = getSetting('site_address', SITE_ADDRESS);
?>
<?php include 'includes/header.php'; ?>
<script type="application/ld+json"><?php echo json_encode(seoBreadcrumbSchema([
    ['name' => 'Home', 'url' => SITE_URL . '/'],
    ['name' => 'Contact Us', 'url' => SITE_URL . '/contact-us'],
]), JSON_UNESCAPED_SLASHES); ?></script>

<section class="inner-hero" style="background: linear-gradient(135deg, var(--primary) 0%, #0D2E4A 100%); padding: 140px 0 80px;">
    <div class="container text-center">
        <span class="section-subtitle"><?php echo __('contact_page_subtitle'); ?></span>
        <h1 style="color: var(--white); font-size: clamp(2.5rem, 5vw, 4rem);"><?php echo __('contact_page_title'); ?></h1>
        <p style="color: rgba(255,255,255,0.8); max-width: 650px; margin: 1rem auto 0; font-size: 1.1rem;">
            <?php echo __('contact_page_desc'); ?>
        </p>
    </div>
</section>

<section class="section-padding">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-5" data-aos="fade-right">
                <span class="section-subtitle"><?php echo __('contact_info_title'); ?></span>
                <h2 class="section-title"><?php echo __('contact_info_desc'); ?></h2>
                <p style="color: var(--text-light); margin-bottom: 2rem;"><?php echo __('contact_info_text'); ?></p>

                <div class="d-flex align-items-start gap-3 mb-4" itemscope itemtype="https://schema.org/PostalAddress">
                    <div class="value-card-icon" style="width: 50px; height: 50px; font-size: 1.2rem; margin: 0; flex-shrink: 0;"><i class="fas fa-map-marker-alt"></i></div>
                    <div>
                        <h6 class="mb-1 fw-bold"><?php echo __('contact_office'); ?></h6>
                        <p class="text-muted mb-0" itemprop="streetAddress"><?php echo htmlspecialchars($siteAddress); ?></p>
                        <p class="text-muted mb-0"><span itemprop="addressLocality">Arusha</span>, <span itemprop="addressCountry">Tanzania</span></p>
                    </div>
                </div>
                <div class="d-flex align-items-start gap-3 mb-4">
                    <div class="value-card-icon" style="width: 50px; height: 50px; font-size: 1.2rem; margin: 0; flex-shrink: 0;"><i class="fas fa-phone-alt"></i></div>
                    <div>
                        <h6 class="mb-1 fw-bold"><?php echo __('contact_phone_label'); ?></h6>
                        <p class="text-muted mb-0"><a href="tel:<?php echo $sitePhone; ?>" style="color: var(--text-light);"><?php echo htmlspecialchars($sitePhone); ?></a></p>
                        <p style="font-size: 0.85rem; color: var(--text-muted);"><?php echo __('contact_phone_hours'); ?></p>
                    </div>
                </div>
                <div class="d-flex align-items-start gap-3 mb-4">
                    <div class="value-card-icon" style="width: 50px; height: 50px; font-size: 1.2rem; margin: 0; flex-shrink: 0;"><i class="fas fa-envelope"></i></div>
                    <div>
                        <h6 class="mb-1 fw-bold"><?php echo __('contact_email_label'); ?></h6>
                        <p class="text-muted mb-0"><a href="mailto:<?php echo $siteEmail; ?>" style="color: var(--text-light);"><?php echo htmlspecialchars($siteEmail); ?></a></p>
                        <p style="font-size: 0.85rem; color: var(--text-muted);"><?php echo __('contact_email_hours'); ?></p>
                    </div>
                </div>
                <div class="d-flex align-items-start gap-3 mb-4">
                    <div class="value-card-icon" style="width: 50px; height: 50px; font-size: 1.2rem; margin: 0; flex-shrink: 0;"><i class="fab fa-whatsapp"></i></div>
                    <div>
                        <h6 class="mb-1 fw-bold"><?php echo __('contact_whatsapp_label'); ?></h6>
                        <p class="text-muted mb-0"><a href="https://wa.me/<?php echo $siteWhatsapp; ?>" target="_blank" style="color: var(--text-light);"><?php echo __('contact_whatsapp_text'); ?></a></p>
                        <p style="font-size: 0.85rem; color: var(--text-muted);"><?php echo __('contact_whatsapp_hours'); ?></p>
                    </div>
                </div>

                <div class="mt-4 p-4 rounded-0" style="background: var(--off-white);">
                    <h5 style="font-family: var(--font-secondary);"><?php echo __('contact_hours_title'); ?></h5>
                    <table class="table table-borderless mb-0" style="font-size: 0.9rem;">
                        <tr><td><?php echo __('contact_hours_mon_sat'); ?></td><td style="text-align: right; font-weight: 600;"><?php echo __('contact_hours_weekday'); ?></td></tr>
                        <tr><td><?php echo __('contact_hours_sun'); ?></td><td style="text-align: right; font-weight: 600;"><?php echo __('contact_hours_weekend'); ?></td></tr>
                        <tr><td><?php echo __('contact_hours_emergency'); ?></td><td style="text-align: right; font-weight: 600; color: var(--secondary);"><?php echo __('contact_hours_24_7'); ?></td></tr>
                    </table>
                </div>

                <div class="mt-4">
                    <a href="https://wa.me/<?php echo $siteWhatsapp; ?>" class="btn btn-premium btn-whatsapp btn-lg w-100" target="_blank">
                        <i class="fab fa-whatsapp"></i> <?php echo __('contact_chat_whatsapp'); ?>
                    </a>
                </div>
            </div>

            <div class="col-lg-7" data-aos="fade-left">
                <div class="rounded-0 p-4 p-lg-5" style="background: var(--off-white);">
                    <h4 class="mb-4"><?php echo __('contact_form_page_title'); ?></h4>
                    <form id="contactFormPage" method="POST" action="api/contact.php">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold"><?php echo __('contact_form_name_label'); ?> <span style="color: red;">*</span></label>
                                <input type="text" class="form-control form-control-lg" name="full_name" placeholder="<?php echo __('contact_form_name_placeholder'); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold"><?php echo __('contact_form_email_label'); ?> <span style="color: red;">*</span></label>
                                <input type="email" class="form-control form-control-lg" name="email" placeholder="<?php echo __('contact_form_email_placeholder'); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold"><?php echo __('contact_form_phone_label'); ?></label>
                                <input type="tel" class="form-control form-control-lg" name="phone" placeholder="<?php echo __('contact_form_phone_placeholder'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold"><?php echo __('contact_form_subject_label'); ?></label>
                                <select class="form-select form-select-lg" name="subject">
                                    <option value=""><?php echo __('contact_form_subject_select'); ?></option>
                                    <option value="safari-inquiry"><?php echo __('contact_form_subject_safari'); ?></option>
                                    <option value="booking"><?php echo __('contact_form_subject_booking'); ?></option>
                                    <option value="gorilla-trekking"><?php echo __('contact_form_subject_gorilla'); ?></option>
                                    <option value="kilimanjaro"><?php echo __('contact_form_subject_kilimanjaro'); ?></option>
                                    <option value="zanzibar"><?php echo __('contact_form_subject_zanzibar'); ?></option>
                                    <option value="custom-tour"><?php echo __('contact_form_subject_custom'); ?></option>
                                    <option value="group-booking"><?php echo __('contact_form_subject_group'); ?></option>
                                    <option value="other"><?php echo __('contact_form_subject_other'); ?></option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold"><?php echo __('contact_form_message_label'); ?> <span style="color: red;">*</span></label>
                                <textarea class="form-control form-control-lg" name="message" rows="6" placeholder="<?php echo __('contact_form_message_placeholder'); ?>" required></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-premium btn-gold btn-lg w-100" id="contactPageSubmit">
                                    <i class="fas fa-paper-plane"></i> <?php echo __('contact_form_submit_btn'); ?>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="mt-4 rounded-0 overflow-hidden" style="height: 300px; border: 1px solid #e0e0e0;">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d255198.3308948984!2d36.5805498554677!3d-3.373085676298425!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x18371c8d1c6e2f0f%3A0xb29b0a5a3e3c5e5f!2sArusha%2C%20Tanzania!5e0!3m2!1sen!2s!4v1" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Kizza Tours & Safaris - Arusha, Tanzania Office Location"></iframe>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-padding section-dark text-center">
    <div class="container">
        <h2 style="color: var(--white);"><?php echo __('contact_cta_title'); ?></h2>
        <p style="color: rgba(255,255,255,0.8); max-width: 600px; margin: 1rem auto 2rem;"><?php echo __('contact_cta_desc'); ?></p>
        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
            <a href="<?php echo SITE_URL; ?>/book-tour" class="btn btn-premium btn-gold btn-lg"><i class="fas fa-calendar-check"></i> <?php echo __('contact_cta_book'); ?></a>
            <a href="https://wa.me/<?php echo $siteWhatsapp; ?>" class="btn btn-premium btn-whatsapp btn-lg" target="_blank"><i class="fab fa-whatsapp"></i> <?php echo __('contact_cta_whatsapp'); ?></a>
        </div>
    </div>
</section>

<script>
document.getElementById('contactFormPage').addEventListener('submit', function(e) {
    e.preventDefault();
    var btn = document.getElementById('contactPageSubmit');
    var origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?php echo __('contact_sending'); ?>';
    var formData = new FormData(this);
    fetch(this.action, { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.innerHTML = origHtml;
            if (data.success) {
                showToast(data.message, 'success');
                document.getElementById('contactFormPage').reset();
            } else {
                showToast(data.message, 'error');
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.innerHTML = origHtml;
            showToast('<?php echo __('contact_error_text'); ?>', 'error');
        });
});
</script>
<?php include 'includes/footer.php'; ?>
