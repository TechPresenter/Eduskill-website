<?php
/**
 * =============================================================================
 *  Admin — Course Batches (LMS cohorts for one course)
 * =============================================================================
 *  list · create · edit · delete. Every batch belongs to one course; the page
 *  is scoped by a required ?course=<id> context that is preserved in the
 *  "+ Add" link, edit links and after-save redirects. Standard admin pattern:
 *  CSRF, prepared statements, pagination, stat cards, activity logging,
 *  flash + redirect.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table    = 'course_batches';
$action   = get('action', 'list');
$id       = (int) get('id', 0);
$courseId = (int) get('course', 0); // required parent context

$statuses = [
    'upcoming'  => 'Upcoming',
    'ongoing'   => 'Ongoing',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
];

// Parent course context is mandatory — bail to the courses list otherwise.
$course = $courseId ? find('courses', $courseId) : null;
if (!$course) {
    set_flash('error', 'Select a course to manage its batches.');
    redirect('/admin/courses');
}

$ctxUrl = '/admin/course-batches?course=' . $courseId; // canonical scoped list URL

/* -------------------------------------------------------------- SAVE */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);
    $old    = $editId ? find($table, $editId) : null;
    if ($editId && (!$old || (int) $old['course_id'] !== $courseId)) {
        set_flash('error', 'Batch not found.');
        redirect($ctxUrl);
    }

    $name = clean(post('name'));
    $ret  = $ctxUrl . '&action=' . ($editId ? 'edit&id=' . $editId : 'create');

    if ($name === '') {
        flash_old($_POST);
        set_flash('error', 'Batch name is required.');
        redirect($ret);
    }

    $data = [
        'course_id'  => $courseId,
        'name'       => $name,
        'start_date' => clean(post('start_date')) ?: null,
        'end_date'   => clean(post('end_date')) ?: null,
        'seats'      => (int) post('seats') ?: null,
        'status'     => isset($statuses[(string) post('status')]) ? post('status') : 'upcoming',
    ];

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'courses', 'Updated course batch #' . $editId . ' (' . $name . ')');
        set_flash('success', 'Batch updated.');
    } else {
        $newId = db_insert($table, $data);
        log_activity('create', 'courses', 'Created course batch #' . $newId . ' (' . $name . ')');
        set_flash('success', 'Batch created.');
    }
    redirect($ctxUrl);
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row   = find($table, $delId);
    if ($row && (int) $row['course_id'] === $courseId) {
        db_delete($table, 'id = :id', [':id' => $delId]); // enrollments detach (batch_id nullable)
        log_activity('delete', 'courses', 'Deleted course batch #' . $delId . ' (' . ($row['name'] ?? '') . ')');
        set_flash('success', 'Batch deleted.');
    }
    redirect($ctxUrl);
}

/* -------------------------------------------------------------- FORM */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && (!$row || (int) $row['course_id'] !== $courseId)) {
        set_flash('error', 'Batch not found.');
        redirect($ctxUrl);
    }

    $curStatus = (string) old('status', $row['status'] ?? 'upcoming');
    $o = static fn(string $k, $d = '') => e(old($k, $row[$k] ?? $d));

    $backQ      = '?course=' . $courseId;
    $page_title = $action === 'edit' ? 'Edit Batch' : 'Add Batch';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted"><?= e($course['title']) ?> / <?= $action === 'edit' ? 'Edit batch' : 'New batch' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('course-batches' . $backQ)) ?>">← Back to list</a>
    </div>

    <form class="admin-form" method="post" action="<?= e(admin_url('course-batches' . $backQ)) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="save">
        <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

        <div class="panel"><div class="panel-body">
            <h3 class="mb-3">Batch details</h3>

            <div class="form-group"><label class="form-label">Batch name <span class="req">*</span></label>
                <input class="form-control" name="name" required value="<?= $o('name') ?>" placeholder="e.g. January 2026 Cohort"></div>

            <div class="grid-3">
                <div class="form-group"><label class="form-label">Start date</label>
                    <input class="form-control" type="date" name="start_date" value="<?= $o('start_date') ?>"></div>
                <div class="form-group"><label class="form-label">End date</label>
                    <input class="form-control" type="date" name="end_date" value="<?= $o('end_date') ?>"></div>
                <div class="form-group"><label class="form-label">Seats</label>
                    <input class="form-control" type="number" name="seats" min="0" value="<?= $o('seats') ?>" placeholder="e.g. 40">
                    <small class="form-hint">Leave blank for unlimited.</small></div>
            </div>

            <div class="grid-2">
                <div class="form-group"><label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <?php foreach ($statuses as $k => $l): ?>
                            <option value="<?= e($k) ?>" <?= $curStatus === $k ? 'selected' : '' ?>><?= e($l) ?></option>
                        <?php endforeach; ?>
                    </select></div>
            </div>

            <div class="form-actions mt-3">
                <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> batch</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('course-batches' . $backQ)) ?>">Cancel</a>
            </div>
        </div></div>
    </form>
    <?php
    clear_old();
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title   = 'Course Batches';
$search       = trim((string) get('q', ''));
$statusFilter = (string) get('status', '');

