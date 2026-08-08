<?php
/**
 * Member — My Payments. Transaction history, receipts, refund status, and
 * one-click retry of failed payments. Members only.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/pay.php';
require_member();

$m = current_member();

/* ---- Retry a failed/expired/cancelled payment (re-checkout same order) ---- */
if (is_post() && post('_do') === 'retry') {
    require_csrf();
    $old = pay_get((string) post('order', ''));
    if ($old && (int) ($old['member_id'] ?? 0) === (int) $m['id'] && in_array($old['status'], ['failed', 'cancelled', 'expired'], true)) {
        $r = pay_start([
            'context_type' => $old['context_type'], 'context_id' => $old['context_id'], 'member_id' => (int) $m['id'],
            'name' => $old['customer_name'], 'email' => $old['customer_email'], 'phone' => $old['customer_phone'],
            'amount' => (float) $old['amount'], 'discount' => (float) $old['discount'], 'coupon_code' => $old['coupon_code'],
            'purpose' => $old['purpose'], 'meta' => json_decode((string) $old['meta'], true) ?: [],
        ]);
        if ($r['ok']) { redirect($r['url']); }
        set_flash('error', $r['error'] ?? 'Could not start the payment.');
    }
    redirect('/my-payments');
}

$rows = db_all(
    "SELECT * FROM payments WHERE member_id = :m OR customer_email = :e ORDER BY id DESC LIMIT 100",
    [':m' => (int) $m['id'], ':e' => strtolower((string) $m['email'])]
);

seo_set(['title' => 'My Payments', 'robots' => 'noindex,nofollow']);
$page_hero = ['title' => 'My Payments', 'subtitle' => 'Your transaction history, receipts and payment status.', 'breadcrumb' => [['label' => 'My Payments']]];
include __DIR__ . '/includes/header.php';
$pill = ['paid' => 'badge-success', 'failed' => 'badge-danger', 'refunded' => 'badge-warning', 'pending' => 'badge-brand'];
?>
<section class="section">
    <div class="container">
        <?php if ($rows): ?>
        <div class="table-wrap card" style="padding:0;overflow-x:auto;">
            <table class="table" style="margin:0;">
                <thead><tr><th>Order</th><th>For</th><th>Amount</th><th>Status</th><th>Date</th><th>Receipt</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><code><?= e($r['order_id']) ?></code></td>
                        <td><?= e($r['purpose']) ?></td>
                        <td><strong><?= e(money((float) $r['net_amount'], '₹', 0)) ?></strong></td>
                        <td><span class="badge <?= $pill[$r['status']] ?? 'badge-muted' ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                        <td><small class="text-muted"><?= e(format_date($r['created_at'], 'd M Y')) ?></small></td>
                        <td><?php if (!empty($r['receipt_no'])): ?><small><?= e($r['receipt_no']) ?></small><?php else: ?>—<?php endif; ?></td>
                        <td style="text-align:right;">
                            <?php if ($r['status'] === 'pending'): ?>
                                <a class="btn btn-ghost btn-sm" href="<?= e(url('pay-return?order=' . urlencode($r['order_id']))) ?>"><?= lucide('refresh-cw') ?> Check</a>
                            <?php elseif (in_array($r['status'], ['failed', 'cancelled', 'expired'], true)): ?>
                                <form method="post" action="<?= e(url('my-payments')) ?>" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="_do" value="retry"><input type="hidden" name="order" value="<?= e($r['order_id']) ?>"><button class="btn btn-outline btn-sm" type="submit"><?= lucide('rotate-cw') ?> Retry</button></form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="empty-state"><div class="icon-badge"><?= lucide('receipt') ?></div><h3>No payments yet</h3><p class="text-muted">Your transactions will appear here.</p></div>
        <?php endif; ?>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
