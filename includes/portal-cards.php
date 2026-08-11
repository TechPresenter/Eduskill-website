<?php
/**
 * =============================================================================
 *  ACCESS YOUR PORTAL — the seven contextual sign-in doors, as one section.
 * -----------------------------------------------------------------------------
 *  Drop it anywhere inside <body>, after includes/header.php:
 *
 *      include __DIR__ . '/includes/portal-cards.php';
 *
 *  It also runs standalone (own bootstrap, own stylesheet), so a page that does
 *  not use the shared header can still include it.
 *
 *  Optional — set $portal_cards BEFORE the include to override any of these:
 *
 *      $portal_cards = [
 *          'eyebrow'    => 'Sign in',
 *          'title'      => 'Access Your Portal',
 *          'subtitle'   => 'Choose the portal that matches your …',
 *          'level'      => 'h2',     // 'h2' (default) or 'h3' if the host
 *                                    //  section sits under another h2
 *          'heading_id' => 'portalsHeading',
 *          'class'      => '',       // extra classes on the <section>
 *          'help'       => true,     // the account/password line under the grid
 *          'css'        => true,     // false when the page linked portals.css
 *      ];
 *
 *  Every tile is ONE real anchor stretched over the card (.pt-link::after), so
 *  the whole tile is clickable while the accessible name, the tab stop and the
 *  focus ring stay on the link. Icons are decorative and marked aria-hidden —
 *  the visible portal name carries the meaning. lucide() emits a bare
 *  <i data-lucide> with no aria-hidden of its own, so every icon here needs a
 *  wrapper that carries it; that is what .pt-ico / .pt-i are for.
 *
 *  The icon plate and the "Sign in" affordance are design-system components
 *  (.fc-ico and .fc-more from §10), not new ones — portals.css only carries the
 *  size/hover deltas. See the note at the top of portals.css for why the card
 *  SHELL is still .portal-tile and not .focus-card.
 *
 *  The URLs are the contextual login routes defined in .htaccess:
 *      /login/admin  -> admin/login.php   (separate auth realm: IP allow-list,
 *                                          TOTP 2FA, its own session)
 *      /login/{role} -> login.php?portal={role}   for the six public audiences
 *  Those roles are an explicit whitelist in .htaccess; adding a portal here
 *  without adding it there gives a 404, which is the intended failure mode.
 * =============================================================================
 */
if (!defined('BASE_PATH')) {
    require_once __DIR__ . '/bootstrap.php';
}

if (!function_exists('portal_cards_list')) {
    /**
     * The seven portals, in the order the client specified. `staff` marks the
     * one that is not for the public — it is rendered as the full-width lead
     * band, which is also what keeps a 7-tile grid from ending in an orphan
     * (7 is prime; 1 + 6 divides at 3, 2 and 1 columns).
     */
    function portal_cards_list(): array
    {
        return [
            [
                'key'   => 'admin',
                'icon'  => 'shield-check',
                'name'  => 'Admin Portal',
                'desc'  => 'For administrators and authorized staff',
                'path'  => 'login/admin',
                'staff' => true,
                /* rel=nofollow, and robots.txt disallows /login/admin.
                   includes/footer.php:191 records a deliberate decision — "the
                   admin login is intentionally not linked from the public
                   footer" — and this page is linked from the footer and the
                   megamenu on every page, so the card would have quietly
                   reversed it. The card stays because the client asked for
                   seven doors and staff need one place to look, but it is kept
                   out of the crawl: the compensating controls its own
                   justification names (admin_ip_whitelist, force_2fa) are OFF
                   in this install and no admin account has TOTP enabled, so
                   what is behind the link is a password form with a lockout
                   and a math CAPTCHA. Turn those on in Security Center and the
                   nofollow becomes belt-and-braces rather than the control. */
                'rel'   => 'nofollow',
            ],
            [
                'key'  => 'school',
                'icon' => 'school',
                'name' => 'School Portal',
                'desc' => 'Manage students, teachers and programs',
                'path' => 'login/school',
            ],
            [
                'key'  => 'teacher',
                'icon' => 'presentation',
                'name' => 'Teacher Portal',
                'desc' => 'Manage courses, attendance and students',
                'path' => 'login/teacher',
            ],
            [
                'key'  => 'student',
                'icon' => 'graduation-cap',
                'name' => 'Student Portal',
                'desc' => 'Access courses, results and certificates',
                'path' => 'login/student',
            ],
            [
                'key'  => 'volunteer',
                'icon' => 'hand-heart',
                'name' => 'Volunteer Portal',
                'desc' => 'Manage campaigns, tasks and volunteer activities',
                'path' => 'login/volunteer',
            ],
            [
                'key'  => 'member',
                'icon' => 'id-card',
                'name' => 'Member Portal',
                'desc' => 'Manage membership, ID card and benefits',
                'path' => 'login/member',
            ],
            [
                'key'  => 'donor',
                'icon' => 'hand-coins',
                'name' => 'Donor Portal',
                'desc' => 'Manage donations, receipts and campaigns',
                'path' => 'login/donor',
            ],
        ];
    }
}

if (!function_exists('portal_cards_css')) {
    /**
     * The stylesheet ships with the section rather than from the footer: a
     * <link> after the markup would repaint the grid mid-load. A <link> in
     * <body> is honoured by every browser and blocks render for this subtree
     * only. Returns the tag once per request, however many times the section is
     * rendered — and portals.php, which links it itself, passes 'css' => false.
     */
    function portal_cards_css(): string
    {
        if (!empty($GLOBALS['pwf_portals_css_done'])) {
            return '';
        }
        $GLOBALS['pwf_portals_css_done'] = true;
        return '<link rel="stylesheet" href="' . e(asset('css/portals.css')) . '">';
    }
}

