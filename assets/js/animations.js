/**
 * KIZZA TOURS & SAFARIS - Animations
 * Premium East Africa Tourism Platform
 * GSAP, AOS, ScrollTrigger Animations
 */

// GSAP Animations
document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    if (typeof gsap === 'undefined' || (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) return;

    // Short delay for layout settle
    setTimeout(() => {
        // Keep the headline and booking buttons visible while enhancing their position.
        gsap.from('.hero-title', {duration:0.45, y:20, ease:'power2.out'});
        gsap.from('.hero-subtitle', {duration:0.35, y:10, ease:'power2.out'});
        gsap.from('.hero-buttons .btn-premium', {duration:0.3, y:8, stagger:0.06, ease:'power2.out'});

        // ScrollTrigger Animations
        gsap.utils.toArray('.value-card').forEach((card, i) => {
            gsap.from(card, {
                scrollTrigger: {
                    trigger: card,
                    start: 'top 85%',
                    toggleActions: 'play none none none'
                },
                y: 24,
                opacity: 0,
                duration: 0.4,
                delay: (i % 3) * 0.06,
                ease: 'power3.out'
            });
        });

        gsap.utils.toArray('.package-card').forEach((card, i) => {
            gsap.from(card, {
                scrollTrigger: {
                    trigger: card,
                    start: 'top 85%',
                    toggleActions: 'play none none none'
                },
                y: 24,
                opacity: 0,
                duration: 0.4,
                delay: (i % 3) * 0.06,
                ease: 'power3.out'
            });
        });

        // Booking CTA stagger animation
        gsap.from('.cta-feature-item', {
            scrollTrigger: {
                trigger: '.booking-cta-card',
                start: 'top 85%',
                toggleActions: 'play none none none'
            },
            x: -30,
            opacity: 0,
            duration: 0.5,
            stagger: 0.15,
            ease: 'power3.out'
        });

        gsap.from('.booking-cta-icon', {
            scrollTrigger: {
                trigger: '.booking-cta-card',
                start: 'top 85%',
                toggleActions: 'play none none none'
            },
            scale: 0,
            rotation: -180,
            duration: 0.7,
            ease: 'back.out(1.7)'
        });

        // Counter Animation
        gsap.utils.toArray('.counter-number[data-count]').forEach(counter => {
            gsap.from(counter, {
                scrollTrigger: {
                    trigger: counter,
                    start: 'top 85%',
                    toggleActions: 'play none none none'
                },
                textContent: 0,
                duration: 2,
                ease: 'power1.out',
                snap: { textContent: 1 },
                onUpdate: function() {
                    const target = parseInt(counter.getAttribute('data-count'));
                    const current = Math.round(this.targets()[0].textContent);
                    if (current >= target) {
                        counter.textContent = target.toLocaleString() + '+';
                    } else {
                        counter.textContent = current;
                    }
                }
            });
        });

        // Create floating particles
        createParticles();

    }, 0);

    // Create floating particles in hero
    function createParticles() {
        const container = document.getElementById('particlesContainer');
        if (!container) return;

        const particleCount = 12;
        for (let i = 0; i < particleCount; i++) {
            const particle = document.createElement('div');
            particle.className = 'particle';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.width = (Math.random() * 4 + 2) + 'px';
            particle.style.height = particle.style.width;
            particle.style.animationDuration = (Math.random() * 10 + 10) + 's';
            particle.style.animationDelay = (Math.random() * 10) + 's';
            particle.style.opacity = Math.random() * 0.5 + 0.1;
            container.appendChild(particle);
        }
    }

});

// Navbar background on scroll
window.addEventListener('scroll', function() {
    const navbar = document.getElementById('mainNav');
    if (window.scrollY > 100) {
        navbar.classList.add('scrolled');
    } else {
        navbar.classList.remove('scrolled');
    }
});
