<?php
/** Blog listing. */
require __DIR__ . '/includes/config.php';
$page_title = 'Blog & News';
$posts = db_all(
    "SELECT p.*, c.name AS category_name FROM posts p
     LEFT JOIN post_categories c ON c.id = p.category_id
     WHERE p.deleted_at IS NULL AND p.status = 'published'
     ORDER BY COALESCE(p.published_at, p.created_at) DESC LIMIT 30"
);
require __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="container-site">
    <div class="mx-auto mb-12 max-w-2xl text-center" data-aos="fade-up">
      <span class="eyebrow">Stories &amp; updates</span>
      <h1 class="section-heading">Blog &amp; News</h1>
    </div>
    <?php if ($posts === []): ?>
      <div class="rounded-card border border-dashed border-edge p-12 text-center text-content-muted">No posts published yet.</div>
    <?php else: ?>
      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($posts as $i => $post): ?>
          <a href="<?= e(url('blog-details.php?slug=' . urlencode((string) $post['slug']))) ?>" class="content-card group" data-aos="fade-up" data-aos-delay="<?= ($i % 3) * 100 ?>">
            <div class="content-card-media">
              <?php if (!empty($post['featured_image'])): ?><img src="<?= e(asset($post['featured_image'])) ?>" alt="" loading="lazy"><?php else: ?><div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-brand-500 to-brand-700 text-on-brand"><?= icon('blog', 'h-10 w-10 opacity-70') ?></div><?php endif; ?>
            </div>
            <div class="content-card-body">
              <?php if (!empty($post['category_name'])): ?><span class="text-2xs font-semibold uppercase tracking-wide text-brand-600"><?= e($post['category_name']) ?></span><?php endif; ?>
              <h3 class="content-card-title"><?= e($post['title']) ?></h3>
              <?php if (!empty($post['excerpt'])): ?><p class="content-card-excerpt"><?= e($post['excerpt']) ?></p><?php endif; ?>
              <span class="mt-auto pt-3 text-xs text-content-subtle"><?= e(date('d M Y', strtotime((string) ($post['published_at'] ?: $post['created_at'])))) ?></span>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
