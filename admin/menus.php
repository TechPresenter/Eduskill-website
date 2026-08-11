<?php
/**
 * =============================================================================
 *  Admin — Navigation Menu builder.
 *  A premium inline editor for the header/footer navigation: drag to reorder,
 *  edit label / URL / page-key / icon / mega / new-tab inline, add & delete
 *  items and sub-items — all without leaving the page (AJAX). Public nav
 *  (includes/navbar.php + footer.php) reads the same `menus` table.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table = 'menus';

/** JSON reply helper for the inline AJAX actions. */
$reply = static function (bool $ok, array $extra = []): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['ok' => $ok], $extra));
    exit;
};

$ICONS = ['', 'home', 'info', 'book-open', 'graduation-cap', 'image', 'camera', 'video', 'file-text', 'newspaper',
    'pen-line', 'help-circle', 'phone', 'mail', 'map-pin', 'users', 'user', 'hand-heart', 'heart-handshake', 'handshake',
    'award', 'gift', 'coins', 'receipt', 'briefcase', 'target', 'folder', 'layout-grid', 'layers', 'megaphone',
    'calendar-days', 'star', 'shield-check', 'gem', 'sparkles', 'globe', 'link', 'search', 'id-card', 'book', 'leaf'];

/* -------------------------------------------------------------- SAVE (create/update) */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);

    $title = clean(post('title'));
    if ($title === '') {
        if (is_ajax()) { $reply(false, ['error' => 'Label is required.']); }
        set_flash('error', 'Label is required.'); redirect('/admin/menus');
    }

    // Only top-level items can be a parent; a menu can't parent itself.
    $parentId = (int) post('parent_id', 0);
    if ($parentId) {
        $parent = find($table, $parentId);
        if (!$parent || (int) ($parent['parent_id'] ?? 0) !== 0 || $parentId === $editId) { $parentId = 0; }
    }

    $data = [
        'title'      => $title,
        'url'        => clean(post('url', '#')) ?: '#',
        'page_key'   => clean(post('page_key', '')),
        'icon'       => clean(post('icon', '')),
        'mega'       => post('mega') ? 1 : 0,
        'location'   => in_array(post('location'), ['header', 'footer', 'both'], true) ? post('location') : 'header',
        'parent_id'  => $parentId ?: null,
        'target'     => post('new_tab') ? '_blank' : '_self',
    ];
    if ($editId) {
        // keep existing order + status on inline edits
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'menus', 'Updated menu #' . $editId);
        if (is_ajax()) { $reply(true, ['id' => $editId, 'message' => 'Saved']); }
        set_flash('success', 'Menu item saved.'); redirect('/admin/menus');
    }
    // new: append to the end of its sibling group
    $maxSql = 'SELECT COALESCE(MAX(sort_order),0)+1 FROM ' . $table . ' WHERE ' . ($parentId ? 'parent_id = :p' : 'parent_id IS NULL AND location = :loc');
    $data['sort_order'] = (int) db_value($maxSql, $parentId ? [':p' => $parentId] : [':loc' => $data['location']]);
    $data['status'] = 1;
    $newId = db_insert($table, $data);
    log_activity('create', 'menus', 'Created menu #' . $newId);
    if (is_ajax()) { $reply(true, ['id' => $newId, 'message' => 'Added']); }
    set_flash('success', 'Menu item added.'); redirect('/admin/menus');
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    if ($delId) {
        db_delete($table, 'parent_id = :p', [':p' => $delId]);   // remove children first
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'menus', 'Deleted menu #' . $delId);
    }
    if (is_ajax()) { $reply(true); }
    set_flash('success', 'Menu item deleted.'); redirect('/admin/menus');
}

/* -------------------------------------------------------------- REORDER */
if (is_post() && post('_do') === 'reorder') {
    require_csrf();
    $order = (array) post('order', []);
    $i = 1;
    foreach ($order as $mid) {
        $mid = (int) $mid;
        if ($mid > 0) { db_update($table, ['sort_order' => $i++], 'id = :id', [':id' => $mid]); }
    }
    if (is_ajax()) { $reply(true); }
    redirect('/admin/menus');
}

