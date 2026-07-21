<?php
/** Campaign detail — /campaign-details.php?slug=... */
require __DIR__ . '/includes/config.php';

$slug = (string) ($_GET['slug'] ?? '');
$c = db_one(
    "SELECT * FROM campaigns WHERE slug = ? AND deleted_at IS NULL AND status IN ('active','completed','closed') LIMIT 1",
    [$slug]
);
if ($c === null) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}
$pct = campaign_progress($c);
$days = campaign_days_left($c);
$page_title = (string) $c['title'];
$meta_description = (string) ($c['summary'] ?? '');
require __DIR__ . '/includes/header.php';
?>
<article class="section">
  <div class="container-site">
    <nav class="breadcrumbs mb-6">
      <a href="<?= e(url('index.php')) ?>" class="hover:text-brand-600">Home</a><span class="breadcrumb-sep">/</span>
      <a href="<?= e(url('campaigns.php')) ?>" class="hover:text-brand-600">Campaigns</a><span class="breadcrumb-sep">/</span>
      <span class="text-content"><?= e($c['title']) ?></span>
    </nav>
    <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
      <div class="lg:col-span-2">
        <?php if (!empty($c['image'])): ?><img src="<?= e(asset($c['image'])) ?>" alt="" class="mb-6 aspect-[16/9] w-full rounded-panel object-cover"><?php endif; ?>
        <?php if (!empty($c['category'])): ?><span class="eyebrow"><?= e($c['category']) ?></span><?php endif; ?>
        <h1 class="font-display text-3xl font-bold tracking-tight text-content sm:text-4xl"><?= e($c['title']) ?></h1>
        <?php if (!empty($c['summary'])): ?><p class="mt-3 text-lg text-content-muted"><?= e($c['summary']) ?></p><?php endif; ?>
        <?php if (!empty($c['description'])): ?><div class="prose-content mt-6"><?= $c['description'] ?></div><?php endif; ?>
      </div>
      <aside class="lg:col-span-1">
        <div class="sticky top-20 rounded-panel border border-edge bg-surface p-6 shadow-card">
          <div class="flex items-baseline justify-between">
            <span class="font-display text-2xl font-bold text-content">₹<?= e(inr((int) $c['raised_amount'])) ?></span>
            <span class="text-sm text-content-muted"><?= $pct ?>%</span>
          </div>
          <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-surface-sunken"><div class="h-full rounded-full bg-brand-600" style="width: <?= $pct ?>%"></div></div>
          <p class="mt-2 text-sm text-content-muted">raised of ₹<?= e(inr((int) $c['goal_amount'])) ?> goal</p>
          <div class="mt-5 grid grid-cols-2 gap-3 text-center">
            <div class="rounded-lg bg-surface-sunken p-3"><div class="font-display text-lg font-bold text-content"><?= e(inr((int) $c['donor_count'] * 100)) ?></div><div class="text-xs text-content-muted">donors</div></div>
            <div class="rounded-lg bg-surface-sunken p-3"><div class="font-display text-lg font-bold text-content"><?= $days !== null ? $days : '—' ?></div><div class="text-xs text-content-muted">days left</div></div>
          </div>
          <a href="<?= e(url('donate.php')) ?>" class="mt-5 block rounded-lg bg-brand-600 px-4 py-3 text-center text-sm font-semibold text-on-brand transition hover:bg-brand-700">Donate to this campaign</a>
          <p class="mt-3 text-center text-2xs text-content-subtle">Donations are eligible for tax deduction under Section 80G.</p>
        </div>
      </aside>
    </div>
  </div>
</article>
<?php require __DIR__ . '/includes/footer.php'; ?>
