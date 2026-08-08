<?php
/**
 * =============================================================================
 *  Admin — School Batches (school-specific course batches with schedules)
 * =============================================================================
 *  list · create · edit · delete. Each batch belongs to one school and may be
 *  linked to an NGO program and a teacher (a school_staff row of that school).
 *  A "school context" (?school=<id>) pre-filters the list and defaults the form,
 *  and is preserved in the "+ Add" link and after-save redirect.
 *  Standard admin pattern: CSRF, prepared statements, pagination, stat cards,
 *  activity logging, flash + redirect.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'school_batches';
$action = get('action', 'list');
$id     = (int) get('id', 0);
$ctx    = (int) get('school', 0); // school context

$statuses = [
    'upcoming'  => 'Upcoming',
    'ongoing'   => 'Ongoing',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
];

$schools  = school_options();
$programs = school_programs();

/* -------------------------------------------------------------- SAVE */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);
    $old    = $editId ? find($table, $editId) : null;
    if ($editId && !$old) {
        set_flash('error', 'Batch not found.');
        redirect('/admin/school-batches');
    }

    $schoolId = (int) post('school_id', 0);
    $name     = clean(post('name'));

    // Preserve school context on validation-failure redirects.
    $ctxQ = $schoolId ? '&school=' . $schoolId : '';
    $ret  = '/admin/school-batches?action=' . ($editId ? 'edit&id=' . $editId : 'create') . $ctxQ;

    if (!isset($schools[$schoolId])) {
        flash_old($_POST);
        set_flash('error', 'Please select a valid school.');
        redirect($ret);
    }
    if ($name === '') {
        flash_old($_POST);
        set_flash('error', 'Batch name is required.');
        redirect($ret);
    }

    // program_id must be a known NGO program (or none).
    $programId = (int) post('program_id', 0);
    if (!isset($programs[$programId])) {
        $programId = null;
    }

    // teacher_id must belong to this school (else null).
    $teacherId = (int) post('teacher_id', 0) ?: null;
    if ($teacherId) {
        $ok = db_value(
            "SELECT id FROM school_staff WHERE id = :id AND school_id = :s LIMIT 1",
            [':id' => $teacherId, ':s' => $schoolId]
        );
        if (!$ok) {
            $teacherId = null;
        }
    }

    $data = [
        'school_id'  => $schoolId,
        'name'       => $name,
        'program_id' => $programId,
        'teacher_id' => $teacherId,
        'schedule'   => clean(post('schedule')) ?: null,
        'start_date' => clean(post('start_date')) ?: null,
        'end_date'   => clean(post('end_date')) ?: null,
        'capacity'   => (int) post('capacity') ?: null,
        'status'     => isset($statuses[(string) post('status')]) ? post('status') : 'upcoming',
        'notes'      => trim((string) post('notes')) ?: null,
    ];

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'school_batches', 'Updated batch #' . $editId . ' (' . $name . ')');
        set_flash('success', 'Batch updated.');
    } else {
        $newId = db_insert($table, $data);
        log_activity('create', 'school_batches', 'Created batch #' . $newId . ' (' . $name . ')');
        set_flash('success', 'Batch created.');
    }
    redirect('/admin/school-batches' . ($schoolId ? '?school=' . $schoolId : ''));
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row   = find($table, $delId);
    if ($row) {
        db_delete($table, 'id = :id', [':id' => $delId]); // attendance cascades
        log_activity('delete', 'school_batches', 'Deleted batch #' . $delId . ' (' . ($row['name'] ?? '') . ')');
        set_flash('success', 'Batch deleted.');
    }
    redirect('/admin/school-batches' . ($ctx ? '?school=' . $ctx : ''));
}

