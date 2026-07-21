<?php
defined('ESK') || exit('No direct access.');
/**
 * PREMIUM navbar — glassmorphism sticky header + animated mega-menu + off-canvas mobile drawer.
 * Drop-in replacement for navbar.php. Keeps id="esk-header" so app.js's scroll-shadow still works;
 * the mobile drawer uses data-pm-* hooks handled by premium.js (never the old #esk-burger wiring).
 */
$siteName = (string) setting('site_name', APP_NAME);

$about = [
    ['About Us', 'about.php', 'fa-circle-info'],
    ['Mission & Vision', 'mission.php', 'fa-bullseye'],
    ['Our Team', 'team.php', 'fa-users'],
];
$programmes = [
    ['Our Programmes', 'programs.php', 'fa-graduation-cap', 'Education, skilling & scholarships'],
    ['Schemes & Initiatives', 'schemes.php', 'fa-landmark', 'Government schemes & drives'],
    ['Scholarships', 'scholarships.php', 'fa-award', 'Financial support for learners'],
    ['Internships', 'internships.php', 'fa-user-graduate', 'Hands-on experience'],
    ['Campaigns', 'campaigns.php', 'fa-bullhorn', 'Active fundraising causes'],
    ['Events', 'events.php', 'fa-calendar-day', 'Workshops, camps & drives'],
];
$involved = [
    ['Volunteer', 'volunteer.php', 'fa-hands-holding-circle'],
    ['Become a Partner', 'partners.php', 'fa-handshake'],
    ['Careers', 'careers.php', 'fa-briefcase'],
    ['Verify Certificate', 'verify-certificate.php', 'fa-certificate'],
];
$media = [
    ['Photo Gallery', 'gallery.php', 'fa-images'],
    ['Video Gallery', 'videos.php', 'fa-play'],
    ['Testimonials', 'testimonials.php', 'fa-quote-right'],
    ['Blog', 'blog.php', 'fa-pen-nib'],
    ['News & Media', 'news.php', 'fa-newspaper'],
    ['Downloads', 'downloads.php', 'fa-download'],
];
$drop = static function (string $label, array $items): void { ?>
  <div class="pm-menu">
    <button type="button" class="nav-link inline-flex items-center gap-1.5"><?= e($label) ?> <i class="fa-solid fa-chevron-down pm-caret text-xs"></i></button>
    <div class="pm-panel">
      <?php foreach ($items as $it): ?>
        <a href="<?= e(url($it[1])) ?>" class="pm-menu-item"><i class="fa-solid <?= e($it[2]) ?> w-4 text-center"></i><span class="text-sm font-semibold text-content"><?= e($it[0]) ?></span></a>
      <?php endforeach; ?>
    </div>
  </div>
<?php };
?>
<header id="esk-header" class="site-header">
  <div class="container-site">
    <div class="flex h-16 items-center justify-between gap-4">
      <a href="<?= e(url('index.php')) ?>" class="flex items-center gap-2.5">
        <span class="grid h-9 w-9 place-items-center rounded-xl bg-brand-600 font-display text-base font-extrabold text-on-brand">E</span>
        <span class="font-display text-base font-bold tracking-tight text-content sm:text-lg"><?= e($siteName) ?></span>
      </a>

      <nav class="hidden items-center gap-0.5 lg:flex" aria-label="Primary">
        <a href="<?= e(url('index.php')) ?>" class="nav-link">Home</a>
        <?php $drop('About', $about); ?>

        <div class="pm-menu">
          <button type="button" class="nav-link inline-flex items-center gap-1.5">Programmes <i class="fa-solid fa-chevron-down pm-caret text-xs"></i></button>
          <div class="pm-panel pm-panel--wide">
            <div class="grid grid-cols-2 gap-1">
              <?php foreach ($programmes as $p): ?>
                <a href="<?= e(url($p[1])) ?>" class="pm-menu-item">
                  <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600"><i class="fa-solid <?= e($p[2]) ?>"></i></span>
                  <span><span class="block text-sm font-semibold text-content"><?= e($p[0]) ?></span><span class="block text-xs text-content-muted"><?= e($p[3]) ?></span></span>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <?php $drop('Get Involved', $involved); ?>

        <div class="pm-menu">
          <button type="button" class="nav-link inline-flex items-center gap-1.5">Media <i class="fa-solid fa-chevron-down pm-caret text-xs"></i></button>
          <div class="pm-panel pm-panel--right">
            <?php foreach ($media as $it): ?>
              <a href="<?= e(url($it[1])) ?>" class="pm-menu-item"><i class="fa-solid <?= e($it[2]) ?> w-4 text-center"></i><span class="text-sm font-semibold text-content"><?= e($it[0]) ?></span></a>
            <?php endforeach; ?>
          </div>
        </div>

        <a href="<?= e(url('contact.php')) ?>" class="nav-link">Contact</a>
      </nav>

      <div class="flex items-center gap-2">
        <a href="<?= e(url('donate.php')) ?>" class="hidden rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-on-brand transition hover:bg-brand-700 sm:inline-block"><i class="fa-solid fa-heart mr-1"></i>Donate</a>
        <button type="button" data-pm-nav-toggle aria-expanded="false" aria-label="Open menu" class="grid h-10 w-10 place-items-center rounded-lg text-content hover:bg-surface-sunken lg:hidden"><i class="fa-solid fa-bars text-lg"></i></button>
      </div>
    </div>
  </div>
</header>

<!-- Mobile off-canvas drawer -->
<div data-pm-nav class="pm-drawer lg:hidden">
  <div class="pm-drawer-panel">
    <div class="flex h-16 items-center justify-between border-b border-edge px-5">
      <span class="font-display text-base font-bold text-content"><?= e($siteName) ?></span>
      <button type="button" data-pm-nav-close aria-label="Close menu" class="grid h-9 w-9 place-items-center rounded-lg text-content hover:bg-surface-sunken"><i class="fa-solid fa-xmark text-lg"></i></button>
    </div>
    <nav class="space-y-1 p-4">
      <a href="<?= e(url('index.php')) ?>" class="block rounded-lg px-3 py-2.5 text-sm font-semibold text-content hover:bg-surface-sunken">Home</a>
      <?php foreach ([['About', $about], ['Programmes', $programmes], ['Get Involved', $involved], ['Media', $media]] as $group): ?>
        <div>
          <button type="button" data-pm-acc-toggle aria-expanded="false" class="pm-acc-toggle flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-sm font-semibold text-content hover:bg-surface-sunken">
            <span><?= e($group[0]) ?></span><i class="fa-solid fa-chevron-down pm-caret text-xs"></i>
          </button>
          <div class="pm-acc-panel">
            <div class="py-1 pl-3">
              <?php foreach ($group[1] as $it): ?>
                <a href="<?= e(url($it[1])) ?>" class="block rounded-lg px-3 py-2 text-sm text-content-muted hover:bg-surface-sunken hover:text-content"><?= e($it[0]) ?></a>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      <a href="<?= e(url('contact.php')) ?>" class="block rounded-lg px-3 py-2.5 text-sm font-semibold text-content hover:bg-surface-sunken">Contact</a>
      <a href="<?= e(url('donate.php')) ?>" class="mt-3 flex items-center justify-center gap-2 rounded-lg bg-brand-600 px-3 py-3 text-sm font-semibold text-on-brand"><i class="fa-solid fa-heart"></i> Donate</a>
    </nav>
  </div>
</div>
