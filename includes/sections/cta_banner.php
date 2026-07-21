<?php defined('ESK') || exit; /** @var array $s */
$heading = (string) ($s['heading'] ?? '');
$text = (string) ($s['text'] ?? '');
$cta = (string) ($s['cta_label'] ?? '');
$ctaUrl = (string) ($s['cta_url'] ?? '#');
$link = fn (string $u): string => $u === '' ? '#' : (preg_match('#^https?://#', $u) ? $u : url(ltrim($u, '/')));
?>
<section class="section">
  <div class="container-site">
    <div class="relative overflow-hidden rounded-panel bg-gradient-to-br from-brand-600 to-brand-800 px-6 py-12 text-center sm:px-12 sm:py-16" data-aos="fade-up">
      <?php if ($heading !== ''): ?><h2 class="font-display text-2xl font-bold tracking-tight text-white sm:text-3xl"><?= e($heading) ?></h2><?php endif; ?>
      <?php if ($text !== ''): ?><p class="mx-auto mt-3 max-w-xl text-base leading-relaxed text-white/85"><?= e($text) ?></p><?php endif; ?>
      <?php if ($cta !== ''): ?><a href="<?= e($link($ctaUrl)) ?>" class="mt-8 inline-block rounded-lg bg-white px-6 py-3 text-sm font-semibold text-brand-700 shadow-pop transition hover:bg-white/90"><?= e($cta) ?></a><?php endif; ?>
    </div>
  </div>
</section>
