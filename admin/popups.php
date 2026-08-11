<?php
/**
 * =============================================================================
 *  Admin — Popups CRUD  (marketing popup manager).
 *  Full list + create + edit + delete, following the programs.php exemplar:
 *  CSRF, validation, pagination, image upload (with old-file cleanup),
 *  activity logging, flash + redirect.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'popups';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/* -------------------------------------------------------------- SAVE (create/update) */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);

    $errors = validate($_POST, [
        'title'       => 'required|max:191',
        'button_text' => 'max:64',
        'button_url'  => 'max:255',
    ]);
    if ($errors) {
        set_flash('error', reset($errors));
        redirect('/admin/popups?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    $data = [
        'title'         => clean(post('title')),
        'content'       => clean(post('content', '')),
        'button_text'   => clean(post('button_text', '')),
        'button_url'    => clean(post('button_url', '')),
        'delay_seconds' => max(0, (int) post('delay_seconds', 3)),
        'start_date'    => post('start_date') ?: null,
        'end_date'      => post('end_date') ?: null,
        'status'        => post('status') ? 1 : 0,
    ];

    // Optional image upload (replaces + deletes the old one).
    if (!empty($_FILES['image']['name'])) {
        $up = upload_image($_FILES['image'], 'media');
        if (!$up['success']) {
            set_flash('error', $up['error']);
            redirect('/admin/popups?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
        }
        $data['image'] = $up['path'];
        if ($editId) {
            $old = find($table, $editId);
            if ($old && !empty($old['image'])) delete_upload($old['image']);
        }
    }

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'popups', 'Updated popup #' . $editId);
        set_flash('success', 'Popup updated successfully.');
    } else {
        $newId = db_insert($table, $data);
        log_activity('create', 'popups', 'Created popup #' . $newId);
        set_flash('success', 'Popup created successfully.');
    }
    redirect('/admin/popups');
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row = find($table, $delId);
    if ($row) {
        if (!empty($row['image'])) delete_upload($row['image']);
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'popups', 'Deleted popup #' . $delId);
        set_flash('success', 'Popup deleted.');
    }
    redirect('/admin/popups');
}

/* -------------------------------------------------------------- FORM (create/edit) */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Popup not found.');
        redirect('/admin/popups');
    }
    $page_title = $action === 'edit' ? 'Edit Popup' : 'Add Popup';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Popups / <?= $action === 'edit' ? 'Edit' : 'Create' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('popups')) ?>">← Back to list</a>
    </div>

    <div class="panel">
        <form class="admin-form panel-body" method="post" enctype="multipart/form-data" action="<?= e(admin_url('popups')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="_do" value="save">
            <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

            <div class="form-group">
                <label class="form-label">Title <span class="req">*</span></label>
                <input class="form-control" name="title" required maxlength="191" value="<?= e($row['title'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Content</label>
                <textarea class="form-textarea" name="content" style="min-height:140px;"><?= e($row['content'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Image</label>
                <input class="form-control" type="file" name="image" accept="image/*" data-preview="#imgPreview">
                <img id="imgPreview" class="img-preview" src="<?= e(!empty($row['image']) ? upload_url($row['image']) : asset('images/placeholder.svg')) ?>" alt="preview" style="<?= empty($row['image']) ? 'display:none;' : '' ?>">
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Button Text</label>
                    <input class="form-control" name="button_text" maxlength="64" value="<?= e($row['button_text'] ?? '') ?>" placeholder="Learn More">
                </div>
                <div class="form-group">
                    <label class="form-label">Button URL</label>
                    <input class="form-control" name="button_url" maxlength="255" value="<?= e($row['button_url'] ?? '') ?>" placeholder="/donate">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Delay (seconds)</label>
                    <input class="form-control" type="number" name="delay_seconds" min="0" value="<?= (int) ($row['delay_seconds'] ?? 3) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <div style="padding-top: var(--sp-3);">
                        <label class="checkbox"><input type="checkbox" name="status" value="1" <?= (!isset($row['status']) || !empty($row['status'])) ? 'checked' : '' ?>> Active (show this popup on the site)</label>
                    </div>
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Start Date</label>
                    <input class="form-control" type="date" name="start_date" value="<?= e($row['start_date'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">End Date</label>
                    <input class="form-control" type="date" name="end_date" value="<?= e($row['end_date'] ?? '') ?>">
                </div>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> Popup</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('popups')) ?>">Cancel</a>
            </div>
        </form>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Popup Manager';
$search = trim((string) get('q', ''));
$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= ' AND title LIKE :q';
    $params[':q'] = '%' . $search . '%';
}
$p = paginate("SELECT * FROM $table WHERE $where ORDER BY id DESC", $params, 12);

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Popup Manager</h1><span class="muted"><?= (int) $p['total'] ?> total</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('popups?action=create')) ?>">+ Add Popup</a>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('popups')) ?>">
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search popups…">
            </form>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Image</th><th>Title</th><th>Schedule</th><th>Delay</th><th>Status</th><th class="num">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td><img class="thumb" src="<?= e(!empty($r['image']) ? upload_url($r['image']) : asset('images/placeholder.svg')) ?>" alt=""></td>
                        <td>
                            <strong><?= e($r['title']) ?></strong><br>
                            <small class="text-muted"><?= e(excerpt($r['content'] ?? '', 12)) ?></small>
                        </td>
                        <td>
                            <small class="text-muted">
                                <?= e(!empty($r['start_date']) ? format_date($r['start_date'], 'd M Y') : '—') ?>
                                &rarr;
                                <?= e(!empty($r['end_date']) ? format_date($r['end_date'], 'd M Y') : '—') ?>
                            </small>
                        </td>
                        <td><?= (int) $r['delay_seconds'] ?>s</td>
                        <td><span class="pill <?= !empty($r['status']) ? 'pill-green' : 'pill-gray' ?>"><?= !empty($r['status']) ? 'Active' : 'Inactive' ?></span></td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('popups?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                                <form method="post" action="<?= e(admin_url('popups')) ?>" data-confirm="Delete this popup permanently?" class="inline-form">
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
            <div class="empty-state">
                            <div class="icon"><?= lucide('message-square') ?></div>
                            <?php if ($search !== ''): ?>
                                <p class="es-title">No popups match &ldquo;<?= e($search) ?>&rdquo;</p>
                                <p class="es-text">No popup has that title. Clear the search to see every one.</p>
                                <div class="es-actions">
                                    <a class="btn btn-secondary" href="<?= e(admin_url('popups')) ?>">Clear search</a>
                                    <a class="btn btn-primary" href="<?= e(admin_url('popups?action=create')) ?>"><?= lucide('plus') ?> Add your first popup</a>
                                </div>
                            <?php else: ?>
                                <p class="es-title">No popups yet</p>
                                <p class="es-text">A popup shows once per visitor after a delay you set. Use it for a campaign push or an urgent notice — sparingly.</p>
                                <div class="es-actions">
                                    <a class="btn btn-primary" href="<?= e(admin_url('popups?action=create')) ?>"><?= lucide('plus') ?> Add your first popup</a>
                                </div>
                            <?php endif; ?>
                        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
