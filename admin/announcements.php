<?php
/**
 * =============================================================================
 *  Admin — Announcements CRUD  (the top announcement bar).
 *  list + create + edit + delete, CSRF, validation, pagination, activity log.
 *  No slug, no image upload — a lightweight text/colour/date module.
 *  Only ONE bar shows on the site at a time: the newest active row whose
 *  optional start/end window contains today (flagged "Live" in the list).
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'announcements';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/* -------------------------------------------------------------- SAVE (create/update) */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);

    $errors = validate($_POST, ['message' => 'required|max:500']);
    if ($errors) {
        set_flash('error', reset($errors));
        redirect('/admin/announcements?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    $data = [
        'message'    => clean(post('message')),
        'link_text'  => clean(post('link_text', '')),
        'link_url'   => clean(post('link_url', '')),
        'bg_color'   => clean(post('bg_color', '#111827')),
        'text_color' => clean(post('text_color', '#ffffff')),
        'start_date' => post('start_date') ?: null,
        'end_date'   => post('end_date') ?: null,
        'status'     => post('status') ? 1 : 0,
    ];

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'announcements', 'Updated announcement #' . $editId);
        set_flash('success', 'Announcement updated successfully.');
    } else {
        $newId = db_insert($table, $data);
        log_activity('create', 'announcements', 'Created announcement #' . $newId);
        set_flash('success', 'Announcement created successfully.');
    }
    redirect('/admin/announcements');
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row = find($table, $delId);
    if ($row) {
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'announcements', 'Deleted announcement #' . $delId);
        set_flash('success', 'Announcement deleted.');
    }
    redirect('/admin/announcements');
}

/* -------------------------------------------------------------- FORM (create/edit) */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Announcement not found.');
        redirect('/admin/announcements');
    }
    $page_title = $action === 'edit' ? 'Edit Announcement' : 'Add Announcement';
    $bg   = $row['bg_color']   ?? '#111827';
    $text = $row['text_color'] ?? '#ffffff';
    include __DIR__ . '/partials/head.php';
    ?>
    <style>
        /* Layout-only. The preview strip keeps inline background/colour because it
           PREVIEWS the two colours being edited. */
        .an-msg { min-height: 90px; }
        .admin-content .an-color { height: 48px; padding: var(--sp-1); cursor: pointer; }
        .an-preview { padding: var(--sp-3) var(--sp-4); border-radius: var(--r-sm); text-align: center; font-weight: 600; }
        .an-preview-link { text-decoration: underline; margin-left: var(--sp-1); }
    </style>

    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Announcement Bar / <?= $action === 'edit' ? 'Edit' : 'Create' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('announcements')) ?>">← Back to list</a>
    </div>

    <div class="panel">
        <form class="admin-form panel-body" method="post" action="<?= e(admin_url('announcements')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="_do" value="save">
            <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

            <div class="form-group">
                <label class="form-label">Message <span class="req">*</span></label>
                <textarea class="form-textarea an-msg" name="message" maxlength="500" required placeholder="Our Annual Charity Run is on 15 Sep — register today!"><?= e($row['message'] ?? '') ?></textarea>
                <small class="form-hint">Shown in the site-wide bar at the top of every page. Only the newest active announcement (within its date window) is displayed.</small>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Link Text</label>
                    <input class="form-control" name="link_text" maxlength="64" value="<?= e($row['link_text'] ?? '') ?>" placeholder="e.g. Register">
                </div>
                <div class="form-group">
                    <label class="form-label">Link URL</label>
                    <input class="form-control" name="link_url" maxlength="255" value="<?= e($row['link_url'] ?? '') ?>" placeholder="e.g. events or https://…">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Background Colour</label>
                    <input class="form-control an-color" type="color" name="bg_color" value="<?= e($bg) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Text Colour</label>
                    <input class="form-control an-color" type="color" name="text_color" value="<?= e($text) ?>">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Start Date</label>
                    <input class="form-control" type="date" name="start_date" value="<?= e($row['start_date'] ?? '') ?>">
                    <small class="form-hint">Optional — leave blank to show immediately.</small>
                </div>
                <div class="form-group">
                    <label class="form-label">End Date</label>
                    <input class="form-control" type="date" name="end_date" value="<?= e($row['end_date'] ?? '') ?>">
                    <small class="form-hint">Optional — leave blank to show indefinitely.</small>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Live Preview</label>
                <div class="an-preview" style="background:<?= e($bg) ?>;color:<?= e($text) ?>;">
                    <?= e($row['message'] ?? 'Your announcement preview appears here') ?>
                    <?php if (!empty($row['link_text'])): ?><span class="an-preview-link"><?= e($row['link_text']) ?></span><?php endif; ?>
                </div>
            </div>

            <label class="checkbox"><input type="checkbox" name="status" value="1" <?= (($row['status'] ?? 1)) ? 'checked' : '' ?>> Active (eligible to show in the bar)</label>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> Announcement</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('announcements')) ?>">Cancel</a>
            </div>
        </form>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Announcement Bar';
