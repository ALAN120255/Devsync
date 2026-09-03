/* TeleCure - dashboard.js */

document.addEventListener('DOMContentLoaded', function () {

    // ---- SIDEBAR TOGGLE ----
    const sidebar    = document.getElementById('sidebar');
    const menuToggle = document.getElementById('menuToggle');
    const sidebarClose = document.getElementById('sidebarClose');
    const overlay    = document.getElementById('sidebarOverlay');

    function openSidebar() {
        sidebar?.classList.add('open');
        overlay?.classList.add('visible');
        document.body.style.overflow = 'hidden';
    }
    function closeSidebar() {
        sidebar?.classList.remove('open');
        overlay?.classList.remove('visible');
        document.body.style.overflow = '';
    }

    menuToggle?.addEventListener('click', openSidebar);
    sidebarClose?.addEventListener('click', closeSidebar);
    overlay?.addEventListener('click', closeSidebar);

    // ---- NOTIFICATION DROPDOWN ----
    const notifToggle = document.getElementById('notifToggle');
    const notifDropdown = document.getElementById('notifDropdown');
    if (notifToggle && notifDropdown) {
        document.addEventListener('click', (e) => {
            if (!notifToggle.contains(e.target)) {
                notifDropdown.style.display = 'none';
            }
        });
        notifToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            const isVisible = notifDropdown.style.display === 'block';
            notifDropdown.style.display = isVisible ? 'none' : 'block';
        });
    }

    // ---- STAT CARD ANIMATION ----
    const statCards = document.querySelectorAll('.stat-card');
    const cardObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animationPlayState = 'running';
            }
        });
    }, { threshold: 0.1 });
    statCards.forEach(card => {
        card.style.animationPlayState = 'paused';
        cardObserver.observe(card);
    });

    // ---- APPOINTMENT CARD HOVER EFFECT ----
    document.querySelectorAll('.appointment-card').forEach(card => {
        card.addEventListener('mouseenter', function () {
            this.style.transition = 'transform 0.25s ease, box-shadow 0.25s ease';
        });
    });

    // ---- AUTO-DISMISS ALERTS ----
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease, height 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });

    // ---- SEARCH FUNCTIONALITY ----
    const searchInput = document.querySelector('.topbar-search input');
    if (searchInput) {
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                const q = searchInput.value.trim();
                if (q) {
                    // Could route to a search page
                    console.log('Searching for:', q);
                }
            }
        });
    }

    // ---- LIVE CLOCK IN TOPBAR ----
    function updateClock() {
        const now = new Date();
        const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const clockEl = document.getElementById('liveClock');
        if (clockEl) clockEl.textContent = timeStr;
    }
    setInterval(updateClock, 1000);
    updateClock();

    // ---- RIPPLE EFFECT ON BUTTONS ----
    document.querySelectorAll('.btn-primary, .btn-submit').forEach(btn => {
        btn.addEventListener('click', function (e) {
            const rect = this.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const ripple = document.createElement('span');
            ripple.style.cssText = `
                position: absolute; left: ${x}px; top: ${y}px;
                width: 4px; height: 4px; border-radius: 50%;
                background: rgba(255,255,255,0.5);
                transform: translate(-50%, -50%) scale(0);
                animation: rippleEffect 0.5s ease;
                pointer-events: none;
            `;
            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);
            setTimeout(() => ripple.remove(), 500);
        });
    });

    // ---- WELCOME BANNER CLOSE ----
    document.querySelectorAll('.welcome-banner button').forEach(btn => {
        btn.addEventListener('click', function () {
            this.closest('.welcome-banner').style.animation = 'fadeOut 0.3s ease forwards';
            setTimeout(() => this.closest('.welcome-banner')?.remove(), 300);
        });
    });

    // ---- KEYBOARD SHORTCUT: ESC closes modals ----
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay').forEach(m => {
                m.style.display = 'none';
            });
        }
    });

    // ---- SMOOTH SCROLL TO SECTION ----
    document.querySelectorAll('[data-scroll]').forEach(link => {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.dataset.scroll);
            target?.scrollIntoView({ behavior: 'smooth' });
        });
    });

    // Inject ripple keyframe dynamically
    const style = document.createElement('style');
    style.textContent = `
        @keyframes rippleEffect {
            to { transform: translate(-50%, -50%) scale(40); opacity: 0; }
        }
        @keyframes fadeOut {
            to { opacity: 0; height: 0; padding: 0; margin: 0; overflow: hidden; }
        }
    `;
    document.head.appendChild(style);

});
