<?php defined('ESK') || exit; /** @var array $s */
$heading = (string) ($s['heading'] ?? '');
$sub = (string) ($s['subheading'] ?? '');
$img = (string) ($s['image'] ?? '');
$cta = (string) ($s['cta_label'] ?? '');
$ctaUrl = (string) ($s['cta_url'] ?? '#');
$cta2 = (string) ($s['secondary_cta_label'] ?? '');
$cta2Url = (string) ($s['secondary_cta_url'] ?? '#');
$link = fn (string $u): string => $u === '' ? '#' : (preg_match('#^https?://|^/#', $u) ? url(ltrim($u, '/')) : url($u));
?>
<section class="hero">
  <?php if ($img !== ''): ?><img src="<?= e(asset($img)) ?>" alt="" class="absolute inset-0 -z-20 h-full w-full object-cover"><?php endif; ?>
  <div class="hero-overlay"></div>
  <div class="container-site relative py-20 sm:py-28 lg:py-32">
    <div class="max-w-2xl" data-aos="fade-up">
      <?php if ($heading !== ''): ?><h1 class="hero-title text-balance"><?= e($heading) ?></h1><?php endif; ?>
      <?php if ($sub !== ''): ?><p class="hero-lead"><?= e($sub) ?></p><?php endif; ?>
      <?php if ($cta !== '' || $cta2 !== ''): ?>
        <div class="mt-8 flex flex-wrap gap-3">
          <?php if ($cta !== ''): ?><a href="<?= e($link($ctaUrl)) ?>" class="rounded-lg bg-brand-600 px-6 py-3 text-sm font-semibold text-on-brand shadow-pop transition hover:bg-brand-700"><?= e($cta) ?></a><?php endif; ?>
          <?php if ($cta2 !== ''): ?><a href="<?= e($link($cta2Url)) ?>" class="rounded-lg border border-white/30 bg-white/10 px-6 py-3 text-sm font-semibold text-white backdrop-blur transition hover:bg-white/20"><?= e($cta2) ?></a><?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
