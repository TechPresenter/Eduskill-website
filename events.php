<?php
/** Events listing. */
require __DIR__ . '/includes/config.php';
$page_title = 'Events';
$events = db_all("SELECT * FROM events WHERE deleted_at IS NULL AND status <> 'draft' ORDER BY starts_at DESC");
require __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="container-site">
    <div class="mx-auto mb-12 max-w-2xl text-center" data-aos="fade-up">
      <span class="eyebrow">What's happening</span>
      <h1 class="section-heading">Events</h1>
      <p class="section-subheading mx-auto">Workshops, camps, drives and community gatherings — join us in person.</p>
    </div>
    <?php if ($events === []): ?>
      <div class="rounded-card border border-dashed border-edge p-12 text-center text-content-muted">No events scheduled right now. Please check back soon.</div>
    <?php else: ?>
      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($events as $i => $ev):
          $start = !empty($ev['starts_at']) ? strtotime((string) $ev['starts_at']) : null;
          $upcoming = $start !== null && $start >= strtotime('today');
        ?>
          <a href="<?= e(url('event-details.php?slug=' . urlencode((string) $ev['slug']))) ?>" class="content-card" data-aos="fade-up" data-aos-delay="<?= ($i % 3) * 100 ?>">
            <div class="content-card-media">
              <?php if (!empty($ev['image'])): ?><img src="<?= e(asset($ev['image'])) ?>" alt="" loading="lazy"><?php else: ?><div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-brand-500 to-brand-700 text-on-brand"><?= icon('events', 'h-10 w-10 opacity-70') ?></div><?php endif; ?>
              <?php if ($start !== null): ?>
                <span class="absolute left-3 top-3 rounded-lg bg-surface/90 px-3 py-1.5 text-center backdrop-blur">
                  <span class="block text-lg font-bold leading-none text-content"><?= e(date('d', $start)) ?></span>
                  <span class="block text-2xs font-semibold uppercase text-content-muted"><?= e(date('M', $start)) ?></span>
                </span>
              <?php endif; ?>
              <?php if ($upcoming): ?><span class="absolute right-3 top-3 rounded-full bg-success-500 px-2.5 py-1 text-2xs font-semibold text-white">Upcoming</span><?php endif; ?>
            </div>
            <div class="content-card-body">
              <h3 class="content-card-title"><?= e($ev['title']) ?></h3>
              <?php if (!empty($ev['location'])): ?><p class="text-xs text-content-muted"><i class="fa-solid fa-location-dot mr-1"></i><?= e($ev['location']) ?></p><?php endif; ?>
              <?php if (!empty($ev['summary'])): ?><p class="content-card-excerpt"><?= e($ev['summary']) ?></p><?php endif; ?>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
