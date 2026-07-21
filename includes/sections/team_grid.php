<?php defined('ESK') || exit; /** @var array $s */
$heading = (string) ($s['heading'] ?? 'Our team');
$limit = max(1, (int) ($s['limit'] ?? 8));
$team = db_all(
    'SELECT name, role_title, photo FROM team_members
     WHERE deleted_at IS NULL AND is_active = 1 ORDER BY position ASC, id ASC LIMIT ' . $limit
);
if ($team === []) {
    return;
}
?>
<section class="section">
  <div class="container-site">
    <?php if ($heading !== ''): ?><h2 class="section-heading mb-10 text-center" data-aos="fade-up"><?= e($heading) ?></h2><?php endif; ?>
    <div class="grid grid-cols-2 gap-8 sm:grid-cols-3 lg:grid-cols-4">
      <?php foreach ($team as $i => $m): ?>
        <div class="text-center" data-aos="fade-up" data-aos-delay="<?= ($i % 4) * 80 ?>">
          <div class="mx-auto h-24 w-24 overflow-hidden rounded-full bg-surface-sunken">
            <?php if (!empty($m['photo'])): ?>
              <img src="<?= e(asset($m['photo'])) ?>" alt="<?= e($m['name']) ?>" class="h-full w-full object-cover" loading="lazy">
            <?php else: ?>
              <div class="grid h-full w-full place-items-center bg-brand-50 font-display text-2xl font-bold text-brand-600"><?= e(mb_strtoupper(mb_substr((string) $m['name'], 0, 1))) ?></div>
            <?php endif; ?>
          </div>
          <h3 class="mt-3 font-display text-base font-semibold text-content"><?= e($m['name']) ?></h3>
          <?php if (!empty($m['role_title'])): ?><p class="text-sm text-content-muted"><?= e($m['role_title']) ?></p><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
