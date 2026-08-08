<?php
/**
 * =============================================================================
 *  News & Media — the press room of EDUSKILL INDIA FOUNDATION.
 *
 *  - News & Press cards come from blogs joined to blog_categories, limited to
 *    the news / press / media categories (or featured posts). If none of those
 *    exist yet, the page gracefully falls back to the latest published posts.
 *  - A curated "Media Coverage" wall shows how the Foundation has been featured
 *    in the press (default copy — degrades cleanly before any is seeded).
 *  - A "Press Kit / Downloads" library pulls documents in the press / media /
 *    report categories, each served through the download counter endpoint.
 *
 *  Every section is DB-driven with sensible fallbacks and empty-states so the
 *  page looks complete and premium before any content is seeded.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* ---- Data ------------------------------------------------------------- */

/* News / press / media posts — or featured posts — newest first. */
$news = db_all(
    "SELECT b.*, c.name AS category_name, c.slug AS category_slug
     FROM blogs b
     LEFT JOIN blog_categories c ON c.id = b.category_id
     WHERE b.status = 'published' AND b.deleted_at IS NULL
       AND (c.slug IN ('news', 'press', 'media') OR b.is_featured = 1)
     ORDER BY b.published_at DESC, b.id DESC
     LIMIT 7"
);

/* Fallback — latest published posts so the newsroom is never empty. */
if (!$news) {
    $news = db_all(
        "SELECT b.*, c.name AS category_name, c.slug AS category_slug
         FROM blogs b
         LEFT JOIN blog_categories c ON c.id = b.category_id
         WHERE b.status = 'published' AND b.deleted_at IS NULL
         ORDER BY b.published_at DESC, b.id DESC
         LIMIT 7"
    );
}

/* Split off a lead story for the spotlight, the rest fill the grid. */
$lead    = $news ? $news[0] : null;
$rest    = $news ? array_slice($news, 1, 6) : [];

/* Press-kit documents (press / media / report), grouped by category. */
$documents = db_all(
    "SELECT * FROM documents
     WHERE status = 1 AND category IN ('press', 'media', 'report', 'reports')
     ORDER BY category ASC, title ASC"
);

/* Headline numbers, derived from real data with pre-seed fallbacks. */
$newsCount = count($news);
$kitCount  = count($documents);
$kitDownloads = 0;
foreach ($documents as $d) {
    $kitDownloads += (int) $d['downloads'];
}

/* Press desk contact details (DB-driven with config fallbacks). */
$pressEmail = get_setting('press_email', get_setting('contact_email', SITE_EMAIL));
$pressPhone = get_setting('contact_phone', SITE_PHONE);
$pressAddr  = get_setting('contact_address', SITE_ADDRESS);
$pressCin   = get_setting('company_cin', SITE_CIN);
$mailSubject = rawurlencode('Press enquiry — ' . SITE_NAME);

/* Presentation helper — colour + Lucide icon name for a given file type. */
$fileMeta = static function (?string $type): array {
    $t = strtolower(trim((string) $type));
    return match ($t) {
        'pdf'                                       => ['class' => 'badge-danger',  'icon' => 'file-text',        'label' => 'PDF'],
        'doc', 'docx', 'rtf', 'odt'                 => ['class' => 'badge-brand',   'icon' => 'file-text',        'label' => strtoupper($t)],
        'xls', 'xlsx', 'csv', 'ods'                 => ['class' => 'badge-success', 'icon' => 'file-spreadsheet', 'label' => strtoupper($t)],
        'ppt', 'pptx', 'odp'                        => ['class' => 'badge-accent',  'icon' => 'presentation',     'label' => strtoupper($t)],
        'zip', 'rar', '7z', 'tar'                   => ['class' => 'badge-muted',   'icon' => 'file-archive',     'label' => strtoupper($t)],
        'jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'  => ['class' => 'badge-accent',  'icon' => 'file-image',       'label' => strtoupper($t)],
        default                                     => ['class' => 'badge-muted',   'icon' => 'file',             'label' => $t !== '' ? strtoupper($t) : 'FILE'],
    };
};

