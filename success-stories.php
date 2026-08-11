<?php
/**
 * =============================================================================
 *  Success Stories — real change, told through the people who lived it.
 *
 *  Primary source : published blogs in the "success-stories" category, shown as
 *                   story cards with pagination via paginate().
 *  Fallback source: if no such stories exist yet, published testimonials
 *                   (status = 1) are rendered as story cards instead.
 *  If neither exists, a graceful empty-state keeps the page complete.
 *
 *  Every section is DB-driven with sensible fallbacks so the page looks
 *  polished before any content has been seeded.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* ---- Data -------------------------------------------------------------- */

/* Impact counters (shared with the homepage look) — DB first, then fallback. */
$counters = get_all('achievements', ['status' => 1], 'sort_order ASC', 4);
if (!$counters) {
    $counters = [
        ['icon' => 'heart',          'value' => 25000, 'suffix' => '+', 'title' => 'Lives Impacted'],
        ['icon' => 'graduation-cap', 'value' => 120,   'suffix' => '+', 'title' => 'Projects Completed'],
        ['icon' => 'handshake',      'value' => 800,   'suffix' => '+', 'title' => 'Volunteers'],
        ['icon' => 'home',           'value' => 60,    'suffix' => '+', 'title' => 'Villages Reached'],
    ];
}

/* Published success-story blogs (category slug = 'success-stories'), paginated. */
$storiesSql = "SELECT b.*, c.name AS category_name, c.slug AS category_slug, u.name AS author_name
               FROM blogs b
               JOIN blog_categories c ON c.id = b.category_id
               LEFT JOIN users u ON u.id = b.author_id
               WHERE b.status = 'published'
                 AND b.deleted_at IS NULL
                 AND c.slug = :cat
               ORDER BY b.is_featured DESC, b.published_at DESC, b.id DESC";
$pager      = paginate($storiesSql, [':cat' => 'success-stories'], 9);
$stories    = $pager['items'];
$hasStories = $pager['total'] > 0;

/* Fallback: published testimonials shown as story cards. */
$testimonials = [];
if (!$hasStories) {
    $testimonials = get_all('testimonials', ['status' => 1], 'sort_order ASC, id DESC');
}

/* Story count for the intro copy. */
$storyCount = $hasStories ? (int) $pager['total'] : count($testimonials);

seo_set([
    'title'       => 'Success Stories',
    'description' => 'Real stories of hope and transformation from EDUSKILL INDIA FOUNDATION. Meet the children, women and communities across Bihar whose lives have changed through education, healthcare, skill development and relief.',
    'page_key'    => 'success-stories',
]);

$page_hero = [
    'title'      => 'Success Stories',
    'subtitle'   => 'Behind every number is a name, a face and a life transformed. These are the real journeys of the people and communities we serve across Bihar.',
    'breadcrumb' => [['label' => 'Success Stories']],
];

include __DIR__ . '/includes/header.php';
?>

