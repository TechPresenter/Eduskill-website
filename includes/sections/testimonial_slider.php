<?php defined('ESK') || exit; /** @var array $s */
$heading = (string) ($s['heading'] ?? 'What people say');
$limit = max(1, (int) ($s['limit'] ?? 3));
$items = db_all(
    'SELECT author_name, author_role, quote, rating, photo FROM testimonials
     WHERE deleted_at IS NULL AND is_active = 1 ORDER BY position ASC, id ASC LIMIT ' . $limit
);
if ($items === []) {
    return;
}
?>
<section class="section bg-surface-sunken">
  <div class="container-site">
    <?php if ($heading !== ''): ?><h2 class="section-heading mb-10 text-center" data-aos="fade-up"><?= e($heading) ?></h2><?php endif; ?>
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <?php foreach ($items as $t): ?>
        <figure class="flex flex-col rounded-card border border-edge bg-surface p-6 shadow-card" data-aos="fade-up">
          <?php $rating = (int) ($t['rating'] ?? 0); ?>
          <?php if ($rating > 0): ?>
            <div class="mb-3 flex gap-0.5 text-warning-500" aria-label="<?= $rating ?> out of 5">
              <?php for ($i = 0; $i < 5; $i++): ?><svg class="h-4 w-4" viewBox="0 0 20 20" fill="<?= $i < $rating ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="1.5"><path d="M10 1.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L10 15l-5.2 2.6 1-5.8L1.5 7.7l5.9-.9z"/></svg><?php endfor; ?>
            </div>
          <?php endif; ?>
          <blockquote class="flex-1 text-sm leading-relaxed text-content">“<?= e($t['quote']) ?>”</blockquote>
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
  </div>
</section>
