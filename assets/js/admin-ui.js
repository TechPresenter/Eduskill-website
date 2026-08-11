/* =============================================================================
   admin-ui.js — the behaviour half of the admin design system.
   -----------------------------------------------------------------------------
   The class API lives in assets/css/admin-ds.css (loaded LAST on admin pages).
   This file drives the five components that need JS to exist at all:

       1. toasts                 AdminUI.toast(message, tone, title)
       2. drawer / bottom sheet  AdminUI.openDrawer(id) / closeDrawer(id)
       3. bulk row selection     [data-bulk-all] + [data-bulk-item] + .bulk-bar
       4. confirm dialog         AdminUI.confirm(idOrOptions) -> Promise<boolean>
       5. table column chooser   [data-cols-for] (persisted in localStorage)

   Conventions inherited from assets/js/admin.js: one IIFE, vanilla ES5-safe
   ES6, no framework, no build step, no dependency on SweetAlert/lucide.

   RULES THIS FILE OBEYS
   ---------------------
   * Fail soft. It loads on 137 screens; a missing element must be a no-op, not
     an exception that kills every other script on the page. Every public entry
     point and every delegated listener is wrapped.
   * prefers-reduced-motion: every JS-side delay derived from --dur-* collapses
     to 0 so nothing waits on a transition that will not run.
   * Status is never colour alone — a toast carries a hue, a glyph AND a word.
   * No inline geometry that is not built from the DS tokens (--sp-*, --r-*,
     --elev-*), because two components (the column menu and the no-<dialog>
     fallback) need positioning that the stylesheet does not declare.

   It deliberately does NOT re-implement anything already in admin.js
   (sidebar, topbar, table search, image preview, auto-slug, CKEditor).

   ONE OVERLAP, HANDLED ON PURPOSE: admin.js:initConfirm() binds [data-confirm]
   to a SweetAlert2 popup. The design system owns that interaction now, so the
   handlers here run in the CAPTURE phase and stop propagation — the element's
   own bubble-phase listener (admin.js's, or a page's inline onclick) never sees
   the first event. After the user confirms, the original interaction is
   re-dispatched with a one-shot bypass flag, so page-level handlers still run
   exactly once, in their normal order. Nothing in admin.js had to change.
   ============================================================================= */
