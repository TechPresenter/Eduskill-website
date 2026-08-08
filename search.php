<?php
/**
 * =============================================================================
 *  Global search — full-text-ish search across pages, blogs, programs, events,
 *  schemes, scholarships and campaigns, with simple relevance ranking.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

$q = trim((string) get('q', ''));
$results = [];

if ($q !== '') {
    $like = '%' . $q . '%';

    /** Helper to append rows from one source. */
    $collect = static function (string $type, string $sql, string $urlPattern, array $extra = []) use (&$results, $like, $q) {
        try {
            foreach (db_all($sql, [':q' => $like]) as $r) {
                $title = $r['title'] ?? ($r['name'] ?? '');
                $results[] = [
                    'type'    => $type,
                    'title'   => $title,
                    'excerpt' => excerpt($r['excerpt'] ?? $r['short_description'] ?? $r['description'] ?? $r['content'] ?? '', 26),
                    'url'     => str_replace('{slug}', (string) ($r['slug'] ?? ''), $urlPattern),
                    // relevance: exact-ish title match ranks higher
                    'score'   => stripos($title, $q) !== false ? 2 : 1,
                ];
            }
        } catch (Throwable $e) { /* table may be absent */ }
    };

    // NOTE: named params may not repeat in one query (PDO emulation off → HY093),
    // hence CONCAT_WS over the searchable columns with a single :q.
    $collect('Page',        "SELECT title, slug, content   FROM pages    WHERE status='published' AND CONCAT_WS(' ', title, content) LIKE :q LIMIT 10", '{slug}');
    $collect('Blog',        "SELECT title, slug, excerpt   FROM blogs    WHERE status='published' AND deleted_at IS NULL AND CONCAT_WS(' ', title, excerpt, content) LIKE :q LIMIT 10", 'blog-details?slug={slug}');
    $collect('Program',     "SELECT title, slug, short_description FROM programs WHERE status='active' AND deleted_at IS NULL AND CONCAT_WS(' ', title, short_description, description) LIKE :q LIMIT 10", 'programs?slug={slug}');
    $collect('Event',       "SELECT title, slug, excerpt   FROM events   WHERE status='published' AND deleted_at IS NULL AND CONCAT_WS(' ', title, description) LIKE :q LIMIT 10", 'events?slug={slug}');
    $collect('Scheme',      "SELECT title, slug, short_description FROM schemes WHERE status='active' AND CONCAT_WS(' ', title, short_description, description) LIKE :q LIMIT 10", 'schemes?slug={slug}');
    $collect('Scholarship', "SELECT title, slug, description FROM scholarships WHERE status='open' AND CONCAT_WS(' ', title, description) LIKE :q LIMIT 10", 'scholarship');
    $collect('Campaign',    "SELECT title, slug, short_description FROM campaigns WHERE status IN ('active','completed') AND deleted_at IS NULL AND CONCAT_WS(' ', title, short_description, description) LIKE :q LIMIT 10", 'campaigns?slug={slug}');

    // Sort by relevance score (desc).
    usort($results, static fn($a, $b) => $b['score'] <=> $a['score']);
}

seo_set([
    'title'       => $q !== '' ? 'Search: ' . $q : 'Search',
    'description' => $q !== ''
        ? 'Search results for "' . $q . '" — matching pages, programs, events, schemes and stories from ' . SITE_NAME . '.'
        : 'Search across the pages, programs, events, schemes and stories of ' . SITE_NAME . '.',
    'robots'      => 'noindex,follow',
]);
$page_hero = ['title' => 'Search', 'subtitle' => $q !== '' ? 'Results for "' . $q . '"' : 'Find pages, programs, events and more', 'breadcrumb' => [['label' => 'Search']]];
include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container container-narrow">
        <form method="get" action="<?= e(url('search')) ?>" class="flex gap-2 mb-4" role="search">
            <input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('search.placeholder', 'Search the site…')) ?>" autofocus aria-label="Search">
            <button class="btn btn-3d" type="submit"><?= lucide('search') ?> Search</button>
        </form>

        <?php if ($q === ''): ?>
            <div class="empty-state"><div class="icon"><?= lucide('search') ?></div>Type a keyword above to search across the site.</div>
        <?php elseif (!$results): ?>
            <div class="empty-state"><div class="icon"><?= lucide('search-x') ?></div><?= e(t('search.no_results', 'No results found. Try different keywords.')) ?></div>
        <?php else: ?>
            <p class="text-muted mb-3"><?= count($results) ?> result<?= count($results) === 1 ? '' : 's' ?> for “<strong><?= e($q) ?></strong>”</p>
            <div class="grid gap-2">
                <?php foreach ($results as $r): ?>
                    <a class="card" href="<?= e(url($r['url'])) ?>" style="display:block;">
                        <div class="card-body" style="padding:1.1rem 1.3rem;">
                            <span class="badge badge-brand mb-1"><?= e($r['type']) ?></span>
                            <h2 class="card-title" style="margin:.3rem 0;"><?= e($r['title']) ?></h2>
                            <?php if ($r['excerpt']): ?><p class="card-text" style="margin:0;"><?= e($r['excerpt']) ?></p><?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
