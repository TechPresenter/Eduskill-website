<?php
/**
 * =============================================================================
 *  Homepage — all sections are DB-driven with sensible fallbacks so the page
 *  looks complete before any content is seeded.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/hero.php';

/* ---- Data ------------------------------------------------------------- */
$slides   = array_map('hero_slide', get_all('hero_slides', ['status' => 1], 'sort_order ASC'));
$counters = get_all('achievements', ['status' => 1], 'sort_order ASC', 4);
$programs = db_all("SELECT * FROM programs WHERE status='active' ORDER BY is_featured DESC, sort_order ASC LIMIT 6");
$events   = db_all("SELECT * FROM events WHERE status='published' AND start_datetime >= NOW() ORDER BY start_datetime ASC LIMIT 3");
$blogs    = db_all("SELECT b.*, c.name AS category FROM blogs b LEFT JOIN blog_categories c ON c.id=b.category_id
                    WHERE b.status='published' ORDER BY b.published_at DESC, b.id DESC LIMIT 3");
$testis   = get_all('testimonials', ['status' => 1], 'sort_order ASC', 8);
$albums   = db_all("SELECT a.*, (SELECT file_path FROM gallery_media m WHERE m.album_id=a.id ORDER BY m.sort_order LIMIT 1) AS thumb
                    FROM gallery_albums a WHERE a.status=1 ORDER BY a.sort_order ASC LIMIT 6");
$partners = get_all('partners', ['status' => 1], 'sort_order ASC');
$sponsors = get_all('sponsors', ['status' => 1], 'sort_order ASC');
$video    = db_row("SELECT * FROM videos WHERE status=1 ORDER BY sort_order ASC LIMIT 1");

/* Fallback counters if none seeded */
if (!$counters) {
    $counters = [
        ['icon' => 'users',          'value' => 25000, 'suffix' => '+', 'title' => 'Lives Impacted'],
        ['icon' => 'graduation-cap', 'value' => 120,   'suffix' => '+', 'title' => 'Projects Completed'],
        ['icon' => 'handshake',      'value' => 800,   'suffix' => '+', 'title' => 'Volunteers'],
        ['icon' => 'home',           'value' => 60,    'suffix' => '+', 'title' => 'Villages Reached'],
    ];
}
/* Fallback programs */
if (!$programs) {
    $programs = [
        ['title' => 'Education for All',   'slug' => '', 'icon' => 'book', 'short_description' => 'Free schooling, scholarships and learning kits for underprivileged children across Bihar.', 'color' => '#063566'],
        ['title' => 'Healthcare & Camps',  'slug' => '', 'icon' => 'stethoscope', 'short_description' => 'Free medical camps, health awareness and access to essential care in rural areas.', 'color' => '#084881'],
        ['title' => 'Women Empowerment',   'slug' => '', 'icon' => 'user', 'short_description' => 'Skill training, self-help groups and livelihood support for women.', 'color' => '#E67B1D'],
        ['title' => 'Skill Development',   'slug' => '', 'icon' => 'wrench', 'short_description' => 'Vocational training that prepares youth for sustainable employment.', 'color' => '#58A42F'],
        ['title' => 'Clean Water & Sanitation', 'slug' => '', 'icon' => 'droplet', 'short_description' => 'Safe drinking water, hygiene drives and sanitation infrastructure.', 'color' => '#084881'],
        ['title' => 'Relief & Rehabilitation', 'slug' => '', 'icon' => 'life-buoy', 'short_description' => 'Rapid disaster relief, food distribution and long-term rehabilitation.', 'color' => '#dc2626'],
    ];
}
if (!$testis) {
    $testis = [
        ['name' => 'Anita Kumari', 'designation' => 'Beneficiary, Patna', 'message' => 'The skill program changed my life — I now run my own tailoring business and support my family.', 'rating' => 5, 'photo' => null],
        ['name' => 'Rakesh Singh', 'designation' => 'Volunteer', 'message' => 'Volunteering with EduSkill India has been the most rewarding experience. The team truly cares about impact.', 'rating' => 5, 'photo' => null],
        ['name' => 'Dr. Meena Rao', 'designation' => 'Partner, Health Camp', 'message' => 'Their organisation and dedication during our medical camps was exceptional. Real change on the ground.', 'rating' => 5, 'photo' => null],
    ];
}

seo_set([
    'title'       => null, // homepage uses the site name as-is
    'description' => get_setting('site_description', 'EDUSKILL INDIA FOUNDATION empowers communities across Bihar through education, healthcare, skill development and relief. Join us to spread hope and create change.'),
    'page_key'    => 'home',
    'type'        => 'website',
]);

include __DIR__ . '/includes/header.php';
?>

<!-- ============================== HERO ============================== -->
<?php
if (!$slides) {
    $slides = [hero_slide([
        'badge_text' => 'Registered NGO · 80G Certified', 'badge_icon' => 'shield-check',
        'title' => 'Empowering Communities, Creating {Lasting Change}',
        'description' => 'EDUSKILL INDIA FOUNDATION works across Bihar to bring education, healthcare, and opportunity to those who need it most. Together, we can build a brighter future.',
        'button_text' => 'Donate Now', 'button_url' => 'donate', 'btn_icon' => 'heart',
        'button2_text' => 'Become a Volunteer', 'button2_url' => 'volunteer', 'btn2_icon' => 'hand-heart',
        'trust_text' => 'Trusted across 40+ communities', 'rating' => 4.9, 'rating_count' => 1200,
        'accent' => '#a855f7', 'bg_type' => 'mesh', 'bg_from' => '#084881', 'bg_to' => '#084881', 'divider' => 'wave',
    ])];
}
$first = $slides[0];
?>
<section class="hx hx-h-<?= e($first['height']) ?> hx-align-<?= e($first['text_align']) ?><?= !empty($first['animate']) ? ' hx-animate' : '' ?>"
         data-hx data-interval="<?= (int) get_setting('hero_autoplay_seconds', 6) * 1000 ?>" style="--acc:<?= e(hero_accent($first)) ?>">
    <div class="hx-bgs" data-hx-parallax>
        <?php foreach ($slides as $i => $s): ?>
            <div class="hx-bg<?= $i === 0 ? ' on' : '' ?>" style="<?= hero_bg_style($s) ?>">
                <?php if (($s['bg_type'] ?? '') === 'video' && !empty($s['bg_video'])): ?>
                    <video class="hx-video" autoplay muted loop playsinline src="<?= e(preg_match('#^https?://#', $s['bg_video']) ? $s['bg_video'] : upload_url($s['bg_video'])) ?>"></video>
                <?php endif; ?>
                <?php if ((int) ($s['overlay'] ?? 0) > 0): ?><span class="hx-overlay" style="opacity:<?= max(0, min(100, (int) $s['overlay'])) / 100 ?>"></span><?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <span class="hx-blob b1" aria-hidden="true"></span><span class="hx-blob b2" aria-hidden="true"></span><span class="hx-blob b3" aria-hidden="true"></span>

    <div class="container hx-container">
        <?php foreach ($slides as $i => $s):
            $isSplit = ($s['layout'] ?? 'center') === 'split' && !empty($s['hero_image']); ?>
            <div class="hx-copy<?= $i === 0 ? ' on' : '' ?><?= $isSplit ? ' is-split' : '' ?>" data-hx-copy>
                <div class="hx-text">
                    <?php if (!empty($s['badge_text'])): ?>
                        <span class="hx-badge"><?= !empty($s['badge_icon']) ? lucide($s['badge_icon']) : '' ?><?= e($s['badge_text']) ?></span>
                    <?php endif; ?>
                    <?php $tag = $i === 0 ? 'h1' : 'p'; ?>
                    <<?= $tag ?> class="hx-title"<?= $i === 0 ? '' : ' role="heading" aria-level="2"' ?>><?= hero_title_html($s) . hero_typing_html($s) ?></<?= $tag ?>>
                    <?php $para = $s['description'] ?: ($s['subtitle'] ?? ''); ?>
                    <?php if ($para !== ''): ?><p class="hx-sub"><?= e($para) ?></p><?php endif; ?>
                    <?php $rating = hero_rating_html($s); ?>
                    <?php if ($rating !== '' || !empty($s['trust_text'])): ?>
                        <div class="hx-trust">
                            <?= $rating ?>
                            <?php if (!empty($s['trust_text'])): ?><span class="hx-trust-txt"><?= lucide('badge-check') ?><?= e($s['trust_text']) ?></span><?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="hx-actions">
                        <?= hero_cta($s['button_text'] ?? '', $s['button_url'] ?? '', $s['btn_style'] ?? 'gradient', $s['btn_icon'] ?? '', true, hero_accent($s)) ?>
                        <?= hero_cta($s['button2_text'] ?? '', $s['button2_url'] ?? '', $s['btn2_style'] ?? 'glass', $s['btn2_icon'] ?? '', false, hero_accent($s)) ?>
                    </div>
                </div>
                <?php if ($isSplit): ?>
                    <div class="hx-media"><span class="hx-media-glow"></span><img class="hx-img" src="<?= e(upload_url($s['hero_image'])) ?>" alt="" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>"></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <?= hero_divider_svg($first['divider'] ?? 'none') ?>
    <?php if (count($slides) > 1): ?>
        <div class="hx-dots" role="tablist" aria-label="Hero slides">
            <?php foreach ($slides as $i => $s): ?><button class="hx-dot<?= $i === 0 ? ' on' : '' ?>" data-hx-dot="<?= $i ?>" aria-label="Slide <?= $i + 1 ?>"></button><?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/hero-assets.php'; ?>

<!-- ============================== STAT BAR + RUNNING MARQUEE ============================== -->
<section class="stat-bar-section" style="margin-top:clamp(-72px,-6vw,-44px);">
    <div class="container">
        <div class="stat-bar reveal">
            <div class="sb-stats">
                <?php foreach (array_slice($counters, 0, 3) as $c): ?>
                    <div class="sb-stat">
                        <strong><span data-counter="<?= (int) $c['value'] ?>">0</span><span class="u"><?= e($c['suffix'] ?? '') ?></span></strong>
                        <small><?= e($c['title']) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="sb-divider"></div>
            <div class="sb-marquee marquee">
                <div class="marquee-track">
                    <?php
                    $highlights = ['Registered Non-Profit (Sec. 8)', '80G Tax Exemption', 'Transparent Impact', 'Community-Led Programs', 'Volunteer-Powered', 'Serving Bihar with Care'];
                    for ($rep = 0; $rep < 2; $rep++):
                        foreach ($highlights as $h): ?>
                            <span class="sb-pill"><?= e($h) ?></span>
                    <?php endforeach; endfor; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================== WHO WE ARE / MISSION / VISION ============================== -->
<section class="section">
    <div class="container grid grid-2 items-center">
        <div class="reveal">
            <span class="eyebrow">Who We Are</span>
            <h2 class="section-title"><?= e(get_setting('home_about_title', 'A movement for dignity, hope and opportunity')) ?></h2>
            <p class="text-muted"><?= e(get_setting('home_about_text', 'EDUSKILL INDIA FOUNDATION is a registered non-profit (CIN ' . SITE_CIN . ') based in Patna, Bihar. Since our inception we have worked hand-in-hand with communities to deliver education, healthcare, skill development, and emergency relief — always with compassion and accountability.')) ?></p>
            <ul class="list-check mt-3">
                <li>Transparent, community-led programs with measurable impact</li>
                <li>Registered non-profit governed by dedicated directors</li>
                <li>Every rupee tracked — from donation to delivery</li>
            </ul>
            <a class="btn btn-primary mt-4" href="<?= e(url('about')) ?>">Learn More About Us</a>
        </div>
        <div class="grid gap-3 reveal delay-1">
            <div class="card-3d">
                <div class="icon-badge"><?= lucide('target') ?></div>
                <h3 class="card-title">Our Mission</h3>
                <p class="card-text"><?= e(get_setting('mission_short', 'To empower underserved communities by providing access to quality education, healthcare, and sustainable livelihoods.')) ?></p>
            </div>
            <div class="card-3d">
                <div class="icon-badge accent"><?= lucide('sparkles') ?></div>
                <h3 class="card-title">Our Vision</h3>
                <p class="card-text"><?= e(get_setting('vision_short', 'An equitable society where every individual has the opportunity to live with dignity and reach their full potential.')) ?></p>
            </div>
        </div>
    </div>
</section>

<?php if ($video): $ytid = youtube_id($video['youtube_id'] ?: $video['video_url']); ?>
<!-- ============================== VIDEO BANNER ============================== -->
<section class="section section-dark" style="background:linear-gradient(rgba(11,17,32,.85),rgba(11,17,32,.9)), var(--grad-brand);">
    <div class="container text-center reveal">
        <span class="eyebrow" style="color:#7BB8EC;">Watch Our Story</span>
        <h2 class="text-white"><?= e($video['title']) ?></h2>
        <?php if ($ytid): ?>
        <div class="map-embed mt-4" style="max-width:900px;margin-inline:auto;aspect-ratio:16/9;">
            <iframe src="https://www.youtube.com/embed/<?= e($ytid) ?>" title="<?= e($video['title']) ?>" allowfullscreen loading="lazy"></iframe>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- ============================== PROGRAMS ============================== -->
<style>
/* ===== Our Programs — scoped redesign (.pgm-*) ===== */
.pgm-section { position: relative; overflow: hidden; }
.pgm-section .container { position: relative; z-index: 1; }

/* Decorative background orbs + grid texture */
.pgm-orb { position: absolute; border-radius: 50%; filter: blur(80px); pointer-events: none; z-index: 0; opacity: .55; }
.pgm-orb-1 { width: 380px; height: 380px; top: -140px; right: -90px;
    background: radial-gradient(circle at 30% 30%, rgba(8,72,129,.5), transparent 70%); }
.pgm-orb-2 { width: 440px; height: 440px; bottom: -180px; left: -130px;
    background: radial-gradient(circle at 40% 40%, rgba(6,53,102,.4), transparent 70%); }
.pgm-mesh { position: absolute; inset: 0; z-index: 0; pointer-events: none; opacity: .5;
    background-image: radial-gradient(circle at 1px 1px, rgba(6,53,102,.12) 1px, transparent 0);
    background-size: 26px 26px;
    -webkit-mask-image: radial-gradient(ellipse 70% 60% at 50% 40%, #000 30%, transparent 75%);
    mask-image: radial-gradient(ellipse 70% 60% at 50% 40%, #000 30%, transparent 75%); }
:root[data-theme="dark"] .pgm-orb, :root[data-theme="dark"] .pgm-mesh { opacity: .28; }
@media (prefers-color-scheme: dark) {
    :root:not([data-theme="light"]) .pgm-orb,
    :root:not([data-theme="light"]) .pgm-mesh { opacity: .28; }
}

/* Eyebrow accent chip */
.pgm-eyebrow { display: inline-flex; align-items: center; gap: .5rem; }
.pgm-eyebrow svg { width: 16px; height: 16px; color: #084881; }

/* Grid */
.pgm-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.6rem; }

/* Card */
.pgm-card {
    position: relative; display: flex; flex-direction: column;
    padding: 2rem 1.75rem; color: inherit; isolation: isolate; overflow: hidden;
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-lg); box-shadow: 0 8px 26px rgba(6,53,102,.06);
    transition: transform .45s cubic-bezier(.2,.8,.2,1), box-shadow .45s, border-color .45s;
}
.pgm-card::before {
    content: ""; position: absolute; inset: 0 0 auto 0; height: 4px; z-index: 2;
    background: linear-gradient(90deg, var(--pgm-c, #063566), #084881);
    transform: scaleX(0); transform-origin: left; transition: transform .5s ease;
}
.pgm-card:hover, .pgm-card:focus-visible {
    transform: translateY(-10px); border-color: transparent;
    box-shadow: 0 28px 54px rgba(6,53,102,.18);
}
.pgm-card:hover::before, .pgm-card:focus-visible::before { transform: scaleX(1); }
.pgm-card:focus-visible { outline: 2px solid #084881; outline-offset: 3px; }
:root[data-theme="dark"] .pgm-card { box-shadow: 0 10px 28px rgba(0,0,0,.4); }

/* Hover glow behind the card content */
.pgm-glow {
    position: absolute; z-index: -1; top: -35%; right: -25%; width: 260px; height: 260px; border-radius: 50%;
    background: radial-gradient(circle, color-mix(in srgb, var(--pgm-c, #063566) 32%, transparent), transparent 70%);
    opacity: 0; transform: scale(.55); transition: opacity .55s ease, transform .55s ease;
}
.pgm-card:hover .pgm-glow, .pgm-card:focus-visible .pgm-glow { opacity: 1; transform: scale(1); }

/* Gradient icon tile */
.pgm-icon {
    width: 70px; height: 70px; border-radius: 20px; margin-bottom: 1.3rem;
    display: grid; place-items: center; color: #fff;
    background: linear-gradient(135deg, var(--pgm-c, #063566), #084881);
    box-shadow: 0 12px 24px color-mix(in srgb, var(--pgm-c, #063566) 42%, transparent);
    transition: transform .45s cubic-bezier(.2,.8,.2,1);
}
.pgm-icon svg { width: 32px; height: 32px; stroke-width: 2; }
.pgm-card:hover .pgm-icon, .pgm-card:focus-visible .pgm-icon {
    transform: translateY(-4px) rotate(-6deg) scale(1.07);
}

.pgm-title {
    font-family: 'Outfit', var(--font-sans); font-size: 1.24rem; font-weight: 700;
    line-height: 1.25; margin: 0 0 .55rem; color: var(--text);
}
.pgm-text { color: var(--muted); font-size: .95rem; line-height: 1.62; margin: 0 0 1.4rem; flex: 1 1 auto; }

.pgm-link {
    display: inline-flex; align-items: center; gap: .4rem; margin-top: auto;
    font-weight: 700; font-size: .92rem; letter-spacing: .01em; color: #063566;
    transition: gap .3s ease, color .3s ease;
}
.pgm-link svg { width: 18px; height: 18px; transition: transform .3s ease; }
.pgm-card:hover .pgm-link, .pgm-card:focus-visible .pgm-link { gap: .7rem; color: #084881; }
.pgm-card:hover .pgm-link svg, .pgm-card:focus-visible .pgm-link svg { transform: translateX(4px); }
:root[data-theme="dark"] .pgm-link { color: #7BC94F; }
:root[data-theme="dark"] .pgm-card:hover .pgm-link,
:root[data-theme="dark"] .pgm-card:focus-visible .pgm-link { color: #5eead4; }
@media (prefers-color-scheme: dark) {
    :root:not([data-theme="light"]) .pgm-link { color: #7BC94F; }
    :root:not([data-theme="light"]) .pgm-card:hover .pgm-link,
    :root:not([data-theme="light"]) .pgm-card:focus-visible .pgm-link { color: #5eead4; }
}

@media (max-width: 640px) {
    .pgm-grid { grid-template-columns: 1fr; gap: 1.25rem; }
    .pgm-card { padding: 1.7rem 1.4rem; }
}
@media (prefers-reduced-motion: reduce) {
    .pgm-card, .pgm-card::before, .pgm-icon, .pgm-glow, .pgm-link, .pgm-link svg { transition: none; }
}
</style>
<section class="section section-soft pgm-section">
    <span class="pgm-orb pgm-orb-1" aria-hidden="true"></span>
    <span class="pgm-orb pgm-orb-2" aria-hidden="true"></span>
    <span class="pgm-mesh" aria-hidden="true"></span>
    <div class="container">
        <div class="section-head reveal">
            <span class="eyebrow pgm-eyebrow"><?= lucide('sparkles') ?> What We Do</span>
            <h2 class="section-title">Our Programs</h2>
            <p class="section-subtitle">Focused initiatives designed to create real, measurable change in the communities we serve.</p>
        </div>
        <div class="pgm-grid">
            <?php foreach ($programs as $i => $p): ?>
                <a class="pgm-card reveal <?= 'delay-' . (($i % 3) + 1) ?>" href="<?= e($p['slug'] ? url('programs?slug=' . $p['slug']) : url('programs')) ?>" style="--pgm-c:<?= e($p['color'] ?: '#063566') ?>;">
                    <span class="pgm-glow" aria-hidden="true"></span>
                    <div class="pgm-icon"><?= lucide($p['icon'] ?: 'star') ?></div>
                    <h3 class="pgm-title"><?= e($p['title']) ?></h3>
                    <p class="pgm-text"><?= e(excerpt($p['short_description'] ?? '', 22)) ?></p>
                    <span class="pgm-link">Explore <?= lucide('arrow-right') ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if ($events): ?>
<!-- ============================== UPCOMING EVENTS ============================== -->
<section class="section">
    <div class="container">
        <div class="section-head left flex justify-between items-center flex-wrap gap-2">
            <div>
                <span class="eyebrow">Join Us</span>
                <h2 class="section-title mb-0">Upcoming Events</h2>
            </div>
            <a class="btn btn-outline btn-sm" href="<?= e(url('events')) ?>">View All Events</a>
        </div>
        <div class="grid grid-3">
            <?php foreach ($events as $ev): ?>
                <article class="card reveal">
                    <div class="card-media">
                        <img src="<?= e(image_url($ev['image'])) ?>" alt="<?= e($ev['title']) ?>" loading="lazy" width="600" height="375">
                        <span class="badge badge-brand" style="position:absolute;top:12px;left:12px;background:#fff;"><?= e(format_date($ev['start_datetime'], 'd M Y')) ?></span>
                    </div>
                    <div class="card-body">
                        <h3 class="card-title"><?= e($ev['title']) ?></h3>
                        <p class="card-text"><?= lucide('map-pin') ?> <?= e($ev['location'] ?: 'Patna, Bihar') ?></p>
                        <a class="btn btn-primary btn-sm" href="<?= e(url('events?slug=' . $ev['slug'])) ?>">Details & Register</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================== DONATE / VOLUNTEER CTA ============================== -->
<section class="section">
    <div class="container grid grid-2 gap-4">
        <div class="card-3d reveal" style="background:var(--grad-brand);color:#fff;">
            <h2 class="text-white">Your Support Changes Lives</h2>
            <p style="color:rgba(255,255,255,.9);">Every contribution helps us reach more children, families and communities with the resources they need to thrive.</p>
            <a class="btn btn-white btn-lg mt-2" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Donate Now</a>
        </div>
        <div class="card-3d reveal delay-1" style="background:var(--grad-accent);color:#fff;">
            <h2 class="text-white">Become a Volunteer</h2>
            <p style="color:rgba(255,255,255,.92);">Give your time and skills to a cause that matters. Join our community of changemakers across Bihar.</p>
            <a class="btn btn-white btn-lg mt-2" href="<?= e(url('volunteer')) ?>"><?= lucide('handshake') ?> Join Us</a>
        </div>
    </div>
</section>

<!-- ============================== TESTIMONIALS ============================== -->
<section class="section section-alt">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Voices of Change</span>
            <h2 class="section-title">What People Say</h2>
        </div>
        <div data-carousel>
            <div class="testi-track" data-carousel-track>
                <?php foreach ($testis as $t): ?>
                    <div class="testi-card">
                        <div class="card" style="height:100%;">
                            <div class="card-body">
                                <?= star_rating((int) ($t['rating'] ?? 5)) ?>
                                <p class="mt-2" style="font-size:1.05rem;color:var(--text-soft);">“<?= e($t['message']) ?>”</p>
                                <div class="flex items-center gap-2 mt-3">
                                    <img src="<?= e(image_url($t['photo'] ?? null, 'avatar')) ?>" alt="<?= e($t['name']) ?>" width="52" height="52" loading="lazy" decoding="async" style="width:52px;height:52px;border-radius:50%;object-fit:cover;">
                                    <div>
                                        <strong><?= e($t['name']) ?></strong><br>
                                        <small class="text-muted"><?= e($t['designation'] ?? '') ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="flex justify-center gap-2 mt-4">
                <button class="btn btn-secondary btn-sm" data-carousel-prev aria-label="Previous"><?= lucide('arrow-left') ?></button>
                <button class="btn btn-secondary btn-sm" data-carousel-next aria-label="Next"><?= lucide('arrow-right') ?></button>
            </div>
        </div>
    </div>
</section>

<?php if ($blogs): ?>
<!-- ============================== LATEST BLOGS ============================== -->
<section class="section">
    <div class="container">
        <div class="section-head left flex justify-between items-center flex-wrap gap-2">
            <div>
                <span class="eyebrow">From Our Blog</span>
                <h2 class="section-title mb-0">Latest Stories & News</h2>
            </div>
            <a class="btn btn-outline btn-sm" href="<?= e(url('blogs')) ?>">Read All</a>
        </div>
        <div class="grid grid-3">
            <?php foreach ($blogs as $b): ?>
                <article class="card reveal">
                    <a href="<?= e(url('blog-details?slug=' . $b['slug'])) ?>" class="card-media">
                        <img src="<?= e(image_url($b['featured_image'], 'blog')) ?>" alt="<?= e($b['title']) ?>" loading="lazy" width="600" height="375">
                    </a>
                    <div class="card-body">
                        <?php if (!empty($b['category'])): ?><span class="badge badge-brand mb-1"><?= e($b['category']) ?></span><?php endif; ?>
                        <h3 class="card-title"><a href="<?= e(url('blog-details?slug=' . $b['slug'])) ?>" style="color:inherit;"><?= e($b['title']) ?></a></h3>
                        <p class="card-text"><?= e(excerpt($b['excerpt'] ?: $b['content'], 18)) ?></p>
                        <small class="text-muted"><?= e(format_date($b['published_at'] ?: $b['created_at'])) ?></small>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($albums): ?>
<!-- ============================== GALLERY PREVIEW ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Moments</span>
            <h2 class="section-title">Gallery</h2>
        </div>
        <div class="gallery-grid">
            <?php foreach ($albums as $a): if (empty($a['thumb'])) continue; ?>
                <a class="gallery-item" href="<?= e(url('gallery?album=' . $a['slug'])) ?>" data-lightbox="<?= e(upload_url($a['thumb'])) ?>">
                    <img src="<?= e(upload_url($a['thumb'])) ?>" alt="<?= e($a['title']) ?>" loading="lazy">
                </a>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-4"><a class="btn btn-outline" href="<?= e(url('gallery')) ?>">View Full Gallery</a></div>
    </div>
</section>
<?php endif; ?>

<?php if ($partners || $sponsors): ?>
<!-- ============================== PARTNERS & SPONSORS ============================== -->
<section class="section section-partners" aria-labelledby="partnersHeading">
    <span class="pn-blob pn-b1" aria-hidden="true"></span>
    <span class="pn-blob pn-b2" aria-hidden="true"></span>
    <div class="container text-center">
        <span class="eyebrow" style="justify-content:center;">Together We Achieve More</span>
        <h2 class="section-title" id="partnersHeading">Our Partners &amp; Sponsors</h2>
        <p class="section-subtitle">Organisations who stand with us — funding, mentoring and delivering change alongside our teams.</p>
    </div>

    <?php
    $orgs = array_merge($partners, $sponsors);
    // Monogram fallback so the section looks designed even before logos are uploaded.
    $initials = static function (string $n): string {
        $w = preg_split('/\s+/', trim($n)) ?: [];
        $s = '';
        foreach ($w as $part) { if ($part !== '' && ctype_alpha($part[0])) { $s .= strtoupper($part[0]); } if (strlen($s) === 2) break; }
        return $s !== '' ? $s : strtoupper(substr($n, 0, 1));
    };
    /* The marquee needs the list twice so the loop is seamless. */
    $render = function (array $list, bool $clone = false) use ($initials) {
        foreach ($list as $i => $org):
            $hasLogo = !empty($org['logo']);
            $site    = trim((string) ($org['website'] ?? ''));
            $tag     = $site !== '' ? 'a' : 'div';
            $attrs   = $site !== ''
                ? ' href="' . e(preg_match('#^https?://#i', $site) ? $site : 'https://' . $site) . '" target="_blank" rel="noopener noreferrer"'
                : '';
            ?>
            <<?= $tag ?> class="pn-card"<?= $attrs ?> style="--pn-i:<?= $i % 6 ?>"<?= $clone ? ' aria-hidden="true" tabindex="-1"' : '' ?>>
                <span class="pn-inner">
                    <?php if ($hasLogo): ?>
                        <img class="pn-logo" src="<?= e(upload_url($org['logo'])) ?>" alt="<?= e($org['name']) ?>"
                             width="120" height="56" loading="lazy" decoding="async">
                    <?php else: ?>
                        <span class="pn-mono" aria-hidden="true"><?= e($initials((string) $org['name'])) ?></span>
                        <span class="pn-name"><?= e($org['name']) ?></span>
                    <?php endif; ?>
                </span>
                <span class="pn-tip" role="tooltip"><?= e($org['name']) ?><?= $site !== '' ? ' ↗' : '' ?></span>
            </<?= $tag ?>>
        <?php endforeach;
    };
    ?>
    <div class="pn-marquee" data-pn-marquee>
        <div class="pn-track">
            <?php $render($orgs); $render($orgs, true); ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