/* -------------------------------------------------------------- FORM */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Batch not found.');
        redirect('/admin/school-batches');
    }

    // School whose staff feed the teacher dropdown: the row's school on edit,
    // otherwise the incoming ?school context.
    $formSchool = $action === 'edit' ? (int) ($row['school_id'] ?? 0) : $ctx;
    $teachers   = $formSchool
        ? db_all("SELECT id, name, role FROM school_staff WHERE school_id = :s AND status = 'active' ORDER BY name", [':s' => $formSchool])
        : [];

    $curSchool  = (int) old('school_id', $row['school_id'] ?? $ctx);
    $curProgram = (int) old('program_id', $row['program_id'] ?? 0);
    $curTeacher = (int) old('teacher_id', $row['teacher_id'] ?? 0);
    $curStatus  = (string) old('status', $row['status'] ?? 'upcoming');

    $o = static fn(string $k, $d = '') => e(old($k, $row[$k] ?? $d));

    $backQ      = $ctx ? '?school=' . $ctx : '';
    $page_title = $action === 'edit' ? 'Edit Batch' : 'Add Batch';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">School batches / <?= $action === 'edit' ? 'Edit' : 'New' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('school-batches' . $backQ)) ?>">← Back to list</a>
    </div>

    <form class="admin-form" method="post" action="<?= e(admin_url('school-batches')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="save">
        <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

        <div class="panel"><div class="panel-body">
            <h3 class="mb-3">Batch details</h3>

            <div class="grid-2">
                <div class="form-group"><label class="form-label">School <span class="req">*</span></label>
                    <select class="form-select" name="school_id" required>
                        <option value="">— Select school —</option>
                        <?php foreach ($schools as $sid => $sname): ?>
                            <option value="<?= (int) $sid ?>" <?= $curSchool === (int) $sid ? 'selected' : '' ?>><?= e($sname) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label class="form-label">Batch name <span class="req">*</span></label>
                    <input class="form-control" name="name" required value="<?= $o('name') ?>" placeholder="e.g. Evening Science Batch A"></div>
            </div>

            <div class="grid-2">
                <div class="form-group"><label class="form-label">Program / course</label>
                    <select class="form-select" name="program_id">
                        <option value="">None</option>
                        <?php foreach ($programs as $pid => $ptitle): ?>
                            <option value="<?= (int) $pid ?>" <?= $curProgram === (int) $pid ? 'selected' : '' ?>><?= e($ptitle) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$programs): ?><small class="form-hint">No active NGO programs available.</small><?php endif; ?>
                </div>
                <div class="form-group"><label class="form-label">Teacher</label>
                    <select class="form-select" name="teacher_id">
                        <option value="">Unassigned</option>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?= (int) $t['id'] ?>" <?= $curTeacher === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?> — <?= e(school_role_labels()[$t['role']] ?? $t['role']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$teachers): ?><small class="form-hint"><?= $formSchool ? 'No active staff for this school yet.' : 'Pick a school first to list its staff.' ?></small><?php endif; ?>
                </div>
            </div>

            <div class="form-group"><label class="form-label">Schedule</label>
                <input class="form-control" name="schedule" value="<?= $o('schedule') ?>" placeholder='e.g. Mon/Wed/Fri 4–5 PM'></div>

            <div class="grid-3">
                <div class="form-group"><label class="form-label">Start date</label>
                    <input class="form-control" type="date" name="start_date" value="<?= $o('start_date') ?>"></div>
                <div class="form-group"><label class="form-label">End date</label>
                    <input class="form-control" type="date" name="end_date" value="<?= $o('end_date') ?>"></div>
                <div class="form-group"><label class="form-label">Capacity</label>
                    <input class="form-control" type="number" name="capacity" min="0" value="<?= $o('capacity') ?>" placeholder="e.g. 30"></div>
            </div>

            <div class="grid-2">
                <div class="form-group"><label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <?php foreach ($statuses as $k => $l): ?>
                            <option value="<?= e($k) ?>" <?= $curStatus === $k ? 'selected' : '' ?>><?= e($l) ?></option>
                        <?php endforeach; ?>
                    </select></div>
            </div>

            <div class="form-group"><label class="form-label">Notes</label>
                <textarea class="form-textarea" name="notes" style="min-height:90px;"><?= e($row['notes'] ?? old('notes', '')) ?></textarea></div>

            <div class="form-actions mt-3">
                <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> batch</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('school-batches' . $backQ)) ?>">Cancel</a>
            </div>
        </div></div>
    </form>
    <?php
    clear_old();
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title   = 'School Batches';
$search       = trim((string) get('q', ''));
$statusFilter = (string) get('status', '');

$ctxSchool = $ctx ? find('schools', $ctx) : null;
if ($ctx && !$ctxSchool) { $ctx = 0; } // stale context id — ignore

$where  = '1=1';
$params = [];
if ($ctx) {
    $where .= ' AND b.school_id = :school';
    $params[':school'] = $ctx;
}
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
            (SELECT COUNT(*) FROM school_students ss WHERE ss.batch_id = b.id) AS enrolled,
            sc.name AS school_name
       FROM school_batches b
       LEFT JOIN schools sc ON sc.id = b.school_id
      WHERE $where
      ORDER BY b.id DESC",
    $params, 15
);

