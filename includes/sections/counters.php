<?php defined('ESK') || exit; /** @var array $s */
$heading = (string) ($s['heading'] ?? '');
$items = is_array($s['items'] ?? null) ? $s['items'] : [];
?>
<section class="section-tight bg-surface-sunken">
  <div class="container-site">
    <?php if ($heading !== ''): ?><h2 class="section-heading mb-10 text-center"><?= e($heading) ?></h2><?php endif; ?>
    <div class="grid grid-cols-2 gap-6 lg:grid-cols-4">
      <?php foreach ($items as $item): ?>
        <div class="text-center" data-aos="zoom-in">
          <div class="counter-value"><span data-count="<?= (int) ($item['value'] ?? 0) ?>">0</span><?= e((string) ($item['suffix'] ?? '')) ?></div>
          <div class="counter-label"><?= e((string) ($item['label'] ?? '')) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
