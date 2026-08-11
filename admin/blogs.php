<?php
/**
 * =============================================================================
 *  Admin — Blogs CRUD.
 *  Full list + create + edit + delete, following the Programs exemplar:
 *    CSRF, validation, pagination, featured-image upload (with old-file
 *    cleanup), slug generation, activity logging. Also manages the
 *    many-to-many tag relationship (blog_tags + blog_tag_map).
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'blogs';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/**
 * Sync a comma-separated tag string to blog_tags + blog_tag_map for one blog.
 * Existing tags are reused (matched by slug); new ones are inserted. The map
 * is fully rebuilt so removed tags are unlinked.
 *
 * @param int    $blogId  The blog whose tags are being rebuilt.
 * @param string $raw     Raw comma-separated tag input.
 * @return void
 */
function blogs_sync_tags(int $blogId, string $raw): void
{
    $tagIds = [];
    foreach (explode(',', $raw) as $name) {
        $name = clean($name);
        if ($name === '') {
            continue;
        }
        $slug = slugify($name);
        if ($slug === '') {
            continue;
        }
        $existing = find_by('blog_tags', 'slug', $slug);
        if ($existing) {
            $tagIds[$existing['id']] = (int) $existing['id'];
        } else {
            $newId = db_insert('blog_tags', [
                'name' => $name,
                'slug' => unique_slug('blog_tags', $slug),
            ]);
            $tagIds[$newId] = (int) $newId;
        }
    }

    // Rebuild the map for this blog.
    db_delete('blog_tag_map', 'blog_id = :b', [':b' => $blogId]);
    foreach ($tagIds as $tagId) {
        db_insert('blog_tag_map', ['blog_id' => $blogId, 'tag_id' => $tagId]);
    }
}

/* -------------------------------------------------------------- SAVE (create/update) */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);

    $errors = validate($_POST, ['title' => 'required|max:191']);
    if ($errors) {
        set_flash('error', reset($errors));
        redirect('/admin/blogs?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    // Normalise published_at (datetime-local -> DATETIME). Auto-stamp when
    // publishing without an explicit date.
    $publishedRaw = trim((string) post('published_at', ''));
    $publishedAt  = null;
    if ($publishedRaw !== '') {
        $ts = strtotime($publishedRaw);
        if ($ts !== false) {
            $publishedAt = date('Y-m-d H:i:s', $ts);
        }
    }
    $status = in_array(post('status'), ['draft', 'published', 'archived'], true) ? post('status') : 'draft';
    if ($status === 'published' && $publishedAt === null) {
        $publishedAt = date('Y-m-d H:i:s');
    }

    $categoryId = (int) post('category_id', 0);
    $content    = post('content', '');
    $wordCount  = str_word_count(strip_tags((string) $content));

    $data = [
        'category_id'      => $categoryId ?: null,
        'title'            => clean(post('title')),
        'slug'             => unique_slug($table, post('slug') ?: post('title'), $editId ?: null),
        'excerpt'          => clean(post('excerpt', '')),
        'content'          => $content, // rich text (sanitised on public render)
        'meta_title'       => clean(post('meta_title', '')) ?: null,
        'meta_description' => clean(post('meta_description', '')) ?: null,
        'canonical_url'    => clean(post('canonical_url', '')) ?: null,
        'is_featured'      => post('is_featured') ? 1 : 0,
        'is_breaking'      => post('is_breaking') ? 1 : 0,
        'is_sticky'        => post('is_sticky') ? 1 : 0,
        'reading_time'     => max(1, (int) ceil($wordCount / 200)),
        'status'           => $status,
        'published_at'     => $publishedAt,
    ];

    // Author (optional override; defaults to the current admin on create).
    $authorId = (int) post('author_id', 0);
    if ($authorId && find('users', $authorId)) {
        $data['author_id'] = $authorId;
    }

    // Optional featured image upload (replaces + deletes the old one).
    if (!empty($_FILES['featured_image']['name'])) {
        $up = upload_image($_FILES['featured_image'], 'blogs');
        if (!$up['success']) {
            set_flash('error', $up['error']);
            redirect('/admin/blogs?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
        }
        $data['featured_image'] = $up['path'];
        if ($editId) {
            $old = find($table, $editId);
            if ($old && !empty($old['featured_image'])) delete_upload($old['featured_image']);
        }
    }

    // Optional OG image upload (for social share cards).
    if (!empty($_FILES['og_image']['name'])) {
        $upOg = upload_image($_FILES['og_image'], 'blogs');
        if ($upOg['success']) {
            $data['og_image'] = $upOg['path'];
            if ($editId) {
                $oldOg = find($table, $editId);
                if ($oldOg && !empty($oldOg['og_image'])) delete_upload($oldOg['og_image']);
            }
        }
    }

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        blogs_sync_tags($editId, (string) post('tags', ''));
        log_activity('update', 'blogs', 'Updated blog #' . $editId);
        set_flash('success', 'Blog post updated successfully.');
    } else {
        if (empty($data['author_id'])) $data['author_id'] = current_user_id();
        $newId = db_insert($table, $data);
        blogs_sync_tags($newId, (string) post('tags', ''));
        log_activity('create', 'blogs', 'Created blog #' . $newId);
        set_flash('success', 'Blog post created successfully.');
    }
    redirect('/admin/blogs');
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row = find($table, $delId);
    if ($row) {
        if (!empty($row['featured_image'])) delete_upload($row['featured_image']);
        db_delete('blog_tag_map', 'blog_id = :b', [':b' => $delId]);
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'blogs', 'Deleted blog #' . $delId);
        set_flash('success', 'Blog post deleted.');
    }
    redirect('/admin/blogs');
}

