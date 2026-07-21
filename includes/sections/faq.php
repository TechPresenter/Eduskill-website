<?php defined('ESK') || exit; /** @var array $s */
$heading = (string) ($s['heading'] ?? 'Frequently asked questions');
$items = is_array($s['items'] ?? null) ? $s['items'] : [];
?>
<section class="section bg-surface">
  <div class="container-site">
    <div class="mx-auto max-w-3xl">
      <h2 class="section-heading mb-8 text-center"><?= e($heading) ?></h2>
      <div class="divide-y divide-edge overflow-hidden rounded-card border border-edge">
        <?php foreach ($items as $item): ?>
          <details class="group bg-surface">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 text-sm font-medium text-content hover:bg-surface-sunken">
              <span><?= e((string) ($item['q'] ?? '')) ?></span>
              <svg class="h-5 w-5 shrink-0 text-content-subtle transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 9l6 6 6-6"/></svg>
            </summary>
            <div class="px-5 pb-5 text-sm leading-relaxed text-content-muted"><?= e((string) ($item['a'] ?? '')) ?></div>
          </details>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
