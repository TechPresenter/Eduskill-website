<?php defined('ESK') || exit; /** @var array $s */
$heading = (string) ($s['heading'] ?? '');
$sub = (string) ($s['subheading'] ?? '');
$cards = is_array($s['cards'] ?? null) ? $s['cards'] : [];
?>
<section class="section bg-surface">
  <div class="container-site">
    <?php if ($heading !== '' || $sub !== ''): ?>
      <div class="mx-auto mb-12 max-w-2xl text-center" data-aos="fade-up">
        <?php if ($heading !== ''): ?><h2 class="section-heading"><?= e($heading) ?></h2><?php endif; ?>
        <?php if ($sub !== ''): ?><p class="section-subheading mx-auto"><?= e($sub) ?></p><?php endif; ?>
      </div>
    <?php endif; ?>
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <?php foreach ($cards as $i => $card): ?>
        <div class="rounded-card border border-edge bg-surface p-6 shadow-card transition hover:shadow-pop" data-aos="fade-up" data-aos-delay="<?= ($i % 3) * 100 ?>">
          <div class="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><?= icon((string) ($card['icon'] ?? 'programs'), 'h-6 w-6') ?></div>
          <h3 class="font-display text-lg font-semibold text-content"><?= e((string) ($card['title'] ?? '')) ?></h3>
          <p class="mt-2 text-sm leading-relaxed text-content-muted"><?= e((string) ($card['text'] ?? '')) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
