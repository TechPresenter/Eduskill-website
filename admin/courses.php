<?php
/**
 * =============================================================================
 *  Admin — Courses (LMS course catalogue + CRUD)
 * =============================================================================
 *  list · create · edit · delete. Each course links out to its lessons,
 *  batches and enrolments. Standard admin pattern: CSRF, prepared statements,
 *  pagination, stat cards, image upload, activity logging, flash + redirect.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'courses';
$action = get('action', 'list');
$id     = (int) get('id', 0);

$levels = [
    'beginner'     => 'Beginner',
    'intermediate' => 'Intermediate',
    'advanced'     => 'Advanced',
];
$statuses = [
    'draft'     => 'Draft',
    'published' => 'Published',
    'archived'  => 'Archived',
];
$certTemplates = [
    'classic' => 'Classic',
    'modern'  => 'Modern',
    'elegant' => 'Elegant',
];

/* -------------------------------------------------------------- SAVE */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);
    $old    = $editId ? find($table, $editId) : null;
    if ($editId && !$old) {
        set_flash('error', 'Course not found.');
        redirect('/admin/courses');
    }

    if (clean(post('title')) === '') {
        flash_old($_POST);
        set_flash('error', 'Course title is required.');
        redirect('/admin/courses?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    $data = [
        'title'                => clean(post('title')),
        'slug'                 => unique_slug($table, post('slug') ?: post('title'), $editId ?: null),
        'category'             => clean(post('category')) ?: null,
        'short_description'    => clean(post('short_description')) ?: null,
        'description'          => trim((string) post('description')) ?: null,
        'objectives'           => trim((string) post('objectives')) ?: null,
        'prerequisites'        => trim((string) post('prerequisites')) ?: null,
        'level'                => isset($levels[(string) post('level')]) ? post('level') : 'beginner',
        'language'             => clean(post('language')) ?: null,
        'duration'             => clean(post('duration')) ?: null,
        'price'                => (float) post('price'),
        'is_featured'          => post('is_featured') ? 1 : 0,
        'certificate_enabled'  => post('certificate_enabled') ? 1 : 0,
        'certificate_template' => isset($certTemplates[(string) post('certificate_template')]) ? post('certificate_template') : 'classic',
        'status'               => isset($statuses[(string) post('status')]) ? post('status') : 'draft',
    ];

    if (!empty($_FILES['thumbnail']['name'])) {
        // Raster formats only — exclude SVG (which upload.php would otherwise
        // accept unsanitised, enabling stored XSS from a crafted .svg).
        $up = upload_image($_FILES['thumbnail'], 'courses', ['allowed' => 'jpg,jpeg,png,gif,webp']);
        if (!$up['success']) {
            flash_old($_POST);
            set_flash('error', 'Thumbnail: ' . $up['error']);
            redirect('/admin/courses?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
        }
        $data['thumbnail'] = $up['path'];
        if ($editId && !empty($old['thumbnail'])) { delete_upload($old['thumbnail']); }
    }

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'courses', 'Updated course #' . $editId . ' (' . $data['title'] . ')');
        set_flash('success', 'Course updated.');
        redirect('/admin/courses');
    }
    $data['created_by'] = current_user_id();
    $newId = db_insert($table, $data);
    log_activity('create', 'courses', 'Created course #' . $newId . ' (' . $data['title'] . ')');
    set_flash('success', 'Course created.');
    redirect('/admin/course-lessons?course=' . $newId);
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row   = find($table, $delId);
    if ($row) {
        if (!empty($row['thumbnail'])) { delete_upload($row['thumbnail']); }
        db_delete($table, 'id = :id', [':id' => $delId]); // lessons, batches, enrolments cascade
        log_activity('delete', 'courses', 'Deleted course #' . $delId . ' (' . ($row['title'] ?? '') . ')');
        set_flash('success', 'Course and all its content deleted.');
    }
    redirect('/admin/courses');
}