/* Presentation helper — friendly label + Lucide icon name for a document category slug. */
$catMeta = static function (string $cat): array {
    $key   = strtolower($cat);
    $icons = ['press' => 'newspaper', 'media' => 'clapperboard', 'report' => 'bar-chart-3', 'reports' => 'bar-chart-3'];
    $labels = ['press' => 'Press Releases', 'media' => 'Media Kit', 'report' => 'Reports', 'reports' => 'Reports'];
    return [
        'icon'  => $icons[$key] ?? 'folder',
        'label' => $labels[$key] ?? ucwords(str_replace(['-', '_'], ' ', $cat)),
    ];
};

/* Group the documents by category (stable order). */
$kitGroups = [];
foreach ($documents as $doc) {
    $cat = ($doc['category'] !== '' && $doc['category'] !== null) ? $doc['category'] : 'press';
    $kitGroups[$cat][] = $doc;
}

/* Media-coverage wall — REAL coverage only, managed in Settings so the site
 * never shows invented outlets/quotes. press_coverage = JSON array of
 * {outlet, quote, topic, icon}; press_outlets = comma-separated names.
 * Both default empty → the honest "coming soon" state renders instead. */
$coverage = json_decode((string) get_setting('press_coverage', '[]'), true);
$coverage = is_array($coverage)
    ? array_values(array_filter($coverage, static fn ($c) => !empty($c['outlet']) && !empty($c['quote'])))
    : [];

$outlets = array_values(array_filter(array_map('trim', explode(',', (string) get_setting('press_outlets', '')))));

seo_set([
    'title'       => 'News & Media',
    'description' => 'The EDUSKILL INDIA FOUNDATION press room — latest news, press releases, media coverage and a downloadable press kit covering our work across Bihar.',
    'page_key'    => 'news-media',
    'image'       => $lead ? image_url($lead['featured_image'] ?? null, 'blog') : null,
]);

$page_hero = [
    'title'      => 'News & Media',
    'subtitle'   => 'The latest announcements, press releases and media coverage of our work — plus a ready-to-use press kit for journalists and partners.',
    'breadcrumb' => [['label' => 'News & Media']],
];

include __DIR__ . '/includes/header.php';
?>

<!-- ============================== STAT HIGHLIGHTS ============================== -->
<?php
/* Real numbers only — a stat renders when its count is non-zero (no invented
 * fallback figures; fabricated stats erode donor trust). */
