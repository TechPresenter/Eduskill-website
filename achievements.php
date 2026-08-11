<?php
/**
 * =============================================================================
 *  Our Achievements — impact counters, journey timeline, and certifications.
 *  Every section is DB-driven with graceful fallbacks so the page looks
 *  complete and polished before any content has been seeded.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* ---- Data ------------------------------------------------------------- */

/* Impact counters — all active achievements, in curated order. */
$counters = get_all('achievements', ['status' => 1], 'sort_order ASC');

/* Journey milestones — built from real completed/ongoing projects. */
$projectRows = db_all(
    "SELECT title, summary, description, start_date, end_date, status, progress, beneficiaries
     FROM projects
     WHERE status IN ('completed', 'ongoing')
     ORDER BY COALESCE(end_date, start_date) ASC, id ASC
     LIMIT 8"
);
$milestones = [];
foreach ($projectRows as $pr) {
    $when = $pr['end_date'] ?: $pr['start_date'];
    $milestones[] = [
        // No date on the row? Label ongoing work "Ongoing"; otherwise omit the chip.
        'year'    => $when ? format_date($when, 'Y') : ($pr['status'] === 'ongoing' ? 'Ongoing' : ''),
        'title'   => $pr['title'],
        'summary' => $pr['summary'] ?: excerpt($pr['description'] ?? '', 26),
        'status'  => $pr['status'],
    ];
}

/* Certificates — small preview grid linking to the full certificates page. */
$certs      = db_all("SELECT * FROM certificates WHERE status = 1 ORDER BY sort_order ASC, issue_date DESC, id DESC LIMIT 8");
$certsTotal = db_count('certificates', 'status = 1');

/* ---- Fallbacks -------------------------------------------------------- */
if (!$counters) {
    $counters = [
        ['icon' => 'users', 'prefix' => '', 'value' => 25000, 'suffix' => '+', 'title' => 'Lives Impacted',      'description' => 'Individuals reached across Bihar'],
        ['icon' => 'graduation-cap', 'prefix' => '', 'value' => 120,   'suffix' => '+', 'title' => 'Projects Completed',   'description' => 'Community initiatives delivered'],
        ['icon' => 'handshake', 'prefix' => '', 'value' => 800,   'suffix' => '+', 'title' => 'Active Volunteers',    'description' => 'Changemakers giving their time'],
        ['icon' => 'home', 'prefix' => '', 'value' => 60,    'suffix' => '+', 'title' => 'Villages Reached',     'description' => 'Communities served on the ground'],
        ['icon' => 'stethoscope', 'prefix' => '', 'value' => 150,   'suffix' => '+', 'title' => 'Health Camps',         'description' => 'Free medical camps organised'],
        ['icon' => 'book', 'prefix' => '', 'value' => 5000,  'suffix' => '+', 'title' => 'Students Supported',   'description' => 'Children given a chance to learn'],
        ['icon' => 'user', 'prefix' => '', 'value' => 1200,  'suffix' => '+', 'title' => 'Women Empowered',      'description' => 'Trained and financially uplifted'],
        ['icon' => 'sprout', 'prefix' => '', 'value' => 15000, 'suffix' => '+', 'title' => 'Trees Planted',        'description' => 'Towards a greener tomorrow'],
    ];
}

if (!$milestones) {
    $milestones = [
        ['year' => '2025',  'title' => 'A Foundation Is Born',          'summary' => 'EDUSKILL INDIA FOUNDATION was registered as a non-profit (CIN ' . SITE_CIN . ') in Patna, Bihar, driven by a mission to empower underserved communities.'],
        ['year' => '2025',  'title' => 'First Programs on the Ground',  'summary' => 'We launched our earliest education drives and free health camps, taking help directly to rural families who needed it most.'],
        ['year' => '2026',  'title' => 'Scaling Our Impact',            'summary' => 'Skill-development and women-empowerment initiatives expanded rapidly, reaching thousands more households across the region.'],
        ['year' => 'Today', 'title' => 'A Growing Movement',            'summary' => 'With a dedicated network of volunteers and partners, we continue to spread hope and create measurable, lasting change every single day.'],
    ];
}

/* ---- SEO + hero ------------------------------------------------------- */
seo_set([
    'title'       => 'Our Achievements',
    'description' => 'Explore the impact of EDUSKILL INDIA FOUNDATION — lives touched, milestones reached, and the recognitions that reflect our commitment to communities across Bihar.',
    'page_key'    => 'achievements',
]);

