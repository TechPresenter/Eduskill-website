<?php
/**
 * Unified payment return page. Cashfree redirects here after checkout with
 * ?order=<orderId>; we re-verify the status server-side and confirm.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/pay.php';

$order = preg_replace('/[^A-Za-z0-9]/', '', (string) get('order', ''));
$p = $order !== '' ? pay_reconcile($order) : null;

seo_set(['title' => 'Payment', 'robots' => 'noindex,nofollow']);
$page_hero = ['title' => 'Payment', 'breadcrumb' => [['label' => 'Payment']]];
include __DIR__ . '/includes/header.php';

$status = $p['status'] ?? 'unknown';
$map = [
    'paid'      => ['circle-check', 'Payment successful', 'Thank you! Your payment has been received and confirmed.'],
    'pending'   => ['clock', 'Payment pending', 'Your payment is still being processed. This page will reflect the final status once confirmed.'],
    'failed'    => ['circle-x', 'Payment failed', 'Your payment could not be completed. No amount has been charged — please try again.'],
    'cancelled' => ['ban', 'Payment cancelled', 'You cancelled the payment. You can try again whenever you are ready.'],
    'expired'   => ['timer-off', 'Payment link expired', 'This payment link has expired. Please start a new payment.'],
];
[$icon, $title, $msg] = $map[$status] ?? ['help-circle', 'Payment status unknown', 'We could not find that payment. If you were charged, please contact us with your order id.'];
?>
<section class="section">
    <div class="container container-narrow text-center">
        <div class="icon-badge <?= $status === 'paid' ? '' : 'accent' ?>" style="margin:0 auto 1rem;width:72px;height:72px;font-size:1.9rem;"><?= lucide($icon) ?></div>
        <h2><?= e($title) ?></h2>
        <p class="text-muted" style="max-width:520px;margin-inline:auto;"><?= e($msg) ?></p>

        <?php if ($p): ?>
        <div class="card" style="max-width:460px;margin:1.5rem auto 0;text-align:left;">
            <div class="card-body">
                <div class="flex justify-between" style="padding:.35rem 0;border-bottom:1px solid var(--border);"><span class="text-muted">Order ID</span><strong><?= e($p['order_id']) ?></strong></div>
                <?php if (!empty($p['receipt_no'])): ?><div class="flex justify-between" style="padding:.35rem 0;border-bottom:1px solid var(--border);"><span class="text-muted">Receipt</span><strong><?= e($p['receipt_no']) ?></strong></div><?php endif; ?>
                <div class="flex justify-between" style="padding:.35rem 0;border-bottom:1px solid var(--border);"><span class="text-muted">Purpose</span><span><?= e($p['purpose']) ?></span></div>
                <div class="flex justify-between" style="padding:.35rem 0;"><span class="text-muted">Amount</span><strong><?= e($p['currency']) ?> <?= number_format((float) $p['net_amount'], 2) ?></strong></div>
            </div>
        </div>
        <?php endif; ?>

        <div class="flex flex-wrap justify-center gap-2 mt-4">
            <?php if ($status === 'pending'): ?>
                <a class="btn btn-primary" href="<?= e(url('pay-return?order=' . $order)) ?>"><?= lucide('refresh-cw') ?> Refresh status</a>
            <?php endif; ?>
            <?php if (is_member_logged_in()): ?>
                <a class="btn btn-outline" href="<?= e(url('my-payments')) ?>"><?= lucide('receipt') ?> My Payments</a>
            <?php endif; ?>
            <a class="btn btn-outline" href="<?= e(url('/')) ?>"><?= lucide('home') ?> Home</a>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
