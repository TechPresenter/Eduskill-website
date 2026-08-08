<?php
/**
 * =============================================================================
 *  Admin — Notices (targeted board + email / SMS dispatch)
 * =============================================================================
 *  list · create · edit · dispatch · delete. A notice targets an audience
 *  (all students / a school / a batch / a course) and can optionally be pushed
 *  out over email and SMS on demand. Standard admin pattern: CSRF-guarded POST
 *  handlers, prepared statements, pagination + search + filters, stat cards,
 *  activity logging, flash + redirect.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'notices';
$action = get('action', 'list');
$id     = (int) get('id', 0);

$audiences = ['all' => 'All students', 'school' => 'A school', 'batch' => 'A batch', 'course' => 'A course'];
$statuses  = ['draft' => 'Draft', 'published' => 'Published'];

/** Pill class for a notice status. */
$statusPill = static fn(string $s): string => $s === 'published' ? 'pill-green' : 'pill-amber';

/* =============================================================================
 |  SAVE (create / edit)
 |============================================================================*/
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);
    $old    = $editId ? find($table, $editId) : null;
    if ($editId && !$old) {
        set_flash('error', 'Notice not found.');
        redirect('/admin/notices');
    }

    $title = clean(post('title'));
    if ($title === '') {
        flash_old($_POST);
        set_flash('error', 'Notice title is required.');
        redirect('/admin/notices?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    $audience = isset($audiences[(string) post('audience')]) ? (string) post('audience') : 'all';

    // Keep only the target that matches the chosen audience, and validate it.
    $schoolId = (int) post('school_id', 0) ?: null;
    $batchId  = (int) post('batch_id', 0) ?: null;
    $courseId = (int) post('course_id', 0) ?: null;
    if ($audience === 'school') {
        $batchId = $courseId = null;
        if (!$schoolId || !find('schools', $schoolId)) { $schoolId = null; }
    } elseif ($audience === 'batch') {
        $schoolId = $courseId = null;
        if (!$batchId || !find('school_batches', $batchId)) { $batchId = null; }
    } elseif ($audience === 'course') {
        $schoolId = $batchId = null;
        if (!$courseId || !find('courses', $courseId)) { $courseId = null; }
    } else {
        $schoolId = $batchId = $courseId = null;
    }

    $status = post('status') === 'published' ? 'published' : 'draft';

    $data = [
        'title'        => $title,
        'body'         => trim((string) post('body')) ?: null,
        'audience'     => $audience,
        'school_id'    => $schoolId,
        'batch_id'     => $batchId,
        'course_id'    => $courseId,
        'notify_email' => post('notify_email') ? 1 : 0,
        'notify_sms'   => post('notify_sms') ? 1 : 0,
        'pinned'       => post('pinned') ? 1 : 0,
        'status'       => $status,
    ];
    // Stamp published_at when publishing; keep the original stamp on re-edits.
    if ($status === 'published') {
        $data['published_at'] = ($old && !empty($old['published_at']))
            ? $old['published_at']
            : date('Y-m-d H:i:s');
    } else {
        $data['published_at'] = null;
    }

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'notices', 'Updated notice #' . $editId);
        set_flash('success', 'Notice updated.');
        redirect('/admin/notices');
    }

    $data['created_by'] = $_SESSION['user']['id'] ?? null;
    $newId = db_insert($table, $data);
    log_activity('create', 'notices', 'Created notice #' . $newId . ' (' . $title . ')');
    set_flash('success', 'Notice created.');
    redirect('/admin/notices');
}

/* =============================================================================
 |  DISPATCH (email / SMS to the notice's audience)
 |============================================================================*/
if (is_post() && post('_do') === 'dispatch') {
    require_csrf();
    $dispId = (int) post('id', 0);
    $row    = find($table, $dispId);
    if (!$row) {
        set_flash('error', 'Notice not found.');
        redirect('/admin/notices');
    }
    $r = notice_dispatch($dispId);
    log_activity('dispatch', 'notices', 'Dispatched notice #' . $dispId . ' (' . ($row['title'] ?? '') . ')');
    set_flash('success', "Emailed {$r['email']}, SMS {$r['sms']} of {$r['total']}.");
    redirect('/admin/notices');
}

