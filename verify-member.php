<?php
/**
 * =============================================================================
 *  Verify Member — public, self-service membership verification
 * =============================================================================
 *  The target of every membership-card QR code. Looks a member up by their
 *  unique qr_token (?token=, what the QR carries) or by membership ID (?code=,
 *  the form and the pretty /verify/member/{CODE} route someone types from a
 *  printed card) and renders a privacy-conscious result.
 *
 *  PUBLIC FIELD ALLOW-LIST — nothing else may be added here: organisation, name,
 *  member code, tier, status, member since, valid through, photo. Email, phone,
 *  address, DOB, documents, payments and the account status value are neither
 *  echoed NOR selected (see VERIFY_MEMBER_FIELDS). Every echoed value is
 *  escaped with e().
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* This URL carries ?token=<32 hex> — a permanent per-member bearer identifier
   printed on a physical card. Every analytics tag reports the full URL as
   page_location, so no third-party tag may load here. Must be defined before
   includes/header.php runs tracking_head(). */
define('PWF_NO_TRACKING', true);

$siteName = get_setting('site_name', SITE_NAME);
$token    = clean(get('token', ''));
$code     = strtoupper(clean(get('code', get('c', ''))));
$hasQuery = $token !== '' || array_key_exists('code', $_GET) || array_key_exists('c', $_GET);

/* Only the columns this page is allowed to reason about are ever loaded. The
   query used to be SELECT *, which pulled the password hash, phone, address and
   document path into scope on a public page — nothing echoed them, but a page
   cannot leak a value it never fetched. */
const VERIFY_MEMBER_FIELDS = 'id, name, member_code, avatar, plan_id, membership_status, status, join_date, expiry_date';

$member    = null;
$throttled = false;
if ($token !== '' || $code !== '') {
    /* SEPARATE counters, not one shared 'verify-lookup'. The token path is
       unguessable (32 hex), so it gets a generous allowance; the ?code= path is
       the only enumerable surface and gets a tight, long window. They were
       sharing a bucket, which meant a code-guesser behind a NAT also blocked
       every legitimate QR scan from that address — the attacker's traffic
       denied service to real members.

       failOpen:false — if logs/throttle cannot be written, "cannot count"
       must not degrade into "unlimited guesses" on an enumeration guard. */
    if ($token !== '') {
        if (!pwf_throttle('verify-token', 20, 300, false)) {
            $throttled = true;
        } else {
            $member = db_row('SELECT ' . VERIFY_MEMBER_FIELDS . ' FROM members WHERE qr_token = :t LIMIT 1', [':t' => $token]);
        }
    } elseif (!pwf_throttle('verify-member-code', 8, 600, false)) {
        $throttled = true;
    } else {
        $member = db_row('SELECT ' . VERIFY_MEMBER_FIELDS . ' FROM members WHERE member_code = :c LIMIT 1', [':c' => $code]);
    }
}

/* Account-level gate: only 'active' accounts verify.
   A held account renders the SAME response as a code that matches nothing —
   byte-identical markup, not a differently-worded warning. The old pair of
   messages ("not currently active" vs "no matching membership found") was an
   existence oracle: it confirmed which codes are real even for pending,
   suspended and cancelled accounts, so an enumerator could census the register
   and infer its size and signup era without ever verifying anybody. The reason
   is withheld either way, so nothing is lost for the person holding a genuine
   card: the contact link is on both branches. */
$accountHold = $member !== null && ($member['status'] ?? '') !== 'active';
if ($accountHold) {
    $member = null;
}

$mStatus = $member ? member_effective_status($member) : null;
$plan    = $member ? membership_plan((int) ($member['plan_id'] ?? 0)) : null;

/* Banner ramp: [gradient start, gradient end, icon, title, subtitle, ink].
   Brand palette only — the previous ramp used #1F5C48/#2F8065/#b45309/#4b5563/
   #9ca3af, none of which is a brand colour, so the same membership looked like
   two different products on the card and on its own verification page. The
   pairings match the card's status chips exactly: green/white for active,
   #151818 on orange for expired, ivory on charcoal for cancelled, #151818 on
   yellow for a registered member with no plan.
   Ink is carried per row because white on #F15A24 is 3.37:1 and fails AA — the
   expired banner takes dark ink instead of forcing a non-brand mid-tone. */
