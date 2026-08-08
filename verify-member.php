<?php
/**
 * =============================================================================
 *  Verify Member — public, self-service membership verification
 * =============================================================================
 *  The target of every membership-card QR code. Looks a member up by their
 *  unique qr_token (?token=) or by membership ID (?code= / form) and renders a
 *  privacy-conscious result: name, photo, tier and validity only — never email,
 *  phone or address. Every echoed value is escaped with e().
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

$siteName = get_setting('site_name', SITE_NAME);
$token    = clean(get('token', ''));
$code     = strtoupper(clean(get('code', get('c', ''))));
$hasQuery = $token !== '' || array_key_exists('code', $_GET) || array_key_exists('c', $_GET);

$member    = null;
$throttled = false;
if ($token !== '' || $code !== '') {
    if (!pwf_throttle('verify-lookup', 10, 300)) {
        $throttled = true;
    } elseif ($token !== '') {
        $member = db_row('SELECT * FROM members WHERE qr_token = :t LIMIT 1', [':t' => $token]);
    } else {
        $member = db_row('SELECT * FROM members WHERE member_code = :c LIMIT 1', [':c' => $code]);
    }
}

/* Account-level gate: only 'active' accounts verify. Suspended/pending get a
   neutral message that deliberately does not reveal the reason. */
$accountHold = $member !== null && ($member['status'] ?? '') !== 'active';

$mStatus = $member ? member_effective_status($member) : null;
$plan    = $member ? membership_plan((int) ($member['plan_id'] ?? 0)) : null;

$banner = [
    'active'    => ['#308629', '#58A42F', 'check', 'Verified — Active Member', 'This membership is genuine and currently active.'],
    'expired'   => ['#b45309', '#E67B1D', 'clock', 'Membership Expired', 'This is a genuine member, but the membership has expired.'],
    'cancelled' => ['#4b5563', '#9ca3af', 'ban', 'Membership Cancelled', 'This membership has been cancelled.'],
    'none'      => ['#4b5563', '#9ca3af', 'user', 'Registered Member', 'This is a registered member without an active membership plan.'],
];

seo_set([
    'title'       => 'Verify Membership',
    'description' => 'Instantly verify a ' . $siteName . ' membership card. Confirm the member, tier and validity — no login required.',
    'page_key'    => 'verify-member',
    'robots'      => 'noindex,follow',
    'type'        => 'website',
]);

$page_hero = [
    'title'      => 'Verify Membership',
    'subtitle'   => 'Scan or enter a membership ID to confirm a ' . $siteName . ' member card in seconds.',
    'breadcrumb' => [['label' => 'Verify Membership']],
];

include __DIR__ . '/includes/header.php';
?>
<section class="section" style="position:relative;overflow:hidden;">
    <span class="blob b1" style="top:-80px;left:-60px;"></span>
    <span class="blob b3" style="bottom:-90px;right:-70px;"></span>
    <div class="container" style="position:relative;z-index:1;">
        <div class="section-head">
            <span class="eyebrow">Authenticity Check</span>
            <h2 class="section-title">Verify a Membership</h2>
            <p class="section-subtitle">Enter the membership ID exactly as printed on the card, or scan its QR code.</p>
        </div>

        <div class="glass-card reveal" style="max-width:620px;margin-inline:auto;">
            <form method="get" action="<?= e(url('verify-member')) ?>#result" novalidate>
                <div class="field-float" style="margin-bottom:1rem;">
                    <input type="text" id="code" name="code" value="<?= e($code) ?>" placeholder=" "
                           autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="32" required>
                    <label for="code">Membership ID</label>
                </div>
                <p class="form-hint mb-3">Example: <strong><?= e(get_setting('membership_code_prefix', 'PWF')) ?>-2026-00042</strong></p>
                <button class="btn btn-3d btn-block btn-lg" type="submit"><?= lucide('shield-check') ?> Verify</button>
            </form>
        </div>

        <?php if ($hasQuery): ?>
        <div id="result" style="max-width:640px;margin:2.75rem auto 0;">
            <?php if ($throttled): ?>
                <div class="alert alert-warning reveal">
                    <strong>Too many verification attempts.</strong>
                    Please wait a few minutes and try again.
                </div>
            <?php elseif (!$member): ?>
                <div class="alert alert-warning reveal">
                    <strong>No matching membership found.</strong>
                    Check for typing mistakes (0/O, 1/l) or a wrong ID. If it still fails, the card may not have been issued by us.
                </div>
            <?php elseif ($accountHold): ?>
                <div class="alert alert-warning reveal">
                    <strong>This membership is not currently active.</strong>
                    The ID could not be verified as an active membership. Please <a href="<?= e(url('contact')) ?>">contact our team</a> if you believe this is an error.
                </div>
            <?php else:
                [$c1, $c2, $icon, $title, $sub] = $banner[$mStatus] ?? $banner['none'];
                $daysLeft = member_days_left($member);
            ?>
                <div class="card-3d reveal" style="padding:0;overflow:hidden;border:1px solid rgba(0,0,0,.08);">
                    <div style="background:linear-gradient(135deg,<?= $c1 ?>,<?= $c2 ?>);color:#fff;padding:1.5rem 1.7rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                        <div class="icon-badge" style="background:rgba(255,255,255,.22);font-size:1.8rem;flex:0 0 auto;box-shadow:none;"><?= lucide($icon) ?></div>
                        <div style="flex:1 1 240px;">
                            <span class="badge" style="background:rgba(255,255,255,.22);color:#fff;"><?= e(membership_status_label($mStatus)) ?></span>
                            <h3 class="text-white mb-0" style="margin-top:.4rem;"><?= e($title) ?></h3>
                            <p style="color:rgba(255,255,255,.92);margin:.25rem 0 0;"><?= e($sub) ?></p>
                        </div>
                    </div>
                    <div style="padding:1.7rem;display:flex;gap:1.4rem;flex-wrap:wrap;align-items:center;">
                        <img src="<?= e(image_url($member['avatar'] ?? null, 'avatar')) ?>" alt=""
                             style="width:104px;height:104px;border-radius:14px;object-fit:cover;border:3px solid <?= $c2 ?>;flex:none;">
                        <div style="flex:1 1 260px;">
                            <div class="grid grid-2 gap-3">
                                <div><small class="text-muted">Member name</small><div><strong><?= e($member['name']) ?></strong></div></div>
                                <div><small class="text-muted">Membership ID</small><div><strong><?= e($member['member_code']) ?></strong></div></div>
                                <div><small class="text-muted">Tier</small><div><strong><?= e($plan['name'] ?? 'Member') ?></strong></div></div>
                                <div><small class="text-muted">Member since</small><div><strong><?= $member['join_date'] ? e(format_date($member['join_date'], 'M Y')) : '—' ?></strong></div></div>
                                <div><small class="text-muted">Valid thru</small><div><strong><?= $member['expiry_date'] ? e(format_date($member['expiry_date'], 'd M Y')) : 'Lifetime' ?></strong>
                                    <?php if ($daysLeft !== null && $mStatus === 'active'): ?><br><small class="text-muted"><?= $daysLeft ?> days left</small><?php endif; ?></div></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
