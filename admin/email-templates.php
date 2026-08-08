<?php
/**
 * =============================================================================
 *  Admin — Email Templates CRUD (transactional email bodies used by mailer).
 *  Full list + create + edit + delete, following the programs.php exemplar:
 *  CSRF, validation, pagination, slug generation, activity logging,
 *  flash + redirect. No file uploads for this module.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'email_templates';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/* -------------------------------------------------------------- SAVE (create/update) */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);

    $errors = validate($_POST, [
        'name'      => 'required|max:128',
        'subject'   => 'required|max:191',
        'body'      => 'required',
        'variables' => 'max:500',
    ]);
    if ($errors) {
        set_flash('error', reset($errors));
        redirect('/admin/email-templates?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    $data = [
        'name'      => clean(post('name')),
        'slug'      => unique_slug($table, post('slug') ?: post('name'), $editId ?: null),
        'subject'   => clean(post('subject')),
        'body'      => post('body', ''), // HTML email body allowed
        'variables' => clean(post('variables', '')),
        'status'    => post('status') ? 1 : 0,
    ];

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'email_templates', 'Updated email template #' . $editId);
        set_flash('success', 'Email template updated successfully.');
    } else {
        $newId = db_insert($table, $data);
        log_activity('create', 'email_templates', 'Created email template #' . $newId);
        set_flash('success', 'Email template created successfully.');
    }
    redirect('/admin/email-templates');
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row = find($table, $delId);
    if ($row) {
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'email_templates', 'Deleted email template #' . $delId);
        set_flash('success', 'Email template deleted.');
    }
    redirect('/admin/email-templates');
}

/* -------------------------------------------------------------- FORM (create/edit) */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Email template not found.');
        redirect('/admin/email-templates');
    }
    $page_title = $action === 'edit' ? 'Edit Email Template' : 'Add Email Template';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Email Templates / <?= $action === 'edit' ? 'Edit' : 'Create' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('email-templates')) ?>">← Back to list</a>
    </div>

    <div class="panel">
        <form class="admin-form panel-body" method="post" action="<?= e(admin_url('email-templates')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="_do" value="save">
            <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Name <span class="req">*</span></label>
                    <input class="form-control" name="name" data-slug-source required maxlength="128" value="<?= e($row['name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Slug</label>
                    <input class="form-control" name="slug" data-slug-target maxlength="128" value="<?= e($row['slug'] ?? '') ?>" placeholder="auto-generated">
                    <small class="form-hint">Used by <code>send_template()</code> to look this template up.</small>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Subject <span class="req">*</span></label>
                <input class="form-control" name="subject" required maxlength="191" value="<?= e($row['subject'] ?? '') ?>" placeholder="e.g. Welcome to {{site_name}}, {{name}}!">
            </div>

            <div class="form-group">
                <label class="form-label">Body <span class="req">*</span></label>
                <textarea class="form-textarea" name="body" required style="min-height:320px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;"><?= e($row['body'] ?? '') ?></textarea>
                <small class="form-hint">HTML is allowed. Insert placeholders like <code>{{name}}</code> that will be replaced when the email is sent.</small>
            </div>

            <div class="form-group">
                <label class="form-label">Variables</label>
                <input class="form-control" name="variables" maxlength="500" value="<?= e($row['variables'] ?? '') ?>" placeholder="{{name}}, {{url}}, {{site_name}}">
                <small class="form-hint">Comma-separated list of placeholders available in this template (for reference).</small>
            </div>

            <label class="checkbox"><input type="checkbox" name="status" value="1" <?= (!isset($row['status']) || !empty($row['status'])) ? 'checked' : '' ?>> Active (allow this template to be sent)</label>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> Template</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('email-templates')) ?>">Cancel</a>
            </div>
        </form>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Email Templates';
$search = trim((string) get('q', ''));
$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', name, slug, subject) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
$p = paginate("SELECT * FROM $table WHERE $where ORDER BY name ASC, id DESC", $params, 12);

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Email Templates</h1><span class="muted"><?= (int) $p['total'] ?> total</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('email-templates?action=create')) ?>">+ Add Template</a>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('email-templates')) ?>">
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search templates…">
            </form>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Name</th><th>Slug</th><th>Subject</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td>
                            <strong><?= e($r['name']) ?></strong>
                            <?php if (!empty($r['variables'])): ?>
                                <br><small class="text-muted"><?= e(excerpt($r['variables'], 12)) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><code><?= e($r['slug']) ?></code></td>
                        <td><?= e(excerpt($r['subject'] ?? '', 12)) ?></td>
                        <td><span class="pill <?= !empty($r['status']) ? 'pill-green' : 'pill-gray' ?>"><?= !empty($r['status']) ? 'Active' : 'Inactive' ?></span></td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('email-templates?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pen-line') ?></a>
                                <form method="post" action="<?= e(admin_url('email-templates')) ?>" data-confirm="Delete this email template permanently?" style="display:inline;">
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
            <div class="empty-state"><div class="icon"><?= lucide('mail') ?></div>No email templates yet. <a href="<?= e(admin_url('email-templates?action=create')) ?>">Add your first template</a>.</div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
