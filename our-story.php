<?php
/**
 * =============================================================================
 *  Our Story — the journey of EDUSKILL INDIA FOUNDATION, told through an
 *  intro, a premium milestone timeline, a founders' note and impact counters.
 *  Every section is DB-driven with strong, curated fallbacks so the page looks
 *  complete and polished before any content is seeded.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* ---- Data ------------------------------------------------------------- */
$siteName = get_setting('site_name', SITE_NAME);

/* Impact counters (shared with the rest of the site) */
$stats = get_all('achievements', ['status' => 1], 'sort_order ASC', 4);
if (!$stats) {
    $stats = [
        ['icon' => 'users',          'value' => 25000, 'suffix' => '+', 'prefix' => '', 'title' => 'Lives Impacted',    'description' => 'across Bihar and beyond'],
        ['icon' => 'home',           'value' => 60,    'suffix' => '+', 'prefix' => '', 'title' => 'Villages Reached',   'description' => 'with grassroots programs'],
        ['icon' => 'graduation-cap', 'value' => 120,   'suffix' => '+', 'prefix' => '', 'title' => 'Projects Delivered',  'description' => 'with full accountability'],
        ['icon' => 'handshake',      'value' => 800,   'suffix' => '+', 'prefix' => '', 'title' => 'Active Volunteers',   'description' => 'giving time and skills'],
    ];
}

/*
 * Milestones — DB-driven via the JSON setting `milestones`.
 * Each entry may use { "year", "event"|"title", "detail"|"text" }.
 * A strong six-item fallback keeps the timeline complete before seeding.
 */
$milestonesRaw = json_column(get_setting('milestones'));
$milestones    = [];
foreach ($milestonesRaw as $m) {
    if (!is_array($m)) {
        continue;
    }
    $year  = trim((string) ($m['year'] ?? ''));
    $title = trim((string) ($m['title'] ?? $m['event'] ?? ''));
    $text  = trim((string) ($m['text'] ?? $m['detail'] ?? $m['description'] ?? ''));
    if ($year === '' && $title === '') {
        continue;
    }
    $milestones[] = ['year' => $year, 'title' => $title, 'text' => $text];
}
if (!$milestones) {
    $milestones = [
        [
            'year'  => '2025',
            'title' => 'The Foundation Is Born',
        ],
        [
            'year'  => '2025',
            'title' => 'Our First Free Medical Camp',
            'text'  => 'Our inaugural health camp brings free check-ups, essential medicines and health awareness to a rural community on the outskirts of Patna — the first of many.',
        ],
        [
            'year'  => '2025',
            'title' => 'Education for All Takes Root',
            'text'  => 'We distribute books, uniforms and learning kits to underprivileged children, launching a programme that puts learning within every child\'s reach.',
        ],
        [
            'year'  => '2026',
            'title' => 'Empowering Women Through Skills',
            'text'  => 'Tailoring and vocational training centres open their doors, helping women build independent livelihoods through self-help groups and mentorship.',
        ],
        [
            'year'  => '2026',
            'title' => 'Clean Water & Sanitation Drive',
            'text'  => 'Safe drinking-water access and hygiene awareness reach several villages under our WASH initiative, protecting families from preventable disease.',
        ],
        [
            'year'  => '2026',
            'title' => 'A Growing Movement',
            'text'  => 'Our network of volunteers expands across Bihar, powering relief, education and healthcare in dozens of communities — proof that shared purpose creates lasting change.',
        ],
    ];
}

/* Founders — populated from the admin panel; empty by default. */
$founders = [];

seo_set([
    'title'       => 'Our Story',
    'description' => get_setting('our_story_meta_description', 'The journey of EDUSKILL INDIA FOUNDATION — from a small group of volunteers in Patna to a movement for education, healthcare and dignity across Bihar. Explore our milestones, founders and impact.'),
    'page_key'    => 'our-story',
    'type'        => 'website',
]);

$page_hero = [
    'title'      => 'Our Story',
    'subtitle'   => 'From a spark of compassion in Patna to a movement for change across Bihar — this is how our journey unfolds.',
    'breadcrumb' => [
        ['label' => 'About', 'url' => url('about')],
        ['label' => 'Our Story'],
    ],
];

include __DIR__ . '/includes/header.php';
?>

<style>
/* ============================================================================
   OUR STORY — page-specific premium polish (forest green + teal + gold).
   Scoped to .our-story-page so shared CSS is never affected.
   ========================================================================= */
