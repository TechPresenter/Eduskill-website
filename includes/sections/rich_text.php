<?php defined('ESK') || exit; /** @var array $s */
$heading = (string) ($s['heading'] ?? '');
$body = (string) ($s['body_html'] ?? ''); // already sanitized on save
$align = ($s['align'] ?? 'left') === 'center' ? 'mx-auto text-center' : '';
?>
<section class="section">
  <div class="container-site">
    <div class="max-w-3xl <?= $align ?>" data-aos="fade-up">
      <?php if ($heading !== ''): ?><h2 class="section-heading mb-5"><?= e($heading) ?></h2><?php endif; ?>
      <div class="prose-content"><?= $body ?></div>
    </div>
  </div>
</section>
