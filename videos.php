<?php
/** Video gallery — YouTube/Vimeo links from video galleries. */
require __DIR__ . '/includes/config.php';
$page_title = 'Video Gallery';
$videos = db_all(
    "SELECT gi.video_url, gi.image, gi.caption, g.title AS gallery_title
     FROM gallery_items gi JOIN galleries g ON g.id = gi.gallery_id
     WHERE g.deleted_at IS NULL AND g.is_active = 1
       AND gi.video_url IS NOT NULL AND gi.video_url <> ''
     ORDER BY g.position ASC, gi.position ASC, gi.id ASC"
);
/** Best-effort YouTube thumbnail from a watch/short/embed URL. */
$ytThumb = static function (string $url): ?string {
    if (preg_match('#(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})#', $url, $m)) {
        return 'https://img.youtube.com/vi/' . $m[1] . '/hqdefault.jpg';
    }
    return null;
};
require __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="container-site">
    <div class="mx-auto mb-12 max-w-2xl text-center" data-aos="fade-up">
      <span class="eyebrow">Watch</span>
      <h1 class="section-heading">Video gallery</h1>
      <p class="section-subheading mx-auto">Stories, highlights and impact from the field.</p>
    </div>
    <?php if ($videos === []): ?>
      <div class="rounded-card border border-dashed border-edge p-12 text-center text-content-muted">Videos will be added here soon.</div>
    <?php else: ?>
      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($videos as $i => $v): $thumb = !empty($v['image']) ? asset($v['image']) : $ytThumb((string) $v['video_url']); ?>
          <a href="<?= e((string) $v['video_url']) ?>" target="_blank" rel="noopener" class="content-card" data-aos="fade-up" data-aos-delay="<?= ($i % 3) * 100 ?>">
            <div class="content-card-media">
              <?php if ($thumb !== null): ?><img src="<?= e($thumb) ?>" alt="" loading="lazy"><?php else: ?><div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-brand-500 to-brand-700"></div><?php endif; ?>
              <span class="absolute inset-0 grid place-items-center"><span class="grid h-14 w-14 place-items-center rounded-full bg-white/90 text-brand-700 shadow-pop transition group-hover:scale-110"><i class="fa-solid fa-play text-xl"></i></span></span>
            </div>
            <?php if (!empty($v['caption'])): ?><div class="content-card-body"><h3 class="content-card-title text-base"><?= e($v['caption']) ?></h3></div><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
