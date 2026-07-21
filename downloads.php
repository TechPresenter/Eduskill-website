<?php
/** Download Center — brochures, forms and reports, grouped by category. */
require __DIR__ . '/includes/config.php';
$page_title = 'Download Center';
$rows = db_all("SELECT * FROM downloads WHERE deleted_at IS NULL AND is_active = 1 ORDER BY category ASC, title ASC");
$groups = [];
foreach ($rows as $r) {
    $groups[(string) ($r['category'] ?: 'General')][] = $r;
}
$fileHref = static function (string $path): string {
    if (preg_match('#^https?://#', $path)) return $path;
    if (str_starts_with($path, '/')) return url(ltrim($path, '/'));
    return asset($path);
};
$fileSize = static function ($bytes): string {
    $b = (int) $bytes;
    if ($b <= 0) return '';
    $u = ['B', 'KB', 'MB', 'GB'];
    $i = (int) floor(log($b, 1024));
    $i = max(0, min($i, count($u) - 1));
    return round($b / (1024 ** $i), 1) . ' ' . $u[$i];
};
require __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="container-site">
    <div class="mx-auto mb-12 max-w-2xl text-center" data-aos="fade-up">
      <span class="eyebrow">Resources</span>
      <h1 class="section-heading">Download center</h1>
      <p class="section-subheading mx-auto">Brochures, application forms, annual reports and other documents.</p>
    </div>
    <?php if ($groups === []): ?>
      <div class="rounded-card border border-dashed border-edge p-12 text-center text-content-muted">Documents will be available for download here soon.</div>
    <?php else: ?>
      <div class="mx-auto max-w-3xl space-y-10">
        <?php foreach ($groups as $cat => $items): ?>
          <div data-aos="fade-up">
            <h2 class="mb-4 font-display text-lg font-bold text-content"><?= e($cat) ?></h2>
            <div class="divide-y divide-edge overflow-hidden rounded-card border border-edge">
              <?php foreach ($items as $d): ?>
                <a href="<?= e($fileHref((string) $d['file_path'])) ?>" target="_blank" rel="noopener" download class="flex items-center gap-4 bg-surface px-5 py-4 transition hover:bg-surface-sunken">
                  <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600"><i class="fa-solid fa-file-arrow-down text-lg"></i></span>
                  <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-semibold text-content"><?= e($d['title']) ?></span>
                    <?php $sz = $fileSize($d['file_size'] ?? 0); if ($sz !== ''): ?><span class="text-xs text-content-muted"><?= e($sz) ?></span><?php endif; ?>
                  </span>
                  <span class="shrink-0 text-content-subtle"><i class="fa-solid fa-download"></i></span>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