<!-- ============================== INTRO + IMPACT ============================== -->
<section class="section">
    <div class="container">
        <div class="grid grid-2 items-center gap-4">
            <div class="reveal">
                <span class="eyebrow">Stories of Change</span>
                <h2 class="section-title"><?= e(get_setting('stories_intro_title', 'Change that you can see, in the lives it touches')) ?></h2>
                <p class="text-muted"><?= e(get_setting('stories_intro_text', 'Every program we run is measured not just in figures, but in futures reclaimed. From a first-generation learner who cleared her exams to a mother who now runs her own business, these stories are proof that compassion — backed by action — creates lasting change.')) ?></p>
                <ul class="list-check mt-3">
                    <li>Real people, real outcomes — documented on the ground</li>
                    <li>Impact you can trace from donation to delivery</li>
                    <li>Communities empowered to sustain their own progress</li>
                </ul>
                <div class="flex flex-wrap gap-2 mt-4">
                    <a class="btn btn-primary" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Fuel the Next Story</a>
                    <a class="btn btn-outline" href="<?= e(url('volunteer')) ?>"><?= lucide('handshake') ?> Become a Volunteer</a>
                </div>
            </div>
            <div class="grid grid-2 gap-3 reveal delay-1">
                <?php foreach ($counters as $c): ?>
                    <div class="card-3d text-center">
                        <div class="icon-badge" style="margin-inline:auto;"><?= lucide($c['icon'] ?? 'star') ?></div>
                        <div class="counter-value" style="font-size:1.7rem;"><span data-counter="<?= (int) ($c['value'] ?? 0) ?>">0</span><?= e($c['suffix'] ?? '') ?></div>
                        <div class="counter-label"><?= e($c['title'] ?? '') ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- ============================== STORY CARDS ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Voices of Impact</span>
            <h2 class="section-title"><?= $hasStories ? 'Journeys of Transformation' : 'In Their Own Words' ?></h2>
            <p class="section-subtitle">
                <?php if ($storyCount > 0): ?>
                    <?= (int) $storyCount ?> inspiring <?= $storyCount === 1 ? 'story' : 'stories' ?> of resilience, dignity and new beginnings.
                <?php else: ?>
                    The first chapters of change are being written right now — check back soon.
                <?php endif; ?>
            </p>
        </div>

        <?php if ($hasStories): ?>
            <!-- Primary: success-story blogs -->
            <div class="grid grid-3">
                <?php foreach ($stories as $i => $s):
                    $storyUrl = url('blog-details?slug=' . $s['slug']);
                    $storyDate = $s['published_at'] ?: $s['created_at'];
                ?>
                    <article class="card reveal <?= 'delay-' . (($i % 3) + 1) ?>">
                        <a href="<?= e($storyUrl) ?>" class="card-media">
                            <img src="<?= e(image_url($s['featured_image'], 'blog')) ?>" alt="<?= e($s['title']) ?>" loading="lazy" width="600" height="375">
                            <?php if (!empty($s['is_featured'])): ?>
                                <span class="badge badge-accent" style="position:absolute;top:12px;left:12px;"><?= lucide('star') ?> Featured</span>
                            <?php else: ?>
                                <span class="badge badge-brand" style="position:absolute;top:12px;left:12px;background:#fff;">Success Story</span>
                            <?php endif; ?>
                        </a>
                        <div class="card-body flex flex-col">
                            <h3 class="card-title"><a href="<?= e($storyUrl) ?>" style="color:inherit;"><?= e($s['title']) ?></a></h3>
                            <p class="card-text"><?= e(excerpt($s['excerpt'] ?: $s['content'], 22)) ?></p>
                            <div class="flex items-center justify-between flex-wrap gap-2 mt-auto pt-2">
                                <small class="text-muted">
                                    <?php if (!empty($s['author_name'])): ?><?= lucide('pen-line') ?> <?= e($s['author_name']) ?> · <?php endif; ?>
                                    <?= e(format_date($storyDate, 'd M Y')) ?>
                                </small>
                                <a class="link-action" href="<?= e($storyUrl) ?>" style="color:var(--brand-600,#0B4E3D);font-weight:700;">Read Story <?= lucide('arrow-right') ?></a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($pager['links'])): ?>
                <div class="mt-4"><?= $pager['links'] ?></div>
            <?php endif; ?>

        <?php elseif ($testimonials): ?>
            <!-- Fallback: testimonials as story cards -->
            <div class="grid grid-3">
                <?php foreach ($testimonials as $i => $t): ?>
                    <article class="card reveal <?= 'delay-' . (($i % 3) + 1) ?>">
                        <div class="card-body flex flex-col">
                            <div class="icon-badge accent" style="font-size:1.4rem;"><?= lucide('quote') ?></div>
                            <?= star_rating((int) ($t['rating'] ?? 5)) ?>
                            <p class="card-text mt-2" style="font-size:1.02rem;color:var(--text-soft);">“<?= e($t['message']) ?>”</p>
                            <div class="flex items-center gap-2 mt-auto pt-3">
                                <img src="<?= e(image_url($t['photo'] ?? null, 'avatar')) ?>" alt="<?= e($t['name']) ?>" width="52" height="52" style="width:52px;height:52px;border-radius:50%;object-fit:cover;">
                                <div>
                                    <strong><?= e($t['name']) ?></strong><br>
                                    <small class="text-muted"><?= e($t['designation'] ?? '') ?></small>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <!-- Nothing seeded yet -->
            <div class="empty-state">
                <div class="icon-badge"><?= lucide('sprout') ?></div>
                <h3>Our Story Is Just Beginning</h3>
                <p class="text-muted">We're documenting the journeys of the people and communities we serve. Soon this space will be filled with real stories of hope and transformation. In the meantime, you can be part of the very next one.</p>
                <div class="flex flex-wrap justify-center gap-2 mt-3">
                    <a class="btn btn-primary" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Donate Now</a>
                    <a class="btn btn-outline" href="<?= e(url('volunteer')) ?>"><?= lucide('handshake') ?> Become a Volunteer</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ============================== HOW CHANGE HAPPENS ============================== -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Why These Stories Matter</span>
            <h2 class="section-title">How Lasting Change Happens</h2>
            <p class="section-subtitle">Every story you read here follows the same simple promise — meet people where they are, and walk with them toward a better future.</p>
        </div>
        <div class="grid grid-3">
            <div class="card-3d reveal">
                <div class="icon-badge"><?= lucide('hand-heart') ?></div>
                <h3 class="card-title">We Listen First</h3>
                <p class="card-text">Real change starts with the community. We begin by understanding what each family and village truly needs.</p>
            </div>
            <div class="card-3d reveal delay-1">
                <div class="icon-badge accent"><?= lucide('waypoints') ?></div>
                <h3 class="card-title">We Build Bridges</h3>
                <p class="card-text">From classrooms to health camps to livelihoods, we connect people to the resources and skills that unlock opportunity.</p>
            </div>
            <div class="card-3d reveal delay-2">
                <div class="icon-badge"><?= lucide('sparkles') ?></div>
                <h3 class="card-title">We Hand Over the Torch</h3>
                <p class="card-text">The goal is independence, not dependence. Every story ends with a community empowered to carry its own progress forward.</p>
            </div>
        </div>
    </div>