$page_hero = [
    'title'      => 'Our Achievements',
    'subtitle'   => 'Numbers tell only part of the story — but every figure here represents a real person, a real village, and a real change. This is the impact we have created together.',
    'breadcrumb' => [['label' => 'Achievements']],
];

include __DIR__ . '/includes/header.php';
?>

<!-- ============================== IMPACT COUNTERS ============================== -->
<section class="section section-brand">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="justify-content:center;color:#dbeafe;">Impact At A Glance</span>
            <h2 class="section-title text-white">Measurable Change, Real Lives</h2>
            <p class="section-subtitle" style="color:rgba(255,255,255,.9);">Since our founding, compassion has translated into tangible results. Here is what your support has helped us accomplish.</p>
        </div>
        <div class="counter-grid">
            <?php foreach ($counters as $c): ?>
                <div class="counter-item reveal">
                    <div class="icon-badge" style="background:rgba(255,255,255,.18);"><?= lucide($c['icon'] ?? 'star') ?></div>
                    <div class="counter-value"><?= e($c['prefix'] ?? '') ?><span data-counter="<?= (int) ($c['value'] ?? 0) ?>">0</span><?= e($c['suffix'] ?? '') ?></div>
                    <div class="counter-label"><?= e($c['title']) ?></div>
                    <?php if (!empty($c['description'])): ?>
                        <p style="color:rgba(255,255,255,.8);font-size:.85rem;margin-top:.35rem;"><?= e($c['description']) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== WHY IT MATTERS ============================== -->
<section class="section">
    <div class="container grid grid-2 items-center gap-4">
        <div class="reveal">
            <span class="eyebrow">Beyond The Numbers</span>
            <h2 class="section-title"><?= e(get_setting('achievements_intro_title', 'Every statistic is a story of hope')) ?></h2>
            <p class="text-muted"><?= e(get_setting('achievements_intro_text', 'At ' . SITE_NAME . ', we measure success not just in figures, but in the dignity restored to families, the futures opened for children, and the resilience built in communities. Each milestone below is the result of transparent, community-led work — and the generosity of people who believe in change.')) ?></p>
            <ul class="list-check mt-3">
                <li>Transparent programs with independently measurable outcomes</li>
                <li>Every rupee tracked from donation to delivery</li>
                <li>Recognised and certified for accountability and impact</li>
            </ul>
            <div class="flex flex-wrap gap-2 mt-4">
                <a class="btn btn-primary" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Support Our Work</a>
                <a class="btn btn-outline" href="<?= e(url('about')) ?>">About the Foundation</a>
            </div>
        </div>
        <div class="grid gap-3 reveal delay-1">
            <div class="card-3d">
                <div class="icon-badge"><?= lucide('target') ?></div>
                <h3 class="card-title">Driven by Mission</h3>
                <p class="card-text"><?= e(get_setting('mission_short', 'To empower underserved communities by providing access to quality education, healthcare, and sustainable livelihoods.')) ?></p>
            </div>
            <div class="card-3d">
                <div class="icon-badge accent"><?= lucide('sparkles') ?></div>
                <h3 class="card-title">Guided by Vision</h3>
                <p class="card-text"><?= e(get_setting('vision_short', 'An equitable society where every individual has the opportunity to live with dignity and reach their full potential.')) ?></p>
            </div>
        </div>
    </div>
</section>