(function () {
    'use strict';

    if (window.AdminUI) { return; }              // never double-install

    var DOC  = document;
    var HTML = DOC.documentElement;

    /* ---------------------------------------------------------------- utils */

    function q(sel, root) {
        try { return (root || DOC).querySelector(sel); } catch (e) { return null; }
    }
    function qa(sel, root) {
        try { return Array.prototype.slice.call((root || DOC).querySelectorAll(sel)); }
        catch (e) { return []; }
    }
    function el(tag, cls, text) {
        var n = DOC.createElement(tag);
        if (cls) { n.className = cls; }
        if (text != null) { n.textContent = text; }
        return n;
    }
    /* Wrap anything that a browser event will call into. A throw inside a
       delegated listener is the one failure mode that can take a page down. */
    function guard(fn) {
        return function () {
            try { return fn.apply(this, arguments); }
            catch (err) {
                if (window.console && console.warn) { console.warn('[AdminUI]', err); }
                return undefined;
            }
        };
    }
    function reducedMotion() {
        try { return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches); }
        catch (e) { return false; }
    }
    /* Mirrors --dur-1/2/3. Returns 0 when the OS asked for no motion, so we
       never sit waiting for a transition the stylesheet has flattened. */
    function ms(n) { return reducedMotion() ? 0 : n; }
    var DUR_1 = 150, DUR_2 = 240, DUR_3 = 400;

    function focusable(root) {
        return qa('a[href],area[href],button,input,select,textarea,summary,iframe,' +
                  'audio[controls],video[controls],[tabindex],[contenteditable="true"]', root)
            .filter(function (n) {
                if (n.disabled || n.getAttribute('aria-hidden') === 'true') { return false; }
                if (n.hasAttribute('inert') || (n.closest && n.closest('[inert]'))) { return false; }
                if (n.getAttribute('tabindex') === '-1') { return false; }
                if (n.type === 'hidden') { return false; }
                return !!(n.offsetWidth || n.offsetHeight || n.getClientRects().length);
            });
    }
    function tryFocus(node) {
        if (!node || !node.focus) { return false; }
        try { node.focus({ preventScroll: true }); } catch (e) { try { node.focus(); } catch (e2) { return false; } }
        return DOC.activeElement === node;
    }
    function emit(node, name, detail) {
        try { node.dispatchEvent(new CustomEvent(name, { bubbles: true, detail: detail || {} })); }
        catch (e) { /* IE-era CustomEvent — not a supported target */ }
    }
    function store(key, value) {                 // localStorage, never fatal
        try {
            if (value === undefined) { return localStorage.getItem(key); }
            if (value === null) { localStorage.removeItem(key); return null; }
            localStorage.setItem(key, value); return value;
        } catch (e) { return null; }
    }

    var ICON = {
        ok:    'M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z',
        warn:  'M12 2 1 21h22zm0 6 6.5 11h-13zm-1 3v4h2v-4zm0 5v2h2v-2z',
        err:   'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm4.3 13.3-1.4 1.4L12 13.9l-2.9 2.8-1.4-1.4L10.6 12 7.7 9.1l1.4-1.4L12 10.6l2.9-2.9 1.4 1.4L13.4 12z',
        info:  'M11 7h2v2h-2zm0 4h2v6h-2zm1-9a10 10 0 1 0 0 20 10 10 0 0 0 0-20z',
        close: 'M19 6.4 17.6 5 12 10.6 6.4 5 5 6.4 10.6 12 5 17.6 6.4 19 12 13.4 17.6 19 19 17.6 13.4 12z',
        cols:  'M3 4h18v16H3zm2 2v12h3.4V6zm5.4 0v12h3.2V6zm5.2 0v12H19V6z'
    };
    /* Same glyph vocabulary as the ::before masks in admin-ds.css, so a status
       reads identically whether CSS drew it or JS did. */
    function svg(path, size) {
        var s = size || 18;
        return '<svg viewBox="0 0 24 24" width="' + s + '" height="' + s +
               '" aria-hidden="true" focusable="false"><path fill="currentColor" d="' + path + '"/></svg>';
    }

    /* ------------------------------------------------- body scroll locking --
       Reference-counted: a confirm opened from inside a drawer must not unlock
       the page when it closes. The scrollbar's width is added back as padding
       so the layout does not jump sideways when the bar disappears. */
    var scrollLock = (function () {
        var depth = 0, prevOverflow = '', prevPad = '';
        return {
            lock: function () {
                if (depth++ > 0) { return; }
                var b = DOC.body, gap = window.innerWidth - HTML.clientWidth;
                prevOverflow = b.style.overflow;
                prevPad      = b.style.paddingRight;
                if (gap > 0) {
                    var cur = parseFloat(window.getComputedStyle(b).paddingRight) || 0;
                    b.style.paddingRight = (cur + gap) + 'px';
                }
                b.style.overflow = 'hidden';
            },
            unlock: function () {
                if (depth === 0 || --depth > 0) { return; }
                DOC.body.style.overflow     = prevOverflow;
                DOC.body.style.paddingRight = prevPad;
            }
        };
    })();

    /* ----------------------------------------------- background inert-ing --
       While a modal surface is open, everything else is removed from the a11y
       tree and from hit-testing. `inert` does the real work; aria-hidden is the
       fallback for browsers without it.

       Note it walks UP from each kept node and inerts its siblings at every
       level, rather than just inerting the body's other children: a .ds-drawer
       is normally rendered inside .admin-content, so inerting .admin-layout
       wholesale would inert the drawer along with it. Nodes marked data-ds-live
       (the toast stack) always stay reachable. */
    function hideBackground(keep) {
        var saved = [], live = [], marked = [];
        (keep || []).filter(Boolean).forEach(function (k) {
            var n = k;
            while (n && n !== DOC.body) { if (live.indexOf(n) < 0) { live.push(n); } n = n.parentElement; }
        });
        live.forEach(function (n) {
            var parent = n.parentElement;
            if (!parent) { return; }
            Array.prototype.forEach.call(parent.children, function (sib) {
                if (live.indexOf(sib) > -1 || marked.indexOf(sib) > -1) { return; }
                if (sib.hasAttribute('data-ds-live')) { return; }
                marked.push(sib);
                saved.push({ node: sib, aria: sib.getAttribute('aria-hidden'), inert: sib.hasAttribute('inert') });
                sib.setAttribute('aria-hidden', 'true');
                try { sib.inert = true; } catch (e) {}
            });
        });
        return saved;
    }
    function restoreBackground(saved) {
        (saved || []).forEach(function (rec) {
            if (rec.aria === null) { rec.node.removeAttribute('aria-hidden'); }
            else { rec.node.setAttribute('aria-hidden', rec.aria); }
            if (!rec.inert) { try { rec.node.inert = false; } catch (e) {} rec.node.removeAttribute('inert'); }
        });
    }

    /* -------------------------------------------------------- focus trap --
       Tab wraps inside the container; a focusin that lands outside (browser
       chrome, a stray programmatic focus) is pulled back to the first stop.

       Traps nest: a confirm opened from inside a drawer must win, so only the
       topmost trap enforces containment — otherwise the drawer would keep
       snatching focus back out of the dialog. */
    var trapStack = [];

    function trapFocus(container) {
        function onKey(e) {
            if (trapStack[trapStack.length - 1] !== container) { return; }
            if (e.key !== 'Tab' && e.keyCode !== 9) { return; }
            var list = focusable(container);
            if (!list.length) { e.preventDefault(); tryFocus(container); return; }
            var first = list[0], last = list[list.length - 1], a = DOC.activeElement;
            if (e.shiftKey && (a === first || !container.contains(a))) { e.preventDefault(); tryFocus(last); }
            else if (!e.shiftKey && (a === last || !container.contains(a))) { e.preventDefault(); tryFocus(first); }
        }
        function onFocusIn(e) {
            if (trapStack[trapStack.length - 1] !== container) { return; }
            if (container.contains(e.target)) { return; }
            var list = focusable(container);
            tryFocus(list.length ? list[0] : container);
        }
        container.addEventListener('keydown', onKey, true);
        DOC.addEventListener('focusin', onFocusIn, true);
        trapStack.push(container);
        return function release() {
            var i = trapStack.lastIndexOf(container);
            if (i > -1) { trapStack.splice(i, 1); }
            container.removeEventListener('keydown', onKey, true);
            DOC.removeEventListener('focusin', onFocusIn, true);
        };
    }

    /* =========================================================================
       1. TOASTS
       -------------------------------------------------------------------------
       AdminUI.toast('Saved.', 'ok', 'Programme updated')

       tone: ok | warn | error | info  (success/danger/warning aliases accepted)
       Auto-dismisses after a delay scaled to the reading length. Hover or
       keyboard focus pauses the countdown; it resumes on leave. Errors are
       announced assertively (role=alert), everything else politely
       (role=status).

       ERRORS DO NOT AUTO-DISMISS (WCAG 2.2.1). The only pause hooks a toast can
       offer are mouseenter and focusin, and the stack is at the end of <body> —
       a keyboard user cannot tab past a full admin page to reach it inside the
       old 8s error window, and the toast rightly does not steal focus. So there
       was no mechanism to extend or stop the limit at all. An error the user
       missed is the one case where persistence is correct: cap = Infinity, close
       button only. The others start at 6s, which is the floor 2.2.1 assumes for
       a "brief" message plus reading time on top.
       ========================================================================= */
    var TONES = {
        ok:    { cls: 'is-ok',    glyph: ICON.ok,   word: 'Success', role: 'status', live: 'polite',    base: 6000, cap: 10000 },
        warn:  { cls: 'is-warn',  glyph: ICON.warn, word: 'Warning', role: 'status', live: 'polite',    base: 7000, cap: 12000 },
        error: { cls: 'is-error', glyph: ICON.err,  word: 'Error',   role: 'alert',  live: 'assertive', base: Infinity, cap: Infinity },
        info:  { cls: '',         glyph: ICON.info, word: 'Notice',  role: 'status', live: 'polite',    base: 6000, cap: 10000 }
    };
    var TONE_ALIAS = {
        success: 'ok', ok: 'ok', done: 'ok', saved: 'ok',
        warn: 'warn', warning: 'warn', caution: 'warn',
        error: 'error', err: 'error', danger: 'error', fail: 'error',
        info: 'info', notice: 'info', neutral: 'info', '': 'info'
    };
    var MAX_TOASTS = 4;
    var toasts = [];                              // newest last

    function toneOf(t) {
        var k = String(t == null ? '' : t).toLowerCase().trim();
        return TONES[TONE_ALIAS[k] || 'info'];
    }
    /* The shell mounts #toastStack in admin/partials/foot.php. Prefer it by id:
       a page that renders its own flash stack higher up the document used to win
       the `.toast-stack` lookup, so runtime toasts landed inside page content
       while the shell's mount stayed empty forever. tabIndex makes the stack
       reachable so the focusin pause has a route (WCAG 2.2.1). */
    function toastStack() {
        if (!DOC.body) { return null; }
        var stack = DOC.getElementById('toastStack') || q('.toast-stack');
        if (!stack) {
            stack = el('div', 'toast-stack');
            stack.id = 'toastStack';
            stack.setAttribute('role', 'region');
            stack.setAttribute('aria-label', 'Notifications');
            stack.setAttribute('data-ds-live', '');   // never inert-ed by a modal
            DOC.body.appendChild(stack);
        }
        if (!stack.hasAttribute('tabindex')) { stack.tabIndex = -1; }
        return stack;
    }
    function readingTime(text, tone) {
        if (!isFinite(tone.base)) { return Infinity; }   // errors persist
        var n = Math.max(tone.base, 2200 + String(text).length * 55);
        return Math.min(n, tone.cap);
    }

    function toast(message, tone, title) {
        var handle = { close: function () {}, el: null };
        try {
            var stack = toastStack();
            if (!stack) { return handle; }

            var text = message == null ? '' : String(message);
            var t    = toneOf(tone);
            var head = title === undefined || title === null ? t.word : String(title);
            var key  = t.cls + ' ' + head + ' ' + text;

            /* Politely: an identical message still on screen restarts rather
               than stacking a duplicate — and is RE-ANNOUNCED. Restarting a timer
               mutates no DOM, so a live region says nothing; a user who clicked
               Save twice heard silence and could not tell whether the second
               action ran. restart() rewrites the text node to force it. */
            for (var i = 0; i < toasts.length; i++) {
                if (toasts[i].key === key && !toasts[i].leaving) { toasts[i].restart(); return toasts[i].handle; }
            }
            /* At the cap, evict the oldest — but never one that has been on screen
               for less than a transition: five messages inside one task could
               otherwise remove the first before assistive tech had reached it.
               A too-young oldest is SCHEDULED instead and the stack briefly holds
               one extra, with a hard ceiling two over the cap so a pathological
               burst still cannot grow without bound. */
            while (toasts.length >= MAX_TOASTS) {
                var oldest = toasts[0];
                var wait   = DUR_2 - (Date.now() - (oldest.shownAt || 0));
                if (wait <= 0 || toasts.length > MAX_TOASTS + 1) { oldest.dismiss(true); continue; }
                oldest.evictIn(wait);
                break;
            }

            var box = el('div', 'toast' + (t.cls ? ' ' + t.cls : ''));
            box.setAttribute('role', t.role);
            box.setAttribute('aria-live', t.live);
            box.setAttribute('aria-atomic', 'true');

            var ico = el('span', 't-ico');
            ico.innerHTML = svg(t.glyph);
            box.appendChild(ico);

            var body = el('div', 't-body');
            if (head.trim()) {
                body.appendChild(el('strong', 't-title', head));
            } else {
                /* Status is never colour alone: with no visible title, the tone
                   still reaches assistive tech as a word. */
                body.appendChild(el('span', 'sr-only', t.word + ': '));
            }
            var textNode = DOC.createTextNode(text);
            body.appendChild(textNode);
            box.appendChild(body);

            var close = el('button', 't-close');
            close.type = 'button';
            close.setAttribute('aria-label', 'Dismiss notification');
            close.innerHTML = svg(ICON.close, 14);
            box.appendChild(close);

            var life = readingTime(text, t), timer = null, endsAt = 0, remain = life;
            var evict = null;
            var rec = { key: key, node: box, leaving: false, handle: handle, shownAt: Date.now() };

            function stop() { if (timer) { clearTimeout(timer); timer = null; } }
            function start(from) {
                stop();
                remain = from == null ? remain : from;
                if (!isFinite(remain)) { endsAt = Infinity; return; }   // error: no timer at all
                endsAt = Date.now() + remain;
                timer  = setTimeout(function () { rec.dismiss(); }, remain);
            }
            function pause() { if (!timer) { return; } remain = Math.max(600, endsAt - Date.now()); stop(); }

            rec.restart = function () {
                /* Blank then rewrite: a live region announces a text CHANGE, and
                   an identical string is not one. */
                textNode.nodeValue = '';
                setTimeout(function () { if (!rec.leaving) { textNode.nodeValue = text; } }, 60);
                start(life);
            };
            rec.evictIn = function (delay) {
                if (evict) { return; }
                evict = setTimeout(function () { rec.dismiss(true); }, delay);
            };
            rec.dismiss = function (immediate) {
                if (rec.leaving) { return; }
                rec.leaving = true;
                if (evict) { clearTimeout(evict); evict = null; }
                stop();
                var idx = toasts.indexOf(rec);
                if (idx > -1) { toasts.splice(idx, 1); }
                var drop = function () { if (box.parentNode) { box.parentNode.removeChild(box); } };
                if (immediate || !ms(DUR_1)) { drop(); return; }
                box.classList.add('is-leaving');
                setTimeout(drop, ms(DUR_1) + 40);
            };

            close.addEventListener('click', guard(function () { rec.dismiss(); }));
            box.addEventListener('mouseenter', pause);
            box.addEventListener('mouseleave', guard(function () { if (!rec.leaving) { start(); } }));
            box.addEventListener('focusin', pause);
            box.addEventListener('focusout', guard(function () {
                if (!rec.leaving && !box.contains(DOC.activeElement)) { start(); }
            }));

            stack.appendChild(box);
            toasts.push(rec);
            start(life);

            handle.el    = box;
            handle.close = function () { rec.dismiss(); };
            return handle;
        } catch (e) {
            if (window.console && console.warn) { console.warn('[AdminUI] toast failed', e); }
            return handle;
        }
    }
    function clearToasts() {
        toasts.slice().forEach(function (r) { r.dismiss(true); });
    }

    /* ---------------------------------------------- server-rendered toasts --
       admin_toast() emits a .toast carrying a .t-close button, but nothing bound
       it: the button did nothing at all (SC 4.1.2 — a control with no function)
       and there was no timer, so a position:fixed panel sat over the corner of
       the page forever. role="alert" on markup already present at parse time is
       also not announced by most SR/browser pairs, which made a server-rendered
       error BOTH silent and permanent. Adopt them here: working close button,
       the same timings a runtime toast gets, and the live attribute re-applied a
       tick later so the region registers as changed rather than merely present. */
    function adoptToasts(root) {
        var found = 0;
        qa('.toast[data-ds-toast]', root).forEach(function (box) {
            if (box.__dsAdopted) { return; }
            box.__dsAdopted = true;
            found++;

            var tone = box.classList.contains('is-error') ? TONES.error
                     : box.classList.contains('is-warn')  ? TONES.warn
                     : box.classList.contains('is-ok')    ? TONES.ok
                     : TONES.info;
            var life  = readingTime(box.textContent || '', tone);
            var timer = null;
            var drop  = guard(function () {
                if (timer) { clearTimeout(timer); timer = null; }
                if (box.parentNode) { box.parentNode.removeChild(box); }
            });
            function start() { if (!timer && isFinite(life)) { timer = setTimeout(drop, life); } }
            function pause() { if (timer) { clearTimeout(timer); timer = null; } }

            var x = q('.t-close', box);
            if (x) { x.addEventListener('click', drop); }
            box.addEventListener('mouseenter', pause);
            box.addEventListener('focusin', pause);
            box.addEventListener('mouseleave', guard(start));
            box.addEventListener('focusout', guard(function () {
                if (!box.contains(DOC.activeElement)) { start(); }
            }));
            start();

            var live = box.getAttribute('aria-live');
            if (live) {
                box.removeAttribute('aria-live');
                setTimeout(function () { box.setAttribute('aria-live', live); }, 100);
            }
        });
        if (found) { toastStack(); }          // gives the stack its tabindex
    }

    /* -------------------------------------------------------------- announce --
       One persistent polite region, mounted by admin/partials/foot.php as
       #adminAnnouncer. A live region has to EXIST before its content changes, so
       a skeleton or an .error-state INSERTED into the page announces nothing on
       its own however correct its role is — whatever performs the swap says it
       through here instead. Keep aria-busy on the container being replaced. */
    function announce(text, assertive) {
        try {
            var box = DOC.getElementById('adminAnnouncer');
            if (!box) {
                box = el('span', 'sr-only');
                box.id = 'adminAnnouncer';
                box.setAttribute('role', 'status');
                box.setAttribute('data-ds-live', '');
                DOC.body.appendChild(box);
            }
            box.setAttribute('aria-live', assertive ? 'assertive' : 'polite');
            box.textContent = '';
            setTimeout(function () { box.textContent = text == null ? '' : String(text); }, 60);
        } catch (e) { /* announcing must never be the thing that breaks a page */ }
    }

    /* =========================================================================
       2. DRAWER  (side panel on desktop, bottom sheet under 640px — CSS does
          that part; this only owns state, focus and the scrim)
       -------------------------------------------------------------------------
          <button data-drawer-open="memberDrawer">Details</button>
          <aside class="ds-drawer" id="memberDrawer"> ...
              <button class="btn btn-ghost" data-drawer-close>Close</button>
          </aside>
       ========================================================================= */
    var openStack = [];                           // innermost last

    function drawerEl(ref) {
        if (!ref) { return null; }
        if (ref.nodeType === 1) { return ref.classList.contains('ds-drawer') ? ref : q('.ds-drawer', ref); }
        var s = String(ref).trim();
        if (!s) { return null; }
        var node = s.charAt(0) === '#' || /[.\s\[>]/.test(s) ? q(s) : DOC.getElementById(s);
        if (!node) { node = q('[data-drawer="' + s.replace(/"/g, '\\"') + '"]'); }
        if (!node) { return null; }
        return node.classList.contains('ds-drawer') ? node : q('.ds-drawer', node);
    }
    function scrimEl() {
        var s = q('.drawer-scrim');
        if (!s) {
            s = el('div', 'drawer-scrim');
            s.setAttribute('data-ds-scrim', '');
            DOC.body.appendChild(s);
        }
        return s;
    }
    /* A closed .ds-drawer is only translated off-screen — still in the tab
       order without this. Mark every one inert until it opens. */
    function sealDrawers(root) {
        qa('.ds-drawer', root).forEach(function (d) {
            if (d.classList.contains('is-open')) { return; }
            d.setAttribute('aria-hidden', 'true');
            try { d.inert = true; } catch (e) {}
        });
    }

    function openDrawer(ref, opener) {
        var d = drawerEl(ref);
        if (!d || d.classList.contains('is-open')) { return !!d; }

        var from  = opener && opener.nodeType === 1 ? opener : DOC.activeElement;
        var scrim = scrimEl();
        var stack = q('.toast-stack');

        if (!d.getAttribute('role')) { d.setAttribute('role', 'dialog'); }
        d.setAttribute('aria-modal', 'true');
        if (!d.hasAttribute('tabindex')) { d.setAttribute('tabindex', '-1'); }
        var titleEl = q('.ds-drawer-title', d);
        if (titleEl && !d.getAttribute('aria-label') && !d.getAttribute('aria-labelledby')) {
            if (!titleEl.id) { titleEl.id = 'ds-drawer-t-' + Math.random().toString(36).slice(2, 8); }
            d.setAttribute('aria-labelledby', titleEl.id);
        }

        d.removeAttribute('aria-hidden');
        try { d.inert = false; } catch (e) {}
        d.removeAttribute('inert');

        scrim.classList.add('is-open');
        d.classList.add('is-open');
        scrollLock.lock();

        var saved   = hideBackground([d, scrim, stack]);
        var release = trapFocus(d);

        var target = q('[data-drawer-autofocus]', d) || q('[autofocus]', d);
        if (!target) {
            var list = focusable(d);
            target = list.length ? list[0] : d;
        }
        setTimeout(function () { tryFocus(target); }, ms(20));

        openStack.push({ node: d, scrim: scrim, opener: from, release: release, saved: saved });
        emit(d, 'adminui:drawer-open', { drawer: d });
        return true;
    }

    function closeDrawer(ref) {
        var rec = null;
        if (ref == null) {
            rec = openStack[openStack.length - 1];
        } else {
            var want = drawerEl(ref);
            for (var i = openStack.length - 1; i >= 0; i--) {
                if (openStack[i].node === want) { rec = openStack[i]; break; }
            }
        }
        if (!rec) { return false; }
        openStack.splice(openStack.indexOf(rec), 1);

        var node = rec.node;
        rec.release();
        node.classList.remove('is-open');
        if (!openStack.length) { rec.scrim.classList.remove('is-open'); }
        restoreBackground(rec.saved);
        scrollLock.unlock();

        if (rec.opener && DOC.contains(rec.opener)) { tryFocus(rec.opener); }

        /* Re-seal only once it has slid away, so the transition still runs. */
        setTimeout(function () {
            if (node.classList.contains('is-open')) { return; }
            node.setAttribute('aria-hidden', 'true');
            try { node.inert = true; } catch (e) {}
        }, ms(DUR_3) + 40);

        emit(node, 'adminui:drawer-close', { drawer: node });
        return true;
    }

    /* =========================================================================
       4. CONFIRM  (defined before the bulk bar, which leans on it)
       -------------------------------------------------------------------------
       AdminUI.confirm('deleteMemberConfirm')  -> uses that <dialog class=ds-confirm>
       AdminUI.confirm('Delete 4 members?')    -> builds one
       AdminUI.confirm({ title, text, affected, ok, cancel, destructive })

       Native <dialog>.showModal() when available (it gives a real backdrop,
       real Escape handling and top-layer stacking); a focus-managed fallback
       with the drawer scrim otherwise.
       ========================================================================= */
    var adhocDialog = null;                       // one reusable node per page
    var confirmOpen = null;

    function buildAdhoc() {
        if (adhocDialog && DOC.contains(adhocDialog)) { return adhocDialog; }
        var d = DOC.createElement('dialog');
        d.className = 'ds-confirm';
        d.setAttribute('data-ds-adhoc', '');
        var body = el('div', 'ds-confirm-body');
        body.appendChild(el('h2', 'ds-confirm-title'));
        /* aria-describedby names the consequence AND the affected line. Without
           it a JS-built confirm announced only its title, so neither what the
           action does nor what it touches ever reached a screen reader — and
           focus lands on Cancel, so nothing invited the user to go and read it. */
        var txt = el('p', 'ds-confirm-text');
        txt.id = 'ds-confirm-x-' + Math.random().toString(36).slice(2, 8);
        body.appendChild(txt);
        var aff = el('p', 'ds-confirm-affected');
        aff.id = 'ds-confirm-a-' + Math.random().toString(36).slice(2, 8);
        aff.hidden = true;
        body.appendChild(aff);
        d.setAttribute('aria-describedby', txt.id + ' ' + aff.id);
        var foot = el('div', 'ds-confirm-foot');
        var no  = el('button', 'btn btn-secondary', 'Cancel');
        no.type = 'button'; no.setAttribute('data-confirm-no', '');
        var yes = el('button', 'btn btn-danger', 'Yes, proceed');
        yes.type = 'button'; yes.setAttribute('data-confirm-yes', '');
        foot.appendChild(no); foot.appendChild(yes);
        d.appendChild(body); d.appendChild(foot);
        DOC.body.appendChild(d);
        adhocDialog = d;
        return d;
    }
    function confirmButtons(d) {
        var yes = q('[data-confirm-yes]', d) || q('button[value="confirm"]', d) ||
                  q('.ds-confirm-foot .btn-danger', d) || q('.ds-confirm-foot .btn-primary', d);
        var no  = q('[data-confirm-no]', d) || q('button[value="cancel"]', d) ||
                  q('.ds-confirm-foot .btn-secondary', d) || q('.ds-confirm-foot .btn-ghost', d);
        if (!yes) {
            var btns = qa('.ds-confirm-foot button, .ds-confirm-foot .btn', d);
            yes = btns[btns.length - 1] || null;
            if (!no && btns.length > 1) { no = btns[0]; }
        }
        return { yes: yes, no: no };
    }
    /* `full` = we built this dialog, so every slot is ours to write. For a
       server-rendered .ds-confirm only the slots the caller actually supplied
       are touched — its copy, its button labels and its tone stay put. */
    function fillDialog(d, o, full) {
        var t = q('.ds-confirm-title', d), x = q('.ds-confirm-text', d), a = q('.ds-confirm-affected', d);
        if (t && (o.title || full)) {
            t.textContent = o.title || (o.destructive ? 'Delete this permanently?' : 'Please confirm');
        }
        if (x && (o.text || full)) {
            x.textContent = o.text || 'This action cannot be undone.';
        }
        if (a && (o.affected || full)) {
            if (o.affected) { a.textContent = o.affected; a.hidden = false; }
            else { a.textContent = ''; a.hidden = true; }
        }
        var b = confirmButtons(d);
        if (b.yes && (o.ok || full)) { b.yes.textContent = o.ok || (o.destructive ? 'Yes, delete' : 'Yes, proceed'); }
        if (b.no  && (o.cancel || full)) { b.no.textContent = o.cancel || 'Cancel'; }
        if (full || o.toneGiven) { d.classList.toggle('is-destructive', !!o.destructive); }

        /* STATUS IS NEVER COLOUR ALONE. .is-destructive does exactly one thing —
           it recolours the title to --st-err — so the destructive state needs the
           glyph and the word too. admin_confirm() renders them server-side; the
           declarative path DEFAULTS to destructive and usually has no
           pre-rendered dialog, which made red text the only signal in the common
           case. The cue goes in its own element, never inside the <h2>, because
           the line above may overwrite the heading's text. */
        var wantWarn = d.classList.contains('is-destructive');
        var warn     = q('.ds-confirm-warn', d);
        var bodyEl   = q('.ds-confirm-body', d);
        if (wantWarn && !warn && bodyEl) {
            warn = el('p', 'ds-confirm-warn');
            warn.innerHTML = svg(ICON.warn) +
                '<span class="sr-only">Warning: </span>' +
                '<span aria-hidden="true">Destructive action</span>';
            bodyEl.insertBefore(warn, t || bodyEl.firstChild);
        } else if (!wantWarn && warn && warn.parentNode) {
            warn.parentNode.removeChild(warn);
        }
        if (!d.getAttribute('aria-labelledby') && t) {
            if (!t.id) { t.id = 'ds-confirm-t-' + Math.random().toString(36).slice(2, 8); }
            d.setAttribute('aria-labelledby', t.id);
        }
    }

    function confirmDialog(arg) {
        var o = { destructive: true };
        var d = null;

        if (arg && arg.nodeType === 1) {
            d = arg.classList.contains('ds-confirm') ? arg : q('.ds-confirm', arg);
        } else if (typeof arg === 'string') {
            var byId = arg.charAt(0) === '#' ? q(arg) : DOC.getElementById(arg);
            if (byId) { d = byId.classList.contains('ds-confirm') ? byId : q('.ds-confirm', byId); }
            if (!d) { o.text = arg; }                       // a message, not an id
        } else if (arg && typeof arg === 'object') {
            for (var k in arg) { if (Object.prototype.hasOwnProperty.call(arg, k)) { o[k] = arg[k]; } }
            if (o.dialog) {
                var ref = o.dialog;
                d = ref.nodeType === 1 ? ref : (ref.charAt(0) === '#' ? q(ref) : DOC.getElementById(ref));
                if (d && !d.classList.contains('ds-confirm')) { d = q('.ds-confirm', d); }
            }
        }
        if (!d) { d = buildAdhoc(); fillDialog(d, o, true); }
        else if (d.hasAttribute('data-ds-adhoc')) { fillDialog(d, o, true); }
        else { fillDialog(d, o, false); }
        return d;
    }

    function askConfirm(arg) {
        return new Promise(function (resolve) {
            var d;
            try { d = confirmDialog(arg); } catch (e) { d = null; }
            if (!d) { resolve(window.confirm(typeof arg === 'string' ? arg : 'Are you sure?')); return; }
            if (confirmOpen) { resolve(false); return; }     // one at a time

            var opener   = DOC.activeElement;
            var native   = typeof d.showModal === 'function';
            var btns     = confirmButtons(d);
            var settled  = false;
            var release  = null, saved = null, prevStyle = null, onKey = null;

            function finish(value) {
                if (settled) { return; }
                settled = true;
                confirmOpen = null;
                d.removeEventListener('click', onClick, true);
                if (release) { release(); }
                if (native) {
                    d.removeEventListener('close', onClose);
                    if (d.open) { try { d.close(); } catch (e) {} }
                } else {
                    if (onKey) { DOC.removeEventListener('keydown', onKey, true); }
                    restoreBackground(saved);
                    if (!openStack.length) { var s = q('.drawer-scrim'); if (s) { s.classList.remove('is-open'); } }
                    d.removeAttribute('open');
                    d.style.cssText = prevStyle || '';
                }
                scrollLock.unlock();
                if (opener && DOC.contains(opener)) { tryFocus(opener); }
                emit(d, 'adminui:confirm', { confirmed: !!value });
                resolve(!!value);
            }
            function onClick(e) {
                var hit = e.target.closest ? e.target.closest('[data-confirm-yes],[data-confirm-no]') : null;
                if (hit && d.contains(hit)) {
                    e.preventDefault();
                    finish(hit.hasAttribute('data-confirm-yes'));
                    return;
                }
                if (btns.yes && btns.yes.contains(e.target)) { e.preventDefault(); finish(true); }
                else if (btns.no && btns.no.contains(e.target)) { e.preventDefault(); finish(false); }
            }
            function onClose() { finish(d.returnValue === 'confirm'); }

            confirmOpen = d;
            d.addEventListener('click', onClick, true);
            scrollLock.lock();

            if (native) {
                d.addEventListener('close', onClose);
                try { d.showModal(); }
                catch (e) { confirmOpen = null; scrollLock.unlock(); resolve(window.confirm('Are you sure?')); return; }
                /* The top layer traps focus for us, but the trap still has to be
                   registered so an enclosing drawer's trap stands down. */
                release = trapFocus(d);
            } else {
                /* No <dialog> support: place it ourselves, over the shared
                   scrim, and manage focus + Escape by hand. Geometry only —
                   every visual value still comes from admin-ds.css. */
                if (!d.getAttribute('role')) { d.setAttribute('role', 'dialog'); }
                d.setAttribute('aria-modal', 'true');
                prevStyle = d.getAttribute('style') || '';
                d.setAttribute('open', '');
                d.style.cssText = prevStyle + ';position:fixed;z-index:1201;left:50%;top:50%;' +
                    'transform:translate(-50%,-50%);display:block;margin:0;';
                scrimEl().classList.add('is-open');
                saved   = hideBackground([d, scrimEl(), q('.toast-stack')]);
                release = trapFocus(d);
                onKey = function (e) {
                    if (e.key === 'Escape' || e.keyCode === 27) { e.preventDefault(); e.stopPropagation(); finish(false); }
                };
                DOC.addEventListener('keydown', onKey, true);
            }

            /* Destructive default lands on Cancel — the safe button. */
            var first = (arg && arg.focus === 'ok') ? btns.yes
                      : (d.classList.contains('is-destructive') ? (btns.no || btns.yes) : (btns.yes || btns.no));
            setTimeout(function () { tryFocus(first || d); }, ms(20));
        });
    }

    /* ---------------------------------------- declarative [data-confirm] ----
       Capture phase + stopPropagation, so exactly one dialog appears even
       though admin.js also binds these elements. On confirmation the original
       gesture is replayed with a one-shot bypass so page handlers still run. */
    var BYPASS = 'dsConfirmPass';

    function confirmOptionsFrom(node, count) {
        function sub(s) {
            return String(s).replace(/\{count\}|\{n\}/g, count == null ? '' : String(count));
        }
        var raw  = node.getAttribute('data-confirm') || '';
        var tone = (node.getAttribute('data-confirm-tone') || '').toLowerCase();
        var text = raw && raw !== '1' && raw !== 'true' ? sub(raw) : '';
        var aff  = node.getAttribute('data-confirm-affected');
        if (aff == null && count != null && count > 0 && node.closest('.bulk-actions')) {
            aff = count + (count === 1 ? ' row selected' : ' rows selected');
        }
        return {
            dialog:      node.getAttribute('data-confirm-dialog') || null,
            title:       node.getAttribute('data-confirm-title') ? sub(node.getAttribute('data-confirm-title')) : '',
            text:        text,
            affected:    aff ? sub(aff) : '',
            ok:          node.getAttribute('data-confirm-ok') || '',
            cancel:      node.getAttribute('data-confirm-cancel') || '',
            toneGiven:   !!tone,
            destructive: tone !== 'neutral' && tone !== 'info'
        };
    }
    /* How many rows are selected in the bulk scope this control belongs to —
       feeds {count} in the confirm copy. */
    function selectionCountNear(node) {
        var n = node;
        while (n && n !== DOC.body) {
            if (n.__dsBulk) { return n.__dsBulk.selected().length; }
            if (n.__dsBulkScope && n.__dsBulkScope.__dsBulk) { return n.__dsBulkScope.__dsBulk.selected().length; }
            n = n.parentElement;
        }
        return null;
    }
    function replay(node, evt, submitter) {
        node.dataset[BYPASS] = '1';
        if (evt === 'submit' && node.tagName === 'FORM') {
            if (node.requestSubmit) { try { node.requestSubmit(submitter || undefined); return; } catch (e) {} }
            node.submit();                                  // no submit event — matches admin.js
            return;
        }
        /* A submit control re-fires its form's submit event; pass that through
           too so the user is not asked twice for the same gesture. */
        if (node.form && node.form.hasAttribute('data-confirm')) { node.form.dataset[BYPASS] = '1'; }
        if (node.click) { node.click(); return; }
        if (node.tagName === 'A' && node.href) { window.location.href = node.href; }
    }

    var onConfirmClick = guard(function (e) {
        if (e.defaultPrevented || (e.button != null && e.button !== 0)) { return; }
        var node = e.target && e.target.closest ? e.target.closest('[data-confirm]') : null;
        if (!node || node.tagName === 'FORM') { return; }
        if (node.dataset[BYPASS]) { delete node.dataset[BYPASS]; return; }
        if (node.disabled || node.getAttribute('aria-disabled') === 'true') { return; }
        if (node.tagName === 'A' && (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey)) { return; }

        e.preventDefault();
        e.stopPropagation();
        if (e.stopImmediatePropagation) { e.stopImmediatePropagation(); }

        askConfirm(confirmOptionsFrom(node, selectionCountNear(node))).then(function (ok) {
            if (ok) { replay(node, 'click'); }
        });
    });

    var onConfirmSubmit = guard(function (e) {
        var form = e.target;
        if (!form || form.tagName !== 'FORM' || !form.hasAttribute('data-confirm')) { return; }
        if (form.dataset[BYPASS]) { delete form.dataset[BYPASS]; return; }
        var submitter = e.submitter || null;

        e.preventDefault();
        e.stopPropagation();
        if (e.stopImmediatePropagation) { e.stopImmediatePropagation(); }

        askConfirm(confirmOptionsFrom(form, selectionCountNear(form))).then(function (ok) {
            if (ok) { replay(form, 'submit', submitter); }
        });
    });

    /* =========================================================================
       3. BULK SELECTION
       -------------------------------------------------------------------------
          <form data-bulk-scope data-bulk-name="ids[]" method="post">
            <table class="admin-table">
              <thead><tr><th data-col-lock><input type="checkbox" data-bulk-all></th> ...
              <tbody><tr><td><input type="checkbox" data-bulk-item value="42"></td> ...
            </table>
            <div class="bulk-bar" hidden>
              <span class="bulk-count"><span class="n">0</span> selected</span>
              <div class="bulk-actions"> <button class="btn btn-danger btn-sm"
                   data-confirm="Delete {count} selected rows?">Delete</button> </div>
            </div>
          </form>

       `data-bulk-scope` (not `data-bulk`) — email-contacts.php and
       email-mailbox.php already use [data-bulk] for their action buttons.
       ========================================================================= */
    function bulkScopes(root) {
        var out = [];
        qa('[data-bulk-scope]', root).forEach(function (s) { if (out.indexOf(s) < 0) { out.push(s); } });
        qa('[data-bulk-all]', root).forEach(function (all) {
            if (all.closest('[data-bulk-scope]')) { return; }
            var s = all.closest('form') || all.closest('.panel') || all.closest('.table-wrap') || all.closest('table');
            if (s && out.indexOf(s) < 0) { out.push(s); }
        });
        return out;
    }
    function isShown(node) {
        return !!(node.offsetWidth || node.offsetHeight || node.getClientRects().length);
    }

    function initBulk(scope) {
        if (scope.__dsBulk) { scope.__dsBulk.update(); return scope.__dsBulk; }

        var head = q('[data-bulk-all]', scope);
        var barSel = scope.getAttribute('data-bulk-bar');
        var bar = barSel ? q(barSel) : (q('.bulk-bar', scope) ||
                  (scope.closest('form') ? q('.bulk-bar', scope.closest('form')) : null) || q('.bulk-bar'));
        var name = scope.getAttribute('data-bulk-name') || 'ids[]';
        var form = scope.tagName === 'FORM' ? scope
                 : (q('[data-bulk-form]', scope) || scope.closest('form') || (bar ? bar.closest('form') : null));
        var countEl = bar ? (q('.bulk-count .n', bar) || q('[data-bulk-count]', bar)) : null;
        var lastIdx = -1;
        /* The announcer must live OUTSIDE the bar — the bar is [hidden] until
           something is ticked, and a live-region write inside a display:none
           subtree is not announced. admin_bulk_bar() renders it as a sibling;
           fall back to the scope for a hand-rolled bar. */
        var statusEl = (bar && bar.parentNode ? q('[data-bulk-status]', bar.parentNode) : null) ||
                       q('[data-bulk-status]', scope);

        if (bar) {
            bar.__dsBulkScope = scope;
            if (!bar.getAttribute('role')) { bar.setAttribute('role', 'region'); }
            if (!bar.getAttribute('aria-label')) { bar.setAttribute('aria-label', 'Bulk actions'); }
            var cnt = q('.bulk-count', bar);
            if (cnt && !cnt.getAttribute('aria-live')) { cnt.setAttribute('aria-live', 'polite'); }
            /* Only .n changes, so without aria-atomic the announcement is a bare
               number with no noun: "3", not "3 selected". */
            if (cnt && !cnt.getAttribute('aria-atomic')) { cnt.setAttribute('aria-atomic', 'true'); }
        }

        function items()    { return qa('[data-bulk-item]', scope).filter(function (i) { return !i.disabled; }); }
        function visible()  { return items().filter(isShown); }
        function checked()  { return items().filter(function (i) { return i.checked; }); }
        function idOf(i)    { return i.value || i.getAttribute('data-id') || ''; }

        function update() {
            var vis = visible(), sel = checked();
            /* Reveal BEFORE writing the count. The other order lost the one
               transition that matters: 0 → 1 wrote into a display:none subtree
               (silent) and un-hiding is not itself a text change, so the first
               selection was never announced while 1 → 2 and 2 → 3 were. */
            if (bar) { bar.hidden = sel.length === 0; }
            if (countEl) { countEl.textContent = String(sel.length); }
            if (statusEl) {
                statusEl.textContent = sel.length === 0
                    ? 'No rows selected'
                    : sel.length + ' of ' + vis.length + ' rows selected';
            }
            if (head) {
                var allOn = vis.length > 0 && vis.every(function (i) { return i.checked; });
                head.checked = allOn;
                /* Chromium, Gecko and WebKit all map .indeterminate to
                   aria-checked="mixed", so the mixed state IS conveyed. Do not
                   "improve" this with a manual aria-checked — it would conflict
                   with the native state. */
                head.indeterminate = sel.length > 0 && !allOn;
                if (!head.getAttribute('aria-label')) { head.setAttribute('aria-label', 'Select all rows'); }
            }
            items().forEach(function (i) {
                var row = i.closest('tr');
                if (row) { row.classList.toggle('is-selected', i.checked); }
                nameRow(i, row);
            });
            emit(scope, 'adminui:bulkchange', { count: sel.length, ids: sel.map(idOf) });
        }

        /* A row checkbox with no name announces as "checkbox, unchecked" with no
           indication of which record. The row is the only thing that knows its own
           subject, so derive the name from its first non-empty text cell. An
           explicit aria-label on the input always wins. */
        function nameRow(box, row) {
            if (box.getAttribute('aria-label') || (box.labels && box.labels.length)) { return; }
            var cell = null;
            if (row) {
                cell = qa('th,td', row).filter(function (c) {
                    return !c.contains(box) && c.textContent.replace(/\s+/g, ' ').trim() !== '';
                })[0] || null;
            }
            var txt = cell ? cell.textContent.replace(/\s+/g, ' ').trim().slice(0, 60) : '';
            box.setAttribute('aria-label', 'Select ' + (txt || 'row'));
        }

        function range(from, to, on) {
            var list = items(), a = Math.min(from, to), b = Math.max(from, to);
            for (var i = a; i <= b; i++) { if (list[i] && !list[i].disabled) { list[i].checked = on; } }
        }

        var api = {
            scope: scope,
            update: update,
            selected: function () { return checked().map(idOf); },
            clear: function () { items().forEach(function (i) { i.checked = false; }); lastIdx = -1; update(); },
            all: function (on) { visible().forEach(function (i) { i.checked = !!on; }); update(); }
        };
        scope.__dsBulk = api;

        /* A click forwarded from a wrapping <label> does not reliably carry the
           modifier flags, so remember Shift from the pointer/key that caused it. */
        var shiftHeld = false;
        scope.addEventListener('mousedown', guard(function (e) { shiftHeld = !!e.shiftKey; }), true);
        scope.addEventListener('keydown',   guard(function (e) { shiftHeld = !!e.shiftKey; }), true);

        scope.addEventListener('click', guard(function (e) {
            var box = e.target && e.target.closest ? e.target.closest('[data-bulk-item]') : null;
            if (!box || !scope.contains(box)) { return; }
            var list = items(), idx = list.indexOf(box);
            if ((e.shiftKey || shiftHeld) && lastIdx > -1 && idx > -1 && lastIdx !== idx) {
                range(lastIdx, idx, box.checked);
            }
            shiftHeld = false;
            if (idx > -1) { lastIdx = idx; }
            update();
        }));

        scope.addEventListener('change', guard(function (e) {
            var t = e.target;
            if (!t) { return; }
            if (head && t === head) { api.all(head.checked); lastIdx = -1; return; }
            if (t.closest && t.closest('[data-bulk-item]')) { update(); }
        }));

        /* Ids reach the server: nothing to do when the boxes already sit in the
           posting form with a name; otherwise mirror them into hidden inputs. */
        if (form) {
            form.addEventListener('submit', guard(function () {
                var old = q('[data-bulk-hidden]', form);
                if (old) { old.parentNode.removeChild(old); }
                var sel = checked();
                var needs = sel.filter(function (i) { return !i.name || !form.contains(i); });
                if (!needs.length) { return; }
                var wrap = el('div');
                wrap.setAttribute('data-bulk-hidden', '');
                wrap.hidden = true;
                needs.forEach(function (i) {
                    var h = DOC.createElement('input');
                    h.type = 'hidden'; h.name = name; h.value = idOf(i);
                    wrap.appendChild(h);
                });
                form.appendChild(wrap);
            }));
        }

        update();
        return api;
    }

    /* =========================================================================
       5. TABLE COLUMN VISIBILITY
       -------------------------------------------------------------------------
          <div data-cols-for="#membersTable" data-cols-key="members"></div>

       The control is generated here (button + checkbox panel) because
       admin-ds.css declares no class for it. Columns whose <th> carries
       data-col-lock — or which hold the bulk select-all box — cannot be
       hidden. The choice is stored per table in localStorage.
       ========================================================================= */
    var COLS_PREFIX = 'pwf-admin-cols:';

    function colsKey(host, table) {
        var k = host.getAttribute('data-cols-key') || table.id ||
                (location.pathname.replace(/[^a-z0-9]+/gi, '-') + '-t');
        return COLS_PREFIX + k;
    }
    function colHeaders(table) {
        var row = q('thead tr', table) || (table.rows && table.rows[0]);
        return row ? Array.prototype.slice.call(row.cells) : [];
    }
    function colLocked(th) {
        return th.hasAttribute('data-col-lock') || !!q('[data-bulk-all]', th);
    }
    function colLabel(th, i) {
        var t = (th.getAttribute('data-col-label') || th.textContent || '').replace(/\s+/g, ' ').trim();
        if (!t) { return 'Column ' + (i + 1); }
        return t.length > 42 ? t.slice(0, 41) + '…' : t;
    }
    function applyCols(table, hidden) {
        var n = colHeaders(table).length;
        if (!n) { return; }
        Array.prototype.forEach.call(table.rows, function (row) {
            if (row.cells.length !== n) { return; }         // colspan row (empty state) — leave alone
            Array.prototype.forEach.call(row.cells, function (cell, i) {
                cell.style.display = hidden.indexOf(i) > -1 ? 'none' : '';
            });
        });
    }

    function initCols(host) {
        if (host.__dsCols) { host.__dsCols.refresh(); return host.__dsCols; }
        var table = q(host.getAttribute('data-cols-for') || '');
        if (!table || table.tagName !== 'TABLE') { return null; }

        var key    = colsKey(host, table);
        var hidden = [];
        try { hidden = JSON.parse(store(key) || '[]'); } catch (e) { hidden = []; }
        if (!Array.isArray(hidden)) { hidden = []; }

        host.innerHTML = '';
        host.style.cssText = 'position:relative;display:inline-block;';

        var btn = el('button', 'btn btn-secondary btn-sm');
        btn.type = 'button';
        btn.setAttribute('aria-expanded', 'false');
        btn.setAttribute('aria-haspopup', 'true');
        /* The label is built as a TEXT NODE, never concatenated into innerHTML.
           getAttribute() returns the decoded value, so a page that correctly
           writes data-cols-label="<?= e($x) ?>" would have had its escaping
           undone here and the markup re-parsed as live HTML. */
        btn.innerHTML = svg(ICON.cols, 16);
        btn.appendChild(DOC.createTextNode(' '));
        btn.appendChild(el('span', null, host.getAttribute('data-cols-label') || 'Columns'));

        var menu = el('div');
        menu.hidden = true;
        menu.setAttribute('role', 'group');
        menu.setAttribute('aria-label', 'Show or hide columns');
        menu.style.cssText =
            'position:absolute;left:0;right:auto;top:calc(100% + var(--sp-2));z-index:30;' +
            'min-width:220px;max-width:calc(100vw - var(--sp-8));max-height:60vh;overflow:auto;' +
            'display:grid;gap:var(--sp-1);padding:var(--sp-3);' +
            'background:var(--surface);border:1px solid var(--border);border-radius:var(--r-md);' +
            'box-shadow:var(--elev-3);';

        function build() {
            menu.innerHTML = '';
            colHeaders(table).forEach(function (th, i) {
                if (colLocked(th)) { return; }
                var label = el('label', 'checkbox');
                label.style.cssText = 'display:flex;align-items:center;gap:var(--sp-2);min-height:44px;' +
                                      'padding:0 var(--sp-1);cursor:pointer;';
                var cb = DOC.createElement('input');
                cb.type = 'checkbox';
                cb.checked = hidden.indexOf(i) < 0;
                cb.setAttribute('data-col-index', String(i));
                label.appendChild(cb);
                label.appendChild(el('span', null, colLabel(th, i)));
                menu.appendChild(label);
            });
            var reset = el('button', 'btn btn-ghost btn-sm', 'Show all columns');
            reset.type = 'button';
            reset.setAttribute('data-cols-reset', '');
            reset.style.marginTop = 'var(--sp-2)';
            menu.appendChild(reset);
        }
        function save() { store(key, JSON.stringify(hidden)); }
        function paint() { applyCols(table, hidden); }

        function close(focusBack) {
            if (menu.hidden) { return; }
            menu.hidden = true;
            btn.setAttribute('aria-expanded', 'false');
            host.removeAttribute('data-ds-popup-open');
            if (focusBack) { tryFocus(btn); }
        }
        function open() {
            menu.hidden = false;
            btn.setAttribute('aria-expanded', 'true');
            /* Marks this subtree as owning Escape, so the document-level handler
               that closes drawers stands down while the menu is up. */
            host.setAttribute('data-ds-popup-open', '');
            /* Anchor left, flip right only if that would leave the viewport —
               the control can sit at either end of a toolbar. */
            menu.style.left = '0'; menu.style.right = 'auto';
            var r = menu.getBoundingClientRect();
            if (r.right > window.innerWidth - 8 && r.width < window.innerWidth - 16) {
                menu.style.left = 'auto'; menu.style.right = '0';
                if (menu.getBoundingClientRect().left < 8) { menu.style.left = '0'; menu.style.right = 'auto'; }
            }
            var first = focusable(menu)[0];
            if (first) { tryFocus(first); }
        }

        btn.addEventListener('click', guard(function (e) {
            e.preventDefault();
            if (menu.hidden) { open(); } else { close(true); }
        }));
        menu.addEventListener('change', guard(function (e) {
            var cb = e.target;
            if (!cb || cb.type !== 'checkbox' || !cb.hasAttribute('data-col-index')) { return; }
            var i = parseInt(cb.getAttribute('data-col-index'), 10);
            var at = hidden.indexOf(i);
            if (cb.checked && at > -1) { hidden.splice(at, 1); }
            else if (!cb.checked && at < 0) { hidden.push(i); }
            save(); paint();
        }));
        menu.addEventListener('click', guard(function (e) {
            if (!e.target.closest || !e.target.closest('[data-cols-reset]')) { return; }
            hidden = [];
            save(); build(); paint();
        }));
        menu.addEventListener('keydown', guard(function (e) {
            if (e.key === 'Escape' || e.keyCode === 27) { e.stopPropagation(); close(true); }
        }));
        DOC.addEventListener('click', guard(function (e) {
            if (!menu.hidden && !host.contains(e.target)) { close(false); }
        }));

        host.appendChild(btn);
        host.appendChild(menu);
        build(); paint();

        host.__dsCols = {
            host: host, table: table,
            refresh: function () { build(); paint(); },
            reset: function () { hidden = []; save(); build(); paint(); },
            hidden: function () { return hidden.slice(); }
        };
        return host.__dsCols;
    }

    /* =========================================================================
       DELEGATED WIRING + INIT
       ========================================================================= */
    function initDeclarative(root) {
        /* Markup that ships with .is-open means "open on load" — adopt it, so
           it gets the scrim, the trap and the scroll lock like any other. */
        qa('.ds-drawer.is-open', root).forEach(function (d) {
            if (openStack.some(function (r) { return r.node === d; })) { return; }
            d.classList.remove('is-open');
            openDrawer(d, null);
        });
        sealDrawers(root);
        try { adoptToasts(root); } catch (e) {}
        bulkScopes(root).forEach(function (s) { try { initBulk(s); } catch (e) {} });
        qa('[data-cols-for]', root).forEach(function (h) { try { initCols(h); } catch (e) {} });
    }

    var onDocClick = guard(function (e) {
        var t = e.target;
        if (!t || !t.closest) { return; }

        /* Clearing a selection needs its own control. Unticking the header box
           does NOT do it: with a partial selection that box is `indeterminate`,
           and activating an indeterminate checkbox sets checked=true in every
           engine — so the "clear" gesture selected everything instead. */
        var clr = t.closest('[data-bulk-clear]');
        if (clr) {
            var sc = clr.closest('[data-bulk-scope]') ||
                     (clr.closest('.bulk-bar') || {}).__dsBulkScope || null;
            if (sc && sc.__dsBulk) { e.preventDefault(); sc.__dsBulk.clear(); }
            return;
        }

        /* preventDefault only on success, so a [data-drawer-open] that is also a
           real link still navigates when its drawer is not on the page. */
        var opener = t.closest('[data-drawer-open]');
        if (opener) {
            if (openDrawer(opener.getAttribute('data-drawer-open'), opener)) { e.preventDefault(); }
            return;
        }
        var closer = t.closest('[data-drawer-close]');
        if (closer) {
            var ref = closer.getAttribute('data-drawer-close');
            if (closeDrawer(ref ? ref : closer.closest('.ds-drawer'))) { e.preventDefault(); }
            return;
        }
        if (openStack.length && t.classList && t.classList.contains('drawer-scrim')) {
            closeDrawer(null);
        }
    });

    var onDocKeydown = guard(function (e) {
        if (e.key !== 'Escape' && e.keyCode !== 27) { return; }
        if (confirmOpen) { return; }        // the dialog on top owns Escape
        if (!openStack.length) { return; }
        /* The TOPMOST surface owns Escape. This listener is in capture and
           stopPropagation()s, so a column chooser open inside a drawer never got
           its own Escape (bubble, on the menu) and the first press closed the
           whole drawer instead of the menu. Stand down while a popup is open
           under the pointer/caret and let it close itself. */
        if (e.target && e.target.closest && e.target.closest('[data-ds-popup-open]')) { return; }
        /* Consume it, so the sidebar/popover Escape handlers in admin.js do not
           also fire while a modal surface is on screen. */
        e.stopPropagation();
        closeDrawer(null);
    });

    DOC.addEventListener('click', onConfirmClick, true);     // capture: beats admin.js
    DOC.addEventListener('submit', onConfirmSubmit, true);
    DOC.addEventListener('click', onDocClick, false);
    DOC.addEventListener('keydown', onDocKeydown, true);

    function boot() { try { initDeclarative(DOC); } catch (e) {} }
    if (DOC.readyState === 'loading') { DOC.addEventListener('DOMContentLoaded', boot); } else { boot(); }

    /* ------------------------------------------------------- public surface */
    window.AdminUI = {
        version: '1.0.0',

        /* toasts */
        toast: toast,
        clearToasts: clearToasts,

        /* announce(text[, assertive]) — write into the shell's persistent live
           region. Use it whenever markup is SWAPPED into the page: a skeleton or
           an .error-state that is inserted carries the right role but a live
           region must exist before its content changes, so neither "loading" nor
           "this panel failed" is spoken on its own. */
        announce: announce,

        /* drawer */
        openDrawer: openDrawer,
        closeDrawer: closeDrawer,

        /* confirm — Promise<boolean> */
        confirm: askConfirm,

        /* bulk selection */
        bulk: {
            refresh: function (root) { bulkScopes(root || DOC).forEach(function (s) { try { initBulk(s); } catch (e) {} }); },
            selected: function (ref) {
                var s = !ref ? bulkScopes(DOC)[0] : (ref.nodeType === 1 ? ref : q(ref));
                return s && s.__dsBulk ? s.__dsBulk.selected() : [];
            },
            clear: function (ref) {
                var s = !ref ? bulkScopes(DOC)[0] : (ref.nodeType === 1 ? ref : q(ref));
                if (s && s.__dsBulk) { s.__dsBulk.clear(); }
            }
        },

        /* column visibility */
        columns: {
            refresh: function (ref) {
                var hosts = !ref ? qa('[data-cols-for]') : [ref.nodeType === 1 ? ref : q(ref)];
                hosts.forEach(function (h) { if (h) { try { initCols(h); } catch (e) {} } });
            },
            reset: function (ref) {
                var h = !ref ? qa('[data-cols-for]')[0] : (ref.nodeType === 1 ? ref : q(ref));
                if (h && h.__dsCols) { h.__dsCols.reset(); }
            }
        },

        /* re-scan after injecting markup (AJAX table reload, drawer body swap) */
        init: function (root) { try { initDeclarative(root || DOC); } catch (e) {} },

        /* ---------------------------------------------------------------------
           MODAL PRIMITIVES — exported so the shell's own off-canvas sidebar can
           be a real modal instead of a second, less accessible implementation of
           one. body.sidebar-open renders a scrim that blocks the pointer, but
           without these the panel is modal in appearance only: focus is not
           trapped, .admin-main is not inert, and Tab from the last rail item
           walks into a topbar sitting behind the scrim.

             var undo  = AdminUI._hideBackground([sidebar]);
             var untrap = AdminUI._trapFocus(sidebar);
             …
             untrap(); AdminUI._restoreBackground(undo);

           _trapFocus returns a release function and participates in the same
           nesting stack the drawer and confirm dialog use, so only the topmost
           surface enforces containment. _focusable is the tree-order list of
           reachable stops inside a container.
           ------------------------------------------------------------------- */
        _trapFocus: trapFocus,
        _hideBackground: hideBackground,
        _restoreBackground: restoreBackground,
        _focusable: focusable
    };
})();
