<?php
/**
 * =============================================================================
 *  Admin — Page Builder (block-based).
 *  List pages, create a new draft, and edit a page's title/slug/status plus an
 *  ordered list of content BLOCKS stored as JSON in `pages.blocks`.
 *
 *  Supported block types: hero, text, image, cards, columns.
 *  The blocks editor is a small vanilla-JS UI that serialises the whole blocks
 *  array into a single hidden field; the server decodes, whitelists and
 *  re-encodes it — so saving is robust even if the JS mangles the payload.
 *
 *  Follows the standard admin CRUD pattern (see admin/faqs.php, admin/pages.php).
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'pages';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/**
 * Decode + whitelist a blocks JSON string into a clean, ordered array.
 *
 * Delegates to blocks_sanitize() (includes/blocks.php), which derives the
 * whitelist from the shared block registry. The registry also drives the editor
 * UI below and the public renderer in page.php, so a new widget is declared
 * once instead of being hand-written into three separate switches that drift.
 */
$sanitizeBlocks = static function (string $json): array {
    return blocks_sanitize($json);
};


/* -------------------------------------------------------------- NEW PAGE (create draft) */
if (is_post() && post('_do') === 'new') {
    require_csrf();
    $title = clean(post('title', ''));
    if ($title === '') {
        set_flash('error', 'Please enter a title for the new page.');
        redirect('/admin/page-builder');
    }
    $newId = db_insert($table, [
        'title'  => $title,
        'slug'   => unique_slug($table, $title),
        'status' => 'draft',
        'blocks' => '[]',
    ]);
    log_activity('create', 'page-builder', 'Created page #' . $newId);
    set_flash('success', 'Draft page created. Start adding blocks.');
    redirect('/admin/page-builder?action=edit&id=' . $newId);
}

/* -------------------------------------------------------------- SAVE (update) */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);
    $row = $editId ? db_row("SELECT * FROM $table WHERE id = :id AND deleted_at IS NULL", [':id' => $editId]) : null;
    if (!$row) {
        set_flash('error', 'Page not found.');
        redirect('/admin/page-builder');
    }

    $errors = validate($_POST, ['title' => 'required|max:191']);
    if ($errors) {
        set_flash('error', reset($errors));
        redirect('/admin/page-builder?action=edit&id=' . $editId);
    }

    $blocks = $sanitizeBlocks((string) post('blocks', '[]'));

    $data = [
        'title'  => clean(post('title')),
        'slug'   => unique_slug($table, post('slug') ?: post('title'), $editId),
        'status' => in_array(post('status'), ['draft', 'published'], true) ? post('status') : 'draft',
        'blocks' => json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ];

    db_update($table, $data, 'id = :id', [':id' => $editId]);
    log_activity('update', 'page-builder', 'Updated page #' . $editId . ' (' . count($blocks) . ' blocks)');
    set_flash('success', 'Page saved successfully.');
    redirect('/admin/page-builder?action=edit&id=' . $editId);
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row = find($table, $delId);
    if ($row) {
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'page-builder', 'Deleted page #' . $delId);
        set_flash('success', 'Page deleted.');
    }
    redirect('/admin/page-builder');
}

