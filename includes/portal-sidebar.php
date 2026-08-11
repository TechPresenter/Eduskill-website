<?php
/**
 * =============================================================================
 *  Portal sidebar — a slide-out drawer holding every sign-in destination.
 * =============================================================================
 *  Distinct from the header navigation, which deliberately has no sidebar: this
 *  is a single-purpose drawer for the eight role portals, opened from a "Sign
 *  in" control. Keeping it separate means the primary nav stays a flat icon bar
 *  while the portals — which people arrive looking for by role — get one
 *  organised place at every screen size.
 *
 *  Roles and destinations come from includes/auth_router.php, so this list can
 *  never drift from the routing table that decides where a login actually goes.
 * =============================================================================
 */

declare(strict_types=1);

/**
 * The eight portals, in the order people ask for them: public roles first,
 * staff roles last. 'blurb' says what the portal is for, not what it is called.
 */
function portal_sidebar_items(): array
{
    $roles = function_exists('auth_roles') ? auth_roles() : [];

    $order = [
        'member'      => ['Member Login',    'Membership, digital ID card, benefits and events.'],
        'donor'       => ['Donor Login',     'Donations, receipts and tax documents.'],
        'volunteer'   => ['Volunteer Login', 'Campaigns, tasks, events and volunteer hours.'],
        'student'     => ['Student Login',   'Courses, assignments, results and certificates.'],
        'teacher'     => ['Teacher Login',   'Classes, students, attendance and results.'],
        'school'      => ['School Login',    'Students, teachers, batches, fees and reports.'],
        'staff'       => ['Staff Login',     'Your assigned administrative workspace.'],
        'super-admin' => ['Admin Login',     'Full platform administration.'],
    ];

    $out = [];
    foreach ($order as $slug => [$label, $blurb]) {
        $cfg = $roles[$slug] ?? [];
        // /login/admin is the documented entry for the super-admin realm.
        $path = $slug === 'super-admin' ? 'login/admin' : 'login/' . $slug;
        $out[] = [
            'slug'  => $slug,
            'label' => $label,
            'blurb' => $blurb,
            'icon'  => $cfg['icon'] ?? 'log-in',
            'url'   => $path,
            'staff' => in_array($slug, ['teacher', 'school', 'staff', 'super-admin'], true),
        ];
    }
    return $out;
}

/** The trigger button. Place it anywhere in the header. */
function portal_sidebar_trigger(): string
{
    return '<button class="ps-trigger" type="button" data-ps-toggle aria-expanded="false" '
         . 'aria-controls="portal-sidebar">'
         . lucide('log-in')
         . '<span class="ps-trigger-txt">Sign in</span></button>';
}

/** The drawer itself. Rendered once, near the end of the document. */
function portal_sidebar_render(): string
{
    $items = portal_sidebar_items();

    $out  = '<div class="ps-scrim" data-ps-scrim hidden></div>';
    $out .= '<aside class="ps-drawer" id="portal-sidebar" data-ps-drawer hidden '
          . 'role="dialog" aria-modal="true" aria-labelledby="ps-title">';

    $out .= '<div class="ps-head">'
          . '<div><p class="ps-eyebrow">Access your portal</p>'
          . '<h2 class="ps-title" id="ps-title">Sign in</h2></div>'
          . '<button class="ps-close" type="button" data-ps-close aria-label="Close sign-in menu">'
          . lucide('x') . '</button></div>';

    $out .= '<p class="ps-note">' . lucide('info')
          . ' Signing in from any door takes you to the dashboard for your actual role.</p>';

    $out .= '<nav class="ps-nav" aria-label="Portals">';

    $inStaff = false;
    foreach ($items as $it) {
        if ($it['staff'] && !$inStaff) {
            $inStaff = true;
            $out .= '<p class="ps-group" id="ps-g-staff">Staff &amp; institutions</p>';
        } elseif (!$it['staff'] && !$inStaff && !isset($startedPublic)) {
            $startedPublic = true;
            $out .= '<p class="ps-group" id="ps-g-public">Members &amp; supporters</p>';
        }

        $out .= '<a class="ps-link" href="' . e(url($it['url'])) . '">'
              . '<span class="ps-ico" aria-hidden="true">' . lucide($it['icon']) . '</span>'
              . '<span class="ps-txt"><strong>' . e($it['label']) . '</strong>'
              . '<small>' . e($it['blurb']) . '</small></span>'
              . '<span class="ps-arrow" aria-hidden="true">' . lucide('arrow-right') . '</span>'
              . '</a>';
    }
    $out .= '</nav>';

    $out .= '<div class="ps-foot">'
          . '<a class="btn btn-primary btn-block" href="' . e(url('signup')) . '">'
          . lucide('user-plus') . ' Create an account</a>'
          . '<p class="ps-help">Trouble signing in? <a href="' . e(url('forgot-password')) . '">Reset your password</a>'
          . ' or <a href="' . e(url('contact')) . '">contact us</a>.</p>'
          . '</div>';

    $out .= '</aside>';
    return $out;
}
