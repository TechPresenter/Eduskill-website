<?php
/**
 * =============================================================================
 *  Mission & Vision — the purpose, values, objectives and working approach of
 *  EDUSKILL INDIA FOUNDATION. Every block is DB-driven (settings table) with
 *  strong NGO-appropriate fallbacks so the page looks complete before seeding.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* ---- Helpers ----------------------------------------------------------- */
/**
 * Read a JSON-encoded list setting, decode it, and fall back to a curated
 * default when the key is missing or holds invalid/empty JSON.
 *
 * @param string $key      settings key_name
 * @param array  $fallback default structure returned when nothing usable
 * @return array
 */
$list_setting = static function (string $key, array $fallback): array {
    $raw = get_setting($key);
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_column($raw);
        if (is_array($decoded) && $decoded) {
            return $decoded;
        }
    }
    return $fallback;
};

/* ---- Copy (settings with fallbacks) ------------------------------------ */
$mission_short = get_setting('mission_short', 'To empower underserved communities across Bihar by ensuring access to quality education, healthcare, skill development and sustainable livelihoods — restoring dignity and unlocking opportunity for every individual we serve.');
$mission_long  = get_setting('mission_long', 'EDUSKILL INDIA FOUNDATION exists to stand beside the people history has left behind. We work hand-in-hand with children, women, youth and families in Bihar\'s most vulnerable villages and urban settlements, turning compassion into concrete, measurable change. Through community-led programs — free schooling and scholarships, medical camps, vocational training, clean water and emergency relief — we help people move from dependence to self-reliance. Every intervention is transparent, accountable and designed with the community, not merely for it, so that the change we create outlasts our presence.');

$vision_short  = get_setting('vision_short', 'An equitable, self-reliant society where every individual — regardless of caste, gender or income — can live with dignity, opportunity and hope.');
$vision_long   = get_setting('vision_long', 'We envision a Bihar where no child is denied an education, no family is pushed into poverty by illness, and no woman is held back from her potential. A future built on empowered communities that lead their own development — resilient, inclusive and just. We see hope replacing hardship, opportunity replacing exclusion, and generations rising to become the changemakers of tomorrow.');

/* Core values — grid of card-3d with icon-badge (icons are Lucide names) */
$core_values = $list_setting('core_values', [
    ['icon' => 'heart',        'title' => 'Compassion',      'text' => 'We lead with empathy, meeting every person we serve with kindness, respect and genuine care.'],
    ['icon' => 'handshake',    'title' => 'Integrity',       'text' => 'We are honest and transparent in every action — every rupee and every promise is accounted for.'],
    ['icon' => 'scale',        'title' => 'Equality',        'text' => 'We serve without discrimination, championing equal dignity and opportunity for all.'],
    ['icon' => 'home',         'title' => 'Community First', 'text' => 'We build with communities, not for them — local voices shape every program we run.'],
    ['icon' => 'sprout',       'title' => 'Sustainability',  'text' => 'We design lasting solutions that empower people to thrive long after our work is done.'],
    ['icon' => 'bar-chart-3',  'title' => 'Accountability',  'text' => 'We measure our impact rigorously and report it openly to the communities and donors we answer to.'],
]);

/* Our Objectives — list-check */
$objectives = $list_setting('foundation_objectives', [
    'Provide free, quality education, scholarships and learning resources to underprivileged children.',
    'Deliver accessible healthcare through medical camps, awareness drives and preventive care in rural areas.',
    'Empower women and youth with vocational skills and sustainable livelihood opportunities.',
    'Ensure access to safe drinking water, sanitation and hygiene in underserved communities.',
    'Respond rapidly to disasters with relief, food distribution and long-term rehabilitation.',
    'Promote environmental stewardship, cleanliness and community-led development initiatives.',
    'Advocate for the rights, dignity and inclusion of marginalised and vulnerable groups.',
    'Build a transparent, accountable organisation that earns the trust of every stakeholder.',
]);

