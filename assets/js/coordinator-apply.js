/* =============================================================================
 *  Community Coordinator application — form behaviour
 * -----------------------------------------------------------------------------
 *  One long form, every field on screen at once. This script only adds the
 *  things a long paper-style form needs to stay manageable:
 *
 *    - the position picker revealing its matching "preferred area" fieldset
 *    - yes/no pairs revealing their follow-up questions
 *    - per-slot document validation (extension + size) with a live file name
 *    - a localStorage draft so a long form survives a closed tab
 *    - one validation pass on submit that lands the reader on the first problem
 *    - the success panel, fed the reference number the handler returns
 *
 *  Everything degrades: with JS off, all conditional blocks are simply visible,
 *  the form posts normally, and the handler validates every rule again anyway.
 *
 *  Submission itself belongs to assets/js/forms.js via [data-ajax-form]. What
 *  this script must guarantee is that forms.js never sees a form whose only
 *  invalid control sits inside a hidden conditional block: reportValidity()
 *  cannot focus a display:none field, so the submit would stall with nothing
 *  shown. The submit hook below stops the event outright in that case.
 * ========================================================================== */
(function () {
    'use strict';

    var root = document.querySelector('[data-coa]');
    if (!root) { return; }

    var REDUCED = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var form    = root.querySelector('[data-coa-form]');
    var STORE   = 'pwf_coordinator_draft';

    /* ------------------------------------------------------- scroll reveal */
    /* threshold 0, not .1: the ratio is measured against the ELEMENT, so a block
       taller than ~10 viewports can never reach 10% visibility and would stay at
       opacity:0 for good. Any intersection at all is enough to reveal. The
       negative rootMargin is gone for the same reason — it shrinks the root and
       makes a tall element harder still to trigger. */
    var revealables = root.querySelectorAll('.cap-rv');
    if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (!en.isIntersecting) { return; }
                en.target.classList.add('is-in');
                io.unobserve(en.target);
            });
        }, { threshold: 0 });
        revealables.forEach(function (el) { io.observe(el); });

        /* Failsafe: nothing may stay invisible because an observer callback did
           not arrive — a bfcache restore, a deep link, a browser that throttles
           observers in a background tab. Anything still hidden after load is
           shown unconditionally. */
        window.addEventListener('load', function () {
            setTimeout(function () {
                revealables.forEach(function (el) { el.classList.add('is-in'); });
            }, 1200);
        });
    } else {
        revealables.forEach(function (el) { el.classList.add('is-in'); });
    }

    if (!form) { return; }

    /* --------------------------------------------------------- validation */

    /** Paint one field's validity onto its .cap-f wrapper. */
    function markField(el) {
        var wrap = el.closest('.cap-f');
        var bad  = !el.checkValidity();
        if (wrap) {
            wrap.classList.toggle('has-error', bad);
            wrap.classList.toggle('is-ok', !bad && !!el.value);
            var err = wrap.querySelector('.cap-err');
            if (err) { err.textContent = bad ? (el.validationMessage || 'Please check this field.') : ''; }
        }
        return !bad;
    }

    function focusProblem(el) {
        if (el.focus) { el.focus({ preventScroll: true }); }
        el.scrollIntoView({ block: 'center', behavior: REDUCED ? 'auto' : 'smooth' });
    }

    /**
     * Validate the whole form and return the first element that failed, or
     * null. Three checks the native constraint API cannot cover on its own:
     * the radio group whose inputs are visually hidden (it could never be
     * focused for a native message), the required document slots (a file input
     * cannot be pre-filled or focused meaningfully), and phone numbers, whose
     * per-country length rules belong to country-select.js.
     */
    function findProblem() {
        var firstBad = null;

        form.querySelectorAll('input, select, textarea').forEach(function (el) {
            if (el.type === 'file' || el.type === 'radio' || el.type === 'checkbox' || el.disabled) { return; }
            // A field inside a hidden conditional block is not being asked for.
            if (el.closest('.coa-area:not(.is-on), .coa-if:not(.is-on)')) { return; }
            if (!markField(el) && !firstBad) { firstBad = el; }
        });

        form.querySelectorAll('[data-country-select]').forEach(function (cs) {
            if (cs._csValidate && cs._csValidate(true) === false && !firstBad) {
                firstBad = cs.querySelector('[data-cs-phone]') || cs;
            }
        });

        form.querySelectorAll('[data-coa-group]').forEach(function (group) {
            var name = group.getAttribute('data-coa-group');
            var got  = form.querySelector('[name="' + name + '"]:checked');
            var err  = group.querySelector('[data-coa-group-err]');
            group.classList.toggle('has-error', !got);
            if (err) { err.textContent = got ? '' : (group.getAttribute('data-coa-group-msg') || 'Please choose an option.'); }
            if (!got && !firstBad) { firstBad = group; }
        });

        form.querySelectorAll('[data-coa-doc][data-coa-doc-required]').forEach(function (row) {
            var input = row.querySelector('input[type="file"]');
            var has   = input && input.files && input.files.length > 0;
            row.classList.toggle('has-error', !has);
            var err = row.querySelector('[data-coa-doc-err]');
            if (err && !has) { err.textContent = 'This document is required.'; }
            if (!has && !firstBad) { firstBad = row; }
        });

        return firstBad;
    }

    /* ------------------------------------------------- inline field feedback */
    form.querySelectorAll('.cap-f input, .cap-f select, .cap-f textarea').forEach(function (el) {
        el.addEventListener('blur', function () {
            if (!el.value && !el.required) { return; }
            markField(el);
        });
        if (el.tagName === 'SELECT') {
            var sync = function () { el.classList.toggle('has-value', !!el.value); };
            el.addEventListener('change', sync);
            sync();
        }
    });

    /* --------------------------------------------------- character counters */
    form.querySelectorAll('[data-coa-count]').forEach(function (ta) {
        var out = ta.parentNode.querySelector('.cap-count');
        if (!out) { return; }
        var max = parseInt(ta.getAttribute('maxlength') || '0', 10);
        var paint = function () { out.textContent = ta.value.length + (max ? ' / ' + max : ''); };
        ta.addEventListener('input', paint);
        paint();
    });

    /* ------------------------------------------- position -> area fieldsets */
    var areas = Array.prototype.slice.call(form.querySelectorAll('[data-coa-area]'));
    function paintAreas() {
        var picked = form.querySelector('[name="position"]:checked');
        var key = picked ? picked.value : '';
        areas.forEach(function (a) { a.classList.toggle('is-on', a.getAttribute('data-coa-area') === key); });
    }
    form.querySelectorAll('[name="position"]').forEach(function (r) {
        r.addEventListener('change', function () {
            paintAreas();
            var group = r.closest('[data-coa-group]');
            if (group) {
                group.classList.remove('has-error');
                var err = group.querySelector('[data-coa-group-err]');
                if (err) { err.textContent = ''; }
            }
        });
    });
    paintAreas();

    /* ------------------------------------------- yes/no -> follow-up blocks */
    form.querySelectorAll('[data-coa-toggle]').forEach(function (input) {
        var target = form.querySelector('[data-coa-if="' + input.getAttribute('data-coa-toggle') + '"]');
        if (!target) { return; }
        var sync = function () { target.classList.toggle('is-on', input.checked); };
        // Radios only fire change on the one being selected — watch the group.
        form.querySelectorAll('[name="' + input.name + '"]').forEach(function (sib) {
            sib.addEventListener('change', sync);
        });
        sync();
    });

    /* ------------------------------------------------------ document slots */
    var MAX_BYTES = 5 * 1024 * 1024;

    function human(b) {
        return b < 1024 ? b + ' B'
             : b < 1048576 ? (b / 1024).toFixed(0) + ' KB'
             : (b / 1048576).toFixed(1) + ' MB';
    }

    form.querySelectorAll('[data-coa-doc]').forEach(function (row) {
        var input = row.querySelector('input[type="file"]');
        var out   = row.querySelector('[data-coa-doc-name]');
        var err   = row.querySelector('[data-coa-doc-err]');
        var kill  = row.querySelector('[data-coa-doc-remove]');
        var hint  = out ? out.textContent : '';
        var okExt = (input.getAttribute('accept') || '')
            .split(',')
            .map(function (s) { return s.trim().replace(/^\./, '').toLowerCase(); })
            .filter(Boolean);

        function clear(message) {
            input.value = '';
            row.classList.remove('is-set');
            if (out) { out.textContent = hint; }
            if (err) { err.textContent = message || ''; }
            row.classList.toggle('has-error', !!message);
        }

        input.addEventListener('change', function () {
            var f = input.files && input.files[0];
            if (!f) { clear(''); return; }
            var ext = (f.name.split('.').pop() || '').toLowerCase();
            if (okExt.length && okExt.indexOf(ext) === -1) {
                clear('Please attach a ' + okExt.join(', ').toUpperCase() + ' file.');
                return;
            }
            if (f.size > MAX_BYTES) {
                clear('That file is ' + human(f.size) + ' — the limit is 5 MB.');
                return;
            }
            row.classList.remove('has-error');
            if (err) { err.textContent = ''; }
            if (out) { out.textContent = f.name + ' · ' + human(f.size); }
            row.classList.add('is-set');
        });

        if (kill) {
            kill.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                clear('');
            });
        }
    });

    /* ------------------------------------------------------------- autosave */
    var SKIP = ['csrf_token', 'pwf_zq'];
    function fieldKey(el) { return el.name + (el.type === 'checkbox' || el.type === 'radio' ? '::' + el.value : ''); }

    function save() {
        try {
            var data = {};
            form.querySelectorAll('input, select, textarea').forEach(function (el) {
                if (!el.name || el.type === 'file' || SKIP.indexOf(el.name) > -1) { return; }
                if (el.type === 'checkbox' || el.type === 'radio') {
                    if (el.checked) { data[fieldKey(el)] = 1; }
                } else if (el.value) {
                    data[el.name] = el.value;
                }
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
            form.querySelectorAll('input, select, textarea').forEach(function (el) {
                if (!el.name || el.type === 'file' || SKIP.indexOf(el.name) > -1) { return; }
                var v = box.d[fieldKey(el)];
                if (v === undefined) { return; }
                if (el.type === 'checkbox' || el.type === 'radio') { el.checked = true; }
                else if (!el.value) { el.value = v; }
            });
            paintAreas();
            form.querySelectorAll('[data-coa-toggle]').forEach(function (i) {
                var t = form.querySelector('[data-coa-if="' + i.getAttribute('data-coa-toggle') + '"]');
                if (t) { t.classList.toggle('is-on', i.checked); }
            });
            form.querySelectorAll('select').forEach(function (s) { s.classList.toggle('has-value', !!s.value); });
        } catch (e) { /* ignore */ }
    }
    restore();

    var saveTimer;
    function queueSave() {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(save, 700);
    }
    form.addEventListener('input', queueSave);
    form.addEventListener('change', queueSave);

    /* --------------------------------------------------------------- ripple */
    form.querySelectorAll('.cap-btn').forEach(function (b) {
        b.addEventListener('click', function (e) {
            if (REDUCED) { return; }
            var r = b.getBoundingClientRect();
            var s = document.createElement('span');
            var d = Math.max(r.width, r.height);
            s.className = 'cap-ripple';
            s.style.width = s.style.height = d + 'px';
            s.style.left = (e.clientX - r.left - d / 2) + 'px';
            s.style.top  = (e.clientY - r.top  - d / 2) + 'px';
            b.appendChild(s);
            setTimeout(function () { s.remove(); }, 600);
        });
    });

    /* --------------------------------------------------------------- submit */
    form.addEventListener('submit', function (e) {
        var bad = findProblem();
        if (bad) {
            /* stopImmediatePropagation, not just preventDefault: forms.js has
               its own submit listener that would otherwise run
               form.checkValidity() and stall on a control it cannot focus,
               leaving the user with a dead button and no message. */
            e.preventDefault();
            e.stopImmediatePropagation();
            focusProblem(bad);
        }
        /* The button's loading state is deliberately left to forms.js, which
           disables it, swaps the label and restores it in a finally block. If
           this listener changed the label first, forms.js would capture the
           "submitting" markup as the label to restore, and a failed POST would
           leave the button stuck mid-spinner. */
    });

    // Dispatched by assets/js/forms.js once the POST comes back successful.
    form.addEventListener('pwf:submitted', function (e) {
        try { localStorage.removeItem(STORE); } catch (err) {}
        var done = root.querySelector('[data-coa-done]');
        var ref  = root.querySelector('[data-coa-ref]');
        var no   = e.detail && e.detail.application_no;
        if (ref) {
            if (no) { ref.textContent = no; }
            else { ref.style.display = 'none'; }
        }
        if (done) {
            form.style.display = 'none';
            var head = root.querySelector('[data-coa-formhead]');
            if (head) { head.style.display = 'none'; }
            done.classList.add('is-on');
            done.scrollIntoView({ behavior: REDUCED ? 'auto' : 'smooth', block: 'center' });
        }
    });

    /* A field the server rejected still needs pointing at — forms.js attaches
       the message to the input, which may be far up a very long page. */
    form.addEventListener('pwf:failed', function (e) {
        var errs = e.detail && e.detail.errors;
        if (!errs) { return; }
        var first = Object.keys(errs)[0];
        var el = form.querySelector('[name="' + first + '"]')
              || form.querySelector('[data-coa-doc-slot="' + first + '"]');
        if (el) { focusProblem(el); }
    });

    /* ------------------------------------------------- mobile sticky "Apply" */
    var sticky = root.querySelector('[data-coa-sticky]');
    var card   = root.querySelector('[data-coa-card]');
    if (sticky && card) {
        window.addEventListener('scroll', function () {
            var r = card.getBoundingClientRect();
            sticky.classList.toggle('is-on', r.top > innerHeight || r.bottom < 0);
        }, { passive: true });
    }
})();
