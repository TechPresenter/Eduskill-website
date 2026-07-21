<?php
/**
 * Home page — institute-style layout (navy + orange), inspired by ayanshinstitute.com but adapted to
 * Eduskill's NGO content. The LAYOUT is fixed; all CONTENT is dynamic: hero + stats from settings,
 * programmes / campaigns / testimonials / blog from their tables. Every block links to its page.
 */
require __DIR__ . '/includes/config.php';

$heroEyebrow = (string) setting('hero_eyebrow', APP_NAME);
$heroTitle = (string) setting('hero_title', 'Education & skills that change lives');
$heroSub = (string) setting('hero_subtitle', 'Quality learning, vocational training, and scholarships for those who need them most.');
$stats = [
    ['n' => (int) setting('stat_students', 12500), 'suffix' => '+', 'label' => 'Students supported'],
    ['n' => (int) setting('stat_schools', 85), 'suffix' => '', 'label' => 'Partner schools'],
    ['n' => (int) setting('stat_volunteers', 340), 'suffix' => '+', 'label' => 'Active volunteers'],
    ['n' => (int) setting('stat_districts', 27), 'suffix' => '', 'label' => 'Districts reached'],
];
$programs = db_all("SELECT * FROM programs WHERE deleted_at IS NULL AND is_active = 1 ORDER BY position ASC, id DESC LIMIT 6");
$campaigns = db_all("SELECT * FROM campaigns WHERE deleted_at IS NULL AND status = 'active' ORDER BY is_featured DESC, id DESC LIMIT 3");
$testimonials = db_all("SELECT * FROM testimonials WHERE deleted_at IS NULL AND is_active = 1 ORDER BY position ASC, id DESC LIMIT 3");
$posts = db_all("SELECT p.*, c.name AS category_name FROM posts p LEFT JOIN post_categories c ON c.id = p.category_id WHERE p.deleted_at IS NULL AND p.status = 'published' ORDER BY COALESCE(p.published_at, p.created_at) DESC LIMIT 3");

require __DIR__ . '/includes/header.php';
?>