/* =============================================================================
 |  DELETE
 |============================================================================*/
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row   = find($table, $delId);
    if ($row) {
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'notices', 'Deleted notice #' . $delId . ' (' . ($row['title'] ?? '') . ')');
        set_flash('success', 'Notice deleted.');
    }
    redirect('/admin/notices');
}

/* =============================================================================
 |  CREATE / EDIT FORM
 |============================================================================*/
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Notice not found.');
        redirect('/admin/notices');
    }
    $o = static fn(string $k, $d = '') => e(old($k, $row[$k] ?? $d));

    // Checkbox state: after a validation error use the posted input (unchecked
    // boxes are absent from POST), otherwise fall back to the stored row.
    $hasOld  = isset($_SESSION['_old']);
    $checked = static function (string $k) use ($row, $hasOld): string {
        $v = $hasOld ? ($_SESSION['_old'][$k] ?? 0) : ($row[$k] ?? 0);
        return !empty($v) ? 'checked' : '';
    };

    $curAudience = (string) old('audience', $row['audience'] ?? 'all');
    $curStatus   = (string) old('status', $row['status'] ?? 'draft');

    $schools = school_options();
    $courses = course_options();
    $batches = db_all(
        "SELECT b.id, b.name, s.name AS school_name
           FROM school_batches b
           LEFT JOIN schools s ON s.id = b.school_id
          ORDER BY s.name, b.name"
    );

    $page_title = $action === 'edit' ? 'Edit Notice' : 'New Notice';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Notices / <?= $action === 'edit' ? 'Edit' : 'New' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('notices')) ?>">&larr; Back to list</a>
    </div>

    <form class="admin-form" method="post" action="<?= e(admin_url('notices')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="save">
        <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

        <div class="grid-2" style="align-items:start;gap:1.25rem;">
            <div class="panel"><div class="panel-body">
                <h3 class="mb-3">Notice content</h3>
                <div class="form-group"><label class="form-label">Title <span class="req">*</span></label>
                    <input class="form-control" name="title" required value="<?= $o('title') ?>"></div>
                <div class="form-group"><label class="form-label">Body</label>
                    <textarea class="form-textarea" name="body" style="min-height:180px;" placeholder="Write the notice…"><?= e(old('body', $row['body'] ?? '')) ?></textarea>
                    <small class="form-hint">Plain text. Line breaks are preserved in emails and on the board.</small>
                </div>
            </div></div>

            <div class="panel"><div class="panel-body">
                <h3 class="mb-3">Audience &amp; delivery</h3>
                <div class="form-group"><label class="form-label">Audience</label>
                    <select class="form-select" name="audience" id="notice-audience" onchange="noticeAudience(this.value)">
                        <?php foreach ($audiences as $k => $l): ?><option value="<?= e($k) ?>" <?= $curAudience === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" data-aud="school" id="aud-school"><label class="form-label">School</label>
                    <select class="form-select" name="school_id">
                        <option value="">Select school…</option>
                        <?php foreach ($schools as $sid => $sname): ?><option value="<?= (int) $sid ?>" <?= (int) old('school_id', $row['school_id'] ?? 0) === (int) $sid ? 'selected' : '' ?>><?= e($sname) ?></option><?php endforeach; ?>
                    </select>
                    <?php if (!$schools): ?><small class="form-hint">No schools yet.</small><?php endif; ?>
                </div>

                <div class="form-group" data-aud="batch" id="aud-batch"><label class="form-label">Batch</label>
                    <select class="form-select" name="batch_id">
                        <option value="">Select batch…</option>
                        <?php foreach ($batches as $b): ?><option value="<?= (int) $b['id'] ?>" <?= (int) old('batch_id', $row['batch_id'] ?? 0) === (int) $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?><?= $b['school_name'] ? ' — ' . e($b['school_name']) : '' ?></option><?php endforeach; ?>
                    </select>
                    <?php if (!$batches): ?><small class="form-hint">No batches yet.</small><?php endif; ?>
                </div>

                <div class="form-group" data-aud="course" id="aud-course"><label class="form-label">Course</label>
                    <select class="form-select" name="course_id">
                        <option value="">Select course…</option>
                        <?php foreach ($courses as $cid => $ctitle): ?><option value="<?= (int) $cid ?>" <?= (int) old('course_id', $row['course_id'] ?? 0) === (int) $cid ? 'selected' : '' ?>><?= e($ctitle) ?></option><?php endforeach; ?>
                    </select>
                    <?php if (!$courses): ?><small class="form-hint">No courses yet.</small><?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label">Notifications</label>
                    <label class="checkbox"><input type="checkbox" name="notify_email" value="1" <?= $checked('notify_email') ?>> Send by email on dispatch</label>
                    <label class="checkbox"><input type="checkbox" name="notify_sms" value="1" <?= $checked('notify_sms') ?>> Send by SMS on dispatch</label>
                    <small class="form-hint">Messages go out only when you press <strong>Dispatch</strong>.</small>
                </div>

                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <?php foreach ($statuses as $k => $l): ?><option value="<?= e($k) ?>" <?= $curStatus === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                        </select>
                        <small class="form-hint">Publishing stamps the publish time.</small>
                    </div>
                    <div class="form-group"><label class="form-label">Pin</label>
                        <label class="checkbox"><input type="checkbox" name="pinned" value="1" <?= $checked('pinned') ?>> Pin to top of the board</label>
                    </div>
                </div>

                <div class="form-actions mt-3">
                    <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> notice</button>
                    <a class="btn btn-ghost" href="<?= e(admin_url('notices')) ?>">Cancel</a>
                </div>
            </div></div>
        </div>
    </form>

    <script>
        function noticeAudience(v) {
            document.querySelectorAll('[data-aud]').forEach(function (el) {
                el.style.display = el.getAttribute('data-aud') === v ? '' : 'none';
            });
        }
        noticeAudience(document.getElementById('notice-audience').value);
    </script>
    <?php
    clear_old();
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* =============================================================================
 |  LIST
 |============================================================================*/