$pressStats = array_values(array_filter([
    ['icon' => 'newspaper', 'count' => (int) $newsCount,       'label' => 'News &amp; Press Items'],
    ['icon' => 'megaphone', 'count' => count($coverage),       'label' => 'Media Mentions'],
    ['icon' => 'folders',   'count' => (int) $kitCount,        'label' => 'Press Kit Assets'],
    ['icon' => 'download',  'count' => (int) $kitDownloads,    'label' => 'Kit Downloads'],
], static fn ($s) => $s['count'] > 0));
?>
<?php if ($pressStats): ?>
<section class="section section-brand section-sm">
    <div class="container">
        <div class="counter-grid">
            <?php foreach ($pressStats as $i => $s): ?>
                <div class="counter-item reveal<?= $i > 0 ? ' delay-' . min($i, 3) : '' ?>">
                    <div class="icon-badge" style="background:rgba(255,255,255,.18);"><?= lucide($s['icon']) ?></div>
                    <div class="counter-value"><span data-counter="<?= $s['count'] ?>">0</span></div>
                    <div class="counter-label"><?= $s['label'] ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================== NEWSROOM ============================== -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">From the Newsroom</span>
            <h2 class="section-title">Latest <span class="text-grad-ocean">News &amp; Press</span></h2>
            <p class="section-subtitle">Announcements, field updates and press releases from across our programs in Bihar.</p>
        </div>

        <?php if ($lead): $leadLink = url('blog-details?slug=' . $lead['slug']); $leadDate = $lead['published_at'] ?: $lead['created_at']; ?>
            <!-- Lead / spotlight story -->
            <article class="glass-card reveal mb-4" style="overflow:hidden;padding:0;">
                <div class="grid grid-2 items-stretch gap-0">
                    <a href="<?= e($leadLink) ?>" class="card-media" style="margin:0;min-height:260px;">
                        <img src="<?= e(image_url($lead['featured_image'] ?? null, 'blog')) ?>" alt="<?= e($lead['title']) ?>" loading="lazy" width="800" height="500" style="width:100%;height:100%;object-fit:cover;">
                    </a>
                    <div style="padding:clamp(1.5rem,4vw,2.75rem);display:flex;flex-direction:column;justify-content:center;">
                        <div class="flex items-center gap-2 flex-wrap mb-2">
                            <span class="badge badge-accent"><?= lucide('star') ?> Featured</span>
                            <?php if (!empty($lead['category_name'])): ?>
                                <span class="badge badge-brand"><?= e($lead['category_name']) ?></span>
                            <?php endif; ?>
                            <small class="text-muted"><?= lucide('calendar-days') ?> <?= e(format_date($leadDate, 'd M Y')) ?></small>
                        </div>
                        <h3 class="mb-2" style="font-size:clamp(1.4rem,3vw,2rem);line-height:1.2;">
                            <a href="<?= e($leadLink) ?>" style="color:inherit;"><?= e($lead['title']) ?></a>
                        </h3>
                        <p class="text-muted"><?= e(excerpt($lead['excerpt'] ?: ($lead['content'] ?? ''), 34)) ?></p>
                        <div class="flex flex-wrap gap-2 mt-3">
                            <a class="btn btn-3d" href="<?= e($leadLink) ?>">Read the Full Story <?= lucide('arrow-right') ?></a>
                            <a class="btn btn-outline" href="<?= e(url('blogs')) ?>">All Articles</a>
                        </div>
                    </div>
                </div>
            </article>

            <?php if ($rest): ?>
                <div class="grid grid-3">
                    <?php foreach ($rest as $i => $b):
                        $link = url('blog-details?slug=' . $b['slug']);
                        $date = $b['published_at'] ?: $b['created_at'];
                    ?>
                        <article class="card reveal <?= 'delay-' . (($i % 3) + 1) ?>">
                            <a href="<?= e($link) ?>" class="card-media">
                                <img src="<?= e(image_url($b['featured_image'] ?? null, 'blog')) ?>" alt="<?= e($b['title']) ?>" loading="lazy" width="600" height="375">
                                <?php if (!empty($b['category_name'])): ?>
                                    <span class="badge badge-brand" style="position:absolute;top:12px;left:12px;"><?= e($b['category_name']) ?></span>
                                <?php endif; ?>
                            </a>
                            <div class="card-body flex flex-col">
                                <small class="text-muted mb-1"><?= lucide('calendar-days') ?> <?= e(format_date($date, 'd M Y')) ?></small>
                                <h3 class="card-title"><a href="<?= e($link) ?>" style="color:inherit;"><?= e($b['title']) ?></a></h3>
                                <p class="card-text"><?= e(excerpt($b['excerpt'] ?: ($b['content'] ?? ''), 20)) ?></p>
                                <a class="btn btn-outline btn-sm mt-auto" href="<?= e($link) ?>">Read More <?= lucide('arrow-right') ?></a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="empty-state">
                <div class="icon-badge"><?= lucide('newspaper') ?></div>
                <h3>Newsroom launching soon</h3>
                <p class="text-muted">Our latest announcements and press releases will be published here. In the meantime, explore our programs or reach out to our press desk.</p>
                <div class="flex flex-wrap justify-center gap-2 mt-3">
                    <a class="btn btn-primary" href="<?= e(url('programs')) ?>">Explore Our Programs</a>
                    <a class="btn btn-outline" href="mailto:<?= e($pressEmail) ?>?subject=<?= e($mailSubject) ?>">Contact the Press Desk</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ============================== MEDIA COVERAGE ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">In the Press</span>
            <h2 class="section-title">Media Coverage</h2>
            <p class="section-subtitle">How journalists and publications have covered our work on the ground across Bihar.</p>
        </div>

        <?php if ($outlets): ?>
        <!-- "As featured in" marquee strip (settings-driven, real outlets only) -->
        <div class="marquee reveal mb-4" aria-label="Publications that have featured our work">
            <div class="marquee-track">
                <?php foreach ($outlets as $o): ?>
                    <span class="logo-slide chip" style="font-weight:700;"><?= e($o) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($coverage): ?>
            <div class="grid grid-2 gap-4">
                <?php foreach ($coverage as $i => $c): ?>
                    <figure class="glass-card reveal <?= 'delay-' . (($i % 2) + 1) ?>" style="margin:0;">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="icon-box o"><span class="ib-icon"><?= lucide($c['icon']) ?></span></span>
                            <div>
                                <strong style="display:block;"><?= e($c['outlet']) ?></strong>
                                <span class="badge badge-muted"><?= e($c['topic']) ?></span>
                            </div>
                        </div>
                        <blockquote style="margin:0;font-size:1.05rem;line-height:1.6;color:var(--text-soft);">“<?= e($c['quote']) ?>”</blockquote>
                    </figure>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon-badge"><?= lucide('megaphone') ?></div>
                <h3>Coverage highlights coming soon</h3>
                <p class="text-muted">As our work reaches more communities, media coverage and press mentions will be gathered here.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ============================== PRESS KIT / DOWNLOADS ============================== -->
