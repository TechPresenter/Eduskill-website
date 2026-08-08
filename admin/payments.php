<?php
/**
 * Admin — Payments. Unified transaction dashboard across every workflow:
 * list/filter/search, CSV export, manual verify, refund, analytics, webhook logs.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
require_once __DIR__ . '/../includes/pay.php';

$action = get('action', 'list');

/* ---- Actions ---- */
if (is_post() && post('_do') === 'verify') {
    require_csrf();
    $p = pay_reconcile((string) post('order', ''));
    set_flash($p ? 'success' : 'error', $p ? ('Re-verified: status is ' . $p['status'] . '.') : 'Payment not found.');
    redirect('/admin/payments' . ($p ? '?action=view&order=' . urlencode($p['order_id']) : ''));
}
if (is_post() && post('_do') === 'refund') {
    require_csrf();
    $p = pay_get((string) post('order', ''));
    if (!$p) { set_flash('error', 'Payment not found.'); redirect('/admin/payments'); }
    if ($p['status'] !== 'paid') { set_flash('error', 'Only paid transactions can be refunded.'); redirect('/admin/payments?action=view&order=' . urlencode($p['order_id'])); }
    $r = pay_refund($p, (float) post('amount', $p['net_amount']), clean(post('reason', '')));
    set_flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Refund recorded. Process it in your Cashfree dashboard if not automatic.' : ($r['error'] ?? 'Refund failed.'));
    redirect('/admin/payments?action=view&order=' . urlencode($p['order_id']));
}

/* ---- Shared filter ---- */
$statusF  = in_array(get('status'), ['created', 'pending', 'paid', 'failed', 'cancelled', 'refunded', 'expired'], true) ? get('status') : '';
$contextF = array_key_exists(get('context', ''), pay_contexts()) ? get('context') : '';
$q        = trim((string) get('q', ''));
$where = '1=1'; $params = [];
if ($statusF !== '')  { $where .= ' AND status = :st'; $params[':st'] = $statusF; }
if ($contextF !== '') { $where .= ' AND context_type = :ct'; $params[':ct'] = $contextF; }
// Distinct placeholder per slot — PDO EMULATE_PREPARES=false binds each name once,
// so the reused :q made both the search and the filtered CSV export 500.
if ($q !== '')        { $where .= ' AND (order_id LIKE :q1 OR customer_email LIKE :q2 OR receipt_no LIKE :q3 OR customer_name LIKE :q4)'; $params[':q1'] = $params[':q2'] = $params[':q3'] = $params[':q4'] = '%' . $q . '%'; }

