<!--=============================================
SECTION 8: BOOK YOUR ADVENTURE
=============================================-->
<section class="booking-section section-padding-lg" id="booking">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-subtitle"><?php echo __('booking_subtitle'); ?></span>
            <h2 class="section-title text-white"><?php echo __('booking_title'); ?></h2>
            <p class="section-description mx-auto text-white-50">
                <?php echo __('booking_desc'); ?>
            </p>
        </div>

        <div class="text-center" data-aos="fade-up" data-aos-delay="200">
            <div class="booking-cta-card mx-auto">
                <div class="booking-cta-icon">
                    <i class="fas fa-map-marked-alt"></i>
                </div>
                <h3 class="booking-cta-title"><?php echo __('booking_cta_title'); ?></h3>
                <p class="booking-cta-text"><?php echo __('booking_cta_text'); ?></p>
                <div class="booking-cta-features">
                    <div class="cta-feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo __('booking_cta_feature1'); ?></span>
                    </div>
                    <div class="cta-feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo __('booking_cta_feature2'); ?></span>
                    </div>
                    <div class="cta-feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo __('booking_cta_feature3'); ?></span>
                    </div>
                </div>
                <button type="button" class="btn btn-premium btn-gold btn-lg px-5 mt-3" id="openBookingModal">
                    <i class="fas fa-calendar-check me-2"></i>
                    <?php echo __('booking_cta_btn'); ?>
                </button>
                <div class="booking-cta-note mt-3">
                    <i class="fas fa-lock me-1"></i>
                    <?php echo __('booking_cta_note'); ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!--=============================================
