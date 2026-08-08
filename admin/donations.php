<?php
/**
 * =============================================================================
 *  Admin — Donations  (INBOX module).
 *  Read-only intake list with:  list + view details + status change + delete.
 *  NO create/edit form — donations are created by the public donate flow.
 *  Shows a stat panel (total completed sum + per-status counts), a status
 *  filter (?status=), and search (?q on donor / email / transaction id).
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table    = 'donations';
$action   = get('action', 'list');
$id       = (int) get('id', 0);
$STATUSES = ['pending', 'completed', 'failed', 'refunded'];

/** Map a donation status to its admin pill colour class. */
$statusPill = static function (string $s): string {
    return [
        'completed' => 'pill-green',
        'pending'   => 'pill-amber',
        'failed'    => 'pill-red',
        'refunded'  => 'pill-blue',
    ][$s] ?? 'pill-gray';
};

/** Format a donation amount respecting its currency. Escape at output. */
$fmtAmount = static function (array $r): string {
    $cur = (string) ($r['currency'] ?? 'INR');
    if ($cur === 'INR' || $cur === '') {
        return money($r['amount'] ?? 0, '₹', 2);
    }
    return $cur . ' ' . number_format((float) ($r['amount'] ?? 0), 2);
};

/* -------------------------------------------------------------- STATUS CHANGE */
if (is_post() && post('_do') === 'status') {
    require_csrf();
    $sid    = (int) post('id', 0);
    $status = (string) post('status', '');

    if (!in_array($status, $STATUSES, true)) {
        set_flash('error', 'Invalid status selected.');
        back(admin_url('donations'));
    }

    $row = find($table, $sid);
    if ($row) {
        $data = ['status' => $status];
        // Stamp the settlement time the first time a donation is marked completed.
        if ($status === 'completed' && empty($row['donated_at'])) {
            $data['donated_at'] = date('Y-m-d H:i:s');
        }
        db_update($table, $data, 'id = :id', [':id' => $sid]);
        // Keep the campaign total in sync + issue a receipt on completion.
        if ($status === 'completed') {
            if (empty($row['receipt_no'])) {
                db_update($table, ['receipt_no' => donation_receipt_no($sid)], 'id = :id', [':id' => $sid]);
            }
            if (!empty($row['campaign_id'])) { campaign_recompute((int) $row['campaign_id']); }
            donation_send_receipt($sid);
        } elseif (!empty($row['campaign_id'])) {
            campaign_recompute((int) $row['campaign_id']); // moved off completed → drop from total
        }
        log_activity('update', 'donations', 'Set donation #' . $sid . ' status to ' . $status);
        set_flash('success', 'Donation status updated to ' . ucfirst($status) . '.');
    } else {
        set_flash('error', 'Donation not found.');
    }
    back(admin_url('donations'));
}

