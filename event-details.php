<?php
/** Event detail — /event-details.php?slug=... */
require __DIR__ . '/includes/config.php';

$slug = (string) ($_GET['slug'] ?? '');
$ev = db_one("SELECT * FROM events WHERE slug = ? AND deleted_at IS NULL AND status <> 'draft' LIMIT 1", [$slug]);
if ($ev === null) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}
$start = !empty($ev['starts_at']) ? strtotime((string) $ev['starts_at']) : null;
$end = !empty($ev['ends_at']) ? strtotime((string) $ev['ends_at']) : null;
$page_title = (string) $ev['title'];
$meta_description = (string) ($ev['summary'] ?? '');
require __DIR__ . '/includes/header.php';
?>
<article class="section">
  <div class="container-site">
    <nav class="breadcrumbs mb-6">
      <a href="<?= e(url('index.php')) ?>" class="hover:text-brand-600">Home</a><span class="breadcrumb-sep">/</span>
      <a href="<?= e(url('events.php')) ?>" class="hover:text-brand-600">Events</a><span class="breadcrumb-sep">/</span>
      <span class="text-content"><?= e($ev['title']) ?></span>
    </nav>
    <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
      <div class="lg:col-span-2">
        <?php if (!empty($ev['image'])): ?><img src="<?= e(asset($ev['image'])) ?>" alt="" class="mb-6 aspect-[16/9] w-full rounded-panel object-cover"><?php endif; ?>
        <h1 class="font-display text-3xl font-bold tracking-tight text-content sm:text-4xl"><?= e($ev['title']) ?></h1>
        <?php if (!empty($ev['summary'])): ?><p class="mt-3 text-lg text-content-muted"><?= e($ev['summary']) ?></p><?php endif; ?>
        <?php if (!empty($ev['description'])): ?><div class="prose-content mt-6"><?= $ev['description'] ?></div><?php endif; ?>
      </div>
      <aside class="lg:col-span-1">
        <div class="sticky top-20 rounded-panel border border-edge bg-surface p-6 shadow-card">
          <h2 class="font-display text-base font-semibold text-content">Event details</h2>
          <dl class="mt-4 space-y-4 text-sm">
            <?php if ($start !== null): ?>
              <div class="flex gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600"><i class="fa-solid fa-calendar-day"></i></span><div><dt class="font-semibold text-content">Date</dt><dd class="text-content-muted"><?= e(date('l, d M Y', $start)) ?></dd></div></div>
              <div class="flex gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600"><i class="fa-solid fa-clock"></i></span><div><dt class="font-semibold text-content">Time</dt><dd class="text-content-muted"><?= e(date('g:i A', $start)) ?><?= $end !== null ? ' – ' . e(date('g:i A', $end)) : '' ?></dd></div></div>
            <?php endif; ?>
            <?php if (!empty($ev['location'])): ?>
              <div class="flex gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600"><i class="fa-solid fa-location-dot"></i></span><div><dt class="font-semibold text-content">Venue</dt><dd class="text-content-muted"><?= e($ev['location']) ?></dd></div></div>
            <?php endif; ?>
          </dl>
          <a href="<?= e(url('contact.php')) ?>" class="mt-6 block rounded-lg bg-brand-600 px-4 py-3 text-center text-sm font-semibold text-on-brand transition hover:bg-brand-700">Register your interest</a>
        </div>
      </aside>
    </div>
  </div>
</article>
<?php require __DIR__ . '/includes/footer.php'; ?>