/* -------------------------------------------------------------- FORM */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Course not found.');
        redirect('/admin/courses');
    }
    $o = static fn(string $k, $d = '') => e(old($k, $row[$k] ?? $d));
    $page_title = $action === 'edit' ? 'Edit Course' : 'New Course';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Courses / <?= $action === 'edit' ? 'Edit' : 'New' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('courses')) ?>">← Back to list</a>
    </div>

    <form class="admin-form" method="post" action="<?= e(admin_url('courses')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="save">
        <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

        <div class="grid-2" style="align-items:start;gap:1.25rem;">
            <div class="panel"><div class="panel-body">
                <h3 class="mb-3">Course details</h3>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Title <span class="req">*</span></label>
                        <input class="form-control" name="title" data-slug-source required value="<?= $o('title') ?>"></div>
                    <div class="form-group"><label class="form-label">Slug</label>
                        <input class="form-control" name="slug" data-slug-target value="<?= $o('slug') ?>" placeholder="auto"></div>
                </div>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Category</label>
                        <input class="form-control" name="category" value="<?= $o('category') ?>" placeholder="e.g. Digital Literacy"></div>
                    <div class="form-group"><label class="form-label">Level</label>
                        <select class="form-select" name="level">
                            <?php foreach ($levels as $k => $l): ?><option value="<?= e($k) ?>" <?= ($row['level'] ?? 'beginner') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                        </select></div>
                </div>
                <div class="grid-3">
                    <div class="form-group"><label class="form-label">Language</label>
                        <input class="form-control" name="language" value="<?= $o('language') ?>" placeholder="e.g. Hindi"></div>
                    <div class="form-group"><label class="form-label">Duration</label>
                        <input class="form-control" name="duration" value="<?= $o('duration') ?>" placeholder="e.g. 6 weeks"></div>
                    <div class="form-group"><label class="form-label">Price (₹)</label>
                        <input class="form-control" type="number" step="0.01" min="0" name="price" value="<?= $o('price', '0') ?>"></div>
                </div>
                <div class="form-group"><label class="form-label">Thumbnail</label>
                    <input class="form-control" type="file" name="thumbnail" accept="image/*">
                    <?php if (!empty($row['thumbnail'])): ?><small class="form-hint">Current thumbnail kept unless replaced.</small><?php endif; ?>
                </div>
                <div class="form-group"><label class="form-label">Short description</label>
                    <textarea class="form-textarea" name="short_description" style="min-height:70px;" maxlength="500"><?= e(old('short_description', $row['short_description'] ?? '')) ?></textarea>
                    <small class="form-hint">Shown on cards and listings (max 500 characters).</small></div>
                <div class="form-group"><label class="form-label">Full description</label>
                    <textarea class="form-textarea" name="description" data-wysiwyg style="min-height:140px;"><?= e(old('description', $row['description'] ?? '')) ?></textarea></div>
            </div></div>

            <div class="panel"><div class="panel-body">
                <h3 class="mb-3">Curriculum & settings</h3>
                <div class="form-group"><label class="form-label">Learning objectives</label>
                    <textarea class="form-textarea" name="objectives" style="min-height:110px;" placeholder="One per line…"><?= e(old('objectives', $row['objectives'] ?? '')) ?></textarea></div>
                <div class="form-group"><label class="form-label">Prerequisites</label>
                    <textarea class="form-textarea" name="prerequisites" style="min-height:90px;" placeholder="One per line…"><?= e(old('prerequisites', $row['prerequisites'] ?? '')) ?></textarea></div>

                <label class="checkbox"><input type="checkbox" name="is_featured" value="1" <?= !empty($row['is_featured']) ? 'checked' : '' ?>> Feature this course on the site</label>
                <label class="checkbox"><input type="checkbox" name="certificate_enabled" value="1" <?= (!array_key_exists('certificate_enabled', (array) $row) || !empty($row['certificate_enabled'])) ? 'checked' : '' ?>> Issue a certificate on completion</label>

                <div class="grid-2 mt-3">
                    <div class="form-group"><label class="form-label">Certificate template</label>
                        <select class="form-select" name="certificate_template">
                            <?php foreach ($certTemplates as $k => $l): ?><option value="<?= e($k) ?>" <?= ($row['certificate_template'] ?? 'classic') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <?php foreach ($statuses as $k => $l): ?><option value="<?= e($k) ?>" <?= ($row['status'] ?? 'draft') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                        </select></div>
                </div>

                <div class="form-actions mt-3">
                    <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> course</button>
                    <a class="btn btn-ghost" href="<?= e(admin_url('courses')) ?>">Cancel</a>
                </div>
            </div></div>
        </div>
    </form>
    <?php
    clear_old();
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title    = 'Courses';
$search        = trim((string) get('q', ''));
$statusFilter  = (string) get('status', '');
$categoryFilter = trim((string) get('category', ''));

$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= ' AND c.title LIKE :q';
    $params[':q'] = '%' . $search . '%';
}
if (isset($statuses[$statusFilter])) {
    $where .= ' AND c.status = :st';
    $params[':st'] = $statusFilter;
}
if ($categoryFilter !== '') {
    $where .= ' AND c.category = :cat';
    $params[':cat'] = $categoryFilter;
}

$p = paginate(
    "SELECT c.*,
            (SELECT COUNT(*) FROM course_lessons cl WHERE cl.course_id = c.id) AS lesson_count,
            (SELECT COUNT(*) FROM course_enrollments ce WHERE ce.course_id = c.id) AS enrol_count
       FROM courses c
      WHERE $where
      ORDER BY c.id DESC",
    $params, 15
);