/* -------------------------------------------------------------- REFUND */
if (is_post() && post('_do') === 'refund') {
    require_csrf();
    $rid = (int) post('id', 0);
    $row = find($table, $rid);
    if (!$row || $row['status'] !== 'completed') {
        set_flash('error', 'Only completed donations can be refunded.');
        redirect('/admin/donations?action=view&id=' . $rid);
    }
    // Cap at what's still refundable (donation total minus prior partial refunds).
    $remaining = max(0.0, (float) $row['amount'] - (float) ($row['refund_amount'] ?? 0));
    $amount = (float) post('refund_amount', 0) ?: $remaining;
    $amount = min($amount, $remaining);
    if ($amount <= 0) {
        set_flash('error', 'This donation is already fully refunded.');
        redirect('/admin/donations?action=view&id=' . $rid);
    }
    $reason = clean(post('refund_reason'));
    $gwRefundId = null;
    $status = 'processed';

    // Attempt a gateway refund when the donation was paid online.
    if (!empty($row['gateway']) && !empty($row['gateway_payment_id']) && donation_load_gateway($row['gateway'])) {
        $res = null;
        if ($row['gateway'] === 'razorpay' && function_exists('razorpay_refund')) {
            $res = razorpay_refund($row['gateway_payment_id'], $amount);
        } elseif ($row['gateway'] === 'stripe' && function_exists('stripe_refund')) {
            $res = stripe_refund($row['gateway_payment_id'], $amount);
        } elseif ($row['gateway'] === 'paypal' && function_exists('paypal_refund')) {
            $res = paypal_refund($row['gateway_payment_id'], $amount, (string) $row['currency']);
        }
        if ($res !== null) {
            if (!empty($res['success'])) {
                $gwRefundId = $res['refund_id'] ?? null;
            } else {
                $status = 'failed';
                set_flash('error', 'Gateway refund failed: ' . ($res['message'] ?? 'unknown'));
            }
        }
    }
    donation_record_refund($rid, $amount, $reason ?: null, $gwRefundId, $status, $_SESSION['user']['id'] ?? null);
    if ($status === 'processed') {
        set_flash('success', 'Refund of ' . money($amount) . ' recorded.');
    }
    redirect('/admin/donations?action=view&id=' . $rid);
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row = find($table, $delId);
    if ($row) {
        // Capture the campaign BEFORE the row goes away: campaigns.raised_amount
        // and donor_count are denormalised columns that only campaign_recompute()
        // refreshes, and the donations FK is ON DELETE SET NULL so nothing
        // cascades. Without this, deleting a completed donation left the public
        // progress bar and campaign totals permanently overstated — the status
        // handler above already recomputes for exactly this reason.
        $campaignId = !empty($row['campaign_id']) ? (int) $row['campaign_id'] : 0;
        db_delete($table, 'id = :id', [':id' => $delId]);
        if ($campaignId) { campaign_recompute($campaignId); }
        log_activity('delete', 'donations', 'Deleted donation #' . $delId);
        set_flash('success', 'Donation deleted.');
    }
    redirect('/admin/donations');
}

