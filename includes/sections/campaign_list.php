<?php defined('ESK') || exit; /** @var array $s */
$heading = (string) ($s['heading'] ?? 'Campaigns you can support');
$limit = (int) ($s['limit'] ?? 3);
$limit = $limit > 0 ? $limit : 3;
$campaigns = db_all(
    "SELECT * FROM campaigns WHERE deleted_at IS NULL AND status = 'active'
     ORDER BY is_featured DESC, id DESC LIMIT " . $limit
);
?>
<section class="section bg-surface-sunken">
  <div class="container-site">
    <?php if ($heading !== ''): ?><h2 class="section-heading mb-10 text-center" data-aos="fade-up"><?= e($heading) ?></h2><?php endif; ?>
    <?php if ($campaigns === []): ?>
      <p class="text-center text-content-muted">No active campaigns right now — please check back soon.</p>
    <?php else: ?>
      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($campaigns as $i => $c): ?>
          <div data-aos="fade-up" data-aos-delay="<?= ($i % 3) * 100 ?>"><?= campaign_card($c) ?></div>
        <?php endforeach; ?>
      </div>
      <div class="mt-10 text-center"><a href="<?= e(url('campaigns.php')) ?>" class="inline-block rounded-lg border border-edge bg-surface px-6 py-3 text-sm font-semibold text-content transition hover:bg-surface-sunken">View all campaigns</a></div>
    <?php endif; ?>
  </div>
</section>
