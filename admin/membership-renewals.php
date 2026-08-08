<?php
/**
 * =============================================================================
 *  Admin — Membership Renewals & Payments
 * =============================================================================
 *  Lists every renewal/payment record (offline + Cashfree), lets an admin
 *  record an offline renewal, and transition a payment's status (which extends
 *  the membership when it becomes paid). Standard admin pattern throughout.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'membership_renewals';
$action = get('action', 'list');

$methods  = ['manual' => 'Manual', 'offline' => 'Offline', 'cashfree' => 'Cashfree', 'other' => 'Other'];
$statuses = ['pending' => 'Pending', 'paid' => 'Paid', 'failed' => 'Failed', 'refunded' => 'Refunded', 'cancelled' => 'Cancelled'];
$statusPill = static fn (string $s): string => match ($s) {
    'paid' => 'pill-green', 'pending' => 'pill-amber', 'refunded' => 'pill-blue',
    'failed', 'cancelled' => 'pill-red', default => 'pill-gray',
};

/* -------------------------------------------------------------- RECORD (offline) */
if (is_post() && post('_do') === 'record') {
    require_csrf();
    $q = clean(post('member'));
    $member = db_row(
        // Distinct placeholder per slot — PDO EMULATE_PREPARES=false binds each
        // name once, so the reused :q made offline renewal recording always fail.
        'SELECT * FROM members WHERE email = :q1 OR member_code = :q2 OR id = :qid LIMIT 1',
        [':q1' => $q, ':q2' => $q, ':qid' => ctype_digit($q) ? (int) $q : 0]
    );
    $planId = (int) post('plan_id', 0);
    $plan   = membership_plan($planId);
    if (!$member) {
        set_flash('error', 'No member found for “' . $q . '”. Enter their email, membership ID or numeric id.');
        redirect('/admin/membership-renewals');
    }
    if (!$plan) {
        set_flash('error', 'Choose a valid plan.');
        redirect('/admin/membership-renewals');
    }
    $renewalId = membership_create_renewal((int) $member['id'], $planId, [
        'amount'     => (float) post('amount', $plan['price']),
        'method'     => isset($methods[(string) post('method')]) ? (string) post('method') : 'manual',
        'status'     => 'paid',
        'note'       => clean(post('note')) ?: null,
        'created_by' => $_SESSION['user']['id'] ?? null,
    ]);
    if ($renewalId) {
        membership_apply_renewal($renewalId, $_SESSION['user']['id'] ?? null);
        log_activity('create', 'membership-renewals', 'Recorded renewal #' . $renewalId . ' for member #' . $member['id']);
        set_flash('success', 'Renewal recorded for ' . $member['name'] . ' and membership extended.');
    } else {
        set_flash('error', 'Could not record the renewal.');
    }
    redirect('/admin/membership-renewals');
}

/* -------------------------------------------------------------- MARK status */
if (is_post() && post('_do') === 'mark') {
    require_csrf();
    $rid    = (int) post('id', 0);
    $status = (string) post('status', '');
    $row    = find($table, $rid);
    if (!$row || !isset($statuses[$status])) {
        set_flash('error', 'Invalid request.');
        redirect('/admin/membership-renewals');
    }
    membership_mark_renewal($rid, $status, null, $_SESSION['user']['id'] ?? null);
    log_activity('update', 'membership-renewals', 'Renewal #' . $rid . ' → ' . $status);
    set_flash('success', 'Payment marked as ' . $statuses[$status] . ($status === 'paid' ? ' (membership extended).' : '.'));
    redirect('/admin/membership-renewals');
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $rid = (int) post('id', 0);
    if (find($table, $rid)) {
        db_delete($table, 'id = :id', [':id' => $rid]);
        log_activity('delete', 'membership-renewals', 'Deleted renewal #' . $rid);
        set_flash('success', 'Renewal record deleted.');
    }
    redirect('/admin/membership-renewals');
}

/* -------------------------------------------------------------- LIST */
$page_title    = 'Membership Renewals';
$search        = trim((string) get('q', ''));
$statusFilter  = (string) get('status', '');
$methodFilter  = (string) get('method', '');

$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', m.name, m.email, m.member_code, r.order_id) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
if (isset($statuses[$statusFilter])) {
    $where .= ' AND r.status = :st';
    $params[':st'] = $statusFilter;
}
if (isset($methods[$methodFilter])) {
    $where .= ' AND r.method = :mt';
    $params[':mt'] = $methodFilter;
}

$p = paginate(
    "SELECT r.*, m.name AS member_name, m.member_code, m.email AS member_email, pl.name AS plan_name
       FROM membership_renewals r
       JOIN members m ON m.id = r.member_id
  LEFT JOIN membership_plans pl ON pl.id = r.plan_id
      WHERE $where
   ORDER BY r.id DESC",
    $params, 20
);

$collected  = (float) db_value("SELECT COALESCE(SUM(amount),0) FROM membership_renewals WHERE status = 'paid'");
$thisMonth  = (float) db_value("SELECT COALESCE(SUM(amount),0) FROM membership_renewals WHERE status = 'paid' AND YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())");
$pendingCnt = db_count($table, "status = 'pending'");
$paidCnt    = db_count($table, "status = 'paid'");
$plans      = membership_plans_all(false);

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Membership Renewals</h1><span class="muted"><?= (int) $p['total'] ?> records</span></div>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon bg-green"><?= lucide('indian-rupee') ?></div><div><div class="stat-value"><?= e(money($collected, '₹', 0)) ?></div><div class="stat-label">Total collected</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-blue"><?= lucide('calendar') ?></div><div><div class="stat-value"><?= e(money($thisMonth, '₹', 0)) ?></div><div class="stat-label">This month</div></div></div>
    <a class="stat-card" href="<?= e(admin_url('membership-renewals?status=pending')) ?>"><div class="stat-icon bg-amber"><?= lucide('clock') ?></div><div><div class="stat-value"><?= (int) $pendingCnt ?></div><div class="stat-label">Pending</div></div></a>
    <a class="stat-card" href="<?= e(admin_url('membership-renewals?status=paid')) ?>"><div class="stat-icon bg-violet"><?= lucide('check-check') ?></div><div><div class="stat-value"><?= (int) $paidCnt ?></div><div class="stat-label">Paid</div></div></a>