/* ---- CSV export ---- */
if (get('export') === 'csv') {
    $rows = db_all("SELECT order_id, context_type, customer_name, customer_email, amount, discount, net_amount, currency, status, receipt_no, created_at, paid_at FROM payments WHERE $where ORDER BY id DESC", $params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="payments-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['order_id', 'context', 'name', 'email', 'amount', 'discount', 'net', 'currency', 'status', 'receipt', 'created', 'paid']);
    foreach ($rows as $r) { fputcsv($out, array_values($r)); }
    fclose($out);
    exit;
}

/* ---- Webhook log view ---- */
if ($action === 'webhooks') {
    $logs = db_all("SELECT * FROM payment_webhooks ORDER BY id DESC LIMIT 100");
    $page_title = 'Webhook Logs';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head"><div><h1>Webhook Logs</h1><span class="muted">Payments / Inbound gateway notifications</span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('payments')) ?>">← Payments</a></div>
    <div class="panel"><div class="panel-body">
    <?php if ($logs): ?>
        <div class="table-wrap"><table class="admin-table"><thead><tr><th>When</th><th>Event</th><th>Order</th><th>Signature</th><th>Handled</th><th>Note</th></tr></thead><tbody>
        <?php foreach ($logs as $l): ?>
            <tr><td><small class="text-muted"><?= e(format_datetime($l['created_at'])) ?></small></td>
                <td><small><?= e($l['event'] ?: '—') ?></small></td><td><code><?= e($l['order_id'] ?: '—') ?></code></td>
                <td><span class="pill <?= $l['signature_ok'] ? 'pill-green' : 'pill-red' ?>"><?= $l['signature_ok'] ? 'valid' : 'invalid' ?></span></td>
                <td><?= $l['handled'] ? lucide('check') : '—' ?></td><td><small class="text-muted"><?= e($l['note'] ?: '') ?></small></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php else: ?><div class="empty-state"><div class="icon"><?= lucide('webhook') ?></div>No webhooks received yet.</div><?php endif; ?>
    </div></div>
    <?php include __DIR__ . '/partials/foot.php'; exit;
}

/* ---- Detail view ---- */
if ($action === 'view') {
    $p = pay_get((string) get('order', ''));
    if (!$p) { set_flash('error', 'Payment not found.'); redirect('/admin/payments'); }
    $meta = json_decode((string) ($p['meta'] ?? ''), true) ?: [];
    $page_title = 'Payment ' . $p['order_id'];
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head"><div><h1><?= e($p['order_id']) ?></h1><span class="muted">Payments / <?= e(ucfirst($p['context_type'])) ?></span></div>
        <div class="flex gap-2">
            <form method="post" action="<?= e(admin_url('payments')) ?>" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="_do" value="verify"><input type="hidden" name="order" value="<?= e($p['order_id']) ?>"><button class="btn btn-secondary" type="submit"><?= lucide('refresh-cw') ?> Re-verify</button></form>
            <a class="btn btn-secondary" href="<?= e(admin_url('payments')) ?>">← Back</a>
        </div>
    </div>
    <div class="grid grid-2" style="align-items:start;gap:1.5rem;">
        <div class="panel"><div class="panel-head"><h3 class="panel-title"><?= lucide('receipt') ?> Transaction</h3></div><div class="panel-body">
            <table class="admin-table"><tbody>
                <tr><th>Status</th><td><span class="pill <?= ['paid'=>'pill-green','failed'=>'pill-red','refunded'=>'pill-amber','cancelled'=>'pill-gray','expired'=>'pill-gray'][$p['status']] ?? 'pill-blue' ?>"><?= e($p['status']) ?></span></td></tr>
                <tr><th>Receipt</th><td><?= e($p['receipt_no'] ?: '—') ?></td></tr>
                <tr><th>Purpose</th><td><?= e($p['purpose']) ?></td></tr>
                <tr><th>Amount</th><td><?= e(money((float) $p['amount'], '₹', 2)) ?><?= (float) $p['discount'] > 0 ? ' − ' . e(money((float) $p['discount'], '₹', 2)) . ' = <strong>' . e(money((float) $p['net_amount'], '₹', 2)) . '</strong>' : '' ?></td></tr>
                <?php if (!empty($p['coupon_code'])): ?><tr><th>Coupon</th><td><code><?= e($p['coupon_code']) ?></code></td></tr><?php endif; ?>
                <tr><th>Gateway</th><td><?= e($p['gateway']) ?> · link <?= e($p['gateway_link_id'] ?: '—') ?></td></tr>
                <tr><th>Created</th><td><?= e(format_datetime($p['created_at'])) ?></td></tr>
                <?php if (!empty($p['paid_at'])): ?><tr><th>Paid</th><td><?= e(format_datetime($p['paid_at'])) ?></td></tr><?php endif; ?>
                <?php if ((float) $p['refund_amount'] > 0): ?><tr><th>Refunded</th><td><?= e(money((float) $p['refund_amount'], '₹', 2)) ?> on <?= e(format_date($p['refunded_at'], 'd M Y')) ?></td></tr><?php endif; ?>
            </tbody></table>
        </div></div>
        <div>
            <div class="panel"><div class="panel-head"><h3 class="panel-title"><?= lucide('user') ?> Customer</h3></div><div class="panel-body">
                <p class="mb-1"><strong><?= e($p['customer_name'] ?: '—') ?></strong></p>
                <p class="mb-1"><small class="text-muted"><?= e($p['customer_email'] ?: '') ?> · <?= e($p['customer_phone'] ?: '') ?></small></p>
                <?php if (!empty($p['member_id'])): ?><p class="mb-0"><span class="pill pill-blue">Member #<?= (int) $p['member_id'] ?></span></p><?php endif; ?>
            </div></div>
            <?php if ($p['status'] === 'paid' && (float) $p['refund_amount'] <= 0): ?>
            <div class="panel"><div class="panel-head"><h3 class="panel-title"><?= lucide('undo-2') ?> Refund</h3></div>
                <form class="admin-form panel-body" method="post" action="<?= e(admin_url('payments')) ?>" data-confirm="Record a refund for this payment?">
                    <?= csrf_field() ?><input type="hidden" name="_do" value="refund"><input type="hidden" name="order" value="<?= e($p['order_id']) ?>">
                    <div class="form-group"><label class="form-label">Amount</label><input class="form-control" type="number" step="0.01" name="amount" value="<?= e($p['net_amount']) ?>" max="<?= e($p['net_amount']) ?>"></div>
                    <div class="form-group"><label class="form-label">Reason</label><input class="form-control" name="reason"></div>
                    <div class="form-actions"><button class="btn btn-danger" type="submit"><?= lucide('undo-2') ?> Record refund</button></div>
                    <span class="form-hint">Records the refund in our ledger. Process the actual gateway refund in your Cashfree dashboard if it is not automatic.</span>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php include __DIR__ . '/partials/foot.php'; exit;
}

/* ---- List + analytics ---- */
$page_title = 'Payments';
$stat = [
    'revenue' => (float) db_value("SELECT COALESCE(SUM(net_amount),0) FROM payments WHERE status = 'paid'"),
    'txns'    => (int) db_value("SELECT COUNT(*) FROM payments"),
    'paid'    => (int) db_value("SELECT COUNT(*) FROM payments WHERE status = 'paid'"),
    'failed'  => (int) db_value("SELECT COUNT(*) FROM payments WHERE status IN ('failed','cancelled','expired')"),
    'refunded'=> (float) db_value("SELECT COALESCE(SUM(refund_amount),0) FROM payments"),
];
$byContext = db_all("SELECT context_type, COUNT(*) c, COALESCE(SUM(CASE WHEN status='paid' THEN net_amount ELSE 0 END),0) rev FROM payments GROUP BY context_type ORDER BY rev DESC");
$p = paginate("SELECT * FROM payments WHERE $where ORDER BY id DESC", $params, 20);
$expQs = http_build_query(array_filter(['export' => 'csv', 'status' => $statusF ?: null, 'context' => $contextF ?: null, 'q' => $q ?: null]));
$pill = ['paid'=>'pill-green','failed'=>'pill-red','refunded'=>'pill-amber','cancelled'=>'pill-gray','expired'=>'pill-gray','pending'=>'pill-blue','created'=>'pill-gray'];
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head"><div><h1>Payments</h1><span class="muted"><?= number_format($stat['txns']) ?> transactions</span></div>
    <div class="flex gap-2"><a class="btn btn-secondary" href="<?= e(admin_url('payments?action=webhooks')) ?>"><?= lucide('webhook') ?> Webhooks</a>
        <a class="btn btn-secondary" href="<?= e(admin_url('payments?' . $expQs)) ?>"><?= lucide('download') ?> Export</a></div></div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon bg-green"><?= lucide('indian-rupee') ?></div><div><div class="stat-value"><?= e(money($stat['revenue'], '₹', 0)) ?></div><div class="stat-label">Revenue</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-blue"><?= lucide('credit-card') ?></div><div><div class="stat-value"><?= number_format($stat['paid']) ?></div><div class="stat-label">Successful</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-rose"><?= lucide('circle-x') ?></div><div><div class="stat-value"><?= number_format($stat['failed']) ?></div><div class="stat-label">Failed/Cancelled</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-amber"><?= lucide('undo-2') ?></div><div><div class="stat-value"><?= e(money($stat['refunded'], '₹', 0)) ?></div><div class="stat-label">Refunded</div></div></div>
</div>

<div class="panel"><div class="panel-body">
    <div class="data-toolbar">
        <form class="search" method="get" action="<?= e(admin_url('payments')) ?>" style="display:flex;gap:.5rem;flex-wrap:wrap;">
            <input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Order, email, receipt…">
            <select class="form-select" name="status" style="max-width:150px;"><option value="">All statuses</option>
                <?php foreach (['paid', 'pending', 'failed', 'cancelled', 'refunded', 'expired'] as $s): ?><option value="<?= $s ?>" <?= $statusF === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
            </select>
            <select class="form-select" name="context" style="max-width:160px;"><option value="">All types</option>
                <?php foreach (pay_contexts() as $k => $l): ?><option value="<?= $k ?>" <?= $contextF === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-secondary" type="submit"><?= lucide('search') ?></button>
        </form>
    </div>

    <?php if ($p['items']): ?>
    <div class="table-wrap"><table class="admin-table"><thead><tr><th>Order</th><th>Type</th><th>Customer</th><th>Amount</th><th>Status</th><th>Date</th><th style="text-align:right;">Actions</th></tr></thead><tbody>
    <?php foreach ($p['items'] as $r): ?>
        <tr>
            <td><a href="<?= e(admin_url('payments?action=view&order=' . urlencode($r['order_id']))) ?>"><code><?= e($r['order_id']) ?></code></a><?php if (!empty($r['receipt_no'])): ?><br><small class="text-muted"><?= e($r['receipt_no']) ?></small><?php endif; ?></td>
            <td><span class="pill pill-blue"><?= e(ucfirst($r['context_type'])) ?></span></td>
            <td><?= e($r['customer_name'] ?: '—') ?><br><small class="text-muted"><?= e($r['customer_email'] ?: '') ?></small></td>
            <td><strong><?= e(money((float) $r['net_amount'], '₹', 0)) ?></strong></td>
            <td><span class="pill <?= $pill[$r['status']] ?? 'pill-gray' ?>"><?= e($r['status']) ?></span></td>
            <td><small class="text-muted"><?= e(format_date($r['created_at'], 'd M Y')) ?></small></td>
            <td style="text-align:right;"><a class="icon-btn" href="<?= e(admin_url('payments?action=view&order=' . urlencode($r['order_id']))) ?>" title="View"><?= lucide('eye') ?></a></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?= $p['links'] ?>
    <?php else: ?><div class="empty-state"><div class="icon"><?= lucide('credit-card') ?></div>No transactions<?= $statusF || $contextF || $q ? ' match your filters' : ' yet' ?>.</div><?php endif; ?>
</div></div>

<?php if ($byContext && (float) $stat['revenue'] > 0): ?>
<div class="panel"><div class="panel-head"><h3 class="panel-title"><?= lucide('chart-pie') ?> Revenue by type</h3></div><div class="panel-body">
    <div class="table-wrap"><table class="admin-table"><thead><tr><th>Type</th><th>Transactions</th><th>Revenue</th></tr></thead><tbody>
    <?php foreach ($byContext as $b): ?><tr><td><?= e(ucfirst($b['context_type'])) ?></td><td><?= number_format((int) $b['c']) ?></td><td><?= e(money((float) $b['rev'], '₹', 0)) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</div></div>
<?php endif; ?>
<?php include __DIR__ . '/partials/foot.php'; ?>
