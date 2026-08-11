/* =============================================================================
   EDUSKILL INDIA FOUNDATION — Premium UX layer (additive, no dependencies)
   -----------------------------------------------------------------------------
   Enhancements that lift the whole site without touching page markup:
     • Button ripple micro-interaction
     • Staggered scroll-reveal for [data-reveal] (fade/slide/zoom/rotate)
     • Hero autoplay progress bar (syncs to the existing slider)
     • Respects prefers-reduced-motion
   ============================================================================ */
(function () {
    'use strict';
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    // Note: button ripple is already provided by premium.js — not duplicated here.

    /* ---------------------------------------------- STAGGERED REVEAL */
    function initReveal() {
        var items = document.querySelectorAll('[data-reveal]');
        if (!items.length) return;
        if (reduce || !('IntersectionObserver' in window)) {
            items.forEach(function (el) { el.classList.add('is-in'); });
            return;
        }
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                var el = entry.target;
                var delay = parseFloat(el.getAttribute('data-reveal-delay')) || 0;
                // Auto-stagger children of a [data-reveal-group]
                if (el.hasAttribute('data-reveal-group')) {
                    Array.prototype.forEach.call(el.children, function (child, i) {
                        child.style.transitionDelay = (i * 0.08) + 's';
                        child.classList.add('is-in');
                    });
                }
                el.style.transitionDelay = delay + 's';
                el.classList.add('is-in');
                io.unobserve(el);
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -50px 0px' });
        items.forEach(function (el) { io.observe(el); });
    }

    /* ------------------------------------------- HERO PROGRESS BAR */
    function initHeroProgress() {
        var slider = document.querySelector('[data-hero-slider]');
        if (!slider) return;
        var slides = slider.querySelectorAll('.hero-slide');
        if (slides.length <= 1) return;

        var seconds = Math.max(2, parseInt(slider.getAttribute('data-interval'), 10) || 6);
        var bar = document.createElement('div');
        bar.className = 'hero-progress';
        bar.innerHTML = '<span></span>';
        slider.appendChild(bar);
        var fill = bar.firstElementChild;
        fill.style.animationDuration = seconds + 's';

        // Restart the bar animation whenever the active slide changes.
        var restart = function () {
            fill.style.animation = 'none';
            // force reflow
            void fill.offsetWidth;
            fill.style.animation = '';
            fill.style.animationDuration = seconds + 's';
        };
        var mo = new MutationObserver(function (muts) {
            for (var i = 0; i < muts.length; i++) {
                if (muts[i].target.classList.contains('is-active')) { restart(); break; }
            }
        });
        slides.forEach(function (s) { mo.observe(s, { attributes: true, attributeFilter: ['class'] }); });
    }

    /* ------------------------------------ HERO DECOR (floating shapes only) */
    function initHeroTransparency() {
        var hero = document.querySelector('#main-content > .hero');
        if (!hero) return;
        // Inject animated floating shapes (decorative, behind content).
        if (!hero.querySelector('.hero-decor') && !reduce) {
            var decor = document.createElement('div');
            decor.className = 'hero-decor';
            decor.setAttribute('aria-hidden', 'true');
            decor.innerHTML = '<span class="hero-orb o1"></span><span class="hero-orb o2"></span><span class="hero-orb o3"></span>';
            hero.appendChild(decor);
        }
        // Note: the navbar stays a solid glass bar (better contrast & alignment)
        // rather than transparent-over-hero.
    }

    /* ------------------------------------ HERO SWIPE / TOUCH */
    function initHeroSwipe() {
        var slider = document.querySelector('[data-hero-slider]');
        if (!slider) return;
        var startX = null;
        slider.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
        slider.addEventListener('touchend', function (e) {
            if (startX === null) return;
            var dx = e.changedTouches[0].clientX - startX;
            startX = null;
            if (Math.abs(dx) < 45) return;
            // Drive the existing slider by clicking the appropriate dot.
            var dots = Array.prototype.slice.call(slider.querySelectorAll('.hero-dots button'));
            if (!dots.length) return;
            var cur = dots.findIndex(function (d) { return d.classList.contains('is-active'); });
            if (cur < 0) cur = 0;
            var target = dx < 0 ? (cur + 1) % dots.length : (cur - 1 + dots.length) % dots.length;
            dots[target].click();
        }, { passive: true });
    }

    /* ------------------------------------ READING PROGRESS BAR (articles) */
    function initReadingProgress() {
        var article = document.querySelector('[data-article]');
        if (!article) return;
        var bar = document.createElement('div');
        bar.className = 'reading-progress';
        bar.innerHTML = '<span></span>';
        document.body.appendChild(bar);
        var fill = bar.firstElementChild;
        var update = function () {
            var rect = article.getBoundingClientRect();
            var total = article.offsetHeight - window.innerHeight;
            var scrolled = Math.min(Math.max(-rect.top, 0), Math.max(total, 1));
            fill.style.width = (total > 0 ? (scrolled / total) * 100 : 0) + '%';
            bar.classList.toggle('is-visible', rect.top < 60 && rect.bottom > 0);
        };
        update();
        window.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
    }

    /* ------------------------------------ TABLE OF CONTENTS (auto, degrades) */
    function initTOC() {
        var container = document.querySelector('[data-toc]');
        var nav = document.querySelector('[data-toc-nav]');
        var prose = document.querySelector('[data-article] .prose');
        if (!container || !nav || !prose) return;
        var heads = prose.querySelectorAll('h2, h3');
        if (!heads.length) return; // plain-text article → TOC stays hidden

        var frag = document.createDocumentFragment();
        Array.prototype.forEach.call(heads, function (h, i) {
            if (!h.id) {
                h.id = 'sec-' + i + '-' + (h.textContent || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 40);
            }
            var a = document.createElement('a');
            a.href = '#' + h.id;
            a.textContent = h.textContent;
            a.className = 'toc-link ' + (h.tagName === 'H3' ? 'lvl-3' : 'lvl-2');
            a.addEventListener('click', function (e) {
                e.preventDefault();
                document.getElementById(h.id).scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
            frag.appendChild(a);
        });
        nav.appendChild(frag);
        container.hidden = false;

        var links = nav.querySelectorAll('.toc-link');
        if ('IntersectionObserver' in window) {
            var spy = new IntersectionObserver(function (entries) {
                entries.forEach(function (en) {
                    if (!en.isIntersecting) return;
                    links.forEach(function (l) { l.classList.toggle('is-active', l.getAttribute('href') === '#' + en.target.id); });
                });
            }, { rootMargin: '-90px 0px -70% 0px' });
            Array.prototype.forEach.call(heads, function (h) { spy.observe(h); });
        }
    }

    /* ------------------------------------ CAROUSEL: autoplay + touch */
    function initCarouselEnhance() {
        document.querySelectorAll('[data-carousel]').forEach(function (car) {
            var track = car.querySelector('[data-carousel-track]');
            var next = car.querySelector('[data-carousel-next]');
            var prev = car.querySelector('[data-carousel-prev]');
            if (!track || !next) return;

            // Touch swipe → drive the existing prev/next buttons.
            var sx = null;
            track.addEventListener('touchstart', function (e) { sx = e.touches[0].clientX; }, { passive: true });
            track.addEventListener('touchend', function (e) {
                if (sx === null) return;
                var dx = e.changedTouches[0].clientX - sx; sx = null;
                if (Math.abs(dx) < 45) return;
                (dx < 0 ? next : (prev || next)).click();
            }, { passive: true });

            // Ping-pong autoplay (reverses at the ends), pauses on hover.
            if (reduce) return;
            var dir = 1, timer = null;
            function tick() {
                var before = track.style.transform || '';
                (dir > 0 ? next : (prev || next)).click();
                if ((track.style.transform || '') === before) { // hit an end → reverse
                    dir *= -1;
                    (dir > 0 ? next : (prev || next)).click();
                }
            }
            function start() { if (!timer) timer = setInterval(tick, 6000); }
            function stop() { clearInterval(timer); timer = null; }
            start();
            car.addEventListener('mouseenter', stop);
            car.addEventListener('mouseleave', start);
        });
    }

    /* ------------------------------------ HEADER LIVE SEARCH */
    function initNavSearch() {
        var wrap = document.querySelector('[data-nav-search]');
        if (!wrap) return;
        var input = wrap.querySelector('[data-nav-search-input]');
        var panel = wrap.querySelector('[data-nav-search-panel]');
        var endpoint = wrap.getAttribute('data-endpoint');
        if (!input || !panel || !endpoint) return;
        var timer = null, lastQ = '';

        function esc(s) { return (s || '').replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

        function render(data) {
            if (!data.groups || !data.groups.length) {
                panel.innerHTML = '<div class="ns-empty">No results for “' + esc(data.query) + '”.</div>';
                panel.hidden = false; return;
            }
            var html = '';
            data.groups.forEach(function (g) {
                html += '<div class="ns-group"><div class="ns-group-title"><i data-lucide="' + esc(g.icon) + '"></i>' + esc(g.type) + '</div>';
                g.items.forEach(function (it) {
                    html += '<a class="ns-item" href="' + esc(it.url) + '"><span class="ns-item-title">' + esc(it.title) + '</span>' +
                            (it.meta ? '<span class="ns-item-meta">' + esc(it.meta) + '</span>' : '') + '</a>';
                });
                html += '</div>';
            });
            html += '<a class="ns-all" href="' + esc(data.all_url) + '">See all results for “' + esc(data.query) + '” →</a>';
            panel.innerHTML = html;
            panel.hidden = false;
            if (window.lucide) window.lucide.createIcons();
        }

        function search(q) {
            fetch(endpoint + '?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(render)
                .catch(function () { panel.hidden = true; });
        }

        input.addEventListener('input', function () {
            var q = input.value.trim();
            clearTimeout(timer);
            if (q.length < 2) { panel.hidden = true; return; }
            if (q === lastQ) return;
            lastQ = q;
            timer = setTimeout(function () { search(q); }, 220);
        });
        input.addEventListener('focus', function () { if (panel.innerHTML && input.value.trim().length >= 2) panel.hidden = false; });
        document.addEventListener('click', function (e) { if (!wrap.contains(e.target)) panel.hidden = true; });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') panel.hidden = true; });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initReveal();
        initHeroProgress();
        initHeroTransparency();
        initHeroSwipe();
        initReadingProgress();
        initTOC();
        initCarouselEnhance();
        initNavSearch();
    });
})();

/* =============================================================================
   Collapsing nav search + voice input
   The header search starts as an icon; clicking expands a compact field.
   Voice input uses the Web Speech API and only appears when supported.
   ============================================================================= */
(function () {
    'use strict';
    var wrap = document.querySelector('[data-nav-search]');
    if (!wrap) return;

    var trigger = wrap.querySelector('[data-search-trigger]'),
        form    = wrap.querySelector('.nav-search-box'),
        input   = wrap.querySelector('[data-nav-search-input]'),
        mic     = wrap.querySelector('[data-search-mic]'),
        closeB  = wrap.querySelector('[data-search-close]'),
        panel   = wrap.querySelector('[data-nav-search-panel]');

    function open() {
        wrap.classList.remove('is-collapsed');
        wrap.classList.add('is-open');
        if (trigger) trigger.setAttribute('aria-expanded', 'true');
        if (input) setTimeout(function () { input.focus(); }, 60);
    }
    function close() {
        wrap.classList.remove('is-open');
        wrap.classList.add('is-collapsed');
        if (trigger) trigger.setAttribute('aria-expanded', 'false');
        if (panel) { panel.hidden = true; panel.innerHTML = ''; }
        stopListening();
    }

    if (trigger) trigger.addEventListener('click', open);
    if (closeB) closeB.addEventListener('click', function () {
        if (input && input.value) { input.value = ''; input.focus(); return; }  // first press clears
        close();
        if (trigger) trigger.focus();
    });

    // Escape closes; click-away closes when the field is empty.
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && wrap.classList.contains('is-open')) { close(); if (trigger) trigger.focus(); }
    });
    document.addEventListener('click', function (e) {
        if (!wrap.classList.contains('is-open')) return;
        // The scrim is a ::before on the wrapper, so a click on it targets the
        // wrapper itself. Without this the backdrop would look dismissible and
        // do nothing, because wrap.contains(wrap) is true.
        if (e.target === wrap) { close(); if (trigger) trigger.focus(); return; }
        if (wrap.contains(e.target)) return;
        close();
    });

    /* ---- Voice input (progressive enhancement) ---- */
    var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    var rec = null, listening = false;

    function stopListening() {
        listening = false;
        if (mic) { mic.classList.remove('is-listening'); mic.setAttribute('aria-label', 'Search by voice'); }
        if (rec) { try { rec.stop(); } catch (err) {} }
    }

    if (SR && mic) {
        mic.hidden = false;                       // only shown where the API exists
        rec = new SR();
        rec.lang = document.documentElement.lang || 'en-IN';
        rec.interimResults = true;
        rec.continuous = false;

        mic.addEventListener('click', function () {
            if (listening) { stopListening(); return; }
            try { rec.start(); } catch (err) { return; }
            listening = true;
            mic.classList.add('is-listening');
            mic.setAttribute('aria-label', 'Stop voice search');
            if (input) input.placeholder = 'Listening…';
        });

        rec.addEventListener('result', function (e) {
            var text = '';
            for (var i = e.resultIndex; i < e.results.length; i++) text += e.results[i][0].transcript;
            if (!input) return;
            input.value = text;
            input.dispatchEvent(new Event('input', { bubbles: true }));   // re-run live search
            if (e.results[e.results.length - 1].isFinal) {
                stopListening();
                input.placeholder = 'Search the site…';
            }
        });
        rec.addEventListener('end', function () { stopListening(); if (input) input.placeholder = 'Search the site…'; });
        rec.addEventListener('error', function () { stopListening(); if (input) input.placeholder = 'Search the site…'; });
    }
})();

