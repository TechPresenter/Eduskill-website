<?php
/**
 * =============================================================================
 *  Admin — Leave Requests (apply -> manager approve/reject -> HR record)
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table    = 'leave_requests';
$action   = get('action', 'list');
$types    = leave_type_labels();
$statuses = leave_status_labels();
$empOpts  = employee_options('active');

/* -------------------------------------------------------------- CREATE (apply) */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $empId = (int) post('employee_id', 0);
    $from  = clean(post('from_date'));
    $to    = clean(post('to_date'));
    if (!isset($empOpts[$empId]) || $from === '' || $to === '') {
        flash_old($_POST);
        set_flash('error', 'Employee and both dates are required.');
        redirect('/admin/leave-requests?action=create');
    }
    if (strtotime($to) < strtotime($from)) { $tmp = $from; $from = $to; $to = $tmp; }
    $days = (float) post('days', 0);
    if ($days <= 0) { $days = (int) floor((strtotime($to) - strtotime($from)) / 86400) + 1; }

    $newId = db_insert($table, [
        'employee_id' => $empId,
        'leave_type'  => isset($types[(string) post('leave_type')]) ? post('leave_type') : 'casual',
        'from_date'   => $from,
        'to_date'     => $to,
        'days'        => $days,
        'reason'      => clean(post('reason')) ?: null,
        'status'      => 'pending',
    ]);
    log_activity('create', 'leave_requests', 'Leave request #' . $newId . ' for employee #' . $empId);
    set_flash('success', 'Leave request submitted (pending approval).');
    redirect('/admin/leave-requests');
}

/* -------------------------------------------------------------- REVIEW (approve/reject) */
if (is_post() && post('_do') === 'review') {
    require_csrf();
    $rid  = (int) post('id', 0);
    $dec  = post('decision');
    $row  = find($table, $rid);
    if ($row && in_array($dec, ['approved', 'rejected'], true)) {
        db_update($table, [
            'status'      => $dec,
            'review_note' => clean(post('review_note')) ?: null,
            'reviewed_by' => (int) current_user_id(),
            'reviewed_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', [':id' => $rid]);
        log_activity('update', 'leave_requests', ucfirst($dec) . ' leave request #' . $rid);
        set_flash('success', 'Leave request ' . $dec . '.');
    }
    redirect('/admin/leave-requests' . (get('status') ? '?status=' . get('status') : ''));
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $rid = (int) post('id', 0);
    if (find($table, $rid)) {
        db_delete($table, 'id = :id', [':id' => $rid]);
        log_activity('delete', 'leave_requests', 'Deleted leave request #' . $rid);
        set_flash('success', 'Leave request deleted.');
    }
    redirect('/admin/leave-requests');
}

/* -------------------------------------------------------------- APPLY FORM */
if ($action === 'create') {
    $o = static fn(string $k, $d = '') => e(old($k, $d));
    $page_title = 'Apply for Leave';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1>Apply for Leave</h1><span class="muted">Create a leave request</span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('leave-requests')) ?>">&larr; Back to list</a>
    </div>
    <form class="admin-form" method="post" action="<?= e(admin_url('leave-requests')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="save">
        <div class="panel" style="max-width:720px;"><div class="panel-body">
            <div class="form-group"><label class="form-label">Employee <span class="req">*</span></label>
                <select class="form-select" name="employee_id" required>
                    <option value="">Select employee…</option>
                    <?php foreach ($empOpts as $eid => $label): ?><option value="<?= (int) $eid ?>" <?= (int) old('employee_id') === (int) $eid ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select></div>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Leave type</label>
                    <select class="form-select" name="leave_type">
                        <?php foreach ($types as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label class="form-label">Days</label>
                    <input class="form-control" type="number" step="0.5" min="0" name="days" id="lv-days" placeholder="Auto from dates"></div>
            </div>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">From <span class="req">*</span></label>
                    <input class="form-control" type="date" name="from_date" id="lv-from" required value="<?= $o('from_date', date('Y-m-d')) ?>"></div>
                <div class="form-group"><label class="form-label">To <span class="req">*</span></label>
                    <input class="form-control" type="date" name="to_date" id="lv-to" required value="<?= $o('to_date', date('Y-m-d')) ?>"></div>
            </div>
            <div class="form-group"><label class="form-label">Reason</label>
                <textarea class="form-textarea" name="reason" style="min-height:90px;"><?= $o('reason') ?></textarea></div>
            <div class="form-actions"><button class="btn btn-primary" type="submit">Submit request</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('leave-requests')) ?>">Cancel</a></div>
        </div></div>
    </form>
    <script>
    (function () {
        var f = document.getElementById('lv-from'), t = document.getElementById('lv-to'), d = document.getElementById('lv-days');
        function calc() { if (!f.value || !t.value) return; var a = new Date(f.value), b = new Date(t.value); if (b < a) return; d.value = Math.round((b - a) / 86400000) + 1; }
        f.addEventListener('change', calc); t.addEventListener('change', calc); calc();
    })();
    </script>
    <?php clear_old(); include __DIR__ . '/partials/foot.php'; exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Leave Requests';
$statFilter = (string) get('status', '');
$where = '1=1'; $params = [];
if (isset($statuses[$statFilter])) { $where .= ' AND l.status = :st'; $params[':st'] = $statFilter; }

$p = paginate(
    "SELECT l.*, e.name AS emp_name, e.employee_code
       FROM leave_requests l
       JOIN employees e ON e.id = l.employee_id
      WHERE $where
      ORDER BY (l.status = 'pending') DESC, l.from_date DESC",
    $params, 20
);
$counts = [
    'pending'  => db_count('leave_requests', "status = 'pending'"),
    'approved' => db_count('leave_requests', "status = 'approved'"),
    'today'    => (int) db_value("SELECT COUNT(*) FROM leave_requests WHERE status='approved' AND CURDATE() BETWEEN from_date AND to_date"),
];
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Leave Requests</h1><span class="muted"><?= (int) $counts['pending'] ?> pending · <?= (int) $counts['today'] ?> on leave today</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('leave-requests?action=create')) ?>">+ Apply for Leave</a>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon bg-amber"><?= lucide('clock') ?></div><div><div class="stat-value"><?= (int) $counts['pending'] ?></div><div class="stat-label">Pending</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-green"><?= lucide('circle-check') ?></div><div><div class="stat-value"><?= (int) $counts['approved'] ?></div><div class="stat-label">Approved</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-blue"><?= lucide('plane') ?></div><div><div class="stat-value"><?= (int) $counts['today'] ?></div><div class="stat-label">On leave today</div></div></div>
</div>

