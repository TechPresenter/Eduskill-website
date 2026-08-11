<?php
/**
 * =============================================================================
 *  Admin — Social Media Links CRUD.
 *  Full list + create + edit + delete, following the programs.php exemplar:
 *  CSRF, validation, pagination, activity logging, flash + redirect.
 *  No slug and no uploads for this table — platform/url/icon/sort/status only.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'social_links';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/* Allowed platform values (kept in sync with the public footer icons). */
$platforms = ['facebook', 'twitter', 'instagram', 'linkedin', 'youtube', 'whatsapp'];

/* -------------------------------------------------------------- SAVE (create/update) */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);

    $errors = validate($_POST, [
        'platform' => 'required|max:64',
        'url'      => 'required|max:255',
        'icon'     => 'max:64',
    ]);

    $platform = clean(post('platform'));
    if (!$errors && !in_array($platform, $platforms, true)) {
        $errors['platform'] = 'Please choose a valid platform.';
    }

    $url = trim((string) post('url'));
    if (!$errors && !filter_var($url, FILTER_VALIDATE_URL)) {
        $errors['url'] = 'Please enter a valid URL (including https://).';
    }

    if ($errors) {
        set_flash('error', reset($errors));
        redirect('/admin/social-links?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    $data = [
        'platform'   => $platform,
        'url'        => $url,
        'icon'       => clean(post('icon', '')),
        'sort_order' => (int) post('sort_order', 0),
        'status'     => post('status') ? 1 : 0,
    ];

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'social_links', 'Updated social link #' . $editId);
        set_flash('success', 'Social link updated successfully.');
    } else {
        $newId = db_insert($table, $data);
        log_activity('create', 'social_links', 'Created social link #' . $newId);
        set_flash('success', 'Social link created successfully.');
    }
    redirect('/admin/social-links');
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row = find($table, $delId);
    if ($row) {
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'social_links', 'Deleted social link #' . $delId);
        set_flash('success', 'Social link deleted.');
    }
    redirect('/admin/social-links');
}

/* -------------------------------------------------------------- FORM (create/edit) */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Social link not found.');
        redirect('/admin/social-links');
    }
    $page_title = $action === 'edit' ? 'Edit Social Link' : 'Add Social Link';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Social Media Links / <?= $action === 'edit' ? 'Edit' : 'Create' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('social-links')) ?>">← Back to list</a>
    </div>

    <div class="panel">
        <form class="admin-form panel-body" method="post" action="<?= e(admin_url('social-links')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="_do" value="save">
            <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Platform <span class="req">*</span></label>
                    <select class="form-select" name="platform" required>
                        <?php $cur = $row['platform'] ?? ''; ?>
                        <?php if ($cur === ''): ?><option value="">— Select platform —</option><?php endif; ?>
                        <?php foreach ($platforms as $pf): ?>
                            <option value="<?= e($pf) ?>" <?= $cur === $pf ? 'selected' : '' ?>><?= e(ucfirst($pf)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Icon (glyph or text)</label>
                    <input class="form-control" name="icon" maxlength="64" value="<?= e($row['icon'] ?? '') ?>" placeholder="e.g. f, in, yt">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">URL <span class="req">*</span></label>
                <input class="form-control" type="url" name="url" required maxlength="255" value="<?= e($row['url'] ?? '') ?>" placeholder="https://facebook.com/yourpage">
            </div>

            <div class="form-group">
                <label class="form-label">Sort Order</label>
                <input class="form-control" type="number" name="sort_order" value="<?= (int) ($row['sort_order'] ?? 0) ?>">
            </div>

            <label class="checkbox"><input type="checkbox" name="status" value="1" <?= (!isset($row['status']) || !empty($row['status'])) ? 'checked' : '' ?>> Active (show this link on the site)</label>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> Link</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('social-links')) ?>">Cancel</a>
            </div>
        </form>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Social Media Links';
$search = trim((string) get('q', ''));
$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', platform, url) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
$p = paginate("SELECT * FROM $table WHERE $where ORDER BY sort_order ASC, id DESC", $params, 12);

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Social Media Links</h1><span class="muted"><?= (int) $p['total'] ?> total</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('social-links?action=create')) ?>">+ Add Link</a>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('social-links')) ?>">
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search social links…">
            </form>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Icon</th><th>Platform</th><th>URL</th><th>Status</th><th>Order</th><th class="num">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td><?= !empty($r['icon']) ? e($r['icon']) : '<span class="text-muted">—</span>' ?></td>
                        <td><strong><?= e(ucfirst($r['platform'])) ?></strong></td>
                        <td><a href="<?= e(safe_href($r['url'])) ?>" target="_blank" rel="noopener noreferrer"><?= e($r['url']) ?></a></td>
                        <td><span class="pill <?= !empty($r['status']) ? 'pill-green' : 'pill-gray' ?>"><?= !empty($r['status']) ? 'Active' : 'Inactive' ?></span></td>
                        <td><?= (int) $r['sort_order'] ?></td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('social-links?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                                <form method="post" action="<?= e(admin_url('social-links')) ?>" data-confirm="Delete this social link permanently?" class="inline-form">
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
                            <div class="icon"><?= lucide('link') ?></div>
                            <?php if ($search !== ''): ?>
                                <p class="es-title">No social links match &ldquo;<?= e($search) ?>&rdquo;</p>
                                <p class="es-text">No link has that platform name or URL. Clear the search to see every one.</p>
                                <div class="es-actions">
                                    <a class="btn btn-secondary" href="<?= e(admin_url('social-links')) ?>">Clear search</a>
                                    <a class="btn btn-primary" href="<?= e(admin_url('social-links?action=create')) ?>"><?= lucide('plus') ?> Add your first link</a>
                                </div>
                            <?php else: ?>
                                <p class="es-title">No social links yet</p>
                                <p class="es-text">These are the icons in the site footer and header. Each one needs a platform and a full https:// URL.</p>
                                <div class="es-actions">
                                    <a class="btn btn-primary" href="<?= e(admin_url('social-links?action=create')) ?>"><?= lucide('plus') ?> Add your first link</a>
                                </div>
                            <?php endif; ?>
                        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
