<?php
/**
 * =============================================================================
 *  Blog — public article listing.
 *
 *  Filters (all optional, combinable, all preserved across pagination):
 *    • ?q=<term>          → full-text-ish search on title / excerpt (LIKE)
 *    • ?category=<slug>   → limit to a single blog category
 *    • ?page=<n>          → handled by paginate()
 *
 *  Layout: grid-sidebar — a two-column card grid of posts on the left, a
 *  sticky sidebar (search, categories, popular tags, recent posts) on the
 *  right. Every query is DB-driven with graceful fallbacks so the page never
 *  looks broken before content is seeded.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* ---- Inputs ----------------------------------------------------------- */
$q       = clean(get('q', ''));
$catSlug = clean(get('category', ''));
$tagSlug = clean(get('tag', ''));

/* Resolve (and validate) the active category. Unknown slugs are ignored. */
$activeCategory = null;
if ($catSlug !== '') {
    $activeCategory = db_row(
        "SELECT id, name, slug FROM blog_categories WHERE slug = :s AND status = 1 LIMIT 1",
        [':s' => $catSlug]
    );
    if (!$activeCategory) {
        $catSlug = '';
    }
}

/* Resolve the active tag (for ?tag= filtering from tag clouds / article tags). */
$activeTag = null;
if ($tagSlug !== '') {
    $activeTag = db_row("SELECT id, name, slug FROM blog_tags WHERE slug = :s LIMIT 1", [':s' => $tagSlug]);
    if (!$activeTag) {
        $tagSlug = '';
    }
}

/* ---- Build the WHERE dynamically with named placeholders --------------- */
$conditions = ["b.status = 'published'", "b.deleted_at IS NULL", "(b.published_at IS NULL OR b.published_at <= NOW())"];
$params     = [];

if ($q !== '') {
    $conditions[]  = "CONCAT_WS(' ', b.title, b.excerpt) LIKE :q";
    $params[':q']  = '%' . $q . '%';
}
if ($catSlug !== '') {
    $conditions[]   = "c.slug = :cat";
    $params[':cat'] = $catSlug;
}
if ($tagSlug !== '') {
    $conditions[]   = "EXISTS (SELECT 1 FROM blog_tag_map m JOIN blog_tags t ON t.id = m.tag_id WHERE m.blog_id = b.id AND t.slug = :tag)";
    $params[':tag'] = $tagSlug;
}
$whereSql = implode(' AND ', $conditions);

$listSql = "SELECT b.*, c.name AS category_name, c.slug AS category_slug
            FROM blogs b
            LEFT JOIN blog_categories c ON c.id = b.category_id
            WHERE $whereSql
            ORDER BY b.is_sticky DESC, b.published_at DESC, b.id DESC";

$pager = paginate($listSql, $params, 6);
$posts = $pager['items'];

/* ---- Sidebar data ----------------------------------------------------- */
$categories = db_all(
    "SELECT c.id, c.name, c.slug, c.parent_id, COUNT(b.id) AS cnt
     FROM blog_categories c
     LEFT JOIN blogs b
            ON b.category_id = c.id
           AND b.status = 'published'
           AND b.deleted_at IS NULL
           AND (b.published_at IS NULL OR b.published_at <= NOW())
     WHERE c.status = 1
     GROUP BY c.id, c.name, c.slug, c.parent_id
     ORDER BY c.sort_order ASC, c.name ASC"
);
/* Group categories by parent for a hierarchical sidebar tree. */
$catsByParent = [];
foreach ($categories as $c) { $catsByParent[(int) ($c['parent_id'] ?? 0)][] = $c; }

$tags = db_all(
    "SELECT t.id, t.name, t.slug, COUNT(b.id) AS cnt
     FROM blog_tags t
     JOIN blog_tag_map m ON m.tag_id = t.id
     JOIN blogs b
            ON b.id = m.blog_id
           AND b.status = 'published'
           AND b.deleted_at IS NULL
           AND (b.published_at IS NULL OR b.published_at <= NOW())
     GROUP BY t.id, t.name, t.slug
     ORDER BY cnt DESC, t.name ASC
     LIMIT 24"
);
/* Tag-cloud weighting: map counts to 1..5 size buckets. */
$tagMax = 1;
foreach ($tags as $t) { $tagMax = max($tagMax, (int) $t['cnt']); }

