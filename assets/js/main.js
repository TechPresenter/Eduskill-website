
/* Run fn once the DOM is parsed — safe to call after DOMContentLoaded too. */
function pwfReady(fn) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fn, { once: true });
    } else {
        fn();
    }
}
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
            '<figure class="lightbox-figure"><img alt=""><figcaption class="lightbox-caption"></figcaption></figure>' +
            '<button class="lightbox-nav next" aria-label="Next">&#8250;</button>';
        document.body.appendChild(box);
        const img = box.querySelector('img');
        const cap = box.querySelector('.lightbox-caption');
        let current = 0;

        const show = (i) => {
            current = (i + triggers.length) % triggers.length;
            const t = triggers[current];
            const src = t.dataset.lightbox || t.querySelector('img')?.src;
            img.src = src;
            /* Caption, and with it the alt text: the image had an empty alt and no
               visible label, so an opened photograph announced nothing at all. */
            const label = t.dataset.caption || t.querySelector('img')?.alt || '';
            img.alt = label;
            cap.textContent = label;
            cap.hidden = label === '';
            /* Position in the set, for anyone navigating with the arrow keys. */
            if (triggers.length > 1) {
                box.setAttribute('aria-label', 'Image ' + (current + 1) + ' of ' + triggers.length +
                    (label ? ': ' + label : ''));
            }
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

/* ---------------------------------------------------------------- MEGA MENU
   One trigger, one panel, both breakpoints. Keyboard-complete: Escape closes
   and returns focus to the trigger, Tab is trapped inside the open panel, and
   the trigger's aria-expanded always reflects reality.                        */
pwfReady(function () {
    var trigger = document.querySelector('[data-mm-toggle]');
    var panel   = document.querySelector('[data-mm-panel]');
    var scrim   = document.querySelector('[data-mm-scrim]');
    if (!trigger || !panel) { return; }

    var FOCUSABLE = 'a[href],button:not([disabled]),input,select,textarea,[tabindex]:not([tabindex="-1"])';
    var isOpen = false;

    function open() {
        if (isOpen) { return; }
        isOpen = true;
        panel.hidden = false;
        if (scrim) { scrim.hidden = false; }
        // Next frame so the transition runs from the hidden start state.
        requestAnimationFrame(function () {
            panel.classList.add('is-open');
            if (scrim) { scrim.classList.add('is-open'); }
        });
        trigger.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';

        /* preventScroll matters here. Moving focus makes the browser scroll the
           target into view, and the panel is late in the document, so opening
           the menu threw the page to the bottom — you tapped "Menu" and lost
           your place. The focus itself is still wanted (keyboard and screen
           reader users need to land inside the panel); it is only the scrolling
           side effect that was wrong. */
        var first = panel.querySelector(FOCUSABLE);
        if (first) { first.focus({ preventScroll: true }); }
    }

    function close(returnFocus) {
        if (!isOpen) { return; }
        isOpen = false;
        panel.classList.remove('is-open');
        if (scrim) { scrim.classList.remove('is-open'); }
        trigger.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
        window.setTimeout(function () {
            if (!isOpen) {
                panel.hidden = true;
                if (scrim) { scrim.hidden = true; }
            }
        }, 300);
        if (returnFocus) { trigger.focus(); }
    }

    trigger.addEventListener('click', function () { isOpen ? close(true) : open(); });
    if (scrim) { scrim.addEventListener('click', function () { close(false); }); }

    /* The drawer's own Close button. The [data-drawer-close] handler further up
       this file belongs to the ADMIN drawer and never saw this panel, so without
       this the X would render and do nothing. Delegated, because the panel's
       contents are built server-side and an accordion can be re-rendered. */
    panel.addEventListener('click', function (e) {
        var closer = e.target.closest ? e.target.closest('[data-drawer-close]') : null;
        if (closer) { e.preventDefault(); close(true); return; }
        /* An in-page anchor (#section) does not navigate, so the drawer would
           stay open over the content the visitor just asked to see. */
        var link = e.target.closest ? e.target.closest('a[href]') : null;
        if (link && (link.getAttribute('href') || '').charAt(0) === '#') { close(false); }
    });

    document.addEventListener('keydown', function (e) {
        if (!isOpen) { return; }
        if (e.key === 'Escape') { e.preventDefault(); close(true); return; }
        if (e.key !== 'Tab') { return; }

        var items = Array.prototype.slice.call(panel.querySelectorAll(FOCUSABLE))
            .filter(function (el) { return el.offsetParent !== null; });
        if (!items.length) { return; }
        var first = items[0], last = items[items.length - 1];
        // Include the trigger at the boundaries so focus cycles sensibly.
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); trigger.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); trigger.focus(); }
    });

    // Following a link should not leave the panel open behind the new page.
    panel.addEventListener('click', function (e) {
        if (e.target.closest('a[href]')) { close(false); }
    });
});

/* ------------------------------------------------- MEGA DROPDOWNS + ACCORDION
   Desktop: hover or click opens one dropdown at a time, positioned under the
   header. Mobile: the same tree as an accordion inside the single panel.
   Keyboard-complete: Escape closes, arrow keys are not hijacked, focus returns
   to the trigger, and aria-expanded always matches reality.                    */
