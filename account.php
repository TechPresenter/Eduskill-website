<?php
/**
 * =============================================================================
 *  My Account — member dashboard. Uses the public layout (header/footer) but is
 *  gated behind require_member(). Shows the signed-in member's profile card and
 *  premium quick-action cards linking to the key member journeys.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

// Members only — bounce guests to the login page.
require_member('/login');

$member = current_member();

// Enrich the session profile with a few extra details (graceful fallback if the
// row is unavailable for any reason).
$row = db_row(
    'SELECT * FROM members WHERE id = :id LIMIT 1',
    [':id' => (int) ($member['id'] ?? 0)]
) ?: [];

$name      = $member['name']  ?? 'Member';
$email     = $member['email'] ?? '';
$avatar    = $member['avatar'] ?? null;
$phone     = $row['phone'] ?? '';
$verified  = !empty($row['email_verified_at']);
$joined    = $row['created_at'] ?? null;
$lastLogin = $row['last_login_at'] ?? null;

// Membership snapshot.
$mStatus   = $row ? member_effective_status($row) : 'none';
$mPlan     = $row ? membership_plan((int) ($row['plan_id'] ?? 0)) : null;
$mDaysLeft = $row ? member_days_left($row) : null;
$mExpiry   = $row['expiry_date'] ?? null;
$mCode     = $row['member_code'] ?? '';

// Learning / student portal snapshot.
$mid         = (int) ($member['id'] ?? 0);
$studentRow  = db_row('SELECT * FROM school_students WHERE member_id = :m LIMIT 1', [':m' => $mid]);
$enrollCount = (int) db_value('SELECT COUNT(*) FROM course_enrollments WHERE member_id = :m', [':m' => $mid]);
$isLearner   = ($studentRow !== null) || $enrollCount > 0;

// A friendly initial for the avatar fallback badge.
$initial = strtoupper(mb_substr(trim($name) !== '' ? $name : 'M', 0, 1));

/* Quick actions — the core member journeys. All routes exist at the site root. */
$actions = [
    [
        'icon'  => 'heart',
        'tone'  => '',           // brand (blue)
        'title' => 'Donate',
        'text'  => 'Support a cause you care about. Every contribution is tracked from donation to delivery.',
        'url'   => url('donate'),
        'cta'   => 'Give Now',
    ],
    [
        'icon'  => 'handshake',
        'tone'  => 'g',          // green
        'title' => 'Volunteer',
        'text'  => 'Give your time and skills. Join our community of changemakers across Bihar.',
        'url'   => url('volunteer'),
        'cta'   => 'Get Involved',
    ],
    [
        'icon'  => 'calendar-days',
        'tone'  => 'o',          // orange
        'title' => 'Events',
        'text'  => 'Discover upcoming camps, drives and gatherings — and register in a couple of clicks.',
        'url'   => url('events'),
        'cta'   => 'Browse Events',
    ],
    [
        'icon'  => 'star',
        'tone'  => 'p',          // brand accent
        'title' => 'Membership',
        'text'  => 'Become a formal member and unlock deeper ways to contribute to our mission.',
        'url'   => url('membership'),
        'cta'   => 'View Plans',
    ],
    /* Learning had no route in from here. The catalogue (/courses), the player
       (/learn), progress (/my-learning), assignments and exams all exist and
       work, but this page linked only to /certificates — the END of the journey
       — so a member who had started a course had no way back to it except by
       typing the URL. */
    [
        'icon'  => 'book-open',
        'tone'  => 'p',
        'title' => 'My Learning',
        'text'  => 'Pick up a course where you left off, track your progress and sit your exams.',
        'url'   => url('my-learning'),
        'cta'   => 'Continue Learning',
    ],
    [
        'icon'  => 'graduation-cap',
        'tone'  => 's',
        'title' => 'Courses',
        'text'  => 'Browse the free training we run — digital literacy, health work and tailoring.',
        'url'   => url('courses'),
        'cta'   => 'Browse Courses',
    ],
];

seo_set([
    'title'       => 'My Account',
    'description' => 'Manage your EDUSKILL INDIA FOUNDATION membership, donations, volunteering and event registrations.',
    'page_key'    => 'account',
    'robots'      => 'noindex,nofollow',
]);