$recent = db_all(
    "SELECT b.title, b.slug, b.featured_image, b.published_at, b.created_at
     FROM blogs b
     WHERE b.status = 'published' AND b.deleted_at IS NULL
       AND (b.published_at IS NULL OR b.published_at <= NOW())
     ORDER BY b.published_at DESC, b.id DESC
     LIMIT 5"
);

/* ---- SEO + hero ------------------------------------------------------- */
if ($activeCategory) {
    $seoTitle = $activeCategory['name'] . ' — Blog';
} elseif ($activeTag) {
    $seoTitle = '#' . $activeTag['name'] . ' — Blog';
} elseif ($q !== '') {
    $seoTitle = 'Search: ' . $q . ' — Blog';
} else {
    $seoTitle = 'Blog';
}

seo_set([
    'title'       => $seoTitle,
    'description' => 'Stories of change, field updates, success stories and news from EDUSKILL INDIA FOUNDATION — read how education, healthcare, skill development and relief work is transforming communities across Bihar.',
    'page_key'    => 'blogs',
]);

$heroCrumb = [['label' => 'Blog']];
if ($activeCategory) {
    $heroCrumb = [['label' => 'Blog', 'url' => url('blogs')], ['label' => $activeCategory['name']]];
}

$page_hero = [
    'title'      => 'Our Blog',
    'subtitle'   => 'Insights, updates and stories from the field — follow the journey of change we are creating together across Bihar.',
    'breadcrumb' => $heroCrumb,
];

include __DIR__ . '/includes/header.php';
?>