/* -------------------------------------------------------------- FORM (create/edit) */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Blog post not found.');
        redirect('/admin/blogs');
    }

    // Hierarchical category list (parents, then indented children).
    $catRows = db_all("SELECT id, name, parent_id FROM blog_categories WHERE status = 1 ORDER BY sort_order ASC, name ASC");
    $catByParent = [];
    foreach ($catRows as $c) { $catByParent[(int) ($c['parent_id'] ?? 0)][] = $c; }
    $categories = [];
    $walkCats = function ($parent, $depth) use (&$walkCats, &$categories, $catByParent) {
        foreach ($catByParent[$parent] ?? [] as $c) {
            $categories[] = ['id' => (int) $c['id'], 'name' => str_repeat('— ', $depth) . $c['name']];
            $walkCats((int) $c['id'], $depth + 1);
        }
    };
    $walkCats(0, 0);
    $authors = db_all("SELECT id, name FROM users ORDER BY name");

    // Existing tags for this blog, as a comma-separated string for the input.
    $tagString = '';
    if (!empty($row['id'])) {
        $tagRows = db_all(
            "SELECT t.name FROM blog_tags t
             JOIN blog_tag_map m ON m.tag_id = t.id
             WHERE m.blog_id = :b ORDER BY t.name ASC",
            [':b' => (int) $row['id']]
        );
        $tagString = implode(', ', array_column($tagRows, 'name'));
    }

    // published_at value for a datetime-local input.
    $publishedValue = '';
    if (!empty($row['published_at'])) {
        $ts = strtotime((string) $row['published_at']);
        if ($ts !== false) $publishedValue = date('Y-m-d\TH:i', $ts);
    }

    $page_title = $action === 'edit' ? 'Edit Blog Post' : 'Add Blog Post';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Blogs / <?= $action === 'edit' ? 'Edit' : 'Create' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('blogs')) ?>">← Back to list</a>
    </div>

    <div class="panel">
        <form class="admin-form panel-body" method="post" enctype="multipart/form-data" action="<?= e(admin_url('blogs')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="_do" value="save">
            <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Title <span class="req">*</span></label>
                    <input class="form-control" name="title" data-slug-source required value="<?= e($row['title'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Slug</label>
                    <input class="form-control" name="slug" data-slug-target value="<?= e($row['slug'] ?? '') ?>" placeholder="auto-generated">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select class="form-select" name="category_id">
                        <option value="">— None —</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int) $cat['id'] ?>" <?= ((int) ($row['category_id'] ?? 0) === (int) $cat['id']) ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <?php foreach (['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'] as $val => $label): ?>
                            <option value="<?= e($val) ?>" <?= (($row['status'] ?? 'draft') === $val) ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Excerpt</label>
                <textarea class="form-textarea" name="excerpt" style="min-height:80px;" maxlength="500"><?= e($row['excerpt'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Content</label>
                <textarea class="form-textarea" name="content" data-wysiwyg style="min-height:260px;"><?= e($row['content'] ?? '') ?></textarea>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Publish Date</label>
                    <input class="form-control" type="datetime-local" name="published_at" value="<?= e($publishedValue) ?>">
                    <small class="form-hint">Leave blank to auto-stamp when status is Published.</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Tags</label>
                    <input class="form-control" name="tags" value="<?= e($tagString) ?>" placeholder="e.g. health, education, community">
                    <small class="form-hint">Comma-separated. New tags are created automatically.</small>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Featured Image</label>
                <input class="form-control" type="file" name="featured_image" accept="image/*" data-preview="#imgPreview">
                <img id="imgPreview" class="img-preview" src="<?= e(!empty($row['featured_image']) ? upload_url($row['featured_image']) : asset('images/placeholder.svg')) ?>" alt="preview" style="<?= empty($row['featured_image']) ? 'display:none;' : '' ?>">
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Author</label>
                    <select class="form-select" name="author_id">
                        <?php $curAuthor = (int) ($row['author_id'] ?? current_user_id()); foreach ($authors as $a): ?>
                            <option value="<?= (int) $a['id'] ?>" <?= $curAuthor === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Flags</label>
                    <div class="flex flex-wrap gap-3" style="padding-top: var(--sp-2);">
                        <label class="checkbox"><input type="checkbox" name="is_featured" value="1" <?= !empty($row['is_featured']) ? 'checked' : '' ?>> <?= lucide('star') ?> Featured</label>
                        <label class="checkbox"><input type="checkbox" name="is_breaking" value="1" <?= !empty($row['is_breaking']) ? 'checked' : '' ?>> <?= lucide('zap') ?> Breaking</label>
                        <label class="checkbox"><input type="checkbox" name="is_sticky" value="1" <?= !empty($row['is_sticky']) ? 'checked' : '' ?>> <?= lucide('pin') ?> Sticky</label>
                    </div>
                </div>
            </div>

            <details class="panel" style="margin: var(--sp-1) 0 var(--sp-4);"<?= (!empty($row['meta_title']) || !empty($row['meta_description']) || !empty($row['canonical_url']) || !empty($row['og_image'])) ? ' open' : '' ?>>
                <summary style="cursor:pointer;list-style:none;padding: var(--sp-3) var(--sp-4);">
                    <h2 class="panel-title"><?= lucide('search') ?> SEO &amp; Social meta</h2>
                    <span class="text-muted">(optional — overrides the auto values)</span>
                </summary>
                <div class="panel-body" style="padding-top: var(--sp-1);">
                    <div class="form-group"><label class="form-label">Meta title</label>
                        <input class="form-control" name="meta_title" maxlength="191" value="<?= e($row['meta_title'] ?? '') ?>" placeholder="Defaults to the post title"></div>
                    <div class="form-group"><label class="form-label">Meta description</label>
                        <textarea class="form-textarea" name="meta_description" maxlength="300" style="min-height:70px;" placeholder="Defaults to the excerpt"><?= e($row['meta_description'] ?? '') ?></textarea></div>
                    <div class="grid-2">
                        <div class="form-group"><label class="form-label">Canonical URL</label>
                            <input class="form-control" type="url" name="canonical_url" value="<?= e($row['canonical_url'] ?? '') ?>" placeholder="https://…"></div>
                        <div class="form-group"><label class="form-label">Social (OG) image</label>
                            <input class="form-control" type="file" name="og_image" accept="image/*">
                            <?php if (!empty($row['og_image'])): ?><small class="form-hint">Current: <a href="<?= e(upload_url($row['og_image'])) ?>" target="_blank" rel="noopener">view image</a></small><?php endif; ?></div>
                    </div>
                </div>
            </details>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> Post</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('blogs')) ?>">Cancel</a>
            </div>
        </form>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Blogs';
$search = trim((string) get('q', ''));
$where  = 'b.deleted_at IS NULL';
$params = [];
if ($search !== '') {
    $where .= ' AND b.title LIKE :q';
    $params[':q'] = '%' . $search . '%';
}
$p = paginate(
    "SELECT b.*, c.name AS category_name
     FROM $table b
     LEFT JOIN blog_categories c ON c.id = b.category_id
     WHERE $where
     ORDER BY b.id DESC",
    $params,
    12
);

$statusPill = ['published' => 'pill-green', 'draft' => 'pill-amber', 'archived' => 'pill-gray'];

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Blogs</h1><span class="muted"><?= (int) $p['total'] ?> total</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('blogs?action=create')) ?>">+ Add Blog Post</a>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('blogs')) ?>">
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search blog posts…">
            </form>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Image</th><th>Title</th><th>Category</th><th>Status</th><th>Flags</th><th>Published</th><th class="num">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td><img class="thumb" src="<?= e(!empty($r['featured_image']) ? upload_url($r['featured_image']) : asset('images/placeholder.svg')) ?>" alt=""></td>
                        <td>
                            <strong><?= e($r['title']) ?></strong><br>
                            <small class="text-muted"><?= e(excerpt($r['excerpt'] ?? '', 12)) ?></small>
                        </td>
                        <td><?= !empty($r['category_name']) ? e($r['category_name']) : '<span class="text-muted">—</span>' ?></td>
                        <?php $isScheduled = $r['status'] === 'published' && !empty($r['published_at']) && strtotime((string) $r['published_at']) > time(); ?>
                        <td><span class="pill <?= $isScheduled ? 'pill-blue' : ($statusPill[$r['status']] ?? 'pill-gray') ?>"><?= $isScheduled ? 'Scheduled' : e(ucfirst($r['status'])) ?></span></td>
                        <td>
                            <?php
                            $flags = [];
                            // Flags carry a label, not a bare colour — admin-ds.css adds the glyph.
                            if (!empty($r['is_featured'])) $flags[] = '<span class="pill pill-blue">Featured</span>';
                            if (!empty($r['is_breaking'])) $flags[] = '<span class="pill pill-amber">Breaking</span>';
                            if (!empty($r['is_sticky']))   $flags[] = '<span class="pill pill-gray">Sticky</span>';
                            echo $flags ? implode(' ', $flags) : '<span class="text-muted">—</span>';
                            ?>
                        </td>
                        <td><?= !empty($r['published_at']) ? e(format_date($r['published_at'], 'd M Y')) : '<span class="text-muted">—</span>' ?></td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('blogs?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                                <form method="post" action="<?= e(admin_url('blogs')) ?>" data-confirm="Delete this blog post permanently?" class="inline-form">
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
                            <div class="icon"><?= lucide('clipboard-pen') ?></div>
                            <?php if ($search !== ''): ?>
                                <p class="es-title">No blog posts match &ldquo;<?= e($search) ?>&rdquo;</p>
                                <p class="es-text">No post has that title or slug. Clear the search to see every one.</p>
                                <div class="es-actions">
                                    <a class="btn btn-secondary" href="<?= e(admin_url('blogs')) ?>">Clear search</a>
                                    <a class="btn btn-primary" href="<?= e(admin_url('blogs?action=create')) ?>"><?= lucide('plus') ?> Add your first post</a>
                                </div>
                            <?php else: ?>
                                <p class="es-title">No blog posts yet</p>
                                <p class="es-text">Posts are the dated, categorised part of the site. Write one and it appears on the blog index as soon as you publish.</p>
                                <div class="es-actions">
                                    <a class="btn btn-primary" href="<?= e(admin_url('blogs?action=create')) ?>"><?= lucide('plus') ?> Add your first post</a>
                                </div>
                            <?php endif; ?>
                        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
