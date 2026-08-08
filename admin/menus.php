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
        <span class="mrow-drag" data-drag title="Drag to reorder"><?= lucide('grip-vertical') ?></span>
        <span class="mrow-ico"><i data-lucide="<?= e($m['icon'] ?: 'circle') ?>"></i></span>
        <input class="mrow-in mrow-label" data-f="title" value="<?= e($m['title'] ?? '') ?>" placeholder="Label" aria-label="Label">
        <input class="mrow-in mrow-url" data-f="url" value="<?= e($m['url'] ?? '') ?>" placeholder="/link" aria-label="URL">
        <input class="mrow-in mrow-key" data-f="page_key" value="<?= e($m['page_key'] ?? '') ?>" placeholder="page key" aria-label="Page key">
        <select class="mrow-sel" data-f="icon" aria-label="Icon">
            <?php foreach ($ICONS as $ic): ?><option value="<?= e($ic) ?>" <?= ($m['icon'] ?? '') === $ic ? 'selected' : '' ?>><?= $ic === '' ? 'no icon' : e($ic) ?></option><?php endforeach; ?>
        </select>
        <label class="mrow-chk" title="Mega menu"><input type="checkbox" data-f="mega" <?= !empty($m['mega']) ? 'checked' : '' ?>> Mega</label>
        <label class="mrow-chk" title="Open in new tab"><input type="checkbox" data-f="new_tab" <?= $newTab ? 'checked' : '' ?>> New</label>
        <button type="button" class="mrow-btn save" data-act="save"><?= lucide('check') ?> Save</button>
        <a class="mrow-ib" href="<?= e($m['url'] && preg_match('#^https?://#', $m['url']) ? $m['url'] : url($m['url'] ?? '/')) ?>" target="_blank" title="Open"><?= lucide('eye') ?></a>
        <button type="button" class="mrow-ib danger" data-act="delete" title="Delete"><?= lucide('x') ?></button>
    </div>
    <?php
};

