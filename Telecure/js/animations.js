/* TeleCure - animations.js */

document.addEventListener('DOMContentLoaded', function () {

    // ---- HERO SECTION STAGGERED ENTRANCE ----
    const heroElements = [
        document.querySelector('.hero-badge'),
        document.querySelector('.hero-title'),
        document.querySelector('.hero-subtitle'),
        document.querySelector('.hero-actions'),
        document.querySelector('.hero-stats'),
    ];

    heroElements.forEach((el, i) => {
        if (!el) return;
        el.style.opacity = '0';
        el.style.transform = 'translateY(28px)';
        el.style.transition = `opacity 0.7s ease ${i * 0.12}s, transform 0.7s ease ${i * 0.12}s`;
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                el.style.opacity = '1';
                el.style.transform = 'translateY(0)';
            });
        });
    });

    // ---- HERO VISUAL ENTRANCE ----
    const heroVisual = document.querySelector('.hero-visual');
    if (heroVisual) {
        heroVisual.style.opacity = '0';
        heroVisual.style.transform = 'translateX(40px)';
        heroVisual.style.transition = 'opacity 0.9s ease 0.4s, transform 0.9s ease 0.4s';
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                heroVisual.style.opacity = '1';
                heroVisual.style.transform = 'translateX(0)';
            });
        });
    }

    // ---- INTERSECTION OBSERVER for fade-in sections ----
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const el = entry.target;
                const delay = parseInt(el.dataset.delay || '0');
                setTimeout(() => {
                    el.classList.add('animated');
                }, delay);
                observer.unobserve(el);
            }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -50px 0px' });

    document.querySelectorAll('[data-aos]').forEach(el => observer.observe(el));

    // ---- FEATURE CARDS STAGGER ----
    const featureCards = document.querySelectorAll('.feature-card');
    const featureObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const cards = entry.target.querySelectorAll('.feature-card');
                cards.forEach((card, i) => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(30px)';
                    card.style.transition = `opacity 0.6s ease ${i * 0.1}s, transform 0.6s ease ${i * 0.1}s`;
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 100 + i * 80);
                });
                featureObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    const featuresGrid = document.querySelector('.features-grid');
    if (featuresGrid) featureObserver.observe(featuresGrid);

    // ---- DOCTOR CARDS STAGGER ----
    const doctorsGrid = document.querySelector('.doctors-grid');
    if (doctorsGrid) {
        const docObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.querySelectorAll('.doctor-card').forEach((card, i) => {
                        card.style.opacity = '0';
                        card.style.transform = 'translateY(24px)';
                        card.style.transition = `opacity 0.5s ease ${i * 0.08}s, transform 0.5s ease ${i * 0.08}s`;
                        setTimeout(() => {
                            card.style.opacity = '1';
                            card.style.transform = 'translateY(0)';
                        }, 60 + i * 60);
                    });
                    docObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });
        docObserver.observe(doctorsGrid);
    }

    // ---- STEP CARDS ANIMATION ----
    const stepsContainer = document.querySelector('.steps-container');
    if (stepsContainer) {
        const stepObs = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.querySelectorAll('.step').forEach((step, i) => {
                        step.style.opacity = '0';
                        step.style.transform = 'translateY(20px)';
                        setTimeout(() => {
                            step.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                            step.style.opacity = '1';
                            step.style.transform = 'translateY(0)';
                        }, i * 150);
                    });
                    stepObs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.2 });
        stepObs.observe(stepsContainer);
    }

    // ---- FLOATING CARD ENTRANCE ----
    const floatingCards = document.querySelectorAll('.floating-card');
    floatingCards.forEach((card, i) => {
        card.style.opacity = '0';
        card.style.transform = 'scale(0.8)';
        setTimeout(() => {
            card.style.transition = 'opacity 0.5s ease, transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1)';
            card.style.opacity = '1';
            card.style.transform = 'scale(1)';
        }, 700 + i * 200);
    });

    // ---- PHONE MOCKUP CHAT ANIMATION ----
    const chatBubbles = document.querySelectorAll('.chat-bubble');
    chatBubbles.forEach((bubble, i) => {
        bubble.style.opacity = '0';
        bubble.style.transform = 'translateY(10px)';
        setTimeout(() => {
            bubble.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            bubble.style.opacity = '1';
            bubble.style.transform = 'translateY(0)';
        }, 900 + i * 300);
    });

    // ---- PARALLAX EFFECT on hero ----
    const hero = document.querySelector('.hero');
    if (hero) {
        window.addEventListener('scroll', () => {
            const scrolled = window.scrollY;
            const rate = scrolled * 0.3;
            const heroContent = hero.querySelector('.hero-content');
            if (heroContent && scrolled < window.innerHeight) {
                heroContent.style.transform = `translateY(${rate * 0.15}px)`;
            }
        }, { passive: true });
    }

    // ---- CURSOR TRAIL EFFECT (subtle) ----
    let lastX = 0, lastY = 0;
    document.addEventListener('mousemove', (e) => {
        const dx = Math.abs(e.clientX - lastX);
        const dy = Math.abs(e.clientY - lastY);
        if (dx + dy > 30) {
            lastX = e.clientX;
            lastY = e.clientY;
        }
    }, { passive: true });

    // ---- CTA SECTION ENTRANCE ----
    const cta = document.querySelector('.cta-section');
    if (cta) {
        const ctaObs = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting) {
                cta.style.animation = 'fadeIn 0.8s ease both';
                ctaObs.disconnect();
            }
        }, { threshold: 0.2 });
        ctaObs.observe(cta);
    }

    // ---- LOGO HOVER ANIMATION ----
    document.querySelectorAll('.logo').forEach(logo => {
        logo.addEventListener('mouseenter', () => {
            const svg = logo.querySelector('svg');
            if (svg) {
                svg.style.transform = 'rotate(15deg) scale(1.1)';
                svg.style.transition = 'transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1)';
            }
        });
        logo.addEventListener('mouseleave', () => {
            const svg = logo.querySelector('svg');
            if (svg) {
                svg.style.transform = 'rotate(0deg) scale(1)';
            }
        });
    });

});
