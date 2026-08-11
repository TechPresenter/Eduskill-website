<?php
/**
 * =============================================================================
 *  Human-readable HTML sitemap — a complete, grouped index of every public
 *  section of the site, plus a live index of published programs, blog posts,
 *  events and campaigns. Everything is DB-driven with graceful fallbacks so the
 *  page is polished and complete before any content is seeded.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* ---- Curated navigation groups (the static site structure) ------------- */
$groups = [
    [
        'title' => 'Main',
        'icon'  => 'home',
        'class' => '',
        'links' => [
            ['Home',            ''],
            ['About Us',        'about'],
            ['Our Story',       'our-story'],
            ['Mission & Vision','mission-vision'],
            ['Leadership',      'leadership-team'],
            ['Our Team',        'team'],
            ['NGO Details',     'ngo-details'],
        ],
    ],
    [
        'title' => 'Programs & Causes',
        'icon'  => 'target',
        'class' => 'p',
        'links' => [
            ['Programs', 'programs'],
            ['Causes',   'causes'],
            ['Schemes',  'schemes'],
            ['Projects', 'projects'],
        ],
    ],
    [
        'title' => 'Get Involved',
        'icon'  => 'handshake',
        'class' => 'g',
        'links' => [
            ['Volunteer',        'volunteer'],
            ['Internship',       'internship'],
            ['Membership',       'membership'],
            ['Careers',          'career'],
            ['Become a Partner', 'become-partner'],
            ['Donate',           'donate'],
            ['Scholarship',      'scholarship'],
        ],
    ],
    [
        'title' => 'Media',
        'icon'  => 'camera',
        'class' => 'o',
        'links' => [
            ['Gallery',       'gallery'],
            ['News & Media',  'news-media'],
            ['Blog',          'blogs'],
            ['Testimonials',  'testimonials'],
            ['Resources',     'resources'],
            ['Events',        'events'],
            ['Calendar',      'calendar'],
        ],
    ],
    [
        'title' => 'Account',
        'icon'  => 'user',
        'class' => '',
        'links' => [
            ['Login',  'login'],
            ['Signup', 'signup'],
        ],
    ],
    [
        'title' => 'Legal',
        'icon'  => 'scroll',
        'class' => 'p',
        'links' => [
            ['Privacy Policy', 'privacy-policy'],
            ['Terms of Use',   'terms'],
            ['Refund Policy',  'refund-policy'],
            ['Disclaimer',     'disclaimer'],
            ['Cookie Policy',  'cookie-policy'],
        ],
    ],
];

/* ---- Live published content (defensive: tables may be partly seeded) ---- */
$safe = static function (string $sql, array $params = []): array {
    try {
        return db_all($sql, $params);
    } catch (Throwable $e) {
        return [];
    }
};

$programs  = $safe("SELECT title, slug FROM programs WHERE status='active' ORDER BY is_featured DESC, sort_order ASC, title ASC");
$blogs     = $safe("SELECT title, slug, published_at, created_at FROM blogs WHERE status='published' ORDER BY published_at DESC, id DESC LIMIT 10");
$events    = $safe("SELECT title, slug, start_datetime FROM events WHERE status='published' AND start_datetime >= NOW() ORDER BY start_datetime ASC LIMIT 12");
$campaigns = $safe("SELECT title, slug FROM campaigns WHERE status IN ('active','completed') ORDER BY is_featured DESC, id DESC LIMIT 12");

/* Total link count for the intro stat */
$staticCount = 0;
foreach ($groups as $g) { $staticCount += count($g['links']); }
$liveCount = count($programs) + count($blogs) + count($events) + count($campaigns);

seo_set([
    'title'       => 'Sitemap',
    'description' => 'Browse the complete sitemap of EDUSKILL INDIA FOUNDATION — every page, program, cause, event and story in one place. Find exactly what you are looking for.',
    'page_key'    => 'sitemap',
    'type'        => 'website',
]);

$page_hero = [
    'title'      => 'Sitemap',
    'subtitle'   => 'Every page, program and story on our website — neatly organised so you can find exactly what you need.',
    'breadcrumb' => [['label' => 'Sitemap']],
];

include __DIR__ . '/includes/header.php';
?>

