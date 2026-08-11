<?php
/**
 * =============================================================================
 *  Admin — Redirects CRUD.
 *  Manage URL redirects (301/302): list + create + edit + delete.
 *  source_url is unique (verified with a COUNT check). No file uploads.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'redirects';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/* -------------------------------------------------------------- SAVE (create/update) */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);

    $errors = validate($_POST, [
        'source_url' => 'required|max:255',
        'target_url' => 'required|max:255',
    ]);
    if ($errors) {
        set_flash('error', reset($errors));
        redirect('/admin/redirects?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    $sourceUrl = clean(post('source_url'));
    $targetUrl = clean(post('target_url'));

    // source_url must be unique (COUNT check, excluding the row being edited).
    $dupWhere  = 'source_url = :s';
    $dupParams = [':s' => $sourceUrl];
    if ($editId) {
        $dupWhere .= ' AND id <> :id';
        $dupParams[':id'] = $editId;
    }
    if (db_count($table, $dupWhere, $dupParams) > 0) {
        set_flash('error', 'A redirect for that source URL already exists.');
        redirect('/admin/redirects?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    $data = [
        'source_url'  => $sourceUrl,
        'target_url'  => $targetUrl,
        'status_code' => in_array((int) post('status_code'), [301, 302], true) ? (int) post('status_code') : 301,
        'status'      => post('status') ? 1 : 0,
    ];

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'redirects', 'Updated redirect #' . $editId);
        set_flash('success', 'Redirect updated successfully.');
    } else {
        $newId = db_insert($table, $data);
        log_activity('create', 'redirects', 'Created redirect #' . $newId);
        set_flash('success', 'Redirect created successfully.');
    }
    redirect('/admin/redirects');
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row = find($table, $delId);
    if ($row) {
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'redirects', 'Deleted redirect #' . $delId);
        set_flash('success', 'Redirect deleted.');
    }
    redirect('/admin/redirects');
}

/* -------------------------------------------------------------- FORM (create/edit) */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Redirect not found.');
        redirect('/admin/redirects');
    }
    $page_title = $action === 'edit' ? 'Edit Redirect' : 'Add Redirect';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= lucide('route') ?> <?= e($page_title) ?></h1><span class="muted">Redirects / <?= $action === 'edit' ? 'Edit' : 'Create' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('redirects')) ?>">← Back to list</a>
    </div>

    <div class="panel">
        <div class="panel-head">
            <h2 class="panel-title"><?= lucide('shuffle') ?> Redirect rule</h2>
            <?php if ($action === 'edit'): ?>
                <span class="pill <?= !empty($row['status']) ? 'pill-green' : 'pill-gray' ?>"><?= !empty($row['status']) ? 'Active' : 'Inactive' ?></span>
            <?php endif; ?>
        </div>
        <form class="admin-form panel-body" method="post" action="<?= e(admin_url('redirects')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="_do" value="save">
            <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

            <div class="form-group">
                <label class="form-label">Source URL <span class="req">*</span></label>
                <input class="form-control" name="source_url" required value="<?= e($row['source_url'] ?? '') ?>" placeholder="/old-page">
                <span class="form-hint">The path visitors request (must be unique), e.g. <code>/old-page</code>.</span>
            </div>

            <div class="form-group">
                <label class="form-label">Target URL <span class="req">*</span></label>
                <input class="form-control" name="target_url" required value="<?= e($row['target_url'] ?? '') ?>" placeholder="/new-page">
                <span class="form-hint">Where they are sent, e.g. <code>/new-page</code> or a full URL.</span>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Status Code</label>
                    <select class="form-select" name="status_code">
                        <?php $sc = (int) ($row['status_code'] ?? 301); ?>
                        <option value="301" <?= $sc === 301 ? 'selected' : '' ?>>301 — Permanent</option>
                        <option value="302" <?= $sc === 302 ? 'selected' : '' ?>>302 — Temporary</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Hits</label>
                    <input class="form-control" type="text" value="<?= (int) ($row['hits'] ?? 0) ?>" readonly>
                    <span class="form-hint">Times this redirect has been followed.</span>
                </div>
            </div>

            <label class="checkbox"><input type="checkbox" name="status" value="1" <?= (!isset($row['status']) || !empty($row['status'])) ? 'checked' : '' ?>> Active (redirect is live)</label>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> Redirect</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('redirects')) ?>">Cancel</a>
            </div>
        </form>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Redirect Manager';
$search = trim((string) get('q', ''));
$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', source_url, target_url) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
$p = paginate("SELECT * FROM $table WHERE $where ORDER BY id DESC", $params, 15);

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1><?= lucide('route') ?> Redirect Manager</h1><span class="muted"><?= (int) $p['total'] ?> total</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('redirects?action=create')) ?>"><?= lucide('plus') ?> Add Redirect</a>
</div>

<div class="panel">
    <div class="panel-head">
        <h2 class="panel-title"><?= lucide('shuffle') ?> Redirect rules</h2>
        <span class="pill pill-tag"><?= (int) $p['total'] ?> rules</span>
    </div>
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('redirects')) ?>">
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search redirects…">
            </form>
            <?php if ($search !== ''): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('redirects')) ?>">Clear search</a>
            <?php endif; ?>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Source</th><th>Target</th><th>Code</th><th>Hits</th><th>Status</th><th class="num">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td><code><?= e($r['source_url']) ?></code></td>
                        <td><code><?= e($r['target_url']) ?></code></td>
                        <td><span class="pill <?= 'pill-tag' ?>"><?= (int) $r['status_code'] ?></span></td>
                        <td><?= (int) $r['hits'] ?></td>
                        <td><span class="pill <?= !empty($r['status']) ? 'pill-green' : 'pill-gray' ?>"><?= !empty($r['status']) ? 'Active' : 'Inactive' ?></span></td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('redirects?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                                <form method="post" action="<?= e(admin_url('redirects')) ?>" data-confirm="Delete this redirect permanently?">
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
            <?php /* Shared .empty-state shape — swap for the admin_ui.php helper once it ships. */ ?>
            <div class="empty-state">
                <div class="icon"><?= lucide('shuffle') ?></div>
                <?php if ($search !== ''): ?>
                    <p class="es-title">No redirects match &ldquo;<?= e($search) ?>&rdquo;</p>
                    <p class="es-text">No rule has that source or target URL. Clear the search to see every rule.</p>
                    <div class="es-actions">
                        <a class="btn btn-secondary" href="<?= e(admin_url('redirects')) ?>">Clear search</a>
                        <a class="btn btn-primary" href="<?= e(admin_url('redirects?action=create')) ?>"><?= lucide('plus') ?> Add Redirect</a>
                    </div>
                <?php else: ?>
                    <p class="es-title">No redirects yet</p>
                    <p class="es-text">Add a rule when a page moves, so old links keep working instead of returning a 404. Use 301 for a permanent move, 302 while you are still deciding.</p>
                    <div class="es-actions">
                        <a class="btn btn-primary" href="<?= e(admin_url('redirects?action=create')) ?>"><?= lucide('plus') ?> Add your first redirect</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
