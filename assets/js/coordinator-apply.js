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

        /* Last, because it is about the submission rather than any one field:
           every attachment can be individually legal and the request still be
           refused for its total size. Sending it anyway is the worst outcome —
           PHP would discard the body and the applicant would be told their
           whole form was blank — so it is stopped here instead. */
        if (typeof paintTotal === 'function' && paintTotal() > MAX_TOTAL) {
            var over = form.querySelector('[data-coa-doc-total]')
                    || form.querySelector('[data-coa-doc]');
            if (over && !firstBad) { firstBad = over; }
        }

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
    /* Two ceilings, both read from the form rather than assumed. They are
       php.ini's upload_max_filesize and post_max_size, rendered into
       data-max-file / data-max-total by coordinator-apply.php.

       This block used to hardcode 5 MB per file and check nothing else, which
       was wrong twice over. Production runs the stock PHP defaults - 2 MB per
       file, 8 MB per request - so a normal phone photograph was waved through
       here and rejected by the server, and two or three documents together
       exceeded post_max_size, at which point PHP discards the entire body and
       the applicant is told that every field they filled in is empty.

       So: shrink what can be shrunk, refuse the rest before it is sent, and
       never let the running total pass the server's budget. */
    var MAX_FILE  = parseInt(form.getAttribute('data-max-file'), 10)  || (2 * 1024 * 1024);
    var MAX_TOTAL = parseInt(form.getAttribute('data-max-total'), 10) || (8 * 1024 * 1024);

    var totalOut = form.querySelector('[data-coa-doc-total]');

    function human(b) {
        return b < 1024 ? b + ' B'
             : b < 1048576 ? (b / 1024).toFixed(0) + ' KB'
             : (b / 1048576).toFixed(1) + ' MB';
    }

    /** Every file currently attached, across all ten slots. */
    function attached() {
        var out = [];
        form.querySelectorAll('[data-coa-doc] input[type="file"]').forEach(function (i) {
            if (i.files && i.files[0]) { out.push(i.files[0]); }
        });
        return out;
    }

    function totalBytes() {
        return attached().reduce(function (n, f) { return n + f.size; }, 0);
    }

    /**
     * Paint the running total, and flag it when the submission as a whole no
     * longer fits. Returns the total so callers can act on it.
     */
    function paintTotal() {
        var total = totalBytes();
        var count = attached().length;
        if (!totalOut) { return total; }
        if (!count) {
            totalOut.hidden = true;
            totalOut.textContent = '';
            return total;
        }
        totalOut.hidden = false;
        totalOut.classList.toggle('is-over', total > MAX_TOTAL);
        totalOut.textContent = count + (count === 1 ? ' document' : ' documents')
            + ' attached \u00b7 ' + human(total) + ' of ' + human(MAX_TOTAL)
            + (total > MAX_TOTAL ? ' \u2014 too large to send. Please remove or replace a document.' : '');
        return total;
    }

    /* Re-encoding a photograph is what makes this form usable on a 2 MB server:
       a 4 MB phone picture becomes a few hundred KB with no meaningful loss for
       an identity document. Only real raster images can be re-encoded - a PDF
       or a DOC has to be refused instead. */
    var SHRINKABLE = ['jpg', 'jpeg', 'png', 'webp'];
    var MAX_EDGE   = 1600;

    function canShrink(ext) {
        return SHRINKABLE.indexOf(ext) > -1
            && typeof HTMLCanvasElement !== 'undefined'
            && typeof DataTransfer !== 'undefined';
    }

    /**
     * Decode a picked file into something drawable.
     *
     * createImageBitmap() is tried first and is the reason this works at all on
     * the live site: it decodes a Blob directly, so no URL is ever created and
     * the Content-Security-Policy is never consulted. The obvious approach —
     * URL.createObjectURL() into an <img> — is silently blocked here, because
     * the site's CSP allows img-src 'self' data: https: and a blob: URL matches
     * none of those. The image simply fires onerror, every photograph looks
     * un-shrinkable, and the applicant is told to find a smaller one.
     *
     * The <img> path is kept as a fallback for browsers without
     * createImageBitmap; it needs blob: in img-src to work (see .htaccess).
     */
    function decodeImage(file, done) {
        if (typeof createImageBitmap === 'function') {
            createImageBitmap(file).then(function (bmp) {
                done(bmp, bmp.width, bmp.height);
            }).catch(function () {
                decodeViaImg(file, done);
            });
            return;
        }
        decodeViaImg(file, done);
    }

    function decodeViaImg(file, done) {
        var URLish = window.URL || window.webkitURL;
        if (!URLish) { done(null); return; }

        var url = URLish.createObjectURL(file);
        var img = new Image();
        img.onload = function () {
            URLish.revokeObjectURL(url);
            done(img, img.naturalWidth || img.width, img.naturalHeight || img.height);
        };
        img.onerror = function () {
            URLish.revokeObjectURL(url);
            done(null);
        };
        img.src = url;
    }

    /**
     * Downscale an image to fit MAX_EDGE and re-encode it as JPEG, stepping the
     * quality down until it fits the per-file budget. Calls back with the new
     * File, or with null when it cannot be brought under the limit.
     *
     * The result is always named .jpg. The server checks that the real content
     * type matches the extension, so handing it JPEG bytes inside a .png name
     * would be rejected as a mismatch.
     */
    function shrinkImage(file, budget, done) {
        decodeImage(file, function (source, w, h) {
            if (!source || !w || !h) { done(null); return; }

            var scale = Math.min(1, MAX_EDGE / Math.max(w, h));
            var cv    = document.createElement('canvas');
            cv.width  = Math.max(1, Math.round(w * scale));
            cv.height = Math.max(1, Math.round(h * scale));

            var ctx = cv.getContext('2d');
            if (!ctx) { done(null); return; }
            ctx.drawImage(source, 0, 0, cv.width, cv.height);
            if (source.close) { source.close(); }      // release an ImageBitmap

            var qualities = [0.82, 0.7, 0.6, 0.5, 0.4];
            var i = 0;

            (function attempt() {
                if (i >= qualities.length) { done(null); return; }
                cv.toBlob(function (blob) {
                    if (!blob) { done(null); return; }
                    if (blob.size > budget && i < qualities.length - 1) { i++; attempt(); return; }
                    if (blob.size > budget) { done(null); return; }
                    var base = (file.name.replace(/\.[^.]+$/, '') || 'document');
                    done(new File([blob], base + '.jpg', {
                        type: 'image/jpeg',
                        lastModified: Date.now()
                    }));
                }, 'image/jpeg', qualities[i]);
            })();
        });
    }

    /** Put a replacement File into a file input, keeping it a real upload. */
    function setFile(input, file) {
        var dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
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
            row.classList.remove('is-set', 'is-busy');
            if (out) { out.textContent = hint; }
            if (err) { err.textContent = message || ''; }
            row.classList.toggle('has-error', !!message);
            paintTotal();
        }

        /** Show an accepted file, noting when it had to be resized. */
        function accept(file, note) {
            row.classList.remove('has-error', 'is-busy');
            if (err) { err.textContent = ''; }
            if (out) { out.textContent = file.name + ' \u00b7 ' + human(file.size) + (note || ''); }
            row.classList.add('is-set');

            /* One file can be within its own limit and still not fit alongside
               the others; say so on the slot that broke the budget. */
            if (paintTotal() > MAX_TOTAL) {
                row.classList.add('has-error');
                if (err) {
                    err.textContent = 'Together your documents are over the '
                        + human(MAX_TOTAL) + ' limit for one submission.';
                }
            }
        }

        input.addEventListener('change', function () {
            var f = input.files && input.files[0];
            if (!f) { clear(''); return; }

            var ext = (f.name.split('.').pop() || '').toLowerCase();
            if (okExt.length && okExt.indexOf(ext) === -1) {
                clear('Please attach a ' + okExt.join(', ').toUpperCase() + ' file.');
                return;
            }

            if (f.size <= MAX_FILE) { accept(f, ''); return; }

            if (!canShrink(ext)) {
                clear('That file is ' + human(f.size) + ' \u2014 the limit is ' + human(MAX_FILE)
                    + '. Please attach a smaller file.');
                return;
            }

            row.classList.add('is-busy');
            if (out) { out.textContent = 'Resizing ' + f.name + '\u2026'; }

            shrinkImage(f, MAX_FILE, function (small) {
                if (!small) {
                    clear('That image is ' + human(f.size) + ' and could not be reduced below '
                        + human(MAX_FILE) + '. Please attach a smaller photo.');
                    return;
                }
                try {
                    setFile(input, small);
                } catch (e) {
                    clear('That file is ' + human(f.size) + ' \u2014 the limit is ' + human(MAX_FILE) + '.');
                    return;
                }
                accept(small, ' \u00b7 resized from ' + human(f.size));
            });
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