<!-- ============================== INTRO ============================== -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Find Your Way</span>
            <h2 class="section-title">Explore the <span class="text-grad-multi">Entire Website</span></h2>
            <p class="section-subtitle">A complete, human-friendly map of EDUSKILL INDIA FOUNDATION. Jump straight to any section, or scroll down for a live index of our latest programs, stories, events and campaigns.</p>
        </div>

        <div class="grid grid-3 gap-3 mb-4">
            <div class="stat-premium reveal text-center">
                <div class="counter-value" style="font-size:2.4rem;"><span data-counter="<?= (int) $staticCount ?>">0</span></div>
                <div class="counter-label">Core Pages</div>
            </div>
            <div class="stat-premium reveal delay-1 text-center">
                <div class="counter-value" style="font-size:2.4rem;"><span data-counter="<?= (int) count($groups) ?>">0</span></div>
                <div class="counter-label">Sections</div>
            </div>
            <div class="stat-premium reveal delay-2 text-center">
                <div class="counter-value" style="font-size:2.4rem;"><span data-counter="<?= (int) $liveCount ?>">0</span>+</div>
                <div class="counter-label">Live Content Links</div>
            </div>
        </div>

        <div class="text-center">
            <a class="btn btn-outline" href="<?= e(url('sitemap.xml')) ?>" target="_blank" rel="noopener"><?= lucide('search') ?> View XML Sitemap (for search engines)</a>
        </div>
    </div>
</section>

<!-- ============================== GROUPED LINK LISTS ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Site Structure</span>
            <h2 class="section-title">Browse by Section</h2>
            <p class="section-subtitle">All the main pages, grouped by what you are trying to do.</p>
        </div>

        <div class="grid grid-3 gap-4">
            <?php foreach ($groups as $i => $g): ?>
                <div class="glass-card reveal <?= 'delay-' . (($i % 3) + 1) ?>" style="padding:1.75rem;">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="icon-box <?= e($g['class']) ?>" style="padding:0;">
                            <span class="ib-icon" style="width:52px;height:52px;font-size:1.5rem;margin:0;"><?= lucide($g['icon']) ?></span>
                        </span>
                        <h3 class="card-title mb-0"><?= e($g['title']) ?></h3>
                    </div>
                    <ul class="sitemap-list">
                        <?php foreach ($g['links'] as $link): [$label, $path] = $link; ?>
                            <li>
                                <a href="<?= e(url($path)) ?>">
                                    <span aria-hidden="true" style="color:var(--brand-600);font-weight:800;"><?= lucide('chevron-right') ?></span>
                                    <?= e($label) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== LIVE PROGRAMS ============================== -->
<section class="section">
    <div class="container">
        <div class="section-head left flex justify-between items-center flex-wrap gap-2">
            <div>
                <span class="eyebrow">What We Do</span>
                <h2 class="section-title mb-0">Programs</h2>
            </div>
            <a class="btn btn-outline btn-sm" href="<?= e(url('programs')) ?>">All Programs</a>
        </div>

        <?php if ($programs): ?>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($programs as $p): ?>
                    <a class="chip" href="<?= e($p['slug'] ? url('programs?slug=' . $p['slug']) : url('programs')) ?>"><?= lucide('target') ?> <?= e($p['title']) ?></a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon-badge"><?= lucide('target') ?></div>
                <h3>Programs coming soon</h3>
                <p class="text-muted">Our programs are being prepared. Explore the overview page in the meantime.</p>
                <a class="btn btn-primary mt-2" href="<?= e(url('programs')) ?>">View Programs</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ============================== LIVE BLOGS + EVENTS ============================== -->