/* -------------------------------------------------------------- TOGGLE STATUS */
if (is_post() && post('_do') === 'toggle') {
    require_csrf();
    $m = find($table, (int) post('id', 0));
    if ($m) { db_update($table, ['status' => $m['status'] ? 0 : 1], 'id = :id', [':id' => (int) $m['id']]); }
    if (is_ajax()) { $reply(true, ['status' => $m ? ($m['status'] ? 0 : 1) : 0]); }
    redirect('/admin/menus');
}

/* -------------------------------------------------------------- VIEW DATA */
$loc = in_array(get('loc', 'header'), ['header', 'footer'], true) ? get('loc') : 'header';
$top = db_all("SELECT * FROM $table WHERE parent_id IS NULL AND location IN (:loc,'both') ORDER BY sort_order ASC, id ASC", [':loc' => $loc]);
$childBy = [];
foreach (db_all("SELECT * FROM $table WHERE parent_id IS NOT NULL ORDER BY sort_order ASC, id ASC") as $c) {
    $childBy[(int) $c['parent_id']][] = $c;
}
$totalItems = count($top);
foreach ($top as $t) { $totalItems += count($childBy[(int) $t['id']] ?? []); }

/** Render one editable menu row (top-level or child). */
$renderRow = static function (array $m, bool $isChild = false) use ($ICONS): void {
    $newTab = ($m['target'] ?? '_self') === '_blank';
    ?>
    <div class="mrow<?= $isChild ? ' is-child' : '' ?><?= empty($m['status']) ? ' is-off' : '' ?>" data-id="<?= (int) $m['id'] ?>" data-loc="<?= e($m['location'] ?? 'header') ?>">
        <?php /* The grip is a mouse affordance only — a <span> with no tabindex, no role
                 and no key handler, so reordering was drag-only (fails SC 2.1.1 Keyboard
                 and SC 2.5.7 Dragging Movements). The two buttons beside it are the
                 keyboard path, mirroring admin/page-builder.php's move-up/move-down.
                 They reorder the DOM and then call the SAME saveOrder() the drag already
                 used — same _do=reorder, same order[] field, same endpoint. */ ?>
        <span class="mrow-drag" data-drag title="Drag to reorder" aria-hidden="true"><?= lucide('grip-vertical') ?></span>
        <span class="mrow-move">
            <button type="button" class="icon-btn" data-move="up" title="Move up" aria-label="Move up"><?= lucide('chevron-up') ?></button>
            <button type="button" class="icon-btn" data-move="down" title="Move down" aria-label="Move down"><?= lucide('chevron-down') ?></button>
        </span>
        <span class="mrow-ico"><i data-lucide="<?= e($m['icon'] ?: 'circle') ?>"></i></span>
        <input class="form-control mrow-label" data-f="title" value="<?= e($m['title'] ?? '') ?>" placeholder="Label" aria-label="Label">
        <input class="form-control mrow-url" data-f="url" value="<?= e($m['url'] ?? '') ?>" placeholder="/link" aria-label="URL">
        <input class="form-control mrow-key" data-f="page_key" value="<?= e($m['page_key'] ?? '') ?>" placeholder="page key" aria-label="Page key">
        <select class="form-select mrow-sel" data-f="icon" aria-label="Icon">
            <?php foreach ($ICONS as $ic): ?><option value="<?= e($ic) ?>" <?= ($m['icon'] ?? '') === $ic ? 'selected' : '' ?>><?= $ic === '' ? 'no icon' : e($ic) ?></option><?php endforeach; ?>
        </select>
        <label class="mrow-chk" title="Mega menu"><input type="checkbox" data-f="mega" <?= !empty($m['mega']) ? 'checked' : '' ?>> Mega</label>
        <label class="mrow-chk" title="Open in new tab"><input type="checkbox" data-f="new_tab" <?= $newTab ? 'checked' : '' ?>> New</label>
        <button type="button" class="btn btn-primary btn-sm" data-act="save"><?= lucide('check') ?> Save</button>
        <a class="icon-btn" href="<?= e($m['url'] && preg_match('#^https?://#', $m['url']) ? $m['url'] : url($m['url'] ?? '/')) ?>" target="_blank" title="Open"><?= lucide('eye') ?></a>
        <button type="button" class="icon-btn danger" data-act="delete" title="Delete"><?= lucide('x') ?></button>
    </div>
    <?php
};

