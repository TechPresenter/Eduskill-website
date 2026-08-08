<?php
/**
 * =============================================================================
 *  Admin — Live Classes (Zoom / Google Meet live sessions CRUD)
 * =============================================================================
 *  list · create · edit · delete. Each session may belong to a course and one
 *  of its cohorts (course_batches) and carries a join link, schedule and
 *  status. Standard admin pattern: CSRF, prepared statements, pagination,
 *  stat cards, activity logging, flash + redirect.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'live_sessions';
$action = get('action', 'list');
$id     = (int) get('id', 0);

$platforms = [
    'zoom'  => 'Zoom',
    'meet'  => 'Google Meet',
    'other' => 'Other',
];
$statuses = [
    'scheduled' => 'Scheduled',
    'live'      => 'Live',
    'ended'     => 'Ended',
    'cancelled' => 'Cancelled',
];
$statusPill = [
    'scheduled' => 'pill-blue',
    'live'      => 'pill-green',
    'ended'     => 'pill-gray',
    'cancelled' => 'pill-red',
];

$courses = course_options();

// Cohorts for the batch dropdown: [id => label]. Labelled with the course
// title where one exists so same-named batches stay distinguishable.
$batches   = db_all(
    "SELECT b.id, b.name, c.title AS course_title
       FROM course_batches b
       LEFT JOIN courses c ON c.id = b.course_id
      ORDER BY b.name"
);
$batchOpts = [];
foreach ($batches as $b) {
    $bid = (int) $b['id'];
    $batchOpts[$bid] = $b['name'] . ($b['course_title'] ? ' — ' . $b['course_title'] : '');
}

/* -------------------------------------------------------------- SAVE */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);
    $old    = $editId ? find($table, $editId) : null;
    if ($editId && !$old) {
        set_flash('error', 'Live class not found.');
        redirect('/admin/live-sessions');
    }

    $ret = '/admin/live-sessions?action=' . ($editId ? 'edit&id=' . $editId : 'create');

    if (clean(post('title')) === '') {
        flash_old($_POST);
        set_flash('error', 'Title is required.');
        redirect($ret);
    }

    // course_id / batch_id must reference known rows (or none).
    $courseId = (int) post('course_id', 0);
    if (!isset($courses[$courseId])) {
        $courseId = null;
    }
    $batchId = (int) post('batch_id', 0);
    if (!isset($batchOpts[$batchId])) {
        $batchId = null;
    }

    // Normalise the datetime-local value (YYYY-MM-DDTHH:MM) into a DATETIME.
    $schedRaw    = trim((string) post('scheduled_at', ''));
    $scheduledAt = null;
    if ($schedRaw !== '') {
        $ts = strtotime($schedRaw);
        if ($ts !== false) { $scheduledAt = date('Y-m-d H:i:s', $ts); }
    }

    $data = [
        'title'        => clean(post('title')),
        'course_id'    => $courseId,
        'batch_id'     => $batchId,
        'platform'     => isset($platforms[(string) post('platform')]) ? post('platform') : 'meet',
        'link'         => clean(post('link')) ?: null,
        'scheduled_at' => $scheduledAt,
        'duration_min' => max(0, (int) post('duration_min')) ?: null,
        'notes'        => trim((string) post('notes')) ?: null,
        'status'       => isset($statuses[(string) post('status')]) ? post('status') : 'scheduled',
    ];

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'live-sessions', 'Updated live class #' . $editId . ' (' . $data['title'] . ')');
        set_flash('success', 'Live class updated.');
    } else {
        $newId = db_insert($table, $data);
        log_activity('create', 'live-sessions', 'Created live class #' . $newId . ' (' . $data['title'] . ')');
        set_flash('success', 'Live class scheduled.');
    }
    redirect('/admin/live-sessions');
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row   = find($table, $delId);
    if ($row) {
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'live-sessions', 'Deleted live class #' . $delId . ' (' . ($row['title'] ?? '') . ')');
        set_flash('success', 'Live class deleted.');
    }
    redirect('/admin/live-sessions');
}

