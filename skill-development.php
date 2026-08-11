<?php
/**
 * =============================================================================
 *  Skill Development — program landing page.
 *
 *  Hero copy is pulled from the matching `programs` row (slug =
 *  'skill-development'); every section degrades gracefully to strong,
 *  NGO-appropriate defaults so the page looks complete before seeding.
 *
 *  Sections: overview · what we offer · outcome counters · learning pathway ·
 *  related projects · enrol / volunteer CTA.
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

/* ---- Program record (hero + overview copy) ----------------------------- */
$program = db_row(
    "SELECT * FROM programs WHERE slug = :slug AND deleted_at IS NULL LIMIT 1",
    [':slug' => 'skill-development']
);

$prog_title = $program['title'] ?? 'Skill Development';
$prog_short = $program['short_description']
    ?? 'Practical, market-ready vocational training that equips youth and women across Bihar with the skills, confidence and connections to earn a dignified, sustainable livelihood.';
$prog_long  = $program['description']
    ?? 'At ' . SITE_NAME . ', skill development is a bridge out of poverty. We run hands-on training in trades that local markets actually demand — from tailoring and computer literacy to electrical work and beauty & wellness — pairing every course with life-skills coaching, financial literacy and real placement support. Our trainers work shoulder-to-shoulder with learners who may never have had a formal opportunity, turning raw potential into steady income and lasting self-reliance for entire families.';
$prog_icon  = $program['icon'] ?? 'wrench';
$prog_color = $program['color'] ?? '#2F8065';

/* ---- What we offer (skills grid) --------------------------------------- */
$offerings = $list_setting('skill_offerings', [
    ['icon' => 'scissors',    'title' => 'Tailoring & Stitching',    'text' => 'Cutting, stitching, embroidery and garment finishing that lead to home-based businesses and boutique jobs.'],
    ['icon' => 'laptop',      'title' => 'Computer Literacy',        'text' => 'Basic to intermediate computing, MS Office, internet and digital services for office-ready employability.'],
    ['icon' => 'zap',         'title' => 'Electrical & Wiring',      'text' => 'House wiring, appliance repair and safety practices for a trade that is always in demand.'],
    ['icon' => 'sparkles',    'title' => 'Beauty & Wellness',        'text' => 'Salon skills, grooming and self-care services that open the door to salon jobs and micro-enterprises.'],
    ['icon' => 'wrench',      'title' => 'Vocational Trades',        'text' => 'Plumbing, mobile repair, welding and other hands-on trades matched to local market needs.'],
    ['icon' => 'mic',         'title' => 'Spoken English & Soft Skills', 'text' => 'Communication, interview readiness and workplace confidence that multiply every technical skill.'],
    ['icon' => 'bar-chart-3', 'title' => 'Financial Literacy',       'text' => 'Budgeting, banking, savings and basic bookkeeping so earnings translate into stability.'],
    ['icon' => 'rocket',      'title' => 'Entrepreneurship',         'text' => 'Micro-enterprise planning, self-help group linkage and seed guidance to help learners start their own venture.'],
]);

/* ---- Outcome counters -------------------------------------------------- */
$outcomes = $list_setting('skill_outcomes', [
    ['icon' => 'graduation-cap', 'value' => 3200, 'suffix' => '+', 'title' => 'Youth Trained',        'description' => 'Learners equipped with a livelihood skill'],
    ['icon' => 'briefcase',      'value' => 72,   'suffix' => '%',  'title' => 'Placement Rate',       'description' => 'Trained graduates in work or self-employment'],
    ['icon' => 'user',           'value' => 1800, 'suffix' => '+', 'title' => 'Women Empowered',       'description' => 'Financially independent through skills'],
    ['icon' => 'trophy',         'value' => 45,   'suffix' => '+', 'title' => 'Training Batches',      'description' => 'Cohorts completed across Bihar'],
]);

/* ---- Learning pathway -------------------------------------------------- */
$pathway = $list_setting('skill_pathway', [
    ['title' => 'Enrol & Assess',    'text' => 'Register for free, meet our counsellors and choose a trade that fits your interest and the local market.', 'color' => '#0B4E3D'],
    ['title' => 'Learn by Doing',    'text' => 'Train hands-on with experienced instructors using real tools, live projects and small, focused batches.',   'color' => '#174D3D'],
    ['title' => 'Certify & Prepare', 'text' => 'Earn a completion certificate and sharpen soft skills, interviews and financial literacy for the workplace.', 'color' => '#F15A24'],
    ['title' => 'Earn & Grow',       'text' => 'Get placement support or micro-enterprise guidance — and step into a dignified, sustainable income.',        'color' => '#2F8065'],
]);

/* ---- Related projects (this program first, then any skill work) -------- */
$projectSql = "SELECT pr.*, pg.title AS program_title, pg.slug AS program_slug
               FROM projects pr
               LEFT JOIN programs pg ON pg.id = pr.program_id
               WHERE pr.deleted_at IS NULL
                 AND pr.status IN ('ongoing', 'completed')";