/* ============================================================== EDIT VIEW */
if ($action === 'edit') {
    $row = db_row("SELECT * FROM $table WHERE id = :id AND deleted_at IS NULL", [':id' => $id]);
    if (!$row) {
        set_flash('error', 'Page not found.');
        redirect('/admin/page-builder');
    }

    $blocks = [];
    if (!empty($row['blocks'])) {
        $decoded = json_decode((string) $row['blocks'], true);
        if (is_array($decoded)) {
            $blocks = $sanitizeBlocks((string) $row['blocks']);
        }
    }
    $blocksJson = json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

    $page_title = 'Edit Page';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1>Edit Page</h1><span class="muted">Page Builder / <?= e($row['title']) ?></span></div>
        <div class="flex gap-2">
            <?php if ($row['status'] === 'published'): ?>
                <a class="btn btn-outline" href="<?= e(url('page?slug=' . rawurlencode($row['slug']))) ?>" target="_blank"><?= lucide('external-link') ?> View</a>
            <?php endif; ?>
            <a class="btn btn-secondary" href="<?= e(admin_url('page-builder')) ?>">&larr; All Pages</a>
        </div>
    </div>

    <form id="pbForm" method="post" action="<?= e(admin_url('page-builder')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="save">
        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
        <input type="hidden" name="blocks" id="pbBlocksField" value="<?= e($blocksJson) ?>">

        <!-- ---- Page meta ---- -->
        <div class="panel mb-3">
            <div class="panel-head"><h2 class="panel-title"><?= lucide('file-text') ?> Page Details</h2></div>
            <div class="panel-body admin-form">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Title <span class="req">*</span></label>
                        <input class="form-control" name="title" data-slug-source required value="<?= e($row['title']) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Slug</label>
                        <input class="form-control" name="slug" data-slug-target value="<?= e($row['slug']) ?>" placeholder="auto-generated">
                        <small class="form-hint">Frontend URL: <code><?= e(url('page?slug=' . $row['slug'])) ?></code></small>
                    </div>
                </div>
                <div class="form-group pb-w-280">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="draft"     <?= $row['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="published" <?= $row['status'] === 'published' ? 'selected' : '' ?>>Published</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- ---- Blocks editor ---- -->
        <div class="panel">
            <div class="panel-head">
                <h2 class="panel-title"><?= lucide('layout-list') ?> Content Blocks</h2>
                <span class="muted">Add, reorder and edit the blocks that make up this page.</span>
            </div>
            <div class="panel-body">
                <div id="pbBlocks" class="pb-blocks"></div>

                <?php
                /* Widget picker, built from the registry so a new entry in
                   blocks_registry() appears here automatically. */
                $pbGroups = [];
                foreach (blocks_registry() as $pbType => $pbDef) {
                    $pbGroups[$pbDef['group'] ?? 'Other'][$pbType] = $pbDef;
                }
                ?>
                <div class="pb-add">
                    <select class="form-select" id="pbAddType" aria-label="Block type to add">
                        <?php foreach ($pbGroups as $pbGroup => $pbTypes): ?>
                            <optgroup label="<?= e($pbGroup) ?>">
                                <?php foreach ($pbTypes as $pbType => $pbDef): ?>
                                    <option value="<?= e($pbType) ?>"><?= e($pbDef['label']) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn btn-secondary" id="pbAddBtn"><?= lucide('plus') ?> Add block</button>
                    <span class="pb-autosave" id="pbAutosave" aria-live="polite"></span>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit"><?= lucide('save') ?> Save Page</button>
            <a class="btn btn-ghost" href="<?= e(admin_url('page-builder')) ?>">Cancel</a>
        </div>
    </form>

    <style>
        /* Block-editor layout only. Buttons, icon buttons, panels, form controls
           and the empty state all come from the shared admin layers. */
        .pb-blocks { display: flex; flex-direction: column; gap: var(--sp-4); }
        .pb-block { border: 1px solid var(--border); border-radius: var(--r-md); background: var(--surface-2); overflow: hidden;
            transition: box-shadow var(--dur-1) var(--ease), opacity var(--dur-1) var(--ease); }
        .pb-block-head { display: flex; align-items: center; justify-content: space-between; gap: var(--sp-3);
            padding: var(--sp-2) var(--sp-3); background: var(--surface); border-bottom: 1px solid var(--border); }
        .pb-type { display: inline-flex; align-items: center; gap: var(--sp-1); font-weight: 700; font-size: .9rem; text-transform: capitalize; }
        .pb-type i { width: 16px; height: 16px; }
        .pb-tools { display: flex; gap: var(--sp-1); }
        .pb-block-body { padding: var(--sp-4); display: grid; gap: var(--sp-3); }
        .pb-item { border: 1px dashed var(--border); border-radius: var(--r-md); padding: var(--sp-3);
            display: grid; gap: var(--sp-2); background: var(--surface); position: relative; }
        .pb-item-head { display: flex; align-items: center; justify-content: space-between; }
        .pb-item-head strong { font-size: .8rem; color: var(--muted); }
        .pb-sub-add { justify-self: start; }
        .pb-add { display: flex; gap: var(--sp-2); align-items: center; margin-top: var(--sp-4);
            padding-top: var(--sp-4); border-top: 1px dashed var(--border); flex-wrap: wrap; }
        .pb-add .form-select { max-width: 200px; }
        .pb-w-280 { max-width: 280px; }

        /* ---- drag to reorder: the grip and the drop indicator ---- */
        .pb-grip { cursor: grab; color: var(--muted); display: inline-flex; padding: var(--sp-1); margin-right: calc(var(--sp-1) * -1); }
        .pb-grip:active { cursor: grabbing; }
        .pb-grip i { width: 16px; height: 16px; }
        .pb-block.is-dragging { opacity: .45; }
        .pb-block.is-over { box-shadow: 0 -3px 0 0 var(--brand-600, #0B4E3D); }

        /* ---- collapse ---- */
        .pb-block.is-collapsed .pb-block-body { display: none; }
        .pb-block.is-collapsed .pb-block-head { border-bottom: 0; }

        /* ---- generic field wrapper (registry-driven) ---- */
        .pb-f { min-width: 0; }
        .pb-block-body .form-label, .pb-f .form-label { display: block; font-size: .82rem; margin-bottom: var(--sp-1); }
        .pb-items { display: grid; gap: var(--sp-2); }
        /* a colour input previews the value it holds, so it keeps its own chrome */
        .pb-color { width: 100%; height: 38px; padding: 2px; border: 1px solid var(--border);
            border-radius: var(--r-sm); background: var(--surface); cursor: pointer; }

        /* ---- style drawer ---- */
        .pb-style { margin-top: var(--sp-1); border: 1px dashed var(--border); border-radius: var(--r-md); background: var(--surface); }
        .pb-style > summary { cursor: pointer; padding: var(--sp-2) var(--sp-3); font-size: .84rem; font-weight: 650;
            display: flex; align-items: center; gap: var(--sp-1); list-style: none; }
        .pb-style > summary::-webkit-details-marker { display: none; }
        .pb-style > summary i { width: 15px; height: 15px; }
        .pb-style[open] > summary { border-bottom: 1px dashed var(--border); }
        .pb-style-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: var(--sp-2); padding: var(--sp-3); }

        /* ---- autosave note ---- */
        .pb-autosave { margin-left: auto; font-size: .8rem; color: var(--muted); display: inline-flex; align-items: center; gap: var(--sp-1); }
        @media (max-width: 640px) { .pb-autosave { margin-left: 0; width: 100%; } }
    </style>

    <?php
    /* The registry, shipped to the editor so the field forms, defaults and
       labels all come from one definition rather than a parallel JS switch. */
    $pbJs = [];
    foreach (blocks_registry() as $pbT => $pbD) {
        $pbJs[$pbT] = [
            'label'  => $pbD['label'],
            'icon'   => $pbD['icon'],
            'group'  => $pbD['group'] ?? 'Other',
            'fields' => $pbD['fields'],
        ];
    }
    ?>
    <script type="application/json" id="pbRegistry"><?= json_encode($pbJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <script type="application/json" id="pbStyleFields"><?= json_encode(blocks_style_fields(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <script>
    (function () {
        var container = document.getElementById('pbBlocks');
        var hidden    = document.getElementById('pbBlocksField');
        var form      = document.getElementById('pbForm');
        var addBtn    = document.getElementById('pbAddBtn');
        var addType   = document.getElementById('pbAddType');
        if (!container || !hidden || !form) return;
        /* ------------------------------------------------------------------
         * Registry-driven block editor.
         * Field forms, defaults, labels and icons all come from
         * blocks_registry() (includes/blocks.php) via the JSON above, so a new
         * widget declared there shows up here with no JS change.
         * ---------------------------------------------------------------- */
        var REG    = JSON.parse(document.getElementById('pbRegistry').textContent);
        var STYLES = JSON.parse(document.getElementById('pbStyleFields').textContent);
        var DRAFT  = 'pwf_pb_draft_' + (form.querySelector('[name="id"]') ? form.querySelector('[name="id"]').value : 'new');

        var blocks = [];
        try { blocks = JSON.parse(hidden.value || '[]'); } catch (e) { blocks = []; }
        if (!Array.isArray(blocks)) { blocks = []; }

        function esc(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }
        function drawIcons() {
            if (window.PWFdrawIcons) { window.PWFdrawIcons(); }
            else if (window.lucide) { try { window.lucide.createIcons(); } catch (e) {} }
        }

        /* ---- defaults straight from the registry -------------------------- */
        function newBlock(type) {
            var def = REG[type];
            if (!def) { return null; }
            var b = { type: type };
            Object.keys(def.fields).forEach(function (k) {
                var f = def.fields[k];
                if (f.type === 'repeater') {
                    var row = {};
                    Object.keys(f.sub).forEach(function (sk) { row[sk] = ''; });
                    b[k] = [row];
                } else {
                    b[k] = f.default != null ? f.default : '';
                }
            });
            return b;
        }

        /* ---- one field, rendered by its declared type --------------------- */
        function field(key, spec, value, prefix) {
            var attr = 'data-field="' + esc(prefix ? prefix + '.' + key : key) + '"';
            var lbl  = '<label class="form-label">' + esc(spec.label || key) + '</label>';

            if (spec.type === 'textarea' || spec.type === 'richtext') {
                return '<div class="pb-f">' + lbl + '<textarea class="form-textarea" ' + attr +
                    ' rows="' + (spec.type === 'richtext' ? 6 : 4) + '">' + esc(value) + '</textarea></div>';
            }
            if (spec.type === 'select') {
                var opts = Object.keys(spec.options || {}).map(function (o) {
                    return '<option value="' + esc(o) + '"' + (String(value) === o ? ' selected' : '') + '>' +
                        esc(spec.options[o]) + '</option>';
                }).join('');
                return '<div class="pb-f">' + lbl + '<select class="form-select" ' + attr + '>' + opts + '</select></div>';
            }
            if (spec.type === 'color') {
                var v = /^#[0-9a-fA-F]{6}$/.test(value || '') ? value : '#0B4E3D';
                return '<div class="pb-f">' + lbl + '<input type="color" class="pb-color" ' + attr + ' value="' + esc(v) + '"></div>';
            }
            if (spec.type === 'number') {
                return '<div class="pb-f">' + lbl + '<input class="form-control" type="number" ' + attr + ' value="' + esc(value) + '"></div>';
            }
            var ph = spec.type === 'image' ? 'pages/photo.jpg or https://…'
                   : spec.type === 'url'   ? 'https://… or /about' : '';
            return '<div class="pb-f">' + lbl + '<input class="form-control" ' + attr +
                ' value="' + esc(value) + '" placeholder="' + esc(ph) + '"></div>';
        }

        /* ---- the body of one block --------------------------------------- */
        function buildBody(b) {
            var def = REG[b.type];
            if (!def) { return ''; }
            var html = '';
            var simple = [];

            Object.keys(def.fields).forEach(function (key) {
                var spec = def.fields[key];
                if (spec.type === 'repeater') {
                    html += '<div class="pb-items" data-rep="' + esc(key) + '">';
                    (b[key] || []).forEach(function (row, i) {
                        html += '<div class="pb-item" data-rep-row="' + esc(key) + '">' +
                            '<div class="pb-item-head"><strong>' + esc(spec.label || key) + ' ' + (i + 1) + '</strong>' +
                            '<button type="button" class="icon-btn danger" data-action="rep-remove" data-rep-key="' + esc(key) + '" title="Remove"><i data-lucide="x"></i></button></div>';
                        Object.keys(spec.sub).forEach(function (sk) {
                            html += field(sk, spec.sub[sk], row[sk] || '');
                        });
                        html += '</div>';
                    });
                    html += '</div><button type="button" class="btn btn-outline btn-sm pb-sub-add" data-action="rep-add" data-rep-key="' +
                        esc(key) + '"><i data-lucide="plus"></i> Add ' + esc((spec.label || key).replace(/s$/, '').toLowerCase()) +
                        ' <span class="muted">(max ' + (spec.max || 12) + ')</span></button>';
                } else {
                    simple.push(field(key, spec, b[key] != null ? b[key] : ''));
                }
            });

            // Pair up short fields two-across; textareas get their own row.
            html = simple.join('') + html;

            /* Shared style drawer, collapsed by default. */
            var st = b.style || {};
            var styleHtml = Object.keys(STYLES).map(function (k) {
                return field(k, STYLES[k], st[k] != null ? st[k] : (STYLES[k].default || ''), 'style');
            }).join('');
            html += '<details class="pb-style"><summary><i data-lucide="palette"></i> Style &amp; visibility</summary>' +
                '<div class="pb-style-grid">' + styleHtml + '</div></details>';

            return html;
        }

        function buildBlock(b, i, total) {
            var def = REG[b.type] || { label: b.type, icon: 'square' };
            return '<div class="pb-block" data-type="' + esc(b.type) + '" draggable="true">' +
                '<div class="pb-block-head">' +
                    '<span class="pb-grip" title="Drag to reorder"><i data-lucide="grip-vertical"></i></span>' +
                    '<span class="pb-type"><i data-lucide="' + esc(def.icon) + '"></i>' + esc(def.label) + '</span>' +
                    '<div class="pb-tools">' +
                        '<button type="button" class="icon-btn" data-action="toggle" title="Collapse / expand"><i data-lucide="chevrons-up-down"></i></button>' +
                        '<button type="button" class="icon-btn" data-action="duplicate" title="Duplicate"><i data-lucide="copy"></i></button>' +
                        '<button type="button" class="icon-btn" data-action="move-up" title="Move up"' + (i === 0 ? ' disabled' : '') + '><i data-lucide="arrow-up"></i></button>' +
                        '<button type="button" class="icon-btn" data-action="move-down" title="Move down"' + (i === total - 1 ? ' disabled' : '') + '><i data-lucide="arrow-down"></i></button>' +
                        '<button type="button" class="icon-btn danger" data-action="remove-block" title="Remove"><i data-lucide="trash-2"></i></button>' +
                    '</div>' +
                '</div>' +
                '<div class="pb-block-body">' + buildBody(b) + '</div>' +
            '</div>';
        }

        /* ---- DOM -> model, driven by the same registry -------------------- */
        function syncFromDom() {
            var out = [];
            container.querySelectorAll(':scope > .pb-block').forEach(function (el) {
                var type = el.getAttribute('data-type');
                var def  = REG[type];
                if (!def) { return; }
                var b = { type: type };

                Object.keys(def.fields).forEach(function (key) {
                    var spec = def.fields[key];
                    if (spec.type === 'repeater') {
                        b[key] = [];
                        el.querySelectorAll('[data-rep-row="' + key + '"]').forEach(function (row) {
                            var r = {};
                            Object.keys(spec.sub).forEach(function (sk) {
                                var f = row.querySelector('[data-field="' + sk + '"]');
                                r[sk] = f ? f.value : '';
                            });
                            b[key].push(r);
                        });
                    } else {
                        var f = el.querySelector('.pb-block-body > .pb-f [data-field="' + key + '"], .pb-block-body [data-field="' + key + '"]:not([data-field^="style."])');
                        b[key] = f ? f.value : '';
                    }
                });

                var st = {};
                Object.keys(STYLES).forEach(function (k) {
                    var f = el.querySelector('[data-field="style.' + k + '"]');
                    if (f && f.value !== '') { st[k] = f.value; }
                });
                if (Object.keys(st).length) { b.style = st; }

                out.push(b);
            });
            blocks = out;
        }

        function render() {
            if (!blocks.length) {
                container.innerHTML = '<div class="empty-state"><div class="icon"><i data-lucide="layout-list"></i></div>' +
                    'No blocks yet. Choose a widget below and click &ldquo;Add block&rdquo; to begin.</div>';
                drawIcons();
                return;
            }
            container.innerHTML = blocks.map(function (b, i) { return buildBlock(b, i, blocks.length); }).join('');
            drawIcons();
        }

        function blockIndex(el) {
            return Array.prototype.indexOf.call(container.querySelectorAll(':scope > .pb-block'), el);
        }

        /* ---- actions ------------------------------------------------------ */
        container.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-action]');
            if (!btn) { return; }
            e.preventDefault();
            var action  = btn.getAttribute('data-action');
            var blockEl = btn.closest('.pb-block');

            if (action === 'toggle') {           // purely visual, no re-render
                blockEl.classList.toggle('is-collapsed');
                return;
            }

            syncFromDom();
            var idx = blockEl ? blockIndex(blockEl) : -1;
            if (idx < 0) { return; }

            if (action === 'remove-block') {
                blocks.splice(idx, 1);
            } else if (action === 'duplicate') {
                blocks.splice(idx + 1, 0, JSON.parse(JSON.stringify(blocks[idx])));
            } else if (action === 'move-up' && idx > 0) {
                var t = blocks[idx - 1]; blocks[idx - 1] = blocks[idx]; blocks[idx] = t;
            } else if (action === 'move-down' && idx < blocks.length - 1) {
                var d = blocks[idx + 1]; blocks[idx + 1] = blocks[idx]; blocks[idx] = d;
            } else if (action === 'rep-add') {
                var ak = btn.getAttribute('data-rep-key');
                var spec = REG[blocks[idx].type].fields[ak];
                blocks[idx][ak] = blocks[idx][ak] || [];
                if (blocks[idx][ak].length < (spec.max || 12)) {
                    var row = {};
                    Object.keys(spec.sub).forEach(function (sk) { row[sk] = ''; });
                    blocks[idx][ak].push(row);
                }
            } else if (action === 'rep-remove') {
                var rk  = btn.getAttribute('data-rep-key');
                var rows = Array.prototype.slice.call(blockEl.querySelectorAll('[data-rep-row="' + rk + '"]'));
                var ri  = rows.indexOf(btn.closest('[data-rep-row]'));
                if (ri > -1 && blocks[idx][rk]) { blocks[idx][rk].splice(ri, 1); }
            }
            render();
            autosave();
        });

        /* ---- drag to reorder ---------------------------------------------- */
        var dragEl = null;
        container.addEventListener('dragstart', function (e) {
            var el = e.target.closest('.pb-block');
            if (!el) { return; }
            dragEl = el;
            el.classList.add('is-dragging');
            e.dataTransfer.effectAllowed = 'move';
            try { e.dataTransfer.setData('text/plain', ''); } catch (err) {}
        });
        container.addEventListener('dragend', function () {
            if (dragEl) { dragEl.classList.remove('is-dragging'); }
            container.querySelectorAll('.pb-block').forEach(function (b) { b.classList.remove('is-over'); });
            dragEl = null;
        });
        container.addEventListener('dragover', function (e) {
            if (!dragEl) { return; }
            e.preventDefault();
            var over = e.target.closest('.pb-block');
            container.querySelectorAll('.pb-block').forEach(function (b) { b.classList.remove('is-over'); });
            if (over && over !== dragEl) { over.classList.add('is-over'); }
        });
        container.addEventListener('drop', function (e) {
            if (!dragEl) { return; }
            e.preventDefault();
            var over = e.target.closest('.pb-block');
            if (!over || over === dragEl) { return; }
            syncFromDom();
            var from = blockIndex(dragEl);
            var to   = blockIndex(over);
            if (from < 0 || to < 0) { return; }
            var moved = blocks.splice(from, 1)[0];
            blocks.splice(to, 0, moved);
            render();
            autosave();
        });

        /* ---- add ----------------------------------------------------------- */
        if (addBtn) {
            addBtn.addEventListener('click', function () {
                syncFromDom();
                var b = newBlock(addType.value);
                if (b) { blocks.push(b); render(); autosave(); }
            });
        }

        /* ---- autosave (local draft only; Save Page still writes the DB) ---- */
        var saveNote = document.getElementById('pbAutosave');
        var saveTimer;
        function autosave() {
            clearTimeout(saveTimer);
            saveTimer = setTimeout(function () {
                try {
                    syncFromDom();
                    localStorage.setItem(DRAFT, JSON.stringify({ t: Date.now(), b: blocks }));
                    if (saveNote) {
                        saveNote.textContent = 'Draft saved locally · ' +
                            new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    }
                } catch (err) { /* private mode / quota — best effort */ }
            }, 900);
        }
        container.addEventListener('input', autosave);

        // Offer an unsaved local draft if it is newer than what the DB returned.
        try {
            var raw = localStorage.getItem(DRAFT);
            if (raw) {
                var box = JSON.parse(raw);
                if (box && box.b && JSON.stringify(box.b) !== JSON.stringify(blocks) && (Date.now() - box.t) < 6048e5) {
                    if (saveNote) {
                        saveNote.innerHTML = 'Unsaved local draft found. ' +
                            '<button type="button" class="btn btn-sm btn-outline" id="pbRestore">Restore</button> ' +
                            '<button type="button" class="btn btn-sm btn-ghost" id="pbDiscard">Discard</button>';
                        saveNote.addEventListener('click', function (ev) {
                            if (ev.target.id === 'pbRestore') { blocks = box.b; render(); saveNote.textContent = 'Draft restored.'; }
                            if (ev.target.id === 'pbDiscard') { localStorage.removeItem(DRAFT); saveNote.textContent = ''; }
                        });
                    }
                }
            }
        } catch (err) { /* ignore */ }

        form.addEventListener('submit', function () {
            syncFromDom();
            hidden.value = JSON.stringify(blocks);
            try { localStorage.removeItem(DRAFT); } catch (err) {}
        });

        render();
    })();
    </script>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* ============================================================== LIST VIEW */
$page_title = 'Page Builder';
$search = trim((string) get('q', ''));
$where  = 'deleted_at IS NULL';
$params = [];
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', title, slug) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
$p = paginate("SELECT * FROM $table WHERE $where ORDER BY id DESC", $params, 12);

include __DIR__ . '/partials/head.php';
?>
<style>
    /* Two layout-only helpers for the list view. */
    .pb-w-360 { max-width: 360px; }
</style>

<div class="admin-page-head">
    <div><h1>Page Builder</h1><span class="muted"><?= (int) $p['total'] ?> total</span></div>
</div>

<div class="panel mb-3">
    <div class="panel-head"><h2 class="panel-title"><?= lucide('plus') ?> Create a New Page</h2></div>
    <div class="panel-body">
        <form class="flex gap-2 flex-wrap items-center" method="post" action="<?= e(admin_url('page-builder')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="_do" value="new">
            <input class="form-control pb-w-360" name="title" required maxlength="191" placeholder="New page title…">
            <button class="btn btn-primary" type="submit"><?= lucide('file-plus') ?> New Page</button>
        </form>
        <small class="form-hint">Creates a draft with a unique slug, then opens the block editor.</small>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('page-builder')) ?>">
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search pages…">
            </form>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>#</th><th>Title</th><th>Slug</th><th>Blocks</th><th>Status</th><th>Updated</th><th class="num">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r):
                    $decoded = json_decode((string) ($r['blocks'] ?? ''), true);
                    $blockCount = is_array($decoded) ? count($decoded) : 0; ?>
                    <tr>
                        <td><?= (int) $r['id'] ?></td>
                        <td><strong><?= e($r['title']) ?></strong></td>
                        <td><code><?= e($r['slug']) ?></code></td>
                        <td><span class="pill pill-tag"><?= (int) $blockCount ?> block<?= $blockCount === 1 ? '' : 's' ?></span></td>
                        <td><span class="pill <?= $r['status'] === 'published' ? 'pill-green' : 'pill-amber' ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                        <td><small class="text-muted"><?= e(format_date($r['updated_at'] ?? $r['created_at'], 'd M Y')) ?></small></td>
                        <td>
                            <div class="actions">
                                <?php if ($r['status'] === 'published'): ?>
                                    <a class="icon-btn" href="<?= e(url('page?slug=' . rawurlencode($r['slug']))) ?>" target="_blank" title="View"><?= lucide('external-link') ?></a>
                                <?php endif; ?>
                                <a class="icon-btn" href="<?= e(admin_url('page-builder?action=edit&id=' . $r['id'])) ?>" title="Edit blocks"><?= lucide('pencil') ?></a>
                                <form method="post" action="<?= e(admin_url('page-builder')) ?>" data-confirm="Delete this page permanently?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_do" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= $p['links'] ?>
        <?php else: ?>
            <?php /* Branched on $search. Creation is the inline form in the panel above,
                     not an ?action=create route, so the empty arm points there. */ ?>
            <div class="empty-state">
                <div class="icon"><?= lucide('layout-template') ?></div>
                <?php if ($search !== ''): ?>
                    <p class="es-title">No pages match &ldquo;<?= e($search) ?>&rdquo;</p>
                    <p class="es-text">No page has that title or slug. Clear the search to see every page.</p>
                    <div class="es-actions">
                        <a class="btn btn-secondary" href="<?= e(admin_url('page-builder')) ?>">Clear search</a>
                    </div>
                <?php else: ?>
                    <p class="es-title">No pages yet</p>
                    <p class="es-text">A builder page is assembled from content blocks rather than one body field.
                        Name one in the panel above and you will land straight in the block editor.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
