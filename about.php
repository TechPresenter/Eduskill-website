<?php
/**
 * =============================================================================
 *  About Us — the story, values, impact and leadership of EDUSKILL INDIA FOUNDATION
 *  Foundation. Every section is DB-driven with graceful fallbacks so the page
 *  looks complete and polished before any content is seeded.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* ---- Data ------------------------------------------------------------- */
$stats  = get_all('achievements', ['status' => 1], 'sort_order ASC', 8);
$leaders = db_all(
    "SELECT * FROM team_members
     WHERE is_leadership = 1 AND status = 1
     ORDER BY sort_order ASC, id ASC
     LIMIT 3"
);

/* Fallback impact stats if none seeded */
if (!$stats) {
    $stats = [
        ['icon' => 'heart-handshake', 'value' => 25000, 'suffix' => '+', 'prefix' => '', 'title' => 'Lives Impacted',      'description' => 'across Bihar and beyond'],
        ['icon' => 'map-pin',         'value' => 60,    'suffix' => '+', 'prefix' => '', 'title' => 'Villages Reached',     'description' => 'with grassroots programs'],
        ['icon' => 'circle-check',    'value' => 120,   'suffix' => '+', 'prefix' => '', 'title' => 'Projects Completed',   'description' => 'delivered with accountability'],
        ['icon' => 'users',           'value' => 800,   'suffix' => '+', 'prefix' => '', 'title' => 'Active Volunteers',    'description' => 'giving time and skills'],
    ];
}

/* Fallback leadership — the founding directors of the Foundation */
if (!$leaders) {
    $leaders = [];
}

/* Curated core values that guide our work */
$values = [
    ['icon' => 'heart',     'title' => 'Compassion',    'text' => 'We lead with empathy, meeting every community with dignity, respect and genuine care.'],
    ['icon' => 'shield',    'title' => 'Integrity',     'text' => 'We act ethically and honestly in everything we do — accountable to the people we serve.'],
    ['icon' => 'eye',       'title' => 'Transparency',  'text' => 'Every rupee is tracked from donation to delivery, with open reporting for our supporters.'],
    ['icon' => 'zap',       'title' => 'Empowerment',   'text' => 'We build capability, not dependency — helping communities lead their own change.'],
    ['icon' => 'handshake', 'title' => 'Collaboration', 'text' => 'We partner with communities, volunteers and institutions to multiply our collective impact.'],
    ['icon' => 'sprout',    'title' => 'Sustainability','text' => 'We design programs for lasting change that continue to grow long after we arrive.'],
];

seo_set([
    'title'       => 'About Us',
    'description' => get_setting('about_meta_description', 'Learn about EDUSKILL INDIA FOUNDATION — a registered non-profit in Patna, Bihar working across education, healthcare, skill development and relief to empower communities and create lasting change.'),
    'page_key'    => 'about',
    'type'        => 'website',
]);

$page_hero = [
    'title'      => 'Who We Are',
    'subtitle'   => 'A registered non-profit from Patna, Bihar — empowering communities, spreading hope and creating lasting change.',
    'breadcrumb' => [['label' => 'About Us']],
];

include __DIR__ . '/includes/header.php';
?>

<style>
/* ============================================================================
   ABOUT PAGE — page-specific premium layer (green / teal / gold brand)
   Scoped to .about-page so it never leaks to other pages. Base --grad-brand
   is re-pointed to the Foundation's forest-green identity here.
   ========================================================================== */
