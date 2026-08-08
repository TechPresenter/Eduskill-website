<?php
/**
 * =============================================================================
 *  Frontend — Dynamic page renderer for the block-based Page Builder.
 *
 *  Loads a published `pages` row by ?slug=, then renders each ordered block
 *  from the `blocks` JSON column with premium markup + lucide() icons:
 *      hero    -> a coloured section       text -> .prose
 *      image   -> figure                   cards -> grid of .card-3d
 *      columns -> responsive grid
 *  Falls back to rendering `pages.content` when there are no blocks.
 *  Sends a proper 404 (inside the site layout) when the page is not found.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

$slug = trim((string) get('slug', ''));

/* These slugs have dedicated root pages — 301 to the canonical URL, no duplicate render. */
$dedicated = ['privacy-policy', 'terms', 'refund-policy', 'cookie-policy', 'disclaimer'];
if (in_array($slug, $dedicated, true)) {
    redirect('/' . $slug, 301);
}

$page = $slug !== ''
    ? db_row(
        "SELECT * FROM pages WHERE slug = :slug AND status = 'published' AND deleted_at IS NULL LIMIT 1",
        [':slug' => $slug]
    )
    : null;

/* ---------------------------------------------------------------- 404 */
if (!$page) {
    http_response_code(404);
    seo_set([
        'title'       => 'Page Not Found',
        'description' => 'The page you were looking for could not be found.',
        'robots'      => 'noindex,follow',
    ]);
    include __DIR__ . '/includes/header.php';
    ?>
    <section class="section text-center">
        <div class="container container-narrow">
            <span class="eyebrow" style="justify-content:center;">Error 404</span>
            <div class="text-gradient reveal" style="font-size:clamp(5rem,20vw,11rem);font-weight:800;line-height:.95;letter-spacing:-.04em;margin:.25rem 0;">404</div>
            <h1 class="section-title">We couldn't find that page</h1>
            <p class="section-subtitle" style="margin-inline:auto;">The page may have been unpublished, renamed or removed. Let's get you back on track.</p>
            <div class="hero-actions" style="justify-content:center;">
                <a class="btn btn-primary btn-lg" href="<?= e(url('')) ?>"><?= lucide('home') ?> Back to Home</a>
                <a class="btn btn-outline btn-lg" href="<?= e(url('contact')) ?>">Contact Us</a>
            </div>
        </div>
    </section>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

/* ---------------------------------------------------------------- Blocks */
/* Run the stored JSON back through the registry whitelist rather than trusting
   it. The save path already sanitises, but a row could predate a widget being
   removed, or have been written directly in the database — this guarantees the
   renderer only ever sees block types and fields it actually knows. */
$blocks = !empty($page['blocks']) ? blocks_sanitize((string) $page['blocks']) : [];

/* A safe hex colour, or null. */
$hexOk = static fn($v): ?string => (is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v)) ? $v : null;
/* A safe lucide icon slug. */
$iconOk = static fn($v): string => preg_replace('/[^a-z0-9\-]/', '', strtolower((string) $v)) ?: 'star';

/* ---------------------------------------------------------------- SEO + hero */
seo_set([
    'title'       => $page['title'],
    'description' => $page['subtitle'] ?: excerpt(strip_tags((string) ($page['content'] ?? '')), 30),
    'page_key'    => 'page:' . $page['slug'],
    'type'        => 'article',
]);

$page_hero = [
    'title'      => $page['title'],
    'subtitle'   => $page['subtitle'] ?? '',
    'breadcrumb' => [['label' => $page['title']]],
];

include __DIR__ . '/includes/header.php';
?>

<?php if ($blocks): ?>
    <?php /* Rendered by blocks_render() (includes/blocks.php) — the same
             registry that drives the admin editor and the save-time
             whitelist, so all 15 widget types render here automatically
             instead of only the five this page used to switch on. */ ?>
    <?= blocks_render($blocks) ?>


<?php elseif (trim((string) ($page['content'] ?? '')) !== ''): ?>
    <!-- Fallback: legacy HTML content when no blocks are defined -->
    <section class="section">
        <div class="container container-narrow reveal">
            <div class="prose"><?= rich_text($page['content']) ?></div>
        </div>
    </section>

<?php else: ?>
    <section class="section">
        <div class="container container-narrow text-center">
            <div class="empty-state"><div class="icon"><?= lucide('file-text') ?></div>This page has no content yet.</div>
        </div>
    </section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
