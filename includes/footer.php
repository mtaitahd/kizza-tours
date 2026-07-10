<?php
$siteEmail = getSetting('site_email', SITE_EMAIL);
$sitePhone = getSetting('site_phone', SITE_PHONE);
$siteWhatsapp = getSetting('site_whatsapp', SITE_WHATSAPP);
$siteAddress = getSetting('site_address', SITE_ADDRESS);
$fbUrl = getSetting('facebook_url', '#');
$igUrl = getSetting('instagram_url', '#');
$twUrl = getSetting('twitter_url', '#');
$ytUrl = getSetting('youtube_url', '#');
$taUrl = getSetting('tripadvisor_url', '#');
?>
<footer class="footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="brand-footer"><?php echo __('footer_brand'); ?></div>
                <p class="mt-3" style="color: rgba(255,255,255,0.6);"><?php echo __('footer_desc'); ?></p>
                <div class="social-links mt-4">
                    <a href="<?php echo $fbUrl; ?>" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="<?php echo $igUrl; ?>" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="<?php echo $twUrl; ?>" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                    <a href="<?php echo $ytUrl; ?>" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                    <a href="<?php echo $taUrl; ?>" aria-label="TripAdvisor"><i class="fab fa-tripadvisor"></i></a>
                </div>
            </div>
            <div class="col-lg-2 col-md-4">
                <h5><?php echo __('footer_company'); ?></h5>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/about-us"><?php echo __('footer_about'); ?></a></li>
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/contact-us"><?php echo __('footer_contact'); ?></a></li>
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/book-tour"><?php echo __('footer_book'); ?></a></li>
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/about-us#faq"><?php echo __('footer_faq'); ?></a></li>
                </ul>
            </div>
            <div class="col-lg-2 col-md-4">
                <h5><?php echo __('footer_tours'); ?></h5>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/tanzania-safari"><?php echo __('footer_tour_tz'); ?></a></li>
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/kenya-tanzania-safari"><?php echo __('footer_tour_ke_tz'); ?></a></li>
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/uganda-tours"><?php echo __('footer_tour_ug'); ?></a></li>
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/zanzibar-holidays"><?php echo __('footer_tour_zanzibar'); ?></a></li>
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/burundi-tours"><?php echo __('footer_tour_bi'); ?></a></li>
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/rwanda-gorilla-trekking"><?php echo __('footer_tour_rw'); ?></a></li>
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/mount-kenya-climbing"><?php echo __('footer_tour_kenya'); ?></a></li>
                </ul>
            </div>
            <div class="col-lg-4 col-md-4">
                <h5><?php echo __('footer_contact_title'); ?></h5>
                <ul class="list-unstyled">
                    <li class="mb-3"><i class="fas fa-map-marker-alt me-2 text-gold" style="color: var(--secondary);"></i> <?php echo htmlspecialchars($siteAddress); ?></li>
                    <li class="mb-3"><i class="fas fa-phone me-2 text-gold" style="color: var(--secondary);"></i> <a href="tel:<?php echo $sitePhone; ?>"><?php echo htmlspecialchars($sitePhone); ?></a></li>
                    <li class="mb-3"><i class="fas fa-envelope me-2 text-gold" style="color: var(--secondary);"></i> <a href="mailto:<?php echo $siteEmail; ?>"><?php echo htmlspecialchars($siteEmail); ?></a></li>
                    <li><i class="fab fa-whatsapp me-2 text-gold" style="color: var(--secondary);"></i> <a href="https://wa.me/<?php echo $siteWhatsapp; ?>" target="_blank"><?php echo __('footer_whatsapp'); ?></a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> <?php echo __('footer_brand'); ?>. <?php echo __('footer_copyright'); ?>.</p>
        </div>
    </div>
</footer>

<!-- Bootstrap Icons (deferred) -->
<link rel="preload" as="style" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"></noscript>

<!-- Scripts (deferred for performance) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js" defer></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/js/lightbox.min.js" defer></script>

<!-- Main JavaScript (minified) -->
<script src="<?php echo ASSETS_PATH; ?>js/animations.min.js?v=2" defer></script>
<script src="<?php echo ASSETS_PATH; ?>js/main.min.js?v=2" defer></script>
<script src="<?php echo ASSETS_PATH; ?>js/booking.min.js?v=2" defer></script>