/* --- Options ------------------------------------------------------------- */
$pcOpt = isset($portal_cards) && is_array($portal_cards) ? $portal_cards : [];

$pcTag     = (($pcOpt['level'] ?? 'h2') === 'h3') ? 'h3' : 'h2';
$pcCardTag = $pcTag === 'h3' ? 'h4' : 'h3';          /* keep the levels nested */

/* The heading id is referenced by the section's aria-labelledby, so a page that
   renders the section twice must not emit it twice — a duplicate id makes the
   second aria-labelledby point at the first section's heading. */
$GLOBALS['pwf_portals_seq'] = ($GLOBALS['pwf_portals_seq'] ?? 0) + 1;
$pcId = (string) ($pcOpt['heading_id']
    ?? 'portalsHeading' . ($GLOBALS['pwf_portals_seq'] > 1 ? '-' . $GLOBALS['pwf_portals_seq'] : ''));
$pcEyebrow = array_key_exists('eyebrow', $pcOpt) ? (string) $pcOpt['eyebrow'] : 'Sign in';
$pcTitle   = (string) ($pcOpt['title'] ?? 'Access Your Portal');
$pcSub     = array_key_exists('subtitle', $pcOpt)
    ? (string) $pcOpt['subtitle']
    : 'Choose the portal that matches your EduSkill India account.';
$pcClass   = trim('section portals-band ' . (string) ($pcOpt['class'] ?? ''));
$pcHelp    = !array_key_exists('help', $pcOpt) || $pcOpt['help'];
$pcCss     = !array_key_exists('css', $pcOpt) || $pcOpt['css'];

if ($pcCss) {
    echo portal_cards_css();
}
?>
<section class="<?= e($pcClass) ?>" aria-labelledby="<?= e($pcId) ?>">
    <div class="container">

        <div class="section-head is-centered">
            <?php if ($pcEyebrow !== ''): ?>
                <span class="eyebrow"><?= e($pcEyebrow) ?></span>
            <?php endif; ?>
            <<?= $pcTag ?> class="section-title" id="<?= e($pcId) ?>"><?= e($pcTitle) ?></<?= $pcTag ?>>
            <?php if ($pcSub !== ''): ?>
                <p class="section-subtitle"><?= e($pcSub) ?></p>
            <?php endif; ?>
        </div>

        <ul class="portals-grid" role="list">
            <?php foreach (portal_cards_list() as $pcI => $pcCard):
                $pcStaff = !empty($pcCard['staff']); ?>
                <li class="portal-tile<?= $pcStaff ? ' is-staff' : '' ?> reveal<?= $pcI ? ' delay-' . min(3, $pcI) : '' ?>">
                    <span class="fc-ico pt-ico" aria-hidden="true"><?= lucide($pcCard['icon']) ?></span>

                    <?php if ($pcStaff): ?><div class="pt-body"><?php endif; ?>

                        <?php if ($pcStaff): ?>
                            <span class="pt-flag">
                                <span class="pt-i" aria-hidden="true"><?= lucide('lock') ?></span> Staff only
                            </span>
                        <?php endif; ?>

                        <<?= $pcCardTag ?> class="pt-name">
                            <a class="pt-link" href="<?= e(url($pcCard['path'])) ?>"<?php
                                if (!empty($pcCard['rel'])): ?> rel="<?= e($pcCard['rel']) ?>"<?php endif;
                            ?>><?= e($pcCard['name']) ?></a>
                        </<?= $pcCardTag ?>>
                        <p class="pt-desc"><?= e($pcCard['desc']) ?></p>

                    <?php if ($pcStaff): ?></div><?php endif; ?>

                    <?php /* The anchor above already IS the action; this is the
                             visual affordance, so it stays out of the a11y tree
                             rather than repeating the link name. .fc-more is the
                             design system's affordance — portals.css only sets
                             the size and re-binds the hover to .portal-tile. */ ?>
                    <span class="fc-more pt-go" aria-hidden="true">Sign in <?= lucide('arrow-right') ?></span>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($pcHelp): ?>
        <?php /* .link-underline is the design system's text-link treatment; these
                 links used to restate all five of its declarations by hand. */ ?>
        <p class="portals-help">
            <span>New to EduSkill India?</span>
            <a class="link-underline" href="<?= e(url('signup')) ?>"><span class="pt-i" aria-hidden="true"><?= lucide('user-plus') ?></span> Create an account</a>
            <a class="link-underline" href="<?= e(url('forgot-password')) ?>"><span class="pt-i" aria-hidden="true"><?= lucide('key-round') ?></span> Forgot your password</a>
            <a class="link-underline" href="<?= e(url('contact')) ?>"><span class="pt-i" aria-hidden="true"><?= lucide('life-buoy') ?></span> Not sure which portal?</a>
        </p>
        <?php endif; ?>

    </div>
</section>
<?php
/* The include shares scope with its host page — clear the locals so nothing
   here can collide with a $pcTitle / $pcCard the caller happens to use. */
unset($pcOpt, $pcTag, $pcCardTag, $pcId, $pcEyebrow, $pcTitle, $pcSub, $pcClass,
      $pcHelp, $pcCss, $pcI, $pcCard, $pcStaff, $portal_cards);
