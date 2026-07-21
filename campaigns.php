<?php
/** Campaigns listing — live records from the campaigns table. */
require __DIR__ . '/includes/config.php';
$page_title = 'Campaigns';
$campaigns = db_all(
    "SELECT * FROM campaigns WHERE deleted_at IS NULL AND status IN ('active','completed','closed')
     ORDER BY is_featured DESC, id DESC LIMIT 60"
);
require __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="container-site">
    <div class="mx-auto mb-12 max-w-2xl text-center" data-aos="fade-up">
      <span class="eyebrow">Support a cause</span>
      <h1 class="section-heading">Our campaigns</h1>
      <p class="section-subheading mx-auto">Every contribution helps a child stay in school and a young person find work.</p>
    </div>
    <?php if ($campaigns === []): ?>
      <div class="rounded-card border border-dashed border-edge p-12 text-center text-content-muted">
        No active campaigns right now — or <a href="<?= e(url('donate.php')) ?>" class="text-brand-600 hover:underline">make a general donation</a>.
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($campaigns as $i => $c): ?>
          <div data-aos="fade-up" data-aos-delay="<?= ($i % 3) * 100 ?>"><?= campaign_card($c) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
