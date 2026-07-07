<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
$pageSeo = seoPageMeta('book');
$sitePhone = getSetting('site_phone', SITE_PHONE);
$siteEmail = getSetting('site_email', SITE_EMAIL);
$siteWhatsapp = getSetting('site_whatsapp', SITE_WHATSAPP);
?>
<?php include 'includes/header.php'; ?>
<script type="application/ld+json"><?php echo json_encode(seoBreadcrumbSchema([
    ['name' => 'Home', 'url' => SITE_URL . '/'],
    ['name' => 'Book Tour', 'url' => SITE_URL . '/book-tour'],
]), JSON_UNESCAPED_SLASHES); ?></script>

<section class="inner-hero" style="background: linear-gradient(135deg, var(--primary) 0%, #0D2E4A 100%); padding: 140px 0 80px;">
    <div class="container text-center">
        <span class="section-subtitle"><?php echo __('book_page_subtitle'); ?></span>
        <h1 style="color: var(--white); font-size: clamp(2.5rem, 5vw, 4rem);"><?php echo __('book_page_title'); ?></h1>
        <p style="color: rgba(255,255,255,0.8); max-width: 650px; margin: 1rem auto 0; font-size: 1.1rem;">
            <?php echo __('book_page_desc'); ?>
        </p>
    </div>
</section>