$counts = [
    'all'       => db_count('courses'),
    'published' => db_count('courses', "status = 'published'"),
    'enrolments' => db_count('course_enrollments'),
];

// Distinct categories for the filter dropdown.
$categories = db_all("SELECT DISTINCT category FROM courses WHERE category IS NOT NULL AND category <> '' ORDER BY category");

$hasFilter = $search !== '' || $statusFilter !== '' || $categoryFilter !== '';

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Courses</h1><span class="muted"><?= (int) $counts['all'] ?> courses</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('courses?action=create')) ?>">+ New Course</a>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon bg-violet"><?= lucide('graduation-cap') ?></div><div><div class="stat-value"><?= (int) $counts['all'] ?></div><div class="stat-label">Courses</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-green"><?= lucide('circle-check') ?></div><div><div class="stat-value"><?= (int) $counts['published'] ?></div><div class="stat-label">Published</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-blue"><?= lucide('users') ?></div><div><div class="stat-value"><?= (int) $counts['enrolments'] ?></div><div class="stat-label">Enrolments</div></div></div>
</div>

<div class="panel"><div class="panel-body">
    <div class="data-toolbar">
        <form class="search" method="get" action="<?= e(admin_url('courses')) ?>">
            <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search course title…"></form>
        <form method="get" action="<?= e(admin_url('courses')) ?>" class="flex" style="gap:.5rem;">
            <select class="form-select" name="category" onchange="this.form.submit()">
                <option value="">All categories</option>
                <?php foreach ($categories as $cat): ?><option value="<?= e($cat['category']) ?>" <?= $categoryFilter === $cat['category'] ? 'selected' : '' ?>><?= e($cat['category']) ?></option><?php endforeach; ?>
            </select>
            <select class="form-select" name="status" onchange="this.form.submit()">
                <option value="">All statuses</option>
                <?php foreach ($statuses as $k => $l): ?><option value="<?= e($k) ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
            </select>
        </form>
        <?php if ($hasFilter): ?><a class="btn btn-ghost btn-sm" href="<?= e(admin_url('courses')) ?>">Clear</a><?php endif; ?>
    </div>

    <?php if ($p['items']): ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th>Course</th><th>Level</th><th>Lessons</th><th>Enrolled</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($p['items'] as $r): ?>
            <tr>
                <td><div class="flex items-center" style="gap:.6rem;">
                    <img src="<?= e(image_url($r['thumbnail'] ?? null, 'general')) ?>" alt="" style="width:40px;height:30px;border-radius:6px;object-fit:cover;flex:none;background:#eef2f7;">
                    <div><a href="<?= e(admin_url('courses?action=edit&id=' . $r['id'])) ?>"><strong><?= e($r['title']) ?></strong></a><br><small class="text-muted"><?= $r['category'] ? e($r['category']) : '—' ?><?= (float) $r['price'] > 0 ? ' · ' . e(money($r['price'])) : ' · Free' ?></small></div>
                </div></td>
                <td><?= e($levels[$r['level']] ?? $r['level']) ?></td>
                <td><?= (int) $r['lesson_count'] ?></td>
                <td><?= (int) $r['enrol_count'] ?></td>
                <td><span class="pill <?= e(course_status_pill($r['status'])) ?>"><?= e($statuses[$r['status']] ?? ucfirst($r['status'])) ?></span></td>
                <td><div class="actions">
                    <a class="icon-btn" href="<?= e(admin_url('course-lessons?course=' . $r['id'])) ?>" title="Lessons"><?= lucide('list-video') ?></a>
                    <a class="icon-btn" href="<?= e(admin_url('course-batches?course=' . $r['id'])) ?>" title="Batches"><?= lucide('layers') ?></a>
                    <a class="icon-btn" href="<?= e(admin_url('course-enrollments?course=' . $r['id'])) ?>" title="Enrolments"><?= lucide('users') ?></a>
                    <a class="icon-btn" href="<?= e(admin_url('courses?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                    <form method="post" action="<?= e(admin_url('courses')) ?>" data-confirm="Delete this course and ALL its lessons, batches and enrolments? This cannot be undone." style="display:inline;">
                        <?= csrf_field() ?><input type="hidden" name="_do" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
                    </form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?= $p['links'] ?>
    <?php else: ?>
        <div class="empty-state"><div class="icon"><?= lucide('graduation-cap') ?></div>
            <?= $hasFilter ? 'No courses match your filters. ' : 'No courses yet. ' ?>
            <a href="<?= e(admin_url('courses?action=create')) ?>">Create one</a>.</div>
    <?php endif; ?>
</div></div>

<?php include __DIR__ . '/partials/foot.php'; ?>