$projects = db_all(
    $projectSql . " AND pg.slug = :slug
                    ORDER BY pr.is_featured DESC, pr.sort_order ASC, pr.id DESC
                    LIMIT 6",
    [':slug' => 'skill-development']
);

/* Fall back to any recent ongoing/completed work so the section is never empty. */
$isFallback = false;
if (!$projects) {
    $projects = db_all(
        $projectSql . " ORDER BY pr.is_featured DESC, pr.sort_order ASC, pr.id DESC
                        LIMIT 6"
    );
    $isFallback = (bool) $projects;
}

/* ---- SEO --------------------------------------------------------------- */
seo_set([
    'title'       => $prog_title,
    'description' => excerpt($prog_short, 32),
    'page_key'    => 'skill-development',
    'type'        => 'website',
]);

/* ---- Hero banner ------------------------------------------------------- */
$page_hero = [
    'title'      => $prog_title,
    'subtitle'   => $prog_short,
    'breadcrumb' => [
        ['label' => 'Programs', 'url' => url('programs')],
        ['label' => $prog_title],
    ],
];

include __DIR__ . '/includes/header.php';
?>

<!-- ============================== OVERVIEW ============================== -->
<section class="section">
    <div class="container grid grid-2 items-center gap-4">
        <div class="reveal">
            <span class="eyebrow">Skills for Livelihoods</span>
            <h2 class="section-title">From Training to a Dignified Income</h2>
            <p class="lead"><?= e($prog_short) ?></p>
            <p class="text-muted"><?= e($prog_long) ?></p>
            <ul class="list-check mt-3">
                <li>Market-relevant trades chosen with local employers in mind</li>
                <li>Free or highly subsidised training for youth and women</li>
                <li>Placement support, certificates and enterprise guidance</li>
            </ul>
            <div class="flex flex-wrap gap-2 mt-4">
                <a class="btn btn-primary" href="<?= e(url('volunteer')) ?>">Enrol / Volunteer</a>
                <a class="btn btn-outline" href="<?= e(url('programs')) ?>">All Programs</a>
            </div>
        </div>
        <div class="reveal delay-1">
            <?php if (!empty($program['image'])): ?>
                <img src="<?= e(image_url($program['image'])) ?>" alt="<?= e($prog_title) ?>"
                     loading="lazy" width="720" height="540"
                     style="width:100%;border-radius:var(--radius-lg);box-shadow:var(--shadow);object-fit:cover;">
            <?php else: ?>
                <div class="card-3d" style="background:linear-gradient(135deg,<?= e($prog_color) ?>,#174D3D);color:#fff;">
                    <div class="icon-badge" style="background:rgba(255,255,255,.2);"><?= lucide($prog_icon) ?></div>
                    <h3 class="card-title text-white">Empowering Hands, Uplifting Futures</h3>
                    <p style="color:rgba(255,255,255,.92);">“A skill in hand is a future secured. When we teach a person to earn, we help a whole family rise — with dignity and hope.”</p>
                    <p class="mt-2" style="color:rgba(255,255,255,.9);">— <?= e(SITE_NAME) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ============================== WHAT WE OFFER ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="justify-content:center;">What We Offer</span>
            <h2 class="section-title">Trades &amp; Courses We Teach</h2>
            <p class="section-subtitle">Every course is hands-on, mentor-led and matched to real demand in the communities we serve.</p>
        </div>
        <div class="grid grid-4">
            <?php foreach ($offerings as $i => $o): ?>
                <div class="card-3d reveal <?= 'delay-' . (($i % 4) + 1) ?>">
                    <div class="icon-badge" style="background:linear-gradient(135deg,<?= e($prog_color) ?>,#174D3D);"><?= lucide($o['icon'] ?? 'star') ?></div>
                    <h3 class="card-title"><?= e($o['title'] ?? '') ?></h3>
                    <p class="card-text"><?= e($o['text'] ?? '') ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== OUTCOME COUNTERS ============================== -->
<section class="section section-brand">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="justify-content:center;color:#dbeafe;">Outcomes That Matter</span>
            <h2 class="section-title text-white">Skills Turned Into Livelihoods</h2>
            <p class="section-subtitle" style="color:rgba(255,255,255,.9);">Behind every number is a learner who walked in with hope and walked out with a way to earn.</p>
        </div>
        <div class="counter-grid">
            <?php foreach ($outcomes as $c): ?>
                <div class="counter-item reveal">
                    <div class="icon-badge" style="background:rgba(255,255,255,.18);"><?= lucide($c['icon'] ?? 'star') ?></div>
                    <div class="counter-value"><?= e($c['prefix'] ?? '') ?><span data-counter="<?= (int) ($c['value'] ?? 0) ?>">0</span><?= e($c['suffix'] ?? '') ?></div>
                    <div class="counter-label"><?= e($c['title'] ?? '') ?></div>
                    <?php if (!empty($c['description'])): ?>
                        <p style="color:rgba(255,255,255,.8);font-size:.85rem;margin-top:.35rem;"><?= e($c['description']) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== LEARNING PATHWAY ============================== -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="justify-content:center;">How It Works</span>
            <h2 class="section-title">Your Pathway to Employment</h2>
            <p class="section-subtitle">A simple, supported journey — from your first day of class to your first pay cheque.</p>
        </div>
        <div class="grid grid-4">
            <?php foreach ($pathway as $i => $step): $num = $i + 1; ?>
                <div class="card-3d reveal <?= 'delay-' . (($i % 4) + 1) ?>">
                    <div class="icon-badge" style="background:linear-gradient(135deg,<?= e($step['color'] ?? '#0B4E3D') ?>,#174D3D);font-weight:800;"><?= e(str_pad((string) $num, 2, '0', STR_PAD_LEFT)) ?></div>
                    <h3 class="card-title"><?= e($step['title'] ?? '') ?></h3>
                    <p class="card-text"><?= e($step['text'] ?? '') ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== RELATED PROJECTS ============================== -->