<section class="section" id="press-kit">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">For Journalists</span>
            <h2 class="section-title">Press Kit &amp; Downloads</h2>
            <p class="section-subtitle">Official press releases, media assets and reports — free to download and reuse with attribution.</p>
        </div>

        <?php if ($kitGroups): ?>
            <?php foreach ($kitGroups as $cat => $docs): $m = $catMeta($cat); ?>
                <div class="flex items-center gap-2 mb-3 mt-4 reveal">
                    <span class="icon-box g"><span class="ib-icon"><?= lucide($m['icon']) ?></span></span>
                    <div>
                        <h3 class="mb-0" style="font-size:1.2rem;"><?= e($m['label']) ?></h3>
                        <small class="text-muted"><?= count($docs) ?> <?= count($docs) === 1 ? 'file' : 'files' ?> available</small>
                    </div>
                </div>
                <div class="grid grid-3">
                    <?php foreach ($docs as $i => $doc):
                        $meta  = $fileMeta($doc['file_type']);
                        $bytes = (int) ($doc['file_size'] ?? 0);
                        $size  = $bytes > 0 ? human_filesize($bytes) : null;
                        $href  = url('forms/download?id=' . (int) $doc['id']);
                    ?>
                        <article class="card reveal <?= 'delay-' . (($i % 3) + 1) ?>">
                            <div class="card-body flex flex-col">
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <span class="icon-box p" style="font-size:1.4rem;"><span class="ib-icon"><?= lucide($meta['icon']) ?></span></span>
                                    <span class="badge <?= e($meta['class']) ?>"><?= e($meta['label']) ?></span>
                                </div>
                                <h4 class="card-title" style="font-size:1.05rem;"><?= e($doc['title']) ?></h4>
                                <?php if (!empty($doc['description'])): ?>
                                    <p class="card-text"><?= e(excerpt($doc['description'], 18)) ?></p>
                                <?php endif; ?>
                                <div class="flex items-center gap-2 text-muted mb-3" style="font-size:.82rem;">
                                    <?php if ($size): ?><span><?= lucide('package') ?> <?= e($size) ?></span><?php endif; ?>
                                    <span><?= lucide('download') ?> <?= e(number_short((int) $doc['downloads'])) ?> downloads</span>
                                </div>
                                <a class="btn btn-primary btn-sm btn-block mt-auto" href="<?= e($href) ?>" rel="noopener"><?= lucide('download') ?> Download</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon-badge"><?= lucide('folders') ?></div>
                <h3>Press kit in preparation</h3>
                <p class="text-muted">
                    Our official press releases, logo pack, fact sheet and reports are being finalised for publication.
                    Need an asset urgently? Email our press desk and we'll send it straight over.
                </p>
                <div class="flex flex-wrap justify-center gap-2 mt-3">
                    <a class="btn btn-primary" href="mailto:<?= e($pressEmail) ?>?subject=<?= e($mailSubject) ?>">Request Press Assets</a>
                    <a class="btn btn-outline" href="<?= e(url('resources')) ?>">Browse All Resources</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ============================== PRESS DESK CONTACT ============================== -->