<section class="section section-alt">
    <div class="container grid grid-2 gap-4 items-start">

        <!-- Latest blog posts -->
        <div class="reveal">
            <div class="section-head left flex justify-between items-center flex-wrap gap-2">
                <div>
                    <span class="eyebrow">From Our Blog</span>
                    <h2 class="section-title mb-0">Latest Stories</h2>
                </div>
                <a class="btn btn-outline btn-sm" href="<?= e(url('blogs')) ?>">All Posts</a>
            </div>
            <?php if ($blogs): ?>
                <ul class="sitemap-list sitemap-list-plain">
                    <?php foreach ($blogs as $b): ?>
                        <li>
                            <a href="<?= e(url('blog-details?slug=' . $b['slug'])) ?>">
                                <span aria-hidden="true" style="color:var(--brand-600);font-weight:800;"><?= lucide('chevron-right') ?></span>
                                <span><?= e($b['title']) ?></span>
                                <small class="text-muted" style="margin-left:auto;white-space:nowrap;"><?= e(format_date($b['published_at'] ?: $b['created_at'], 'd M Y')) ?></small>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="empty-state">
                    <div class="icon-badge"><?= lucide('clipboard-pen') ?></div>
                    <h3>No stories yet</h3>
                    <p class="text-muted">Fresh stories and updates will appear here soon.</p>
                    <a class="btn btn-primary mt-2" href="<?= e(url('blogs')) ?>">Visit the Blog</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Upcoming events -->
        <div class="reveal delay-1">
            <div class="section-head left flex justify-between items-center flex-wrap gap-2">
                <div>
                    <span class="eyebrow">Join Us</span>
                    <h2 class="section-title mb-0">Upcoming Events</h2>
                </div>
                <a class="btn btn-outline btn-sm" href="<?= e(url('events')) ?>">All Events</a>
            </div>
            <?php if ($events): ?>
                <ul class="sitemap-list sitemap-list-plain">
                    <?php foreach ($events as $ev): ?>
                        <li>
                            <a href="<?= e(url('events?slug=' . $ev['slug'])) ?>">
                                <span aria-hidden="true" style="color:var(--brand-600);font-weight:800;"><?= lucide('chevron-right') ?></span>
                                <span><?= e($ev['title']) ?></span>
                                <small class="text-muted" style="margin-left:auto;white-space:nowrap;"><?= lucide('calendar-days') ?> <?= e(format_date($ev['start_datetime'], 'd M Y')) ?></small>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="empty-state">
                    <div class="icon-badge"><?= lucide('calendar-days') ?></div>
                    <h3>No upcoming events</h3>
                    <p class="text-muted">Check back soon — new events are added regularly.</p>
                    <a class="btn btn-primary mt-2" href="<?= e(url('calendar')) ?>">See the Calendar</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ============================== LIVE CAMPAIGNS ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="section-head left flex justify-between items-center flex-wrap gap-2">
            <div>
                <span class="eyebrow">Support a Cause</span>
                <h2 class="section-title mb-0">Campaigns</h2>
            </div>
            <a class="btn btn-outline btn-sm" href="<?= e(url('campaigns')) ?>">All Campaigns</a>
        </div>

        <?php if ($campaigns): ?>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($campaigns as $c): ?>
                    <a class="chip" href="<?= e($c['slug'] ? url('campaigns?slug=' . $c['slug']) : url('campaigns')) ?>"><?= lucide('heart') ?> <?= e($c['title']) ?></a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon-badge"><?= lucide('heart') ?></div>
                <h3>No active campaigns</h3>
                <p class="text-muted">There are no live fundraising campaigns right now. You can still support our everyday work.</p>
                <a class="btn btn-primary mt-2" href="<?= e(url('donate')) ?>">Donate Now</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ============================== CTA ============================== -->
<section class="section section-brand">
    <div class="container text-center reveal">
        <span class="eyebrow" style="color:#bfdbfe;justify-content:center;">Can't find it?</span>
        <h2 class="section-title text-white">We're Here to Help</h2>
        <p class="section-subtitle" style="color:rgba(255,255,255,.9);max-width:640px;margin-inline:auto;">If a page isn't listed here or you need a hand finding something, reach out to our team — we'll point you in the right direction.</p>
        <div class="hero-actions flex flex-wrap justify-center gap-2 mt-4">
            <a class="btn btn-white btn-lg" href="<?= e(url('contact')) ?>"><?= lucide('mail') ?> Contact Us</a>
            <a class="btn btn-outline btn-lg" style="border-color:#fff;color:#fff;" href="<?= e(url('faqs')) ?>">Read the FAQs</a>
        </div>
    </div>
</section>

<style>
/* Small page-specific polish for the sitemap link lists */
.sitemap-list { list-style: none; margin: 0; padding: 0; display: grid; gap: .35rem; }
.sitemap-list li { margin: 0; }
.sitemap-list a {
    display: flex; align-items: center; gap: .55rem;
    padding: .5rem .65rem; border-radius: 10px;
    color: var(--text, inherit); font-weight: 600; text-decoration: none;
    transition: background .2s ease, transform .2s ease, color .2s ease;
}
.sitemap-list a:hover { background: rgba(11,78,61, .09); transform: translateX(4px); color: var(--brand-600); }
:root[data-theme="dark"] .sitemap-list a:hover { background: rgba(96, 165, 250, .14); }
.sitemap-list-plain a { padding: .6rem .5rem; border-bottom: 1px solid var(--border, rgba(0,0,0,.08)); border-radius: 0; }
.sitemap-list-plain a:hover { transform: none; background: rgba(11,78,61, .06); }
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