<section class="section section-alt">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="justify-content:center;">On The Ground</span>
            <h2 class="section-title"><?= $isFallback ? 'Our Recent Projects' : 'Skill Development Projects' ?></h2>
            <p class="section-subtitle"><?= $isFallback
                ? 'Recent work from across our programs — skill-development batches are announced here as they launch.'
                : 'Real training centres, batches and drives bringing employable skills to communities across Bihar.' ?></p>
        </div>

        <?php if ($projects): ?>
            <div class="grid grid-3">
                <?php foreach ($projects as $i => $pr):
                    $isDone   = ($pr['status'] === 'completed');
                    $progress = max(0, min(100, (int) ($pr['progress'] ?? 0)));
                    if ($isDone) {
                        $progress = 100;
                    }
                    ?>
                    <article class="card reveal <?= 'delay-' . (($i % 3) + 1) ?>">
                        <div class="card-media" style="position:relative;">
                            <img src="<?= e(image_url($pr['image'], 'blog')) ?>" alt="<?= e($pr['title']) ?>" loading="lazy" width="600" height="375">
                            <span class="badge <?= $isDone ? 'badge-success' : 'badge-accent' ?>" style="position:absolute;top:12px;left:12px;">
                                <?= $isDone ? 'Completed' : 'Ongoing' ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($pr['program_title'])): ?>
                                <span class="badge badge-brand mb-1"><?= e($pr['program_title']) ?></span>
                            <?php endif; ?>
                            <h3 class="card-title"><?= e($pr['title']) ?></h3>
                            <p class="card-text"><?= e($pr['summary'] ?: excerpt($pr['description'] ?? '', 20)) ?></p>

                            <div class="flex flex-wrap gap-2 mb-1" style="font-size:.85rem;color:var(--text-soft);">
                                <?php if (!empty($pr['location'])): ?><span><?= lucide('map-pin') ?> <?= e($pr['location']) ?></span><?php endif; ?>
                                <?php if (!empty($pr['beneficiaries'])): ?><span><?= lucide('users') ?> <?= e(number_short((int) $pr['beneficiaries'])) ?> beneficiaries</span><?php endif; ?>
                            </div>

                            <?php if (!$isDone): ?>
                                <div class="flex items-center justify-between" style="font-size:.8rem;color:var(--text-soft);margin-top:.5rem;">
                                    <span>Progress</span><strong><?= $progress ?>%</strong>
                                </div>
                                <div class="progress mt-1">
                                    <div class="progress-bar" style="width:<?= $progress ?>%;"></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="text-center mt-4">
                <a class="btn btn-outline" href="<?= e(url('projects')) ?>">Explore All Projects</a>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon-badge"><?= lucide($prog_icon) ?></div>
                <h3>New Batches Launching Soon</h3>
                <p class="text-muted">Our next round of skill-development projects and training centres is being planned. Register your interest and we will reach out the moment enrolment opens.</p>
                <a class="btn btn-primary mt-2" href="<?= e(url('volunteer')) ?>">Register Your Interest</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ============================== ENROL / VOLUNTEER CTA ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="card-3d reveal text-center" style="background:var(--grad-brand);color:#fff;max-width:900px;margin-inline:auto;">
            <span class="eyebrow" style="justify-content:center;color:#dbeafe;"><?= e(SITE_TAGLINE) ?></span>
            <h2 class="text-white">Ready to Learn a Skill — or Teach One?</h2>
            <p style="color:rgba(255,255,255,.92);max-width:640px;margin-inline:auto;">Whether you want to enrol in a free course or share your expertise as a trainer or mentor, there is a place for you in this movement. Take the first step today.</p>
            <div class="flex flex-wrap justify-center gap-2 mt-4">
                <a class="btn btn-white btn-lg" href="<?= e(url('volunteer')) ?>"><?= lucide('graduation-cap') ?> Enrol Now</a>
                <a class="btn btn-outline btn-lg" style="border-color:#fff;color:#fff;" href="<?= e(url('volunteer')) ?>"><?= lucide('handshake') ?> Volunteer as a Trainer</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
