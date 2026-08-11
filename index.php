<?php
/**
 * =============================================================================
 *  Homepage — all sections are DB-driven with sensible fallbacks so the page
 *  looks complete before any content is seeded.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/hero.php';
require_once __DIR__ . '/includes/homepage.php';   // section registry + editable content

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
        ['title' => 'Education for All',   'slug' => '', 'icon' => 'book', 'short_description' => 'Free schooling, scholarships and learning kits for underprivileged children across Bihar.', 'color' => '#0B4E3D'],
        ['title' => 'Healthcare & Camps',  'slug' => '', 'icon' => 'stethoscope', 'short_description' => 'Free medical camps, health awareness and access to essential care in rural areas.', 'color' => '#174D3D'],
        ['title' => 'Women Empowerment',   'slug' => '', 'icon' => 'user', 'short_description' => 'Skill training, self-help groups and livelihood support for women.', 'color' => '#F15A24'],
        ['title' => 'Skill Development',   'slug' => '', 'icon' => 'wrench', 'short_description' => 'Vocational training that prepares youth for sustainable employment.', 'color' => '#2F8065'],
        ['title' => 'Clean Water & Sanitation', 'slug' => '', 'icon' => 'droplet', 'short_description' => 'Safe drinking water, hygiene drives and sanitation infrastructure.', 'color' => '#174D3D'],
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
        'accent' => '#a855f7', 'bg_type' => 'mesh', 'bg_from' => '#174D3D', 'bg_to' => '#174D3D', 'divider' => 'wave',
    ])];
}
$first = $slides[0];
/* Editorial hero: copy on a flat brand field, photograph beside it. Chosen when
   the slide is a split layout with an image, so the photo never sits behind the
   headline. See .hx-panel in includes/hero-assets.php. */
$panelMode = ($first['layout'] ?? '') === 'split' && !empty($first['hero_image']);
?>
<section class="hx hx-h-<?= e($first['height']) ?> hx-align-<?= e($first['text_align']) ?><?= !empty($first['animate']) ? ' hx-animate' : '' ?><?= $panelMode ? ' hx-panel' : '' ?>"
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

<!-- ============================== IMPACT BAR ============================== -->
<?php /* Sits directly under the hero on an ivory field. The previous version
         pulled itself up with a -72px margin to overlap a full-bleed hero photo;
         the hero is now a flat green panel, so the overlap has nothing to sit
         against and is gone. Numbers count up on entry via data-counter. */ ?>
