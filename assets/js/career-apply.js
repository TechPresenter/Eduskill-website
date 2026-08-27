/* =============================================================================
 *  Careers — premium application form behaviour
 * -----------------------------------------------------------------------------
 *  Multi-step navigation, inline validation, drag & drop résumé upload with a
 *  preview, character counters, count-up statistics, scroll reveal, autosave to
 *  localStorage, ripple + confetti on submit.
 *
 *  Everything degrades: with JS off the form is a single scrolling page that
 *  posts normally, because the panels are only hidden by a class this script
 *  adds. Phone validation is owned by country-select.js, which guards submit
 *  independently.
 * ========================================================================== */
(function () {
    'use strict';

    var root = document.querySelector('[data-cap]');
    if (!root) { return; }

    var REDUCED = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var form    = root.querySelector('[data-cap-form]');
    var STORE   = 'pwf_career_draft';

    /* ------------------------------------------------------- scroll reveal */
    if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (!en.isIntersecting) { return; }
                en.target.classList.add('is-in');
                io.unobserve(en.target);
            });
        }, { rootMargin: '0px 0px -8% 0px', threshold: .12 });
        root.querySelectorAll('.cap-rv, .cap-tl-item').forEach(function (el) { io.observe(el); });
    } else {
        root.querySelectorAll('.cap-rv, .cap-tl-item').forEach(function (el) { el.classList.add('is-in'); });
    }

    /* ------------------------------------------------------- count-up stats */
    function countUp(el) {
        var target = parseFloat(el.dataset.to || '0');
        var suffix = el.dataset.suffix || '';
        if (REDUCED) { el.textContent = target + suffix; return; }
        var start = null, dur = 1400;
        function tick(ts) {
            if (start === null) { start = ts; }
            var p = Math.min((ts - start) / dur, 1);
            // easeOutCubic
            var v = target * (1 - Math.pow(1 - p, 3));
            el.textContent = (target % 1 ? v.toFixed(1) : Math.round(v)) + suffix;
            if (p < 1) { requestAnimationFrame(tick); }
        }
        requestAnimationFrame(tick);
    }
    if ('IntersectionObserver' in window) {
        var io2 = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (!en.isIntersecting) { return; }
                countUp(en.target);
                io2.unobserve(en.target);
            });
        }, { threshold: .5 });
        root.querySelectorAll('[data-count]').forEach(function (el) { io2.observe(el); });
    } else {
        root.querySelectorAll('[data-count]').forEach(countUp);
    }

    if (!form) { return; }

    /* ------------------------------------------------------------ stepping */
    var panels = Array.prototype.slice.call(form.querySelectorAll('[data-cap-panel]'));
    var steps  = Array.prototype.slice.call(root.querySelectorAll('[data-cap-step]'));
    var bar    = root.querySelector('[data-cap-bar]');
    var btnNext= form.querySelector('[data-cap-next]');
    var btnPrev= form.querySelector('[data-cap-prev]');
    var btnSend= form.querySelector('[data-cap-submit]');
    var at = 0;

    function paintSteps() {
        steps.forEach(function (s, i) {
            s.classList.toggle('is-active', i === at);
            s.classList.toggle('is-done', i < at);
        });
        if (bar) { bar.style.width = (at / (panels.length - 1) * 100) + '%'; }
        panels.forEach(function (p, i) { p.classList.toggle('is-on', i === at); });
        if (btnPrev) { btnPrev.style.display = at === 0 ? 'none' : ''; }
        if (btnNext) { btnNext.style.display = at === panels.length - 1 ? 'none' : ''; }
        if (btnSend) { btnSend.style.display = at === panels.length - 1 ? '' : 'none'; }
    }

    /** Validate only the fields inside the current panel. */
    function validPanel(i) {
        var ok = true;
        panels[i].querySelectorAll('input, select, textarea').forEach(function (el) {
            if (el.type === 'file' || el.disabled) { return; }
            var wrap = el.closest('.cap-f');
            var bad  = !el.checkValidity();
            if (wrap) {
                wrap.classList.toggle('has-error', bad);
                wrap.classList.toggle('is-ok', !bad && !!el.value);
                var err = wrap.querySelector('.cap-err');
                if (err) { err.textContent = bad ? (el.validationMessage || 'Please check this field.') : ''; }
            }
            if (bad && ok) { ok = false; el.focus(); }
        });
        return ok;
    }

    if (btnNext) {
        btnNext.addEventListener('click', function () {
            if (!validPanel(at)) { return; }
            at = Math.min(at + 1, panels.length - 1);
            paintSteps();
            root.querySelector('[data-cap-card]').scrollIntoView({ behavior: REDUCED ? 'auto' : 'smooth', block: 'start' });
        });
    }
    if (btnPrev) {
        btnPrev.addEventListener('click', function () {
            at = Math.max(at - 1, 0);
            paintSteps();
        });
    }
    steps.forEach(function (s, i) {
        s.addEventListener('click', function () {
            // Only allow jumping back, or forward through validated panels.
            if (i < at || validPanel(at)) { at = i; paintSteps(); }
        });
    });
    paintSteps();

    /* ------------------------------------------------- inline field feedback */
    form.querySelectorAll('.cap-f input, .cap-f select, .cap-f textarea').forEach(function (el) {
        el.addEventListener('blur', function () {
            var wrap = el.closest('.cap-f');
            if (!wrap || (!el.value && !el.required)) { return; }
            var bad = !el.checkValidity();
            wrap.classList.toggle('has-error', bad);
            wrap.classList.toggle('is-ok', !bad && !!el.value);
            var err = wrap.querySelector('.cap-err');
            if (err) { err.textContent = bad ? (el.validationMessage || '') : ''; }
        });
        if (el.tagName === 'SELECT') {
            el.addEventListener('change', function () { el.classList.toggle('has-value', !!el.value); });
            el.classList.toggle('has-value', !!el.value);
        }
    });

    /* --------------------------------------------------- character counters */
    form.querySelectorAll('[data-cap-count]').forEach(function (ta) {
        var out = ta.parentNode.querySelector('.cap-count');
        if (!out) { return; }
        var max = parseInt(ta.getAttribute('maxlength') || '0', 10);
        function paint() { out.textContent = ta.value.length + (max ? ' / ' + max : ''); }
        ta.addEventListener('input', paint);
        paint();
    });

    /* ------------------------------------------------------------- uploader */
    var drop = form.querySelector('[data-cap-drop]');
    if (drop) {
        var input = drop.querySelector('input[type="file"]');
        var card  = form.querySelector('[data-cap-file]');
        var nameEl= form.querySelector('[data-cap-file-name]');
        var sizeEl= form.querySelector('[data-cap-file-size]');
        var barEl = form.querySelector('[data-cap-file-bar]');
        var killEl= form.querySelector('[data-cap-file-remove]');
        var errEl = form.querySelector('[data-cap-file-err]');
        var MAX   = 5 * 1024 * 1024;
        var OK    = ['pdf', 'doc', 'docx'];

        function human(b) {
            return b < 1024 ? b + ' B'
                 : b < 1048576 ? (b / 1024).toFixed(0) + ' KB'
                 : (b / 1048576).toFixed(1) + ' MB';
        }
        function reset() {
            input.value = '';
            if (card) { card.classList.remove('is-on'); }
            if (barEl) { barEl.style.width = '0'; }
        }
        function show(f) {
            if (errEl) { errEl.textContent = ''; }
            var ext = (f.name.split('.').pop() || '').toLowerCase();
            if (OK.indexOf(ext) === -1) {
                if (errEl) { errEl.textContent = 'Please attach a PDF, DOC or DOCX file.'; }
                reset(); return false;
            }
            if (f.size > MAX) {
                if (errEl) { errEl.textContent = 'That file is ' + human(f.size) + ' — the limit is 5 MB.'; }
                reset(); return false;
            }
            if (nameEl) { nameEl.textContent = f.name; }
            if (sizeEl) { sizeEl.textContent = human(f.size); }
            if (card)   { card.classList.add('is-on'); }
            // Progress is presentational — the real upload happens on submit.
            if (barEl && !REDUCED) {
                barEl.style.width = '0';
                setTimeout(function () { barEl.style.width = '100%'; }, 40);
            } else if (barEl) { barEl.style.width = '100%'; }
            return true;
        }

        input.addEventListener('change', function () { if (input.files[0]) { show(input.files[0]); } });
        ['dragenter', 'dragover'].forEach(function (ev) {
            drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('is-over'); });
        });
        ['dragleave', 'drop'].forEach(function (ev) {
            drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('is-over'); });
        });
        drop.addEventListener('drop', function (e) {
            var f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
            if (!f) { return; }
            // Put the dropped file into the input so it submits with the form.
            var dt = new DataTransfer();
            dt.items.add(f);
            input.files = dt.files;
            show(f);
        });
        if (killEl) {
            killEl.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); reset(); });
        }
    }

    /* ------------------------------------------------------------- autosave */
    var SKIP = ['csrf_token', 'pwf_zq', 'resume'];
    function save() {
        try {
            var data = {};
            form.querySelectorAll('input, select, textarea').forEach(function (el) {
                if (!el.name || SKIP.indexOf(el.name) > -1 || el.type === 'file') { return; }
                data[el.name] = el.value;
            });
            localStorage.setItem(STORE, JSON.stringify({ t: Date.now(), d: data }));
        } catch (e) { /* private mode / quota — autosave is best-effort */ }
    }
    function restore() {
        try {
            var raw = localStorage.getItem(STORE);
            if (!raw) { return; }
            var box = JSON.parse(raw);
            // Drop drafts older than 7 days.
            if (!box || !box.d || (Date.now() - box.t) > 6048e5) { localStorage.removeItem(STORE); return; }
            Object.keys(box.d).forEach(function (k) {
                var el = form.querySelector('[name="' + CSS.escape(k) + '"]');
                if (el && !el.value) { el.value = box.d[k]; }
            });
        } catch (e) { /* ignore */ }
    }
    restore();
    var saveTimer;
    form.addEventListener('input', function () {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(save, 700);
    });

    /* --------------------------------------------------------------- ripple */
    form.querySelectorAll('.cap-btn').forEach(function (b) {
        b.addEventListener('click', function (e) {
            if (REDUCED) { return; }
            var r = b.getBoundingClientRect();
            var s = document.createElement('span');
            s.className = 'cap-ripple';
            var d = Math.max(r.width, r.height);
            s.style.width = s.style.height = d + 'px';
            s.style.left = (e.clientX - r.left - d / 2) + 'px';
            s.style.top  = (e.clientY - r.top  - d / 2) + 'px';
            b.appendChild(s);
            setTimeout(function () { s.remove(); }, 600);
        });
    });

    /* ------------------------------------------------------------- confetti */
    function confetti() {
        if (REDUCED) { return; }
        var cvs = document.createElement('canvas');
        cvs.style.cssText = 'position:fixed;inset:0;pointer-events:none;z-index:9999';
        cvs.width = innerWidth; cvs.height = innerHeight;
        document.body.appendChild(cvs);
        var ctx = cvs.getContext('2d');
        var cols = ['#0B4EA2', '#1F6BFF', '#18C37E', '#4F8DFF', '#fbbf24'];
        var bits = [];
        for (var i = 0; i < 110; i++) {
            bits.push({
                x: innerWidth / 2, y: innerHeight / 2.4,
                vx: (Math.random() - .5) * 13, vy: Math.random() * -13 - 3,
                s: Math.random() * 7 + 3, c: cols[i % cols.length],
                a: Math.random() * Math.PI, va: (Math.random() - .5) * .3
            });
        }
        var frames = 0;
        (function loop() {
            ctx.clearRect(0, 0, cvs.width, cvs.height);
            bits.forEach(function (p) {
                p.vy += .32; p.x += p.vx; p.y += p.vy; p.a += p.va;
                ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.a);
                ctx.fillStyle = p.c; ctx.fillRect(-p.s / 2, -p.s / 2, p.s, p.s * .6);
                ctx.restore();
            });
            if (++frames < 190) { requestAnimationFrame(loop); } else { cvs.remove(); }
        })();
    }

    /* --------------------------------------------------------------- submit */
    form.addEventListener('submit', function (e) {
        // Validate every panel, not just the visible one.
        for (var i = 0; i < panels.length; i++) {
            if (!validPanel(i)) { e.preventDefault(); at = i; paintSteps(); return; }
        }
        if (btnSend) {
            btnSend.disabled = true;
            btnSend.innerHTML = '<span class="cap-spin"></span> Submitting…';
        }
    });

    // The shared AJAX form handler dispatches this once the POST succeeds.
    form.addEventListener('pwf:submitted', function () {
        try { localStorage.removeItem(STORE); } catch (e) {}
        var done = root.querySelector('[data-cap-done]');
        if (done) {
            form.style.display = 'none';
            done.classList.add('is-on');
        }
        confetti();
    });

    /* ------------------------------------------------- mobile sticky "Apply" */
    var sticky = root.querySelector('[data-cap-sticky]');
    if (sticky) {
        var card = root.querySelector('[data-cap-card]');
        window.addEventListener('scroll', function () {
            var r = card.getBoundingClientRect();
            // Show while the card is off-screen.
            sticky.classList.toggle('is-on', r.top > innerHeight || r.bottom < 0);
        }, { passive: true });
    }
})();