<section class="section section-alt">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Media Enquiries</span>
            <h2 class="section-title">Talk to Our Press Desk</h2>
            <p class="section-subtitle">Working on a story about our work in Bihar? We can help with interviews, verified figures, photographs and filming access.</p>
        </div>
        <div class="grid grid-2 gap-4 items-stretch">
            <div class="card-3d reveal">
                <div class="icon-badge"><?= lucide('phone') ?></div>
                <h3 class="card-title">Reach the Communications Team</h3>
                <p class="card-text">For interviews with our directors, on-ground access or data on our programs, contact the press desk — we typically respond within two working days.</p>
                <ul style="list-style:none;padding:0;margin:1rem 0 0;line-height:2;">
                    <li><?= lucide('mail') ?> <a href="mailto:<?= e($pressEmail) ?>?subject=<?= e($mailSubject) ?>"><?= e($pressEmail) ?></a></li>
                    <li><?= lucide('phone') ?> <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $pressPhone)) ?>"><?= e($pressPhone) ?></a></li>
                    <li><?= lucide('map-pin') ?> <span><?= e($pressAddr) ?></span></li>
                    <li><?= lucide('landmark') ?> <span>CIN: <?= e($pressCin) ?></span></li>
                </ul>
                <a class="btn btn-primary mt-4" href="mailto:<?= e($pressEmail) ?>?subject=<?= e($mailSubject) ?>">Email the Press Desk</a>
            </div>
            <div class="card-3d reveal delay-1">
                <div class="icon-badge accent"><?= lucide('building-2') ?></div>
                <h3 class="card-title">About the Foundation</h3>
                <p class="card-text"><?= e(get_setting('footer_about', SITE_NAME . ' is a registered non-profit based in Patna, Bihar, working across education, healthcare, skill development and relief to empower underserved communities — spreading hope and creating lasting change.')) ?></p>
                <ul class="list-check mt-3">
                    <li>Registered non-profit — CIN <?= e($pressCin) ?></li>
                    <li>Based in Patna, Bihar 840007</li>
                </ul>
                <div class="flex gap-2 mt-4 flex-wrap">
                    <a class="btn btn-outline" href="<?= e(url('about')) ?>">More About Us</a>
                    <a class="btn btn-outline" href="<?= e(url('media')) ?>">Videos &amp; Films</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================== CTA BAND ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="card-3d reveal text-center" style="background:var(--grad-brand);color:#fff;">
            <span class="eyebrow" style="justify-content:center;color:#dbeafe;">Stay in the Loop</span>
            <h2 class="text-white">Get Our News First</h2>
            <p style="color:rgba(255,255,255,.9);max-width:560px;margin-inline:auto;">Subscribe to our newsletter for press releases, field updates and stories of impact delivered straight to your inbox.</p>
            <form data-ajax-form data-endpoint="<?= e(url('forms/newsletter')) ?>" class="mt-4" style="max-width:520px;margin-inline:auto;">
                <?= csrf_field() ?>
                <div class="flex gap-2 flex-wrap justify-center">
                    <input class="form-control" type="email" name="email" placeholder="you@example.com" required aria-label="Email address" style="flex:1;min-width:220px;">
                    <button class="btn btn-white" type="submit">Subscribe</button>
                </div>
            </form>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
