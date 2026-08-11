<?php
/**
 * =============================================================================
 *  Courses — public course catalogue (LMS)
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

$siteName = get_setting('site_name', SITE_NAME);
$search   = trim((string) get('q', ''));
$category = (string) get('category', '');

$where  = "status = 'published'";
$params = [];
if ($search !== '')   { $where .= " AND CONCAT_WS(' ', title, short_description, category) LIKE :q"; $params[':q'] = '%' . $search . '%'; }
if ($category !== '') { $where .= ' AND category = :c'; $params[':c'] = $category; }

$courses = db_all("SELECT * FROM courses WHERE $where ORDER BY is_featured DESC, id DESC", $params);
$categories = db_all("SELECT DISTINCT category FROM courses WHERE status='published' AND category IS NOT NULL AND category<>'' ORDER BY category");

seo_set([
    'title'       => 'Courses',
    'description' => 'Explore learning courses offered by ' . $siteName . '.',
    'page_key'    => 'courses',
]);
$page_hero = [
    'title'      => 'Courses',
    'subtitle'   => 'Learn new skills with our free and community courses.',
    'breadcrumb' => [['label' => 'Courses']],
];
include __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <form method="get" action="<?= e(url('courses')) ?>" class="flex flex-wrap gap-2 mb-4" style="justify-content:center;">
            <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search courses…" style="max-width:280px;">
            <?php if ($categories): ?>
                <select class="form-select" name="category" onchange="this.form.submit()" style="max-width:200px;">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $c): ?><option value="<?= e($c['category']) ?>" <?= $category === $c['category'] ? 'selected' : '' ?>><?= e($c['category']) ?></option><?php endforeach; ?>
                </select>
            <?php endif; ?>
            <button class="btn btn-primary" type="submit" aria-label="Search courses"><?= lucide('search') ?></button>
        </form>

        <?php if ($courses): ?>
            <div class="grid grid-3 gap-3">
                <?php foreach ($courses as $c): ?>
                    <a class="glass-card reveal" href="<?= e(url('course?slug=' . urlencode($c['slug']))) ?>" style="color:inherit;display:flex;flex-direction:column;overflow:hidden;padding:0;">
                        <div style="aspect-ratio:16/9;background:#eef2f7;overflow:hidden;">
                            <img src="<?= e(image_url($c['thumbnail'] ?? null, 'blog')) ?>" alt="" loading="lazy" width="600" height="338" style="width:100%;height:100%;object-fit:cover;">
                        </div>
                        <div style="padding:1rem;flex:1;">
                            <?php if (!empty($c['category'])): ?><span class="badge badge-brand"><?= e($c['category']) ?></span><?php endif; ?>
                            <h3 class="card-title mb-1" style="margin-top:.5rem;"><?= e($c['title']) ?></h3>
                            <p class="card-text text-muted" style="font-size:.9rem;"><?= e(excerpt($c['short_description'] ?: $c['description'], 20)) ?></p>
                            <div class="flex items-center justify-between mt-2" style="gap:.6rem;flex-wrap:wrap;">
                                <span class="text-muted" style="font-size:.85rem;"><?= lucide('signal') ?> <?= e(ucfirst($c['level'])) ?></span>
                                <?php if (!empty($c['duration'])): ?>
                                    <span class="text-muted" style="font-size:.85rem;"><?= lucide('clock') ?> <?= e($c['duration']) ?></span>
                                <?php endif; ?>
                                <strong style="color:var(--brand-600,#0B4E3D);"><?= (float) $c['price'] > 0 ? e(money($c['price'], '₹', 0)) : 'Free' ?></strong>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="glass-card reveal" style="text-align:center;padding:2.5rem;"><p class="text-muted mb-0">No courses found. Please check back soon.</p></div>
        <?php endif; ?>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
