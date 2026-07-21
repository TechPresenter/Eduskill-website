<?php
/** Programmes listing. */
require __DIR__ . '/includes/config.php';
$page_title = 'Our Programmes';
$programs = db_all("SELECT * FROM programs WHERE deleted_at IS NULL AND is_active = 1 ORDER BY position ASC, id DESC");
require __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="container-site">
    <div class="mx-auto mb-12 max-w-2xl text-center" data-aos="fade-up">
      <span class="eyebrow">What we do</span>
      <h1 class="section-heading">Our programmes</h1>
      <p class="section-subheading mx-auto">Education, skilling, and scholarships designed with local partners and measured by real outcomes.</p>
    </div>
    <?php if ($programs === []): ?>
      <div class="rounded-card border border-dashed border-edge p-12 text-center text-content-muted">Programmes will be listed here soon.</div>
    <?php else: ?>
      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($programs as $i => $p): ?>
          <div class="rounded-card border border-edge bg-surface p-6 shadow-card transition hover:shadow-pop" data-aos="fade-up" data-aos-delay="<?= ($i % 3) * 100 ?>">
            <div class="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><?= icon((string) ($p['icon'] ?: 'programs'), 'h-6 w-6') ?></div>
            <span class="mb-1 inline-block text-2xs font-semibold uppercase tracking-wide text-content-subtle"><?= e($p['kind']) ?></span>
            <h3 class="font-display text-lg font-semibold text-content"><?= e($p['title']) ?></h3>
            <?php if (!empty($p['summary'])): ?><p class="mt-2 text-sm leading-relaxed text-content-muted"><?= e($p['summary']) ?></p><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
