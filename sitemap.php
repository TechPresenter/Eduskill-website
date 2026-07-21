<?php
/** Human-readable HTML sitemap, auto-built from the live content tables. */
require __DIR__ . '/includes/config.php';
$page_title = 'Sitemap';

$main = [
    'Home' => 'index.php', 'About Us' => 'about.php', 'Mission & Vision' => 'mission.php',
    'Our Team' => 'team.php', 'Programmes' => 'programs.php', 'Schemes & Initiatives' => 'schemes.php',
    'Campaigns' => 'campaigns.php', 'Events' => 'events.php', 'Photo Gallery' => 'gallery.php',
    'Video Gallery' => 'videos.php', 'Blog' => 'blog.php', 'News & Media' => 'news.php',
    'Testimonials' => 'testimonials.php', 'FAQ' => 'faq.php', 'Contact' => 'contact.php',
];
$involved = [
    'Donate' => 'donate.php', 'Volunteer' => 'volunteer.php', 'Become a Partner' => 'partners.php',
    'Careers' => 'careers.php', 'Scholarships' => 'scholarships.php', 'Internships' => 'internships.php',
    'Verify Certificate' => 'verify-certificate.php', 'Download Center' => 'downloads.php',
];
$legal = [
    'Privacy Policy' => 'privacy-policy.php', 'Terms & Conditions' => 'terms.php',
    'Refund Policy' => 'refund-policy.php', 'Disclaimer' => 'disclaimer.php', 'Cookie Policy' => 'cookie-policy.php',
];
$grab = static function (string $sql): array { try { return db_all($sql); } catch (Throwable) { return []; } };
$campaigns = $grab("SELECT title, slug FROM campaigns WHERE deleted_at IS NULL AND status IN ('active','completed','closed') ORDER BY id DESC LIMIT 50");
$posts = $grab("SELECT title, slug FROM posts WHERE deleted_at IS NULL AND status='published' ORDER BY COALESCE(published_at,created_at) DESC LIMIT 50");
$events = $grab("SELECT title, slug FROM events WHERE deleted_at IS NULL AND status <> 'draft' ORDER BY starts_at DESC LIMIT 50");

$col = static function (string $title, array $links): void { ?>
  <div>
    <h2 class="footer-heading text-content"><?= e($title) ?></h2>
    <ul class="space-y-1.5">
      <?php foreach ($links as $label => $file): ?>
        <li><a href="<?= e(url($file)) ?>" class="text-sm text-content-muted hover:text-brand-600"><?= e($label) ?></a></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php };
require __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="container-site">
    <div class="mx-auto mb-12 max-w-2xl text-center" data-aos="fade-up">
      <span class="eyebrow">Find your way</span>
      <h1 class="section-heading">Sitemap</h1>
      <p class="section-subheading mx-auto">Every page on the Eduskill India Foundation website, in one place.</p>
    </div>
    <div class="grid grid-cols-1 gap-10 sm:grid-cols-2 lg:grid-cols-3">
      <?php $col('Explore', $main); ?>
      <?php $col('Get Involved', $involved); ?>
      <?php $col('Legal', $legal); ?>

      <?php if ($campaigns !== []): ?>
      <div>
        <h2 class="footer-heading text-content">Campaigns</h2>
        <ul class="space-y-1.5">
          <?php foreach ($campaigns as $c): ?><li><a href="<?= e(url('campaign-details.php?slug=' . urlencode((string) $c['slug']))) ?>" class="text-sm text-content-muted hover:text-brand-600"><?= e($c['title']) ?></a></li><?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <?php if ($events !== []): ?>
      <div>
        <h2 class="footer-heading text-content">Events</h2>
        <ul class="space-y-1.5">
          <?php foreach ($events as $ev): ?><li><a href="<?= e(url('event-details.php?slug=' . urlencode((string) $ev['slug']))) ?>" class="text-sm text-content-muted hover:text-brand-600"><?= e($ev['title']) ?></a></li><?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <?php if ($posts !== []): ?>
      <div>
        <h2 class="footer-heading text-content">Latest posts</h2>
        <ul class="space-y-1.5">
          <?php foreach ($posts as $p): ?><li><a href="<?= e(url('blog-details.php?slug=' . urlencode((string) $p['slug']))) ?>" class="text-sm text-content-muted hover:text-brand-600"><?= e($p['title']) ?></a></li><?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