/* -------------------------------------------------------------- VIEW (details) */
if ($action === 'view') {
    $row = db_row(
        "SELECT d.*, c.title AS campaign_title, c.id AS campaign_ref
         FROM donations d
         LEFT JOIN campaigns c ON c.id = d.campaign_id
         WHERE d.id = :id",
        [':id' => $id]
    );
    if (!$row) {
        set_flash('error', 'Donation not found.');
        redirect('/admin/donations');
    }

    $page_title = 'Donation #' . (int) $row['id'];
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1>Donation #<?= (int) $row['id'] ?></h1><span class="muted">Donations / Details</span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('donations')) ?>">← Back to list</a>
    </div>

    <div class="grid grid-2 gap-3" style="align-items:start;">
        <!-- Details -->
        <div class="panel">
            <div class="panel-head"><h3>Donation Details</h3></div>
            <div class="panel-body">
                <table class="admin-table">
                    <tbody>
                        <tr><th style="width:40%;">Donor</th><td><?= e($row['donor_name']) ?><?= !empty($row['is_anonymous']) ? ' <span class="pill pill-gray">Anonymous</span>' : '' ?></td></tr>
                        <tr><th>Amount</th><td><strong><?= e($fmtAmount($row)) ?></strong></td></tr>
                        <tr><th>Status</th><td><span class="pill <?= e($statusPill($row['status'])) ?>"><?= e(ucfirst($row['status'])) ?></span></td></tr>
                        <tr><th>Campaign</th><td><?= !empty($row['campaign_title']) ? '<a href="' . e(admin_url('campaigns?action=edit&id=' . (int) $row['campaign_ref'])) . '">' . e($row['campaign_title']) . '</a>' : '<span class="text-muted">General / Unrestricted</span>' ?></td></tr>
                        <tr><th>Email</th><td><?= $row['email'] !== null && $row['email'] !== '' ? '<a href="mailto:' . e($row['email']) . '">' . e($row['email']) . '</a>' : '—' ?></td></tr>
                        <tr><th>Phone</th><td><?= e($row['phone'] ?: '—') ?></td></tr>
                        <tr><th>PAN</th><td><?= e($row['pan'] ?: '—') ?></td></tr>
                        <tr><th>Address</th><td><?= e($row['address'] ?: '—') ?></td></tr>
                        <tr><th>Payment Method</th><td><?= e($row['payment_method'] ?: '—') ?></td></tr>
                        <tr><th>Transaction ID</th><td><?= e($row['transaction_id'] ?: '—') ?></td></tr>
                        <tr><th>Message</th><td><?= $row['message'] !== null && $row['message'] !== '' ? nl2br(e($row['message'])) : '—' ?></td></tr>
                        <tr><th>Donated At</th><td><?= e($row['donated_at'] ? format_datetime($row['donated_at']) : '—') ?></td></tr>
                        <tr><th>Received</th><td><?= e(format_datetime($row['created_at'])) ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Actions -->
        <div class="panel">
            <div class="panel-head"><h3>Manage</h3></div>
            <div class="panel-body">
                <form method="post" action="<?= e(admin_url('donations')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_do" value="status">
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                    <div class="form-group">
                        <label class="form-label">Change Status</label>
                        <select class="form-select" name="status">
                            <?php foreach ($STATUSES as $st): ?>
                                <option value="<?= e($st) ?>" <?= $row['status'] === $st ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn btn-primary btn-block" type="submit">Update Status</button>
                </form>

                <?php if ($row['status'] === 'completed'): ?>
                    <div class="divider" style="margin:1.25rem 0;"></div>
                    <a class="btn btn-outline btn-block mb-2" target="_blank" href="<?= e(admin_url('donation-receipt?id=' . (int) $row['id'])) ?>"><?= lucide('receipt') ?> View 80G receipt</a>
                    <form method="post" action="<?= e(admin_url('donations')) ?>" data-confirm="Process this refund? If the donation was paid online this calls the payment gateway.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_do" value="refund">
                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                        <div class="form-group"><label class="form-label">Refund amount</label>
                            <input class="form-control" type="number" step="0.01" min="0" max="<?= e($row['amount']) ?>" name="refund_amount" value="<?= e($row['amount']) ?>"></div>
                        <div class="form-group"><label class="form-label">Reason</label>
                            <input class="form-control" name="refund_reason" placeholder="Reason for refund"></div>
                        <button class="btn btn-danger btn-block" type="submit"><?= lucide('undo-2') ?> Refund donation</button>
                    </form>
                <?php endif; ?>

                <div class="divider" style="margin:1.25rem 0;"></div>

                <form method="post" action="<?= e(admin_url('donations')) ?>" data-confirm="Delete this donation record permanently?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_do" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                    <button class="btn btn-danger btn-block" type="submit">Delete Donation</button>
                </form>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Donations';

// Filters.
$statusFilter = (string) get('status', '');
if (!in_array($statusFilter, $STATUSES, true)) {
    $statusFilter = '';
}
$search = trim((string) get('q', ''));

$where  = '1=1';
$params = [];
if ($statusFilter !== '') {
    $where .= ' AND d.status = :st';
    $params[':st'] = $statusFilter;
}
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', d.donor_name, d.email, d.transaction_id) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}

$p = paginate(
    "SELECT d.*, c.title AS campaign_title
     FROM donations d
     LEFT JOIN campaigns c ON c.id = d.campaign_id
     WHERE $where
     ORDER BY d.created_at DESC",
    $params,
    15
);

// Stats (whole table, independent of the current filter).
$counts = [];
foreach (db_all("SELECT status, COUNT(*) AS c FROM donations GROUP BY status") as $rc) {
    $counts[$rc['status']] = (int) $rc['c'];
}
$totalAll     = array_sum($counts);
$completedSum = (float) db_value("SELECT COALESCE(SUM(amount), 0) FROM donations WHERE status = 'completed'");

$filters = ['' => 'All', 'pending' => 'Pending', 'completed' => 'Completed', 'failed' => 'Failed', 'refunded' => 'Refunded'];

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Donations</h1><span class="muted"><?= (int) $p['total'] ?> shown · <?= (int) $totalAll ?> total</span></div>
</div>

<!-- Stat panel -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon bg-green"><?= lucide('coins') ?></div>
        <div>
            <div class="stat-value"><?= e(money($completedSum, '₹', 0)) ?></div>
            <div class="stat-label">Total Raised (completed)</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-blue"><?= lucide('inbox') ?></div>
        <div>
            <div class="stat-value"><?= number_format($totalAll) ?></div>
            <div class="stat-label">All Donations</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-amber"><?= lucide('hourglass') ?></div>
        <div>
            <div class="stat-value"><?= number_format($counts['pending'] ?? 0) ?></div>
            <div class="stat-label">Pending</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-rose"><?= lucide('undo-2') ?></div>
        <div>
            <div class="stat-value"><?= number_format(($counts['failed'] ?? 0) + ($counts['refunded'] ?? 0)) ?></div>
            <div class="stat-label">Failed / Refunded</div>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar" style="flex-wrap:wrap;gap:.5rem;">
            <div class="flex items-center gap-1" style="flex-wrap:wrap;">
                <?php foreach ($filters as $key => $label): ?>
                    <?php
                    $count = $key === '' ? $totalAll : ($counts[$key] ?? 0);
                    $qs    = array_filter(['status' => $key, 'q' => $search], static fn($v) => $v !== '');
                    $href  = admin_url('donations' . ($qs ? '?' . http_build_query($qs) : ''));
                    $cls   = $statusFilter === $key ? 'btn-primary' : 'btn-outline';
                    ?>
                    <a class="btn btn-sm <?= $cls ?>" href="<?= e($href) ?>"><?= e($label) ?> (<?= (int) $count ?>)</a>
                <?php endforeach; ?>
            </div>
            <form class="search" method="get" action="<?= e(admin_url('donations')) ?>">
                <?php if ($statusFilter !== ''): ?><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><?php endif; ?>
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search donor, email, txn id…">
            </form>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Donor</th>
                        <th style="text-align:right;">Amount</th>
                        <th>Campaign</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td>
                            <strong><?= e($r['donor_name']) ?></strong>
                            <?= !empty($r['is_anonymous']) ? ' <span class="pill pill-gray">Anon</span>' : '' ?>
                            <?php if (!empty($r['email'])): ?><br><small class="text-muted"><?= e($r['email']) ?></small><?php endif; ?>
                        </td>
                        <td style="text-align:right;"><strong><?= e($fmtAmount($r)) ?></strong></td>
                        <td><?= !empty($r['campaign_title']) ? e($r['campaign_title']) : '<span class="text-muted">General</span>' ?></td>
                        <td><?= e($r['payment_method'] ?: '—') ?></td>
                        <td>
                            <form method="post" action="<?= e(admin_url('donations')) ?>" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_do" value="status">
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <select class="form-select form-select-sm" name="status" onchange="this.form.submit()" style="min-width:130px;padding:.3rem .5rem;">
                                    <?php foreach ($STATUSES as $st): ?>
                                        <option value="<?= e($st) ?>" <?= $r['status'] === $st ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                        <td><?= e(format_date($r['donated_at'] ?: $r['created_at'], 'd M Y')) ?></td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('donations?action=view&id=' . (int) $r['id'])) ?>" title="View details"><?= lucide('eye') ?></a>
                                <form method="post" action="<?= e(admin_url('donations')) ?>" data-confirm="Delete this donation record permanently?" style="display:inline;">
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
            <div class="empty-state"><div class="icon"><?= lucide('heart-handshake') ?></div>No donations found<?= $statusFilter !== '' || $search !== '' ? ' for this filter' : ' yet' ?>.</div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