</section>

<!-- ============================== SHARE YOUR STORY ============================== -->
<section class="section section-alt">
    <div class="container grid grid-2 items-center gap-4">
        <div class="reveal">
            <span class="eyebrow">Been Part of Our Journey?</span>
            <h2 class="section-title">Share Your Story</h2>
            <p class="text-muted">Have you been touched by our work — as a beneficiary, volunteer, partner or supporter? Your experience could inspire someone else to hope, to give, or to get involved. We'd love to hear from you.</p>
            <ul class="list-check mt-3">
                <li>Tell us what changed and how it happened</li>
                <li>We may feature your story to inspire others</li>
                <li>Every voice strengthens the movement</li>
            </ul>
        </div>
        <div class="reveal delay-1">
            <form data-ajax-form data-endpoint="<?= e(url('forms/feedback')) ?>" class="card-3d">
                <?= csrf_field() ?>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Name <span class="req">*</span></label>
                        <input class="form-control" name="name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input class="form-control" type="email" name="email">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Subject</label>
                    <input class="form-control" name="subject" value="My Success Story">
                </div>
                <div class="form-group">
                    <label class="form-label">Your Story <span class="req">*</span></label>
                    <textarea class="form-textarea" name="message" rows="4" required placeholder="Tell us how our work made a difference…"></textarea>
                </div>
                <button class="btn btn-primary btn-block" type="submit">Share My Story</button>
            </form>
        </div>
    </div>
</section>

<!-- ============================== CTA BAND ============================== -->
<section class="section">
    <div class="container">
        <div class="card-3d reveal text-center" style="background:var(--grad-brand);color:#fff;">
            <span class="eyebrow" style="justify-content:center;color:#dbeafe;"><?= e(SITE_TAGLINE) ?></span>
            <h2 class="text-white">Write the Next Success Story</h2>
            <p style="color:rgba(255,255,255,.9);max-width:640px;margin-inline:auto;">Every story on this page began with someone who chose to act. Your support today becomes tomorrow's story of hope for a child, a family, a whole community across Bihar.</p>
            <div class="flex flex-wrap justify-center gap-2 mt-4">
                <a class="btn btn-white btn-lg" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Donate Now</a>
                <a class="btn btn-outline btn-lg" style="border-color:#fff;color:#fff;" href="<?= e(url('volunteer')) ?>"><?= lucide('handshake') ?> Volunteer With Us</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