/* Our Approach — numbered steps */
$approach = $list_setting('our_approach', [
    ['title' => 'Listen & Assess',      'text' => 'We begin in the community — mapping real needs alongside local families, leaders and partners before a single program is designed.', 'color' => '#063566'],
    ['title' => 'Design & Mobilise',    'text' => 'We co-create focused, evidence-based interventions and rally volunteers, resources and partners around a shared plan.',            'color' => '#084881'],
    ['title' => 'Implement & Empower',  'text' => 'We deliver on the ground with dedicated teams, building local ownership and skills so change takes root from within.',             'color' => '#E67B1D'],
    ['title' => 'Measure & Sustain',    'text' => 'We track outcomes transparently, learn from every project, and hand over lasting systems that outlive our involvement.',           'color' => '#58A42F'],
]);

/* ---- SEO --------------------------------------------------------------- */
seo_set([
    'title'       => 'Mission & Vision',
    'description' => 'Discover the mission, vision, core values, objectives and working approach of EDUSKILL INDIA FOUNDATION — empowering communities across Bihar through education, healthcare, skill development and relief.',
    'page_key'    => 'mission-vision',
    'type'        => 'website',
]);

/* ---- Hero banner ------------------------------------------------------- */
$page_hero = [
    'title'      => 'Mission & Vision',
    'subtitle'   => 'The purpose that drives us and the future we are building — together with the communities we serve.',
    'breadcrumb' => [['label' => 'Mission & Vision']],
];

include __DIR__ . '/includes/header.php';
?>

<style>
/* =============================================================================
   Mission & Vision — page-specific premium polish (scoped, no shared-CSS edits)
   ========================================================================== */
