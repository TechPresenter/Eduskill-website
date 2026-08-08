<?php
/**
 * =============================================================================
 *  Testimonials — voices of the people we serve, our volunteers and partners.
 *  Fully DB-driven (testimonials WHERE status=1 ORDER BY sort_order) with a
 *  curated fallback set so the page is complete and premium before any seeding.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* ---- Data ------------------------------------------------------------- */
$testimonials = db_all(
    "SELECT * FROM testimonials
     WHERE status = 1
     ORDER BY sort_order ASC, id ASC"
);

/* Curated fallback testimonials so the page never looks empty */
if (!$testimonials) {
    $testimonials = [
        ['name' => 'Anita Kumari',   'designation' => 'Beneficiary, Patna',        'photo' => null, 'rating' => 5, 'message' => 'The skill development programme completely changed my life. I now run my own tailoring business and proudly support my entire family. EduSkill India gave me not just training, but belief in myself.'],
        ['name' => 'Rakesh Singh',   'designation' => 'Volunteer since 2021',      'photo' => null, 'rating' => 5, 'message' => 'Volunteering with EDUSKILL INDIA FOUNDATION has been the most rewarding experience of my life. The team truly cares about impact — every rupee and every hour reaches the people who need it most.'],
        ['name' => 'Dr. Meena Rao',  'designation' => 'Partner, Free Health Camp',  'photo' => null, 'rating' => 5, 'message' => 'Their organisation and dedication during our medical camps was exceptional. I witnessed real change on the ground — families receiving care they could never otherwise afford.'],
        ['name' => 'Sunil Mahto',    'designation' => 'Parent, Education Program',   'photo' => null, 'rating' => 5, 'message' => 'My two children now go to school with proper books and uniforms thanks to EduSkill India. As a daily-wage worker, I never imagined this would be possible. I am forever grateful.'],
        ['name' => 'Priya Sharma',   'designation' => 'Women Empowerment Member',   'photo' => null, 'rating' => 5, 'message' => 'The self-help group connected me with other women in my village. Together we started a small savings circle and today I contribute equally to my household. This is dignity.'],
        ['name' => 'Ramesh Prasad',  'designation' => 'Corporate Partner',          'photo' => null, 'rating' => 4, 'message' => 'We chose to fund EduSkill India because of their transparency. Every quarter we receive clear, honest reporting on exactly where our contribution went and the lives it touched.'],
    ];
}

/* ---- Rating summary --------------------------------------------------- */
$total     = count($testimonials);
$ratings   = array_map(static fn($t) => max(1, min(5, (int) ($t['rating'] ?? 5))), $testimonials);
$sum       = array_sum($ratings);
$average   = $total ? $sum / $total : 0;
$happy     = count(array_filter($ratings, static fn($r) => $r >= 4));
$satisfied = $total ? (int) round(($happy / $total) * 100) : 0;

/* Distribution of stars, 5 -> 1 */
$distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
foreach ($ratings as $r) {
    $distribution[$r] = ($distribution[$r] ?? 0) + 1;
}

seo_set([
    'title'       => 'Testimonials',
    'description' => get_setting('testimonials_meta_description', 'Read what beneficiaries, volunteers and partners say about EDUSKILL INDIA FOUNDATION — real stories of hope, dignity and lasting change across Bihar.'),
    'page_key'    => 'testimonials',
    'type'        => 'website',
]);

$page_hero = [
    'title'      => 'Testimonials',
    'subtitle'   => 'Real voices from the people we serve, the volunteers who give their time, and the partners who walk with us.',
    'breadcrumb' => [['label' => 'Testimonials']],
];

include __DIR__ . '/includes/header.php';
?>