</div>

<!-- Record an offline renewal -->
<div class="panel"><div class="panel-body">
    <h3 class="mb-2">Record an offline renewal</h3>
    <form method="post" action="<?= e(admin_url('membership-renewals')) ?>" class="grid-auto" style="gap:.75rem;align-items:end;">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="record">
        <div class="form-group" style="margin:0;"><label class="form-label">Member</label>
            <input class="form-control" name="member" placeholder="email / membership ID / id" required></div>
        <div class="form-group" style="margin:0;"><label class="form-label">Plan</label>
            <select class="form-select" name="plan_id" required>
                <option value="">Select…</option>
                <?php foreach ($plans as $pl): ?><option value="<?= (int) $pl['id'] ?>" data-price="<?= e($pl['price']) ?>"><?= e($pl['name']) ?> (<?= e(money($pl['price'], '₹', 0)) ?>)</option><?php endforeach; ?>
            </select></div>
        <div class="form-group" style="margin:0;"><label class="form-label">Amount (₹)</label>
            <input class="form-control" type="number" step="0.01" name="amount" value=""></div>
        <div class="form-group" style="margin:0;"><label class="form-label">Method</label>
            <select class="form-select" name="method"><option value="offline">Offline</option><option value="manual">Manual</option><option value="other">Other</option></select></div>
        <div class="form-group" style="margin:0;"><label class="form-label">Note</label>
            <input class="form-control" name="note" placeholder="Receipt / ref"></div>
        <button class="btn btn-primary" type="submit" style="height:fit-content;"><?= lucide('plus') ?> Record</button>
    </form>
</div></div>

<div class="panel"><div class="panel-body">
    <div class="data-toolbar">
        <form class="search" method="get" action="<?= e(admin_url('membership-renewals')) ?>">
            <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search member, ID, order…"></form>
        <form method="get" action="<?= e(admin_url('membership-renewals')) ?>" class="flex" style="gap:.5rem;">
            <select class="form-select" name="status" onchange="this.form.submit()">
                <option value="">All statuses</option>
                <?php foreach ($statuses as $k => $l): ?><option value="<?= e($k) ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
            </select>
            <select class="form-select" name="method" onchange="this.form.submit()">
                <option value="">All methods</option>
                <?php foreach ($methods as $k => $l): ?><option value="<?= e($k) ?>" <?= $methodFilter === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
            </select>
        </form>
        <?php if ($search !== '' || $statusFilter !== '' || $methodFilter !== ''): ?><a class="btn btn-ghost btn-sm" href="<?= e(admin_url('membership-renewals')) ?>">Clear</a><?php endif; ?>
    </div>

    <?php if ($p['items']): ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th>Date</th><th>Member</th><th>Plan</th><th>Amount</th><th>Method</th><th>Period</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($p['items'] as $r): ?>
            <tr>
                <td><?= e(format_date($r['created_at'])) ?></td>
                <td><a href="<?= e(admin_url('members?action=view&id=' . $r['member_id'])) ?>"><strong><?= e($r['member_name']) ?></strong></a><br><small class="text-muted"><?= e($r['member_code']) ?></small></td>
                <td><?= e($r['plan_name'] ?? '—') ?></td>
                <td><?= e(money($r['amount'], '₹', 2)) ?></td>
                <td><?= e($methods[$r['method']] ?? $r['method']) ?><?= $r['order_id'] ? '<br><small class="text-muted">' . e($r['order_id']) . '</small>' : '' ?></td>
                <td><?= $r['period_end'] ? e(format_date($r['period_start'], 'd/m/y')) . '–' . e(format_date($r['period_end'], 'd/m/y')) : '—' ?></td>
                <td><span class="pill <?= e($statusPill($r['status'])) ?>"><?= e($statuses[$r['status']] ?? $r['status']) ?></span></td>
                <td><div class="actions" style="justify-content:flex-end;">
                    <?php if ($r['status'] === 'pending'): ?>
                        <form method="post" action="<?= e(admin_url('membership-renewals')) ?>" style="display:inline;">
                            <?= csrf_field() ?><input type="hidden" name="_do" value="mark"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="status" value="paid">
                            <button class="icon-btn" type="submit" title="Mark paid"><?= lucide('check') ?></button>
                        </form>
                    <?php elseif ($r['status'] === 'paid'): ?>
                        <form method="post" action="<?= e(admin_url('membership-renewals')) ?>" style="display:inline;" data-confirm="Mark this payment refunded? If this renewal set the member's current term, that term will be rolled back.">
                            <?= csrf_field() ?><input type="hidden" name="_do" value="mark"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="status" value="refunded">
                            <button class="icon-btn" type="submit" title="Refund"><?= lucide('undo-2') ?></button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="<?= e(admin_url('membership-renewals')) ?>" style="display:inline;" data-confirm="Delete this renewal record?">
                        <?= csrf_field() ?><input type="hidden" name="_do" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
                    </form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?= $p['links'] ?>
    <?php else: ?>
        <div class="empty-state"><div class="icon"><?= lucide('receipt') ?></div>No renewals recorded yet.</div>
    <?php endif; ?>
</div></div>

<?php include __DIR__ . '/partials/foot.php'; ?>