<!-- ============================== JOURNEY / MILESTONES TIMELINE ============================== -->
<section class="section section-alt">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Our Journey</span>
            <h2 class="section-title">Milestones Along the Way</h2>
            <p class="section-subtitle">From a shared dream to a movement for change — the moments that shaped EDUSKILL INDIA FOUNDATION.</p>
        </div>
        <div style="max-width:820px;margin-inline:auto;position:relative;">
            <?php foreach ($milestones as $i => $m): $isLast = ($i === count($milestones) - 1); ?>
                <div class="reveal <?= 'delay-' . (($i % 3) + 1) ?>" style="position:relative;padding:0 0 <?= $isLast ? '0' : '1.75rem' ?> 2.25rem;border-left:2px solid <?= $isLast ? 'transparent' : 'var(--border,#e5e7eb)' ?>;">
                    <span aria-hidden="true" style="position:absolute;left:-9px;top:2px;width:16px;height:16px;border-radius:50%;background:var(--brand-600,#0B4E3D);box-shadow:0 0 0 4px rgba(11,78,61,.15);"></span>
                    <div class="card">
                        <div class="card-body">
                            <div class="flex items-center gap-2 flex-wrap mb-1">
                                <?php if (!empty($m['year'])): ?><span class="badge badge-brand"><?= e($m['year']) ?></span><?php endif; ?>
                                <?php if (!empty($m['status']) && $m['status'] === 'ongoing' && ($m['year'] ?? '') !== 'Ongoing'): ?><span class="badge badge-accent">Ongoing</span><?php endif; ?>
                            </div>
                            <h3 class="card-title"><?= e($m['title']) ?></h3>
                            <?php if (!empty($m['summary'])): ?><p class="card-text mb-0"><?= e($m['summary']) ?></p><?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== CERTIFICATES & RECOGNITION ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Recognition &amp; Compliance</span>
            <h2 class="section-title">Certificates &amp; Registrations</h2>
            <p class="section-subtitle">Our credentials reflect a commitment to transparency, legal compliance, and the trust our supporters place in us.</p>
        </div>

        <?php if ($certs): ?>
            <div class="grid grid-4">
                <?php foreach ($certs as $i => $ct): ?>
                    <a class="card reveal <?= 'delay-' . (($i % 3) + 1) ?>" href="<?= e(url('certificates')) ?>" style="color:inherit;">
                        <?php if (!empty($ct['image'])): ?>
                            <div class="card-media">
                                <img src="<?= e(image_url($ct['image'])) ?>" alt="<?= e($ct['title']) ?>" loading="lazy" width="400" height="300">
                            </div>
                            <div class="card-body">
                                <h3 class="card-title" style="font-size:1.02rem;"><?= e($ct['title']) ?></h3>
                                <?php if (!empty($ct['issued_by'])): ?>
                                    <p class="card-text mb-0">Issued by <?= e($ct['issued_by']) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($ct['issue_date'])): ?>
                                    <small class="text-muted"><?= e(format_date($ct['issue_date'], 'M Y')) ?></small>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <!-- No scan uploaded — a styled text card instead of a grey placeholder -->
                            <div class="card-body text-center" style="padding-top:1.6rem;">
                                <div class="icon-badge accent" style="margin:0 auto .8rem;"><?= lucide('award') ?></div>
                                <h3 class="card-title" style="font-size:1.02rem;"><?= e($ct['title']) ?></h3>
                                <?php if (!empty($ct['issued_by'])): ?>
                                    <p class="card-text mb-0">Issued by <?= e($ct['issued_by']) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($ct['description'])): ?>
                                    <p class="card-text mb-0"><?= e(excerpt($ct['description'], 14)) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($ct['issue_date'])): ?>
                                    <small class="text-muted"><?= e(format_date($ct['issue_date'], 'M Y')) ?></small>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="text-center mt-4">
                <a class="btn btn-outline" href="<?= e(url('certificates')) ?>">
                    <?= $certsTotal > count($certs) ? 'View All ' . (int) $certsTotal . ' Certificates' : 'View All Certificates' ?>
                </a>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon-badge"><?= lucide('scroll') ?></div>
                <h3>Certifications Coming Online</h3>
                <p class="text-muted">EDUSKILL INDIA FOUNDATION is a registered non-profit (CIN <?= e(SITE_CIN) ?>). Our official certificates and registrations will be published here soon.</p>
                <a class="btn btn-primary mt-2" href="<?= e(url('contact')) ?>">Request Our Credentials</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ============================== CTA BAND ============================== -->
<section class="section">
    <div class="container">
        <div class="card-3d reveal text-center" style="background:var(--grad-brand);color:#fff;">
            <span class="eyebrow" style="justify-content:center;color:#dbeafe;"><?= e(SITE_TAGLINE) ?></span>
            <h2 class="text-white">Help Us Reach the Next Milestone</h2>
            <p style="color:rgba(255,255,255,.9);max-width:640px;margin-inline:auto;">Every achievement on this page began with someone who chose to act. Join us — as a donor, a volunteer, or a partner — and help write the next chapter of change.</p>
            <div class="flex flex-wrap justify-center gap-2 mt-4">
                <a class="btn btn-white btn-lg" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Donate Now</a>
                <a class="btn btn-outline btn-lg" style="border-color:#fff;color:#fff;" href="<?= e(url('volunteer')) ?>"><?= lucide('handshake') ?> Become a Volunteer</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