<!-- ============================== BLOG LISTING + SIDEBAR ============================== -->
<section class="section">
    <div class="container grid grid-sidebar gap-4 items-start">

        <!-- ---------- MAIN COLUMN ---------- -->
        <div class="reveal">

            <!-- Results header -->
            <div class="flex justify-between items-center flex-wrap gap-2 mb-3">
                <div>
                    <?php if ($activeCategory): ?>
                        <span class="eyebrow">Category</span>
                        <h2 class="section-title mb-0" style="font-size:1.6rem;"><?= e($activeCategory['name']) ?></h2>
                    <?php elseif ($activeTag): ?>
                        <span class="eyebrow">Tag</span>
                        <h2 class="section-title mb-0" style="font-size:1.6rem;">#<?= e($activeTag['name']) ?></h2>
                    <?php elseif ($q !== ''): ?>
                        <span class="eyebrow">Search</span>
                        <h2 class="section-title mb-0" style="font-size:1.6rem;">Results for &ldquo;<?= e($q) ?>&rdquo;</h2>
                    <?php else: ?>
                        <span class="eyebrow">From the Field</span>
                        <h2 class="section-title mb-0" style="font-size:1.6rem;">Latest Articles</h2>
                    <?php endif; ?>
                </div>
                <small class="text-muted"><?= (int) $pager['total'] ?> article<?= (int) $pager['total'] === 1 ? '' : 's' ?></small>
            </div>

            <!-- Active-filter chips -->
            <?php if ($activeCategory || $activeTag || $q !== ''): ?>
                <div class="flex items-center gap-2 flex-wrap mb-3">
                    <?php if ($activeCategory): ?>
                        <span class="chip">Category: <?= e($activeCategory['name']) ?></span>
                    <?php endif; ?>
                    <?php if ($activeTag): ?>
                        <span class="chip">Tag: #<?= e($activeTag['name']) ?></span>
                    <?php endif; ?>
                    <?php if ($q !== ''): ?>
                        <span class="chip">Search: &ldquo;<?= e($q) ?>&rdquo;</span>
                    <?php endif; ?>
                    <a class="chip" href="<?= e(url('blogs')) ?>">&times; Clear filters</a>
                </div>
            <?php endif; ?>

            <?php if ($posts): ?>
                <div class="blog-masonry">
                    <?php foreach ($posts as $b):
                        $link = url('blog-details?slug=' . $b['slug']);
                        $date = $b['published_at'] ?: $b['created_at'];
                    ?>
                        <article class="card reveal">
                            <!-- div wrapper: anchors must not nest (image link + category link). -->
                            <div class="card-media">
                                <a href="<?= e($link) ?>" aria-label="<?= e($b['title']) ?>" style="display:block;">
                                    <img src="<?= e(image_url($b['featured_image'], 'blog')) ?>" alt="<?= e($b['title']) ?>" loading="lazy" width="600" height="375">
                                </a>
                                <?php if (!empty($b['category_name'])): ?>
                                    <a href="<?= e(url('blogs?category=' . $b['category_slug'])) ?>" class="badge badge-brand" style="position:absolute;top:12px;left:12px;"><?= e($b['category_name']) ?></a>
                                <?php endif; ?>
                                <?php if (!empty($b['is_breaking'])): ?>
                                    <span class="badge" style="position:absolute;top:12px;right:12px;background:#dc2626;color:#fff;"><?= lucide('zap') ?> Breaking</span>
                                <?php elseif (!empty($b['is_featured'])): ?>
                                    <span class="badge" style="position:absolute;top:12px;right:12px;background:#E67B1D;color:#111;"><?= lucide('star') ?> Featured</span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body flex flex-col">
                                <div class="flex items-center gap-2 text-muted mb-1" style="font-size:.82rem;">
                                    <span><?= lucide('calendar-days') ?> <?= e(format_date($date, 'd M Y')) ?></span>
                                    <?php if (!empty($b['reading_time'])): ?>
                                        <span>&middot; <?= (int) $b['reading_time'] ?> min read</span>
                                    <?php endif; ?>
                                </div>
                                <h3 class="card-title"><a href="<?= e($link) ?>" style="color:inherit;"><?= e($b['title']) ?></a></h3>
                                <p class="card-text"><?= e(excerpt($b['excerpt'] ?: ($b['content'] ?? ''), 22)) ?></p>
                                <a class="btn btn-outline btn-sm mt-auto" href="<?= e($link) ?>">Read Article &rarr;</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($pager['links'])): ?>
                    <div class="flex justify-center mt-4"><?= $pager['links'] ?></div>
                <?php endif; ?>

            <?php else: ?>
                <div class="empty-state">
                    <div class="icon-badge"><?= lucide('clipboard-pen') ?></div>
                    <h3>No articles found</h3>
                    <p class="text-muted">
                        <?php if ($q !== '' || $activeCategory): ?>
                            We couldn't find any posts matching your filters. Try a different keyword or browse all of our stories.
                        <?php else: ?>
                            Fresh stories from the field are on the way. Check back soon for updates, success stories and news from our programs.
                        <?php endif; ?>
                    </p>
                    <div class="flex flex-wrap justify-center gap-2 mt-3">
                        <a class="btn btn-primary" href="<?= e(url('blogs')) ?>">View All Articles</a>
                        <a class="btn btn-outline" href="<?= e(url('programs')) ?>">Explore Our Programs</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- ---------- SIDEBAR ---------- -->
        <aside class="reveal delay-1">

            <!-- Search -->
            <div class="card mb-4">
                <div class="card-body">
                    <h3 class="card-title">Search Articles</h3>
                    <form action="<?= e(url('blogs')) ?>" method="get" role="search">
                        <?php if ($activeCategory): ?>
                            <input type="hidden" name="category" value="<?= e($activeCategory['slug']) ?>">
                        <?php endif; ?>
                        <div class="form-group mb-0">
                            <input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Type a keyword&hellip;" aria-label="Search articles">
                        </div>
                        <button class="btn btn-primary btn-block mt-2" type="submit"><?= lucide('search') ?> Search</button>
                    </form>
                </div>
            </div>

            <!-- Categories -->
            <div class="card mb-4">
                <div class="card-body">
                    <h3 class="card-title">Categories</h3>
                    <?php if ($categories):
                        $renderCatTree = function ($parent, $depth) use (&$renderCatTree, $catsByParent, $activeCategory) {
                            if (empty($catsByParent[$parent])) return;
                            foreach ($catsByParent[$parent] as $cat):
                                $isActive = $activeCategory && $activeCategory['slug'] === $cat['slug']; ?>
                                <li>
                                    <a href="<?= e(url('blogs?category=' . $cat['slug'])) ?>" class="flex justify-between items-center" style="padding:.55rem 0;padding-left:<?= (int) ($depth * 14) ?>px;border-bottom:1px solid var(--border);<?= $isActive ? 'color:var(--brand-600);font-weight:700;' : 'color:inherit;' ?>">
                                        <span><?= $depth > 0 ? '<span style="opacity:.45;">&mdash; </span>' : '' ?><?= e($cat['name']) ?></span>
                                        <span class="badge badge-muted"><?= (int) $cat['cnt'] ?></span>
                                    </a>
                                </li>
                                <?php $renderCatTree((int) $cat['id'], $depth + 1);
                            endforeach;
                        }; ?>
                        <ul style="list-style:none;margin:0;padding:0;">
                            <?php $renderCatTree(0, 0); ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted mb-0" style="font-size:.9rem;">Categories will appear here as we publish more stories.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Popular tags -->
            <div class="card mb-4">
                <div class="card-body">
                    <h3 class="card-title">Popular Tags</h3>
                    <?php if ($tags): ?>
                        <div class="flex flex-wrap gap-2" style="align-items:baseline;">
                            <?php foreach ($tags as $t):
                                $sz  = 0.76 + ((int) $t['cnt'] / $tagMax) * 0.55;
                                $act = $activeTag && $activeTag['slug'] === $t['slug']; ?>
                                <a class="chip" style="font-size:<?= number_format($sz, 2) ?>rem;<?= $act ? 'background:var(--brand-600);color:#fff;' : '' ?>" href="<?= e(url('blogs?tag=' . $t['slug'])) ?>">#<?= e($t['name']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0" style="font-size:.9rem;">Tags will appear here as articles are tagged.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent posts -->
            <div class="card">
                <div class="card-body">
                    <h3 class="card-title">Recent Posts</h3>
                    <?php if ($recent): ?>
                        <ul style="list-style:none;margin:0;padding:0;">
                            <?php foreach ($recent as $r):
                                $rlink = url('blog-details?slug=' . $r['slug']);
                            ?>
                                <li class="flex gap-2 items-center" style="padding:.6rem 0;border-bottom:1px solid var(--border);">
                                    <a href="<?= e($rlink) ?>" style="flex:0 0 64px;">
                                        <img src="<?= e(image_url($r['featured_image'], 'blog')) ?>" alt="<?= e($r['title']) ?>" loading="lazy" width="64" height="64" style="width:64px;height:64px;object-fit:cover;border-radius:var(--radius-sm,10px);display:block;">
                                    </a>
                                    <div>
                                        <a href="<?= e($rlink) ?>" style="color:inherit;font-weight:600;font-size:.92rem;line-height:1.3;display:block;"><?= e($r['title']) ?></a>
                                        <small class="text-muted" style="display:block;margin-top:.2rem;"><?= lucide('calendar-days') ?> <?= e(format_date($r['published_at'] ?: $r['created_at'], 'd M Y')) ?></small>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted mb-0" style="font-size:.9rem;">No posts published yet.</p>
                    <?php endif; ?>
                </div>
            </div>

        </aside>
    </div>
</section>

<!-- ============================== NEWSLETTER CTA ============================== -->
<section class="section section-alt">
    <div class="container">
        <div class="card-3d reveal text-center" style="background:var(--grad-brand);color:#fff;">
            <span class="eyebrow" style="justify-content:center;color:#dbeafe;">Stay Connected</span>
            <h2 class="text-white">Never Miss a Story</h2>
            <p style="color:rgba(255,255,255,.9);max-width:560px;margin-inline:auto;">Subscribe to our newsletter and get the latest field updates, success stories and ways to get involved delivered to your inbox.</p>
            <form data-ajax-form data-endpoint="<?= e(url('forms/newsletter')) ?>" class="mt-4" style="max-width:520px;margin-inline:auto;">
                <?= csrf_field() ?>
                <div class="flex gap-2 flex-wrap justify-center">
                    <input class="form-control" type="email" name="email" placeholder="you@example.com" required aria-label="Email address" style="flex:1;min-width:220px;">
                    <button class="btn btn-white" type="submit">Subscribe</button>
                </div>
            </form>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