$page_title = 'Navigation Menu';
include __DIR__ . '/partials/head.php';
?>
<style>
/* Layout-only rules for the menu builder. Tabs, panels, buttons, icon buttons
   and form controls all come from the shared admin layers. */
.nm-wrap{max-width:1240px}
.nm-grid{display:grid;grid-template-columns:1fr 340px;gap:var(--sp-5);align-items:start}
.nm-hint{display:flex;align-items:center;gap:var(--sp-1);color:var(--muted);font-size:.82rem}
.nm-listhead{display:flex;align-items:center;justify-content:space-between;gap:var(--sp-3);margin-bottom:var(--sp-4)}

/* One draggable, inline-editable row of the tree. */
.mrow{display:flex;align-items:center;gap:var(--sp-2);padding:var(--sp-2);margin-bottom:var(--sp-2);
    border:1px solid var(--border);border-radius:var(--r-md);background:var(--surface);box-shadow:var(--elev-1);
    transition:border-color var(--dur-1) var(--ease),opacity var(--dur-1) var(--ease)}
.mrow:hover{border-color:var(--muted)}
.mrow.is-child{margin-left:var(--sp-7);background:var(--surface-2);border-style:dashed}
.mrow.is-off{opacity:.55}
.mrow.dragging{opacity:.4;border-style:dashed}
.mrow.drop-target{border-color:var(--brand-600,#0B4E3D);box-shadow:0 0 0 3px color-mix(in srgb,var(--brand-600,#0B4E3D) 18%,transparent)}
.mrow-drag{flex:0 0 auto;width:26px;height:34px;display:grid;place-items:center;cursor:grab;color:var(--muted);border-radius:var(--r-sm)}
.mrow-drag:hover{background:var(--surface-2);color:var(--text)}
.mrow-drag:active{cursor:grabbing}
/* Keyboard reorder controls. Stacked so the pair costs one column, not two. */
.mrow-move{flex:0 0 auto;display:grid;gap:2px}
.mrow-move .icon-btn{width:24px;height:19px;border-radius:var(--r-sm)}
.mrow-move .icon-btn svg{width:13px;height:13px}
/* Under a thumb the stacked pair is unreachable; side by side at 44px each. */
@media (max-width:1024px),(pointer:coarse){
    .mrow-move{grid-auto-flow:column}
    .mrow-move .icon-btn{width:auto;height:auto}
}
.mrow-ico{flex:0 0 auto;width:38px;height:38px;display:grid;place-items:center;border-radius:var(--r-md);
    background:color-mix(in srgb,var(--brand-600,#0B4E3D) 10%,transparent);color:var(--brand-600,#0B4E3D)}
.mrow-ico svg,.mrow-ico i{width:19px;height:19px}
/* the shared control, sized down for a single-line editor row */
.mrow .form-control,.mrow .form-select{width:auto;min-width:0;font-size:.86rem;padding:var(--sp-2) var(--sp-3)}
.mrow-label{flex:1 1 150px}.mrow-url{flex:1 1 130px}.mrow-key{flex:0 1 110px}.mrow-sel{flex:0 0 130px}
.mrow .btn,.mrow .icon-btn{flex:0 0 auto}
.mrow .btn.saved{background:var(--st-ok)}
.mrow-chk{flex:0 0 auto;display:inline-flex;align-items:center;gap:var(--sp-1);font-size:.76rem;font-weight:650;color:var(--text-soft);white-space:nowrap;cursor:pointer}
.mrow-chk input{accent-color:var(--brand-600,#0B4E3D)}

.nm-addsub{margin:var(--sp-1) 0 var(--sp-3) var(--sp-7)}
.nm-addsub button{display:inline-flex;align-items:center;gap:var(--sp-1);padding:var(--sp-1) var(--sp-3);cursor:pointer;
    background:transparent;border:1px dashed var(--border);border-radius:var(--r-sm);color:var(--muted);font:inherit;font-size:.8rem;font-weight:600}
.nm-addsub button:hover{border-color:var(--brand-600,#0B4E3D);color:var(--brand-600,#0B4E3D)}
.nm-flags{display:flex;gap:var(--sp-5);margin-bottom:var(--sp-4)}
.nm-addbtn{width:100%;justify-content:center}
.nm-tips{background:var(--surface-2);border:1px solid var(--border);border-radius:var(--r-lg);padding:var(--sp-4)}
.nm-tips strong{display:flex;align-items:center;gap:var(--sp-1);color:var(--brand-600,#0B4E3D)}
.nm-tips ul{margin:var(--sp-2) 0 0;padding-left:var(--sp-4);color:var(--text-soft);font-size:.82rem;line-height:1.6}
.nm-tips code{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-sm);padding:0 var(--sp-1);font-size:.92em}
@media (max-width:960px){.nm-grid{grid-template-columns:1fr}.mrow{flex-wrap:wrap}.mrow-label,.mrow-url{flex:1 1 46%}}
</style>

<div class="admin-content nm-wrap">
<div class="admin-page-head">
    <div><h1><?= lucide('menu') ?> Navigation Menu</h1><span class="muted">Reorder · edit inline · manage header &amp; footer nav</span></div>
    <a class="btn btn-secondary" href="<?= e(url('/')) ?>" target="_blank"><?= lucide('external-link') ?> View site</a>
</div>

<div class="tabs">
    <a class="tab <?= $loc === 'header' ? 'is-active' : '' ?>" href="<?= e(admin_url('menus?loc=header')) ?>"><?= lucide('panel-top') ?> Header</a>
    <a class="tab <?= $loc === 'footer' ? 'is-active' : '' ?>" href="<?= e(admin_url('menus?loc=footer')) ?>"><?= lucide('panel-bottom') ?> Footer</a>
</div>

<div class="nm-grid">
    <!-- Current menu -->
    <div class="panel"><div class="panel-body">
        <div class="nm-listhead">
            <h2 class="panel-title"><?= lucide('list') ?> Current Menu <span class="muted">(<?= (int) $totalItems ?> items)</span></h2>
            <span class="nm-hint"><?= lucide('move') ?> Drag the handle, or use the arrows, to reorder</span>
        </div>
        <?php if ($top): ?>
            <div data-sortable data-scope="top">
                <?php foreach ($top as $t): $kids = $childBy[(int) $t['id']] ?? []; ?>
                    <?php $renderRow($t); ?>
                    <div data-sortable data-scope="child-<?= (int) $t['id'] ?>">
                        <?php foreach ($kids as $c) { $renderRow($c, true); } ?>
                    </div>
                    <div class="nm-addsub"><button type="button" data-addsub="<?= (int) $t['id'] ?>"><?= lucide('plus') ?> Add sub-item</button></div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon"><?= lucide('menu') ?></div>
                <p class="es-title">No <?= e($loc) ?> menu items yet</p>
                <p class="es-text">This is the navigation your visitors actually use. Add the first item
                    with the form on the right, then reorder and nest as the site grows.</p>
            </div>
        <?php endif; ?>
    </div></div>

    <!-- Add + tips -->
    <div>
        <div class="panel"><div class="panel-body">
            <h2 class="panel-title"><?= lucide('plus-circle') ?> Add Menu Item</h2>
            <form id="nmAdd">
                <div class="form-group"><label class="form-label">Label <span class="req">*</span></label><input class="form-control" name="title" required placeholder="About Us"></div>
                <div class="form-group"><label class="form-label">URL <span class="req">*</span></label><input class="form-control" name="url" required placeholder="/about"></div>
                <div class="form-group"><label class="form-label">Page key <span class="muted">(active state)</span></label><input class="form-control" name="page_key" placeholder="about"></div>
                <div class="form-group"><label class="form-label">Icon</label>
                    <select class="form-select" name="icon"><?php foreach ($ICONS as $ic): ?><option value="<?= e($ic) ?>"><?= $ic === '' ? '— none —' : e($ic) ?></option><?php endforeach; ?></select></div>
                <div class="nm-flags">
                    <label class="mrow-chk"><input type="checkbox" name="mega"> Mega Menu</label>
                    <label class="mrow-chk"><input type="checkbox" name="new_tab"> New Tab</label>
                </div>
                <input type="hidden" name="location" value="<?= e($loc) ?>">
                <button class="btn btn-primary nm-addbtn" type="submit"><?= lucide('plus') ?> Add Item</button>
            </form>
        </div></div>
        <div class="nm-tips">
            <strong><?= lucide('lightbulb') ?> Page Key Tips</strong>
            <ul>
                <li>The <b>page key</b> highlights the active menu item. Use the key the page is known by — e.g. <code>home</code>, <code>about</code>, <code>blog</code>, <code>contact</code>.</li>
                <li>Turn on <b>Mega</b> for items with sub-items to render a mega dropdown.</li>
                <li>Drag the <?= lucide('grip-vertical') ?> handle to reorder, or use the <?= lucide('chevron-up') ?><?= lucide('chevron-down') ?> arrows if you are on a keyboard. Either way the new order saves automatically.</li>
            </ul>
        </div>
    </div>
</div>
</div>

<script>
(function () {
    var token = document.querySelector('meta[name="csrf-token"]');
    token = token ? token.getAttribute('content') : '';
    var ENDPOINT = <?= json_encode(admin_url('menus')) ?>;

    function post(fields) {
        var fd = new FormData();
        Object.keys(fields).forEach(function (k) { fd.append(k, fields[k]); });
        return fetch(ENDPOINT, { method: 'POST', headers: { 'X-CSRF-Token': token, 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
            .then(function (r) { return r.json().catch(function () { return { ok: false }; }); });
    }
    function drawIcons() { if (window.lucide && window.lucide.createIcons) { try { window.lucide.createIcons(); } catch (e) {} } }

    function rowData(row) {
        var d = { _do: 'save', id: row.dataset.id, location: row.dataset.loc };
        row.querySelectorAll('[data-f]').forEach(function (el) {
            var f = el.dataset.f;
            d[f] = el.type === 'checkbox' ? (el.checked ? 1 : 0) : el.value;
        });
        return d;
    }

    /* ---- Inline save / delete ---- */
    document.addEventListener('click', function (e) {
        var saveBtn = e.target.closest('[data-act="save"]');
        if (saveBtn) {
            var row = saveBtn.closest('.mrow');
            post(rowData(row)).then(function (res) {
                if (res.ok) {
                    saveBtn.classList.add('saved');
                    saveBtn.innerHTML = '<i data-lucide="check"></i> Saved'; drawIcons();
                    // refresh icon preview
                    var ic = row.querySelector('[data-f="icon"]').value;
                    row.querySelector('.mrow-ico').innerHTML = '<i data-lucide="' + (ic || 'circle') + '"></i>'; drawIcons();
                    setTimeout(function () { saveBtn.classList.remove('saved'); saveBtn.innerHTML = '<i data-lucide="check"></i> Save'; drawIcons(); }, 1400);
                } else { alert(res.error || 'Could not save.'); }
            });
            return;
        }
        var delBtn = e.target.closest('[data-act="delete"]');
        if (delBtn) {
            var row2 = delBtn.closest('.mrow');
            if (!confirm('Delete this menu item' + (row2.querySelector('[data-scope^="child-"]') ? ' and its sub-items' : '') + '?')) return;
            post({ _do: 'delete', id: row2.dataset.id }).then(function (res) {
                if (res.ok) { row2.style.opacity = 0; setTimeout(function () { location.reload(); }, 150); }
            });
            return;
        }
        var addSub = e.target.closest('[data-addsub]');
        if (addSub) {
            var pid = addSub.dataset.addsub;
            post({ _do: 'save', id: 0, title: 'New sub-item', url: '#', location: <?= json_encode($loc) ?>, parent_id: pid }).then(function (res) { if (res.ok) location.reload(); });
        }
    });

    /* live icon preview on select change */
    document.addEventListener('change', function (e) {
        if (e.target.matches('.mrow-sel[data-f="icon"]')) {
            var row = e.target.closest('.mrow');
            row.querySelector('.mrow-ico').innerHTML = '<i data-lucide="' + (e.target.value || 'circle') + '"></i>'; drawIcons();
        }
    });

    /* ---- Add item ---- */
    var addForm = document.getElementById('nmAdd');
    if (addForm) addForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var f = new FormData(addForm), d = { _do: 'save', id: 0 };
        f.forEach(function (v, k) { d[k] = v; });
        d.mega = addForm.mega.checked ? 1 : 0; d.new_tab = addForm.new_tab.checked ? 1 : 0;
        post(d).then(function (res) { if (res.ok) location.reload(); else alert(res.error || 'Could not add.'); });
    });

    /* ---- Drag reorder (native, per sortable container) ---- */
    var dragEl = null;
    document.querySelectorAll('[data-sortable]').forEach(function (list) {
        list.querySelectorAll(':scope > .mrow').forEach(function (row) { bindRow(row, list); });
    });
    /* A top-level row is followed by its child container and its "Add sub-item"
       button. Moving the row alone would strand them, so a move carries the whole
       group: the row plus every following sibling up to the next .mrow. In a child
       list a group is just the row. saveOrder() still sends only the .mrow ids, so
       what is persisted is identical either way. */
    function groupOf(row) {
        var g = [row], n = row.nextElementSibling;
        while (n && !n.classList.contains('mrow')) { g.push(n); n = n.nextElementSibling; }
        return g;
    }
    function moveRow(row, list, dir) {
        var rows = Array.prototype.slice.call(list.querySelectorAll(':scope > .mrow'));
        var i = rows.indexOf(row);
        if (i < 0) return false;
        var target = rows[dir === 'up' ? i - 1 : i + 1];
        if (!target) return false;
        var group = groupOf(row), tGroup = groupOf(target);
        var anchor = dir === 'up' ? target : tGroup[tGroup.length - 1].nextSibling;
        group.forEach(function (el) { list.insertBefore(el, anchor); });
        saveOrder(list);
        return true;
    }
    function bindRow(row, list) {
        row.querySelectorAll(':scope > .mrow-move > [data-move]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (moveRow(row, list, btn.dataset.move)) { btn.focus(); }
            });
        });
        var handle = row.querySelector('[data-drag]');
        if (!handle) return;
        handle.addEventListener('mousedown', function () { row.draggable = true; });
        handle.addEventListener('mouseup', function () { row.draggable = false; });
        row.addEventListener('dragstart', function (e) { dragEl = row; row.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; });
        row.addEventListener('dragend', function () { row.classList.remove('dragging'); row.draggable = false; row.classList.remove('drop-target'); saveOrder(list); });
        row.addEventListener('dragover', function (e) {
            e.preventDefault();
            if (!dragEl || dragEl === row || dragEl.parentNode !== list) return;
            var r = row.getBoundingClientRect(), after = e.clientY > r.top + r.height / 2;
            list.insertBefore(dragEl, after ? row.nextSibling : row);
        });
    }
    function saveOrder(list) {
        var ids = Array.prototype.slice.call(list.querySelectorAll(':scope > .mrow')).map(function (r) { return r.dataset.id; });
        if (!ids.length) return;
        var fd = new FormData(); fd.append('_do', 'reorder');
        ids.forEach(function (id) { fd.append('order[]', id); });
        fetch(ENDPOINT, { method: 'POST', headers: { 'X-CSRF-Token': token, 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
    }
    drawIcons();
})();
</script>
<?php include __DIR__ . '/partials/foot.php'; ?>
