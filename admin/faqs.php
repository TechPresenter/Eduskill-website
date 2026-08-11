<?php
/**
 * =============================================================================
 *  Admin — FAQs CRUD.
 *  list + create + edit + delete, CSRF, validation, pagination, activity
 *  logging. Follows the standard admin module pattern (see admin/programs.php).
 *  No file uploads and no slug for this module.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'faqs';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/* -------------------------------------------------------------- SAVE (create/update) */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);

    $errors = validate($_POST, [
        'question' => 'required|max:255',
        'answer'   => 'required',
    ]);
    if ($errors) {
        set_flash('error', reset($errors));
        redirect('/admin/faqs?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    $data = [
        'question'   => clean(post('question')),
        'answer'     => clean(post('answer')),
        'category'   => clean(post('category', '')) ?: 'general',
        'sort_order' => (int) post('sort_order', 0),
        'status'     => post('status') ? 1 : 0,
    ];

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'faqs', 'Updated FAQ #' . $editId);
        set_flash('success', 'FAQ updated successfully.');
    } else {
        $newId = db_insert($table, $data);
        log_activity('create', 'faqs', 'Created FAQ #' . $newId);
        set_flash('success', 'FAQ created successfully.');
    }
    redirect('/admin/faqs');
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row = find($table, $delId);
    if ($row) {
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'faqs', 'Deleted FAQ #' . $delId);
        set_flash('success', 'FAQ deleted.');
    }
    redirect('/admin/faqs');
}

/* -------------------------------------------------------------- FORM (create/edit) */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'FAQ not found.');
        redirect('/admin/faqs');
    }
    $page_title = $action === 'edit' ? 'Edit FAQ' : 'Add FAQ';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">FAQs / <?= $action === 'edit' ? 'Edit' : 'Create' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('faqs')) ?>">← Back to list</a>
    </div>

    <div class="panel">
        <form class="admin-form panel-body" method="post" action="<?= e(admin_url('faqs')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="_do" value="save">
            <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

            <div class="form-group">
                <label class="form-label">Question <span class="req">*</span></label>
                <input class="form-control" name="question" required maxlength="255" value="<?= e($row['question'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Answer <span class="req">*</span></label>
                <textarea class="form-textarea" name="answer" data-wysiwyg required style="min-height:160px;"><?= e($row['answer'] ?? '') ?></textarea>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <input class="form-control" name="category" maxlength="64" value="<?= e($row['category'] ?? 'general') ?>" placeholder="general">
                </div>
                <div class="form-group">
                    <label class="form-label">Sort Order</label>
                    <input class="form-control" type="number" name="sort_order" value="<?= (int) ($row['sort_order'] ?? 0) ?>">
                </div>
            </div>

            <label class="checkbox"><input type="checkbox" name="status" value="1" <?= (!array_key_exists('status', (array) $row) || !empty($row['status'])) ? 'checked' : '' ?>> Active (visible on the site)</label>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> FAQ</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('faqs')) ?>">Cancel</a>
            </div>
        </form>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'FAQs';
$search = trim((string) get('q', ''));
$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', question, answer, category) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
$p = paginate("SELECT * FROM $table WHERE $where ORDER BY sort_order ASC, id DESC", $params, 12);

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>FAQs</h1><span class="muted"><?= (int) $p['total'] ?> total</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('faqs?action=create')) ?>">+ Add FAQ</a>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('faqs')) ?>">
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search FAQs…">
            </form>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Question</th><th>Category</th><th>Status</th><th>Order</th><th class="num">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td>
                            <strong><?= e($r['question']) ?></strong><br>
                            <small class="text-muted"><?= e(excerpt($r['answer'] ?? '', 16)) ?></small>
                        </td>
                        <td><span class="pill pill-tag"><?= e($r['category']) ?></span></td>
                        <td><span class="pill <?= !empty($r['status']) ? 'pill-green' : 'pill-gray' ?>"><?= !empty($r['status']) ? 'Active' : 'Inactive' ?></span></td>
                        <td><?= (int) $r['sort_order'] ?></td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('faqs?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                                <form method="post" action="<?= e(admin_url('faqs')) ?>" data-confirm="Delete this FAQ permanently?" class="inline-form">
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
                            <div class="icon"><?= lucide('circle-help') ?></div>
                            <?php if ($search !== ''): ?>
                                <p class="es-title">No FAQs match &ldquo;<?= e($search) ?>&rdquo;</p>
                                <p class="es-text">No question or answer matches. Clear the search to see every one.</p>
                                <div class="es-actions">
                                    <a class="btn btn-secondary" href="<?= e(admin_url('faqs')) ?>">Clear search</a>
                                    <a class="btn btn-primary" href="<?= e(admin_url('faqs?action=create')) ?>"><?= lucide('plus') ?> Add your first FAQ</a>
                                </div>
                            <?php else: ?>
                                <p class="es-title">No FAQs yet</p>
                                <p class="es-text">FAQs are grouped by category on the public help page, and answer the questions your inbox keeps repeating.</p>
                                <div class="es-actions">
                                    <a class="btn btn-primary" href="<?= e(admin_url('faqs?action=create')) ?>"><?= lucide('plus') ?> Add your first FAQ</a>
                                </div>
                            <?php endif; ?>
                        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
