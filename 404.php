<?php
/**
 * =============================================================================
 *  404 — Page Not Found
 * -----------------------------------------------------------------------------
 *  A friendly, self-contained error page. Sends a proper 404 status, tells
 *  search engines not to index it (but to keep following links), and offers
 *  the visitor clear ways back into the site. The "popular pages" and
 *  "explore our programs" blocks are DB-driven with graceful fallbacks so the
 *  page is useful even before any content is seeded.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* ---- Data: popular navigation + featured programs (DB-driven w/ fallback) - */
$popularLinks = [];
try {
    $popularLinks = db_all(
        "SELECT title, url FROM menus
         WHERE status = 1 AND location IN ('header','both') AND parent_id IS NULL
         ORDER BY sort_order ASC, id ASC LIMIT 6"
    );
} catch (Throwable $e) {
    $popularLinks = [];
}
if (empty($popularLinks)) {
    $popularLinks = [
        ['title' => 'About Us',     'url' => 'about'],
        ['title' => 'Our Programs', 'url' => 'programs'],
        ['title' => 'Events',       'url' => 'events'],
        ['title' => 'Blog & News',  'url' => 'blogs'],
        ['title' => 'Gallery',      'url' => 'gallery'],
        ['title' => 'Contact Us',   'url' => 'contact'],
    ];
}

$programs = [];
try {
    $programs = db_all(
        "SELECT title, slug, icon, color FROM programs
         WHERE status = 'active' ORDER BY is_featured DESC, sort_order ASC LIMIT 6"
    );
} catch (Throwable $e) {
    $programs = [];
}

/* ---- Proper 404 response BEFORE any output ---------------------------- */
http_response_code(404);

/* ---- SEO: keep 404s out of the index, but keep following links -------- */
seo_set([
    'title'       => 'Page Not Found',
    'description' => 'The page you were looking for could not be found. Explore our programs, events and stories, or head back to the EDUSKILL INDIA FOUNDATION homepage.',
    'page_key'    => '404',
    'robots'      => 'noindex,follow',
]);

/* No $page_hero — this page centres its own content. */
include __DIR__ . '/includes/header.php';
?>

<!-- ============================== 404 ============================== -->
<section class="section text-center">
    <div class="container container-narrow">

        <span class="eyebrow" style="justify-content:center;">Error 404</span>

        <div class="text-gradient reveal"
             style="font-size:clamp(6rem,24vw,13rem);font-weight:800;line-height:.95;letter-spacing:-.04em;margin:.25rem 0;">
            404
        </div>

        <h1 class="section-title reveal delay-1">Oops! This page wandered off.</h1>
        <p class="section-subtitle reveal delay-1" style="margin-inline:auto;">
            The page you're looking for may have been moved, renamed, or never existed.
            Let's get you back on track — there's a lot of good happening across Bihar to explore.
        </p>

        <div class="hero-actions reveal delay-2" style="justify-content:center;">
            <a class="btn btn-primary btn-lg" href="<?= e(url('')) ?>"><?= lucide('home') ?> Back to Home</a>
            <a class="btn btn-outline btn-lg" href="<?= e(url('programs')) ?>">Our Programs</a>
            <a class="btn btn-secondary btn-lg" href="<?= e(url('contact')) ?>">Contact Us</a>
        </div>

        <div class="divider" style="max-width:520px;margin:2.5rem auto 1rem;"></div>

        <!-- Popular pages -->
        <h2 class="text-muted mb-2" style="font-weight:600;letter-spacing:.02em;font-size:1rem;">Popular pages</h2>
        <div class="flex flex-wrap justify-center gap-2">
            <?php foreach ($popularLinks as $link): ?>
                <a class="chip"
                   href="<?= e(preg_match('#^https?://#i', $link['url']) ? $link['url'] : url($link['url'])) ?>">
                    <?= e($link['title']) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($programs): ?>
        <!-- Explore our programs (only shown when programs are seeded) -->
        <div class="mt-4">
            <h2 class="text-muted mb-2" style="font-weight:600;letter-spacing:.02em;font-size:1rem;">Or explore our programs</h2>
            <div class="grid grid-3 gap-2 text-center">
                <?php foreach ($programs as $p): ?>
                    <a class="card reveal"
                       href="<?= e($p['slug'] ? url('programs?slug=' . $p['slug']) : url('programs')) ?>"
                       style="padding:1.1rem;color:inherit;">
                        <div class="icon-badge" style="margin-inline:auto;background:linear-gradient(135deg,<?= e($p['color'] ?: '#0B4E3D') ?>,#174D3D);">
                            <?= lucide($p['icon'] ?: 'star') ?>
                        </div>
                        <strong class="mt-2" style="display:block;"><?= e($p['title']) ?></strong>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <p class="text-muted mt-4">
            Still stuck? Email us at
            <a href="mailto:<?= e(get_setting('contact_email', SITE_EMAIL)) ?>"><?= e(get_setting('contact_email', SITE_EMAIL)) ?></a>
            and we'll help you find your way.
        </p>

    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