/* -------------------------------------------------------------- FORM */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Live class not found.');
        redirect('/admin/live-sessions');
    }

    $curCourse   = (int) old('course_id', $row['course_id'] ?? 0);
    $curBatch    = (int) old('batch_id', $row['batch_id'] ?? 0);
    $curPlatform = (string) old('platform', $row['platform'] ?? 'meet');
    $curStatus   = (string) old('status', $row['status'] ?? 'scheduled');

    // datetime-local value for the input (prefer flashed old input).
    $schedValue = old('scheduled_at', '');
    if ($schedValue === '' && !empty($row['scheduled_at'])) {
        $ts = strtotime((string) $row['scheduled_at']);
        if ($ts !== false) { $schedValue = date('Y-m-d\TH:i', $ts); }
    }

    $o = static fn(string $k, $d = '') => e(old($k, $row[$k] ?? $d));
    $page_title = $action === 'edit' ? 'Edit Live Class' : 'Schedule Live Class';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Live classes / <?= $action === 'edit' ? 'Edit' : 'New' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('live-sessions')) ?>">← Back to list</a>
    </div>

    <form class="admin-form" method="post" action="<?= e(admin_url('live-sessions')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="save">
        <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

        <div class="grid-2" style="align-items:start;gap:1.25rem;">
            <div class="panel"><div class="panel-body">
                <h3 class="mb-3">Session</h3>

                <div class="form-group"><label class="form-label">Title <span class="req">*</span></label>
                    <input class="form-control" name="title" required value="<?= $o('title') ?>" placeholder="e.g. Doubt-clearing session — Algebra"></div>

                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Course</label>
                        <select class="form-select" name="course_id">
                            <option value="">None</option>
                            <?php foreach ($courses as $cid => $ctitle): ?>
                                <option value="<?= (int) $cid ?>" <?= $curCourse === (int) $cid ? 'selected' : '' ?>><?= e($ctitle) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$courses): ?><small class="form-hint">No courses yet — this session can stand alone.</small><?php endif; ?>
                    </div>
                    <div class="form-group"><label class="form-label">Batch / cohort</label>
                        <select class="form-select" name="batch_id">
                            <option value="">None</option>
                            <?php foreach ($batchOpts as $bid => $blabel): ?>
                                <option value="<?= (int) $bid ?>" <?= $curBatch === (int) $bid ? 'selected' : '' ?>><?= e($blabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$batchOpts): ?><small class="form-hint">No course batches available yet.</small><?php endif; ?>
                    </div>
                </div>

                <div class="form-group"><label class="form-label">Notes</label>
                    <textarea class="form-textarea" name="notes" style="min-height:110px;" placeholder="Agenda, topics or joining instructions shown to learners."><?= e($row['notes'] ?? old('notes', '')) ?></textarea></div>
            </div></div>

            <div class="panel"><div class="panel-body">
                <h3 class="mb-3">Schedule &amp; access</h3>

                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Platform</label>
                        <select class="form-select" name="platform">
                            <?php foreach ($platforms as $k => $l): ?>
                                <option value="<?= e($k) ?>" <?= $curPlatform === $k ? 'selected' : '' ?>><?= e($l) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <?php foreach ($statuses as $k => $l): ?>
                                <option value="<?= e($k) ?>" <?= $curStatus === $k ? 'selected' : '' ?>><?= e($l) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                </div>

                <div class="form-group"><label class="form-label">Join link</label>
                    <input class="form-control" type="url" name="link" value="<?= $o('link') ?>" placeholder="https://…"></div>

                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Scheduled at</label>
                        <input class="form-control" type="datetime-local" name="scheduled_at" value="<?= e($schedValue) ?>"></div>
                    <div class="form-group"><label class="form-label">Duration (min)</label>
                        <input class="form-control" type="number" name="duration_min" min="0" value="<?= $o('duration_min') ?>" placeholder="e.g. 60"></div>
                </div>

                <div class="form-actions mt-3">
                    <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Schedule' ?> class</button>
                    <a class="btn btn-ghost" href="<?= e(admin_url('live-sessions')) ?>">Cancel</a>
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
$page_title   = 'Live Classes';
$search       = trim((string) get('q', ''));
$statusFilter = (string) get('status', '');
$courseFilter = (int) get('course', 0);

$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= ' AND l.title LIKE :q';
    $params[':q'] = '%' . $search . '%';
}
if (isset($statuses[$statusFilter])) {
    $where .= ' AND l.status = :st';
    $params[':st'] = $statusFilter;
}
if (isset($courses[$courseFilter])) {
    $where .= ' AND l.course_id = :co';
    $params[':co'] = $courseFilter;
}

$p = paginate(
    "SELECT l.*, c.title AS course_title
       FROM live_sessions l
       LEFT JOIN courses c ON c.id = l.course_id
      WHERE $where
      ORDER BY l.scheduled_at DESC, l.id DESC",
    $params, 15
);