/* =============================================================================
   Colourful cursor — brand dot, easing halo and a colour-cycling spark trail.
   Pointer-events are disabled on every element it creates, so clicking,
   selecting and hovering behave exactly as before.
   ============================================================================= */
(function () {
    'use strict';

    // Fine pointer only, and never when the visitor asked for reduced motion.
    var fine = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;
    var calm = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (!fine || calm) return;

    var dot  = document.createElement('div');
    var ring = document.createElement('div');
    dot.className = 'cursor-dot';
    ring.className = 'cursor-ring';
    dot.setAttribute('aria-hidden', 'true');
    ring.setAttribute('aria-hidden', 'true');
    document.body.appendChild(ring);
    document.body.appendChild(dot);

    // Brand palette the sparks cycle through.
    var css = getComputedStyle(document.documentElement);
    function tok(name, fallback) {
        var v = css.getPropertyValue(name);
        return (v && v.trim()) || fallback;
    }
    var palette = [
        tok('--orange', '#E67B1D'),
        tok('--green', '#58A42F'),
        tok('--secondary', '#084881'),
        tok('--accent-2', '#F59E0B'),
        tok('--primary', '#063566')
    ];

    var mx = window.innerWidth / 2, my = window.innerHeight / 2;   // target
    var rx = mx, ry = my;                                          // eased ring
    var lastSpark = 0, hue = 0, moved = false;

    document.addEventListener('pointermove', function (e) {
        mx = e.clientX; my = e.clientY; moved = true;
        // dot tracks exactly — no easing, so it feels precise
        dot.style.transform = 'translate3d(' + mx + 'px,' + my + 'px,0)';

        // spawn a spark at most every 45ms so fast movement can't flood the DOM
        var now = performance.now();
        if (now - lastSpark > 45) {
            lastSpark = now;
            var s = document.createElement('div');
            s.className = 'cursor-spark';
            s.setAttribute('aria-hidden', 'true');
            s.style.setProperty('--spark', palette[hue++ % palette.length]);
            s.style.setProperty('--x', mx + 'px');
            s.style.setProperty('--y', my + 'px');
            s.style.transform = 'translate3d(' + mx + 'px,' + my + 'px,0)';
            document.body.appendChild(s);
            setTimeout(function () { s.remove(); }, 760);   // matches the fade duration
        }
    }, { passive: true });

    // Ring eases toward the pointer for a soft trailing feel.
    (function loop() {
        rx += (mx - rx) * 0.18;
        ry += (my - ry) * 0.18;
        ring.style.transform = 'translate3d(' + rx + 'px,' + ry + 'px,0)';
        requestAnimationFrame(loop);
    })();

    // Grow over anything interactive.
    var HOT = 'a,button,input,select,textarea,summary,label,[role="button"],[tabindex]:not([tabindex="-1"])';
    document.addEventListener('pointerover', function (e) {
        if (e.target.closest && e.target.closest(HOT)) document.body.classList.add('cursor-hot');
    }, { passive: true });
    document.addEventListener('pointerout', function (e) {
        if (e.target.closest && e.target.closest(HOT)) document.body.classList.remove('cursor-hot');
    }, { passive: true });

    // Click feedback.
    document.addEventListener('pointerdown', function () { document.body.classList.add('cursor-press'); }, { passive: true });
    document.addEventListener('pointerup',   function () { document.body.classList.remove('cursor-press'); }, { passive: true });

    // Hide while the pointer is outside the window.
    document.addEventListener('pointerleave', function () { dot.style.opacity = ring.style.opacity = '0'; });
    document.addEventListener('pointerenter', function () { dot.style.opacity = ring.style.opacity = '1'; });
})();