<section class="section-padding">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-7" data-aos="fade-right">
                <span class="section-subtitle"><?php echo __('book_form_subtitle'); ?></span>
                <h2 class="section-title"><?php echo __('book_form_title'); ?></h2>
                <p style="color: var(--text-light); margin-bottom: 2rem;"><?php echo __('book_form_desc'); ?></p>

                <div class="rounded-0 p-4 p-lg-5" style="background: var(--off-white);">
                    <form id="bookingFormPage" method="POST" action="api/submit-booking.php">
                        <div class="row g-3">
                            <div class="col-12">
                                <h5 style="font-family: var(--font-secondary); margin-bottom: 1rem; color: var(--primary);"><?php echo __('book_form_preferences'); ?></h5>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold"><?php echo __('book_form_destination_label'); ?> <span style="color: red;">*</span></label>
                                <select class="form-select form-select-lg" name="destination" required>
                                    <option value=""><?php echo __('book_form_destination_select'); ?></option>
                                    <option value="serengeti"><?php echo __('book_form_dest_serengeti'); ?></option>
                                    <option value="maasai-mara"><?php echo __('book_form_dest_maasai_mara'); ?></option>
                                    <option value="ngorongoro"><?php echo __('book_form_dest_ngorongoro'); ?></option>
                                    <option value="kilimanjaro"><?php echo __('book_form_dest_kilimanjaro'); ?></option>
                                    <option value="zanzibar"><?php echo __('book_form_dest_zanzibar'); ?></option>
                                    <option value="bwindi"><?php echo __('book_form_dest_bwindi'); ?></option>
                                    <option value="volcanoes"><?php echo __('book_form_dest_volcanoes'); ?></option>
                                    <option value="amboseli"><?php echo __('book_form_dest_amboseli'); ?></option>
                                    <option value="tarangire"><?php echo __('book_form_dest_tarangire'); ?></option>
                                    <option value="mount-kenya"><?php echo __('book_form_dest_mount_kenya'); ?></option>
                                    <option value="multiple"><?php echo __('book_form_dest_multiple'); ?></option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold"><?php echo __('book_form_package_label'); ?></label>
                                <select class="form-select form-select-lg" name="package">
                                    <option value=""><?php echo __('book_form_package_select'); ?></option>
                                    <option value="luxury-safari"><?php echo __('book_form_pkg_luxury'); ?></option>
                                    <option value="migration-safari"><?php echo __('book_form_pkg_migration'); ?></option>
                                    <option value="gorilla-trekking"><?php echo __('book_form_pkg_gorilla'); ?></option>
                                    <option value="kilimanjaro-climb"><?php echo __('book_form_pkg_kilimanjaro'); ?></option>
                                    <option value="beach-holiday"><?php echo __('book_form_pkg_beach'); ?></option>
                                    <option value="cultural-tour"><?php echo __('book_form_pkg_cultural'); ?></option>
                                    <option value="honeymoon"><?php echo __('book_form_pkg_honeymoon'); ?></option>
                                    <option value="family-safari"><?php echo __('book_form_pkg_family'); ?></option>
                                    <option value="mount-kenya-climb"><?php echo __('book_form_pkg_mount_kenya'); ?></option>
                                    <option value="private-tour"><?php echo __('book_form_pkg_private'); ?></option>
                                    <option value="custom"><?php echo __('book_form_pkg_custom'); ?></option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold"><?php echo __('book_form_date_label'); ?> <span style="color: red;">*</span></label>
                                <input type="date" class="form-control form-control-lg" name="travel_date" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold"><?php echo __('book_form_guests_label'); ?> <span style="color: red;">*</span></label>
                                <input type="number" class="form-control form-control-lg" name="guests" min="1" max="50" value="2" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold"><?php echo __('book_form_budget_label'); ?></label>
                                <select class="form-select form-select-lg" name="budget" required>
                                    <option value=""><?php echo __('book_form_budget_select'); ?></option>
                                    <option value="1000-2000">$1,000 - $2,000</option>
                                    <option value="2000-3500">$2,000 - $3,500</option>
                                    <option value="3500-5000">$3,500 - $5,000</option>
                                    <option value="5000-7500">$5,000 - $7,500</option>
                                    <option value="7500-10000">$7,500 - $10,000</option>
                                    <option value="10000+">$10,000+</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold"><?php echo __('book_form_accommodation_label'); ?></label>
                                <select class="form-select form-select-lg" name="accommodation">
                                    <option value=""><?php echo __('book_form_accommodation_select'); ?></option>
                                    <option value="luxury"><?php echo __('book_form_acc_luxury'); ?></option>
                                    <option value="mid-range"><?php echo __('book_form_acc_mid_range'); ?></option>
                                    <option value="budget"><?php echo __('book_form_acc_budget'); ?></option>
                                    <option value="tented"><?php echo __('book_form_acc_tented'); ?></option>
                                    <option value="resort"><?php echo __('book_form_acc_resort'); ?></option>
                                    <option value="mixed"><?php echo __('book_form_acc_mixed'); ?></option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold"><?php echo __('book_form_duration_label'); ?></label>
                                <select class="form-select form-select-lg" name="duration">
                                    <option value=""><?php echo __('book_form_duration_select'); ?></option>
                                    <option value="3-4"><?php echo __('book_form_dur_3_4'); ?></option>
                                    <option value="5-7"><?php echo __('book_form_dur_5_7'); ?></option>
                                    <option value="8-10"><?php echo __('book_form_dur_8_10'); ?></option>
                                    <option value="11-14"><?php echo __('book_form_dur_11_14'); ?></option>
                                    <option value="15+"><?php echo __('book_form_dur_15'); ?></option>
                                </select>
                            </div>

                            <div class="col-12 mt-3">
                                <h5 style="font-family: var(--font-secondary); margin-bottom: 1rem; color: var(--primary);"><?php echo __('book_form_personal'); ?></h5>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold"><?php echo __('book_form_name_label'); ?> <span style="color: red;">*</span></label>
                                <input type="text" class="form-control form-control-lg" name="full_name" placeholder="<?php echo __('book_form_name_placeholder'); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold"><?php echo __('book_form_email_label'); ?> <span style="color: red;">*</span></label>
                                <input type="email" class="form-control form-control-lg" name="email" placeholder="<?php echo __('book_form_email_placeholder'); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold"><?php echo __('book_form_phone_label'); ?> <span style="color: red;">*</span></label>
                                <input type="tel" class="form-control form-control-lg" name="phone" placeholder="<?php echo __('book_form_phone_placeholder'); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold"><?php echo __('book_form_country_label'); ?></label>
                                <input type="text" class="form-control form-control-lg" name="country" placeholder="<?php echo __('book_form_country_placeholder'); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold"><?php echo __('book_form_requests_label'); ?></label>
                                <textarea class="form-control form-control-lg" name="message" rows="4" placeholder="<?php echo __('book_form_requests_placeholder'); ?>"></textarea>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="agree" id="agreeCheck" required>
                                    <label class="form-check-label" for="agreeCheck" style="font-size: 0.9rem;">
                                        <?php echo __('book_form_agree'); ?> <span style="color: red;">*</span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-12 text-center">
                                <button type="submit" class="btn btn-premium btn-gold btn-lg px-5" id="bookingPageSubmit">
                                    <i class="fas fa-paper-plane"></i> <?php echo __('book_form_submit'); ?>
                                </button>
                                <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 1rem;">
                                    <i class="fas fa-lock me-1"></i> <?php echo __('book_form_secure'); ?>
                                </p>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-lg-5" data-aos="fade-left">
                <div class="rounded-0 p-4" style="background: var(--primary); color: var(--white); position: sticky; top: 120px;">
                    <h4 style="color: var(--secondary); font-family: var(--font-secondary);"><?php echo __('book_why_title'); ?></h4>
                    <ul class="list-unstyled mt-4">
                        <li class="mb-3 d-flex align-items-start gap-3">
                            <i class="fas fa-check-circle" style="color: var(--secondary); font-size: 1.2rem; margin-top: 2px;"></i>
                            <div><strong><?php echo __('book_why_1_title'); ?></strong><br><span style="font-size: 0.85rem; color: rgba(255,255,255,0.7);"><?php echo __('book_why_1_desc'); ?></span></div>
                        </li>
                        <li class="mb-3 d-flex align-items-start gap-3">
                            <i class="fas fa-check-circle" style="color: var(--secondary); font-size: 1.2rem; margin-top: 2px;"></i>
                            <div><strong><?php echo __('book_why_2_title'); ?></strong><br><span style="font-size: 0.85rem; color: rgba(255,255,255,0.7);"><?php echo __('book_why_2_desc'); ?></span></div>
                        </li>
                        <li class="mb-3 d-flex align-items-start gap-3">
                            <i class="fas fa-check-circle" style="color: var(--secondary); font-size: 1.2rem; margin-top: 2px;"></i>
                            <div><strong><?php echo __('book_why_3_title'); ?></strong><br><span style="font-size: 0.85rem; color: rgba(255,255,255,0.7);"><?php echo __('book_why_3_desc'); ?></span></div>
                        </li>
                        <li class="mb-3 d-flex align-items-start gap-3">
                            <i class="fas fa-check-circle" style="color: var(--secondary); font-size: 1.2rem; margin-top: 2px;"></i>
                            <div><strong><?php echo __('book_why_4_title'); ?></strong><br><span style="font-size: 0.85rem; color: rgba(255,255,255,0.7);"><?php echo __('book_why_4_desc'); ?></span></div>
                        </li>
                        <li class="mb-3 d-flex align-items-start gap-3">
                            <i class="fas fa-check-circle" style="color: var(--secondary); font-size: 1.2rem; margin-top: 2px;"></i>
                            <div><strong><?php echo __('book_why_5_title'); ?></strong><br><span style="font-size: 0.85rem; color: rgba(255,255,255,0.7);"><?php echo __('book_why_5_desc'); ?></span></div>
                        </li>
                        <li class="mb-3 d-flex align-items-start gap-3">
                            <i class="fas fa-check-circle" style="color: var(--secondary); font-size: 1.2rem; margin-top: 2px;"></i>
                            <div><strong><?php echo __('book_why_6_title'); ?></strong><br><span style="font-size: 0.85rem; color: rgba(255,255,255,0.7);"><?php echo __('book_why_6_desc'); ?></span></div>
                        </li>
                    </ul>
                    <hr style="border-color: rgba(255,255,255,0.2);">
                    <div class="text-center">
                        <p style="color: rgba(255,255,255,0.7); font-size: 0.9rem;"><?php echo __('book_why_question'); ?></p>
                        <a href="https://wa.me/<?php echo $siteWhatsapp; ?>" class="btn btn-premium btn-whatsapp w-100" target="_blank">
                            <i class="fab fa-whatsapp"></i> <?php echo __('book_why_whatsapp'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-padding" style="background: var(--off-white);">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-subtitle"><?php echo __('book_popular_subtitle'); ?></span>
            <h2 class="section-title"><?php echo __('book_popular_title'); ?></h2>
        </div>
        <div class="row g-4">
            <?php $featured = getTourPackages(['featured' => false], 3); ?>
            <?php if (!empty($featured)): ?>
                <?php foreach ($featured as $pkg): ?>
                <div class="col-md-4" data-aos="fade-up">
                    <div class="package-card">
                        <div class="package-card-image">
                            <img src="<?php echo !empty($pkg['image']) && file_exists(BASE_PATH . $pkg['image']) ? SITE_URL . '/' . $pkg['image'] : 'assets/images/placeholder.svg'; ?>" alt="<?php echo htmlspecialchars($pkg['title']); ?>" loading="lazy">
                        </div>
                        <div class="package-card-body">
                            <div class="package-card-meta">
                                <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($pkg['duration'] ?: 'N/A'); ?></span>
                                <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($pkg['country']); ?></span>
                            </div>
                            <h3 class="package-card-title"><?php echo htmlspecialchars($pkg['title']); ?></h3>
                            <p style="color: var(--text-light); font-size: 0.9rem;"><?php echo htmlspecialchars(substr($pkg['description'] ?? '', 0, 120)) . '...'; ?></p>
                            <div class="package-card-price">$<?php echo number_format($pkg['price'], 0); ?> <small><?php echo __('book_per_person'); ?></small></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-md-4" data-aos="fade-up">
                    <div class="package-card"><div class="package-card-body"><h3 class="package-card-title"><?php echo __('book_fallback_1_title'); ?></h3><p style="color: var(--text-light);"><?php echo __('book_fallback_1_desc'); ?></p></div></div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="package-card"><div class="package-card-body"><h3 class="package-card-title"><?php echo __('book_fallback_2_title'); ?></h3><p style="color: var(--text-light);"><?php echo __('book_fallback_2_desc'); ?></p></div></div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="package-card"><div class="package-card-body"><h3 class="package-card-title"><?php echo __('book_fallback_3_title'); ?></h3><p style="color: var(--text-light);"><?php echo __('book_fallback_3_desc'); ?></p></div></div>
                </div>
            <?php endif; ?>
        </div>
        <div class="text-center mt-4" data-aos="fade-up">
            <a href="<?php echo SITE_URL; ?>/tanzania-safari" class="btn btn-premium btn-outline-gold"><i class="fas fa-safari"></i> <?php echo __('book_view_all'); ?></a>
        </div>
    </div>
</section>

<script>
function showPageError(message) {
    var container = document.getElementById('bookingErrorContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'bookingErrorContainer';
        container.className = 'alert alert-danger alert-dismissible fade show mt-3';
        container.setAttribute('role', 'alert');
        var form = document.getElementById('bookingFormPage');
        form.parentNode.insertBefore(container, form);
    }
    container.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>' + message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    container.style.display = 'block';
    setTimeout(function() { container.style.display = 'none'; }, 6000);
}

document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('bookingFormPage');
    if (!form) return;
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var btn = document.getElementById('bookingPageSubmit');
        var orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

        var xhr = new XMLHttpRequest();
        xhr.open('POST', form.action, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.timeout = 30000;
        xhr.onload = function() {
            try {
                var res = JSON.parse(xhr.responseText);
                if (res.success) {
                    form.reset();
                    if (typeof showBookingSuccessModal === 'function') {
                        showBookingSuccessModal(res);
                    } else {
                        // fallback if modal function isn't ready yet
                        document.querySelector('#refDisplay').textContent = res.reference || 'N/A';
                        document.querySelector('#idDisplay').textContent = '#' + (res.booking_id || '');
                        document.querySelector('#successMessage').textContent = res.message || 'Thank you for choosing Kizza Safari Tours.';
                        setTimeout(function() {
                            var modalEl = document.getElementById('bookingSuccessModal');
                            if (modalEl && typeof bootstrap !== 'undefined') {
                                var modal = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: false });
                                modal.show();
                            }
                        }, 100);
                    }
                } else {
                    showPageError(res.message || 'Submission failed');
                }
            } catch(e) {
                showPageError('Invalid response from server.');
            }
            btn.disabled = false;
            btn.innerHTML = orig;
        };
        xhr.onerror = function() {
            showPageError('Connection error. Please try again.');
            btn.disabled = false;
            btn.innerHTML = orig;
        };
        xhr.ontimeout = function() {
            showPageError('Request timed out. Please check your connection.');
            btn.disabled = false;
            btn.innerHTML = orig;
        };
        xhr.send(new URLSearchParams(new FormData(form)).toString());
    });
});
</script>
<?php include 'includes/footer.php'; ?>