<!-- Booking Success Modal -->
<div class="modal fade" id="bookingSuccessModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content booking-success-content">
      <div class="modal-body p-0">
        <button type="button" class="btn-close booking-success-close" data-bs-dismiss="modal" aria-label="Close"></button>

        <!-- Icon -->
        <div class="text-center pt-5">
          <div class="success-icon-wrap">
            <div class="success-icon-circle">
              <i class="bi bi-check-lg"></i>
            </div>
          </div>
        </div>

        <!-- Heading -->
        <div class="text-center px-4 mt-4">
          <h3 class="booking-success-heading">Booking Request Received Successfully!</h3>
          <p class="booking-success-text" id="successMessage">Thank you for choosing Kizza Tours and Safaris for your adventure journey. Please allow us a little time to prepare and send you your itinerary package.</p>
        </div>

        <!-- Reference Card -->
        <div class="container px-4 mt-4">
          <div class="reference-card">
            <div class="row g-0">
              <div class="col-md-7 border-md-end">
                <div class="reference-item p-3">
                  <span class="reference-label">Booking Reference</span>
                  <div class="d-flex align-items-center gap-2">
                    <span class="reference-value" id="refDisplay"></span>
                    <button class="copy-ref-btn" id="copyRefBtn" title="Copy reference">
                      <i class="bi bi-clipboard"></i>
                    </button>
                  </div>
                </div>
              </div>
              <div class="col-md-5">
                <div class="reference-item p-3">
                  <span class="reference-label">Booking ID</span>
                  <span class="reference-value" id="idDisplay"></span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Next Steps -->
        <div class="container px-4 mt-4">
          <h5 class="next-steps-heading">What Happens Next</h5>
          <div class="row g-2">
            <div class="col-sm-6">
              <div class="step-item">
                <i class="bi bi-check-circle-fill step-icon step-done"></i>
                <span>Request received</span>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="step-item">
                <i class="bi bi-person-check-fill step-icon step-active"></i>
                <span>Travel specialist assigned</span>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="step-item">
                <i class="bi bi-map-fill step-icon step-pending"></i>
                <span>Custom itinerary preparation</span>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="step-item">
                <i class="bi bi-clock-fill step-icon step-pending"></i>
                <span>Contact within 24 hours</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="container px-4 mt-4 pb-4">
          <div class="row g-2">
            <div class="col-sm-4">
              <a href="#" class="btn btn-gold w-100 booking-action-btn" id="trackBookingBtn"><i class="bi bi-geo-alt-fill"></i> Track Booking</a>
            </div>
            <div class="col-sm-4">
              <a href="<?php echo SITE_URL; ?>" class="btn btn-outline-gold w-100 booking-action-btn"><i class="bi bi-house-fill"></i> Return Home</a>
            </div>
            <div class="col-sm-4">
              <a href="<?php echo SITE_URL; ?>/contact-us" class="btn btn-outline-secondary w-100 booking-action-btn"><i class="bi bi-headset"></i> Contact Support</a>
            </div>
          </div>
        </div>

        <!-- Info Box -->
        <div class="container px-4 pb-5">
          <div class="info-box">
            <i class="bi bi-info-circle-fill me-2"></i>
            Please save your booking reference number for future communication.
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
/* ── Booking Success Modal ── */
.booking-success-content {
  border: none;
  border-radius: 24px;
  background: #fff;
  box-shadow: 0 30px 80px rgba(0,0,0,0.2);
  position: relative;
  overflow: hidden;
}
.booking-success-content::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 5px;
  background: linear-gradient(90deg, #D4AF37, #0A2540, #D4AF37);
}
.booking-success-close {
  position: absolute;
  top: 1.2rem;
  right: 1.2rem;
  z-index: 10;
  font-size: 1.1rem;
  opacity: 0.6;
  transition: opacity 0.2s;
}
.booking-success-close:hover { opacity: 1; }