$page_title = 'Navigation Menu';
include __DIR__ . '/partials/head.php';
?>
<style>
.nm-wrap{max-width:1240px}
.nm-tabs{display:inline-flex;background:var(--surface-2);border:1px solid var(--border);border-radius:12px;padding:3px;gap:2px;margin-bottom:1.2rem}
.nm-tab{border:0;background:transparent;color:var(--text-soft);font:inherit;font-weight:650;font-size:.9rem;padding:.5rem 1.1rem;border-radius:9px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;transition:.15s}
.nm-tab.is-active{background:linear-gradient(135deg,#308629,#58A42F);color:#fff;box-shadow:0 8px 18px -10px rgba(48,134,41,.7)}
.nm-grid{display:grid;grid-template-columns:1fr 340px;gap:1.4rem;align-items:start}
.nm-hint{display:flex;align-items:center;gap:.4rem;color:var(--muted);font-size:.82rem}
.mrow{display:flex;align-items:center;gap:.5rem;padding:.55rem .6rem;border:1px solid var(--border);border-radius:13px;background:var(--surface);margin-bottom:.6rem;
    box-shadow:0 1px 2px rgba(6,53,102,.04);transition:box-shadow .16s ease,border-color .16s ease,transform .16s ease,opacity .16s ease;animation:nmrise .3s ease both}
.mrow:hover{border-color:#a7f3d0;box-shadow:0 10px 24px -16px rgba(48,134,41,.5)}
.mrow.is-child{margin-left:2.2rem;background:var(--surface-2);border-style:dashed}
.mrow.is-off{opacity:.55}
.mrow.dragging{opacity:.4;border-style:dashed}
.mrow.drop-target{border-color:#58A42F;box-shadow:0 0 0 3px rgba(88,164,47,.18)}
@keyframes nmrise{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
.mrow-drag{cursor:grab;color:var(--muted);display:grid;place-items:center;width:26px;height:34px;flex:0 0 auto;border-radius:7px}
.mrow-drag:hover{background:var(--surface-2);color:var(--text)}
.mrow-drag:active{cursor:grabbing}
.mrow-ico{width:38px;height:38px;flex:0 0 auto;display:grid;place-items:center;border-radius:10px;background:var(--grad-brand-soft,rgba(88,164,47,.12));color:#308629}
.mrow-ico svg,.mrow-ico i{width:19px;height:19px}
.mrow-in,.mrow-sel{border:1px solid var(--border);border-radius:9px;padding:.5rem .6rem;background:var(--surface);color:var(--text);font:inherit;font-size:.86rem;transition:border-color .15s,box-shadow .15s;min-width:0}
.mrow-in:focus,.mrow-sel:focus{outline:none;border-color:#58A42F;box-shadow:0 0 0 3px rgba(88,164,47,.15)}
.mrow-label{flex:1 1 150px}.mrow-url{flex:1 1 130px}.mrow-key{flex:0 1 110px}.mrow-sel{flex:0 0 130px}
.mrow-chk{display:inline-flex;align-items:center;gap:.28rem;font-size:.76rem;font-weight:650;color:var(--text-soft);white-space:nowrap;cursor:pointer;flex:0 0 auto}
.mrow-chk input{accent-color:#308629}
.mrow-btn{display:inline-flex;align-items:center;gap:.35rem;border:0;border-radius:9px;padding:.5rem .8rem;font:inherit;font-weight:700;font-size:.82rem;cursor:pointer;flex:0 0 auto;
    background:linear-gradient(135deg,#308629,#58A42F);color:#fff;box-shadow:0 8px 16px -9px rgba(48,134,41,.7);transition:transform .15s,box-shadow .2s,background .2s}
.mrow-btn:hover{transform:translateY(-2px)}
.mrow-btn svg{width:1em;height:1em}
.mrow-btn.saved{background:linear-gradient(135deg,#047857,#308629)}
.mrow-ib{width:34px;height:34px;flex:0 0 auto;display:grid;place-items:center;border:1px solid var(--border);border-radius:9px;background:var(--surface);color:var(--text-soft);cursor:pointer;text-decoration:none;transition:.15s}
.mrow-ib:hover{transform:translateY(-2px);border-color:#58A42F;color:#308629}
.mrow-ib.danger:hover{border-color:#fecaca;color:#dc2626}
.mrow-ib svg{width:16px;height:16px}
.nm-addsub{margin:.1rem 0 .8rem 2.2rem}
.nm-addsub button{display:inline-flex;align-items:center;gap:.35rem;background:transparent;border:1px dashed var(--border);border-radius:9px;color:var(--muted);font:inherit;font-size:.8rem;font-weight:600;padding:.35rem .7rem;cursor:pointer}
.nm-addsub button:hover{border-color:#58A42F;color:#308629}
/* Add card + tips */
.nm-card{border:1px solid var(--border);border-radius:16px;background:var(--surface);box-shadow:var(--elev-1,0 12px 26px -18px rgba(6,53,102,.4));padding:1.2rem}
.nm-card h3{margin:0 0 1rem;font-size:1.02rem;font-weight:800;display:flex;align-items:center;gap:.5rem}
.nm-card h3 svg{color:#308629}
.nm-field{margin-bottom:.85rem}
.nm-field label{display:block;font-size:.8rem;font-weight:650;color:var(--text-soft);margin-bottom:.3rem}
.nm-field input,.nm-field select{width:100%;border:1px solid var(--border);border-radius:10px;padding:.55rem .7rem;background:var(--surface);color:var(--text);font:inherit;font-size:.88rem}
.nm-field input:focus,.nm-field select:focus{outline:none;border-color:#58A42F;box-shadow:0 0 0 3px rgba(88,164,47,.15)}
.nm-addbtn{width:100%;justify-content:center;font-size:.95rem;padding:.75rem}
.nm-tips{margin-top:1rem;background:linear-gradient(135deg,rgba(88,164,47,.08),rgba(48,134,41,.05));border:1px solid rgba(88,164,47,.28);border-radius:14px;padding:1rem 1.1rem}
.nm-tips strong{color:#047857;display:flex;align-items:center;gap:.4rem}
.nm-tips ul{margin:.5rem 0 0;padding-left:1.1rem;color:var(--text-soft);font-size:.82rem;line-height:1.6}
.nm-tips code{background:var(--surface);border:1px solid var(--border);border-radius:5px;padding:.05rem .3rem;font-size:.92em}
@media (max-width:960px){.nm-grid{grid-template-columns:1fr}.mrow{flex-wrap:wrap}.mrow-label,.mrow-url{flex:1 1 46%}}
</style>

<div class="admin-content nm-wrap">
<div class="admin-page-head">
    <div><h1><?= lucide('menu') ?> Navigation Menu</h1><span class="muted">Drag to reorder · edit inline · manage header &amp; footer nav</span></div>
    <a class="btn btn-secondary" href="<?= e(url('/')) ?>" target="_blank"><?= lucide('external-link') ?> View site</a>
</div>

<div class="nm-tabs">
    <a class="nm-tab <?= $loc === 'header' ? 'is-active' : '' ?>" href="<?= e(admin_url('menus?loc=header')) ?>"><?= lucide('panel-top') ?> Header</a>
    <a class="nm-tab <?= $loc === 'footer' ? 'is-active' : '' ?>" href="<?= e(admin_url('menus?loc=footer')) ?>"><?= lucide('panel-bottom') ?> Footer</a>
</div>

<div class="nm-grid">
    <!-- Current menu -->
    <div class="nm-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
            <h3 style="margin:0;"><?= lucide('list') ?> Current Menu <span class="muted" style="font-weight:600;">(<?= (int) $totalItems ?> items)</span></h3>
            <span class="nm-hint"><?= lucide('move') ?> Drag to reorder</span>
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
            <div class="empty-state"><div class="icon"><?= lucide('menu') ?></div>No <?= e($loc) ?> menu items yet. Add one on the right.</div>
        <?php endif; ?>
    </div>

    <!-- Add + tips -->
    <div>
        <div class="nm-card">
            <h3><?= lucide('plus-circle') ?> Add Menu Item</h3>
            <form id="nmAdd">
                <div class="nm-field"><label>Label <span style="color:#dc2626">*</span></label><input name="title" required placeholder="About Us"></div>
                <div class="nm-field"><label>URL <span style="color:#dc2626">*</span></label><input name="url" required placeholder="/about"></div>
                <div class="nm-field"><label>Page key <span class="muted">(active state)</span></label><input name="page_key" placeholder="about"></div>
                <div class="nm-field"><label>Icon</label>
                    <select name="icon"><?php foreach ($ICONS as $ic): ?><option value="<?= e($ic) ?>"><?= $ic === '' ? '— none —' : e($ic) ?></option><?php endforeach; ?></select></div>
                <div style="display:flex;gap:1.2rem;margin-bottom:1rem;">
                    <label class="mrow-chk"><input type="checkbox" name="mega"> Mega Menu</label>
                    <label class="mrow-chk"><input type="checkbox" name="new_tab"> New Tab</label>
                </div>
                <input type="hidden" name="location" value="<?= e($loc) ?>">
                <button class="mrow-btn nm-addbtn" type="submit"><?= lucide('plus') ?> Add Item</button>
            </form>
        </div>
        <div class="nm-tips">
            <strong><?= lucide('lightbulb') ?> Page Key Tips</strong>
            <ul>
                <li>The <b>page key</b> highlights the active menu item. Use the key the page is known by — e.g. <code>home</code>, <code>about</code>, <code>blog</code>, <code>contact</code>.</li>
                <li>Turn on <b>Mega</b> for items with sub-items to render a mega dropdown.</li>
                <li>Drag the <?= lucide('grip-vertical') ?> handle to reorder; changes save automatically.</li>
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
    function bindRow(row, list) {
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