.mv-page{ --mv-gold:#E67B1D; }

/* ---- Unique hero treatment (only loads on this page) --------------------- */
.page-hero{
    background:
        radial-gradient(820px circle at 12% 130%, rgba(230,123,29,.20), transparent 55%),
        radial-gradient(720px circle at 88% -35%, rgba(8,72,129,.30), transparent 55%),
        var(--grad-brand);
    padding-block: clamp(3.6rem,7vw,6.2rem) clamp(3rem,6vw,5rem);
}
.page-hero::before{
    content:""; position:absolute; inset:0; z-index:0;
    background-image:radial-gradient(rgba(255,255,255,.11) 1.1px, transparent 1.1px);
    background-size:24px 24px;
    -webkit-mask-image:linear-gradient(180deg,#000 0%,transparent 85%);
    mask-image:linear-gradient(180deg,#000 0%,transparent 85%);
    opacity:.7;
}
.page-hero h1{ font-size:clamp(2.15rem,4.8vw,3.35rem); font-weight:800; letter-spacing:-.025em; }
.page-hero .lead{ font-size:clamp(1.02rem,1.6vw,1.2rem); line-height:1.65; }
/* A slim gold underline flourish beneath the hero heading */
.page-hero h1::after{
    content:""; display:block; width:74px; height:4px; margin-top:1rem;
    background:linear-gradient(90deg,var(--mv-gold),rgba(230,123,29,0));
    border-radius:4px;
}

/* ---- Shared section rhythm ---------------------------------------------- */
.mv-page .section{ position:relative; }
.mv-page .section-title{ font-size:clamp(1.7rem,3.2vw,2.4rem); }
.mv-page .lead{ line-height:1.6; }
.mv-page .section-head{ margin-bottom:2.6rem; }

/* Decorative in-title mark (icon between two gradient rules) */
.mv-mark{
    display:flex; align-items:center; justify-content:center; gap:.85rem;
    max-width:230px; margin:0 auto 1.15rem;
}
.mv-mark::before,.mv-mark::after{
    content:""; height:2px; flex:1; border-radius:2px;
    background:linear-gradient(90deg,transparent,var(--border));
}
.mv-mark::after{ background:linear-gradient(90deg,var(--border),transparent); }
.mv-mark .mv-mark-ico{
    display:grid; place-items:center; width:38px; height:38px; border-radius:12px;
    color:#fff; background:var(--grad-brand); box-shadow:var(--shadow-brand);
}
.mv-mark.gold .mv-mark-ico{ background:linear-gradient(135deg,var(--mv-gold),#b8860b); box-shadow:0 10px 22px rgba(230,123,29,.34); }
.mv-mark .mv-mark-ico svg.lucide{ width:19px; height:19px; }

/* ---- Feature (mission / vision) gradient cards -------------------------- */
.mv-feature-card{ position:relative; overflow:hidden; padding:2.4rem; }
.mv-feature-card::after{
    content:""; position:absolute; right:-60px; top:-60px; width:200px; height:200px;
    background:radial-gradient(circle,rgba(255,255,255,.16),transparent 70%);
    pointer-events:none;
}
.mv-feature-card .icon-badge{ width:66px; height:66px; }
.mv-feature-card .icon-badge svg.lucide{ width:30px; height:30px; }
.mv-feature-card .card-title{ font-size:1.5rem; line-height:1.25; }
.mv-quote{ font-size:1.12rem; line-height:1.6; font-style:italic; position:relative; }

/* Small stat strip beneath the mission text */
.mv-pillars{ display:flex; flex-wrap:wrap; gap:.6rem; margin-top:1.6rem; }
.mv-pillars .chip{ background:var(--surface); font-weight:600; }
.mv-pillars .chip svg.lucide{ color:var(--brand-600); width:16px; height:16px; }

/* ---- Core values grid --------------------------------------------------- */
.mv-values .grid{ gap:1.6rem; }
.mv-value-card{ position:relative; overflow:hidden; }
.mv-value-card::after{
    content:""; position:absolute; left:0; right:0; top:0; height:3px;
    background:var(--grad-brand); transform:scaleX(0); transform-origin:left;
    transition:transform .45s ease;
}
.mv-value-card:hover::after{ transform:scaleX(1); }
.mv-value-card .icon-badge{ transition:transform .45s ease; }
.mv-value-card:hover .icon-badge{ transform:translateY(-3px) rotate(-6deg); }
.mv-value-card:nth-child(3n-1) .icon-badge{ background:var(--grad-accent); box-shadow:0 10px 24px rgba(230,123,29,.3); }
.mv-value-card:nth-child(3n-1)::after{ background:var(--grad-accent); }
.mv-value-card .icon-badge svg.lucide{ width:28px; height:28px; }
.mv-value-card .card-title{ font-size:1.2rem; }

/* ---- Objectives panel --------------------------------------------------- */
.mv-obj-card{ position:relative; overflow:hidden; padding:0; }
.mv-obj-card .card-body{ padding:2.4rem; }
.mv-obj-card::before{
    content:""; position:absolute; left:0; top:0; bottom:0; width:5px;
    background:var(--grad-brand);
}
.mv-obj-head{
    display:flex; align-items:center; gap:.9rem; margin-bottom:1.6rem;
    padding-bottom:1.3rem; border-bottom:1px dashed var(--border);
}
.mv-obj-head .icon-badge{ width:48px; height:48px; border-radius:13px; flex-shrink:0; }
.mv-obj-head .icon-badge svg.lucide{ width:23px; height:23px; }
.mv-obj-head h3{ margin:0; font-size:1.15rem; }
.mv-obj-head p{ margin:.15rem 0 0; font-size:.9rem; color:var(--muted); }
.mv-obj-card .list-check li{ line-height:1.55; }

/* ---- Approach steps with connector ------------------------------------- */
.mv-approach .grid{ position:relative; gap:1.6rem; }
.mv-step{ position:relative; text-align:left; }
.mv-step .icon-badge{
    font-weight:800; font-size:1.05rem; letter-spacing:.02em;
    position:relative; z-index:1;
}
.mv-step .mv-step-k{
    display:inline-flex; align-items:center; gap:.4rem; margin-bottom:.55rem;
    font-size:.72rem; font-weight:800; letter-spacing:.12em; text-transform:uppercase;
    color:var(--brand-600);
}
.mv-step .card-title{ font-size:1.18rem; }
/* horizontal connector line across the four steps on desktop */
@media (min-width:993px){
    .mv-approach .grid::before{
        content:""; position:absolute; top:52px; left:12%; right:12%; height:2px;
        background:linear-gradient(90deg,transparent,var(--border) 12%,var(--border) 88%,transparent);
        z-index:0;
    }
}

/* ---- CTA ---------------------------------------------------------------- */
.mv-cta-card{ position:relative; overflow:hidden; padding:clamp(2.4rem,4vw,3.4rem); }
.mv-cta-card::after{
    content:""; position:absolute; inset:0; pointer-events:none;
    background:
        radial-gradient(420px circle at 85% 120%, rgba(230,123,29,.28), transparent 60%),
        radial-gradient(420px circle at 10% -20%, rgba(255,255,255,.16), transparent 60%);
}
.mv-cta-card > *{ position:relative; z-index:1; }
.mv-cta-card h2{ font-size:clamp(1.7rem,3.4vw,2.35rem); }

/* ---- Responsive tuning -------------------------------------------------- */
@media (max-width:992px){
    .mv-page .grid.grid-2.items-center{ gap:2.2rem; }
    /* keep the visual card above the copy on stacked mission/vision blocks */
    .mv-vision .mv-order-first{ order:-1; }
}
@media (max-width:640px){
    .mv-feature-card{ padding:1.8rem; }
    .mv-obj-card .card-body{ padding:1.6rem; }
    .mv-mark{ max-width:180px; }
}
</style>

<div class="mv-page">

<!-- ============================== MISSION ============================== -->
<section class="section mv-mission">
    <div class="container grid grid-2 items-center gap-4">
        <div class="reveal">
            <span class="eyebrow">Our Purpose</span>
            <h2 class="section-title">Our Mission</h2>
            <p class="lead"><?= e($mission_short) ?></p>
            <p class="text-muted"><?= e($mission_long) ?></p>
            <ul class="list-check mt-3">
                <li>Community-led programs with measurable, lasting impact</li>
                <li>Dignity, compassion and accountability in everything we do</li>
                <li>Every rupee tracked — from donation to delivery</li>
            </ul>
            <div class="mv-pillars">
                <span class="chip"><?= lucide('graduation-cap') ?> Education</span>
                <span class="chip"><?= lucide('stethoscope') ?> Healthcare</span>
                <span class="chip"><?= lucide('briefcase') ?> Livelihoods</span>
                <span class="chip"><?= lucide('droplet') ?> Clean Water</span>
            </div>
            <a class="btn btn-primary mt-4" href="<?= e(url('programs')) ?>"><?= lucide('compass') ?> Explore Our Programs</a>
        </div>
        <div class="reveal delay-1">
            <div class="card-3d mv-feature-card" style="background:var(--grad-brand);color:#fff;">
                <div class="icon-badge" style="background:rgba(255,255,255,.18);"><?= lucide('target') ?></div>
                <h3 class="card-title text-white">Turning Compassion Into Change</h3>
                <p class="mv-quote" style="color:rgba(255,255,255,.94);">“We do not simply give aid — we build the capacity of communities to shape their own future with dignity and hope.”</p>
                <p class="mt-2" style="color:rgba(255,255,255,.9);font-weight:600;">— <?= e(SITE_NAME) ?></p>
            </div>
        </div>
    </div>
</section>

<!-- ============================== VISION ============================== -->
<section class="section section-soft mv-vision">
    <div class="container grid grid-2 items-center gap-4">
        <div class="reveal mv-order-first">
            <div class="card-3d mv-feature-card" style="background:var(--grad-accent);color:#fff;">
                <div class="icon-badge accent" style="background:rgba(255,255,255,.2);"><?= lucide('sparkles') ?></div>
                <h3 class="card-title text-white">A Brighter Tomorrow for Bihar</h3>
                <p style="color:rgba(255,255,255,.95);line-height:1.6;">A future where empowered, self-reliant communities lead their own development — inclusive, resilient and just.</p>
                <ul class="list-check mt-3">
                    <li style="color:#fff;">Education for every child</li>
                    <li style="color:#fff;">Healthcare within every family's reach</li>
                    <li style="color:#fff;">Opportunity for every woman and youth</li>
                </ul>
            </div>
        </div>
        <div class="reveal delay-1">
            <span class="eyebrow">Looking Ahead</span>
            <h2 class="section-title">Our Vision</h2>
            <p class="lead"><?= e($vision_short) ?></p>
            <p class="text-muted"><?= e($vision_long) ?></p>
            <a class="btn btn-accent mt-4" href="<?= e(url('about')) ?>"><?= lucide('arrow-right') ?> Learn More About Us</a>
        </div>
    </div>
</section>

<!-- ============================== CORE VALUES ============================== -->
<section class="section mv-values">
    <div class="container">
        <div class="section-head">
            <div class="mv-mark" aria-hidden="true"><span class="mv-mark-ico"><?= lucide('heart-handshake') ?></span></div>
            <span class="eyebrow" style="justify-content:center;">What Guides Us</span>
            <h2 class="section-title">Our Core Values</h2>
            <p class="section-subtitle">The principles at the heart of every decision we make and every community we serve.</p>
        </div>
        <div class="grid grid-3">
            <?php foreach ($core_values as $i => $v): ?>
                <div class="card-3d mv-value-card reveal <?= 'delay-' . (($i % 3) + 1) ?>">
                    <div class="icon-badge"><?= lucide($v['icon'] ?? 'star') ?></div>
                    <h3 class="card-title"><?= e($v['title'] ?? '') ?></h3>
                    <p class="card-text"><?= e($v['text'] ?? '') ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== OBJECTIVES ============================== -->
<section class="section section-alt mv-objectives">
    <div class="container">
        <div class="section-head">
            <div class="mv-mark gold" aria-hidden="true"><span class="mv-mark-ico"><?= lucide('target') ?></span></div>
            <span class="eyebrow" style="justify-content:center;">Our Commitments</span>
            <h2 class="section-title">Our Objectives</h2>
            <p class="section-subtitle">Clear, actionable goals that translate our mission into real work on the ground.</p>
        </div>
        <div class="card mv-obj-card reveal" style="max-width:980px;margin-inline:auto;">
            <div class="card-body">
                <div class="mv-obj-head">
                    <div class="icon-badge"><?= lucide('list-checks') ?></div>
                    <div>
                        <h3>Eight promises we work to keep</h3>
                        <p>Each objective maps directly to a live program on the ground.</p>
                    </div>
                </div>
                <div class="grid grid-2 gap-4">
                    <?php
                    $obj_count = count($objectives);
                    $half      = (int) ceil($obj_count / 2);
                    $columns   = [array_slice($objectives, 0, $half), array_slice($objectives, $half)];
                    foreach ($columns as $col): if (!$col) continue; ?>
                        <ul class="list-check">
                            <?php foreach ($col as $obj): ?>
                                <li><?= e(is_array($obj) ? ($obj['text'] ?? '') : $obj) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================== OUR APPROACH ============================== -->
<section class="section mv-approach">
    <div class="container">
        <div class="section-head">
            <div class="mv-mark" aria-hidden="true"><span class="mv-mark-ico"><?= lucide('workflow') ?></span></div>
            <span class="eyebrow" style="justify-content:center;">How We Work</span>
            <h2 class="section-title">Our Approach</h2>
            <p class="section-subtitle">A simple, disciplined cycle that turns intent into impact — and impact into lasting change.</p>
        </div>
        <div class="grid grid-4">
            <?php foreach ($approach as $i => $step): $num = $i + 1; ?>
                <div class="card-3d mv-step reveal <?= 'delay-' . (($i % 4) + 1) ?>">
                    <div class="icon-badge" style="background:linear-gradient(135deg,<?= e($step['color'] ?? '#063566') ?>,#084881);"><?= e(str_pad((string) $num, 2, '0', STR_PAD_LEFT)) ?></div>
                    <span class="mv-step-k"><?= lucide('arrow-right') ?> Step <?= e((string) $num) ?></span>
                    <h3 class="card-title"><?= e($step['title'] ?? '') ?></h3>
                    <p class="card-text"><?= e($step['text'] ?? '') ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== CTA ============================== -->
<section class="section section-soft mv-cta">
    <div class="container">
        <div class="card-3d mv-cta-card reveal text-center" style="background:var(--grad-brand);color:#fff;max-width:920px;margin-inline:auto;">
            <span class="eyebrow" style="justify-content:center;color:#fff;">Be Part of the Change</span>
            <h2 class="text-white">Help Us Turn Vision Into Reality</h2>
            <p style="color:rgba(255,255,255,.92);max-width:620px;margin-inline:auto;">Your support powers education, healthcare and opportunity for thousands across Bihar. Join us as a donor or a volunteer today.</p>
            <div class="flex flex-wrap justify-center gap-2 mt-4">
                <a class="btn btn-white btn-lg" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Donate Now</a>
                <a class="btn btn-outline btn-lg" style="border-color:#fff;color:#fff;" href="<?= e(url('volunteer')) ?>"><?= lucide('handshake') ?> Become a Volunteer</a>
            </div>
        </div>
    </div>
</section>

</div><!-- /.mv-page -->

<?php include __DIR__ . '/includes/footer.php'; ?>
