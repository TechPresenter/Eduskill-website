<?php
/**
 * =============================================================================
 *  Renew Membership — member self-service renewal
 * =============================================================================
 *  Behind require_member(). Lists plans, and on submit either starts a Cashfree
 *  payment (redirecting to the hosted link) when the gateway is enabled, or
 *  explains the offline route. Auto-renew preference is saved here too.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/payments.php';
require_member('/login');

$sess   = current_member();
$member = member_ensure_identity(find('members', (int) ($sess['id'] ?? 0)));
$mStatus = member_effective_status($member);
$plans   = membership_plans_all(true);
$siteName = get_setting('site_name', SITE_NAME);

/* -------- Save auto-renew preference -------- */
if (is_post() && post('_do') === 'autorenew') {
    require_csrf();
    db_update('members', ['auto_renew' => post('auto_renew') ? 1 : 0], 'id = :id', [':id' => (int) $member['id']]);
    set_flash('success', post('auto_renew') ? 'Auto-renew reminders enabled.' : 'Auto-renew disabled.');
    redirect('/membership-renew');
}

/* -------- Start a renewal -------- */
if (is_post() && post('_do') === 'renew') {
    require_csrf();
    $planId = (int) post('plan_id', 0);
    $plan   = membership_plan($planId);
    if (!$plan) {
        set_flash('error', 'Please choose a valid plan.');
        redirect('/membership-renew');
    }

    if (!cashfree_enabled()) {
        set_flash('info', 'Online payment is not available right now. Please contact our office to renew your membership offline.');
        redirect('/membership-renew');
    }

    $renewalId = membership_create_renewal((int) $member['id'], $planId, [
        'amount'  => (float) $plan['price'],
        'method'  => 'cashfree',
        'gateway' => 'cashfree',
        'status'  => 'pending',
    ]);
    if (!$renewalId) {
        set_flash('error', 'Could not start the renewal. Please try again.');
        redirect('/membership-renew');
    }
    $linkId = 'pwf_ren_' . $renewalId . '_' . substr(bin2hex(random_bytes(4)), 0, 6);
    db_update('membership_renewals', ['order_id' => $linkId], 'id = :id', [':id' => $renewalId]);

    $res = cashfree_create_link([
        'linkId'        => $linkId,
        'amount'        => (float) $plan['price'],
        'purpose'       => $siteName . ' membership renewal (' . $plan['name'] . ')',
        'customerName'  => $member['name'],
        'customerEmail' => $member['email'],
        'customerPhone' => $member['phone'] ?? '',
        'returnUrl'     => abs_url('membership-payment-return?renewal=' . $renewalId),
        'notifyUrl'     => abs_url('api/v1/cashfree-webhook'),
    ]);

    if ($res['success'] && $res['link_url']) {
        redirect($res['link_url']);
    }
    membership_mark_renewal($renewalId, 'failed');
    set_flash('error', 'Payment could not be started: ' . $res['message']);
    redirect('/membership-renew');
}

seo_set([
    'title'       => 'Renew Membership',
    'description' => 'Renew your ' . $siteName . ' membership online.',
    'page_key'    => 'membership-renew',
    'robots'      => 'noindex,nofollow',
]);
$page_hero = [
    'title'      => 'Renew Membership',
    'subtitle'   => 'Keep your benefits going — renew in a couple of clicks.',
    'breadcrumb' => [['label' => 'Renew Membership']],
];

include __DIR__ . '/includes/header.php';
$currentPlan = membership_plan((int) ($member['plan_id'] ?? 0));
?>
<section class="section">
    <div class="container">
        <!-- Current status -->
        <div class="glass-card reveal mb-4" style="display:flex;flex-wrap:wrap;gap:1rem;align-items:center;justify-content:space-between;">
            <div>
                <span class="badge <?= $mStatus === 'active' ? 'badge-success' : 'badge-muted' ?>"><?= e(membership_status_label($mStatus)) ?></span>
                <h3 class="mb-0" style="margin-top:.4rem;"><?= e($currentPlan['name'] ?? 'No active plan') ?></h3>
                <p class="text-muted mb-0">
                    <?php if (!empty($member['expiry_date'])): ?>
                        Valid until <strong><?= e(format_date($member['expiry_date'], 'd M Y')) ?></strong>
                        <?php $dl = member_days_left($member); if ($dl !== null): ?>
                            · <?= $dl < 0 ? abs($dl) . ' days ago' : $dl . ' days left' ?>
                        <?php endif; ?>
                    <?php else: ?>You don't have an active membership yet.<?php endif; ?>
                </p>
            </div>
            <a class="btn btn-outline" href="<?= e(url('member-card')) ?>"><?= lucide('id-card') ?> My card</a>
        </div>

        <div class="section-head left mb-3">
            <span class="eyebrow">Choose a plan</span>
            <h2 class="section-title mb-0">Renew or upgrade</h2>
            <p class="section-subtitle">Your new term is added on top of any remaining days.</p>
        </div>

        <?php if (!$plans): ?>
            <div class="alert alert-info">No membership plans are available right now. Please check back soon.</div>
        <?php else: ?>
        <form method="post" action="<?= e(url('membership-renew')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="_do" value="renew">
            <div class="grid grid-3 gap-3">
                <?php foreach ($plans as $i => $pl):
                    $color = membership_tier_color($pl);
                    $benefits = array_filter(array_map('trim', explode("\n", (string) $pl['benefits'])));
                ?>
                    <label class="glass-card reveal" style="cursor:pointer;display:block;border-top:4px solid <?= e($color) ?>;">
                        <div class="flex items-center justify-between">
                            <strong style="font-size:1.15rem;"><?= e($pl['name']) ?></strong>
                            <input type="radio" name="plan_id" value="<?= (int) $pl['id'] ?>" <?= ((int) ($member['plan_id'] ?? 0) === (int) $pl['id'] || $i === 0) ? 'checked' : '' ?>>
                        </div>
                        <div style="font-size:1.8rem;font-weight:800;color:<?= e($color) ?>;"><?= e(money($pl['price'], '₹', 0)) ?>
                            <span style="font-size:.85rem;color:var(--text-muted,#6b7280);font-weight:600;">/ <?= (int) $pl['duration_days'] ?> days</span></div>
                        <?php if ($benefits): ?>
                            <ul class="list-check" style="margin-top:.6rem;font-size:.88rem;">
                                <?php foreach (array_slice($benefits, 0, 5) as $b): ?><li><?= e($b) ?></li><?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="text-center mt-4">
                <?php if (cashfree_enabled()): ?>
                    <button class="btn btn-3d btn-lg" type="submit"><?= lucide('credit-card') ?> Proceed to secure payment</button>
                    <p class="form-hint mt-2">Payments are processed securely by Cashfree.</p>
                <?php else: ?>
                    <button class="btn btn-3d btn-lg" type="submit"><?= lucide('info') ?> Request renewal</button>
                    <p class="form-hint mt-2">Online payment isn't enabled yet — we'll guide you through offline renewal.</p>
                <?php endif; ?>
            </div>
        </form>
        <?php endif; ?>

        <!-- Auto-renew -->
        <form method="post" action="<?= e(url('membership-renew')) ?>" class="glass-card reveal mt-4" style="max-width:520px;">
            <?= csrf_field() ?>
            <input type="hidden" name="_do" value="autorenew">
            <label class="checkbox"><input type="checkbox" name="auto_renew" value="1" onchange="this.form.submit()" <?= !empty($member['auto_renew']) ? 'checked' : '' ?>>
                Remind me to auto-renew before my membership expires</label>
        </form>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
