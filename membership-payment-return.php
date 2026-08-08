<?php
/**
 * =============================================================================
 *  Cashfree payment return handler
 * =============================================================================
 *  Cashfree redirects the member here after checkout with ?renewal=<id>. We
 *  authoritatively re-check the payment link's status via the Cashfree API
 *  (never trusting the redirect alone) and, if paid, apply the renewal. The
 *  webhook does the same server-to-server, so either path completes the renewal.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/payments.php';

// Members only. Cashfree redirects the member's own browser here after payment;
// require_member() stores the intended URL and returns them here after login if
// their session lapsed mid-payment. This also prevents anonymous enumeration of
// renewal ids (names / membership IDs) via ?renewal=.
require_member('/login');

$siteName  = get_setting('site_name', SITE_NAME);
$renewalId = (int) get('renewal', 0);
$renewal   = $renewalId ? find('membership_renewals', $renewalId) : null;

// Ownership guard — only ever act on / reveal the signed-in member's own renewal.
if ($renewal && (int) $renewal['member_id'] !== (int) (current_member()['id'] ?? 0)) {
    $renewal = null;
}

$state = 'unknown'; // paid | pending | failed | unknown
if ($renewal) {
    if ($renewal['status'] === 'paid') {
        $state = 'paid';
    } elseif (!empty($renewal['order_id']) && cashfree_enabled()) {
        $st = cashfree_link_status((string) $renewal['order_id']);
        if ($st['paid']) {
            membership_mark_renewal($renewalId, 'paid');
            $renewal = find('membership_renewals', $renewalId);
            $state   = 'paid';
        } else {
            $state = in_array($st['status'], ['EXPIRED', 'CANCELLED'], true) ? 'failed' : 'pending';
        }
    }
}
$member = $renewal ? find('members', (int) $renewal['member_id']) : null;

seo_set([
    'title'    => 'Payment Status',
    'page_key' => 'membership-payment-return',
    'robots'   => 'noindex,nofollow',
]);
$page_hero = [
    'title'      => 'Membership Renewal',
    'subtitle'   => 'Payment status',
    'breadcrumb' => [['label' => 'Renewal']],
];
include __DIR__ . '/includes/header.php';

$views = [
    'paid'    => ['#308629', '#58A42F', 'check', 'Payment successful', 'Thank you! Your membership has been renewed.'],
    'pending' => ['#b45309', '#E67B1D', 'clock', 'Payment pending', 'We have not received a confirmation yet. If money was debited, it will reflect shortly.'],
    'failed'  => ['#b91c1c', '#ef4444', 'x', 'Payment not completed', 'The payment was not completed. You have not been charged, or any debit will be refunded.'],
    'unknown' => ['#4b5563', '#9ca3af', 'help-circle', 'Status unavailable', 'We could not locate this payment. Please check your account or contact us.'],
];
[$c1, $c2, $icon, $title, $sub] = $views[$state] ?? $views['unknown'];
?>
<section class="section">
    <div class="container" style="max-width:640px;">
        <div class="card-3d reveal" style="padding:0;overflow:hidden;">
            <div style="background:linear-gradient(135deg,<?= $c1 ?>,<?= $c2 ?>);color:#fff;padding:1.8rem;text-align:center;">
                <div class="icon-badge" style="background:rgba(255,255,255,.22);font-size:2rem;margin:0 auto .6rem;box-shadow:none;"><?= lucide($icon) ?></div>
                <h2 class="text-white mb-0"><?= e($title) ?></h2>
                <p style="color:rgba(255,255,255,.92);margin:.3rem 0 0;"><?= e($sub) ?></p>
            </div>
            <div style="padding:1.8rem;text-align:center;">
                <?php if ($renewal && $member): ?>
                    <p class="mb-1"><strong><?= e($member['name']) ?></strong> · <?= e($member['member_code']) ?></p>
                    <?php if ($state === 'paid' && !empty($member['expiry_date'])): ?>
                        <p class="mb-3">Valid until <strong><?= e(format_date($member['expiry_date'], 'd M Y')) ?></strong></p>
                    <?php endif; ?>
                <?php endif; ?>
                <div class="flex justify-center gap-2 flex-wrap">
                    <a class="btn btn-primary" href="<?= e(url('account')) ?>">Go to my account</a>
                    <?php if ($state === 'paid'): ?>
                        <a class="btn btn-outline" href="<?= e(url('member-card')) ?>">View my card</a>
                    <?php elseif ($state === 'failed' || $state === 'pending'): ?>
                        <a class="btn btn-outline" href="<?= e(url('membership-renew')) ?>">Try again</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