// Stat cards — scoped to the school context when present, else global.
$scopeSql    = $ctx ? 'school_id = :s AND ' : '';
$scopeParams = $ctx ? [':s' => $ctx] : [];
$counts = [
    'all'       => db_count('school_batches', $ctx ? 'school_id = :s' : '', $scopeParams),
    'upcoming'  => db_count('school_batches', $scopeSql . "status = 'upcoming'", $scopeParams),
    'ongoing'   => db_count('school_batches', $scopeSql . "status = 'ongoing'", $scopeParams),
    'completed' => db_count('school_batches', $scopeSql . "status = 'completed'", $scopeParams),
];

$ctxQ    = $ctx ? '&school=' . $ctx : '';
$hasFilter = $search !== '' || $statusFilter !== '';

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div>
        <h1>School Batches</h1>
        <span class="muted"><?= $ctxSchool ? e($ctxSchool['name']) . ' · ' : '' ?><?= (int) $counts['all'] ?> batches</span>
    </div>
    <div class="flex" style="gap:.5rem;">
        <?php if ($ctxSchool): ?><a class="btn btn-secondary" href="<?= e(admin_url('school-dashboard?id=' . $ctx)) ?>">← Dashboard</a><?php endif; ?>
        <a class="btn btn-primary" href="<?= e(admin_url('school-batches?action=create' . $ctxQ)) ?>">+ Add Batch</a>
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
        <form class="search" method="get" action="<?= e(admin_url('school-batches')) ?>">
            <?php if ($ctx): ?><input type="hidden" name="school" value="<?= (int) $ctx ?>"><?php endif; ?>
            <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search batch name…">
        </form>
        <form method="get" action="<?= e(admin_url('school-batches')) ?>" class="flex" style="gap:.5rem;">
            <?php if ($ctx): ?><input type="hidden" name="school" value="<?= (int) $ctx ?>"><?php endif; ?>
            <select class="form-select" name="school" onchange="this.form.submit()">
                <option value="">All schools</option>
                <?php foreach ($schools as $sid => $sname): ?>
                    <option value="<?= (int) $sid ?>" <?= $ctx === (int) $sid ? 'selected' : '' ?>><?= e($sname) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" name="status" onchange="this.form.submit()">
                <option value="">All statuses</option>
                <?php foreach ($statuses as $k => $l): ?>
                    <option value="<?= e($k) ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= e($l) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php if ($ctx || $hasFilter): ?><a class="btn btn-ghost btn-sm" href="<?= e(admin_url('school-batches')) ?>">Clear</a><?php endif; ?>
    </div>

    <?php if ($p['items']): ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th>Batch</th><th>Schedule</th><th>Dates</th><th>Enrolled</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($p['items'] as $r):
            $prog  = $r['program_id'] ? ($programs[(int) $r['program_id']] ?? null) : null;
            $sub   = array_filter([$prog, ($ctx ? null : ($r['school_name'] ?? null))]);
            $start = format_date($r['start_date'] ?? null, 'd M Y');
            $end   = format_date($r['end_date'] ?? null, 'd M Y');
            $editQ = 'school-batches?action=edit&id=' . $r['id'] . ($ctx ? '&school=' . $ctx : '');
        ?>
            <tr>
                <td>
                    <strong><?= e($r['name']) ?></strong>
                    <?php if ($sub): ?><br><small class="text-muted"><?= e(implode(' · ', $sub)) ?></small><?php endif; ?>
                </td>
                <td><?= $r['schedule'] ? e($r['schedule']) : '<span class="text-muted">—</span>' ?></td>
                <td>
                    <?php if ($start || $end): ?>
                        <?= e($start ?: '…') ?> – <?= e($end ?: '…') ?>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td><?= (int) $r['enrolled'] ?><?= $r['capacity'] ? ' / ' . (int) $r['capacity'] : '' ?></td>
                <td><span class="pill <?= e(batch_status_pill($r['status'])) ?>"><?= e($statuses[$r['status']] ?? ucfirst($r['status'])) ?></span></td>
                <td><div class="actions">
                    <a class="icon-btn" href="<?= e(admin_url($editQ)) ?>" title="Edit"><?= lucide('pencil') ?></a>
                    <form method="post" action="<?= e(admin_url('school-batches' . ($ctx ? '?school=' . $ctx : ''))) ?>" data-confirm="Delete this batch? Its attendance records will also be removed." style="display:inline;">
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
            <?= ($ctx || $hasFilter) ? 'No batches match your filters. ' : 'No batches yet. ' ?>
            <a href="<?= e(admin_url('school-batches?action=create' . $ctxQ)) ?>">Add one</a>.</div>
    <?php endif; ?>
</div></div>

<?php include __DIR__ . '/partials/foot.php'; ?>
