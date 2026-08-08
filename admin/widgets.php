<?php
/**
 * =============================================================================
 *  Admin — Widget Manager CRUD.
 *  Reusable content blocks rendered into named positions (footer, sidebar,
 *  homepage) via includes/widgets.php render_widgets(). Provides list +
 *  create + edit + delete + status toggle, with CSRF, validation, pagination
 *  and activity logging. Follows the standard admin module pattern
 *  (see admin/faqs.php).
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'widgets';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/* Allowed value sets (keep in sync with the widgets ENUM + render_widgets()). */
$positions = ['footer', 'sidebar', 'homepage'];
$types     = ['html', 'text', 'links', 'contact', 'newsletter'];

/* Short authoring hint shown under the content box, one per widget type. */
$typeHints = [
    'html'       => 'Raw HTML — output exactly as written (trusted admin markup).',
    'text'       => 'Plain text — line breaks are kept, any HTML is escaped.',
    'links'      => 'One link per line as "Label | url". External URLs kept as-is; internal paths are resolved.',
    'contact'    => 'Auto-generated from the site contact settings — the content box is ignored.',
    'newsletter' => 'Auto-generated subscribe form — the content box is ignored.',
];

/* -------------------------------------------------------------- SAVE (create/update) */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);

    $errors = validate($_POST, [
        'title' => 'required|max:191',
    ]);
    if ($errors) {
        set_flash('error', reset($errors));
        redirect('/admin/widgets?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    $position = clean(post('position', ''));
    if (!in_array($position, $positions, true)) {
        $position = 'footer';
    }
    $type = post('type', 'html');
    if (!in_array($type, $types, true)) {
        $type = 'html';
    }

    $data = [
        'title'      => clean(post('title')),
        'position'   => $position,
        'type'       => $type,
        'content'    => (string) post('content', ''),
        'sort_order' => (int) post('sort_order', 0),
        'status'     => post('status') ? 1 : 0,
    ];

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'widgets', 'Updated widget #' . $editId);
        set_flash('success', 'Widget updated successfully.');
    } else {
        $newId = db_insert($table, $data);
        log_activity('create', 'widgets', 'Created widget #' . $newId);
        set_flash('success', 'Widget created successfully.');
    }
    redirect('/admin/widgets');
}

/* -------------------------------------------------------------- STATUS TOGGLE */
if (is_post() && post('_do') === 'toggle') {
    require_csrf();
    $tid = (int) post('id', 0);
    $row = find($table, $tid);
    if ($row) {
        $new = empty($row['status']) ? 1 : 0;
        db_update($table, ['status' => $new], 'id = :id', [':id' => $tid]);
        log_activity('update', 'widgets', 'Set widget #' . $tid . ' to ' . ($new ? 'active' : 'inactive'));
        set_flash('success', 'Widget ' . ($new ? 'activated' : 'deactivated') . '.');
    }
    redirect('/admin/widgets');
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row = find($table, $delId);
    if ($row) {
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'widgets', 'Deleted widget #' . $delId);
        set_flash('success', 'Widget deleted.');
    }
    redirect('/admin/widgets');
}