<!-- ============================ HERO ============================ -->
<section class="relative isolate overflow-hidden bg-brand-900">
  <div class="absolute inset-0 -z-10 bg-gradient-to-br from-brand-900 via-brand-800 to-brand-950"></div>
  <div class="absolute inset-0 -z-10 opacity-20" style="background-image:radial-gradient(circle at 20% 20%, rgb(var(--accent-500)) 0, transparent 40%),radial-gradient(circle at 80% 60%, rgb(var(--brand-400)) 0, transparent 45%)"></div>
  <div class="container-site relative grid items-center gap-12 py-16 lg:grid-cols-2 lg:py-24">
    <div data-aos="fade-up">
      <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-white ring-1 ring-inset ring-white/20"><span class="h-1.5 w-1.5 rounded-full bg-accent-400"></span> <?= e($heroEyebrow) ?></span>
      <h1 class="mt-5 font-display text-4xl font-extrabold leading-[1.1] tracking-tight text-white sm:text-5xl lg:text-6xl"><?= e($heroTitle) ?></h1>
      <p class="mt-5 max-w-xl text-lg leading-relaxed text-white/80"><?= e($heroSub) ?></p>
      <div class="mt-8 flex flex-wrap gap-3">
        <a href="<?= e(url('donate.php')) ?>" class="rounded-xl bg-accent-500 px-7 py-3.5 text-sm font-bold text-white shadow-lg shadow-accent-500/30 transition hover:bg-accent-600">Donate now</a>
        <a href="<?= e(url('programs.php')) ?>" class="rounded-xl border border-white/25 bg-white/5 px-7 py-3.5 text-sm font-bold text-white backdrop-blur transition hover:bg-white/15">Explore programmes</a>
      </div>
      <div class="mt-8 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-white/70">
        <span class="inline-flex items-center gap-2"><i class="fa-solid fa-circle-check text-accent-400"></i> 12A &amp; 80G tax-exempt</span>
        <span class="inline-flex items-center gap-2"><i class="fa-solid fa-circle-check text-accent-400"></i> 100% transparent</span>
        <span class="inline-flex items-center gap-2"><i class="fa-solid fa-circle-check text-accent-400"></i> On-ground impact</span>
      </div>
    </div>
    <!-- Floating impact card -->
    <div class="relative" data-aos="fade-left">
      <div class="rounded-panel bg-white/10 p-2 ring-1 ring-inset ring-white/15 backdrop-blur">
        <div class="rounded-[1rem] bg-surface p-6 shadow-modal">
          <p class="text-sm font-semibold uppercase tracking-wide text-content-muted">Our reach so far</p>
          <div class="mt-4 grid grid-cols-2 gap-4">
            <?php foreach ($stats as $s): ?>
              <div class="rounded-xl bg-surface-sunken p-4">
                <div class="font-display text-2xl font-extrabold text-brand-700"><span data-count="<?= (int) $s['n'] ?>">0</span><?= e($s['suffix']) ?></div>
                <div class="mt-0.5 text-xs font-medium text-content-muted"><?= e($s['label']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
          <a href="<?= e(url('about.php')) ?>" class="mt-5 flex items-center justify-center gap-2 rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-800">About our work <i class="fa-solid fa-arrow-right text-xs"></i></a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============================ STATS STRIP ============================ -->
<section class="border-b border-edge bg-surface">
  <div class="container-site grid grid-cols-2 gap-6 py-10 lg:grid-cols-4">
    <?php foreach ($stats as $s): ?>
      <div class="text-center" data-aos="zoom-in">
        <div class="font-display text-3xl font-extrabold tracking-tight text-brand-700 sm:text-4xl"><span data-count="<?= (int) $s['n'] ?>">0</span><?= e($s['suffix']) ?></div>
        <div class="mt-1 text-sm font-medium text-content-muted"><?= e($s['label']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ============================ IMPACT / TRANSPARENCY ============================ -->
<section class="section bg-surface">
  <div class="container-site grid items-center gap-12 lg:grid-cols-2">
    <div class="relative" data-aos="fade-right">
      <div class="aspect-[4/3] overflow-hidden rounded-panel bg-gradient-to-br from-brand-700 to-brand-900">
        <div class="flex h-full w-full flex-col items-center justify-center text-center text-white">
          <i class="fa-solid fa-graduation-cap text-6xl text-accent-400"></i>
          <p class="mt-4 max-w-xs px-6 text-lg font-semibold">Every rupee reaches the classroom</p>
        </div>
      </div>
      <div class="absolute -bottom-6 -right-4 hidden rounded-2xl bg-accent-500 px-6 py-4 text-white shadow-pop sm:block">
        <div class="font-display text-2xl font-extrabold">80G</div><div class="text-xs font-medium">tax-exempt giving</div>
      </div>
    </div>
    <div data-aos="fade-left">
      <span class="eyebrow">Why support us</span>
      <h2 class="section-heading">Real programmes, measurable impact</h2>
      <p class="section-subheading">We work alongside government schools and local communities, and we report on every programme. Your support is accountable and visible.</p>
      <ul class="mt-6 space-y-3">
        <?php foreach (['Direct-to-classroom education support', 'Job-ready vocational & digital skills', 'Merit & need-based scholarships', 'Published annual reports & donor updates', 'Registered under 12A & 80G of the Income Tax Act'] as $point): ?>
          <li class="flex items-start gap-3"><span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full bg-accent-100 text-accent-600"><i class="fa-solid fa-check text-xs"></i></span><span class="text-sm text-content"><?= e($point) ?></span></li>
        <?php endforeach; ?>
      </ul>
      <a href="<?= e(url('about.php')) ?>" class="mt-7 inline-flex items-center gap-2 rounded-lg bg-brand-700 px-6 py-3 text-sm font-bold text-white transition hover:bg-brand-800">Learn more about us <i class="fa-solid fa-arrow-right text-xs"></i></a>
    </div>
  </div>
</section>

<!-- ============================ PROGRAMMES ============================ -->
<section class="section bg-surface-sunken">
  <div class="container-site">
    <div class="mx-auto mb-12 max-w-2xl text-center" data-aos="fade-up">
      <span class="eyebrow">What we do</span>
      <h2 class="section-heading">Our programmes</h2>
      <p class="section-subheading mx-auto">Designed with local partners and measured by real outcomes.</p>
    </div>
    <?php if ($programs === []): ?>
      <p class="text-center text-content-muted">Programmes will be listed here soon.</p>
    <?php else: ?>
      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($programs as $i => $p): ?>
          <div class="group flex flex-col overflow-hidden rounded-card border border-edge bg-surface shadow-card transition hover:-translate-y-1 hover:shadow-pop" data-aos="fade-up" data-aos-delay="<?= ($i % 3) * 100 ?>">
            <div class="flex items-center gap-3 border-b border-edge bg-gradient-to-br from-brand-50 to-surface p-5">
              <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-brand-700 text-white"><?= icon((string) ($p['icon'] ?: 'programs'), 'h-6 w-6') ?></span>
              <div><span class="text-2xs font-semibold uppercase tracking-wide text-accent-600"><?= e($p['kind']) ?></span><h3 class="font-display text-base font-bold leading-snug text-content"><?= e($p['title']) ?></h3></div>
            </div>
            <div class="flex flex-1 flex-col p-5">
              <p class="line-clamp-3 text-sm leading-relaxed text-content-muted"><?= e((string) $p['summary']) ?></p>
              <a href="<?= e(url('programs.php')) ?>" class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 group-hover:text-accent-600">Learn more <i class="fa-solid fa-arrow-right text-xs transition group-hover:translate-x-0.5"></i></a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="mt-10 text-center"><a href="<?= e(url('programs.php')) ?>" class="inline-block rounded-lg border border-edge bg-surface px-6 py-3 text-sm font-semibold text-content transition hover:bg-surface">View all programmes</a></div>
    <?php endif; ?>
  </div>
</section>

<!-- ============================ CAMPAIGNS ============================ -->
<?php if ($campaigns !== []): ?>
<section class="section bg-surface">
  <div class="container-site">
    <div class="mb-12 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-end" data-aos="fade-up">
      <div><span class="eyebrow">Support a cause</span><h2 class="section-heading">Active campaigns</h2></div>
      <a href="<?= e(url('campaigns.php')) ?>" class="text-sm font-semibold text-brand-700 hover:text-accent-600">View all campaigns →</a>
    </div>
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <?php foreach ($campaigns as $i => $c): ?><div data-aos="fade-up" data-aos-delay="<?= ($i % 3) * 100 ?>"><?= campaign_card($c) ?></div><?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ============================ WHY CHOOSE / FEATURES ============================ -->
<section class="section bg-brand-900 text-white">
  <div class="container-site">
    <div class="mx-auto mb-12 max-w-2xl text-center" data-aos="fade-up">
      <span class="mb-3 inline-block text-2xs font-bold uppercase tracking-[0.14em] text-accent-400">The Eduskill difference</span>
      <h2 class="font-display text-3xl font-bold tracking-tight sm:text-4xl">Why families &amp; donors trust us</h2>
    </div>
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
      <?php foreach ([['fa-hand-holding-heart', 'Direct impact', 'Funds reach classrooms and learners, not overheads.'], ['fa-chart-line', 'Measured outcomes', 'We track attendance, skills, and placements.'], ['fa-people-group', 'Local partners', 'Built with schools and community organisations.'], ['fa-file-shield', 'Full transparency', 'Annual reports and 80G receipts for every gift.']] as $f): ?>
        <div class="rounded-card bg-white/5 p-6 ring-1 ring-inset ring-white/10 transition hover:bg-white/10" data-aos="fade-up">
          <span class="grid h-12 w-12 place-items-center rounded-xl bg-accent-500 text-white"><i class="fa-solid <?= e($f[0]) ?> text-lg"></i></span>
          <h3 class="mt-4 font-display text-lg font-bold"><?= e($f[1]) ?></h3>
          <p class="mt-2 text-sm leading-relaxed text-white/70"><?= e($f[2]) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ============================ TESTIMONIALS ============================ -->
<?php if ($testimonials !== []): ?>
<section class="section bg-surface-sunken">
  <div class="container-site">
    <div class="mx-auto mb-12 max-w-2xl text-center" data-aos="fade-up"><span class="eyebrow">Voices from our community</span><h2 class="section-heading">Stories of change</h2></div>
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <?php foreach ($testimonials as $t): ?>
        <figure class="flex flex-col rounded-card border border-edge bg-surface p-6 shadow-card" data-aos="fade-up">
          <div class="mb-3 flex gap-0.5 text-accent-500"><?php for ($i = 0; $i < max(1, (int) $t['rating']); $i++): ?><i class="fa-solid fa-star text-sm"></i><?php endfor; ?></div>
          <blockquote class="flex-1 text-sm leading-relaxed text-content">“<?= e($t['quote']) ?>”</blockquote>
          <figcaption class="mt-4 flex items-center gap-3">
            <span class="grid h-10 w-10 place-items-center rounded-full bg-brand-100 text-sm font-bold text-brand-700"><?= e(mb_strtoupper(mb_substr((string) $t['author_name'], 0, 1))) ?></span>
            <div><div class="text-sm font-semibold text-content"><?= e($t['author_name']) ?></div><?php if ($t['author_role']): ?><div class="text-xs text-content-muted"><?= e($t['author_role']) ?></div><?php endif; ?></div>
          </figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ============================ BLOG ============================ -->
<?php if ($posts !== []): ?>
<section class="section bg-surface">
  <div class="container-site">
    <div class="mb-12 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-end" data-aos="fade-up">
      <div><span class="eyebrow">Latest updates</span><h2 class="section-heading">From our blog</h2></div>
      <a href="<?= e(url('blog.php')) ?>" class="text-sm font-semibold text-brand-700 hover:text-accent-600">Read all articles →</a>
    </div>
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <?php foreach ($posts as $i => $post): ?>
        <a href="<?= e(url('blog-details.php?slug=' . urlencode((string) $post['slug']))) ?>" class="content-card group" data-aos="fade-up" data-aos-delay="<?= ($i % 3) * 100 ?>">
          <div class="content-card-media"><?php if ($post['featured_image']): ?><img src="<?= e(asset($post['featured_image'])) ?>" alt="" loading="lazy"><?php else: ?><div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-brand-700 to-brand-900 text-white"><?= icon('blog', 'h-10 w-10 opacity-70') ?></div><?php endif; ?></div>
          <div class="content-card-body"><?php if ($post['category_name']): ?><span class="text-2xs font-semibold uppercase tracking-wide text-accent-600"><?= e($post['category_name']) ?></span><?php endif; ?><h3 class="content-card-title"><?= e($post['title']) ?></h3><span class="mt-auto pt-3 text-xs text-content-subtle"><?= e(date('d M Y', strtotime((string) ($post['published_at'] ?: $post['created_at'])))) ?></span></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ============================ FINAL CTA ============================ -->
<section class="section">
  <div class="container-site">
    <div class="relative overflow-hidden rounded-panel bg-gradient-to-br from-accent-500 to-accent-600 px-6 py-14 text-center sm:px-12" data-aos="zoom-in">
      <h2 class="font-display text-3xl font-extrabold tracking-tight text-white sm:text-4xl">Ready to change a life?</h2>
      <p class="mx-auto mt-3 max-w-xl text-base text-white/90">Your gift keeps a child in school and helps a young person find work. Every donation is tax-exempt under Section 80G.</p>
      <div class="mt-8 flex flex-wrap justify-center gap-3">
        <a href="<?= e(url('donate.php')) ?>" class="rounded-xl bg-white px-8 py-3.5 text-sm font-bold text-accent-600 shadow-lg transition hover:bg-white/90">Donate now</a>
        <a href="<?= e(url('contact.php')) ?>" class="rounded-xl border border-white/40 px-8 py-3.5 text-sm font-bold text-white transition hover:bg-white/10">Get in touch</a>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
