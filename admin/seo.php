<?php
/**
 * =============================================================================
 *  Admin — SEO Manager CRUD.
 *  Per-page SEO overrides stored in `seo_meta`, keyed on a unique `page_key`
 *  (e.g. 'home', 'about', 'blog:my-slug'). Follows the standard admin CRUD
 *  pattern: list + create + edit + delete, CSRF, validation, pagination,
 *  OG image upload (with old-file cleanup) and activity logging.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'seo_meta';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/* -------------------------------------------------------------- SAVE (create/update) */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);

    $errors = validate($_POST, [
        'page_key'         => 'required|max:191',
        'meta_title'       => 'max:191',
        'meta_description' => 'max:320',
        'meta_keywords'    => 'max:320',
        'og_title'         => 'max:191',
        'og_description'   => 'max:320',
        'canonical'        => 'max:255',
        'robots'           => 'max:64',
    ]);
    if ($errors) {
        set_flash('error', reset($errors));
        redirect('/admin/seo?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    // page_key is used like a slug: normalise (lower + trim) and enforce uniqueness with a COUNT check.
    $pageKey = strtolower(clean(post('page_key')));

    $dupWhere  = 'page_key = :pk';
    $dupParams = [':pk' => $pageKey];
    if ($editId) {
        $dupWhere .= ' AND id != :id';
        $dupParams[':id'] = $editId;
    }
    if (db_count($table, $dupWhere, $dupParams) > 0) {
        set_flash('error', 'That page key is already in use. Choose a unique key.');
        redirect('/admin/seo?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    // Optional Schema.org JSON-LD must be valid JSON when provided.
    $schemaJson = trim((string) post('schema_json', ''));
    if ($schemaJson !== '') {
        json_decode($schemaJson);
        if (json_last_error() !== JSON_ERROR_NONE) {
            set_flash('error', 'Schema JSON is not valid JSON: ' . json_last_error_msg());
            redirect('/admin/seo?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
        }
    }

    $data = [
        'page_key'         => $pageKey,
        'meta_title'       => clean(post('meta_title', '')),
        'meta_description' => clean(post('meta_description', '')),
        'meta_keywords'    => clean(post('meta_keywords', '')),
        'og_title'         => clean(post('og_title', '')),
        'og_description'   => clean(post('og_description', '')),
        'canonical'        => clean(post('canonical', '')),
        'robots'           => clean(post('robots', '')) ?: 'index,follow',
        'schema_json'      => $schemaJson, // raw JSON, preserved as-is
    ];

    // Optional OG image upload (replaces + deletes the old one).
    if (!empty($_FILES['og_image']['name'])) {
        $up = upload_image($_FILES['og_image'], 'images');
        if (!$up['success']) {
            set_flash('error', $up['error']);
            redirect('/admin/seo?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
        }
        $data['og_image'] = $up['path'];
        if ($editId) {
            $old = find($table, $editId);
            if ($old && !empty($old['og_image'])) delete_upload($old['og_image']);
        }
    }

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'seo', 'Updated SEO meta #' . $editId . ' (' . $pageKey . ')');
        set_flash('success', 'SEO entry updated successfully.');
    } else {
        $newId = db_insert($table, $data);
        log_activity('create', 'seo', 'Created SEO meta #' . $newId . ' (' . $pageKey . ')');
        set_flash('success', 'SEO entry created successfully.');
    }
    redirect('/admin/seo');
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row = find($table, $delId);
    if ($row) {
        if (!empty($row['og_image'])) delete_upload($row['og_image']);
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'seo', 'Deleted SEO meta #' . $delId);
        set_flash('success', 'SEO entry deleted.');
    }
    redirect('/admin/seo');
}

/* -------------------------------------------------------------- FORM (create/edit) */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'SEO entry not found.');
        redirect('/admin/seo');
    }
    $page_title = $action === 'edit' ? 'Edit SEO Entry' : 'Add SEO Entry';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">SEO Manager / <?= $action === 'edit' ? 'Edit' : 'Create' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('seo')) ?>">← Back to list</a>
    </div>

    <div class="panel">
        <form class="admin-form panel-body" method="post" enctype="multipart/form-data" action="<?= e(admin_url('seo')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="_do" value="save">
            <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

            <div class="form-group">
                <label class="form-label">Page Key <span class="req">*</span></label>
                <input class="form-control" name="page_key" required value="<?= e($row['page_key'] ?? '') ?>" placeholder="home, about, blog:my-slug">
                <span class="form-hint">Unique identifier that maps this record to a page (e.g. <code>home</code>, <code>about</code>, <code>blog:my-slug</code>).</span>
            </div>

            <div class="form-group">
                <label class="form-label">Meta Title</label>
                <input class="form-control" name="meta_title" maxlength="191" value="<?= e($row['meta_title'] ?? '') ?>">
                <span class="form-hint">Ideally 50–60 characters.</span>
            </div>

            <div class="form-group">
                <label class="form-label">Meta Description</label>
                <textarea class="form-textarea" name="meta_description" maxlength="320" style="min-height:90px;"><?= e($row['meta_description'] ?? '') ?></textarea>
                <span class="form-hint">Ideally 150–160 characters.</span>
            </div>

            <div class="form-group">
                <label class="form-label">Meta Keywords</label>
                <input class="form-control" name="meta_keywords" maxlength="320" value="<?= e($row['meta_keywords'] ?? '') ?>" placeholder="comma, separated, keywords">
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">OG Title</label>
                    <input class="form-control" name="og_title" maxlength="191" value="<?= e($row['og_title'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Robots</label>
                    <input class="form-control" name="robots" maxlength="64" value="<?= e($row['robots'] ?? 'index,follow') ?>" placeholder="index,follow">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">OG Description</label>
                <textarea class="form-textarea" name="og_description" maxlength="320" style="min-height:80px;"><?= e($row['og_description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Canonical URL</label>
                <input class="form-control" type="url" name="canonical" maxlength="255" value="<?= e($row['canonical'] ?? '') ?>" placeholder="https://example.org/page">
            </div>

            <div class="form-group">
                <label class="form-label">OG Image</label>
                <input class="form-control" type="file" name="og_image" accept="image/*" data-preview="#imgPreview">
                <img id="imgPreview" class="img-preview" src="<?= e(!empty($row['og_image']) ? upload_url($row['og_image']) : asset('images/placeholder.svg')) ?>" alt="preview" style="<?= empty($row['og_image']) ? 'display:none;' : '' ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Schema JSON (JSON-LD)</label>
                <textarea class="form-textarea" name="schema_json" style="min-height:160px;font-family:monospace;" placeholder='{"@context":"https://schema.org", ...}'><?= e($row['schema_json'] ?? '') ?></textarea>
                <span class="form-hint">Optional structured data. Must be valid JSON if provided.</span>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> SEO Entry</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('seo')) ?>">Cancel</a>
            </div>
        </form>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'SEO Manager';
$search = trim((string) get('q', ''));
$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', page_key, meta_title) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
$p = paginate("SELECT * FROM $table WHERE $where ORDER BY page_key ASC, id DESC", $params, 12);

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>SEO Manager</h1><span class="muted"><?= (int) $p['total'] ?> total</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('seo?action=create')) ?>">+ Add SEO Entry</a>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('seo')) ?>">
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search by page key or title…">
            </form>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>OG Image</th><th>Page Key</th><th>Meta Title</th><th>Robots</th><th>Schema</th><th style="text-align:right;">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td><img class="thumb" src="<?= e(!empty($r['og_image']) ? upload_url($r['og_image']) : asset('images/placeholder.svg')) ?>" alt=""></td>
                        <td><strong><?= e($r['page_key']) ?></strong></td>
                        <td>
                            <?= e($r['meta_title'] ?: '—') ?><br>
                            <small class="text-muted"><?= e(excerpt($r['meta_description'] ?? '', 12)) ?></small>
                        </td>
                        <td><span class="pill <?= (stripos((string) $r['robots'], 'noindex') !== false) ? 'pill-amber' : 'pill-green' ?>"><?= e($r['robots'] ?: 'index,follow') ?></span></td>
                        <td><?= !empty($r['schema_json']) ? '<span class="pill pill-blue">JSON-LD</span>' : '—' ?></td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('seo?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                                <form method="post" action="<?= e(admin_url('seo')) ?>" data-confirm="Delete this SEO entry permanently?" style="display:inline;">
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
            <div class="empty-state"><div class="icon"><?= lucide('search') ?></div>No SEO entries yet. <a href="<?= e(admin_url('seo?action=create')) ?>">Add your first SEO entry</a>.</div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