<?php if (home_section_visible('counters')): ?>
<section class="impact-bar" aria-labelledby="impactHeading">
    <h2 id="impactHeading" class="sr-only"><?= e(home_heading('counters')) ?></h2>
    <div class="container">
        <div class="ib-grid">
            <?php foreach (array_slice($counters, 0, 4) as $c): ?>
                <div class="ib-stat reveal">
                    <span class="ib-ico" aria-hidden="true"><?= lucide($c['icon'] ?: 'sparkles') ?></span>
                    <strong class="ib-num">
                        <span data-counter="<?= (int) $c['value'] ?>">0</span><span class="ib-suffix"><?= e($c['suffix'] ?? '') ?></span>
                    </strong>
                    <span class="ib-label"><?= e($c['title']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <ul class="ib-trust">
            <li><?= lucide('shield-check') ?> Registered Non-Profit (Sec. 8)</li>
            <li><?= lucide('receipt') ?> 80G Tax Exemption</li>
            <li><?= lucide('eye') ?> Every rupee tracked</li>
            <li><?= lucide('users') ?> Community-led programmes</li>
        </ul>
    </div>
</section>
<?php endif; ?>

<!-- ============================== WHO WE ARE / MISSION / VISION ============================== -->
<?php /* Editorial split: an asymmetric two-column with the statement on the left
         and the mission/vision pair plus progress indicators on the right. */ ?>
<?php if (home_section_visible('mission')): ?>
<section class="section mission-section">
    <div class="container mission-grid">
        <div class="mission-lede reveal">
            <span class="eyebrow">Our Mission</span>
            <h2 class="section-title"><?= e(get_setting('home_about_title', 'Creating opportunities that help communities build a stronger future.')) ?></h2>
            <p class="mission-text"><?= e(get_setting('home_about_text', 'EDUSKILL INDIA FOUNDATION is a registered non-profit (CIN ' . SITE_CIN . '). Since our inception we have worked hand-in-hand with communities to deliver education, healthcare, skill development, and emergency relief — always with compassion and accountability.')) ?></p>

            <?php /* Focus split — how effort is distributed across our work. Stored as
                     "Label|percent" per line and edited under Settings → Homepage;
                     home_focus_split() parses it and supplies the fallback set. */ ?>
            <ul class="focus-bars">
                <?php foreach (home_focus_split() as $fb): $pct = (int) $fb['pct']; ?>
                    <li>
                        <span class="fb-head"><span class="fb-label"><?= e($fb['label']) ?></span><span class="fb-pct"><?= $pct ?>%</span></span>
                        <span class="fb-track"><span class="fb-fill" style="--pct:<?= $pct ?>%"></span></span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <a class="btn btn-outline mt-4" href="<?= e(url('about')) ?>">About Us <?= lucide('arrow-right') ?></a>
        </div>

        <div class="mission-aside reveal delay-1">
            <article class="mv-card">
                <span class="mv-ico"><?= lucide('target') ?></span>
                <h3 class="mv-title">Our Mission</h3>
                <p class="mv-text"><?= e(get_setting('mission_short', 'To empower underserved communities by providing access to quality education, healthcare, and sustainable livelihoods.')) ?></p>
            </article>
            <article class="mv-card is-accent">
                <span class="mv-ico"><?= lucide('sparkles') ?></span>
                <h3 class="mv-title">Our Vision</h3>
                <p class="mv-text"><?= e(get_setting('vision_short', 'An equitable society where every individual has the opportunity to live with dignity and reach their full potential.')) ?></p>
            </article>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================== FOCUS AREAS ============================== -->
<?php
/* The areas of work. Edited under Settings → Focus Areas and stored in the
   `home_focus_areas` JSON setting; home_focus_areas() supplies the fallback set
   until an admin saves their own. Icons are lucide slugs, images are paths under
   uploads/ (blank = the icon tile carries the card on its own, which is why no
   placeholder photo is invented for a card without one). */
if (home_section_visible('focus')):
    $focusAreas = home_focus_areas();
    $focusSub   = home_subtitle('focus');
?>
<section class="section focus-section" aria-labelledby="focusHeading">
    <div class="container">
        <div class="section-head is-centered">
            <span class="eyebrow">What We Do</span>
            <h2 class="section-title" id="focusHeading"><?= e(home_heading('focus')) ?></h2>
            <?php if ($focusSub !== ''): ?><p class="section-subtitle"><?= e($focusSub) ?></p><?php endif; ?>
        </div>

        <div class="focus-grid">
            <?php foreach ($focusAreas as $i => $fa):
                $faUrl = !empty($fa['url']) ? url((string) $fa['url']) : url('programs'); ?>
                <article class="focus-card reveal<?= $i === 0 ? '' : ' delay-' . min(3, $i) ?>">
                    <?php if (!empty($fa['image'])): ?>
                        <div class="fc-media">
                            <img src="<?= e(upload_url($fa['image'])) ?>" alt="" loading="lazy" decoding="async" width="480" height="320">
                        </div>
                    <?php endif; ?>
                    <div class="fc-body">
                        <span class="fc-ico" aria-hidden="true"><?= lucide($fa['icon'] ?: 'sparkles') ?></span>
                        <span class="fc-label"><?= e($fa['label'] ?? '') ?></span>
                        <h3 class="fc-title"><a class="fc-link" href="<?= e($faUrl) ?>"><?= e($fa['title'] ?? '') ?></a></h3>
                        <p class="fc-text"><?= e($fa['text'] ?? '') ?></p>
                        <span class="fc-more" aria-hidden="true">Explore <?= lucide('arrow-right') ?></span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================== FEATURED STORY ============================== -->
<?php
/* Pulls the newest post in a "Success Stories"/"Stories" category. Driven by
   real editorial content rather than invented copy: IMAGE_SOURCES.md commits to
   never presenting stock photography as a named beneficiary, so nothing here
   captions the photograph as a specific person — it carries the story the post
   itself tells, and links through to the full piece. */
$featured = home_section_visible('featured_story') ? db_row(
    "SELECT b.*, c.name AS category
     FROM blogs b
     LEFT JOIN blog_categories c ON c.id = b.category_id
     WHERE b.status = 'published' AND b.featured_image IS NOT NULL AND b.featured_image <> ''
       AND c.name IN ('Success Stories', 'Stories')
     ORDER BY b.published_at DESC, b.id DESC LIMIT 1"
) : null;
/* No fallback by design: this section is one photograph carrying one real story.
   With nothing published there is no honest stand-in — a stock photograph with
   invented copy beside it would be exactly the thing IMAGE_SOURCES.md forbids —
   so the section stays out and the Blog & Stories section below carries the
   "nothing published yet" message once, rather than twice. */
if ($featured):
    $fStoryUrl = url('blog/' . ($featured['slug'] ?: ''));
    $fExcerpt  = trim((string) ($featured['excerpt'] ?? ''));
    if ($fExcerpt === '') {
        $fExcerpt = excerpt(strip_tags((string) ($featured['content'] ?? '')), 34);
    }
?>
<section class="section featured-story" aria-labelledby="featuredStoryHeading">
    <div class="container fs-grid">
        <div class="fs-media reveal">
            <img src="<?= e(upload_url($featured['featured_image'])) ?>" alt="" loading="lazy" decoding="async" width="720" height="540">
        </div>
        <div class="fs-body reveal delay-1">
            <span class="eyebrow">Featured Story</span>
            <h2 class="section-title" id="featuredStoryHeading">
                <?= e($featured['title']) ?>
            </h2>
            <p class="fs-text"><?= e($fExcerpt) ?></p>
            <dl class="fs-metrics">
                <div><dt>Programme</dt><dd><?= e($featured['category'] ?: 'Impact') ?></dd></div>
                <?php if (!empty($featured['published_at'])): ?>
                    <div><dt>Published</dt><dd><?= e(date('M Y', strtotime((string) $featured['published_at']))) ?></dd></div>
                <?php endif; ?>
            </dl>
            <a class="btn btn-primary" href="<?= e($fStoryUrl) ?>">Read Story <?= lucide('arrow-right') ?></a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================== VIDEO BANNER ============================== -->
<?php
/* Fallback: an EDITOR-ONLY empty state. A band headed "Watch Our Story" with no
   video in it gives a visitor nothing, and there is no honest stand-in — we are
   not going to embed somebody else's film — so the public simply doesn't see the
   section. Signed-in staff get told where it went, which is the part that was
   missing: before this, the section vanished silently on unseeded data.
   The eyebrow was #7BB8EC, an off-palette blue; it is brand yellow now
   (#FFE987 on this near-black field, 15.5:1). */
if (home_section_visible('video')):
    $ytid = $video ? youtube_id($video['youtube_id'] ?: $video['video_url']) : null;
?>
    <?php if ($video): ?>
    <section class="section section-dark" style="background:linear-gradient(rgba(11,17,32,.85),rgba(11,17,32,.9)), var(--grad-brand);">
        <div class="container text-center reveal">
            <span class="eyebrow" style="color:var(--yellow,#FFE987);">Watch Our Story</span>
            <h2 class="text-white"><?= e($video['title']) ?></h2>
            <?php if ($ytid): ?>
            <div class="map-embed mt-4" style="max-width:900px;margin-inline:auto;aspect-ratio:16/9;">
                <iframe src="https://www.youtube.com/embed/<?= e($ytid) ?>" title="<?= e($video['title']) ?>" allowfullscreen loading="lazy"></iframe>
            </div>
            <?php endif; ?>
        </div>
    </section>
    <?php else:
        $videoNote = home_empty([
            'audience' => 'editor',
            'icon'     => 'play-circle',
            'title'    => 'No video is published yet',
            'text'     => 'The “Watch our story” band appears here as soon as one video is added and set to active. Visitors are not seeing an empty band in its place.',
            'manage'   => ['Manage videos' => 'videos'],
        ]);
        if ($videoNote !== ''): ?>
        <section class="section"><div class="container"><?= $videoNote ?></div></section>
    <?php endif; endif; ?>
<?php endif; ?>

<!-- ============================== PROGRAMS ============================== -->
<?php /* The <style> block moved INSIDE the visibility gate: it styles nothing but
         .pgm-* elements, which only this section emits, so with Programs switched
         off it was 5,175 bytes of dead CSS on every homepage request. */ ?>
<?php if (home_section_visible('programs')): $pgmSub = home_subtitle('programs'); ?>
<style>
/* ===== Our Programs — scoped redesign (.pgm-*) ===== */
.pgm-section { position: relative; overflow: hidden; }
.pgm-section .container { position: relative; z-index: 1; }

/* Decorative background orbs + grid texture */
.pgm-orb { position: absolute; border-radius: 50%; filter: blur(80px); pointer-events: none; z-index: 0; opacity: .55; }
.pgm-orb-1 { width: 380px; height: 380px; top: -140px; right: -90px;
    background: radial-gradient(circle at 30% 30%, rgba(23,77,61,.5), transparent 70%); }
.pgm-orb-2 { width: 440px; height: 440px; bottom: -180px; left: -130px;
    background: radial-gradient(circle at 40% 40%, rgba(11,78,61,.4), transparent 70%); }
.pgm-mesh { position: absolute; inset: 0; z-index: 0; pointer-events: none; opacity: .5;
    background-image: radial-gradient(circle at 1px 1px, rgba(11,78,61,.12) 1px, transparent 0);
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
.pgm-eyebrow svg { width: 16px; height: 16px; color: #174D3D; }

/* Grid */
.pgm-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.6rem; }

/* Card */
.pgm-card {
    position: relative; display: flex; flex-direction: column;
    padding: 2rem 1.75rem; color: inherit; isolation: isolate; overflow: hidden;
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-lg); box-shadow: 0 8px 26px rgba(11,78,61,.06);
    transition: transform .45s cubic-bezier(.2,.8,.2,1), box-shadow .45s, border-color .45s;
}
.pgm-card::before {
    content: ""; position: absolute; inset: 0 0 auto 0; height: 4px; z-index: 2;
    background: linear-gradient(90deg, var(--pgm-c, #0B4E3D), #174D3D);
    transform: scaleX(0); transform-origin: left; transition: transform .5s ease;
}
.pgm-card:hover, .pgm-card:focus-visible {
    transform: translateY(-10px); border-color: transparent;
    box-shadow: 0 28px 54px rgba(11,78,61,.18);
}
.pgm-card:hover::before, .pgm-card:focus-visible::before { transform: scaleX(1); }
.pgm-card:focus-visible { outline: 2px solid #174D3D; outline-offset: 3px; }
:root[data-theme="dark"] .pgm-card { box-shadow: 0 10px 28px rgba(0,0,0,.4); }

/* Hover glow behind the card content */
.pgm-glow {
    position: absolute; z-index: -1; top: -35%; right: -25%; width: 260px; height: 260px; border-radius: 50%;
    background: radial-gradient(circle, color-mix(in srgb, var(--pgm-c, #0B4E3D) 32%, transparent), transparent 70%);
    opacity: 0; transform: scale(.55); transition: opacity .55s ease, transform .55s ease;
}
.pgm-card:hover .pgm-glow, .pgm-card:focus-visible .pgm-glow { opacity: 1; transform: scale(1); }

/* Gradient icon tile */
.pgm-icon {
    width: 70px; height: 70px; border-radius: 20px; margin-bottom: 1.3rem;
    display: grid; place-items: center; color: #fff;
    background: linear-gradient(135deg, var(--pgm-c, #0B4E3D), #174D3D);
    box-shadow: 0 12px 24px color-mix(in srgb, var(--pgm-c, #0B4E3D) 42%, transparent);
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
    font-weight: 700; font-size: .92rem; letter-spacing: .01em; color: #0B4E3D;
    transition: gap .3s ease, color .3s ease;
}
.pgm-link svg { width: 18px; height: 18px; transition: transform .3s ease; }
.pgm-card:hover .pgm-link, .pgm-card:focus-visible .pgm-link { gap: .7rem; color: #174D3D; }
.pgm-card:hover .pgm-link svg, .pgm-card:focus-visible .pgm-link svg { transform: translateX(4px); }
:root[data-theme="dark"] .pgm-link { color: #4CA383; }
:root[data-theme="dark"] .pgm-card:hover .pgm-link,
:root[data-theme="dark"] .pgm-card:focus-visible .pgm-link { color: #5eead4; }
@media (prefers-color-scheme: dark) {
    :root:not([data-theme="light"]) .pgm-link { color: #4CA383; }
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
            <h2 class="section-title"><?= e(home_heading('programs')) ?></h2>
            <?php if ($pgmSub !== ''): ?><p class="section-subtitle"><?= e($pgmSub) ?></p><?php endif; ?>
        </div>
        <div class="pgm-grid">
            <?php foreach ($programs as $i => $p): ?>
                <a class="pgm-card reveal <?= 'delay-' . (($i % 3) + 1) ?>" href="<?= e($p['slug'] ? url('programs?slug=' . $p['slug']) : url('programs')) ?>" style="--pgm-c:<?= e($p['color'] ?: '#0B4E3D') ?>;">
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
<?php endif; ?>

<!-- ============================== UPCOMING EVENTS ============================== -->
<?php
/* Fallback: a PUBLIC empty state. An empty calendar is a real, useful fact — the
   visitor learns the dates are not out yet and gets a way to be involved anyway —
   so unlike the video band this section keeps its place rather than disappearing.
   Nothing is invented: no placeholder event, no fictional date. */
if (home_section_visible('events')):
?>
<section class="section">
    <div class="container">
        <div class="section-head left flex justify-between items-center flex-wrap gap-2">
            <div>
                <span class="eyebrow">Join Us</span>
                <h2 class="section-title mb-0"><?= e(home_heading('events')) ?></h2>
            </div>
            <?php if ($events): ?>
                <a class="btn btn-outline btn-sm" href="<?= e(url('events')) ?>">View All Events</a>
            <?php endif; ?>
        </div>
        <?php if (!$events): ?>
            <?= home_empty([
                'icon'    => 'calendar-days',
                'title'   => 'No dates on the calendar just yet',
                'text'    => 'Community days, health camps and workshops are listed here as soon as they are confirmed. In the meantime you are welcome to join us as a volunteer.',
                'actions' => ['Become a volunteer' => 'volunteer'],
                'manage'  => ['Add an event' => 'events'],
            ]) ?>
        <?php else: ?>
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
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- ============================== DONATION TIERS ============================== -->
<?php
/* Amount tiers with the tangible thing each one funds. Deliberately NOT a
   checkout: it is a considered ask that hands off to /donate with the amount
   carried through, so the trust copy and 80G detail live in one place.
   Edited under Settings → Donation Tiers (`home_donation_tiers`); the fallback
   set lives with the accessor in includes/homepage.php. */
if (home_section_visible('donation_tiers')):
    $donationTiers = home_donation_tiers();
    $dtSub         = home_subtitle('donation_tiers');
?>
<section class="section donate-band" aria-labelledby="donateHeading">
    <div class="container">
        <div class="section-head is-centered">
            <span class="eyebrow">Support our mission</span>
            <h2 class="section-title" id="donateHeading"><?= e(home_heading('donation_tiers')) ?></h2>
            <?php if ($dtSub !== ''): ?><p class="section-subtitle"><?= e($dtSub) ?></p><?php endif; ?>
        </div>

        <ul class="dt-grid">
            <?php foreach ($donationTiers as $i => $t):
                $amt = (int) ($t['amount'] ?? 0);
                if ($amt <= 0) { continue; } ?>
                <li class="dt-card reveal<?= $i ? ' delay-' . min(3, $i) : '' ?>">
                    <span class="dt-ico" aria-hidden="true"><?= lucide($t['icon'] ?: 'heart') ?></span>
                    <span class="dt-amount"><?= e(money($amt)) ?></span>
                    <strong class="dt-title"><?= e($t['title'] ?? '') ?></strong>
                    <p class="dt-impact"><?= e($t['impact'] ?? '') ?></p>
                    <a class="btn btn-primary btn-sm dt-cta" href="<?= e(url('donate') . '?amount=' . $amt) ?>">
                        Give <?= e(money($amt)) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="dt-foot">
            <ul class="dt-trust">
                <li><?= lucide('lock') ?> Secure payment</li>
                <li><?= lucide('receipt') ?> 80G tax receipt</li>
                <li><?= lucide('eye') ?> Transparent reporting</li>
                <li><?= lucide('repeat') ?> One-time or monthly</li>
            </ul>
            <a class="btn btn-outline" href="<?= e(url('donate')) ?>">Choose another amount <?= lucide('arrow-right') ?></a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================== DONATE / VOLUNTEER CTA ============================== -->
<?php /* Two bands, one flat brand field each.

         Both used to be gradient cards with white text: the donate half on the
         green gradient, the volunteer half on --grad-accent (orange → gold).
         White on that gold end is roughly a 1.8:1 pair — the paragraph was the
         one piece of genuinely illegible copy on the homepage. The volunteer
         band now follows the brand rule for this section: warm yellow field,
         forest-green type (7.95:1), with orange kept for the donate ask. */ ?>
<?php if (home_section_visible('cta')): ?>
<section class="section">
    <div class="container grid grid-2 gap-4">
        <div class="cta-band is-green on-dark reveal">
            <h2 class="text-white">Your Support Changes Lives</h2>
            <p>Every contribution helps us reach more children, families and communities with the resources they need to thrive.</p>
            <div class="cta-actions">
                <a class="btn btn-primary btn-lg" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Donate Now</a>
            </div>
        </div>
        <div class="cta-band is-yellow reveal delay-1">
            <h2>Your Time Can Create Change</h2>
            <p>Give your time and skills to a cause that matters. Join our community of changemakers across Bihar.</p>
            <div class="cta-actions">
                <a class="btn btn-secondary btn-lg" href="<?= e(url('volunteer')) ?>"><?= lucide('handshake') ?> Become a Volunteer</a>
                <a class="btn btn-outline btn-lg" href="<?= e(url('become-partner')) ?>">Partner With Us</a>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================== TESTIMONIALS ============================== -->
<?php if (home_section_visible('testimonials')): $tsSub = home_subtitle('testimonials'); ?>
<section class="section section-alt">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Voices of Change</span>
            <h2 class="section-title"><?= e(home_heading('testimonials')) ?></h2>
            <?php if ($tsSub !== ''): ?><p class="section-subtitle"><?= e($tsSub) ?></p><?php endif; ?>
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
<?php endif; ?>

<!-- ============================== LATEST BLOGS ============================== -->
<?php
/* Fallback: a PUBLIC empty state. "Nothing published yet" is honest and the
   visitor is handed somewhere real to go instead. No lorem-ipsum article cards:
   a fake headline in a card that links nowhere would be worse than the silence
   it replaces. */
if (home_section_visible('blogs')):
?>
<section class="section">
    <div class="container">
        <div class="section-head left flex justify-between items-center flex-wrap gap-2">
            <div>
                <span class="eyebrow">From Our Blog</span>
                <h2 class="section-title mb-0"><?= e(home_heading('blogs')) ?></h2>
            </div>
            <?php if ($blogs): ?>
                <a class="btn btn-outline btn-sm" href="<?= e(url('blogs')) ?>">Read All</a>
            <?php endif; ?>
        </div>
        <?php if (!$blogs): ?>
            <?= home_empty([
                'icon'    => 'pen-line',
                'title'   => 'The first stories are still being written',
                'text'    => 'Field reports, programme updates and news from the communities we work with will appear here as they are published.',
                'actions' => ['Explore our programmes' => 'programs'],
                'manage'  => ['Write a post' => 'blogs'],
            ]) ?>
        <?php else: ?>
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
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- ============================== FIELD GALLERY ============================== -->
<?php /* An immersive masonry wall rather than a grid of identical squares.
         Frames cycle through four crops — tall, square, wide, portrait — so no
         two neighbours share a shape, and the columns pack with no holes at any
         album count. Each frame carries its caption, revealed with the overlay
         on hover; the whole tile opens the lightbox, and without JS the href
         still navigates to the album.

         Fallback: an EDITOR-ONLY empty state. This section IS its photographs —
         with none there is nothing for a visitor to look at, and the "View full
         gallery" button would lead to an equally empty page. Albums without a
         cover photo are filtered out first, because the old `if ($albums)` gate
         let an album with no media render an empty wall. */
$albums = array_values(array_filter($albums, static fn(array $a): bool => !empty($a['thumb'])));
if (home_section_visible('gallery')):
    $galSub = home_subtitle('gallery');
?>
    <?php if ($albums): ?>
<section class="section section-soft">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Moments</span>
            <h2 class="section-title"><?= e(home_heading('gallery')) ?></h2>
            <?php if ($galSub !== ''): ?><p class="section-subtitle"><?= e($galSub) ?></p><?php endif; ?>
        </div>
        <div class="gal-wall">
            <?php foreach ($albums as $a): ?>
                <a class="gal-frame" href="<?= e(url('gallery?album=' . $a['slug'])) ?>"
                   data-lightbox="<?= e(upload_url($a['thumb'])) ?>"
                   data-caption="<?= e($a['title']) ?>">
                    <img src="<?= e(upload_url($a['thumb'])) ?>" alt="<?= e($a['title']) ?>" loading="lazy" decoding="async">
                    <span class="gal-veil" aria-hidden="true"></span>
                    <span class="gal-cap">
                        <span class="gal-cap-txt"><?= e($a['title']) ?></span>
                        <span class="gal-cap-ico" aria-hidden="true"><?= lucide('arrow-up-right') ?></span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-4"><a class="btn btn-outline" href="<?= e(url('gallery')) ?>">View Full Gallery <?= lucide('arrow-right') ?></a></div>
    </div>
</section>
    <?php else:
        $galNote = home_empty([
            'audience' => 'editor',
            'icon'     => 'image',
            'title'    => 'The photo wall has nothing to show yet',
            'text'     => 'It fills itself from your gallery albums — each album needs at least one photograph before it appears here. Visitors are not seeing an empty wall in its place.',
            'manage'   => ['Manage albums' => 'gallery-albums', 'Upload photos' => 'gallery-media'],
        ]);
        if ($galNote !== ''): ?>
        <section class="section section-soft"><div class="container"><?= $galNote ?></div></section>
    <?php endif; endif; ?>
<?php endif; ?>

<!-- ============================== PARTNERS & SPONSORS ============================== -->
<?php
/* Fallback: a PUBLIC invitation. An NGO with no supporters listed yet has an ask,
   not an absence — so the wall becomes the invitation to be the first name on it.
   No placeholder logos and no invented partner names; the subtitle, which claims
   organisations already stand with us, is withheld until one does. */
if (home_section_visible('partners')):
    $orgs   = array_merge($partners, $sponsors);
    $pnSub  = home_subtitle('partners');
?>
<section class="section section-partners" aria-labelledby="partnersHeading">
    <span class="pn-blob pn-b1" aria-hidden="true"></span>
    <span class="pn-blob pn-b2" aria-hidden="true"></span>
    <div class="container text-center">
        <span class="eyebrow" style="justify-content:center;">Together We Achieve More</span>
        <h2 class="section-title" id="partnersHeading"><?= e(home_heading('partners')) ?></h2>
        <?php if ($orgs && $pnSub !== ''): ?><p class="section-subtitle"><?= e($pnSub) ?></p><?php endif; ?>
    </div>

    <?php if (!$orgs): ?>
    <div class="container">
        <?= home_empty([
            'icon'    => 'handshake',
            'title'   => 'There is room here for your organisation',
            'text'    => 'We work with schools, companies and foundations who fund, mentor and deliver programmes alongside our teams. Partnerships and sponsors are listed on this wall.',
            'actions' => ['Partner with us' => 'become-partner', 'Talk to us' => 'contact'],
            'manage'  => ['Add a partner' => 'partners', 'Add a sponsor' => 'sponsors'],
        ]) ?>
    </div>
    <?php else: ?>

    <?php
    $cards = [];
    // Monogram fallback so the wall looks designed even before logos are uploaded.
    $initials = static function (string $n): string {
        $w = preg_split('/\s+/', trim($n)) ?: [];
        $s = '';
        foreach ($w as $part) { if ($part !== '' && ctype_alpha($part[0])) { $s .= strtoupper($part[0]); } if (strlen($s) === 2) break; }
        return $s !== '' ? $s : strtoupper(substr($n, 0, 1));
    };
    /* A static wall, rendered once. This used to emit the list TWICE — the second
       copy aria-hidden — because the marks scrolled past on an infinite marquee
       and a seamless loop needs the duplicate. The wall needs neither, so every
       supporter is now announced exactly once and there is no perpetual motion
       (and no hover-to-read tooltip standing between a visitor and a name). */
    foreach ($orgs as $org):
        $hasLogo = !empty($org['logo']);
        $site    = trim((string) ($org['website'] ?? ''));
        $tag     = $site !== '' ? 'a' : 'div';
        $attrs   = $site !== ''
            ? ' href="' . e(preg_match('#^https?://#i', $site) ? $site : 'https://' . $site) . '" target="_blank" rel="noopener noreferrer"'
            : '';
        $cards[] = '<' . $tag . ' class="pn-card"' . $attrs . '>'
            . '<span class="pn-inner">'
            . ($hasLogo
                ? '<img class="pn-logo" src="' . e(upload_url($org['logo'])) . '" alt="' . e($org['name']) . '" width="120" height="54" loading="lazy" decoding="async">'
                : '<span class="pn-mono" aria-hidden="true">' . e($initials((string) $org['name'])) . '</span>'
                  . '<span class="pn-name">' . e($org['name']) . '</span>')
            . '</span>'
            . ($site !== '' ? '<span class="pn-out" aria-hidden="true">' . lucide('arrow-up-right') . '</span>' : '')
            . '</' . $tag . '>';
    endforeach;
    ?>
    <div class="container">
        <div class="pn-wall"><?= implode('', $cards) ?></div>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