/* Icon */
.success-icon-wrap {
  animation: successBounce 0.8s ease;
}
.success-icon-circle {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: linear-gradient(135deg, #28a745, #20c997);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto;
  box-shadow: 0 8px 30px rgba(40,167,69,0.3);
  animation: pulseGlow 2s ease-in-out infinite;
}
.success-icon-circle i {
  font-size: 2.8rem;
  color: #fff;
}

/* Text */
.booking-success-heading {
  font-family: 'Cormorant Garamond', serif;
  font-weight: 700;
  color: #0A2540;
  font-size: 1.6rem;
}
.booking-success-text {
  color: #6c757d;
  font-size: 0.95rem;
  max-width: 520px;
  margin: 0.5rem auto 0;
  line-height: 1.6;
}

/* Reference Card */
.reference-card {
  background: #f8f9fa;
  border-radius: 16px;
  border: 1px solid #e9ecef;
  overflow: hidden;
}
.reference-label {
  display: block;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: #adb5bd;
  font-weight: 600;
  margin-bottom: 0.25rem;
}
.reference-value {
  font-family: 'Cormorant Garamond', serif;
  font-size: 1.3rem;
  font-weight: 700;
  color: #0A2540;
  letter-spacing: 0.5px;
}
.copy-ref-btn {
  background: none;
  border: 1px solid #dee2e6;
  border-radius: 8px;
  padding: 0.35rem 0.6rem;
  color: #6c757d;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 0.9rem;
}
.copy-ref-btn:hover {
  background: #0A2540;
  color: #fff;
  border-color: #0A2540;
}

/* Next Steps */
.next-steps-heading {
  font-family: 'Cormorant Garamond', serif;
  font-weight: 700;
  color: #0A2540;
  font-size: 1.1rem;
  margin-bottom: 0.75rem;
}
.step-item {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.5rem 0.75rem;
  border-radius: 10px;
  background: #f8f9fa;
  font-size: 0.88rem;
  color: #495057;
  transition: all 0.2s;
}
.step-item:hover {
  background: #fff;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.step-icon { font-size: 1.1rem; }
.step-done { color: #28a745; }
.step-active { color: #D4AF37; }
.step-pending { color: #adb5bd; }

/* Action Buttons */
.booking-action-btn {
  border-radius: 12px !important;
  padding: 0.7rem 0.5rem !important;
  font-size: 0.85rem !important;
  font-weight: 600 !important;
  transition: all 0.3s ease !important;
}
.booking-action-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(0,0,0,0.12);
}
.btn-outline-gold {
  border: 2px solid #D4AF37;
  color: #0A2540;
  background: transparent;
}
.btn-outline-gold:hover {
  background: #D4AF37;
  color: #fff;
}

/* Info Box */
.info-box {
  background: #fff8e1;
  border: 1px solid #ffeeba;
  border-radius: 12px;
  padding: 0.8rem 1rem;
  font-size: 0.85rem;
  color: #856404;
  display: flex;
  align-items: center;
}
.info-box i { color: #D4AF37; font-size: 1rem; }

/* Animations */
@keyframes successBounce {
  0% { transform: scale(0); opacity: 0; }
  50% { transform: scale(1.15); }
  100% { transform: scale(1); opacity: 1; }
}
@keyframes pulseGlow {
  0%, 100% { box-shadow: 0 8px 30px rgba(40,167,69,0.3); }
  50% { box-shadow: 0 8px 40px rgba(40,167,69,0.5); }
}

/* Dark Mode */
@media (prefers-color-scheme: dark) {
  .booking-success-content { background: #0D2E4A; }
  .booking-success-content::before { background: linear-gradient(90deg, #D4AF37, #1a5276, #D4AF37); }
  .booking-success-heading { color: #fff; }
  .booking-success-text { color: rgba(255,255,255,0.7); }
  .booking-success-close { filter: invert(1); }
  .reference-card { background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.1); }
  .reference-value { color: #fff; }
  .reference-label { color: rgba(255,255,255,0.4); }
  .copy-ref-btn { color: rgba(255,255,255,0.5); border-color: rgba(255,255,255,0.15); }
  .copy-ref-btn:hover { background: #D4AF37; color: #0A2540; border-color: #D4AF37; }
  .step-item { background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.7); }
  .step-item:hover { background: rgba(255,255,255,0.08); }
  .btn-outline-gold { border-color: #D4AF37; color: #D4AF37; }
  .btn-outline-gold:hover { background: #D4AF37; color: #0A2540; }
  .btn-outline-secondary { border-color: rgba(255,255,255,0.2); color: rgba(255,255,255,0.7); }
  .btn-outline-secondary:hover { background: rgba(255,255,255,0.1); color: #fff; }
  .info-box { background: rgba(212,175,55,0.12); border-color: rgba(212,175,55,0.25); color: #D4AF37; }
}

/* Responsive */
@media (max-width: 575.98px) {
  .booking-success-heading { font-size: 1.25rem; }
  .success-icon-circle { width: 64px; height: 64px; }
  .success-icon-circle i { font-size: 2.2rem; }
  .reference-value { font-size: 1rem; }
  .step-item { font-size: 0.82rem; }
  .booking-action-btn { font-size: 0.8rem !important; }
  .border-md-end { border-bottom: 1px solid #e9ecef !important; border-right: none !important; }
}
@media (prefers-color-scheme: dark) and (max-width: 575.98px) {
  .border-md-end { border-bottom-color: rgba(255,255,255,0.1) !important; }
}
</style>

<script>
function showBookingSuccessModal(data) {
  document.getElementById('refDisplay').textContent = data.reference || 'N/A';
  document.getElementById('idDisplay').textContent = '#' + (data.booking_id || '');
  document.getElementById('successMessage').textContent = data.message || 'Thank you for choosing Kizza Tours and Safaris for your adventure journey. Please allow us a little time to prepare and send you your itinerary package.';

  var modal = new bootstrap.Modal(document.getElementById('bookingSuccessModal'), {
    backdrop: 'static',
    keyboard: false
  });
  modal.show();
}

document.addEventListener('DOMContentLoaded', function() {
  var copyBtn = document.getElementById('copyRefBtn');
  if (copyBtn) {
    copyBtn.addEventListener('click', function() {
      var ref = document.getElementById('refDisplay').textContent;
      navigator.clipboard.writeText(ref).then(function() {
        var icon = copyBtn.querySelector('i');
        icon.className = 'bi bi-check-lg';
        setTimeout(function() { icon.className = 'bi bi-clipboard'; }, 2000);
      });
    });
  }
});
</script>

</body>
</html>
