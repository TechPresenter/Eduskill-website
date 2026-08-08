<?php
/**
 * =============================================================================
 *  Causes We Support — the focus areas EDUSKILL INDIA FOUNDATION champions.
 *  Cause cards are pulled from the `programs` table (status = 'active') and
 *  rendered with a per-cause gradient derived from the stored `color`. Every
 *  section is DB-driven with graceful, curated fallbacks so the page looks
 *  complete and polished before any content is seeded.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* ---- Colour helpers (page-local closures — no global pollution) ---------- */

/** Normalise any stored colour to a safe #rrggbb string. */
$hex6 = static function (?string $hex): string {
    $hex = ltrim((string) $hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    return (strlen($hex) === 6 && ctype_xdigit($hex)) ? '#' . strtolower($hex) : '#2563eb';
};

/** Return a lighter shade of a hex colour (mixed toward white). */
$lighten = static function (string $hex, float $pct = 0.34) use ($hex6): string {
    $hex = ltrim($hex6($hex), '#');
    $r = (int) hexdec(substr($hex, 0, 2));
    $g = (int) hexdec(substr($hex, 2, 2));
    $b = (int) hexdec(substr($hex, 4, 2));
    $r = (int) round($r + (255 - $r) * $pct);
    $g = (int) round($g + (255 - $g) * $pct);
    $b = (int) round($b + (255 - $b) * $pct);
    return sprintf('#%02x%02x%02x', $r, $g, $b);
};

/* ---- Data ---------------------------------------------------------------- */
$causes = db_all(
    "SELECT * FROM programs
     WHERE status = 'active'
     ORDER BY is_featured DESC, sort_order ASC, id ASC"
);

/* Fallback causes so the page is complete before any programme is seeded */
if (!$causes) {
    $causes = [
        ['title' => 'Education',            'slug' => '', 'icon' => 'book', 'color' => '#2563eb', 'short_description' => 'Free schooling, scholarships and learning kits that keep underprivileged children in classrooms and on a path to opportunity.'],
        ['title' => 'Health & Wellbeing',   'slug' => '', 'icon' => 'stethoscope', 'color' => '#14b8a6', 'short_description' => 'Free medical camps, screenings and health awareness that bring essential care to families in underserved rural areas.'],
        ['title' => 'Women Empowerment',    'slug' => '', 'icon' => 'user', 'color' => '#ec4899', 'short_description' => 'Skill training, self-help groups and livelihood support that help women earn, lead and shape their own futures.'],
        ['title' => 'Environment',          'slug' => '', 'icon' => 'sprout', 'color' => '#16a34a', 'short_description' => 'Tree plantation, clean-up drives and green awareness that protect the land and climate our communities depend on.'],
        ['title' => 'Disaster Relief',      'slug' => '', 'icon' => 'life-buoy', 'color' => '#f97316', 'short_description' => 'Rapid relief, food and shelter in times of crisis, followed by long-term rehabilitation that helps families rebuild.'],
        ['title' => 'Clean Water',          'slug' => '', 'icon' => 'droplet', 'color' => '#0ea5e9', 'short_description' => 'Safe drinking water, hygiene drives and sanitation infrastructure that protect health at the very source.'],
    ];
}

/* Curated "why it matters" reasons (coloured icon-box variants) */
$reasons = [
    ['cls' => '',  'icon' => 'target', 'title' => 'Focused Where It Counts', 'text' => 'We concentrate our energy on the causes with the deepest, most urgent need in the communities we serve — not scattered effort, but real depth.'],
    ['cls' => 'p', 'icon' => 'search', 'title' => 'Transparent To The Rupee', 'text' => 'Every contribution is tracked from donation to delivery, with open reporting so you always know exactly where your support goes.'],
    ['cls' => 'g', 'icon' => 'sprout', 'title' => 'Built To Last',            'text' => 'We design programmes for lasting change — building capability, not dependency — so impact continues long after we arrive.'],
    ['cls' => 'o', 'icon' => 'handshake', 'title' => 'Led With Communities',      'text' => 'We listen first and act alongside the people we serve, because change is most powerful when it is community-led.'],
];

/* Donate-impact strip — what a gift makes possible */
$impacts = [
    ['amount' => 500,  'icon' => 'book-open', 'text' => 'Learning kits and a month of after-school support for one child.'],
    ['amount' => 1000, 'icon' => 'stethoscope', 'text' => 'A full health screening and essential medicines at a rural camp.'],
    ['amount' => 2500, 'icon' => 'scissors', 'text' => 'A starter toolkit for a woman beginning her skills training.'],
    ['amount' => 5000, 'icon' => 'droplets', 'text' => 'Safe drinking water for a family right through the dry season.'],
];

seo_set([
    'title'       => 'Causes We Support',
    'description' => get_setting('causes_meta_description', 'Explore the causes EDUSKILL INDIA FOUNDATION champions across Bihar — education, health, women empowerment, environment, disaster relief and clean water. Support a cause close to your heart.'),
    'page_key'    => 'causes',
    'type'        => 'website',
]);

$page_hero = [
    'title'      => 'Causes We Support',
    'subtitle'   => 'Six focus areas, one mission — real, measurable change for the communities that need it most.',
    'breadcrumb' => [['label' => 'Causes']],
];

include __DIR__ . '/includes/header.php';
?>

<!-- ============================== CAUSES GRID ============================== -->
<section class="section section-causes">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="justify-content:center;">What We Champion</span>
            <h2 class="section-title">Choose A Cause Close To Your Heart</h2>
            <p class="section-subtitle">Each cause is a focused, community-led programme designed to create real change. Explore the work, then back the one that moves you.</p>
        </div>

        <div class="cause-grid">
            <?php foreach ($causes as $i => $c):
                $color   = $hex6($c['color'] ?? '#2563eb');
                $light   = $lighten($color);
                $grad    = "linear-gradient(135deg, {$color} 0%, {$light} 100%)";
                $slug    = trim((string) ($c['slug'] ?? ''));
                $icon    = $c['icon'] ?? '';
                $learn   = $slug !== '' ? url('programs?slug=' . $slug) : url('programs');
                $donate  = $slug !== '' ? url('donate?cause=' . $slug) : url('donate');
                $desc    = $c['short_description'] ?? $c['description'] ?? '';
            ?>
                <article class="cause-card reveal <?= 'delay-' . (($i % 3) + 1) ?>" style="--cc:<?= $color ?>;--cc-grad:<?= $grad ?>;">
                    <span class="cc-rule" aria-hidden="true"></span>
                    <span class="cc-glow" aria-hidden="true"></span>
                    <div class="cc-icon"><?= lucide($icon !== '' ? $icon : 'handshake') ?></div>
                    <h3 class="cc-title"><?= e($c['title']) ?></h3>
                    <p class="cc-text"><?= e(excerpt($desc, 26)) ?></p>
                    <div class="cc-actions">
                        <a class="cc-btn cc-btn-ghost" href="<?= e($learn) ?>">Learn More</a>
                        <a class="cc-btn cc-btn-fill" href="<?= e($donate) ?>"><?= lucide('heart') ?> Donate</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== WHY IT MATTERS ============================== -->
<section class="section section-soft">
    <div class="container grid grid-2 items-center gap-4">
        <div class="reveal">
            <span class="eyebrow">Why It Matters</span>
            <h2 class="section-title">Small Acts, Multiplied, Change Everything</h2>
            <p class="text-muted">Behind every cause is a real person — a child who wants to learn, a mother who wants to earn, a family that simply wants clean water and good health. On their own these challenges feel overwhelming. Together, and done well, they are entirely solvable.</p>
            <p class="text-muted">That is why we do not spread ourselves thin. We commit to a focused set of causes, work hand-in-hand with communities, and measure the difference we make — so your support becomes lasting change, not a one-off gesture.</p>
            <ul class="list-check mt-3">
                <li>Every programme is designed with the community, not just for it</li>
                <li>100% of your impact is tracked from donation to delivery</li>
                <li>We stay until change is self-sustaining — then we grow it</li>
            </ul>
            <div class="flex flex-wrap gap-2 mt-4">
                <a class="btn btn-3d" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Support a Cause</a>
                <a class="btn btn-outline" href="<?= e(url('projects')) ?>">See Our Work</a>
            </div>
        </div>
        <div class="grid grid-2 gap-3 reveal delay-1">
            <?php foreach ($reasons as $r): ?>
                <div class="glass-card icon-box <?= e($r['cls']) ?>" style="padding:1.6rem 1.25rem;">
                    <div class="ib-icon" style="width:60px;height:60px;font-size:1.6rem;margin-bottom:.9rem;"><?= lucide($r['icon']) ?></div>
                    <h3 class="card-title" style="font-size:1.05rem;"><?= e($r['title']) ?></h3>
                    <p class="card-text" style="font-size:.92rem;"><?= e($r['text']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== DONATE-IMPACT STRIP ============================== -->
<section class="section section-brand" style="position:relative;overflow:hidden;">
    <div class="wave-divider" style="position:absolute;top:0;left:0;color:var(--surface-2, #f1f5f9);" aria-hidden="true">
        <svg viewBox="0 0 1440 60" preserveAspectRatio="none"><path fill="currentColor" d="M0,0 H1440 V26 C1170,58 900,6 660,32 C440,55 200,30 0,46 Z"></path></svg>
    </div>
    <span class="blob b2" style="top:-60px;right:-40px;opacity:.28;"></span>
    <span class="blob b3" style="bottom:-70px;left:-50px;opacity:.24;"></span>

    <div class="container" style="position:relative;z-index:1;">
        <div class="section-head">
            <span class="eyebrow" style="color:#bfdbfe;justify-content:center;">Your Gift, In Action</span>
            <h2 class="section-title text-white">See Exactly What Your Support Makes Possible</h2>
            <p class="section-subtitle" style="color:rgba(255,255,255,.85);">A little goes a remarkably long way. Here is what a single gift can do across our causes.</p>
        </div>

        <div class="grid grid-4">
            <?php foreach ($impacts as $i => $im): ?>
                <div class="glass-card reveal <?= 'delay-' . (($i % 4) + 1) ?>" style="text-align:center;padding:1.8rem 1.25rem;">
                    <div style="font-size:2.2rem;line-height:1;margin-bottom:.6rem;"><?= lucide($im['icon']) ?></div>
                    <div class="text-grad-ocean" style="font-size:1.7rem;font-weight:800;"><?= e(money($im['amount'])) ?></div>
                    <p class="card-text mt-1" style="font-size:.92rem;"><?= e($im['text']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center mt-4">
            <a class="btn btn-white btn-lg" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Donate Now</a>
        </div>
    </div>
</section>

<!-- ============================== GET INVOLVED CTA ============================== -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="justify-content:center;">Be Part Of The Change</span>
            <h2 class="section-title">More Than One Way To Make A Difference</h2>
            <p class="section-subtitle">Give what you can, give your time, or bring your organisation alongside ours — every contribution moves a cause forward.</p>
        </div>
        <div class="grid grid-3 gap-4">
            <div class="card-3d reveal" style="background:var(--grad-brand);color:#fff;">
                <div class="icon-badge" style="background:rgba(255,255,255,.18);"><?= lucide('heart') ?></div>
                <h3 class="card-title text-white">Donate</h3>
                <p class="card-text" style="color:rgba(255,255,255,.9);">Fuel the causes you care about. Every rupee is tracked and put straight to work on the ground.</p>
                <a class="btn btn-white mt-2" href="<?= e(url('donate')) ?>">Give Today</a>
            </div>
            <div class="card-3d reveal delay-1" style="background:var(--grad-accent);color:#fff;">
                <div class="icon-badge" style="background:rgba(255,255,255,.18);"><?= lucide('handshake') ?></div>
                <h3 class="card-title text-white">Volunteer</h3>
                <p class="card-text" style="color:rgba(255,255,255,.92);">Give your time and skills to a cause that matters. Join our community of changemakers across Bihar.</p>
                <a class="btn btn-white mt-2" href="<?= e(url('volunteer')) ?>">Join Us</a>
            </div>
            <div class="card-3d reveal delay-2" style="background:var(--grad-green, linear-gradient(135deg,#16a34a,#4ade80));color:#fff;">
                <div class="icon-badge" style="background:rgba(255,255,255,.18);"><?= lucide('building-2') ?></div>
                <h3 class="card-title text-white">Partner</h3>
                <p class="card-text" style="color:rgba(255,255,255,.92);">Bring your organisation's strength to ours and multiply the impact we can create together.</p>
                <a class="btn btn-white mt-2" href="<?= e(url('contact')) ?>">Partner With Us</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