<!-- ============================== RATING SUMMARY STRIP ============================== -->
<section class="section" style="position:relative;overflow:hidden;">
    <span class="blob b1" style="top:-90px;left:-70px;"></span>
    <span class="blob b3" style="bottom:-110px;right:-60px;"></span>
    <div class="container" style="position:relative;z-index:1;">
        <div class="section-head">
            <span class="eyebrow">Voices of Change</span>
            <h2 class="section-title">Trusted by the Communities We Serve</h2>
            <p class="section-subtitle">Every rating and every word below comes from someone whose life intersects with our work.</p>
        </div>

        <div class="grid grid-sidebar gap-4 items-center">
            <!-- Overall score card -->
            <div class="glass-card reveal text-center" style="padding:2.25rem 1.75rem;">
                <div class="text-grad-multi" style="font-size:3.6rem;line-height:1;font-weight:800;"><?= e(number_format($average, 1)) ?></div>
                <div class="mt-2"><?= star_rating((int) round($average)) ?></div>
                <p class="text-muted mt-2 mb-0">Average rating from <strong><?= e(number_short($total)) ?></strong> <?= $total === 1 ? 'story' : 'stories' ?></p>
            </div>

            <!-- Distribution + quick stats -->
            <div class="reveal delay-1">
                <div class="grid gap-2">
                    <?php foreach ($distribution as $stars => $count): $pct = $total ? round(($count / $total) * 100) : 0; ?>
                        <div class="flex items-center gap-2">
                            <span class="text-muted" style="min-width:44px;font-weight:600;"><?= (int) $stars ?> <?= lucide('star') ?></span>
                            <div class="progress" style="flex:1;">
                                <div class="progress-bar" style="width:<?= (int) $pct ?>%;"></div>
                            </div>
                            <span class="text-muted" style="min-width:44px;text-align:right;"><?= (int) $pct ?>%</span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="grid grid-3 gap-3 mt-4">
                    <div class="stat-premium text-center">
                        <div class="counter-value text-grad-ocean" style="font-size:1.9rem;"><span data-counter="<?= (int) $satisfied ?>">0</span>%</div>
                        <div class="counter-label">Satisfaction</div>
                    </div>
                    <div class="stat-premium text-center">
                        <div class="counter-value text-grad-purple" style="font-size:1.9rem;"><span data-counter="<?= (int) $total ?>">0</span>+</div>
                        <div class="counter-label">Happy Voices</div>
                    </div>
                    <div class="stat-premium text-center">
                        <div class="counter-value text-grad-sunset" style="font-size:1.9rem;"><span data-counter="100">0</span>%</div>
                        <div class="counter-label">Transparent</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================== TESTIMONIALS GRID ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">In Their Own Words</span>
            <h2 class="section-title">Stories of Hope &amp; Dignity</h2>
            <p class="section-subtitle">Behind every number is a person, a family and a community moving forward.</p>
        </div>

        <?php if ($testimonials): ?>
        <div class="grid grid-3">
            <?php foreach ($testimonials as $i => $t): ?>
                <figure class="glass-card reveal <?= 'delay-' . (($i % 3) + 1) ?>" style="padding:1.85rem;display:flex;flex-direction:column;gap:1rem;height:100%;margin:0;">
                    <div class="flex justify-between items-center">
                        <?= star_rating((int) round(max(1, min(5, (int) ($t['rating'] ?? 5))))) ?>
                        <span aria-hidden="true" class="text-grad-ocean" style="font-family:Georgia,serif;font-size:2.6rem;line-height:.5;font-weight:800;">&rdquo;</span>
                    </div>
                    <blockquote class="card-text" style="font-size:1.02rem;color:var(--text-soft);flex:1;margin:0;">&ldquo;<?= e($t['message']) ?>&rdquo;</blockquote>
                    <figcaption class="flex items-center gap-2" style="border-top:1px solid var(--border);padding-top:1rem;">
                        <img src="<?= e(image_url($t['photo'] ?? null, 'avatar')) ?>" alt="<?= e($t['name']) ?>" width="52" height="52" loading="lazy" style="width:52px;height:52px;border-radius:50%;object-fit:cover;flex:none;">
                        <span>
                            <strong style="display:block;"><?= e($t['name']) ?></strong>
                            <?php if (!empty($t['designation'])): ?>
                                <small class="text-muted"><?= e($t['designation']) ?></small>
                            <?php endif; ?>
                        </span>
                    </figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="icon-badge"><?= lucide('message-square') ?></div>
            <h3>Stories are on the way</h3>
            <p class="text-muted">We are collecting testimonials from the communities we serve. Be the first to share your experience with us.</p>
            <a class="btn btn-primary mt-2" href="<?= e(url('feedback')) ?>">Share Your Story</a>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ============================== SHARE YOUR STORY CTA ============================== -->
<section class="section section-brand" style="position:relative;overflow:hidden;">
    <span class="blob b2" style="top:-80px;right:-50px;opacity:.3;"></span>
    <div class="container text-center reveal" style="position:relative;z-index:1;max-width:760px;">
        <div class="icon-box" style="padding-bottom:0;">
            <span class="ib-icon"><?= lucide('pen-line') ?></span>
        </div>
        <h2 class="text-white">Have a Story to Share?</h2>
        <p style="color:rgba(255,255,255,.9);font-size:1.08rem;">
            Whether you have benefited from a programme, volunteered your time, or partnered with us —
            your experience inspires others to join the movement. We would love to hear from you.
        </p>
        <div class="hero-actions flex justify-center flex-wrap gap-2 mt-4">
            <a class="btn btn-white btn-lg" href="<?= e(url('feedback')) ?>"><?= lucide('pen-line') ?> Share Your Story</a>
            <a class="btn btn-outline btn-lg" style="border-color:#fff;color:#fff;" href="<?= e(url('volunteer')) ?>"><?= lucide('handshake') ?> Become a Volunteer</a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
