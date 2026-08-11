<?php
/**
 * =============================================================================
 *  ACCESS YOUR PORTAL — the public sign-in directory.
 * -----------------------------------------------------------------------------
 *  Seven audiences, seven contextual login URLs. People do not think in database
 *  tables, they think "I'm a teacher" or "I'm a donor", so this page maps each
 *  audience onto the right form. It deliberately carries NO credential fields —
 *  it only points at them, which is also why it is safe to index.
 *
 *  The section itself lives in includes/portal-cards.php so the same seven doors
 *  can be dropped onto the homepage without a second copy of the markup.
 *
 *  Destinations (routed in .htaccess):
 *      /login/admin   -> admin/login.php    (separate auth realm)
 *      /login/{role}  -> login.php?portal={role}
 *
 *  No "already signed in" redirect on purpose. The previous two-door version of
 *  this page bounced a signed-in visitor to /account, which on a seven-portal
 *  directory would mean a signed-in member could never reach, say, the
 *  volunteer door. Each login page already redirects an authenticated visitor
 *  to its own dashboard, so the guard belongs there, not here.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

seo_set([
    'title'       => 'Access Your Portal',
    /* The public-facing roles only. "admin" was in both the description and the
       keywords, which made an indexable page that is linked from the footer and
       the megamenu of every page into something built to rank for admin-login
       queries — and .htaccess aliases /login/admin into the admin realm. The
       card still exists for staff (rel=nofollow, and robots.txt disallows
       /login/admin); it just is not advertised to search engines. */
    'description' => 'Sign in to EDUSKILL INDIA FOUNDATION. Choose your portal — school, teacher, student, volunteer, member or donor — and reach the dashboard built for your role.',
    'keywords'    => 'EduSkill India login, student portal, teacher portal, school portal, member portal, donor portal, volunteer portal',
    'page_key'    => 'portals',
    /* Indexable: the page is a public signpost with no credential fields and no
       personal data, and "<role> portal login" is exactly what people search. */
    'robots'      => 'index,follow',
]);

$page_hero = [
    'title'      => 'Access Your Portal',
    'subtitle'   => 'Choose the portal that matches your EduSkill India account. Each one opens the sign-in page for that role.',
    'breadcrumb' => [['label' => 'Access Your Portal']],
];

include __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="<?= e(asset('css/portals.css')) ?>">

<?php
/* 'css' => false because the <link> above already shipped the stylesheet before
   the markup — the include would otherwise emit it a second time.

   The heading is re-worded here and only here. The include's default is the
   client's "Access Your Portal", which is right on the homepage — but on THIS
   page the masthead h1 already says exactly that, and an h2 repeating its
   parent h1 verbatim is noise in the outline a screen reader reads out. The
   client's line stays the page title; the section asks the question instead. */
$portal_cards = [
    'heading_id' => 'portalsHeading',
    'eyebrow'    => 'Sign in',
    'title'      => 'Which portal is yours?',
    'subtitle'   => 'Choose the portal that matches your EduSkill India account.',
    'css'        => false,
];
include __DIR__ . '/includes/portal-cards.php';
?>

<!-- ============================== CLOSING INVITATION ============================== -->
<?php /* .cta-band.is-yellow — the warm invitation band from the design system:
         sunshine field, forest-green type (7.95:1) and a green control, because
         a white button on yellow is 1.2:1 and an orange one 2.4:1.

         One filled button, and the two lesser choices as .link-underline text
         (7.95:1 on the field). An outline button was the obvious second control,
         but design-system.css draws its border at 45% primary, which composites
         to 2.26:1 on yellow — under the 3:1 floor for a control's boundary. A
         link needs no boundary. See portals.css §5. */ ?>
<section class="section portals-cta">
    <div class="container">
        <div class="cta-band is-yellow">
            <h2>No account yet?</h2>
            <p>Membership opens the member portal — your digital ID card, receipts and event registrations. Volunteering and donating create an account for you automatically.</p>
            <div class="cta-actions">
                <?php /* The arrow is in an aria-hidden wrapper like every other
                         lucide() call in this section: lucide() emits a bare
                         <i data-lucide> that the script swaps for an <svg> with
                         no aria-hidden of its own. Harmless here (an empty svg
                         contributes no text, so the name stays "Create an
                         account") but the discipline has to be uniform or the
                         next icon added inherits the gap. */ ?>
                <a class="btn btn-secondary" href="<?= e(url('signup')) ?>">Create an account <span class="pt-i" aria-hidden="true"><?= lucide('arrow-right') ?></span></a>
            </div>
            <p class="portals-cta-links">
                <a class="link-underline" href="<?= e(url('membership')) ?>">What membership includes</a>
                <a class="link-underline" href="<?= e(url('contact')) ?>">Ask us which portal you need</a>
            </p>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