/* -------------------------------------------------------------- FORM (create/edit) */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Widget not found.');
        redirect('/admin/widgets');
    }
    $curPosition = $row['position'] ?? 'footer';
    $curType     = $row['type'] ?? 'html';
    $page_title  = $action === 'edit' ? 'Edit Widget' : 'Add Widget';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Widgets / <?= $action === 'edit' ? 'Edit' : 'Create' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('widgets')) ?>">← Back to list</a>
    </div>

    <div class="panel">
        <form class="admin-form panel-body" method="post" action="<?= e(admin_url('widgets')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="_do" value="save">
            <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

            <div class="form-group">
                <label class="form-label">Title <span class="req">*</span></label>
                <input class="form-control" name="title" required maxlength="191" value="<?= e($row['title'] ?? '') ?>">
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Position</label>
                    <select class="form-select" name="position">
                        <?php foreach ($positions as $pos): ?>
                            <option value="<?= e($pos) ?>" <?= $curPosition === $pos ? 'selected' : '' ?>><?= e(ucfirst($pos)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select class="form-select" name="type" id="widget-type" data-hints='<?= e(json_encode($typeHints, JSON_UNESCAPED_SLASHES)) ?>'>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= e($t) ?>" <?= $curType === $t ? 'selected' : '' ?>><?= e(ucfirst($t)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Content</label>
                <textarea class="form-textarea widget-content" name="content" style="min-height:180px;"><?= e($row['content'] ?? '') ?></textarea>
                <p class="form-hint" id="widget-hint"><?= e($typeHints[$curType] ?? $typeHints['html']) ?></p>
            </div>

            <div class="form-group">
                <label class="form-label">Sort Order</label>
                <input class="form-control" type="number" name="sort_order" value="<?= (int) ($row['sort_order'] ?? 0) ?>" style="max-width:180px;">
            </div>

            <label class="checkbox"><input type="checkbox" name="status" value="1" <?= (!array_key_exists('status', (array) $row) || !empty($row['status'])) ? 'checked' : '' ?>> Active (rendered on the site)</label>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> Widget</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('widgets')) ?>">Cancel</a>
            </div>
        </form>
    </div>

    <style>
        .admin-form .widget-content { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .9rem; line-height: 1.55; }
    </style>
    <script>
        (function () {
            var sel = document.getElementById('widget-type');
            var hint = document.getElementById('widget-hint');
            if (!sel || !hint) return;
            var hints = {};
            try { hints = JSON.parse(sel.getAttribute('data-hints') || '{}'); } catch (e) { hints = {}; }
            sel.addEventListener('change', function () {
                if (hints[sel.value]) hint.textContent = hints[sel.value];
            });
        })();
    </script>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Widgets';
$search = trim((string) get('q', ''));
$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', title, position, content) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
$p = paginate("SELECT * FROM $table WHERE $where ORDER BY position ASC, sort_order ASC, id DESC", $params, 12);

/* Pill colour per widget type for the list badge. */
$typePill = static function (string $t): string {
    switch ($t) {
        case 'html':       return 'pill-blue';
        case 'text':       return 'pill-gray';
        case 'links':      return 'pill-amber';
        case 'contact':    return 'pill-green';
        case 'newsletter': return 'pill-red';
        default:           return 'pill-gray';
    }
};

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Widgets</h1><span class="muted"><?= (int) $p['total'] ?> total</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('widgets?action=create')) ?>">+ Add Widget</a>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('widgets')) ?>">
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search widgets…">
            </form>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Title</th><th>Position</th><th>Type</th><th>Status</th><th>Order</th><th style="text-align:right;">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td><strong><?= e($r['title']) ?></strong></td>
                        <td><span class="pill pill-blue"><?= e(ucfirst($r['position'])) ?></span></td>
                        <td><span class="pill <?= $typePill($r['type']) ?>"><?= e(ucfirst($r['type'])) ?></span></td>
                        <td><span class="pill <?= !empty($r['status']) ? 'pill-green' : 'pill-gray' ?>"><?= !empty($r['status']) ? 'Active' : 'Inactive' ?></span></td>
                        <td><?= (int) $r['sort_order'] ?></td>
                        <td>
                            <div class="actions">
                                <form method="post" action="<?= e(admin_url('widgets')) ?>" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_do" value="toggle">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <button class="icon-btn" type="submit" title="<?= !empty($r['status']) ? 'Deactivate' : 'Activate' ?>"><?= lucide(!empty($r['status']) ? 'toggle-right' : 'toggle-left') ?></button>
                                </form>
                                <a class="icon-btn" href="<?= e(admin_url('widgets?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                                <form method="post" action="<?= e(admin_url('widgets')) ?>" data-confirm="Delete this widget permanently?" style="display:inline;">
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
            <div class="empty-state"><div class="icon"><?= lucide('layout-panel-left') ?></div>No widgets yet. <a href="<?= e(admin_url('widgets?action=create')) ?>">Add your first widget</a>.</div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
