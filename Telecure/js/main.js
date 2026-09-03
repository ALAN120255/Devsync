/* TeleCure - main.js */

document.addEventListener('DOMContentLoaded', function () {

    // ---- NAVBAR SCROLL EFFECT ----
    const navbar = document.getElementById('navbar');
    if (navbar) {
        window.addEventListener('scroll', () => {
            navbar.classList.toggle('scrolled', window.scrollY > 60);
        });
    }

    // ---- HAMBURGER MENU ----
    const hamburger = document.getElementById('hamburger');
    const navLinks = document.querySelector('.nav-links');
    if (hamburger) {
        hamburger.addEventListener('click', () => {
            navLinks?.classList.toggle('mobile-open');
        });
    }

    // ---- SMOOTH SCROLL FOR ANCHOR LINKS ----
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // ---- NUMBER COUNTER ANIMATION ----
    const counters = document.querySelectorAll('.stat-number[data-target]');
    const observerOptions = { threshold: 0.5 };
    const counterObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateCounter(entry.target);
                counterObserver.unobserve(entry.target);
            }
        });
    }, observerOptions);
    counters.forEach(c => counterObserver.observe(c));

    function animateCounter(el) {
        const target = parseInt(el.dataset.target);
        const duration = 1800;
        const start = performance.now();
        function update(now) {
            const progress = Math.min((now - start) / duration, 1);
            const ease = 1 - Math.pow(1 - progress, 3);
            el.textContent = Math.floor(ease * target).toLocaleString();
            if (progress < 1) requestAnimationFrame(update);
        }
        requestAnimationFrame(update);
    }

    // ---- TESTIMONIAL SLIDER ----
    const dots = document.querySelectorAll('.testimonial-dots .dot');
    const testimonials = document.querySelectorAll('.testimonial:not(.testimonial-dots)');
    let current = 0;
    let autoSlide;

    function showTestimonial(idx) {
        testimonials.forEach((t, i) => t.classList.toggle('active', i === idx));
        dots.forEach((d, i) => d.classList.toggle('active', i === idx));
        current = idx;
    }

    dots.forEach(dot => {
        dot.addEventListener('click', () => {
            clearInterval(autoSlide);
            showTestimonial(parseInt(dot.dataset.index));
            startAutoSlide();
        });
    });

    function startAutoSlide() {
        autoSlide = setInterval(() => {
            showTestimonial((current + 1) % testimonials.length);
        }, 5000);
    }

    if (testimonials.length > 0) startAutoSlide();

    // ---- SCROLL ANIMATIONS (AOS-like) ----
    const animElements = document.querySelectorAll('[data-aos]');
    const aosObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const el = entry.target;
                const delay = el.dataset.delay || 0;
                setTimeout(() => el.classList.add('animated'), delay);
            }
        });
    }, { threshold: 0.15 });
    animElements.forEach(el => aosObserver.observe(el));

    // ---- PARTICLES BACKGROUND ----
    const container = document.getElementById('particles');
    if (container) {
        for (let i = 0; i < 20; i++) {
            createParticle(container);
        }
    }

    function createParticle(container) {
        const p = document.createElement('div');
        p.className = 'particle';
        const size = Math.random() * 60 + 20;
        p.style.cssText = `
            width: ${size}px;
            height: ${size}px;
            left: ${Math.random() * 100}%;
            top: ${Math.random() * 100}%;
            animation-duration: ${Math.random() * 15 + 10}s;
            animation-delay: ${Math.random() * 10}s;
            opacity: ${Math.random() * 0.06 + 0.02};
        `;
        container.appendChild(p);
    }

    // ---- NAVBAR ACTIVE LINK HIGHLIGHT ----
    const currentPath = window.location.pathname.split('/').pop();
    document.querySelectorAll('.nav-links a').forEach(link => {
        if (link.getAttribute('href') === currentPath) {
            link.style.color = 'var(--primary)';
            link.style.background = 'var(--primary-pale)';
        }
    });

});
