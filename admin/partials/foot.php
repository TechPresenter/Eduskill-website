<?php
/**
 * Admin layout foot — closes content/main/layout and loads scripts.
 * Set $load_charts = true before including to load Chart.js on that page.
 * Set $load_analytics = true for the visitor-analytics dashboard bundle.
 */
?>
        </main><!-- /.admin-content -->
    </div><!-- /.admin-main -->
</div><!-- /.admin-layout -->

<?php /* Global overlay layer for the design system's transient components.
         admin-ui.js's toastStack() reuses an existing stack rather than creating
         one, so this mount must carry the same contract it would have built
         itself — in particular `data-ds-live`, without which the stack is
         inert-ed along with the rest of the page whenever a drawer or confirm
         dialog is open.

         NO aria-live HERE. Every toast is itself a live region (role=alert or
         role=status with a matching aria-live), and an assertive error appended
         into a polite ancestor region is undefined: in practice either a double
         announcement, or the container's politeness wins and every error is
         silently downgraded. The toast carries the tone; the container carries the
         landmark. Same edit in admin_toast_stack().

         And only ONE stack per document: a page with flashes has already printed
         one higher up (head.php → admin_toast_stack()), and two meant runtime
         toasts landed in the first while this one stayed empty forever, with two
         identically-labelled "Notifications" landmarks. */ ?>
<?php if (!function_exists('admin_toast_stack_rendered') || !admin_toast_stack_rendered()): ?>
<div class="toast-stack" id="toastStack" role="region" aria-label="Notifications" data-ds-live></div>
<?php endif; ?>

<?php /* The shell's one persistent live region. A live region must EXIST before
         its content changes, so a skeleton or an .error-state that is INSERTED
         into the page announces nothing however correct its role is — "loading"
         and "this panel failed" were both silent. Whatever performs the swap
         calls AdminUI.announce(text) and this is where it lands. */ ?>
<span class="sr-only" id="adminAnnouncer" role="status" aria-live="polite" data-ds-live></span>