.our-story-page{
    --os-green:#0B4E3D; --os-green-700:#0B3A2E; --os-teal:#174D3D; --os-gold:#F15A24;
    --os-grad:linear-gradient(135deg,#0B4E3D 0%,#174D3D 100%);
}

/* ---- Unique on-brand hero (only this page's page-hero is restyled) ------ */
.page-hero{ background:linear-gradient(135deg,#0B3A2E 0%,#0B4E3D 45%,#174D3D 100%); }
.page-hero::before{
    content:""; position:absolute; inset:0; opacity:.5; pointer-events:none;
    background:
        radial-gradient(520px circle at 12% 120%, rgba(241,90,36,.28), transparent 60%),
        radial-gradient(circle, rgba(255,255,255,.10) 1px, transparent 1.4px);
    background-size:auto, 26px 26px;
}
.page-hero h1{
    position:relative; display:inline-block; font-weight:800; letter-spacing:-.02em;
}
.page-hero h1::after{
    content:""; display:block; width:74px; height:4px; margin-top:.7rem; border-radius:4px;
    background:linear-gradient(90deg,var(--os-gold),#f4d06a);
}

/* ---- Accent overrides so the page reads forest-green, not blue ---------- */
.our-story-page .eyebrow{ color:var(--os-green); }
.our-story-page .eyebrow::before{ background:var(--os-grad); }
.our-story-page .text-grad-multi{
    background:var(--os-grad); -webkit-background-clip:text; background-clip:text; color:transparent;
}
.our-story-page .icon-badge{
    background:var(--os-grad); box-shadow:0 10px 24px rgba(11,78,61,.28);
}
.our-story-page .icon-badge.accent{
    background:linear-gradient(135deg,var(--os-gold) 0%,#eab308 100%);
    box-shadow:0 10px 24px rgba(241,90,36,.32);
}
.our-story-page .badge-brand{ background:rgba(11,78,61,.12); color:var(--os-green); }
.our-story-page .btn-primary{
    background:var(--os-grad); box-shadow:0 12px 30px rgba(11,78,61,.3);
}
.our-story-page .btn-outline{ border-color:var(--os-green); color:var(--os-green); }
.our-story-page .btn-outline:hover{ background:var(--os-green); color:#fff; }
.our-story-page .btn-3d.green{ background:var(--os-grad); box-shadow:0 6px 0 #0f3d24, 0 14px 28px rgba(23,77,61,.4); }
.our-story-page .btn-3d.green:hover{ box-shadow:0 9px 0 #0f3d24, 0 20px 36px rgba(23,77,61,.5); }
.our-story-page .chip:hover{ border-color:var(--os-teal); color:var(--os-teal); }

/* ---- Ornamental section divider ---------------------------------------- */
.our-story-page .os-ornament{
    display:flex; align-items:center; justify-content:center; gap:1rem;
    max-width:230px; margin:0 auto 1.4rem;
}
.our-story-page .os-ornament::before,
.our-story-page .os-ornament::after{
    content:""; flex:1; height:1px;
    background:linear-gradient(90deg,transparent,rgba(23,77,61,.55));
}
.our-story-page .os-ornament::after{
    background:linear-gradient(90deg,rgba(23,77,61,.55),transparent);
}
.our-story-page .os-om{
    width:40px; height:40px; border-radius:50%; display:grid; place-items:center;
    background:rgba(23,77,61,.10); border:1px solid rgba(23,77,61,.28);
    color:var(--os-teal); box-shadow:0 6px 16px rgba(23,77,61,.16);
    animation:osPulse 4.5s ease-in-out infinite;
}
.our-story-page .os-om svg.lucide{ width:1.15rem; height:1.15rem; }
@keyframes osPulse{ 0%,100%{ transform:translateY(0); } 50%{ transform:translateY(-3px); } }
.our-story-page .section-brand .os-ornament::before{ background:linear-gradient(90deg,transparent,rgba(255,255,255,.55)); }
.our-story-page .section-brand .os-ornament::after{ background:linear-gradient(90deg,rgba(255,255,255,.55),transparent); }
.our-story-page .section-brand .os-om{ background:rgba(255,255,255,.16); border-color:rgba(255,255,255,.35); color:#fff; }

/* ---- Intro info card ---------------------------------------------------- */
.our-story-page .os-info-card{ position:relative; overflow:hidden; }
.our-story-page .os-info-card::before{
    content:""; position:absolute; inset:0 0 auto 0; height:5px;
    background:linear-gradient(90deg,var(--os-green),var(--os-teal),var(--os-gold));
}
.our-story-page .os-info-card .list-check li::before{
    background:rgba(11,78,61,.14); color:var(--os-green); content:"\2713";
}

/* ---- Timeline: greener line, gold-ringed nodes, hover lift -------------- */
.our-story-page .timeline::before{ background:linear-gradient(var(--os-green),var(--os-teal)); }
.our-story-page .timeline-item::before{
    border-color:var(--os-green); box-shadow:0 0 0 4px rgba(11,78,61,.15);
    transition:transform .25s ease, box-shadow .25s ease, background .25s ease;
}
.our-story-page .timeline-item{
    padding:1.1rem 1.1rem 1.1rem 1.6rem; margin-bottom:1.1rem; border-radius:16px;
    background:var(--surface); border:1px solid var(--border);
    box-shadow:0 2px 10px rgba(11,78,61,.05);
    transition:transform .28s ease, box-shadow .28s ease, border-color .28s ease;
}
.our-story-page .timeline-item:hover{
    transform:translateX(6px); border-color:rgba(23,77,61,.35);
    box-shadow:0 16px 34px rgba(11,78,61,.12);
}
.our-story-page .timeline-item:hover::before{
    background:var(--os-teal); box-shadow:0 0 0 5px rgba(23,77,61,.2);
}
.our-story-page .timeline-item .tl-date{
    display:inline-flex; align-items:center; gap:.4rem; color:var(--os-teal);
    letter-spacing:.04em; text-transform:uppercase;
}
.our-story-page .tl-date svg.lucide{ width:.95rem; height:.95rem; }

/* ---- Founders quote block ---------------------------------------------- */
.our-story-page .os-quote{ position:relative; overflow:hidden; }
.our-story-page .os-quote-mark{
    width:54px; height:54px; border-radius:16px; display:grid; place-items:center;
    background:var(--os-grad); color:#fff; box-shadow:0 12px 26px rgba(11,78,61,.28);
    margin-bottom:1.1rem;
}
.our-story-page .os-quote-mark svg.lucide{ width:1.6rem; height:1.6rem; }
.our-story-page .os-quote::after{
    content:""; position:absolute; right:-40px; bottom:-40px; width:150px; height:150px;
    border-radius:50%; background:var(--os-grad); opacity:.08;
}

/* ---- Founder cards + stat cards: soft hover micro-interaction ---------- */
.our-story-page .card-3d{ transition:transform .3s ease, box-shadow .3s ease; }
.our-story-page .stat-premium{
    text-align:center; transition:transform .3s ease, box-shadow .3s ease, border-color .3s ease;
}
.our-story-page .stat-premium::before{ background:var(--os-grad); opacity:.12; }
.our-story-page .stat-premium .icon-badge{ margin:0 auto 1rem; }
.our-story-page .stat-premium:hover{
    transform:translateY(-8px); border-color:rgba(23,77,61,.35);
    box-shadow:0 22px 44px rgba(11,78,61,.14);
}
.our-story-page .stat-premium .counter-value{ color:var(--os-green); }

/* ---- Responsive niceties ----------------------------------------------- */
@media (max-width:900px){
    .our-story-page .grid-sidebar aside .glass-card{ position:static !important; }
}
@media (max-width:560px){
    .our-story-page .os-quote-actions{ flex-direction:column; }
    .our-story-page .os-quote-actions > *{ width:100%; }
}
</style>

<div class="our-story-page">

<!-- ============================== INTRO ============================== -->
<section class="section" style="position:relative;overflow:hidden;">
    <span class="blob b1" style="top:-90px;left:-70px;"></span>
    <span class="blob b3" style="bottom:-110px;right:-80px;"></span>
    <div class="container grid grid-2 items-center gap-4" style="position:relative;z-index:1;">
        <div class="reveal">
            <span class="eyebrow">How It All Began</span>
            <h2 class="section-title">A story of <span class="text-grad-multi">hope, dignity and change</span></h2>
            <div class="prose text-muted">
                <p><?= e(get_setting('our_story_intro', 'EDUSKILL INDIA FOUNDATION began with a simple conviction — that every person, regardless of where they are born, deserves the chance to live with dignity and reach their full potential. What started as a small group of volunteers responding to urgent needs in and around Patna has grown into a structured movement serving communities across Bihar.')) ?></p>
                <p><?= e(get_setting('our_story_intro_2', 'We listen before we act. From free medical camps and learning kits for children, to skill training for women and rapid disaster relief, every programme is shaped by the people we serve — designed not just to help today, but to build the capability that carries a community forward for years to come.')) ?></p>
            </div>
            <div class="flex flex-wrap gap-2 mt-4">
                <a class="btn btn-3d green" href="<?= e(url('programs')) ?>"><?= lucide('compass') ?> Explore Our Programs</a>
                <a class="btn btn-outline" href="<?= e(url('about')) ?>"><?= lucide('landmark') ?> About the Foundation</a>
            </div>
        </div>
        <div class="glass-card os-info-card tilt reveal delay-1" data-tilt="8" style="padding:2rem;">
            <span class="eyebrow">Registered Non-Profit</span>
            <h3 class="card-title mb-1"><?= e($siteName) ?></h3>
            <p class="card-text mb-3"><?= e(SITE_TAGLINE) ?></p>
            <div class="divider"></div>
            <ul class="list-check mt-3">
                <li><strong>Founded:</strong> 2025, in Patna, Bihar</li>
                <li><strong>CIN:</strong> <?= e(get_setting('company_cin', SITE_CIN)) ?></li>
                <li><strong>Mission:</strong> Education, healthcare &amp; dignity for all</li>
            </ul>
            <a class="btn btn-primary btn-block mt-4" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Support Our Journey</a>
        </div>
    </div>
</section>

<div class="wave-divider" aria-hidden="true">
    <svg viewBox="0 0 1440 60" preserveAspectRatio="none"><path fill="var(--surface-soft, #f1f5f9)" d="M0,32L60,29.3C120,27,240,21,360,26.7C480,32,600,48,720,48C840,48,960,32,1080,24C1200,16,1320,16,1380,16L1440,16L1440,60L0,60Z"></path></svg>
</div>

<!-- ============================== MILESTONE TIMELINE ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="os-ornament reveal" aria-hidden="true"><span class="os-om"><?= lucide('sprout') ?></span></div>
        <div class="section-head">
            <span class="eyebrow">Our Journey So Far</span>
            <h2 class="section-title">Milestones That Shaped Us</h2>
            <p class="section-subtitle">Every camp, every classroom and every clean-water well is a chapter in a story still being written.</p>
        </div>
        <div class="grid grid-sidebar gap-4 items-start">
            <div class="timeline">
                <?php foreach ($milestones as $i => $m): ?>
                    <div class="timeline-item reveal <?= 'delay-' . (($i % 3) + 1) ?>">
                        <?php if ($m['year'] !== ''): ?>
                            <span class="tl-date"><?= lucide('calendar-days') ?><?= e($m['year']) ?></span>
                        <?php endif; ?>
                        <?php if (($m['title'] ?? '') !== ''): ?>
                            <h3 class="card-title mb-1 mt-1"><?= e($m['title']) ?></h3>
                        <?php endif; ?>
                        <?php if (($m['text'] ?? '') !== ''): ?>
                            <p class="card-text mb-0"><?= e($m['text']) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <aside class="reveal delay-1">
                <div class="glass-card sticky-aside" style="padding:1.8rem;position:sticky;top:100px;">
                    <div class="icon-badge" style="margin:0 0 1rem;"><?= lucide('book-open') ?></div>
                    <h3 class="card-title mb-1">Written by the People We Serve</h3>
                    <p class="card-text mb-3">Our milestones aren't just dates — they are lives changed, communities strengthened and promises kept. Here is how the journey has unfolded.</p>
                    <div class="flex gap-2 flex-wrap">
                        <span class="chip"><?= lucide('flag') ?> Since 2025</span>
                        <span class="chip"><?= lucide('map-pin') ?> Patna, Bihar</span>
                        <span class="chip"><?= lucide('milestone') ?> <?= count($milestones) ?> Milestones</span>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</section>

<!-- ============================== FOUNDERS' NOTE ============================== -->
<section class="section">
    <div class="container">
        <div class="os-ornament reveal" aria-hidden="true"><span class="os-om"><?= lucide('heart-handshake') ?></span></div>
        <div class="section-head">
            <span class="eyebrow">A Note From Our Founders</span>
            <h2 class="section-title">Why We Started</h2>
            <p class="section-subtitle">The Foundation was born from lived experience and a shared belief that change is most powerful when it is built alongside people, never handed to them.</p>
        </div>
        <div class="glass-card os-quote reveal" style="max-width:900px;margin-inline:auto;padding:2.5rem;position:relative;">
            <div class="os-quote-mark"><?= lucide('quote') ?></div>
            <p style="font-size:1.2rem;line-height:1.7;color:var(--text-soft);position:relative;z-index:1;">
                When we started EDUSKILL INDIA FOUNDATION, we did not set out to run programmes — we set out to restore dignity. We had seen families held back not by a lack of talent, but by a lack of opportunity. Our promise is simple: to listen first, to act with transparency, and to stay until the change we make can stand on its own.
            </p>
            <div class="flex flex-wrap gap-3 mt-4" style="position:relative;z-index:1;">
                <?php foreach ($founders as $f): ?>
                    <div class="flex items-center gap-2" style="flex:1 1 260px;">
                        <div class="icon-badge" style="flex:0 0 auto;"><?= lucide($f['icon']) ?></div>
                        <div>
                            <strong><?= e($f['name']) ?></strong><br>
                            <small class="text-muted"><?= e($f['designation']) ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="grid grid-2 gap-4 mt-4">
            <?php foreach ($founders as $i => $f): ?>
                <div class="card-3d reveal <?= 'delay-' . (($i % 2) + 1) ?>">
                    <div class="icon-badge <?= $i === 1 ? 'accent' : '' ?>"><?= lucide($f['icon']) ?></div>
                    <h3 class="card-title mb-0"><?= e($f['name']) ?></h3>
                    <span class="badge badge-brand mt-1"><?= e($f['designation']) ?></span>
                    <p class="card-text mt-2"><?= e($f['bio']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-4 reveal">
            <a class="btn btn-outline" href="<?= e(url('team')) ?>"><?= lucide('users') ?> Meet the Full Team</a>
        </div>
    </div>
</section>

<!-- ============================== IMPACT COUNTERS ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="os-ornament reveal" aria-hidden="true"><span class="os-om"><?= lucide('sparkles') ?></span></div>
        <div class="section-head">
            <span class="eyebrow">The Difference So Far</span>
            <h2 class="section-title">Our Story, in Numbers</h2>
            <p class="section-subtitle">Behind every figure is a person whose life changed — and a community that grew stronger.</p>
        </div>
        <div class="grid grid-4">
            <?php foreach ($stats as $i => $s): ?>
                <?php $sicon = trim((string) ($s['icon'] ?? '')); if ($sicon === '') { $sicon = 'star'; } ?>
                <div class="stat-premium reveal <?= 'delay-' . (($i % 4) + 1) ?>">
                    <div class="icon-badge" style="position:relative;z-index:1;"><?= lucide($sicon) ?></div>
                    <div class="counter-value" style="font-size:2.4rem;font-weight:800;position:relative;z-index:1;">
                        <?= e($s['prefix'] ?? '') ?><span data-counter="<?= (int) ($s['value'] ?? 0) ?>">0</span><?= e($s['suffix'] ?? '') ?>
                    </div>
                    <div class="counter-label" style="font-weight:700;position:relative;z-index:1;"><?= e($s['title']) ?></div>
                    <?php if (!empty($s['description'])): ?>
                        <small class="text-muted" style="position:relative;z-index:1;"><?= e($s['description']) ?></small>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== CTA ============================== -->
<section class="section section-brand">
    <div class="container">
        <div class="os-ornament reveal" aria-hidden="true"><span class="os-om"><?= lucide('flame') ?></span></div>
        <div class="section-head">
            <?php /* The inline `color:#a7f3d0` (a pale mint that is not in the
                     palette) is gone — an inline declaration was the only thing
                     outranking design-system.css's `.section-brand .eyebrow`,
                     which supplies the highlight yellow at 7.95:1 on the brand
                     field. Centring stays inline because it is layout, not
                     colour. */ ?>
            <span class="eyebrow" style="justify-content:center;">Be Part Of What Comes Next</span>
            <h2 class="section-title text-white">Help Us Write the Next Chapter</h2>
            <p class="section-subtitle" style="color:rgba(255,255,255,.9);">Our story is far from over. With your support, the next milestone could reach one more child, one more family, one more village.</p>
        </div>
        <div class="flex flex-wrap justify-center gap-3 os-quote-actions">
            <a class="btn btn-white btn-lg" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Donate Now</a>
            <a class="btn btn-outline btn-lg" style="border-color:#fff;color:#fff;" href="<?= e(url('volunteer')) ?>"><?= lucide('handshake') ?> Become a Volunteer</a>
        </div>
    </div>
</section>

</div><!-- /.our-story-page -->

<?php include __DIR__ . '/includes/footer.php'; ?>
