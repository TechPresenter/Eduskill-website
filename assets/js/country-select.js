/* =============================================================================
 *  Country selector — flag + dial code + searchable list + phone validation
 * -----------------------------------------------------------------------------
 *  Progressive enhancement: the markup rendered by country_field() already
 *  contains a real <select> and a real <input>, so the form submits correctly
 *  with JavaScript disabled. This script replaces the <select> with a
 *  searchable listbox and adds live validation.
 *
 *  FLAG RENDERING — the reason this does not just print an emoji:
 *  Windows has no colour glyphs for regional-indicator pairs, so "🇮🇳" renders
 *  as the letters "IN" there while macOS/iOS/Android show a flag. We measure
 *  once whether the platform actually ligates a flag emoji, and fall back to a
 *  styled ISO badge when it does not — so the control looks deliberate on every
 *  OS instead of showing stray letters.
 * ========================================================================== */
(function () {
    'use strict';

    /* ---- does this platform render emoji flags? (measured once) ----------- */
    var FLAGS_OK = (function () {
        try {
            var c = document.createElement('canvas');
            if (!c.getContext) { return false; }
            var ctx = c.getContext('2d');
            ctx.font = '20px serif';
            // A rendered flag is one glyph and is narrower than two letters.
            var flag = ctx.measureText('🇮🇳').width; // IN
            var two  = ctx.measureText('IN').width;
            return flag > 0 && Math.abs(flag - two) > 1.5;
        } catch (e) { return false; }
    })();

    function flagFor(iso) {
        if (!FLAGS_OK) { return null; }
        return String.fromCodePoint.apply(String, iso.toUpperCase().split('').map(function (ch) {
            return 0x1F1E6 + ch.charCodeAt(0) - 65;
        }));
    }

    /** Paint the flag slot: emoji where supported, ISO badge otherwise. */
    function paintFlag(el, iso) {
        var f = flagFor(iso);
        if (f) {
            el.textContent = f;
            el.classList.remove('cs-flag-code');
        } else {
            el.textContent = iso.toUpperCase();
            el.classList.add('cs-flag-code');
        }
    }

    function init(root) {
        if (root.dataset.csBound) { return; }
        root.dataset.csBound = '1';

        var DATA   = JSON.parse(document.getElementById('cs-country-data').textContent);
        var select = root.querySelector('[data-cs-select]');   // real <select>, kept for no-JS
        var phone  = root.querySelector('[data-cs-phone]');
        var btn    = root.querySelector('[data-cs-toggle]');
        var pop    = root.querySelector('[data-cs-pop]');
        var search = root.querySelector('[data-cs-search]');
        var list   = root.querySelector('[data-cs-list]');
        var flagEl = root.querySelector('[data-cs-flag]');
        var dialEl = root.querySelector('[data-cs-dial]');
        var errEl  = root.querySelector('[data-cs-error]');
        var hidIso = root.querySelector('[data-cs-iso]');
        var hidName= root.querySelector('[data-cs-name]');
        var hidDial= root.querySelector('[data-cs-dialval]');
        var byIso  = {};
        DATA.forEach(function (c) { byIso[c.i] = c; });

        var current = byIso[select.value] || byIso.IN || DATA[0];
        var filtered = DATA.slice();
        var active = 0;

        /* ---- rendering ---------------------------------------------------- */
        function paintCurrent() {
            paintFlag(flagEl, current.i);
            dialEl.textContent = '+' + current.d;
            select.value  = current.i;
            hidIso.value  = current.i;
            hidName.value = current.n;
            hidDial.value = current.d;
            phone.setAttribute('inputmode', 'tel');
            phone.setAttribute('autocomplete', 'tel-national');
            phone.setAttribute('aria-describedby', errEl.id);
            phone.placeholder = current.mn === current.mx
                ? new Array(current.mn + 1).join('0')
                : current.mn + '–' + current.mx + ' digits';
            btn.setAttribute('aria-label', 'Country: ' + current.n + ' (+' + current.d + ')');
        }

        function renderList() {
            list.innerHTML = '';
            if (!filtered.length) {
                var li = document.createElement('li');
                li.className = 'cs-empty';
                li.textContent = 'No country matches that search.';
                list.appendChild(li);
                return;
            }
            var frag = document.createDocumentFragment();
            filtered.forEach(function (c, i) {
                var li = document.createElement('li');
                li.className = 'cs-item' + (i === active ? ' is-active' : '');
                li.setAttribute('role', 'option');
                li.id = 'cs-opt-' + c.i;
                li.setAttribute('aria-selected', String(c.i === current.i));
                li.dataset.iso = c.i;

                var f = document.createElement('span');
                f.className = 'cs-flag';
                paintFlag(f, c.i);

                var n = document.createElement('span');
                n.className = 'cs-name';
                n.textContent = c.n;

                var d = document.createElement('span');
                d.className = 'cs-dial';
                d.textContent = '+' + c.d;

                li.appendChild(f); li.appendChild(n); li.appendChild(d);
                frag.appendChild(li);
            });
            list.appendChild(frag);
        }

        /* ---- open / close -------------------------------------------------- */
        function open() {
            root.classList.add('is-open');
            pop.hidden = false;
            btn.setAttribute('aria-expanded', 'true');
            search.value = '';
            filtered = DATA.slice();
            active = Math.max(0, filtered.findIndex(function (c) { return c.i === current.i; }));
            renderList();
            scrollActive();
            search.focus();
        }
        function close() {
            root.classList.remove('is-open');
            btn.setAttribute('aria-expanded', 'false');
            setTimeout(function () { if (!root.classList.contains('is-open')) { pop.hidden = true; } }, 160);
        }
        function scrollActive() {
            var el = list.children[active];
            if (el && el.scrollIntoView) { el.scrollIntoView({ block: 'nearest' }); }
        }

        function choose(iso) {
            if (!byIso[iso]) { return; }
            current = byIso[iso];
            paintCurrent();
            // Remember for next visit / other forms.
            document.cookie = 'pwf_country=' + iso + ';path=/;max-age=31536000;SameSite=Lax';
            close();
            btn.focus();
            validate();
        }

        /* ---- validation ---------------------------------------------------- */
        function validate(showEmpty) {
            var digits = (phone.value || '').replace(/\D+/g, '');
            // Tolerate a pasted dial code.
            if (digits.indexOf(current.d) === 0 && digits.length > current.mx) {
                digits = digits.slice(current.d.length);
            }
            digits = digits.replace(/^0+/, '');

            if (!digits) {
                setError(showEmpty && phone.required ? 'Please enter a phone number.' : '');
                return !phone.required;
            }
            if (digits.length < current.mn || digits.length > current.mx) {
                var expect = current.mn === current.mx
                    ? current.mn + ' digits'
                    : current.mn + '–' + current.mx + ' digits';
                setError('Enter a valid ' + current.n + ' number (' + expect + ').');
                return false;
            }
            setError('');
            return true;
        }
        function setError(msg) {
            errEl.textContent = msg;
            root.classList.toggle('has-error', !!msg);
            root.classList.toggle('is-valid', !msg && !!(phone.value || '').replace(/\D+/g, ''));
            phone.setAttribute('aria-invalid', msg ? 'true' : 'false');
        }

        /* ---- events -------------------------------------------------------- */
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            root.classList.contains('is-open') ? close() : open();
        });

        search.addEventListener('input', function () {
            var q = search.value.trim().toLowerCase();
            filtered = !q ? DATA.slice() : DATA.filter(function (c) {
                return c.n.toLowerCase().indexOf(q) > -1 ||
                       c.i.toLowerCase().indexOf(q) > -1 ||
                       ('+' + c.d).indexOf(q) > -1 || c.d.indexOf(q) > -1;
            });
            active = 0;
            renderList();
        });

        list.addEventListener('click', function (e) {
            var li = e.target.closest('[data-iso]');
            if (li) { choose(li.dataset.iso); }
        });

        pop.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { e.preventDefault(); close(); btn.focus(); return; }
            if (e.key === 'Enter') {
                e.preventDefault();
                if (filtered[active]) { choose(filtered[active].i); }
                return;
            }
            if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') { return; }
            e.preventDefault();
            if (!filtered.length) { return; }
            active = e.key === 'ArrowDown'
                ? (active + 1) % filtered.length
                : (active <= 0 ? filtered.length - 1 : active - 1);
            renderList();
            scrollActive();
            list.setAttribute('aria-activedescendant', 'cs-opt-' + filtered[active].i);
        });

        document.addEventListener('click', function (e) { if (!root.contains(e.target)) { close(); } });

        phone.addEventListener('input', function () { if (root.classList.contains('has-error')) { validate(); } });
        phone.addEventListener('blur',  function () { validate(true); });

        // Block submission on an invalid number, and surface the message.
        var form = root.closest('form');
        if (form && !form.dataset.csGuard) {
            form.dataset.csGuard = '1';
            form.addEventListener('submit', function (e) {
                var bad = null;
                form.querySelectorAll('[data-country-select]').forEach(function (r) {
                    var v = r._csValidate && r._csValidate(true);
                    if (v === false && !bad) { bad = r; }
                });
                if (bad) {
                    e.preventDefault();
                    var p = bad.querySelector('[data-cs-phone]');
                    if (p) { p.focus(); }
                }
            });
        }
        root._csValidate = validate;

        paintCurrent();
    }

    function boot() {
        if (!document.getElementById('cs-country-data')) { return; }
        document.querySelectorAll('[data-country-select]').forEach(init);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
    // Re-scan when forms are injected dynamically (modals, AJAX pages).
    window.pwfCountrySelectRefresh = boot;
})();
