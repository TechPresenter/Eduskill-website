<?php
/**
 * =============================================================================
 *  Admin — Awareness Calendar CRUD.
 *  Awareness days/weeks/months (title, dates, category, colour, recurring).
 *  Follows the standard admin CRUD pattern (see admin/programs.php):
 *    list + create + edit + delete, CSRF, validation, pagination,
 *    activity logging, flash + redirect. No slug, no image upload.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'awareness_calendar';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/* -------------------------------------------------------------- SAVE (create/update) */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);

    $errors = validate($_POST, [
        'title'      => 'required|max:191',
        'event_date' => 'required',
    ]);
    if ($errors) {
        set_flash('error', reset($errors));
        redirect('/admin/awareness-calendar?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    $data = [
        'title'        => clean(post('title')),
        'description'  => clean(post('description', '')),
        'event_date'   => post('event_date'),
        'end_date'     => post('end_date') ?: null,
        'category'     => clean(post('category', '')),
        'color'        => clean(post('color', '#063566')) ?: '#063566',
        'is_recurring' => post('is_recurring') ? 1 : 0,
        'status'       => post('status') ? 1 : 0,
    ];

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'awareness_calendar', 'Updated awareness date #' . $editId);
        set_flash('success', 'Awareness date updated successfully.');
    } else {
        $newId = db_insert($table, $data);
        log_activity('create', 'awareness_calendar', 'Created awareness date #' . $newId);
        set_flash('success', 'Awareness date created successfully.');
    }
    redirect('/admin/awareness-calendar');
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row = find($table, $delId);
    if ($row) {
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'awareness_calendar', 'Deleted awareness date #' . $delId);
        set_flash('success', 'Awareness date deleted.');
    }
    redirect('/admin/awareness-calendar');
}

/* -------------------------------------------------------------- FORM (create/edit) */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Awareness date not found.');
        redirect('/admin/awareness-calendar');
    }
    $page_title = $action === 'edit' ? 'Edit Awareness Date' : 'Add Awareness Date';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Awareness Calendar / <?= $action === 'edit' ? 'Edit' : 'Create' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('awareness-calendar')) ?>">← Back to list</a>
    </div>

    <div class="panel">
        <form class="admin-form panel-body" method="post" action="<?= e(admin_url('awareness-calendar')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="_do" value="save">
            <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

            <div class="form-group">
                <label class="form-label">Title <span class="req">*</span></label>
                <input class="form-control" name="title" required maxlength="191" value="<?= e($row['title'] ?? '') ?>" placeholder="e.g. World Health Day">
            </div>

            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-textarea" name="description" style="min-height:120px;"><?= e($row['description'] ?? '') ?></textarea>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Event Date <span class="req">*</span></label>
                    <input class="form-control" type="date" name="event_date" required value="<?= e($row['event_date'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">End Date</label>
                    <input class="form-control" type="date" name="end_date" value="<?= e($row['end_date'] ?? '') ?>">
                    <small class="form-hint">Leave blank for a single-day observance.</small>
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <input class="form-control" name="category" maxlength="64" value="<?= e($row['category'] ?? '') ?>" placeholder="e.g. Health, Environment">
                </div>
                <div class="form-group">
                    <label class="form-label">Accent Colour</label>
                    <input class="form-control" type="color" name="color" value="<?= e($row['color'] ?? '#063566') ?>" style="height:48px;">
                </div>
            </div>

            <label class="checkbox"><input type="checkbox" name="is_recurring" value="1" <?= (!isset($row['is_recurring']) || !empty($row['is_recurring'])) ? 'checked' : '' ?>> Recurring every year</label>
            <label class="checkbox"><input type="checkbox" name="status" value="1" <?= (!isset($row['status']) || !empty($row['status'])) ? 'checked' : '' ?>> Active (show on the calendar)</label>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> Awareness Date</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('awareness-calendar')) ?>">Cancel</a>
            </div>
        </form>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Awareness Calendar';
$search = trim((string) get('q', ''));
$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', title, category) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
$p = paginate("SELECT * FROM $table WHERE $where ORDER BY event_date ASC, id DESC", $params, 12);

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Awareness Calendar</h1><span class="muted"><?= (int) $p['total'] ?> total</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('awareness-calendar?action=create')) ?>">+ Add Awareness Date</a>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('awareness-calendar')) ?>">
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search title or category…">
            </form>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Title</th><th>Date</th><th>Category</th><th>Recurring</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td>
                            <span class="pill" style="background:<?= e($r['color'] ?: '#063566') ?>;color:#fff;">&nbsp;</span>
                            <strong><?= e($r['title']) ?></strong>
                        </td>
                        <td>
                            <?= e(format_date($r['event_date'], 'd M Y')) ?>
                            <?php if (!empty($r['end_date'])): ?>
                                <br><small class="text-muted">to <?= e(format_date($r['end_date'], 'd M Y')) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= !empty($r['category']) ? '<span class="pill pill-blue">' . e($r['category']) . '</span>' : '<span class="text-muted">—</span>' ?></td>
                        <td><span class="pill <?= !empty($r['is_recurring']) ? 'pill-amber' : 'pill-gray' ?>"><?= !empty($r['is_recurring']) ? 'Yearly' : 'One-time' ?></span></td>
                        <td><span class="pill <?= !empty($r['status']) ? 'pill-green' : 'pill-gray' ?>"><?= !empty($r['status']) ? 'Active' : 'Inactive' ?></span></td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('awareness-calendar?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                                <form method="post" action="<?= e(admin_url('awareness-calendar')) ?>" data-confirm="Delete this awareness date permanently?" style="display:inline;">
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
            <div class="empty-state"><div class="icon"><?= lucide('calendar-days') ?></div>No awareness dates yet. <a href="<?= e(admin_url('awareness-calendar?action=create')) ?>">Add your first awareness date</a>.</div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