$banner = [
    'active'    => ['#0B4E3D', '#174D3D', 'check', 'Verified — Active Member',  'This membership is genuine and currently active.',                 '#FFFFFF'],
    'expired'   => ['#F15A24', '#E8C52E', 'clock', 'Membership Expired',        'This is a genuine member, but the membership has expired.',        '#151818'],
    'cancelled' => ['#151818', '#0F4537', 'ban',   'Membership Cancelled',      'This membership has been cancelled.',                              '#FEFEF1'],
    'none'      => ['#FFE987', '#E8C52E', 'user',  'Registered Member',         'This is a registered member without an active membership plan.',   '#151818'],
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
                <p class="form-hint mb-3">Example: <strong><?= e(get_setting('membership_code_prefix', 'EIF')) ?>-2026-00042</strong></p>
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
                    Check for typing mistakes (0/O, 1/l) or a wrong ID. If it still fails, the card may not have been
                    issued by us — please <a href="<?= e(url('contact')) ?>">contact our team</a> if you believe this is an error.
                </div>
            <?php else:
                [$c1, $c2, $icon, $title, $sub, $bInk] = $banner[$mStatus] ?? $banner['none'];
                /* Chip / photo-frame tints derived from the banner ink so the pair
                   holds on both the light (yellow, orange) and dark (green,
                   charcoal) banners — a fixed rgba(255,255,255,.22) is invisible
                   on #FFE987. */
                $bChip    = $bInk === '#151818' ? 'rgba(21,24,24,.14)' : 'rgba(255,255,255,.22)';
                $bSubInk  = $bInk === '#151818' ? 'rgba(21,24,24,.86)' : 'rgba(255,255,255,.92)';
                $daysLeft = member_days_left($member);
                $tierName = trim((string) ($plan['name'] ?? ''));
                $mName    = trim((string) ($member['name'] ?? ''));
                /* Branded fallback mark, matching the card's own: a monogram on a
                   brand tint, never the generic grey avatar placeholder — the same
                   member was appearing as two different products across the two
                   surfaces. */
                $vInitials = '';
                foreach (preg_split('/\s+/', $mName) ?: [] as $vPart) {
                    if ($vPart !== '' && preg_match('/^\p{L}/u', $vPart)) {
                        $vInitials .= mb_strtoupper(mb_substr($vPart, 0, 1));
                    }
                    if (mb_strlen($vInitials) === 2) { break; }
                }
            ?>
                <div class="card-3d reveal" style="padding:0;overflow:hidden;border:1px solid rgba(0,0,0,.08);">
                    <div style="background:linear-gradient(135deg,<?= $c1 ?>,<?= $c2 ?>);color:<?= $bInk ?>;padding:1.5rem 1.7rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                        <div class="icon-badge" style="background:<?= $bChip ?>;color:<?= $bInk ?>;font-size:1.8rem;flex:0 0 auto;box-shadow:none;"><?= lucide($icon) ?></div>
                        <div style="flex:1 1 240px;">
                            <span class="badge" style="background:<?= $bChip ?>;color:<?= $bInk ?>;"><?= e(membership_status_label($mStatus)) ?></span>
                            <h3 class="mb-0" style="margin-top:.4rem;color:<?= $bInk ?>;"><?= e($title) ?></h3>
                            <p style="color:<?= $bSubInk ?>;margin:.25rem 0 0;"><?= e($sub) ?></p>
                        </div>
                    </div>
                    <div style="padding:1.7rem;display:flex;gap:1.4rem;flex-wrap:wrap;align-items:center;">
                        <?php if (!empty($member['avatar'])): ?>
                            <img src="<?= e(image_url($member['avatar'], 'avatar')) ?>" alt=""
                                 style="width:104px;height:104px;border-radius:14px;object-fit:cover;border:3px solid #0B4E3D;flex:none;">
                        <?php else: ?>
                            <span aria-hidden="true" style="width:104px;height:104px;border-radius:14px;flex:none;border:3px solid #0B4E3D;
                                  background:linear-gradient(165deg,#F8FCF8,#FEFEF1);display:flex;align-items:flex-end;justify-content:center;
                                  position:relative;overflow:hidden;">
                                <svg viewBox="0 0 80 100" focusable="false" style="position:absolute;inset:0;width:100%;height:100%;">
                                    <g fill="#0B4E3D" opacity=".17"><circle cx="40" cy="36" r="15"/><path d="M9 100c0-24 14-36 31-36s31 12 31 36Z"/></g>
                                </svg>
                                <?php if ($vInitials !== ''): ?>
                                <span style="position:relative;margin-bottom:9px;background:#0B4E3D;color:#FFFFFF;font-size:.72rem;
                                      font-weight:800;letter-spacing:.08em;padding:.16em .5em;border-radius:999px;"><?= e($vInitials) ?></span>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                        <div style="flex:1 1 260px;">
                            <div class="grid grid-2 gap-3">
                                <?php if ($mName !== ''): ?>
                                <div><small class="text-muted">Member name</small><div><strong><?= e($mName) ?></strong></div></div>
                                <?php endif; ?>
                                <div><small class="text-muted">Membership ID</small><div><strong><?= e($member['member_code']) ?></strong></div></div>
                                <?php /* No plan, no tier row. It used to print the invented word "Member"
                                          for every one of the members who have no plan_id — a tier nobody
                                          was ever granted, on the page whose whole job is telling the truth. */ ?>
                                <?php if ($tierName !== ''): ?>
                                <div><small class="text-muted">Tier</small><div><strong><?= e($tierName) ?></strong></div></div>
                                <?php endif; ?>
                                <div><small class="text-muted">Organisation</small><div><strong><?= e($siteName) ?></strong></div></div>
                                <?php if (!empty($member['join_date'])): ?>
                                <div><small class="text-muted">Member since</small><div><strong><?= e(format_date($member['join_date'], 'M Y')) ?></strong></div></div>
                                <?php endif; ?>
                                <?php
                                /* "Lifetime" used to print for any null expiry_date — including a
                                   member who never enrolled, so the panel could read "Valid thru
                                   Lifetime" directly under "Registered member without an active
                                   plan". Only an actual membership gets a validity. */
                                $validThru = !empty($member['expiry_date'])
                                    ? format_date($member['expiry_date'], 'd M Y')
                                    : (in_array($mStatus, ['active', 'expired'], true) ? 'Lifetime' : '');
                                ?>
                                <?php if ($validThru !== ''): ?>
                                <div><small class="text-muted">Valid thru</small><div><strong><?= e($validThru) ?></strong>
                                    <?php if ($daysLeft !== null && $mStatus === 'active'): ?><br><small class="text-muted"><?= $daysLeft ?> days left</small><?php endif; ?></div></div>
                                <?php endif; ?>
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
