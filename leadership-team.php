<?php
/**
 * =============================================================================
 *  Leadership Team — the Board & senior leadership of EDUSKILL INDIA FOUNDATION.
 *  Profiles are DB-driven from `team_members` (is_leadership = 1) with a graceful
 *  fallback to the registered directors, plus a governance overview and a preview
 *  of the wider team linking through to the full team page.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* ---- Data ------------------------------------------------------------- */
$leaders = db_all(
    "SELECT * FROM team_members
     WHERE is_leadership = 1 AND status = 1
     ORDER BY sort_order ASC, id ASC"
);

/* A small preview of the wider (non-leadership) team, linking to the full page. */
$team = db_all(
    "SELECT * FROM team_members
     WHERE is_leadership = 0 AND status = 1
     ORDER BY sort_order ASC, id ASC
     LIMIT 8"
);

/* Fallback leadership — the founding directors — so the page is complete and
   polished before any team member is seeded through the admin panel. */
if (!$leaders) {
    $leaders = [];
}

/* Prose mentions of the Board use the fetched rows, never hardcoded names. */
$directorNames = implode(' & ', array_map(
    static fn(array $m): string => (string) $m['name'],
    array_slice($leaders, 0, 2)
));

/* Governance pillars that define how the Foundation is run. */
$pillars = [
    ['cls' => '',  'icon' => 'landmark',    'title' => 'Board Oversight',       'text' => 'A dedicated Board of Directors sets our strategy, approves budgets and holds every programme to account.'],
    ['cls' => 'g', 'icon' => 'search',      'title' => 'Financial Transparency', 'text' => 'Every rupee is tracked from donation to delivery, with open reporting to our donors and supporters.'],
    ['cls' => 'p', 'icon' => 'shield',      'title' => 'Ethical Compliance',    'text' => 'We operate as a registered non-profit, meeting statutory obligations with honesty and diligence.'],
    ['cls' => 'o', 'icon' => 'bar-chart-3', 'title' => 'Accountability',        'text' => 'Regular independent reviews and impact reporting keep our work measurable, credible and improving.'],
];

/* How decisions flow through the organisation. */
$governanceSteps = [
    ['title' => 'Board of Directors', 'text' => 'The Board sets vision, policy and annual priorities, and safeguards the Foundation\'s mission and finances.'],
    ['title' => 'Programme Leadership', 'text' => 'Senior leadership translates strategy into focused initiatives across education, health, skilling and relief.'],
    ['title' => 'Field Teams & Volunteers', 'text' => 'Coordinators, field staff and volunteers deliver programmes directly within the communities we serve.'],
    ['title' => 'Review & Reporting', 'text' => 'Outcomes are measured, independently reviewed and reported transparently back to the Board and our donors.'],
];

/* Governance snapshot tiles. */
$snapshot = [
    ['value' => count($leaders), 'suffix' => '', 'icon' => 'users', 'label' => 'Directors on the Board', 'note' => 'setting strategy & policy'],
    ['value' => 100, 'suffix' => '%', 'icon' => 'badge-indian-rupee', 'label' => 'Of Funds Tracked',       'note' => 'from donation to delivery'],
    ['value' => 4,   'suffix' => '',  'icon' => 'clipboard-check',  'label' => 'Governance Reviews',      'note' => 'held every year'],
    ['value' => 6,   'suffix' => '+', 'icon' => 'layout-grid',      'label' => 'Core Programme Areas',    'note' => 'under Board oversight'],
];

/* ---- SEO -------------------------------------------------------------- */
seo_set([
    'title'       => 'Leadership Team',
    'description' => 'Meet the Board and leadership of EDUSKILL INDIA FOUNDATION — the directors and senior team who guide our mission with transparency, accountability and a deep commitment to communities across Bihar.',
    'page_key'    => 'leadership-team',
    'type'        => 'website',
]);

