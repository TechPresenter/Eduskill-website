<?php
/** News & Media — press releases and media coverage (posts in news-type categories). */
require __DIR__ . '/includes/config.php';
$page_title = 'News & Media';
$posts = db_all(
    "SELECT p.*, c.name AS category_name FROM posts p
     JOIN post_categories c ON c.id = p.category_id
     WHERE p.deleted_at IS NULL AND p.status = 'published'
       AND (c.slug IN ('news','press','media','news-media','press-release') OR p.is_breaking = 1)
     ORDER BY COALESCE(p.published_at, p.created_at) DESC LIMIT 30"
);
require __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="container-site">
    <div class="mx-auto mb-12 max-w-2xl text-center" data-aos="fade-up">
      <span class="eyebrow">Press &amp; media</span>
      <h1 class="section-heading">News &amp; Media</h1>
      <p class="section-subheading mx-auto">Press releases, announcements and coverage of our work.</p>
    </div>
    <?php if ($posts === []): ?>
      <div class="rounded-card border border-dashed border-edge p-12 text-center text-content-muted">
        No news yet. Read our latest <a href="<?= e(url('blog.php')) ?>" class="font-medium underline" style="color:rgb(var(--brand-600))">stories &amp; updates</a>.
      </div>
    <?php else: ?>
      <div class="mx-auto max-w-3xl divide-y divide-edge overflow-hidden rounded-card border border-edge">
        <?php foreach ($posts as $post): ?>
          <a href="<?= e(url('blog-details.php?slug=' . urlencode((string) $post['slug']))) ?>" class="flex items-start gap-4 bg-surface p-5 transition hover:bg-surface-sunken" data-aos="fade-up">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600"><i class="fa-regular fa-newspaper text-lg"></i></span>
            <span class="min-w-0 flex-1">
              <span class="text-xs font-medium text-content-muted"><?= e(date('d M Y', strtotime((string) ($post['published_at'] ?: $post['created_at'])))) ?> · <?= e((string) $post['category_name']) ?></span>
              <span class="mt-0.5 block font-display text-base font-semibold text-content"><?= e($post['title']) ?></span>
              <?php if (!empty($post['excerpt'])): ?><span class="mt-1 block text-sm text-content-muted"><?= e($post['excerpt']) ?></span><?php endif; ?>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
