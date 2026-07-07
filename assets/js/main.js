/**
 * KIZZA TOURS & SAFARIS - Main JavaScript
 * Premium East Africa Tourism Platform
 * Navigation, Gallery, Testimonials, FAQ, Preloader
 */

$(document).ready(function() {
    'use strict';

    //==========================================
    // SMOOTH SCROLL FOR NAV LINKS
    //==========================================
    $('a[href*="#"]').not('[href="#"]').not('[data-bs-toggle]').on('click', function(e) {
        if (location.pathname.replace(/^\//, '') === this.pathname.replace(/^\//, '') && 
            location.hostname === this.hostname) {
            const target = $(this.hash);
            target = target.length ? target : $('[name=' + this.hash.slice(1) + ']');
            if (target.length) {
                e.preventDefault();
                $('html, body').animate({
                    scrollTop: target.offset().top - 80
                }, 1000, 'easeInOutCubic');
                
                // Close mobile nav if open
                const navbarCollapse = $('#navbarNav');
                if (navbarCollapse.hasClass('show')) {
                    navbarCollapse.collapse('hide');
                }
            }
        }
    });

    //==========================================
    // NAVBAR ACTIVE LINK ON SCROLL
    //==========================================
    $(window).on('scroll', function() {
        const scrollPos = $(window).scrollTop() + 200;
        
        $('section').each(function() {
            const sectionTop = $(this).offset().top;
            const sectionBottom = sectionTop + $(this).outerHeight();
            const sectionId = $(this).attr('id');
            
            if (sectionId && scrollPos >= sectionTop && scrollPos < sectionBottom) {
                $('.navbar .nav-link').removeClass('active');
                $(`.navbar .nav-link[href="#${sectionId}"]`).addClass('active');
            }
        });
    });

    //==========================================
    // TESTIMONIALS SWIPER
    //==========================================
    const testimonialSwiper = new Swiper('.testimonialSwiper', {
        slidesPerView: 1,
        spaceBetween: 30,
        loop: true,
        autoplay: {
            delay: 5000,
            disableOnInteraction: false,
        },
        pagination: {
            el: '.swiper-pagination',
            clickable: true,
        },
        breakpoints: {
            768: {
                slidesPerView: 2,
            },
            992: {
                slidesPerView: 1,
            }
        }
    });

    //==========================================
    // GALLERY FILTER
    //==========================================
    $('.gallery-filter button').on('click', function() {
        const filterValue = $(this).data('filter');
        
        // Update active state
        $('.gallery-filter button').removeClass('active');
        $(this).addClass('active');
        
        // Filter items
        if (filterValue === 'all') {
            $('.gallery-item').show();
        } else {
            $('.gallery-item').hide();
            $(`.gallery-item[data-category="${filterValue}"]`).show();
        }
    });

    //==========================================
    // PACKAGES FILTER
    //==========================================
    $('.packages-filter button').on('click', function() {
        const filterValue = $(this).data('filter');
        
        $('.packages-filter button').removeClass('active');
        $(this).addClass('active');
        
        if (filterValue === 'all') {
            $('.package-item').show();
        } else {
            $('.package-item').hide();
            $(`.package-item[data-category="${filterValue}"]`).show();
        }
    });

    //==========================================
    // SCROLL INDICATOR CLICK
    //==========================================
    $('#scrollIndicator').on('click', function() {
        $('html, body').animate({
            scrollTop: $('#destinations').offset().top - 80
        }, 1000);
    });

    //==========================================
    // TOAST NOTIFICATION SYSTEM (Bootstrap 5)
    //==========================================
    window.showToast = function(message, type) {
        const container = $('#toastContainer');
        const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        const title = type === 'success' ? 'Success' : 'Error';
        const toastId = 'toast-' + Date.now();
        const toast = $(`
            <div id="${toastId}" class="toast ${type}" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header">
                    <i class="fas ${icon} me-2"></i>
                    <strong class="me-auto">${title}</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">${message}</div>
            </div>
        `);
        container.append(toast);
        const bsToast = new bootstrap.Toast(toast[0], { delay: 5000 });
        bsToast.show();
        toast.on('hidden.bs.toast', function() { $(this).remove(); });
    };

    //==========================================
    // CONTACT FORM SUBMISSION
    //==========================================
    $('#contactForm').on('submit', function(e) {
        e.preventDefault();
        
        const submitBtn = $('#contactSubmit');
        const originalText = submitBtn.html();
        
        // Disable and show loading
        submitBtn.prop('disabled', true);
        submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Sending...');
        
        $.ajax({
            url: 'api/contact.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast(response.message || 'Message sent successfully! We will get back to you within 24 hours.', 'success');
                    $('#contactForm')[0].reset();
                } else {
                    showToast(response.message || 'Failed to send message. Please try again.', 'error');
                }
            },
            error: function() {
                showToast('Connection error. Please try again or contact us via WhatsApp.', 'error');
            },
            complete: function() {
                submitBtn.prop('disabled', false);
                submitBtn.html(originalText);
            }
        });
    });

    //==========================================
    // COUNTDOWN / STATS COUNTER (FALLBACK)
    //==========================================
    function animateCounter(element, target) {
        const duration = 2000;
        const steps = 60;
        const increment = target / steps;
        let current = 0;
        let step = 0;
        
        const timer = setInterval(function() {
            step++;
            current += increment;
            
            if (step >= steps) {
                current = target;
                clearInterval(timer);
            }
            
            element.text(Math.floor(current).toLocaleString());
        }, duration / steps);
    }

    // Intersection Observer for counters
    if (window.IntersectionObserver) {
        const counterObserver = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    const counter = $(entry.target);
                    const target = parseInt(counter.data('count') || counter.text().replace(/[^0-9]/g, ''));
                    if (target > 0 && !counter.hasClass('counted')) {
                        counter.addClass('counted');
                        animateCounter(counter, target);
                    }
                }
            });
        }, { threshold: 0.5 });
        
        $('.counter-number[data-count]').each(function() {
            counterObserver.observe(this);
        });
    }

    //==========================================
    // PARALLAX ON SCROLL (Simple)
    //==========================================
    $(window).on('scroll', function() {
        const scrollPos = $(window).scrollTop();
        
        $('.parallax-bg').each(function() {
            const speed = $(this).data('speed') || 0.5;
            const offset = scrollPos * speed;
            $(this).css('transform', 'translateY(' + offset + 'px)');
        });
    });

    //==========================================
    // LIGHTBOX CONFIG
    //==========================================
    if (typeof lightbox !== 'undefined') {
        lightbox.option({
            'resizeDuration': 0,
            'wrapAround': true,
            'albumLabel': 'Image %1 of %2',
            'fadeDuration': 0,
            'imageFadeDuration': 0
        });
    }

    //==========================================
    // PACKAGE DETAIL MODAL
    //==========================================
    const $pkgOverlay = $('#pkgModalOverlay');
    const $pkgModal = $('#pkgModal');
    let pkgModalOpen = false;

    function renderPackageModal(pkg) {
        const img = pkg.image || 'assets/images/placeholder.svg' + encodeURIComponent(pkg.title);
        const galleryImages = pkg.gallery && typeof pkg.gallery === 'string' && pkg.gallery.trim()
            ? pkg.gallery.split(',').map(function(s) { return s.trim(); }).filter(Boolean)
            : [img];

        const highlights = pkg.highlights
            ? pkg.highlights.split(',').map(function(s) { return s.trim(); }).filter(Boolean)
            : [];
        const includes = pkg.includes
            ? pkg.includes.split(',').map(function(s) { return s.trim(); }).filter(Boolean)
            : [];
        const excludes = pkg.excludes
            ? pkg.excludes.split(',').map(function(s) { return s.trim(); }).filter(Boolean)
            : [];

        var html = '<div class="pkg-modal-gallery" id="pkgGallery">';
        galleryImages.forEach(function(url, idx) {
            html += '<img src="' + url + '" alt="' + pkg.title + '" style="display:' + (idx === 0 ? 'block' : 'none') + '; position:absolute;" onerror="this.src=\'assets/images/placeholder.svg' + encodeURIComponent(pkg.title) + '\'">';
        });
        if (galleryImages.length > 1) {
            html += '<button type="button" class="pkg-modal-gallery-nav prev" id="pkgGalleryPrev"><i class="fas fa-chevron-left"></i></button>';
            html += '<button type="button" class="pkg-modal-gallery-nav next" id="pkgGalleryNext"><i class="fas fa-chevron-right"></i></button>';
            html += '<div class="pkg-modal-gallery-dots" id="pkgGalleryDots">';
            galleryImages.forEach(function(url, idx) {
                html += '<span class="' + (idx === 0 ? 'active' : '') + '" data-index="' + idx + '"></span>';
            });
            html += '</div>';
        }
        html += '</div>';

        html += '<div class="pkg-modal-info">';
        html += '<div class="pkg-meta">';
        if (pkg.duration) html += '<span><i class="fas fa-clock"></i> ' + pkg.duration + '</span>';
        if (pkg.country) html += '<span><i class="fas fa-map-marker-alt"></i> ' + pkg.country + '</span>';
        if (pkg.max_guests) html += '<span><i class="fas fa-users"></i> Max ' + pkg.max_guests + ' guests</span>';
        html += '</div>';
        html += '<h2>' + pkg.title + '</h2>';
        html += '<div class="pkg-rating">';
        var rating = Math.floor(pkg.rating || 5);
        for (var s = 0; s < 5; s++) {
            html += '<i class="fas fa-star" style="color:' + (s < rating ? 'var(--secondary)' : '#ddd') + '"></i>';
        }
        html += '</div>';
        html += '<div class="pkg-price">$' + Number(pkg.price).toLocaleString() + ' <small>/ person</small></div>';
        if (pkg.description) {
            html += '<div class="pkg-description">' + pkg.description + '</div>';
        }
        if (highlights.length) {
            html += '<div class="pkg-section-title">Highlights</div>';
            html += '<div class="pkg-highlights-list">';
            highlights.forEach(function(h) { html += '<span>' + h + '</span>'; });
            html += '</div>';
        }
        if (pkg.itinerary) {
            html += '<div class="pkg-section-title">Itinerary</div>';
            html += '<div class="pkg-itinerary" style="font-size:0.85rem;color:var(--text-color);line-height:1.8;margin-bottom:0.75rem;white-space:pre-line;">' + pkg.itinerary + '</div>';
        }
        html += '<div class="pkg-includes">';
        html += '<div class="pkg-includes-grid">';
        includes.forEach(function(item) {
            html += '<div class="item inc"><i class="fas fa-check-circle"></i> ' + item + '</div>';
        });
        excludes.forEach(function(item) {
            html += '<div class="item exc"><i class="fas fa-times-circle"></i> ' + item + '</div>';
        });
        html += '</div></div>';

        html += '<div class="pkg-modal-book-btn">';
        html += '<button type="button" class="btn btn-premium btn-gold pkg-book-btn"><i class="fas fa-calendar-check"></i> Book This Tour</button>';
        html += '</div></div>';

        $('.pkg-modal-content').html(html);

        // Gallery navigation
        var currentIdx = 0;
        var $gallery = $('#pkgGallery');
        var $imgs = $gallery.find('img');

        function showGalleryImage(idx) {
            $imgs.hide().eq(idx).show();
            $('#pkgGalleryDots span').removeClass('active').eq(idx).addClass('active');
            currentIdx = idx;
        }

        $('#pkgGalleryPrev').off('click').on('click', function() {
            var next = (currentIdx - 1 + $imgs.length) % $imgs.length;
            showGalleryImage(next);
        });
        $('#pkgGalleryNext').off('click').on('click', function() {
            var next = (currentIdx + 1) % $imgs.length;
            showGalleryImage(next);
        });
        $('#pkgGalleryDots').off('click', 'span').on('click', 'span', function() {
            showGalleryImage(parseInt($(this).data('index')));
        });
    }

    function openPkgModal(pkgIndex) {
        if (pkgModalOpen) return;

        var pkg = window.__packageData && window.__packageData[pkgIndex];
        if (!pkg) return;

        renderPackageModal(pkg);
        pkgModalOpen = true;

        $pkgOverlay.css('display', 'flex');
        $('body').css('overflow', 'hidden');

        gsap.to($pkgOverlay, { opacity: 1, duration: 0.4, ease: 'power2.out' });
        gsap.fromTo($pkgModal, { scale: 0.85, y: 40, opacity: 0 }, {
            scale: 1, y: 0, opacity: 1, duration: 0.6, delay: 0.1, ease: 'back.out(1.7)'
        });
    }

    function closePkgModal() {
        if (!pkgModalOpen) return;
        gsap.to($pkgModal, {
            scale: 0.9, y: 30, opacity: 0, duration: 0.3, ease: 'power2.in',
            onComplete: function() {
                $pkgOverlay.css('display', 'none');
                $('body').css('overflow', '');
                pkgModalOpen = false;
            }
        });
        gsap.to($pkgOverlay, { opacity: 0, duration: 0.3, ease: 'power2.in' });
    }

    // Book This Tour button inside package detail modal
    $(document).on('click', '.pkg-book-btn', function() {
        closePkgModal();
        setTimeout(function() {
            // Scroll to booking section then open modal
            $('html, body').animate({
                scrollTop: $('#booking').offset().top - 100
            }, 400, function() {
                setTimeout(openBookingModal, 300);
            });
        }, 350);
    });

    // View Details buttons (link + modal)
    $(document).on('click', '.view-details-btn', function(e) {
        e.preventDefault();
        var idx = $(this).data('package-index');
        if (idx !== undefined) {
            openPkgModal(parseInt(idx));
        }
    });

    // Close package modal
    $('#closePkgModal').on('click', closePkgModal);
    $pkgOverlay.on('click', function(e) {
        if ($(e.target).is($pkgOverlay)) closePkgModal();
    });
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && pkgModalOpen) closePkgModal();
    });

    console.log('%c KIZZA TOURS & SAFARIS ', 'background: #0A2540; color: #D4AF37; font-size: 16px; font-weight: bold; padding: 10px 20px; border-radius: 4px;');
    console.log('%c Discover East Africa Beyond Expectations ', 'color: #D4AF37; font-size: 12px;');
});