$page_hero = [
    'title'      => 'Leadership Team',
    'subtitle'   => 'The Board and senior leadership guiding EDUSKILL INDIA FOUNDATION — with integrity, transparency and an unwavering commitment to the communities we serve.',
    'breadcrumb' => [
        ['label' => 'About', 'url' => url('about')],
        ['label' => 'Leadership Team'],
    ],
];

include __DIR__ . '/includes/header.php';
?>

<style>
/* =========================================================================
   Leadership Team — page-specific premium polish (scoped to this page)
   ========================================================================= */

/* ---- Unique hero treatment ---- */
.page-hero {
    background: linear-gradient(130deg, #0f4429 0%, #0B4E3D 46%, #0d8f82 118%);
}
.page-hero::before {
    content: ""; position: absolute; inset: 0; z-index: 0; pointer-events: none;
    background:
        radial-gradient(440px circle at 14% 130%, rgba(241,90,36,.24), transparent 62%),
        url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='42' height='42'%3E%3Ccircle cx='2' cy='2' r='1.4' fill='%23ffffff' fill-opacity='0.09'/%3E%3C/svg%3E");
    background-size: auto, 42px 42px;
}
.page-hero h1 { font-size: clamp(2.15rem, 4.6vw, 3.35rem); letter-spacing: -.02em; line-height: 1.06; }
.page-hero .lead { font-size: clamp(1.02rem, 1.6vw, 1.18rem); line-height: 1.65; }
.page-hero .breadcrumb { margin-bottom: 1.1rem; }

/* ---- Hero meta strip (lifts over the hero) ---- */
.lt-herometa { margin-top: -2.6rem; margin-bottom: .5rem; position: relative; z-index: 3; }
.lt-herometa-grid {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;
    background: var(--surface, #fff);
    border: 1px solid var(--border, rgba(11,78,61,.08));
    border-radius: 20px; padding: 1.4rem 1.6rem;
    box-shadow: 0 24px 60px -28px rgba(15,63,42,.4), 0 4px 14px rgba(15,63,42,.08);
}
:root[data-theme="dark"] .lt-herometa-grid { background: rgba(20,30,54,.85); }
.lt-meta { display: flex; align-items: center; gap: .9rem; }
.lt-meta + .lt-meta { border-left: 1px solid var(--border, rgba(11,78,61,.09)); padding-left: 1.2rem; }
.lt-meta-ico {
    width: 46px; height: 46px; flex: none; border-radius: 13px; display: grid; place-items: center;
    color: #fff; background: linear-gradient(135deg, #0B4E3D, #174D3D); box-shadow: 0 10px 22px -8px rgba(23,77,61,.55);
}
.lt-meta-ico svg.lucide { width: 22px; height: 22px; }
.lt-meta strong { display: block; font-size: .98rem; line-height: 1.2; color: var(--text, #0f172a); }
.lt-meta small { color: var(--muted, #64748b); font-size: .82rem; }

/* ---- Section heads: consistent gradient divider + tighter measure ---- */
.lt-page .section-head { max-width: 760px; }
.lt-page .section-head::after {
    content: ""; display: block; width: 62px; height: 4px; border-radius: 999px;
    margin: 1.1rem auto 0; background: linear-gradient(90deg, #0B4E3D, #174D3D 55%, #F15A24);
}
.lt-page .section-head.left { max-width: none; }
.lt-page .section-head.left::after { margin-left: 0; }
.lt-page .section-brand .section-head::after { background: linear-gradient(90deg, #F15A24, #bfdbfe); }
.lt-page .eyebrow { letter-spacing: .14em; }

/* ---- Governance intro card ---- */
.lt-page .glass-card { border-radius: 22px; }
.lt-page .list-check li { line-height: 1.55; }

/* ---- Leadership (Board) cards ---- */
.lt-page .lead-card {
    border-radius: 20px; border: 1px solid var(--border, rgba(11,78,61,.08));
    box-shadow: 0 18px 44px -26px rgba(15,63,42,.4);
    transition: transform .35s cubic-bezier(.2,.7,.3,1), box-shadow .35s;
}
.lt-page .lead-card:hover { transform: translateY(-8px); box-shadow: 0 34px 64px -30px rgba(15,63,42,.55); }
.lt-page .lead-card .card-media { position: relative; overflow: hidden; }
.lt-page .lead-card .card-media img { transition: transform .6s cubic-bezier(.2,.7,.3,1); }
.lt-page .lead-card:hover .card-media img { transform: scale(1.05); }
.lt-page .lead-card .card-media::after {
    content: ""; position: absolute; inset: 0;
    background: linear-gradient(to top, rgba(9,40,25,.55) 0%, rgba(9,40,25,.05) 42%, transparent 70%);
}
.lt-page .lead-card .badge { z-index: 2; box-shadow: 0 6px 16px rgba(0,0,0,.22); }
.lt-page .lead-role {
    display: inline-flex; align-items: center; gap: .4rem;
    color: var(--brand-600, #0B4E3D); font-weight: 700; margin: .35rem 0 .55rem;
}
.lt-page .lead-role svg.lucide { width: 16px; height: 16px; }
.lt-page .lead-card .social-links a svg.lucide { width: 18px; height: 18px; }

/* ---- Snapshot (brand band) glass tiles ---- */
.lt-page .section-brand .counter-item {
    background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.16);
    border-radius: 18px; padding: 1.75rem 1.25rem; backdrop-filter: blur(6px);
    transition: transform .3s, background .3s;
}
.lt-page .section-brand .counter-item:hover { transform: translateY(-6px); background: rgba(255,255,255,.14); }
.lt-page .snap-ico {
    width: 52px; height: 52px; margin: 0 auto .9rem; border-radius: 14px; display: grid; place-items: center;
    color: #fff; background: rgba(255,255,255,.16); border: 1px solid rgba(255,255,255,.22);
}
.lt-page .snap-ico svg.lucide { width: 24px; height: 24px; }

/* ---- Governance pillars ---- */
.lt-page .icon-box { border-radius: 20px; transition: transform .35s, box-shadow .35s; }
.lt-page .icon-box:hover { transform: translateY(-6px); box-shadow: 0 28px 56px -30px rgba(15,63,42,.5); }

/* ---- Wider-team preview cards ---- */
.lt-page .team-card { border-radius: 18px; transition: transform .3s, box-shadow .3s; }
.lt-page .team-card:hover { transform: translateY(-6px); box-shadow: 0 24px 50px -28px rgba(15,63,42,.5); }
.lt-page .team-card .card-media { overflow: hidden; }
.lt-page .team-card .card-media img { transition: transform .5s ease; }
.lt-page .team-card:hover .card-media img { transform: scale(1.06); }

/* ---- CTA cards ---- */
.lt-page .cta-card { border-radius: 22px; overflow: hidden; position: relative; }
.lt-page .cta-card::after {
    content: ""; position: absolute; top: -40%; right: -20%; width: 60%; height: 160%;
    background: radial-gradient(circle, rgba(255,255,255,.18), transparent 62%); pointer-events: none;
}
.lt-page .cta-card .btn { position: relative; z-index: 1; }

/* ---- Responsive ---- */
@media (max-width: 860px) {
    .lt-herometa-grid { grid-template-columns: 1fr; gap: .4rem; padding: 1.2rem 1.25rem; }
    .lt-meta + .lt-meta { border-left: 0; border-top: 1px solid var(--border, rgba(11,78,61,.09)); padding-left: 0; padding-top: .9rem; margin-top: .1rem; }
}
@media (max-width: 640px) {
    .lt-herometa { margin-top: -1.6rem; }
    .lt-page .section-head::after { margin-top: .9rem; }
}
</style>

<div class="lt-page">

<!-- ============================== HERO META STRIP ============================== -->
<div class="lt-herometa reveal">
    <div class="container">
        <div class="lt-herometa-grid">
            <div class="lt-meta">
                <span class="lt-meta-ico"><?= lucide('landmark') ?></span>
                <div>
                    <strong>Registered Non-Profit</strong>
                    <small>CIN <?= e(get_setting('cin', SITE_CIN)) ?></small>
                </div>
            </div>
            <div class="lt-meta">
                <span class="lt-meta-ico"><?= lucide('users') ?></span>
                <div>
                    <strong>Board of Directors</strong>
                    <small><?= e($directorNames) ?></small>
                </div>
            </div>
            <div class="lt-meta">
                <span class="lt-meta-ico"><?= lucide('shield-check') ?></span>
                <div>
                    <strong>Fully Accountable</strong>
                    <small>Transparent, audited reporting</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================== GOVERNANCE INTRO ============================== -->
<section class="section" style="position:relative;overflow:hidden;">
    <span class="blob b1" style="top:-80px;right:-60px;"></span>
    <span class="blob b3" style="bottom:-90px;left:-70px;"></span>
    <div class="container grid grid-2 items-center gap-4" style="position:relative;z-index:1;">
        <div class="reveal">
            <span class="eyebrow">Governance &amp; Leadership</span>
            <h2 class="section-title">Guided by a Board that puts <span class="text-grad-ocean">people first</span></h2>
            <p class="text-muted"><?= e(get_setting('leadership_intro', 'EDUSKILL INDIA FOUNDATION is a registered non-profit (CIN ' . SITE_CIN . ') led by a committed Board of Directors. Our leadership sets a clear direction, safeguards our finances and holds every programme to the highest standard of transparency and accountability — so that the trust our supporters place in us is honoured in full.')) ?></p>
            <ul class="list-check mt-3">
                <li>A Board of Directors accountable for strategy and finances</li>
                <li>Transparent reporting — every rupee tracked to delivery</li>
                <li>Community-led programmes with measurable, lasting impact</li>
            </ul>
            <div class="flex flex-wrap gap-2 mt-4">
                <a class="btn btn-3d" href="<?= e(url('team')) ?>"><?= lucide('users') ?> Meet the Full Team</a>
                <a class="btn btn-outline" href="<?= e(url('contact')) ?>"><?= lucide('mail') ?> Contact the Office</a>
            </div>
        </div>
        <div class="glass-card reveal delay-1" style="padding:2rem;">
            <span class="eyebrow">Registered Non-Profit</span>
            <h3 class="card-title mb-2"><?= e(get_setting('site_name', SITE_NAME)) ?></h3>
            <p class="card-text mb-3"><?= e(SITE_TAGLINE) ?></p>
            <div class="divider"></div>
            <ul class="list-check mt-3">
                <li><strong>CIN:</strong> <?= e(get_setting('cin', SITE_CIN)) ?></li>
                <li><strong>Registered Office:</strong> <?= e(get_setting('contact_address', SITE_ADDRESS)) ?></li>
                <li><strong>Directors:</strong> <?= e($directorNames) ?></li>
                <li><strong>Email:</strong> <?= e(get_setting('contact_email', SITE_EMAIL)) ?></li>
            </ul>
        </div>
    </div>
</section>

<!-- ============================== THE BOARD ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">The People In Charge</span>
            <h2 class="section-title">Board &amp; Leadership</h2>
            <p class="section-subtitle">The directors and senior leaders who set our direction, safeguard our mission and ensure every programme delivers real, measurable change.</p>
        </div>

        <?php $leadCols = count($leaders) <= 2 ? 'grid-2' : 'grid-3'; ?>
        <div class="grid <?= e($leadCols) ?> gap-4" <?= count($leaders) <= 2 ? 'style="max-width:900px;margin-inline:auto;"' : '' ?>>
            <?php foreach ($leaders as $i => $m): $socials = json_column($m['socials'] ?? null); ?>
                <article class="card lead-card reveal <?= 'delay-' . (($i % 3) + 1) ?>" style="overflow:hidden;">
                    <div class="card-media" style="aspect-ratio:4/5;position:relative;">
                        <img src="<?= e(image_url($m['photo'] ?? null, 'avatar')) ?>"
                             alt="<?= e($m['name']) ?>" loading="lazy" width="520" height="650"
                             style="width:100%;height:100%;object-fit:cover;">
                        <span class="badge badge-accent" style="position:absolute;top:14px;left:14px;">Board</span>
                    </div>
                    <div class="card-body">
                        <h3 class="card-title mb-0"><?= e($m['name']) ?></h3>
                        <?php if (!empty($m['designation'])): ?>
                            <p class="lead-role"><?= lucide('shield-check') ?><?= e($m['designation']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($m['department'])): ?>
                            <span class="chip"><?= lucide('layers') ?><?= e($m['department']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($m['bio'])): ?>
                            <p class="card-text mt-2"><?= e(excerpt($m['bio'], 42)) ?></p>
                        <?php endif; ?>

                        <?php
                        $hasContact = !empty($m['email']) || !empty($m['phone']) || !empty($socials);
                        if ($hasContact):
                        ?>
                        <div class="social-links" style="margin-top:1rem;">
                            <?php if (!empty($m['email'])): ?>
                                <a href="mailto:<?= e($m['email']) ?>" aria-label="Email <?= e($m['name']) ?>"><?= lucide('mail') ?></a>
                            <?php endif; ?>
                            <?php if (!empty($m['phone'])): ?>
                                <a href="tel:<?= e(preg_replace('/\s+/', '', (string) $m['phone'])) ?>" aria-label="Call <?= e($m['name']) ?>"><?= lucide('phone') ?></a>
                            <?php endif; ?>
                            <?php foreach ($socials as $platform => $link): ?>
                                <?php if (empty($link) || !is_string($link)) { continue; } ?>
                                <a href="<?= e($link) ?>" target="_blank" rel="noopener"
                                   aria-label="<?= e($m['name'] . ' on ' . (string) $platform) ?>"><?= social_svg((string) $platform) ?></a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== GOVERNANCE SNAPSHOT ============================== -->
<section class="section section-brand">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="color:#bfdbfe;justify-content:center;">Governance At A Glance</span>
            <h2 class="section-title text-white">Accountable by Design</h2>
        </div>
        <div class="grid grid-4">
            <?php foreach ($snapshot as $s): ?>
                <div class="counter-item reveal">
                    <span class="snap-ico"><?= lucide($s['icon']) ?></span>
                    <div class="counter-value">
                        <span data-counter="<?= (int) $s['value'] ?>">0</span><?= e($s['suffix']) ?>
                    </div>
                    <div class="counter-label"><?= e($s['label']) ?></div>
                    <small style="color:rgba(255,255,255,.82);"><?= e($s['note']) ?></small>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== GOVERNANCE PILLARS ============================== -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">How We Hold Ourselves To Account</span>
            <h2 class="section-title">Our Governance Principles</h2>
            <p class="section-subtitle">Four commitments that shape every decision our leadership makes — and keep the trust of the communities and donors we serve.</p>
        </div>
        <div class="grid grid-4">
            <?php foreach ($pillars as $i => $p): ?>
                <div class="icon-box glass-card reveal <?= 'delay-' . (($i % 3) + 1) ?> <?= e($p['cls']) ?>">
                    <div class="ib-icon"><?= lucide($p['icon']) ?></div>
                    <h3 class="card-title"><?= e($p['title']) ?></h3>
                    <p class="card-text"><?= e($p['text']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== HOW WE ARE GOVERNED ============================== -->
<section class="section section-alt">
    <div class="container grid grid-2 items-center gap-4">
        <div class="reveal">
            <span class="eyebrow">The Chain Of Accountability</span>
            <h2 class="section-title">How We Are Governed</h2>
            <p class="text-muted">Clear roles and open reporting connect our Board room to the field. Strategy set at the top is delivered by our teams on the ground, then measured and reported back — so accountability flows in both directions.</p>
            <a class="btn btn-primary mt-3" href="<?= e(url('team')) ?>"><?= lucide('users') ?> See Everyone Involved</a>
        </div>
        <div class="process-steps reveal delay-1">
            <?php foreach ($governanceSteps as $step): ?>
                <div class="process-step">
                    <h3 class="card-title mb-0"><?= e($step['title']) ?></h3>
                    <p class="card-text mt-1"><?= e($step['text']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== WIDER TEAM PREVIEW ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="section-head left flex justify-between items-center flex-wrap gap-2">
            <div>
                <span class="eyebrow">Beyond The Board</span>
                <h2 class="section-title mb-0">The Team Behind The Mission</h2>
            </div>
            <a class="btn btn-outline btn-sm" href="<?= e(url('team')) ?>"><?= lucide('arrow-right') ?> View the Full Team</a>
        </div>

        <?php if ($team): ?>
        <div class="grid grid-4">
            <?php foreach ($team as $i => $m): ?>
                <article class="card team-card reveal <?= 'delay-' . (($i % 4) + 1) ?>">
                    <div class="card-media" style="aspect-ratio:1/1;">
                        <img src="<?= e(image_url($m['photo'] ?? null, 'avatar')) ?>"
                             alt="<?= e($m['name']) ?>" loading="lazy" width="360" height="360"
                             style="width:100%;height:100%;object-fit:cover;">
                    </div>
                    <div class="card-body text-center">
                        <h3 class="card-title mb-0" style="font-size:1.1rem;"><?= e($m['name']) ?></h3>
                        <?php if (!empty($m['designation'])): ?>
                            <p style="color:var(--brand-600);font-weight:600;margin:.25rem 0 0;font-size:.92rem;"><?= e($m['designation']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($m['department'])): ?>
                            <span class="chip mt-1"><?= e($m['department']) ?></span>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="icon-badge"><?= lucide('handshake') ?></div>
            <h3>Our team is growing</h3>
            <p class="text-muted">Coordinators, field staff and volunteers turn our leadership's vision into everyday action. Their profiles will appear here soon — and you could be one of them.</p>
            <div class="flex justify-center gap-2 mt-3 flex-wrap">
                <a class="btn btn-primary btn-sm" href="<?= e(url('volunteer')) ?>">Become a Volunteer</a>
                <a class="btn btn-outline btn-sm" href="<?= e(url('team')) ?>">Visit the Team Page</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ============================== JOIN / CONTACT CTA ============================== -->
<section class="section">
    <div class="container grid grid-2 gap-4">
        <div class="card-3d cta-card reveal" style="background:var(--grad-brand);color:#fff;">
            <div class="icon-badge" style="background:rgba(255,255,255,.18);"><?= lucide('handshake') ?></div>
            <h2 class="text-white">Join Our Team</h2>
            <p style="color:rgba(255,255,255,.9);">Bring your time, skills and energy to a cause that matters. Our people are our greatest strength in creating lasting change across Bihar.</p>
            <a class="btn btn-white btn-lg mt-2" href="<?= e(url('volunteer')) ?>">Volunteer With Us</a>
        </div>
        <div class="card-3d cta-card reveal delay-1" style="background:var(--grad-accent);color:#fff;">
            <div class="icon-badge" style="background:rgba(255,255,255,.2);"><?= lucide('mail') ?></div>
            <h2 class="text-white">Talk To Our Leadership</h2>
            <p style="color:rgba(255,255,255,.92);">Have a partnership proposal, a governance query or want to learn more about our work? Our office would be glad to hear from you.</p>
            <a class="btn btn-white btn-lg mt-2" href="<?= e(url('contact')) ?>">Get In Touch</a>
        </div>
    </div>
</section>

</div><!-- /.lt-page -->

<?php include __DIR__ . '/includes/footer.php'; ?>