pwfReady(function () {
    var navbar = document.querySelector('.navbar');

    /* ---------- desktop dropdowns ---------- */
    var triggers = Array.prototype.slice.call(document.querySelectorAll('[data-mm-drop]'));
    var openDrop = null, hoverTimer = null;

    function dropOf(btn) { return document.getElementById(btn.getAttribute('data-mm-drop')); }

    function positionDrop(el) {
        // Sit flush beneath the header, whatever its current height is.
        var r = navbar ? navbar.getBoundingClientRect() : { bottom: 0 };
        el.style.top = Math.max(0, r.bottom) + 'px';
    }

    function closeDrop(returnFocus) {
        if (!openDrop) { return; }
        var btn = openDrop, el = dropOf(btn);
        btn.setAttribute('aria-expanded', 'false');
        if (el) {
            el.classList.remove('is-open');
            window.setTimeout(function () { if (!el.classList.contains('is-open')) { el.hidden = true; } }, 260);
        }
        openDrop = null;
        if (returnFocus && btn) { btn.focus(); }
    }

    function showDrop(btn) {
        if (openDrop === btn) { return; }
        closeDrop(false);
        var el = dropOf(btn);
        if (!el) { return; }
        el.hidden = false;
        positionDrop(el);
        requestAnimationFrame(function () { el.classList.add('is-open'); });
        btn.setAttribute('aria-expanded', 'true');
        openDrop = btn;
    }

    triggers.forEach(function (btn) {
        var item = btn.closest('.mm-item');
        var el   = dropOf(btn);

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            openDrop === btn ? closeDrop(true) : showDrop(btn);
        });

        if (item && window.matchMedia('(hover: hover)').matches) {
            item.addEventListener('mouseenter', function () {
                window.clearTimeout(hoverTimer);
                showDrop(btn);
            });
            item.addEventListener('mouseleave', function () {
                hoverTimer = window.setTimeout(function () { closeDrop(false); }, 160);
            });
            if (el) {
                el.addEventListener('mouseenter', function () { window.clearTimeout(hoverTimer); });
                el.addEventListener('mouseleave', function () {
                    hoverTimer = window.setTimeout(function () { closeDrop(false); }, 160);
                });
            }
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && openDrop) { e.preventDefault(); closeDrop(true); }
    });
    document.addEventListener('click', function (e) {
        if (openDrop && !e.target.closest('.mm-item') && !e.target.closest('[data-mm-dropdown]')) {
            closeDrop(false);
        }
    });
    // A dropdown pinned to the header must follow it.
    window.addEventListener('resize', function () {
        if (openDrop) { var el = dropOf(openDrop); if (el) { positionDrop(el); } }
    }, { passive: true });
    window.addEventListener('scroll', function () {
        if (openDrop) { var el = dropOf(openDrop); if (el) { positionDrop(el); } }
    }, { passive: true });

    /* ---------- mobile accordion ---------- */
    Array.prototype.forEach.call(document.querySelectorAll('[data-mm-acc]'), function (head) {
        head.addEventListener('click', function () {
            var body = document.getElementById(head.getAttribute('data-mm-acc'));
            if (!body) { return; }
            var isOpen = head.getAttribute('aria-expanded') === 'true';
            head.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
            body.hidden = isOpen;
        });
    });
});

/* ------------------------------------------------------------ PORTAL SIDEBAR
   Slide-out drawer of the eight role portals. Modal semantics: scrim click,
   Escape and the close button all dismiss it; focus is trapped while open and
   returned to the trigger on close; body scroll is locked.                     */
pwfReady(function () {
    var trigger = document.querySelector('[data-ps-toggle]');
    var drawer  = document.querySelector('[data-ps-drawer]');
    var scrim   = document.querySelector('[data-ps-scrim]');
    if (!trigger || !drawer) { return; }

    var FOCUSABLE = 'a[href],button:not([disabled]),input,select,textarea,[tabindex]:not([tabindex="-1"])';
    var open = false;

    function show() {
        if (open) { return; }
        open = true;
        drawer.hidden = false;
        if (scrim) { scrim.hidden = false; }
        requestAnimationFrame(function () {
            drawer.classList.add('is-open');
            if (scrim) { scrim.classList.add('is-open'); }
        });
        trigger.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
        var first = drawer.querySelector('[data-ps-close]') || drawer.querySelector(FOCUSABLE);
        if (first) { first.focus(); }
    }

    function hide(returnFocus) {
        if (!open) { return; }
        open = false;
        drawer.classList.remove('is-open');
        if (scrim) { scrim.classList.remove('is-open'); }
        trigger.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
        window.setTimeout(function () {
            if (!open) {
                drawer.hidden = true;
                if (scrim) { scrim.hidden = true; }
            }
        }, 340);
        if (returnFocus) { trigger.focus(); }
    }

    trigger.addEventListener('click', function () { open ? hide(true) : show(); });
    if (scrim) { scrim.addEventListener('click', function () { hide(false); }); }
    Array.prototype.forEach.call(drawer.querySelectorAll('[data-ps-close]'), function (b) {
        b.addEventListener('click', function () { hide(true); });
    });

    document.addEventListener('keydown', function (e) {
        if (!open) { return; }
        if (e.key === 'Escape') { e.preventDefault(); hide(true); return; }
        if (e.key !== 'Tab') { return; }
        var items = Array.prototype.slice.call(drawer.querySelectorAll(FOCUSABLE))
            .filter(function (el) { return el.offsetParent !== null; });
        if (!items.length) { return; }
        var first = items[0], last = items[items.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });

    drawer.addEventListener('click', function (e) {
        if (e.target.closest('a[href]')) { hide(false); }
    });
});