$page_hero = [
    'title'      => 'My Account',
    'subtitle'   => 'Welcome back, ' . $name . '. Manage your profile and pick up where you left off.',
    'breadcrumb' => [['label' => 'My Account']],
];

include __DIR__ . '/includes/header.php';
?>

<!-- ============================== DASHBOARD ============================== -->
<section class="section">
    <div class="container grid grid-sidebar gap-4 items-start">

        <!-- ---------- Profile card ---------- -->
        <aside class="glass-card reveal sticky-aside" style="text-align:center;position:sticky;top:100px;">
            <div style="position:relative;width:112px;height:112px;margin:0 auto 1rem;">
                <?php if (!empty($avatar)): ?>
                    <img src="<?= e(image_url($avatar, 'avatar')) ?>" alt="<?= e($name) ?>"
                         width="112" height="112"
                         style="width:112px;height:112px;border-radius:50%;object-fit:cover;box-shadow:var(--shadow-brand);">
                <?php else: ?>
                    <div aria-hidden="true"
                         style="width:112px;height:112px;border-radius:50%;display:grid;place-items:center;
                                font-family:var(--font-display,inherit);font-size:2.6rem;font-weight:800;color:#fff;
                                background:var(--grad-brand);box-shadow:var(--shadow-brand);">
                        <?= e($initial) ?>
                    </div>
                <?php endif; ?>
                <?php if ($verified): ?>
                    <span title="Verified email"
                          style="position:absolute;right:2px;bottom:2px;width:30px;height:30px;border-radius:50%;
                                 display:grid;place-items:center;background:var(--grad-green,#2F8065);color:#fff;
                                 font-size:.9rem;border:3px solid var(--surface,#fff);"><?= lucide('check') ?></span>
                <?php endif; ?>
            </div>

            <h2 class="card-title mb-0" style="font-size:1.35rem;"><?= e($name) ?></h2>
            <p class="text-muted" style="word-break:break-word;"><?= e($email) ?></p>

            <div class="flex justify-center gap-2 flex-wrap mt-2">
                <?php if ($verified): ?>
                    <span class="badge badge-success"><?= lucide('check') ?> Verified</span>
                <?php else: ?>
                    <span class="badge badge-muted">Unverified</span>
                <?php endif; ?>
                <span class="badge badge-brand">Member</span>
            </div>

            <div class="divider my-3"></div>

            <ul class="list-check" style="text-align:left;">
                <?php if (!empty($phone)): ?>
                    <li><?= lucide('phone') ?> <?= e($phone) ?></li>
                <?php endif; ?>
                <?php if ($joined): ?>
                    <li><?= lucide('calendar') ?> Member since <?= e(format_date($joined, 'M Y')) ?></li>
                <?php endif; ?>
                <?php if ($lastLogin): ?>
                    <li><?= lucide('clock') ?> Last login <?= e(time_ago($lastLogin)) ?></li>
                <?php endif; ?>
            </ul>

            <a class="btn btn-primary btn-block mt-3" href="<?= e(url('account-profile')) ?>">
                <?= lucide('user-round-cog') ?> Manage my profile
            </a>
            <a class="btn btn-outline btn-block mt-2" href="<?= e(url('account-profile') . '?tab=giving') ?>">
                <?= lucide('heart') ?> My giving &amp; receipts
            </a>

            <form method="post" action="<?= e(url('logout')) ?>" class="mt-3">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline btn-block"><?= lucide('log-out') ?> Log Out</button>
            </form>
        </aside>

        <!-- ---------- Main column ---------- -->
        <div>
            <!-- ---------- Membership status ---------- -->
            <?php
            $mTone = ['active' => 'badge-success', 'expired' => 'badge-muted', 'cancelled' => 'badge-muted', 'none' => 'badge-muted'][$mStatus] ?? 'badge-muted';
            $mAccent = membership_tier_color($mPlan);
            ?>
            <div class="glass-card reveal mb-4" style="border-top:4px solid <?= e($mAccent) ?>;">
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <div>
                        <span class="badge <?= e($mTone) ?>"><?= e(membership_status_label($mStatus)) ?></span>
                        <h3 class="mb-0" style="margin-top:.4rem;"><?= e($mPlan['name'] ?? 'Membership') ?></h3>
                        <p class="text-muted mb-0" style="font-size:.9rem;">
                            <?php if ($mCode !== ''): ?>ID <strong><?= e($mCode) ?></strong><?php endif; ?>
                            <?php if ($mExpiry): ?> · Valid until <strong><?= e(format_date($mExpiry, 'd M Y')) ?></strong>
                                <?php if ($mDaysLeft !== null && $mStatus === 'active' && $mDaysLeft <= 30): ?>
                                    <span class="badge badge-accent"><?= (int) $mDaysLeft ?> days left</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="flex gap-2 flex-wrap">
                        <a class="btn btn-outline btn-sm" href="<?= e(url('member-card')) ?>"><?= lucide('id-card') ?> My card</a>
                        <a class="btn btn-primary btn-sm" href="<?= e(url('membership-renew')) ?>"><?= lucide('refresh-cw') ?> <?= $mStatus === 'active' ? 'Renew' : 'Activate' ?></a>
                    </div>
                </div>
            </div>

            <?php if ($isLearner): ?>
            <div class="glass-card reveal mb-4" style="border-top:4px solid var(--gold,#E8C52E);">
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <div>
                        <span class="badge badge-brand"><?= lucide('graduation-cap') ?> Student</span>
                        <h3 class="mb-0" style="margin-top:.4rem;">My Learning</h3>
                        <p class="text-muted mb-0" style="font-size:.9rem;">
                            <?php if ($studentRow && !empty($studentRow['student_code'])): ?>ID <strong><?= e($studentRow['student_code']) ?></strong> · <?php endif; ?>
                            <?= (int) $enrollCount ?> enrolled course<?= $enrollCount === 1 ? '' : 's' ?>
                        </p>
                    </div>
                    <div class="flex gap-2 flex-wrap">
                        <a class="btn btn-primary btn-sm" href="<?= e(url('my-learning')) ?>"><?= lucide('book-open') ?> My courses</a>
                        <?php if ($studentRow): ?>
                            <a class="btn btn-outline btn-sm" href="<?= e(url('student-id-card')) ?>"><?= lucide('id-card') ?> ID card</a>
                            <a class="btn btn-outline btn-sm" href="<?= e(url('my-awards')) ?>"><?= lucide('award') ?> Certificates</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="section-head left mb-3">
                <span class="eyebrow">Quick Actions</span>
                <h2 class="section-title mb-0">What would you like to do?</h2>
                <p class="section-subtitle">Jump straight into the ways you can make a difference today.</p>
            </div>

            <div class="grid grid-2 gap-3">
                <?php foreach ($actions as $i => $a): ?>
                    <a class="glass-card icon-box <?= e($a['tone']) ?> tilt reveal delay-<?= (int) (($i % 3) + 1) ?>"
                       data-tilt="8" href="<?= e($a['url']) ?>"
                       style="text-align:left;color:inherit;display:block;">
                        <div class="ib-icon" style="margin:0 0 1rem;"><?= lucide($a['icon']) ?></div>
                        <h3 class="card-title mb-1"><?= e($a['title']) ?></h3>
                        <p class="card-text"><?= e($a['text']) ?></p>
                        <span style="color:var(--brand-600);font-weight:700;"><?= e($a['cta']) ?> <?= lucide('arrow-right') ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- ---------- Support strip ---------- -->
            <div class="glass-card reveal mt-4"
                 style="background:var(--grad-brand);color:#fff;display:flex;flex-wrap:wrap;
                        align-items:center;justify-content:space-between;gap:1rem;">
                <div>
                    <h3 class="text-white mb-0">Need a hand?</h3>
                    <p style="color:rgba(255,255,255,.9);margin:0;">
                        Our team is here to help with donations, membership or anything else.
                    </p>
                </div>
                <a class="btn btn-white" href="<?= e(url('contact')) ?>">Contact Support</a>
            </div>
        </div>

    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
