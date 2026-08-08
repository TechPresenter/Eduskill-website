<?php
/**
 * =============================================================================
 *  Gateway return handler — Stripe / PayPal / Cashfree (+ Razorpay result)
 * =============================================================================
 *  Authoritatively confirms the payment with the gateway (never trusting the
 *  redirect alone) and completes the donation. The matching webhook does the
 *  same server-to-server, so either path finishes the donation.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

$siteName   = get_setting('site_name', SITE_NAME);
$donationId = (int) get('donation', 0);
$gateway    = preg_replace('/[^a-z]/', '', (string) get('gateway', ''));
$d          = $donationId ? find('donations', $donationId) : null;

$state = 'unknown'; // paid | pending | failed | unknown
if ($d) {
    if ($d['status'] === 'completed') {
        $state = 'paid';
    } elseif ($d['status'] === 'refunded' || $d['status'] === 'failed') {
        $state = 'failed';
    } elseif (!empty($d['gateway_order_id'])) {
        donation_load_gateway($gateway);
        if ($gateway === 'stripe' && function_exists('stripe_get_session')) {
            $sess = (string) get('session', $d['gateway_order_id']);
            if ($sess === (string) $d['gateway_order_id']) {
                $st = stripe_get_session($sess);
                if (!empty($st['paid'])) { donation_mark_paid($donationId, ['gateway' => 'stripe', 'method' => 'Stripe']); $state = 'paid'; }
                else { $state = 'pending'; }
            }
        } elseif ($gateway === 'paypal' && function_exists('paypal_capture')) {
            $token = (string) get('token', $d['gateway_order_id']);
            if ($token === (string) $d['gateway_order_id']) {
                $cap = paypal_capture($token);
                if (!empty($cap['paid'])) { donation_mark_paid($donationId, ['gateway' => 'paypal', 'payment_id' => $cap['capture_id'], 'method' => 'PayPal']); $state = 'paid'; }
                else { $state = 'failed'; }
            }
        } elseif ($gateway === 'cashfree') {
            require_once __DIR__ . '/includes/payments.php';
            if (function_exists('cashfree_link_status')) {
                $st = cashfree_link_status((string) $d['gateway_order_id']);
                if (!empty($st['paid'])) { donation_mark_paid($donationId, ['gateway' => 'cashfree', 'method' => 'Cashfree']); $state = 'paid'; }
                else { $state = 'pending'; }
            }
        }
    }
    // Re-read (mark_paid may have set receipt_no).
    $d = find('donations', $donationId);
    if ($d['status'] === 'completed') { $state = 'paid'; }
}

/**
 * Ownership proof — a completed donation id alone is guessable, so the amount /
 * receipt number / receipt token are only disclosed to a visitor who arrived via
 * the gateway redirect carrying an unguessable proof for THIS donation:
 *   - the receipt capability token (t=…, added to every gateway return URL), or
 *   - the gateway's own session / order token (Stripe session, PayPal token).
 */
$owns = false;
if ($d) {
    $tok = (string) get('t', '');
    if ($tok !== '' && hash_equals(donation_receipt_token($donationId), $tok)) {
        $owns = true;
    } elseif (!empty($d['gateway_order_id'])) {
        $ord = (string) $d['gateway_order_id'];
        foreach (['session', 'token', 'order_id'] as $p) {
            $v = (string) get($p, '');
            if ($v !== '' && hash_equals($ord, $v)) { $owns = true; break; }
        }
    }
}
// Snapshot the donation under a collision-safe name — header.php → navbar.php
// reuses the global `$d` while rendering the menu, clobbering it before render.
$don = $d;

seo_set(['title' => 'Donation Status', 'page_key' => 'donation-return', 'robots' => 'noindex,nofollow']);
$page_hero = ['title' => 'Thank You', 'subtitle' => 'Donation status', 'breadcrumb' => [['label' => 'Donate']]];
include __DIR__ . '/includes/header.php';

$views = [
    'paid'    => ['#308629', '#58A42F', 'heart', 'Thank you for your donation!', 'Your generosity is making a real difference.'],
    'pending' => ['#b45309', '#E67B1D', 'clock', 'Payment pending', 'We have not received confirmation yet. If money was debited it will reflect shortly.'],
    'failed'  => ['#b91c1c', '#ef4444', 'x', 'Payment not completed', 'The payment was not completed. You have not been charged, or any debit will be reversed.'],
    'unknown' => ['#4b5563', '#9ca3af', 'help-circle', 'Status unavailable', 'We could not locate this donation.'],
];
[$c1, $c2, $icon, $title, $sub] = $views[$state] ?? $views['unknown'];
?>
<section class="section"><div class="container" style="max-width:620px;">
    <div class="card-3d reveal" style="padding:0;overflow:hidden;">
        <div style="background:linear-gradient(135deg,<?= $c1 ?>,<?= $c2 ?>);color:#fff;padding:1.9rem;text-align:center;">
            <div class="icon-badge" style="background:rgba(255,255,255,.22);font-size:2rem;margin:0 auto .6rem;box-shadow:none;"><?= lucide($icon) ?></div>
            <h2 class="text-white mb-0"><?= e($title) ?></h2>
            <p style="color:rgba(255,255,255,.92);margin:.3rem 0 0;"><?= e($sub) ?></p>
        </div>
        <div style="padding:1.8rem;text-align:center;">
            <?php if ($state === 'paid' && $don && $owns): ?>
                <p class="mb-1"><strong><?= e(money($don['amount'], donation_symbol($don['currency']), 2)) ?></strong> · Receipt <?= e($don['receipt_no']) ?></p>
                <?php if (!empty($don['receipt_no'])): ?>
                    <a class="btn btn-primary mt-2" target="_blank" href="<?= e(url('donation-receipt?id=' . (int) $don['id'] . '&t=' . donation_receipt_token((int) $don['id']))) ?>"><?= lucide('download') ?> Download 80G receipt</a>
                <?php endif; ?>
            <?php elseif ($state === 'paid'): ?>
                <p class="text-muted mb-1">Your donation is complete. We've emailed your 80G receipt to you.</p>
            <?php endif; ?>
            <div class="mt-3"><a class="btn btn-outline" href="<?= e(url('/')) ?>">Back to home</a></div>
        </div>
    </div>
</div></section>
<?php include __DIR__ . '/includes/footer.php'; ?>
