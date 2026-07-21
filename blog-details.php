<?php
/** Blog post detail — /blog-details.php?slug=... */
require __DIR__ . '/includes/config.php';

$slug = (string) ($_GET['slug'] ?? '');
$post = db_one(
    "SELECT p.*, c.name AS category_name, c.slug AS category_slug
     FROM posts p LEFT JOIN post_categories c ON c.id = p.category_id
     WHERE p.slug = ? AND p.deleted_at IS NULL AND p.status = 'published' LIMIT 1",
    [$slug]
);
if ($post === null) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}
// Best-effort view counter; never let it break the page.
try { db_exec("UPDATE posts SET views = views + 1 WHERE id = ?", [(int) $post['id']]); } catch (Throwable) {}

$tags = db_all(
    "SELECT t.name, t.slug FROM post_tags t JOIN post_tag_map m ON m.tag_id = t.id WHERE m.post_id = ? ORDER BY t.name",
    [(int) $post['id']]
);
$date = strtotime((string) ($post['published_at'] ?: $post['created_at']));
$page_title = (string) $post['title'];
$meta_description = (string) ($post['excerpt'] ?? '');
require __DIR__ . '/includes/header.php';
?>
<article class="section">
  <div class="container-site">
    <div class="mx-auto max-w-3xl">
      <nav class="breadcrumbs mb-6">
        <a href="<?= e(url('index.php')) ?>" class="hover:text-brand-600">Home</a><span class="breadcrumb-sep">/</span>
        <a href="<?= e(url('blog.php')) ?>" class="hover:text-brand-600">Blog</a><span class="breadcrumb-sep">/</span>
        <span class="text-content"><?= e($post['title']) ?></span>
      </nav>
      <?php if (!empty($post['category_name'])): ?><span class="eyebrow"><?= e($post['category_name']) ?></span><?php endif; ?>
      <h1 class="font-display text-3xl font-bold tracking-tight text-content sm:text-4xl"><?= e($post['title']) ?></h1>
      <div class="mt-3 flex items-center gap-3 text-sm text-content-muted">
        <span><i class="fa-regular fa-calendar mr-1"></i><?= e(date('d M Y', $date)) ?></span>
        <span><i class="fa-regular fa-eye mr-1"></i><?= (int) $post['views'] ?> views</span>
      </div>
      <?php if (!empty($post['featured_image'])): ?><img src="<?= e(asset($post['featured_image'])) ?>" alt="" class="mt-6 aspect-[16/9] w-full rounded-panel object-cover"><?php endif; ?>
      <?php if (!empty($post['body'])): ?><div class="prose-content mt-8"><?= $post['body'] ?></div><?php endif; ?>
      <?php if ($tags !== []): ?>
        <div class="mt-8 flex flex-wrap gap-2 border-t border-edge pt-6">
          <?php foreach ($tags as $tg): ?><span class="rounded-full bg-surface-sunken px-3 py-1 text-xs font-medium text-content-muted">#<?= e($tg['name']) ?></span><?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="mt-8"><a href="<?= e(url('blog.php')) ?>" class="text-sm font-medium text-brand-600 hover:text-brand-700"><i class="fa-solid fa-arrow-left mr-1"></i> Back to all posts</a></div>
    </div>
  </div>
</article>
<?php require __DIR__ . '/includes/footer.php'; ?>
