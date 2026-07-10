/**
 * KIZZA TOURS & SAFARIS - Booking System
 * Premium East Africa Tourism Platform
 * Modal popup with GSAP animations + AJAX submission
 */

$(document).ready(function() {
    'use strict';

    //==========================================
    // BOOKING MODAL OPEN / CLOSE
    //==========================================
    const $overlay = $('#bookingModalOverlay');
    const $modal = $('#bookingModal');
    const $body = $('body');
    let modalOpen = false;

    window.openBookingModal = function openBookingModal() {
        if (modalOpen) return;
        modalOpen = true;

        $overlay.css('display', 'flex');
        $body.css('overflow', 'hidden');

        // GSAP entrance animation
        gsap.to($overlay, {
            opacity: 1,
            duration: 0.4,
            ease: 'power2.out'
        });

        gsap.fromTo($modal, {
            scale: 0.85,
            y: 40,
            opacity: 0
        }, {
            scale: 1,
            y: 0,
            opacity: 1,
            duration: 0.6,
            delay: 0.1,
            ease: 'back.out(1.7)'
        });

        // Animate header elements
        gsap.from('.booking-modal-icon', {
            scale: 0,
            rotation: -180,
            duration: 0.5,
            delay: 0.3,
            ease: 'back.out(2)'
        });

        gsap.from('.booking-modal-header h3', {
            y: 20,
            opacity: 0,
            duration: 0.4,
            delay: 0.4,
            ease: 'power2.out'
        });

        gsap.from('.booking-modal-header p', {
            y: 15,
            opacity: 0,
            duration: 0.4,
            delay: 0.5,
            ease: 'power2.out'
        });

        gsap.from('.booking-modal-body .row > div', {
            y: 20,
            opacity: 0,
            stagger: 0.03,
            duration: 0.4,
            delay: 0.55,
            ease: 'power2.out'
        });
    }

    function closeBookingModal() {
        if (!modalOpen) return;

        gsap.to($modal, {
            scale: 0.9,
            y: 30,
            opacity: 0,
            duration: 0.3,
            ease: 'power2.in',
            onComplete: function() {
                $overlay.css('display', 'none');
                $body.css('overflow', '');
                modalOpen = false;
            }
        });

        gsap.to($overlay, {
            opacity: 0,
            duration: 0.3,
            ease: 'power2.in'
        });
    }

    // Open modal triggers
    $('#openBookingModal').on('click', openBookingModal);
    $('#navBookingBtn').on('click', openBookingModal);

    // Close modal triggers
    $('#closeBookingModal').on('click', closeBookingModal);
    $overlay.on('click', function(e) {
        if ($(e.target).is($overlay)) {
            closeBookingModal();
        }
    });

    // Close on Escape key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && modalOpen) {
            closeBookingModal();
        }
    });

    //==========================================
    // INLINE ERROR MESSAGE (no SweetAlert)
    //==========================================
    function showInlineError(form, message) {
        var container = form.find('.booking-error-container');
        if (!container.length) {
            container = $('<div class="booking-error-container alert alert-danger alert-dismissible fade show mt-3" role="alert">');
            form.prepend(container);
        }
        container.html('<i class="fas fa-exclamation-circle me-2"></i>' + message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>');
        container.show();
        gsap.fromTo(container[0], { opacity: 0, y: -10 }, { opacity: 1, y: 0, duration: 0.3, ease: 'power2.out' });
        setTimeout(function() {
            container.fadeOut(400, function() { container.remove(); });
        }, 6000);
    }

    //==========================================
    // BOOKING FORM SUBMISSION
    //==========================================
    $('#bookingForm').on('submit', function(e) {
        e.preventDefault();

        const form = $(this);
        const submitBtn = $('#bookingSubmit');
        const originalText = submitBtn.html();

        // Basic validation
        let isValid = true;
        form.find('[required]').each(function() {
            if (!$(this).val().trim()) {
                $(this).addClass('is-invalid');
                isValid = false;
            } else {
                $(this).removeClass('is-invalid');
            }
        });

        if (!isValid) {
            showInlineError(form, 'Please fill in all required fields.');
            return;
        }

        // Validate email format
        const email = form.find('input[name="email"]').val();
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showInlineError(form, 'Please enter a valid email address.');
            form.find('input[name="email"]').addClass('is-invalid');
            return;
        }

        // Disable and show loading
        submitBtn.prop('disabled', true);
        submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Processing...');

        // Shake button on submit
        gsap.to(submitBtn, {
            x: [-5, 5, -5, 5, 0],
            duration: 0.3,
            ease: 'power2.out'
        });

        $.ajax({
            url: 'api/submit-booking.php',
            type: 'POST',
            data: form.serialize(),
            dataType: 'json',
            timeout: 30000,
            success: function(response) {
                if (response.success) {
                    form[0].reset();
                    $('#priceEstimate').empty();

                    closeBookingModal();
                    showBookingSuccessModal(response);
                } else {
                    showInlineError(form, response.message || 'Failed to submit booking. Please try again.');
                }
            },
            error: function(xhr, status, error) {
                let message = 'Connection error. Please try again or contact us via WhatsApp.';
                if (status === 'timeout') {
                    message = 'Request timed out. Please check your connection and try again.';
                }
                showInlineError(form, message);
                console.error('Booking Error:', error);
            },
            complete: function() {
                submitBtn.prop('disabled', false);
                submitBtn.html(originalText);
            }
        });
    });

    //==========================================
    // REAL-TIME PRICE ESTIMATOR
    //==========================================
    function updatePriceEstimate() {
        const destination = $('select[name="destination"]').val();
        const guests = parseInt($('input[name="guests"]').val()) || 1;

        // Base prices by destination
        const basePrices = {
            'serengeti': 1200,
            'maasai-mara': 1100,
            'ngorongoro': 1000,
            'kilimanjaro': 1500,
            'zanzibar': 800,
            'bwindi': 1800,
            'volcanoes': 2000,
            'amboseli': 900,
            'tarangire': 950,
            'mount-kenya': 1100
        };

        const basePrice = basePrices[destination] || 1000;
        const totalEstimate = basePrice * guests;

        // Update estimate if element exists
        const estimateEl = $('#priceEstimate');
        if (estimateEl.length && destination) {
            estimateEl.html(`
                <div class="glass-gold p-3 rounded-3 mt-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-white-50">Estimated from:</span>
                        <span class="text-white fw-bold" style="font-family: var(--font-primary); font-size: 1.5rem; color: var(--secondary) !important;">
                            $${totalEstimate.toLocaleString()}
                        </span>
                    </div>
                    <small class="text-white-50">per person for ${guests} guest(s)</small>
                </div>
            `);

            gsap.from(estimateEl.children(), {
                y: 10,
                opacity: 0,
                duration: 0.3,
                ease: 'power2.out'
            });
        }
    }

    // Trigger price update on field change
    $(document).on('change', 'select[name="destination"], input[name="guests"]', function() {
        updatePriceEstimate();
    });

    //==========================================
    // SMART FORM AUTO-FILL FROM URL PARAMS
    //==========================================
    function getUrlParams() {
        const params = new URLSearchParams(window.location.search);
        if (params.has('package')) {
            var pkg = params.get('package');
            $('input[name="packages[]"][value="' + pkg + '"]').prop('checked', true);
        }
        if (params.has('destination')) {
            $('select[name="destination"]').val(params.get('destination'));
        }
    }

    getUrlParams();

    //==========================================
    // INPUT MASK FOR PHONE
    //==========================================
    $('input[type="tel"]').on('input', function() {
        let value = $(this).val().replace(/[^0-9+]/g, '');
        $(this).val(value);
    });

    //==========================================
    // DATE RESTRICTION (no past dates)
    //==========================================
    const today = new Date().toISOString().split('T')[0];
    $('input[type="date"]').attr('min', today);
});