$page_title     = 'Notices';
$search         = trim((string) get('q', ''));
$audienceFilter = (string) get('audience', '');
$statusFilter   = (string) get('status', '');

$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', n.title, n.body) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
if (isset($audiences[$audienceFilter])) { $where .= ' AND n.audience = :aud'; $params[':aud'] = $audienceFilter; }
if (isset($statuses[$statusFilter]))    { $where .= ' AND n.status = :st';    $params[':st']  = $statusFilter; }

$p = paginate(
    "SELECT n.*, s.name AS school_name, b.name AS batch_name, c.title AS course_title
       FROM notices n
       LEFT JOIN schools s        ON s.id = n.school_id
       LEFT JOIN school_batches b ON b.id = n.batch_id
       LEFT JOIN courses c        ON c.id = n.course_id
      WHERE $where
      ORDER BY n.pinned DESC, COALESCE(n.published_at, n.created_at) DESC",
    $params, 15
);

$counts = [
    'total'     => db_count('notices'),
    'published' => db_count('notices', "status = 'published'"),
    'draft'     => db_count('notices', "status = 'draft'"),
    'pinned'    => db_count('notices', 'pinned = 1'),
];

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Notices</h1><span class="muted"><?= (int) $counts['total'] ?> notices</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('notices?action=create')) ?>">+ New Notice</a>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon bg-violet"><?= lucide('megaphone') ?></div><div><div class="stat-value"><?= (int) $counts['total'] ?></div><div class="stat-label">Total</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-green"><?= lucide('circle-check') ?></div><div><div class="stat-value"><?= (int) $counts['published'] ?></div><div class="stat-label">Published</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-amber"><?= lucide('file-pen') ?></div><div><div class="stat-value"><?= (int) $counts['draft'] ?></div><div class="stat-label">Drafts</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-blue"><?= lucide('pin') ?></div><div><div class="stat-value"><?= (int) $counts['pinned'] ?></div><div class="stat-label">Pinned</div></div></div>
</div>