<div class="panel"><div class="panel-body">
    <div class="data-toolbar">
        <div class="an-range" style="display:inline-flex;gap:.3rem;">
            <a class="btn btn-sm <?= $statFilter === '' ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(admin_url('leave-requests')) ?>">All</a>
            <?php foreach ($statuses as $k => $l): ?>
                <a class="btn btn-sm <?= $statFilter === $k ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(admin_url('leave-requests?status=' . $k)) ?>"><?= $l ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($p['items']): ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th>Employee</th><th>Type</th><th>Dates</th><th>Days</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($p['items'] as $r): ?>
            <tr>
                <td><strong><?= e($r['emp_name']) ?></strong><br><small class="text-muted"><?= e($r['employee_code']) ?></small></td>
                <td><span class="pill pill-gray"><?= e($types[$r['leave_type']] ?? $r['leave_type']) ?></span></td>
                <td><?= e(format_date($r['from_date'])) ?> &rarr; <?= e(format_date($r['to_date'])) ?><?= $r['reason'] ? '<br><small class="text-muted">' . e($r['reason']) . '</small>' : '' ?></td>
                <td><strong><?= e(rtrim(rtrim((string) $r['days'], '0'), '.')) ?></strong></td>
                <td>
                    <span class="pill <?= e(leave_status_pill($r['status'])) ?>"><?= e($statuses[$r['status']] ?? $r['status']) ?></span>
                    <?php if ($r['status'] !== 'pending' && $r['review_note']): ?><br><small class="text-muted"><?= e($r['review_note']) ?></small><?php endif; ?>
                </td>
                <td><div class="actions">
                    <?php if ($r['status'] === 'pending'): ?>
                        <form method="post" action="<?= e(admin_url('leave-requests')) ?>" style="display:inline;">
                            <?= csrf_field() ?><input type="hidden" name="_do" value="review"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="decision" value="approved">
                            <button class="icon-btn" type="submit" title="Approve" style="color:#308629;"><?= lucide('check') ?></button>
                        </form>
                        <form method="post" action="<?= e(admin_url('leave-requests')) ?>" data-confirm="Reject this leave request?" style="display:inline;">
                            <?= csrf_field() ?><input type="hidden" name="_do" value="review"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="decision" value="rejected">
                            <button class="icon-btn danger" type="submit" title="Reject"><?= lucide('x') ?></button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="<?= e(admin_url('leave-requests')) ?>" data-confirm="Delete this leave request?" style="display:inline;">
                        <?= csrf_field() ?><input type="hidden" name="_do" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
                    </form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?= $p['links'] ?>
    <?php else: ?>
        <div class="empty-state"><div class="icon"><?= lucide('plane') ?></div>No leave requests<?= $statFilter ? ' with this status' : '' ?>. <a href="<?= e(admin_url('leave-requests?action=create')) ?>">Apply for one</a>.</div>
    <?php endif; ?>
</div></div>

<?php include __DIR__ . '/partials/foot.php'; ?>
