/* =============================================================================
   EDUSKILL INDIA FOUNDATION — front-end behaviour (vanilla ES6, no framework)
   -----------------------------------------------------------------------------
   Features: theme toggle, mobile drawer, sticky navbar, scroll reveal,
   animated counters, hero slider, lightbox gallery, lazy images, parallax,
   testimonials carousel, event countdowns, back-to-top, flash auto-dismiss,
   3D card pointer tracking.
   ============================================================================ */
(function () {
    'use strict';

    /* ----------------------------------------------------------- THEME */
    const THEME_KEY = 'pwf-theme';
    const root = document.documentElement;

    function applyTheme(theme) {
        root.setAttribute('data-theme', theme);
        document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
            // Swap the Lucide sun/moon SVG (no emoji). createIcons() below turns
            // the <i data-lucide> into an <svg>; the CSS pop animation replays.
            btn.innerHTML = '<i data-lucide="' + (theme === 'dark' ? 'sun' : 'moon') + '"></i>';
            btn.setAttribute('aria-label', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
            btn.setAttribute('title', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
        });
        if (window.lucide) { try { window.lucide.createIcons(); } catch (e) {} }
    }
    function initTheme() {
        const saved = localStorage.getItem(THEME_KEY);
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        applyTheme(saved || (prefersDark ? 'dark' : 'light'));
        document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                localStorage.setItem(THEME_KEY, next);
                applyTheme(next);
            });
        });
    }

    /* --------------------------------------------------- MOBILE DRAWER */
    function initDrawer() {
        const toggle = document.querySelector('[data-drawer-toggle]');
        const drawer = document.querySelector('[data-drawer]');
        const overlay = document.querySelector('[data-drawer-overlay]');
        if (!toggle || !drawer) return;

        const open = () => { drawer.classList.add('is-open'); overlay && overlay.classList.add('is-open'); document.body.style.overflow = 'hidden'; };
        const close = () => { drawer.classList.remove('is-open'); overlay && overlay.classList.remove('is-open'); document.body.style.overflow = ''; };

        toggle.addEventListener('click', open);
        overlay && overlay.addEventListener('click', close);
        drawer.querySelectorAll('[data-drawer-close], a').forEach((el) => el.addEventListener('click', close));
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
    }

    /* --------------------------------------------------- STICKY NAVBAR */
    function initNavbarScroll() {
        const nav = document.querySelector('[data-navbar]');
        if (!nav) return;
        const onScroll = () => nav.classList.toggle('is-scrolled', window.scrollY > 10);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    /* --------------------------------------------------- SCROLL REVEAL */
    function initReveal() {
        const items = document.querySelectorAll('.reveal');
        if (!items.length || !('IntersectionObserver' in window)) {
            items.forEach((el) => el.classList.add('is-visible'));
            return;
        }
        const io = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) { entry.target.classList.add('is-visible'); io.unobserve(entry.target); }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
        items.forEach((el) => io.observe(el));
    }

    /* ------------------------------------------------ COUNTER ANIMATION */
    function animateCounter(el) {
        const target = parseFloat(el.dataset.counter || el.textContent) || 0;
        const duration = 1800;
        const start = performance.now();
        const decimals = (el.dataset.decimals | 0);
        function tick(now) {
            const p = Math.min((now - start) / duration, 1);
            const eased = 1 - Math.pow(1 - p, 3);
            el.textContent = (target * eased).toLocaleString('en-IN', { maximumFractionDigits: decimals });
            if (p < 1) requestAnimationFrame(tick);
            else el.textContent = target.toLocaleString('en-IN', { maximumFractionDigits: decimals });
        }
        requestAnimationFrame(tick);
    }
    function initCounters() {
        const counters = document.querySelectorAll('[data-counter]');
        if (!counters.length) return;
        if (!('IntersectionObserver' in window)) { counters.forEach(animateCounter); return; }
        const io = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) { animateCounter(entry.target); io.unobserve(entry.target); }
            });
        }, { threshold: 0.5 });
        counters.forEach((el) => io.observe(el));
    }

    /* --------------------------------------------------- HERO SLIDER */
    function initHeroSlider() {
        const slider = document.querySelector('[data-hero-slider]');
        if (!slider) return;
        const slides = Array.from(slider.querySelectorAll('.hero-slide'));
        const copies = Array.from(slider.querySelectorAll('[data-hero-copy]'));
        const dotsWrap = slider.querySelector('.hero-dots');
        if (slides.length <= 1) { slides.forEach((s) => s.classList.add('is-active')); return; }

        let index = 0, timer;
        const interval = Math.max(2000, (parseInt(slider.dataset.interval, 10) || 6) * 1000);
        const dots = slides.map((_, i) => {
            const b = document.createElement('button');
            b.setAttribute('aria-label', 'Go to slide ' + (i + 1));
            b.addEventListener('click', () => { go(i); reset(); });
            dotsWrap && dotsWrap.appendChild(b);
            return b;
        });
        function go(i) {
            slides[index].classList.remove('is-active');
            dots[index] && dots[index].classList.remove('is-active');
            if (copies[index]) copies[index].style.display = 'none';
            index = (i + slides.length) % slides.length;
            slides[index].classList.add('is-active');
            dots[index] && dots[index].classList.add('is-active');
            // Rotate the headline/CTA copy together with the background image.
            if (copies[index]) copies[index].style.display = '';
        }
        function next() { go(index + 1); }
        function reset() { clearInterval(timer); timer = setInterval(next, interval); }
        go(0); reset();
        slider.addEventListener('mouseenter', () => clearInterval(timer));
        slider.addEventListener('mouseleave', reset);
    }

    /* ---------------------------------------------- TESTIMONIALS CAROUSEL */
    function initCarousel() {
        document.querySelectorAll('[data-carousel]').forEach((carousel) => {
            const track = carousel.querySelector('[data-carousel-track]');
            if (!track) return;
            const prev = carousel.querySelector('[data-carousel-prev]');
            const next = carousel.querySelector('[data-carousel-next]');
            let pos = 0;
            const step = () => track.firstElementChild ? track.firstElementChild.getBoundingClientRect().width + 24 : 320;
            const max = () => Math.max(0, track.scrollWidth - carousel.clientWidth);
            const move = (dir) => {
                pos = Math.min(Math.max(0, pos + dir * step() * (window.innerWidth < 768 ? 1 : 2)), max());
                track.style.transform = `translateX(${-pos}px)`;
            };
            prev && prev.addEventListener('click', () => move(-1));
            next && next.addEventListener('click', () => move(1));
        });
    }

    /* --------------------------------------------------- LIGHTBOX */
    function initLightbox() {
        const triggers = Array.from(document.querySelectorAll('[data-lightbox]'));
        if (!triggers.length) return;

        const box = document.createElement('div');
        box.className = 'lightbox';
        box.innerHTML = '<button class="lightbox-close" aria-label="Close">&times;</button>' +
            '<button class="lightbox-nav prev" aria-label="Previous">&#8249;</button>' +
            '<img alt="">' +
            '<button class="lightbox-nav next" aria-label="Next">&#8250;</button>';
        document.body.appendChild(box);
        const img = box.querySelector('img');
        let current = 0;

        const show = (i) => {
            current = (i + triggers.length) % triggers.length;
            const src = triggers[current].dataset.lightbox || triggers[current].querySelector('img')?.src;
            img.src = src;
            box.classList.add('is-open');
            document.body.style.overflow = 'hidden';
        };
        const close = () => { box.classList.remove('is-open'); document.body.style.overflow = ''; };

        triggers.forEach((t, i) => {
            t.addEventListener('click', (e) => { e.preventDefault(); show(i); });
            // Keyboard access: href-less <a> triggers are not focusable/operable.
            if (t.tagName === 'A' && !t.hasAttribute('href')) {
                t.setAttribute('role', 'button');
                t.setAttribute('tabindex', '0');
                t.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); show(i); }
                });
            }
        });
        box.querySelector('.lightbox-close').addEventListener('click', close);
        box.querySelector('.next').addEventListener('click', () => show(current + 1));
        box.querySelector('.prev').addEventListener('click', () => show(current - 1));
        box.addEventListener('click', (e) => { if (e.target === box) close(); });
        document.addEventListener('keydown', (e) => {
            if (!box.classList.contains('is-open')) return;
            if (e.key === 'Escape') close();
            if (e.key === 'ArrowRight') show(current + 1);
            if (e.key === 'ArrowLeft') show(current - 1);
        });
    }

    /* --------------------------------------------------- LAZY IMAGES */
    function initLazyImages() {
        const imgs = document.querySelectorAll('img[data-src]');
        if (!imgs.length) return;
        const load = (img) => { img.src = img.dataset.src; img.removeAttribute('data-src'); img.classList.add('is-loaded'); };
        if (!('IntersectionObserver' in window)) { imgs.forEach(load); return; }
        const io = new IntersectionObserver((entries) => {
            entries.forEach((entry) => { if (entry.isIntersecting) { load(entry.target); io.unobserve(entry.target); } });
        }, { rootMargin: '200px' });
        imgs.forEach((img) => io.observe(img));
    }

    /* --------------------------------------------------- PARALLAX */
    function initParallax() {
        const els = document.querySelectorAll('[data-parallax]');
        if (!els.length) return;
        window.addEventListener('scroll', () => {
            const y = window.scrollY;
            els.forEach((el) => {
                const speed = parseFloat(el.dataset.parallax) || 0.3;
                el.style.transform = `translateY(${y * speed}px)`;
            });
        }, { passive: true });
    }

    /* --------------------------------------------------- 3D CARD TRACK */
    function init3dCards() {
        document.querySelectorAll('.card-3d').forEach((card) => {
            card.addEventListener('pointermove', (e) => {
                const r = card.getBoundingClientRect();
                card.style.setProperty('--mx', ((e.clientX - r.left) / r.width * 100) + '%');
                card.style.setProperty('--my', ((e.clientY - r.top) / r.height * 100) + '%');
            });
        });
    }

    /* --------------------------------------------------- BACK TO TOP */
    function initBackToTop() {
        const btn = document.querySelector('[data-back-to-top]');
        if (!btn) return;
        window.addEventListener('scroll', () => btn.classList.toggle('is-visible', window.scrollY > 500), { passive: true });
        btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
    }

    /* --------------------------------------------------- FLASH TOASTS */
    function initFlash() {
        document.querySelectorAll('.flash-toast').forEach((toast) => {
            setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 400); }, 5000);
        });
    }

    /* --------------------------------------------------- COUNTDOWN */
    function initCountdowns() {
        document.querySelectorAll('[data-countdown]').forEach((el) => {
            // MySQL "YYYY-MM-DD HH:MM:SS" is not a valid Date string in Safari —
            // normalise the space to a "T" so all engines parse it.
            const target = new Date(el.dataset.countdown.replace(' ', 'T')).getTime();
            if (isNaN(target)) return;
            const out = {
                d: el.querySelector('[data-cd-d]'), h: el.querySelector('[data-cd-h]'),
                m: el.querySelector('[data-cd-m]'), s: el.querySelector('[data-cd-s]'),
            };
            const tick = () => {
                let diff = Math.max(0, target - Date.now());
                const d = Math.floor(diff / 86400000); diff -= d * 86400000;
                const h = Math.floor(diff / 3600000); diff -= h * 3600000;
                const m = Math.floor(diff / 60000); diff -= m * 60000;
                const s = Math.floor(diff / 1000);
                if (out.d) out.d.textContent = String(d).padStart(2, '0');
                if (out.h) out.h.textContent = String(h).padStart(2, '0');
                if (out.m) out.m.textContent = String(m).padStart(2, '0');
                if (out.s) out.s.textContent = String(s).padStart(2, '0');
            };
            tick(); setInterval(tick, 1000);
        });
    }

    /* --------------------------------------------------- SMOOTH ANCHORS */
    function initSmoothAnchors() {
        document.querySelectorAll('a[href^="#"]:not([href="#"])').forEach((a) => {
            a.addEventListener('click', (e) => {
                const el = document.querySelector(a.getAttribute('href'));
                if (el) { e.preventDefault(); el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
            });
        });
    }

    /* --------------------------------------------------- INIT */
    document.addEventListener('DOMContentLoaded', () => {
        initTheme();
        initDrawer();
        initNavbarScroll();
        initReveal();
        initCounters();
        initHeroSlider();
        initCarousel();
        initLightbox();
        initLazyImages();
        initParallax();
        init3dCards();
        initBackToTop();
        initFlash();
        initCountdowns();
        initSmoothAnchors();
    });

    // Expose a tiny helper others can use.
    window.PWF = { applyTheme };
})();
