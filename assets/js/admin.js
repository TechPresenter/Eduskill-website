/* =============================================================================
   Admin panel behaviour — sidebar, delete confirmation, table search,
   image preview, auto-slug. Vanilla JS + SweetAlert2 (loaded via CDN).
   ============================================================================ */
(function () {
    'use strict';

    /* Premium sidebar: collapse (desktop), off-canvas (mobile), accordion, ripple */
    function initSidebar() {
        const body = document.body;
        const topToggle = document.getElementById('sidebarToggle');
        const overlay = document.getElementById('adminOverlay');
        const collapseBtn = document.querySelector('[data-sidebar-collapse]');
        const sidebar = document.getElementById('adminSidebar');
        const isMobile = () => window.innerWidth <= 992;

        // Restore collapsed preference (desktop only).
        if (localStorage.getItem('pwf-admin-collapsed') === '1' && !isMobile()) {
            body.classList.add('sidebar-collapsed');
        }
        const toggleCollapse = () => {
            body.classList.toggle('sidebar-collapsed');
            localStorage.setItem('pwf-admin-collapsed', body.classList.contains('sidebar-collapsed') ? '1' : '0');
        };
        const closeMobile = () => body.classList.remove('sidebar-open');

        if (topToggle) topToggle.addEventListener('click', () => {
            isMobile() ? body.classList.toggle('sidebar-open') : toggleCollapse();
        });
        if (collapseBtn) collapseBtn.addEventListener('click', toggleCollapse);
        if (overlay) overlay.addEventListener('click', closeMobile);
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeMobile(); });

        if (!sidebar) return;

        /* Accordion groups — persist open/closed state per group. */
        const KEY = 'pwf-admin-groups';
        let state = {};
        try { state = JSON.parse(localStorage.getItem(KEY) || '{}'); } catch (e) {}
        sidebar.querySelectorAll('[data-nav-group]').forEach((group, i) => {
            const head = group.querySelector('[data-group-toggle]');
            if (!head) return;
            const label = group.querySelector('.ngh-label');
            const id = (label ? label.textContent.trim() : '') || ('g' + i);
            if (Object.prototype.hasOwnProperty.call(state, id)) {
                group.classList.toggle('is-open', !!state[id]);
                head.setAttribute('aria-expanded', state[id] ? 'true' : 'false');
            }
            head.addEventListener('click', () => {
                if (body.classList.contains('sidebar-collapsed') && !isMobile()) return; // icons-only: no accordion
                const open = group.classList.toggle('is-open');
                head.setAttribute('aria-expanded', open ? 'true' : 'false');
                state[id] = open;
                try { localStorage.setItem(KEY, JSON.stringify(state)); } catch (e) {}
            });
        });

        /* Close the mobile drawer after tapping a link. */
        sidebar.querySelectorAll('.sidebar-link').forEach((a) => {
            a.addEventListener('click', () => { if (isMobile()) closeMobile(); });
            /* Ripple micro-interaction. */
            a.addEventListener('pointerdown', (e) => {
                const r = a.getBoundingClientRect();
                const size = Math.max(r.width, r.height);
                const s = document.createElement('span');
                s.className = 'ripple-ink';
                s.style.width = s.style.height = size + 'px';
                s.style.left = (e.clientX - r.left - size / 2) + 'px';
                s.style.top = (e.clientY - r.top - size / 2) + 'px';
                a.appendChild(s);
                setTimeout(() => s.remove(), 600);
            });
        });
    }

    /* Premium topbar: global quick-search, bell/profile popovers, live clock */
    function initTopbar() {
        const topbar = document.getElementById('adminTopbar');
        if (!topbar) return;

        /* ---- Single-popover manager (notifications + profile) ---- */
        const pops = Array.from(topbar.querySelectorAll('.tb-pop'));
        const closeAllPops = (except) => {
            pops.forEach((p) => {
                if (p === except || !p.classList.contains('is-open')) return;
                p.classList.remove('is-open');
                const b = p.querySelector('[data-pop-toggle]');
                if (b) b.setAttribute('aria-expanded', 'false');
            });
        };
        pops.forEach((pop) => {
            const btn = pop.querySelector('[data-pop-toggle]');
            if (!btn) return;
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const willOpen = !pop.classList.contains('is-open');
                closeAllPops(pop);
                pop.classList.toggle('is-open', willOpen);
                btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            });
        });
        document.addEventListener('click', (e) => { if (!e.target.closest('.tb-pop')) closeAllPops(null); });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeAllPops(null); });

        /* ---- Global mini search: quick-nav over the rendered sidebar links ---- */
        const search = document.getElementById('tbSearch');
        const input = document.getElementById('tbSearchInput');
        const results = document.getElementById('tbSearchResults');
        if (search && input && results) {
            const sidebar = document.getElementById('adminSidebar');
            const index = [];
            if (sidebar) {
                sidebar.querySelectorAll('.sidebar-link').forEach((a) => {
                    const lbl = a.querySelector('.lbl');
                    if (!lbl) return;
                    const group = a.closest('.nav-group');
                    const gLbl = group ? group.querySelector('.ngh-label') : null;
                    index.push({
                        label: lbl.textContent.trim(),
                        group: gLbl ? gLbl.textContent.trim() : '',
                        href: a.getAttribute('href') || '#',
                    });
                });
            }
            const esc = (s) => s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            const highlight = (text, q) => {
                const i = text.toLowerCase().indexOf(q);
                if (i < 0) return esc(text);
                return esc(text.slice(0, i)) + '<mark>' + esc(text.slice(i, i + q.length)) + '</mark>' + esc(text.slice(i + q.length));
            };
            let active = -1, current = [];
            const close = () => { search.classList.remove('is-open'); input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant'); active = -1; };
            const render = () => {
                const q = input.value.trim().toLowerCase();
                if (!q) { close(); results.innerHTML = ''; return; }
                current = index.filter((it) => it.label.toLowerCase().includes(q) || it.group.toLowerCase().includes(q)).slice(0, 8);
                results.innerHTML = current.length
                    ? current.map((it, i) =>
                        '<a class="tb-search-item' + (i === active ? ' is-active' : '') + '" id="tbsr-' + i + '" role="option" aria-selected="' + (i === active ? 'true' : 'false') + '" href="' + esc(it.href) + '">' +
                        '<span class="tb-search-item-lbl">' + highlight(it.label, q) + '</span>' +
                        (it.group ? '<span class="tb-search-item-grp">' + esc(it.group) + '</span>' : '') + '</a>').join('')
                    : '<div class="tb-search-empty">No matches for &ldquo;' + esc(input.value.trim()) + '&rdquo;</div>';
                input.removeAttribute('aria-activedescendant');
                search.classList.add('is-open');
                input.setAttribute('aria-expanded', 'true');
            };
            const move = (dir) => {
                if (!current.length) return;
                active = active < 0 ? (dir > 0 ? 0 : current.length - 1) : (active + dir + current.length) % current.length;
                let activeId = '';
                results.querySelectorAll('.tb-search-item').forEach((el, i) => {
                    const on = i === active;
                    el.classList.toggle('is-active', on);
                    el.setAttribute('aria-selected', on ? 'true' : 'false');
                    if (on) { activeId = el.id; el.scrollIntoView({ block: 'nearest' }); }
                });
                if (activeId) input.setAttribute('aria-activedescendant', activeId);
            };
            input.addEventListener('input', () => { active = -1; render(); });
            input.addEventListener('focus', () => { if (input.value.trim()) render(); });
            input.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
                else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
                else if (e.key === 'Enter') {
                    const items = results.querySelectorAll('.tb-search-item');
                    const el = active >= 0 ? items[active] : items[0];
                    if (el) { e.preventDefault(); window.location.href = el.getAttribute('href'); }
                } else if (e.key === 'Escape') { input.value = ''; close(); results.innerHTML = ''; input.blur(); }
            });
            document.addEventListener('click', (e) => { if (!e.target.closest('#tbSearch')) close(); });
            document.addEventListener('keydown', (e) => {
                const tag = (e.target.tagName || '').toLowerCase();
                const typing = tag === 'input' || tag === 'textarea' || e.target.isContentEditable;
                if ((e.key === '/' && !typing) || ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k')) {
                    e.preventDefault(); input.focus(); input.select();
                }
            });
        }

        /* ---- Live date & time ---- */
        const clock = document.getElementById('tbClock');
        if (clock) {
            const dEl = clock.querySelector('.tb-clock-date');
            const tEl = clock.querySelector('.tb-clock-time');
            const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            const mons = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const pad = (n) => (n < 10 ? '0' + n : '' + n);
            const tick = () => {
                const now = new Date();
                if (dEl) dEl.textContent = days[now.getDay()] + ', ' + now.getDate() + ' ' + mons[now.getMonth()] + ' ' + now.getFullYear();
                if (tEl) tEl.textContent = pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
            };
            tick();
            setInterval(tick, 1000);
        }
    }

    /* Delete confirmation for any form/link with [data-confirm] */
    function initConfirm() {
        document.querySelectorAll('[data-confirm]').forEach((el) => {
            el.addEventListener('submit', handle);
            if (el.tagName === 'A') el.addEventListener('click', handle);
            function handle(e) {
                e.preventDefault();
                const msg = el.dataset.confirm || 'Are you sure? This cannot be undone.';
                if (window.Swal) {
                    Swal.fire({
                        title: 'Please confirm', text: msg, icon: 'warning',
                        showCancelButton: true, confirmButtonColor: '#dc2626',
                        cancelButtonColor: '#64748b', confirmButtonText: 'Yes, proceed',
                    }).then((r) => { if (r.isConfirmed) proceed(); });
                } else if (confirm(msg)) {
                    proceed();
                }
                function proceed() {
                    if (el.tagName === 'FORM') el.submit();
                    else window.location.href = el.href;
                }
            }
        });
    }

    /* Client-side table search: <input data-table-search="#tableId"> */
    function initTableSearch() {
        document.querySelectorAll('[data-table-search]').forEach((input) => {
            const table = document.querySelector(input.dataset.tableSearch);
            if (!table) return;
            input.addEventListener('input', () => {
                const q = input.value.toLowerCase();
                table.querySelectorAll('tbody tr').forEach((tr) => {
                    tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
                });
            });
        });
    }

    /* Image preview: <input type="file" data-preview="#previewImg"> */
    function initImagePreview() {
        document.querySelectorAll('[data-preview]').forEach((input) => {
            const img = document.querySelector(input.dataset.preview);
            if (!img) return;
            input.addEventListener('change', () => {
                const file = input.files[0];
                if (file) { img.src = URL.createObjectURL(file); img.style.display = 'block'; }
            });
        });
    }

    /* Auto-slug: <input data-slug-source> -> <input data-slug-target> */
    function initAutoSlug() {
        const source = document.querySelector('[data-slug-source]');
        const target = document.querySelector('[data-slug-target]');
        if (!source || !target) return;
        source.addEventListener('input', () => {
            if (target.dataset.touched) return;
            target.value = source.value.toLowerCase().trim()
                .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        });
        target.addEventListener('input', () => { target.dataset.touched = '1'; });
    }

    /* Rich text editor — CKEditor 5 (super-build via CDN, lazy-loaded only on
       pages that actually have a <textarea data-wysiwyg>). */
    var CK_CDN = 'https://cdn.ckeditor.com/ckeditor5/41.4.2/super-build/ckeditor.js';
    var CK_REMOVE = ['CKBox', 'CKFinder', 'EasyImage', 'Base64UploadAdapter', 'CloudServices',
        'RealTimeCollaborativeComments', 'RealTimeCollaborativeTrackChanges', 'RealTimeCollaborativeRevisionHistory',
        'PresenceList', 'Comments', 'TrackChanges', 'TrackChangesData', 'RevisionHistory', 'Pagination', 'WProofreader',
        'MathType', 'SlashCommand', 'Template', 'DocumentOutline', 'FormatPainter', 'TableOfContents',
        'PasteFromOfficeEnhanced', 'CaseChange', 'AIAssistant', 'MultiLevelList', 'CKBoxImageEdit',
        'ExportPdf', 'ExportWord', 'ImportWord'];

    function ckBuild(ta) {
        if (ta.dataset.ckInit) return; ta.dataset.ckInit = '1';
        var meta = document.querySelector('meta[name="csrf-token"]');
        var csrf = meta ? meta.getAttribute('content') : '';
        var cfg = {
            removePlugins: CK_REMOVE,
            toolbar: { items: [
                'undo', 'redo', '|', 'sourceEditing', 'findAndReplace', '|',
                'heading', '|', 'fontFamily', 'fontSize', 'fontColor', 'fontBackgroundColor', '|',
                'bold', 'italic', 'underline', 'strikethrough', 'removeFormat', '|',
                'alignment', '|', 'bulletedList', 'numberedList', 'outdent', 'indent', '|',
                'link', 'blockQuote', 'insertTable', 'mediaEmbed', 'htmlEmbed', 'codeBlock', 'horizontalLine', 'specialCharacters', 'highlight', '|',
                'imageUpload'
            ], shouldNotGroupWhenFull: true },
            image: { toolbar: ['imageTextAlternative', 'toggleImageCaption', 'imageStyle:inline', 'imageStyle:wrapText', 'imageStyle:breakText', 'linkImage'] },
            table: { contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells', 'tableProperties', 'tableCellProperties'] },
            wordCount: { displayWords: true, displayCharacters: true }
        };
        if (window.PWF_CKUPLOAD) {
            cfg.simpleUpload = { uploadUrl: window.PWF_CKUPLOAD, withCredentials: true, headers: { 'X-CSRF-Token': csrf, 'X-Requested-With': 'XMLHttpRequest' } };
        }
        CKEDITOR.ClassicEditor.create(ta, cfg).then(function (editor) {
            editor.model.document.on('change:data', function () { ta.value = editor.getData(); });
            var form = ta.closest('form');
            if (form) form.addEventListener('submit', function () { ta.value = editor.getData(); });
            try {
                var wc = editor.plugins.get('WordCount');
                if (wc) {
                    var box = document.createElement('div'); box.className = 'ck-wordcount';
                    box.appendChild(wc.wordCountContainer);
                    editor.ui.getEditableElement().parentElement.appendChild(box);
                }
            } catch (e) {}
            ta._ckeditor = editor;
        }).catch(function (e) { console.error('CKEditor init failed:', e); ta.style.display = ''; });
    }

    function initWysiwyg() {
        var tas = document.querySelectorAll('textarea[data-wysiwyg]');
        if (!tas.length) return;
        if (typeof CKEDITOR !== 'undefined' && CKEDITOR.ClassicEditor) { tas.forEach(ckBuild); return; }
        if (window.__ckLoading__) return; window.__ckLoading__ = true;
        var s = document.createElement('script');
        s.src = CK_CDN;
        s.onload = function () { document.querySelectorAll('textarea[data-wysiwyg]').forEach(ckBuild); };
        s.onerror = function () { console.error('CKEditor failed to load from CDN.'); };
        document.head.appendChild(s);
    }

    /* Premium settings-shell pages: saving state + flash→toast (scoped) */
    function initSettingsShell() {
        if (!document.querySelector('.settings-topbar')) return;
        document.querySelectorAll('form').forEach(function (f) {
            f.addEventListener('submit', function () {
                f.querySelectorAll('[data-save]').forEach(function (b) { b.classList.add('is-saving'); b.setAttribute('aria-busy', 'true'); });
            });
        });
        if (window.Swal) {
            document.querySelectorAll('.admin-content > .alert').forEach(function (al) {
                var icon = al.classList.contains('alert-success') ? 'success'
                    : (al.classList.contains('alert-error') || al.classList.contains('alert-danger') ? 'error' : 'info');
                window.Swal.fire({ toast: true, position: 'top-end', icon: icon, title: al.textContent.trim(), showConfirmButton: false, timer: 3200, timerProgressBar: true });
                al.remove();
            });
        }
    }

    /* Flash toast passthrough for ?saved=1 style feedback (optional) */
    document.addEventListener('DOMContentLoaded', () => {
        initSidebar();
        initTopbar();
        initConfirm();
        initTableSearch();
        initImagePreview();
        initAutoSlug();
        initWysiwyg();
        initSettingsShell();
    });
})();