$counts = [
    'all'       => db_count('live_sessions'),
    'upcoming'  => db_count('live_sessions', "status = 'scheduled' AND scheduled_at >= NOW()"),
    'scheduled' => db_count('live_sessions', "status = 'scheduled'"),
    'live'      => db_count('live_sessions', "status = 'live'"),
];

$hasFilter = $search !== '' || $statusFilter !== '' || $courseFilter !== 0;

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Live Classes</h1><span class="muted"><?= (int) $counts['all'] ?> sessions</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('live-sessions?action=create')) ?>">+ Schedule Class</a>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon bg-violet"><?= lucide('video') ?></div><div><div class="stat-value"><?= (int) $counts['all'] ?></div><div class="stat-label">Sessions</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-blue"><?= lucide('calendar-clock') ?></div><div><div class="stat-value"><?= (int) $counts['upcoming'] ?></div><div class="stat-label">Upcoming</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-amber"><?= lucide('calendar') ?></div><div><div class="stat-value"><?= (int) $counts['scheduled'] ?></div><div class="stat-label">Scheduled</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-green"><?= lucide('radio') ?></div><div><div class="stat-value"><?= (int) $counts['live'] ?></div><div class="stat-label">Live now</div></div></div>
</div>

<div class="panel"><div class="panel-body">
    <div class="data-toolbar">
        <form class="search" method="get" action="<?= e(admin_url('live-sessions')) ?>">
            <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search title…">
        </form>
        <form method="get" action="<?= e(admin_url('live-sessions')) ?>" class="flex" style="gap:.5rem;">
            <select class="form-select" name="status" onchange="this.form.submit()">
                <option value="">All statuses</option>
                <?php foreach ($statuses as $k => $l): ?><option value="<?= e($k) ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
            </select>
            <select class="form-select" name="course" onchange="this.form.submit()">
                <option value="">All courses</option>
                <?php foreach ($courses as $cid => $ctitle): ?><option value="<?= (int) $cid ?>" <?= $courseFilter === (int) $cid ? 'selected' : '' ?>><?= e($ctitle) ?></option><?php endforeach; ?>
            </select>
        </form>
        <?php if ($hasFilter): ?><a class="btn btn-ghost btn-sm" href="<?= e(admin_url('live-sessions')) ?>">Clear</a><?php endif; ?>
    </div>

    <?php if ($p['items']): ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th>Title</th><th>Course</th><th>Platform</th><th>Scheduled</th><th>Duration</th><th>Status</th><th>Link</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($p['items'] as $r):
            $sched = format_datetime($r['scheduled_at'] ?? null);
            $lnk   = trim((string) ($r['link'] ?? ''));
        ?>
            <tr>
                <td><strong><?= e($r['title']) ?></strong></td>
                <td><?= $r['course_title'] ? e($r['course_title']) : '<span class="text-muted">—</span>' ?></td>
                <td><?= e($platforms[$r['platform']] ?? ucfirst((string) $r['platform'])) ?></td>
                <td><?= $sched !== '' ? e($sched) : '<span class="text-muted">—</span>' ?></td>
                <td><?= $r['duration_min'] ? (int) $r['duration_min'] . ' min' : '<span class="text-muted">—</span>' ?></td>
                <td><span class="pill <?= e($statusPill[$r['status']] ?? 'pill-gray') ?>"><?= e($statuses[$r['status']] ?? ucfirst((string) $r['status'])) ?></span></td>
                <td>
                    <?php if ($lnk !== '' && preg_match('~^https?://~i', $lnk)): ?>
                        <a href="<?= e($lnk) ?>" target="_blank" rel="noopener noreferrer" title="Open in new tab"><?= lucide('external-link') ?> Open</a>
                    <?php elseif ($lnk !== ''): ?>
                        <span class="text-muted"><?= e($lnk) ?></span>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td><div class="actions">
                    <a class="icon-btn" href="<?= e(admin_url('live-sessions?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                    <form method="post" action="<?= e(admin_url('live-sessions')) ?>" data-confirm="Delete this live class? This cannot be undone." style="display:inline;">
                        <?= csrf_field() ?><input type="hidden" name="_do" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
                    </form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?= $p['links'] ?>
    <?php else: ?>
        <div class="empty-state"><div class="icon"><?= lucide('video') ?></div>
            <?= $hasFilter ? 'No live classes match your filters. ' : 'No live classes yet. ' ?>
            <a href="<?= e(admin_url('live-sessions?action=create')) ?>">Schedule one</a>.</div>
    <?php endif; ?>
</div></div>

<?php include __DIR__ . '/partials/foot.php'; ?>