$search = trim((string) get('q', ''));
$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= ' AND message LIKE :q';
    $params[':q'] = '%' . $search . '%';
}
$p = paginate("SELECT * FROM $table WHERE $where ORDER BY id DESC", $params, 12);

// The single announcement currently live on the public site (if any).
$today  = date('Y-m-d');
$liveId = (int) db_value(
    "SELECT id FROM $table
      WHERE status = 1
        AND (start_date IS NULL OR start_date <= :t1)
        AND (end_date   IS NULL OR end_date   >= :t2)
      ORDER BY id DESC LIMIT 1",
    [':t1' => $today, ':t2' => $today]
);

include __DIR__ . '/partials/head.php';
?>
<style>
    /* The swatch keeps an inline background — it previews the stored colour. */
    .an-swatch { display: inline-block; width: 20px; height: 20px; border-radius: var(--r-sm);
        vertical-align: middle; border: 1px solid var(--border); }
</style>

<div class="admin-page-head">
    <div><h1>Announcement Bar</h1><span class="muted"><?= (int) $p['total'] ?> total</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('announcements?action=create')) ?>">+ Add Announcement</a>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('announcements')) ?>">
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search announcements…">
            </form>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Message</th><th>Link</th><th>Window</th><th>Colours</th><th>Status</th><th class="num">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <?php $isLive = ((int) $r['id'] === $liveId); ?>
                    <tr>
                        <td>
                            <strong><?= e(excerpt($r['message'], 16)) ?></strong>
                            <?php if ($isLive): ?><br><span class="pill pill-green">Live now</span><?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($r['link_text']) || !empty($r['link_url'])): ?>
                                <?= e($r['link_text'] ?: '—') ?><br><small class="text-muted"><?= e(excerpt($r['link_url'] ?? '', 8)) ?></small>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small class="text-muted">
                                <?= e($r['start_date'] ? format_date($r['start_date'], 'd M Y') : 'Anytime') ?>
                                &rarr;
                                <?= e($r['end_date'] ? format_date($r['end_date'], 'd M Y') : 'No end') ?>
                            </small>
                        </td>
                        <td>
                            <span class="an-swatch" style="background:<?= e($r['bg_color'] ?? '#111827') ?>" title="<?= e($r['bg_color'] ?? '') ?>"></span>
                            <span class="an-swatch" style="background:<?= e($r['text_color'] ?? '#ffffff') ?>" title="<?= e($r['text_color'] ?? '') ?>"></span>
                        </td>
                        <td><span class="pill <?= $r['status'] ? 'pill-green' : 'pill-gray' ?>"><?= $r['status'] ? 'Active' : 'Inactive' ?></span></td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('announcements?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                                <form method="post" action="<?= e(admin_url('announcements')) ?>" data-confirm="Delete this announcement permanently?">
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
                            <div class="icon"><?= lucide('megaphone') ?></div>
                            <?php if ($search !== ''): ?>
                                <p class="es-title">No announcements match &ldquo;<?= e($search) ?>&rdquo;</p>
                                <p class="es-text">No announcement has that message. Clear the search to see every one.</p>
                                <div class="es-actions">
                                    <a class="btn btn-secondary" href="<?= e(admin_url('announcements')) ?>">Clear search</a>
                                    <a class="btn btn-primary" href="<?= e(admin_url('announcements?action=create')) ?>"><?= lucide('plus') ?> Add your first announcement</a>
                                </div>
                            <?php else: ?>
                                <p class="es-title">No announcements yet</p>
                                <p class="es-text">An announcement is the strip across the top of the public site. Give it a start and end date and it shows only inside that window.</p>
                                <div class="es-actions">
                                    <a class="btn btn-primary" href="<?= e(admin_url('announcements?action=create')) ?>"><?= lucide('plus') ?> Add your first announcement</a>
                                </div>
                            <?php endif; ?>
                        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
