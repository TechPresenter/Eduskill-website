<?php
/** Testimonials listing. */
require __DIR__ . '/includes/config.php';
$page_title = 'Testimonials';
$items = db_all("SELECT * FROM testimonials WHERE deleted_at IS NULL AND is_active = 1 ORDER BY position ASC, id ASC");
require __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="container-site">
    <div class="mx-auto mb-12 max-w-2xl text-center" data-aos="fade-up">
      <span class="eyebrow">In their words</span>
      <h1 class="section-heading">Stories from our community</h1>
      <p class="section-subheading mx-auto">The people whose lives our programmes have touched — students, parents, partners and volunteers.</p>
    </div>
    <?php if ($items === []): ?>
      <div class="rounded-card border border-dashed border-edge p-12 text-center text-content-muted">Testimonials will appear here soon.</div>
    <?php else: ?>
      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($items as $i => $t): $rating = (int) ($t['rating'] ?? 0); ?>
          <figure class="flex flex-col rounded-card border border-edge bg-surface p-6 shadow-card" data-aos="fade-up" data-aos-delay="<?= ($i % 3) * 100 ?>">
            <?php if ($rating > 0): ?>
              <div class="mb-3 flex gap-0.5 text-warning-500" aria-label="<?= $rating ?> out of 5">
                <?php for ($k = 0; $k < 5; $k++): ?><i class="fa-<?= $k < $rating ? 'solid' : 'regular' ?> fa-star text-sm"></i><?php endfor; ?>
              </div>
            <?php endif; ?>
            <?php if (!empty($t['video_url'])): ?>
              <a href="<?= e((string) $t['video_url']) ?>" target="_blank" rel="noopener" class="mb-3 inline-flex items-center gap-2 text-sm font-medium text-brand-600 hover:text-brand-700"><i class="fa-solid fa-circle-play"></i> Watch video</a>
            <?php endif; ?>
            <blockquote class="flex-1 text-sm leading-relaxed text-content">&ldquo;<?= e($t['quote']) ?>&rdquo;</blockquote>
            <figcaption class="mt-4 flex items-center gap-3">
              <div class="grid h-10 w-10 place-items-center overflow-hidden rounded-full bg-brand-50 text-sm font-bold text-brand-600">
                <?php if (!empty($t['photo'])): ?><img src="<?= e(asset($t['photo'])) ?>" alt="" class="h-full w-full object-cover" loading="lazy"><?php else: ?><?= e(mb_strtoupper(mb_substr((string) $t['author_name'], 0, 1))) ?><?php endif; ?>
              </div>
              <div>
                <div class="text-sm font-semibold text-content"><?= e($t['author_name']) ?></div>
                <?php if (!empty($t['author_role'])): ?><div class="text-xs text-content-muted"><?= e($t['author_role']) ?></div><?php endif; ?>
              </div>
            </figcaption>
          </figure>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