BOOKING MODAL - Premium Popup Form
=============================================-->
<div class="booking-modal-overlay" id="bookingModalOverlay">
    <div class="booking-modal" id="bookingModal">
        <button type="button" class="booking-modal-close" id="closeBookingModal">
            <i class="fas fa-times"></i>
        </button>

        <div class="booking-modal-header">
            <div class="booking-modal-icon">
                <i class="fas fa-paper-plane"></i>
            </div>
            <h3><?php echo __('booking_modal_title'); ?></h3>
            <p><?php echo __('booking_modal_desc'); ?></p>
        </div>

        <div class="booking-modal-body">
            <form id="bookingForm" method="POST">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label"><?php echo __('booking_label_destination'); ?> <span style="color: red;">*</span></label>
                        <select class="form-select" name="destination" required>
                            <option value=""><?php echo __('booking_opt_select_dest'); ?></option>
                            <option value="serengeti"><?php echo __('booking_opt_serengeti'); ?></option>
                            <option value="maasai-mara"><?php echo __('booking_opt_maasai_mara'); ?></option>
                            <option value="ngorongoro"><?php echo __('booking_opt_ngorongoro'); ?></option>
                            <option value="kilimanjaro"><?php echo __('booking_opt_kilimanjaro'); ?></option>
                            <option value="zanzibar"><?php echo __('booking_opt_zanzibar'); ?></option>
                            <option value="bwindi"><?php echo __('booking_opt_bwindi'); ?></option>
                            <option value="volcanoes"><?php echo __('booking_opt_volcanoes'); ?></option>
                            <option value="amboseli"><?php echo __('booking_opt_amboseli'); ?></option>
                            <option value="tarangire"><?php echo __('booking_opt_tarangire'); ?></option>
                            <option value="multiple"><?php echo __('booking_opt_multiple'); ?></option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label"><?php echo __('booking_label_package'); ?></label>
                        <select class="form-select" name="tour_package">
                            <option value=""><?php echo __('booking_opt_select_pkg'); ?></option>
                            <?php
                            $bookingTours = getTourPackages(['status' => 'active'], 50);
                            if (!empty($bookingTours)):
                                foreach ($bookingTours as $bt):
                            ?>
                            <option value="<?php echo htmlspecialchars($bt['slug'] ?? ''); ?>"><?php echo htmlspecialchars($bt['title'] . ' - ' . ($bt['duration'] ?: '') . ' - $' . number_format($bt['price'] ?? 0, 0)); ?></option>
                            <?php
                                endforeach;
                            endif;
                            ?>
                            <option value="custom"><?php echo __('booking_opt_custom'); ?></option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?php echo __('booking_label_accommodation'); ?></label>
                        <select class="form-select" name="accommodation">
                            <option value=""><?php echo __('booking_opt_select_accomm'); ?></option>
                            <option value="luxury"><?php echo __('booking_opt_luxury'); ?></option>
                            <option value="mid-range"><?php echo __('booking_opt_mid_range'); ?></option>
                            <option value="budget"><?php echo __('booking_opt_budget'); ?></option>
                            <option value="tented"><?php echo __('booking_opt_tented'); ?></option>
                            <option value="resort"><?php echo __('booking_opt_resort'); ?></option>
                            <option value="mixed"><?php echo __('booking_opt_mixed'); ?></option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?php echo __('booking_label_budget'); ?></label>
                        <select class="form-select" name="budget" required>
                            <option value=""><?php echo __('booking_opt_select_budget'); ?></option>
                            <option value="1000-2000"><?php echo __('booking_opt_budget_1'); ?></option>
                            <option value="2000-3500"><?php echo __('booking_opt_budget_2'); ?></option>
                            <option value="3500-5000"><?php echo __('booking_opt_budget_3'); ?></option>
                            <option value="5000-7500"><?php echo __('booking_opt_budget_4'); ?></option>
                            <option value="7500-10000"><?php echo __('booking_opt_budget_5'); ?></option>
                            <option value="10000+"><?php echo __('booking_opt_budget_6'); ?></option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><?php echo __('booking_label_date'); ?></label>
                        <input type="date" class="form-control" name="travel_date" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><?php echo __('booking_label_guests'); ?></label>
                        <input type="number" class="form-control" name="guests" min="1" max="50" value="2" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?php echo __('booking_label_name'); ?></label>
                        <input type="text" class="form-control" name="full_name" placeholder="<?php echo __('booking_placeholder_name'); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?php echo __('booking_label_email'); ?></label>
                        <input type="email" class="form-control" name="email" placeholder="<?php echo __('booking_placeholder_email'); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?php echo __('booking_label_phone'); ?></label>
                        <input type="tel" class="form-control" name="phone" placeholder="<?php echo __('booking_placeholder_phone'); ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label"><?php echo __('booking_label_message'); ?></label>
                        <textarea class="form-control" name="message" placeholder="<?php echo __('booking_placeholder_message'); ?>" rows="3"></textarea>
                    </div>
                    <div class="col-12 text-center">
                        <button type="submit" class="btn btn-premium btn-gold btn-lg px-5" id="bookingSubmit">
                            <i class="fas fa-paper-plane"></i>
                            <?php echo __('booking_submit_btn'); ?>
                        </button>
                        <div id="priceEstimate"></div>
                    </div>
                    <div class="col-12 text-center">
                        <p class="text-white-50 small mb-0">
                            <i class="fas fa-lock me-1"></i>
                            <?php echo __('booking_form_note'); ?>
                        </p>
                    </div>
                </div>
            </form>
        </div>

        <div class="booking-modal-footer">
            <div class="d-flex gap-3 justify-content-center">
                <a href="https://wa.me/<?php echo SITE_WHATSAPP; ?>" class="btn btn-premium btn-whatsapp btn-sm" target="_blank">
                    <i class="fab fa-whatsapp"></i> <?php echo __('booking_whatsapp_btn'); ?>
                </a>
                <a href="tel:<?php echo SITE_PHONE; ?>" class="btn btn-premium btn-outline btn-sm">
                    <i class="fas fa-phone"></i> <?php echo __('booking_call_btn'); ?>
                </a>
            </div>
        </div>
    </div>
</div>