<div class="panel"><div class="panel-body">
    <div class="data-toolbar">
        <form class="search" method="get" action="<?= e(admin_url('notices')) ?>">
            <?php if ($audienceFilter !== ''): ?><input type="hidden" name="audience" value="<?= e($audienceFilter) ?>"><?php endif; ?>
            <?php if ($statusFilter !== ''): ?><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><?php endif; ?>
            <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search title, body…">
        </form>
        <form method="get" action="<?= e(admin_url('notices')) ?>" class="flex" style="gap:.5rem;">
            <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?>
            <select class="form-select" name="audience" onchange="this.form.submit()">
                <option value="">All audiences</option>
                <?php foreach ($audiences as $k => $l): ?><option value="<?= e($k) ?>" <?= $audienceFilter === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
            </select>
            <select class="form-select" name="status" onchange="this.form.submit()">
                <option value="">All statuses</option>
                <?php foreach ($statuses as $k => $l): ?><option value="<?= e($k) ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
            </select>
        </form>
        <?php if ($search !== '' || $audienceFilter !== '' || $statusFilter !== ''): ?><a class="btn btn-ghost btn-sm" href="<?= e(admin_url('notices')) ?>">Clear</a><?php endif; ?>
    </div>

    <?php if ($p['items']): ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th>Notice</th><th>Audience</th><th>Status</th><th>Published</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($p['items'] as $r):
            $target = match ($r['audience']) {
                'school' => $r['school_name'] ?: '—',
                'batch'  => $r['batch_name'] ?: '—',
                'course' => $r['course_title'] ?: '—',
                default  => null,
            };
        ?>
            <tr>
                <td>
                    <div class="flex items-center" style="gap:.4rem;">
                        <?php if (!empty($r['pinned'])): ?><span class="text-muted" title="Pinned"><?= lucide('pin') ?></span><?php endif; ?>
                        <strong><?= e($r['title']) ?></strong>
                    </div>
                    <?php if (!empty($r['notify_email']) || !empty($r['notify_sms'])): ?>
                        <small class="text-muted"><?= !empty($r['notify_email']) ? 'Email' : '' ?><?= (!empty($r['notify_email']) && !empty($r['notify_sms'])) ? ' · ' : '' ?><?= !empty($r['notify_sms']) ? 'SMS' : '' ?></small>
                    <?php endif; ?>
                </td>
                <td><?= e($audiences[$r['audience']] ?? $r['audience']) ?><?php if ($target !== null): ?><br><small class="text-muted"><?= e($target) ?></small><?php endif; ?></td>
                <td><span class="pill <?= e($statusPill($r['status'])) ?>"><?= e($statuses[$r['status']] ?? $r['status']) ?></span></td>
                <td><?= $r['published_at'] ? e(format_datetime($r['published_at'])) : '<span class="text-muted">—</span>' ?></td>
                <td><div class="actions">
                    <a class="icon-btn" href="<?= e(admin_url('notices?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                    <form method="post" action="<?= e(admin_url('notices')) ?>" data-confirm="Dispatch this notice by email/SMS to its audience now?" style="display:inline;">
                        <?= csrf_field() ?><input type="hidden" name="_do" value="dispatch"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <button class="icon-btn" type="submit" title="Dispatch"><?= lucide('send') ?></button>
                    </form>
                    <form method="post" action="<?= e(admin_url('notices')) ?>" data-confirm="Delete this notice? This cannot be undone." style="display:inline;">
                        <?= csrf_field() ?><input type="hidden" name="_do" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
                    </form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?= $p['links'] ?>
    <?php else: ?>
        <div class="empty-state"><div class="icon"><?= lucide('megaphone') ?></div>
            <?= ($search !== '' || $audienceFilter !== '' || $statusFilter !== '') ? 'No notices match your filters. ' : 'No notices yet. ' ?>
            <a href="<?= e(admin_url('notices?action=create')) ?>">Create one</a>.</div>
    <?php endif; ?>
</div></div>

<?php include __DIR__ . '/partials/foot.php'; ?>