<?php if (!empty($load_charts)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="<?= e(asset('js/main.js')) ?>"></script>
<script src="<?= e(asset('js/admin.js')) ?>"></script>
<?php /* The DS component runtime (toasts, drawers, confirm dialogs, bulk bar,
         column chooser). A classic script HERE, not `defer` in <head>: it must run
         after admin.js — whose [data-confirm] handler it deliberately pre-empts in
         the capture phase — and window.AdminUI must be defined before the shell
         wiring below, which calls AdminUI._trapFocus / _hideBackground to make the
         off-canvas rail a real modal. Guarded so the panel does not 404 on every
         page before it ships. */ ?>
<?php if (is_file(BASE_PATH . '/assets/js/admin-ui.js')): ?>
<script src="<?= e(asset('js/admin-ui.js')) ?>"></script>
<?php endif; ?>
<!-- Lucide icons (admin UI) -->
<script src="https://cdn.jsdelivr.net/npm/lucide@0.469.0/dist/umd/lucide.min.js"></script>
<script>
    (function () {
        function draw() { try { window.lucide && window.lucide.createIcons(); } catch (e) {} }
        draw(); document.addEventListener('DOMContentLoaded', draw); window.PWFdrawIcons = draw;
    })();
</script>
<script>
/* =============================================================================
   APPLICATION SHELL WIRING
   -----------------------------------------------------------------------------
   admin.js owns the sidebar's state machine (collapse + persistence, off-canvas,
   accordion) and the topbar's popovers, search and clock. This block adds only
   what the shell's markup needs and admin.js does not do:

     1. the in-drawer close control (the topbar hamburger is under the scrim once
        the drawer is open, so it cannot close it),
     2. state kept truthful on the controls that claim to own the rail —
        admin.js flips body classes only,
     3. the off-canvas rail made a REAL modal: focus trapped, background inert,
        Escape handled, focus restored to the opener,
     4. the keyboard hint rendered for the platform (⌘K on macOS, Ctrl K
        elsewhere); the binding itself already accepts both,
     5. the group holding the current page re-opened, because the accordion state
        is persisted per group and would otherwise hide the active item,
     6. a keyboard-visible name for every row of the collapsed icon rail,
     7. the collapsed rail's group heads taken out of the tab order,
     8. an icon fallback for when the Lucide CDN never answers,
     9. two keydown shims that stop admin.js hijacking keystrokes it should not.

   ORDERING IS LOAD-BEARING FOR 3 AND 9. This block runs during parse; admin.js
   binds its own document listeners on DOMContentLoaded. Same node, same phase,
   registration order — so the listeners below run FIRST and can decline to pass
   an event on. That is the only way to correct a document-level binding in a file
   this change does not own.
   ============================================================================= */
(function () {
    'use strict';
    var body = document.body;
    if (!body || body.className.indexOf('admin') === -1) { return; }

    var sidebar    = document.getElementById('adminSidebar');
    var overlay    = document.getElementById('adminOverlay');
    var toggle     = document.getElementById('sidebarToggle');
    var collapse   = document.querySelector('[data-sidebar-collapse]');
    var kbdHint    = document.getElementById('tbSearchKbd');
    var isMobile   = function () { return window.innerWidth <= 992; };
    var ui         = function () { return window.AdminUI || null; };

    /* 4. Platform-correct shortcut hint. */
    if (kbdHint && /Mac|iPhone|iPad|iPod/i.test(navigator.platform || navigator.userAgent || '')) {
        kbdHint.textContent = '⌘ K';
    }

    /* ---------------------------------------------------------------------
       3. THE OFF-CANVAS RAIL IS A MODAL, OR IT IS A LIE.
       body.sidebar-open renders .admin-overlay: position:fixed, inset:0,
       z-index:190, a 50% scrim. Pointer users are blocked from the background.
       Keyboard and screen-reader users were not — #adminSidebar was a plain
       <aside>, focus was not trapped, .admin-main was not inert, and Tab from the
       last rail item walked straight into a topbar sitting behind the scrim.
       The panel had two drawer patterns and only .ds-drawer's was accessible, so
       this reuses that machinery rather than writing a second copy of it.
       ------------------------------------------------------------------- */
    var modal = null;
    function modalOn() {
        if (modal || !sidebar || !isMobile()) { return; }
        sidebar.setAttribute('role', 'dialog');
        sidebar.setAttribute('aria-modal', 'true');
        var API = ui();
        if (API && API._hideBackground && API._trapFocus) {
            /* The overlay stays out of the inert set: it carries
               [data-sidebar-close], and `inert` would kill that click. */
            modal = { saved: API._hideBackground([sidebar, overlay]), untrap: API._trapFocus(sidebar) };
        } else {
            var main = document.querySelector('.admin-main');
            if (main) {
                try { main.inert = true; } catch (e) {}
                main.setAttribute('aria-hidden', 'true');
            }
            modal = { main: main };
        }
    }
    function modalOff() {
        if (!modal) { return; }
        var rec = modal;
        modal = null;                       // before any focus move, see closeDrawer
        if (sidebar) {
            sidebar.removeAttribute('role');
            sidebar.removeAttribute('aria-modal');
        }
        if (rec.untrap) { rec.untrap(); }
        var API = ui();
        if (rec.saved && API && API._restoreBackground) { API._restoreBackground(rec.saved); }
        if (rec.main) {
            try { rec.main.inert = false; } catch (e) {}
            rec.main.removeAttribute('inert');
            rec.main.removeAttribute('aria-hidden');
        }
    }

    /* 2. Reflect the rail's state on the controls that claim to own it. */
    function setLabel(node, text) {
        node.setAttribute('aria-label', text);
        node.setAttribute('title', text);
    }
    function sync() {
        var mobile    = isMobile();
        var collapsed = body.classList.contains('sidebar-collapsed') && !mobile;
        var open      = body.classList.contains('sidebar-open');

        if (toggle) {
            if (mobile) {
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                toggle.removeAttribute('aria-pressed');
                setLabel(toggle, open ? 'Close navigation' : 'Open navigation');
            } else {
                /* Above 992px this button toggles the ICON RAIL: the sidebar and
                   every link in it stay present and reachable. aria-expanded
                   there told a screen-reader user the navigation had been
                   collapsed away, which is false. A two-state toggle is
                   aria-pressed, not a disclosure. */
                toggle.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
                toggle.removeAttribute('aria-expanded');
                setLabel(toggle, collapsed ? 'Expand sidebar from icons' : 'Collapse sidebar to icons');
            }
        }
        if (collapse) {
            collapse.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
            collapse.removeAttribute('aria-expanded');
            setLabel(collapse, collapsed ? 'Expand sidebar from icons' : 'Collapse sidebar to icons');
            var want = collapsed ? 'chevrons-right' : 'chevrons-left';
            if (collapse.getAttribute('data-icon') !== want) {
                collapse.setAttribute('data-icon', want);
                collapse.innerHTML = '<i data-lucide="' + want + '"></i>';
                if (window.PWFdrawIcons) { window.PWFdrawIcons(); }
            }
        }

        syncGroupHeads(collapsed);
        if (!(open && mobile)) { modalOff(); }
        else { modalOn(); }
        if (!collapsed) { hideFlyout(); }
    }

    /* ---------------------------------------------------------------------
       7. Collapsed, a group head is inert decoration: admin.css sets
       pointer-events:none on it, hides its label and chevron, drops its divider,
       and admin.js returns early on click. That left 18 keyboard-reachable
       buttons that announce a group name and do nothing, ahead of every link —
       and aria-expanded lied, reporting the persisted accordion state while the
       CSS force-opened every body. Take them out of the tree while collapsed and
       give them back an honest state when the rail expands.
       ------------------------------------------------------------------- */
    function syncGroupHeads(collapsed) {
        if (!sidebar) { return; }
        var heads = sidebar.querySelectorAll('[data-group-toggle]');
        Array.prototype.forEach.call(heads, function (h) {
            var grp = h.parentElement;
            if (collapsed) {
                h.tabIndex = -1;
                h.setAttribute('aria-hidden', 'true');
                h.removeAttribute('aria-expanded');
                h.removeAttribute('aria-controls');
                return;
            }
            h.tabIndex = 0;
            h.removeAttribute('aria-hidden');
            h.setAttribute('aria-expanded', grp && grp.classList.contains('is-open') ? 'true' : 'false');
            if (!h.getAttribute('aria-controls')) {
                var b = grp ? grp.querySelector('.nav-group-body') : null;
                if (b && b.id) { h.setAttribute('aria-controls', b.id); }
            }
        });
    }

    /* ---------------------------------------------------------------------
       6. A keyboard-visible name for the collapsed rail. `title` fires on hover
       only, never on focus, so a sighted keyboard user tabbed 100+ unnamed
       icons. The styled flyout in admin.css is unreachable — clipped by
       .sidebar-nav{overflow-x:hidden} and .sidebar-link{overflow:hidden} — so
       this one is appended to <body> and positioned from the focused row.
       ------------------------------------------------------------------- */
    var flyout = null;
    function hideFlyout() { if (flyout) { flyout.style.display = 'none'; } }
    function showFlyout(link) {
        if (isMobile() || !body.classList.contains('sidebar-collapsed')) { return; }
        var lbl = link.querySelector('.lbl');
        var text = lbl ? lbl.textContent.replace(/\s+/g, ' ').trim() : (link.getAttribute('title') || '');
        if (!text) { return; }
        if (!flyout) {
            flyout = document.createElement('div');
            flyout.className = 'sb-flyout';
            flyout.setAttribute('aria-hidden', 'true');   // the link is already named
            document.body.appendChild(flyout);
        }
        flyout.textContent = text;
        flyout.style.display = 'block';
        var r = link.getBoundingClientRect();
        flyout.style.left = Math.round(r.right + 10) + 'px';
        flyout.style.top  = Math.round(r.top + (r.height - flyout.offsetHeight) / 2) + 'px';
    }
    if (sidebar) {
        sidebar.addEventListener('focusin', function (e) {
            var a = e.target && e.target.closest ? e.target.closest('.sidebar-link') : null;
            if (a) { showFlyout(a); } else { hideFlyout(); }
        });
        sidebar.addEventListener('focusout', hideFlyout);
        sidebar.addEventListener('mouseover', function (e) {
            var a = e.target && e.target.closest ? e.target.closest('.sidebar-link') : null;
            if (a) { showFlyout(a); }
        });
        sidebar.addEventListener('mouseleave', hideFlyout);
        var navScroller = document.getElementById('adminSidebarNav');
        if (navScroller) { navScroller.addEventListener('scroll', hideFlyout); }
        window.addEventListener('scroll', hideFlyout, true);
    }

    /* 1 + 3. Close the drawer from inside it, and keep focus where it belongs. */
    var lastTrigger = null;
    function closeDrawer() {
        if (!body.classList.contains('sidebar-open')) { return; }
        body.classList.remove('sidebar-open');
        /* Release the trap BEFORE moving focus: the trap's focusin handler would
           otherwise pull the focus straight back into the rail. */
        modalOff();
        sync();
        var back = lastTrigger || toggle;
        if (back && typeof back.focus === 'function') { back.focus(); }
        lastTrigger = null;
    }
    document.querySelectorAll('[data-sidebar-close]').forEach(function (el) {
        el.addEventListener('click', closeDrawer);
    });
    if (toggle) {
        toggle.addEventListener('click', function () {
            lastTrigger = toggle;
            /* admin.js flips the class on the same click; read it next tick. */
            window.setTimeout(function () {
                sync();
                if (body.classList.contains('sidebar-open') && sidebar) {
                    var API = ui();
                    var list = API && API._focusable ? API._focusable(sidebar) : [];
                    var first = list.length ? list[0] : sidebar.querySelector('a, button');
                    if (first) { first.focus(); }
                }
            }, 0);
        });
    }
    if (collapse) { collapse.addEventListener('click', function () { window.setTimeout(sync, 0); }); }

    /* Escape must go through closeDrawer(), which restores focus. admin.js binds
       its own document Escape that strips `sidebar-open` directly, leaving focus
       on a link now at translateX(-100%) — the ring off-screen and the next Tab
       resuming from nowhere. Registered here first, so that one never runs while
       the drawer is open. */
    document.addEventListener('keydown', function (e) {
        if (!e || (e.key !== 'Escape' && e.keyCode !== 27)) { return; }
        if (!body.classList.contains('sidebar-open')) { return; }
        e.stopImmediatePropagation();
        closeDrawer();
    }, false);

    /* Tapping a link closes the drawer; route it through closeDrawer() so focus
       is handled the day one of these links becomes a JS action rather than a
       navigation. Capture, so it precedes admin.js's per-link listener. */
    if (sidebar) {
        sidebar.addEventListener('click', function (e) {
            var a = e.target && e.target.closest ? e.target.closest('.sidebar-link') : null;
            if (a && isMobile() && body.classList.contains('sidebar-open')) { closeDrawer(); }
        }, true);
    }

    /* ---------------------------------------------------------------------
       9. KEYSTROKE SHIMS for admin.js's global-search binding.

       (a) Ctrl/⌘+K. admin.js applies its `typing` guard to `/` only, so Ctrl+K
       inside an <input>, a <textarea> or a CKEditor editable is hijacked with
       preventDefault() — and Ctrl/⌘+K is CKEditor 5's INSERT-LINK shortcut, with
       initWysiwyg() live on every content-authoring screen. An editor could not
       create a link from the keyboard and their caret was yanked to the topbar
       mid-sentence. This does not preventDefault: the event has already reached
       the field, so CKEditor's own handler has run and its balloon stays open —
       it only declines to pass the event to the listener that would steal focus.
       (b) `/` in a <select>, which admin.js's guard also misses.
       The canonical one-line fix belongs in admin.js's own handler; see the notes
       shipped with this change.
       ------------------------------------------------------------------- */
    document.addEventListener('keydown', function (e) {
        if (!e || !e.key) { return; }
        var k     = e.key.toLowerCase();
        var combo = (e.ctrlKey || e.metaKey) && k === 'k';
        var slash = e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey;
        if (!combo && !slash) { return; }
        var t = e.target;
        if (!t || t.id === 'tbSearchInput') { return; }
        var tag = (t.tagName || '').toLowerCase();
        var typing = tag === 'input' || tag === 'textarea' || tag === 'select' ||
                     t.isContentEditable ||
                     !!(t.closest && t.closest('.ck-editor, .ck-content, [role="textbox"]'));
        if (typing) { e.stopImmediatePropagation(); }
    }, false);

    /* 5. Never hide the page you are on behind a folded section. */
    function revealCurrent() {
        var grp = sidebar && sidebar.querySelector('[data-nav-current]');
        if (!grp || grp.classList.contains('is-open')) { return; }
        grp.classList.add('is-open');
        var head = grp.querySelector('[data-group-toggle]');
        if (head && head.tabIndex !== -1) { head.setAttribute('aria-expanded', 'true'); }
    }
    document.addEventListener('DOMContentLoaded', revealCurrent);
    revealCurrent();

    /* ---------------------------------------------------------------------
       8. If Lucide never answers, the collapsed rail is a column of empty 76px
       boxes: lucide() emits <i data-lucide> which stays empty until the CDN
       script in this file succeeds, and collapsed the label is clipped. The
       first letter comes from sidebar.php as data-initial on .ico. CSS cannot do
       this — .ico is never :empty (it holds the <i>) and attr() only reads its
       own element's attributes.
       ------------------------------------------------------------------- */
    function iconFallback() {
        if (window.lucide || !sidebar) { return; }
        var pending = sidebar.querySelectorAll('.sidebar-link .ico > i[data-lucide]');
        Array.prototype.forEach.call(pending, function (i) {
            if (i.textContent) { return; }
            var initial = i.parentElement ? i.parentElement.getAttribute('data-initial') : '';
            if (initial) { i.textContent = initial; }
        });
    }
    window.setTimeout(iconFallback, 2500);
    window.addEventListener('load', function () { window.setTimeout(iconFallback, 500); });

    /* Anything else that flips the shell's body classes (admin.js restoring the
       persisted state, a future component) keeps the controls honest too. */
    if (window.MutationObserver) {
        new MutationObserver(sync).observe(body, { attributes: true, attributeFilter: ['class'] });
    }
    window.addEventListener('resize', sync);
    document.addEventListener('DOMContentLoaded', sync);
    sync();
})();
</script>
<?php if (!empty($load_analytics)): ?>
<!-- Analytics dashboard: export libs (jsPDF + SheetJS) then the dashboard app -->
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.2/dist/jspdf.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="<?= e(asset('js/analytics.js')) ?>"></script>
<?php endif; ?>
</body>
</html>