.about-page{
    --grad-brand: linear-gradient(135deg,#0B4E3D 0%,#174D3D 100%);
    --brand-600:#0B4E3D;
    --shadow-brand:0 12px 30px rgba(20,83,45,.28);
    --ring:rgba(11,78,61,.18);
    --pwf-gold:#F15A24;
    --pwf-teal:#174D3D;
}

/* ---- Unique hero treatment (page-hero is rendered by header.php) -------- */
.about-hero-mode .page-hero{
    background:linear-gradient(130deg,#0e3d24 0%,#0B4E3D 46%,#174D3D 100%);
}
.about-hero-mode .page-hero::before{
    content:"";position:absolute;inset:0;z-index:0;opacity:.5;
    background-image:radial-gradient(rgba(255,255,255,.16) 1px,transparent 1.6px);
    background-size:22px 22px;
    -webkit-mask-image:linear-gradient(180deg,#000,transparent 88%);
            mask-image:linear-gradient(180deg,#000,transparent 88%);
}
.about-hero-mode .page-hero::after{
    background:
        radial-gradient(620px circle at 82% -25%, rgba(241,90,36,.30), transparent 60%),
        radial-gradient(560px circle at 10% 120%, rgba(23,77,61,.42), transparent 55%);
}
.about-hero-mode .page-hero h1{
    font-size:clamp(2.1rem,5vw,3.15rem);letter-spacing:-.02em;line-height:1.08;
}
.about-hero-mode .page-hero .breadcrumb{margin-bottom:1rem;}

/* Floating trust strip that bridges the hero and the first section */
.about-trust{
    max-width:var(--container,1180px);margin:-2.6rem auto 0;padding:0 1.25rem;position:relative;z-index:6;
}
.about-trust .trust-card{
    display:flex;flex-wrap:wrap;gap:.6rem 1.4rem;align-items:center;justify-content:center;
    padding:1rem 1.6rem;border-radius:18px;
    background:rgba(255,255,255,.82);border:1px solid rgba(11,78,61,.12);
    box-shadow:0 18px 50px -22px rgba(15,61,36,.55);
    backdrop-filter:blur(14px) saturate(150%);-webkit-backdrop-filter:blur(14px) saturate(150%);
}
:root[data-theme="dark"] .about-trust .trust-card{background:rgba(20,30,32,.72);border-color:rgba(255,255,255,.08);}
.about-trust .t-item{display:inline-flex;align-items:center;gap:.5rem;font-weight:600;font-size:.9rem;color:var(--text-soft);}
.about-trust .t-item svg.lucide{width:18px;height:18px;color:#0B4E3D;}
.about-trust .t-sep{width:1px;height:20px;background:var(--border);}
@media(max-width:640px){.about-trust .t-sep{display:none;}}

/* ---- Ornamental section divider ---------------------------------------- */
.pwf-orn{display:flex;align-items:center;justify-content:center;gap:.9rem;margin:0 auto 1.15rem;}
.pwf-orn .ln{height:2px;width:min(72px,16vw);border-radius:2px;
    background:linear-gradient(90deg,transparent,var(--pwf-teal));}
.pwf-orn .ln.r{background:linear-gradient(90deg,var(--pwf-teal),transparent);}
.pwf-orn .dot{display:grid;place-items:center;width:38px;height:38px;border-radius:12px;
    background:linear-gradient(135deg,#0B4E3D,#174D3D);color:#fff;box-shadow:0 8px 20px rgba(20,83,45,.34);}
.pwf-orn .dot svg.lucide{width:19px;height:19px;}
.pwf-orn.light .ln{background:linear-gradient(90deg,transparent,rgba(255,255,255,.6));}
.pwf-orn.light .ln.r{background:linear-gradient(90deg,rgba(255,255,255,.6),transparent);}
.pwf-orn.light .dot{background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.35);}

/* ---- Typography + card polish ------------------------------------------ */
.about-page .section-title{letter-spacing:-.02em;}
.about-page .card-3d::before{
    background:radial-gradient(600px circle at var(--mx,50%) var(--my,0%),rgba(23,77,61,.14),transparent 42%);
}
.about-page .card-3d{transition:transform .35s cubic-bezier(.16,1,.3,1),box-shadow .35s cubic-bezier(.16,1,.3,1),border-color .35s;}
.about-page .card-3d:hover{border-color:rgba(23,77,61,.35);}
.about-page .icon-badge{transition:transform .35s cubic-bezier(.16,1,.3,1);}
.about-page .card-3d:hover .icon-badge,
.about-page .value-card:hover .icon-badge{transform:rotate(-6deg) scale(1.08);}
.about-page .badge-brand{background:rgba(11,78,61,.12);color:#0B4E3D;}
.about-page .chip svg.lucide{width:15px;height:15px;}

/* Value cards get a subtle top gradient rule on hover */
.about-page .value-card{position:relative;}
.about-page .value-card::after{
    content:"";position:absolute;left:0;right:0;top:0;height:3px;border-radius:var(--radius-lg) var(--radius-lg) 0 0;
    background:linear-gradient(90deg,#0B4E3D,#174D3D,var(--pwf-gold));
    transform:scaleX(0);transform-origin:left;transition:transform .4s cubic-bezier(.16,1,.3,1);
}
.about-page .value-card:hover::after{transform:scaleX(1);}

/* Facts card (Who We Are aside) */
.about-page .facts-card .fact-row{display:flex;gap:.85rem;align-items:flex-start;padding:.55rem 0;}
.about-page .facts-card .fact-row + .fact-row{border-top:1px dashed var(--border);}
.about-page .facts-card .fi{flex-shrink:0;width:38px;height:38px;border-radius:11px;display:grid;place-items:center;
    background:linear-gradient(135deg,rgba(11,78,61,.14),rgba(23,77,61,.14));color:#0B4E3D;}
.about-page .facts-card .fi svg.lucide{width:18px;height:18px;}
.about-page .facts-card .fk{font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);font-weight:700;}
.about-page .facts-card .fv{font-weight:600;color:var(--text);line-height:1.35;}

/* Story feature cards */
.about-page .story-card{display:flex;gap:1rem;align-items:flex-start;}
.about-page .story-card .icon-badge{margin-bottom:0;}

/* Impact / counters on the green band */
.about-page .section-brand{position:relative;overflow:hidden;}
.about-page .section-brand::after{
    content:"";position:absolute;inset:0;z-index:0;pointer-events:none;opacity:.5;
    background:
        radial-gradient(480px circle at 12% 0%,rgba(241,90,36,.28),transparent 55%),
        radial-gradient(520px circle at 90% 110%,rgba(255,255,255,.14),transparent 55%);
}
.about-page .section-brand > .container{position:relative;z-index:1;}

/* CTA cards */
.about-page .cta-card{position:relative;overflow:hidden;border:0;}
.about-page .cta-card .cta-ico{width:56px;height:56px;border-radius:16px;display:grid;place-items:center;
    background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.28);margin-bottom:1rem;}
.about-page .cta-card .cta-ico svg.lucide{width:26px;height:26px;color:#fff;}
.about-page .cta-card .btn svg.lucide{color:inherit;}

/* Leadership avatar zoom */
.about-page .card-media{overflow:hidden;}
.about-page .card-media img{transition:transform .55s cubic-bezier(.16,1,.3,1);}
.about-page .card:hover .card-media img{transform:scale(1.06);}

/* Responsive niceties */
@media(max-width:900px){
    .about-page .story-card{gap:.85rem;}
}
@media(max-width:600px){
    .about-trust{margin-top:-2rem;}
    .about-trust .trust-card{padding:.9rem 1.1rem;gap:.5rem 1rem;}
}
</style>

<div class="about-page">

<!-- Floating trust strip bridging hero + content -->
<div class="about-trust reveal">
    <div class="trust-card">
        <span class="t-item"><?= lucide('badge-check') ?> Registered Non-Profit</span>
        <span class="t-sep"></span>
        <span class="t-item"><?= lucide('map-pin') ?> Patna, Bihar</span>
        <span class="t-sep"></span>
        <span class="t-item"><?= lucide('shield-check') ?> Transparent &amp; Accountable</span>
        <span class="t-sep"></span>
        <span class="t-item"><?= lucide('heart-handshake') ?> Community-Led</span>
    </div>
</div>

<!-- ============================== WHO WE ARE ============================== -->
<section class="section">
    <div class="container grid grid-2 items-center gap-4">
        <div class="reveal" data-reveal="right">
            <span class="eyebrow">Who We Are</span>
            <h2 class="section-title"><?= e(get_setting('home_about_title', 'A movement for dignity, hope and opportunity')) ?></h2>
            <p class="text-muted"><?= e(get_setting('home_about_text', 'EDUSKILL INDIA FOUNDATION is a registered non-profit. Since our inception we have worked hand-in-hand with communities to deliver education, healthcare, skill development and emergency relief — always with compassion, transparency and accountability. We believe lasting change is built alongside people, not handed to them.')) ?></p>
            <p class="text-muted mt-2"><?= e(get_setting('about_registration_text', SITE_NAME . ' is a Government-registered Section 8 Non-Profit Organization incorporated under the Companies Act, 2013. The Foundation is officially registered with the Ministry of Corporate Affairs (MCA) and enrolled on the NITI Aayog NGO Darpan Portal, enabling collaboration with government departments, CSR partners, educational institutions, and social organizations.')) ?></p>
            <ul class="list-check mt-3">
                <li>Transparent, community-led programs with measurable impact</li>
                <li>Registered non-profit governed by dedicated directors</li>
                <li>Every rupee tracked — from donation to delivery</li>
            </ul>
            <div class="flex flex-wrap gap-2 mt-4">
                <a class="btn btn-primary" href="<?= e(url('programs')) ?>"><?= lucide('compass') ?> Explore Our Programs</a>
                <a class="btn btn-outline" href="<?= e(url('contact')) ?>"><?= lucide('mail') ?> Get In Touch</a>
            </div>
        </div>
        <div class="card-3d facts-card reveal delay-1" data-reveal="left">
            <span class="eyebrow">Registered Non-Profit</span>
            <h3 class="card-title mb-2"><?= e(get_setting('site_name', SITE_NAME)) ?></h3>
            <p class="card-text mb-3"><?= e(SITE_TAGLINE) ?></p>
            <div class="divider"></div>
            <div class="fact-row">
                <span class="fi"><?= lucide('id-card') ?></span>
                <span><span class="fk">CIN</span><br><span class="fv"><?= e(get_setting('company_cin', SITE_CIN)) ?></span></span>
            </div>
            <div class="fact-row">
                <span class="fi"><?= lucide('map-pin') ?></span>
                <span><span class="fk">Location</span><br><span class="fv"><?= e(get_setting('contact_address', SITE_ADDRESS)) ?></span></span>
            </div>
            <div class="fact-row">
                <span class="fi"><?= lucide('badge-check') ?></span>
                <span><span class="fk">NGO Darpan ID</span><br><span class="fv"><?= e(get_setting('ngo_darpan_id', SITE_DARPAN_ID)) ?></span></span>
            </div>
            <div class="fact-row">
                <span class="fi"><?= lucide('mail') ?></span>
                <span><span class="fk">Email</span><br><span class="fv"><?= e(get_setting('contact_email', SITE_EMAIL)) ?></span></span>
            </div>
        </div>
    </div>
</section>

<!-- ==================== GOVERNMENT REGISTRATIONS & COMPLIANCE ==================== -->
<section class="section section-soft">
    <div class="container">
        <?php render_registrations_grid(); ?>
    </div>
</section>

<!-- ============================== OUR STORY ============================== -->
<section class="section section-soft">
    <div class="container grid grid-2 items-center gap-4">
        <div class="reveal" data-reveal="right">
            <span class="eyebrow">Our Journey</span>
            <h2 class="section-title"><?= e(get_setting('about_story_title', 'Our Story')) ?></h2>
            <div class="prose text-muted">
                <p><?= e(get_setting('about_story_text', 'EDUSKILL INDIA FOUNDATION was born from a simple conviction: that every person — regardless of where they are born — deserves the chance to live with dignity and reach their full potential. What began as a small group of volunteers responding to urgent needs in and around Patna has grown into a structured movement serving communities across Bihar.')) ?></p>
                <p><?= e(get_setting('about_story_text_2', 'From free medical camps and learning kits for underprivileged children, to skill training for women and rapid disaster relief, our work is guided by the people we serve. We listen first, act with transparency, and measure the difference we make. Every programme is designed not just to help today, but to build the capability that carries a community forward for years to come.')) ?></p>
            </div>
            <a class="btn btn-primary mt-3" href="<?= e(url('projects')) ?>"><?= lucide('arrow-right') ?> See Our Work</a>
        </div>
        <div class="grid gap-3 reveal delay-1" data-reveal="left">
            <div class="card-3d story-card">
                <div class="icon-badge"><?= lucide('globe') ?></div>
                <div>
                    <h3 class="card-title">Where We Work</h3>
                    <p class="card-text">Rooted in Patna, Bihar, we reach rural and urban communities that are too often left behind — bringing services directly to their doorstep.</p>
                </div>
            </div>
            <div class="card-3d story-card">
                <div class="icon-badge accent"><?= lucide('target') ?></div>
                <div>
                    <h3 class="card-title">What Drives Us</h3>
                    <p class="card-text">A belief that change is most powerful when it is community-led, transparent and built to last well beyond our involvement.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================== CORE VALUES ============================== -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <div class="pwf-orn reveal" aria-hidden="true"><span class="ln"></span><span class="dot"><?= lucide('sprout') ?></span><span class="ln r"></span></div>
            <span class="eyebrow" style="justify-content:center;">What We Stand For</span>
            <h2 class="section-title">Our Core Values</h2>
            <p class="section-subtitle">The principles that shape every decision we make and every community we serve.</p>
        </div>
        <div class="grid grid-3">
            <?php foreach ($values as $i => $v): ?>
                <div class="card-3d value-card reveal <?= 'delay-' . (($i % 3) + 1) ?>">
                    <div class="icon-badge"><?= lucide($v['icon']) ?></div>
                    <h3 class="card-title"><?= e($v['title']) ?></h3>
                    <p class="card-text"><?= e($v['text']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== IMPACT STATS ============================== -->
<section class="section section-brand">
    <div class="container">
        <div class="section-head">
            <div class="pwf-orn light reveal" aria-hidden="true"><span class="ln"></span><span class="dot"><?= lucide('trending-up') ?></span><span class="ln r"></span></div>
            <span class="eyebrow" style="color:#d9f4e6;justify-content:center;">Our Impact So Far</span>
            <h2 class="section-title text-white">Numbers That Tell A Story</h2>
        </div>
        <div class="counter-grid">
            <?php foreach ($stats as $s): ?>
                <?php
                /* lucide() renders emoji values as text and slugs as glyphs —
                   consistent with every other page that shows these icons. */
                $ci = trim((string) ($s['icon'] ?? '')) ?: 'star';
                ?>
                <div class="counter-item reveal">
                    <div class="icon-badge" style="background:rgba(255,255,255,.18);"><?= lucide($ci) ?></div>
                    <div class="counter-value">
                        <?= e($s['prefix'] ?? '') ?><span data-counter="<?= (int) ($s['value'] ?? 0) ?>">0</span><?= e($s['suffix'] ?? '') ?>
                    </div>
                    <div class="counter-label"><?= e($s['title']) ?></div>
                    <?php if (!empty($s['description'])): ?>
                        <small style="color:rgba(255,255,255,.8);"><?= e($s['description']) ?></small>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== MISSION & VISION ============================== -->
<section class="section section-alt">
    <div class="container">
        <div class="section-head">
            <div class="pwf-orn reveal" aria-hidden="true"><span class="ln"></span><span class="dot"><?= lucide('compass') ?></span><span class="ln r"></span></div>
            <span class="eyebrow" style="justify-content:center;">Our Purpose</span>
            <h2 class="section-title">Mission &amp; Vision</h2>
            <p class="section-subtitle">A clear direction for the change we are working to create, together.</p>
        </div>
        <div class="grid grid-2 gap-4">
            <div class="card-3d reveal" data-reveal="up">
                <div class="icon-badge"><?= lucide('target') ?></div>
                <h3 class="card-title">Our Mission</h3>
                <p class="card-text"><?= e(get_setting('mission_short', 'To empower underserved communities by providing access to quality education, healthcare and sustainable livelihoods — delivered with compassion, transparency and accountability.')) ?></p>
            </div>
            <div class="card-3d reveal delay-1" data-reveal="up">
                <div class="icon-badge accent"><?= lucide('sparkles') ?></div>
                <h3 class="card-title">Our Vision</h3>
                <p class="card-text"><?= e(get_setting('vision_short', 'An equitable society where every individual has the opportunity to live with dignity, thrive and reach their full potential.')) ?></p>
            </div>
        </div>
    </div>
</section>

<!-- ============================== LEADERSHIP PREVIEW ============================== -->
<section class="section">
    <div class="container">
        <div class="section-head left flex justify-between items-center flex-wrap gap-2">
            <div>
                <span class="eyebrow">Guided By Purpose</span>
                <h2 class="section-title mb-0">Our Leadership</h2>
            </div>
            <a class="btn btn-outline btn-sm" href="<?= e(url('team')) ?>"><?= lucide('users') ?> Meet The Full Team</a>
        </div>
        <?php if ($leaders): ?>
        <div class="grid grid-3">
            <?php foreach ($leaders as $i => $m): $socials = json_column($m['socials'] ?? null); ?>
                <article class="card reveal <?= 'delay-' . (($i % 3) + 1) ?>">
                    <div class="card-media">
                        <img src="<?= e(image_url($m['photo'] ?? null, 'avatar')) ?>" alt="<?= e($m['name']) ?>" loading="lazy" width="600" height="600" style="aspect-ratio:1/1;object-fit:cover;">
                    </div>
                    <div class="card-body text-center">
                        <h3 class="card-title mb-0"><?= e($m['name']) ?></h3>
                        <?php if (!empty($m['designation'])): ?>
                            <span class="badge badge-brand mt-1"><?= e($m['designation']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($m['bio'])): ?>
                            <p class="card-text mt-2"><?= e(excerpt($m['bio'], 26)) ?></p>
                        <?php endif; ?>
                        <?php if ($socials || !empty($m['email'])): ?>
                            <div class="flex justify-center gap-2 mt-2 flex-wrap">
                                <?php if (!empty($m['email'])): ?>
                                    <a class="chip" href="mailto:<?= e($m['email']) ?>"><?= lucide('mail') ?> Email</a>
                                <?php endif; ?>
                                <?php foreach ($socials as $platform => $link): if (empty($link)) continue; ?>
                                    <a class="chip" href="<?= e($link) ?>" target="_blank" rel="noopener"><?= social_svg((string) $platform) ?> <?= e(ucfirst((string) $platform)) ?></a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="icon-badge"><?= lucide('users') ?></div>
            <h3>Our team is growing</h3>
            <p class="text-muted">Leadership profiles will appear here soon. In the meantime, reach out to learn more about who we are.</p>
            <a class="btn btn-primary mt-2" href="<?= e(url('contact')) ?>"><?= lucide('mail') ?> Contact Us</a>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ============================== DONATE / VOLUNTEER CTA ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="section-head">
            <div class="pwf-orn reveal" aria-hidden="true"><span class="ln"></span><span class="dot"><?= lucide('heart-handshake') ?></span><span class="ln r"></span></div>
            <span class="eyebrow" style="justify-content:center;">Be Part Of The Change</span>
            <h2 class="section-title">Join Our Mission</h2>
            <p class="section-subtitle">Whether you give or you give your time, you help us reach one more child, one more family, one more community.</p>
        </div>
        <div class="grid grid-2 gap-4">
            <div class="card-3d cta-card reveal" data-reveal="up" style="background:var(--grad-brand);color:#fff;">
                <div class="cta-ico"><?= lucide('heart') ?></div>
                <h2 class="text-white">Your Support Changes Lives</h2>
                <p style="color:rgba(255,255,255,.9);">Every contribution helps us deliver education, healthcare and relief to the communities that need it most.</p>
                <a class="btn btn-white btn-lg mt-2" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Donate Now</a>
            </div>
            <div class="card-3d cta-card reveal delay-1" data-reveal="up" style="background:var(--grad-accent);color:#fff;">
                <div class="cta-ico"><?= lucide('hand-heart') ?></div>
                <h2 class="text-white">Become a Volunteer</h2>
                <p style="color:rgba(255,255,255,.92);">Give your time and skills to a cause that matters. Join our community of changemakers across Bihar.</p>
                <a class="btn btn-white btn-lg mt-2" href="<?= e(url('volunteer')) ?>"><?= lucide('handshake') ?> Join Us</a>
            </div>
        </div>
    </div>
</section>

</div><!-- /.about-page -->

<script>
/* Flag the body so the page-hero picks up the About-only styling above. */
document.body.classList.add('about-hero-mode');
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