$where  = 'b.course_id = :course';
$params = [':course' => $courseId];
if ($search !== '') {
    $where .= ' AND b.name LIKE :q';
    $params[':q'] = '%' . $search . '%';
}
if (isset($statuses[$statusFilter])) {
    $where .= ' AND b.status = :st';
    $params[':st'] = $statusFilter;
}

$p = paginate(
    "SELECT b.*,
            (SELECT COUNT(*) FROM course_enrollments e WHERE e.batch_id = b.id) AS enrolled
       FROM course_batches b
      WHERE $where
      ORDER BY b.id DESC",
    $params, 15
);

// Stat cards — scoped to this course.
$counts = [
    'all'       => db_count('course_batches', 'course_id = :c', [':c' => $courseId]),
    'upcoming'  => db_count('course_batches', "course_id = :c AND status = 'upcoming'", [':c' => $courseId]),
    'ongoing'   => db_count('course_batches', "course_id = :c AND status = 'ongoing'", [':c' => $courseId]),
    'completed' => db_count('course_batches', "course_id = :c AND status = 'completed'", [':c' => $courseId]),
];

$hasFilter = $search !== '' || $statusFilter !== '';

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div>
        <h1>Course Batches</h1>
        <span class="muted"><?= e($course['title']) ?> · <?= (int) $counts['all'] ?> batches</span>
    </div>
    <div class="flex" style="gap:.5rem;">
        <a class="btn btn-secondary" href="<?= e(admin_url('courses')) ?>">← Courses</a>
        <a class="btn btn-primary" href="<?= e(admin_url('course-batches?action=create&course=' . $courseId)) ?>">+ Add Batch</a>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon bg-violet"><?= lucide('layers') ?></div><div><div class="stat-value"><?= (int) $counts['all'] ?></div><div class="stat-label">Batches</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-blue"><?= lucide('calendar-clock') ?></div><div><div class="stat-value"><?= (int) $counts['upcoming'] ?></div><div class="stat-label">Upcoming</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-green"><?= lucide('play') ?></div><div><div class="stat-value"><?= (int) $counts['ongoing'] ?></div><div class="stat-label">Ongoing</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-amber"><?= lucide('circle-check') ?></div><div><div class="stat-value"><?= (int) $counts['completed'] ?></div><div class="stat-label">Completed</div></div></div>
</div>

<div class="panel"><div class="panel-body">
    <div class="data-toolbar">
        <form class="search" method="get" action="<?= e(admin_url('course-batches')) ?>">
            <input type="hidden" name="course" value="<?= (int) $courseId ?>">
            <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search batch name…">
        </form>
        <form method="get" action="<?= e(admin_url('course-batches')) ?>" class="flex" style="gap:.5rem;">
            <input type="hidden" name="course" value="<?= (int) $courseId ?>">
            <select class="form-select" name="status" onchange="this.form.submit()">
                <option value="">All statuses</option>
                <?php foreach ($statuses as $k => $l): ?>
                    <option value="<?= e($k) ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= e($l) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php if ($hasFilter): ?><a class="btn btn-ghost btn-sm" href="<?= e(admin_url('course-batches?course=' . $courseId)) ?>">Clear</a><?php endif; ?>
    </div>

    <?php if ($p['items']): ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th>Batch</th><th>Dates</th><th>Enrolled</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($p['items'] as $r):
            $start = format_date($r['start_date'] ?? null, 'd M Y');
            $end   = format_date($r['end_date'] ?? null, 'd M Y');
            $editQ = 'course-batches?action=edit&id=' . $r['id'] . '&course=' . $courseId;
        ?>
            <tr>
                <td><strong><?= e($r['name']) ?></strong></td>
                <td>
                    <?php if ($start || $end): ?>
                        <?= e($start ?: '…') ?> – <?= e($end ?: '…') ?>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td><?= (int) $r['enrolled'] ?><?= $r['seats'] ? ' / ' . (int) $r['seats'] : '' ?></td>
                <td><span class="pill <?= e(batch_status_pill($r['status'])) ?>"><?= e($statuses[$r['status']] ?? ucfirst($r['status'])) ?></span></td>
                <td><div class="actions">
                    <a class="icon-btn" href="<?= e(admin_url($editQ)) ?>" title="Edit"><?= lucide('pencil') ?></a>
                    <form method="post" action="<?= e(admin_url('course-batches?course=' . $courseId)) ?>" data-confirm="Delete this batch? Enrolments in it will be unassigned." style="display:inline;">
                        <?= csrf_field() ?><input type="hidden" name="_do" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
                    </form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?= $p['links'] ?>
    <?php else: ?>
        <div class="empty-state"><div class="icon"><?= lucide('layers') ?></div>
            <?= $hasFilter ? 'No batches match your filters. ' : 'No batches yet. ' ?>
            <a href="<?= e(admin_url('course-batches?action=create&course=' . $courseId)) ?>">Add one</a>.</div>
    <?php endif; ?>
</div></div>

<?php include __DIR__ . '/partials/foot.php'; ?>
