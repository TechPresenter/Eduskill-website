<?php
/**
 * =============================================================================
 *  Programs — premium listing of all programs (3D cards) plus a filterable
 *  projects grid (filtered client-side by program = category).
 *  DB-driven with graceful fallbacks.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* =============================================================================
 |  PROGRAM DETAIL (?slug=…) — description, projects under the program, CTA
 |============================================================================*/
$slug = clean(get('slug', ''));
if ($slug !== '') {
    $program = db_row(
        "SELECT * FROM programs WHERE slug = :s AND status = 'active' AND deleted_at IS NULL LIMIT 1",
        [':s' => $slug]
    );

    if (!$program) {
        http_response_code(404);
        seo_set(['title' => 'Program Not Found', 'page_key' => 'programs', 'robots' => 'noindex,nofollow']);
        $page_hero = ['title' => 'Program Not Found', 'subtitle' => 'The program you are looking for does not exist or is no longer active.', 'breadcrumb' => [['label' => 'Programs', 'url' => url('programs')], ['label' => 'Not found']]];
        include __DIR__ . '/includes/header.php'; ?>
        <section class="section"><div class="container" style="max-width:560px;text-align:center;">
            <div class="card-3d reveal" style="padding:2.2rem;">
                <div class="icon-badge" style="margin:0 auto 1rem;font-size:1.6rem;"><?= lucide('search-x') ?></div>
                <h2 class="mb-1">We couldn't find that program</h2>
                <p class="text-muted">It may have been renamed or retired. Browse all our active programs instead.</p>
                <a class="btn btn-primary mt-2" href="<?= e(url('programs')) ?>"><?= lucide('arrow-left') ?> All Programs</a>
            </div>
        </div></section>
        <?php include __DIR__ . '/includes/footer.php'; exit;
    }

    $progProjects = db_all(
        "SELECT * FROM projects WHERE program_id = :p AND deleted_at IS NULL
         ORDER BY is_featured DESC, sort_order ASC, id DESC",
        [':p' => $program['id']]
    );

    seo_set([
        'title'       => $program['title'],
        'description' => excerpt($program['short_description'] ?: strip_tags((string) $program['description']), 28),
        'page_key'    => 'programs',
        'image'       => $program['image'] ?: null,
    ]);
    $page_hero = [
        'title'      => $program['title'],
        'subtitle'   => $program['short_description'] ?? '',
        'breadcrumb' => [['label' => 'Programs', 'url' => url('programs')], ['label' => $program['title']]],
    ];
    include __DIR__ . '/includes/header.php'; ?>

    <section class="section">
        <div class="container" style="max-width:960px;">
            <div class="grid" style="grid-template-columns:2fr 1fr;gap:2rem;align-items:start;">
                <article class="reveal">
                    <?php if (!empty($program['image'])): ?>
                        <figure class="card-media mb-3" style="border-radius:14px;overflow:hidden;">
                            <img src="<?= e(image_url($program['image'])) ?>" alt="<?= e($program['title']) ?>" width="900" height="500" style="width:100%;height:auto;">
                        </figure>
                    <?php endif; ?>
                    <div class="prose">
                        <?= rich_text($program['description'] ?: $program['short_description']) ?>
                    </div>
                </article>
                <aside class="card-3d reveal delay-1" style="position:sticky;top:90px;">
                    <div class="icon-badge" style="background:linear-gradient(135deg,<?= e($program['color'] ?: '#063566') ?>,#084881);"><?= lucide($program['icon'] ?: 'star') ?></div>
                    <h3 class="card-title mt-2">Support this program</h3>
                    <p class="card-text">Your contribution directly funds <?= e($program['title']) ?> work on the ground.</p>
                    <a class="btn btn-primary btn-block mt-2" href="<?= e(url('donate?program=' . $program['slug'])) ?>"><?= lucide('heart') ?> Donate</a>
                    <a class="btn btn-outline btn-block mt-2" href="<?= e(url('volunteer')) ?>"><?= lucide('hand-heart') ?> Volunteer</a>
                </aside>
            </div>
        </div>
    </section>

    <?php if ($progProjects): ?>
    <section class="section section-soft">
        <div class="container">
            <div class="section-head">
                <span class="eyebrow">On the Ground</span>
                <h2 class="section-title">Projects under <?= e($program['title']) ?></h2>
            </div>
            <div class="grid grid-3">
                <?php foreach ($progProjects as $i => $pj): ?>
                    <article class="card reveal <?= 'delay-' . (($i % 3) + 1) ?>">
                        <div class="card-media">
                            <img src="<?= e(image_url($pj['image'])) ?>" alt="<?= e($pj['title']) ?>" loading="lazy" width="600" height="375">
                        </div>
                        <div class="card-body">
                            <h3 class="card-title"><?= e($pj['title']) ?></h3>
                            <p class="card-text"><?= e(excerpt($pj['summary'] ?: $pj['description'], 18)) ?></p>
                            <a href="<?= e(url('projects?slug=' . $pj['slug'])) ?>" style="color:#063566;font-weight:700;">View project <?= lucide('arrow-right') ?></a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php include __DIR__ . '/includes/footer.php'; exit;
}

$programs = db_all("SELECT * FROM programs WHERE status='active' AND deleted_at IS NULL ORDER BY is_featured DESC, sort_order ASC, id ASC");

$projects = db_all(
    "SELECT pr.*, p.title AS program_title, p.slug AS program_slug
     FROM projects pr
     LEFT JOIN programs p ON p.id = pr.program_id
     WHERE pr.deleted_at IS NULL
     ORDER BY pr.is_featured DESC, pr.sort_order ASC, pr.id DESC"
);

/* Fallback programs so the page is never empty before seeding. */
if (!$programs) {
    $programs = [
        ['title' => 'Education for All', 'slug' => '', 'icon' => 'book', 'color' => '#063566', 'short_description' => 'Free schooling, scholarships and learning kits for underprivileged children across Bihar.'],
        ['title' => 'Healthcare & Camps', 'slug' => '', 'icon' => 'stethoscope', 'color' => '#084881', 'short_description' => 'Free medical camps, health awareness and access to essential care in rural areas.'],
        ['title' => 'Women Empowerment', 'slug' => '', 'icon' => 'user', 'color' => '#E67B1D', 'short_description' => 'Skill training, self-help groups and livelihood support for women.'],
        ['title' => 'Skill Development', 'slug' => 'skill-development', 'icon' => 'wrench', 'color' => '#4ca832', 'short_description' => 'Vocational training that prepares youth for sustainable employment.'],
        ['title' => 'Clean Water & Sanitation', 'slug' => '', 'icon' => 'droplet', 'color' => '#084881', 'short_description' => 'Safe drinking water, hygiene drives and sanitation infrastructure.'],
        ['title' => 'Relief & Rehabilitation', 'slug' => '', 'icon' => 'life-buoy', 'color' => '#dc2626', 'short_description' => 'Rapid disaster relief, food distribution and long-term rehabilitation.'],
    ];
}

/* Distinct programs that have projects (for the filter chips). */
$filterCats = [];
foreach ($projects as $pj) {
    if (!empty($pj['program_slug']) && !isset($filterCats[$pj['program_slug']])) {
        $filterCats[$pj['program_slug']] = $pj['program_title'];
    }
}

seo_set([
    'title'       => 'Our Programs',
    'description' => 'Explore the programs and projects through which EDUSKILL INDIA FOUNDATION delivers education, healthcare, skill development and relief across Bihar.',
    'page_key'    => 'programs',
]);
$page_hero = [
    'title'      => 'Our Programs',
    'subtitle'   => 'Focused initiatives creating real, measurable change in the communities we serve.',
    'breadcrumb' => [['label' => 'Programs']],
];
include __DIR__ . '/includes/header.php';
?>

<!-- ============================== PROGRAMS GRID ============================== -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">What We Do</span>
            <h2 class="section-title">Areas of Impact</h2>
            <p class="section-subtitle">Every program is designed around real community needs — measured, transparent and sustainable.</p>
        </div>
        <div class="pcard-grid">
            <?php foreach ($programs as $i => $p):
                $link  = !empty($p['slug']) ? url('programs?slug=' . $p['slug']) : url('programs');
                $accent = $p['color'] ?: '#063566';
                $img   = !empty($p['image']) ? image_url($p['image']) : ''; ?>
                <article class="pcard reveal <?= 'delay-' . (($i % 3) + 1) ?>">
                    <a class="pcard-media" href="<?= e($link) ?>" aria-label="<?= e($p['title']) ?>">
                        <?php if ($img): ?>
                            <img src="<?= e($img) ?>" alt="<?= e($p['title']) ?>" loading="lazy" width="600" height="412">
                        <?php else: ?>
                            <span class="pcard-ph" style="--pc:<?= e($accent) ?>"><?= lucide($p['icon'] ?: 'star') ?></span>
                        <?php endif; ?>
                        <span class="pcard-badge" style="background:linear-gradient(135deg,<?= e($accent) ?>,#084881);border-color:transparent;"><?= lucide($p['icon'] ?: 'star') ?> Program</span>
                    </a>

                    <div class="pcard-body">
                        <span class="pcard-eyebrow">Area of Impact</span>
                        <h3 class="pcard-title"><a href="<?= e($link) ?>"><?= e($p['title']) ?></a></h3>
                        <p class="pcard-text"><?= e(excerpt($p['short_description'] ?? '', 20)) ?></p>
                    </div>

                    <div class="pcard-strip" style="background:linear-gradient(135deg,<?= e($accent) ?>,#084881);">
                        <span class="ps-col"><span class="ps-k">Focus area</span><span class="ps-v"><?= e(excerpt($p['title'], 3)) ?></span></span>
                        <a class="ps-cta" href="<?= e($link) ?>">Learn more <?= lucide('arrow-right') ?></a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if ($projects): ?>
<!-- ============================== PROJECTS (FILTERABLE) ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">On the Ground</span>
            <h2 class="section-title">Our Projects</h2>
            <p class="section-subtitle">Filter by program to see the work happening across each of our focus areas.</p>
        </div>

        <div class="filter-chips" data-prog-filter role="tablist" aria-label="Filter projects by program">
            <button class="filter-chip is-active" data-filter="all" role="tab" aria-selected="true">All Projects</button>
            <?php foreach ($filterCats as $slug => $title): ?>
                <button class="filter-chip" data-filter="<?= e($slug) ?>" role="tab" aria-selected="false"><?= e($title) ?></button>
            <?php endforeach; ?>
        </div>

        <div class="grid grid-3 mt-4" data-prog-grid>
            <?php foreach ($projects as $i => $pj): ?>
                <article class="card reveal proj-item" data-cat="<?= e($pj['program_slug'] ?? '') ?>">
                    <div class="card-media">
                        <img src="<?= e(image_url($pj['image'])) ?>" alt="<?= e($pj['title']) ?>" loading="lazy" width="600" height="375">
                        <?php if (!empty($pj['status'])): ?><span class="badge badge-brand" style="position:absolute;top:12px;left:12px;background:#fff;"><?= e(ucfirst($pj['status'])) ?></span><?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($pj['program_title'])): ?><span class="badge badge-muted mb-1"><?= e($pj['program_title']) ?></span><?php endif; ?>
                        <h3 class="card-title"><?= e($pj['title']) ?></h3>
                        <p class="card-text"><?= e(excerpt($pj['summary'] ?: $pj['description'], 18)) ?></p>
                        <?php if (!empty($pj['location'])): ?><small class="text-muted"><?= lucide('map-pin') ?> <?= e($pj['location']) ?></small><?php endif; ?>
                        <?php if (isset($pj['progress']) && $pj['progress'] > 0): ?>
                            <div class="progress mt-2" aria-label="Progress <?= (int) $pj['progress'] ?>%"><div class="progress-bar" style="width:<?= (int) $pj['progress'] ?>%;"></div></div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <p class="text-center text-muted mt-3" data-prog-empty hidden>No projects in this program yet.</p>
    </div>
</section>

<script>
    (function () {
        var bar = document.querySelector('[data-prog-filter]');
        if (!bar) return;
        var items = Array.prototype.slice.call(document.querySelectorAll('[data-prog-grid] .proj-item'));
        var empty = document.querySelector('[data-prog-empty]');
        bar.addEventListener('click', function (e) {
            var chip = e.target.closest('.filter-chip');
            if (!chip) return;
            bar.querySelectorAll('.filter-chip').forEach(function (c) { c.classList.remove('is-active'); c.setAttribute('aria-selected', 'false'); });
            chip.classList.add('is-active'); chip.setAttribute('aria-selected', 'true');
            var f = chip.getAttribute('data-filter'), shown = 0;
            items.forEach(function (it) {
                var show = (f === 'all' || it.getAttribute('data-cat') === f);
                it.style.display = show ? '' : 'none';
                if (show) shown++;
            });
            if (empty) empty.hidden = shown !== 0;
        });
    })();
</script>
<?php endif; ?>

<!-- ============================== CTA ============================== -->
<section class="section">
    <div class="container">
        <div class="card-3d text-center" style="background:linear-gradient(135deg,#063566,#084881);color:#fff;">
            <h2 class="text-white">Want to power a program?</h2>
            <p style="color:rgba(255,255,255,.9);max-width:620px;margin-inline:auto;">Your support helps us expand these initiatives to more villages and families across Bihar.</p>
            <div class="flex flex-wrap justify-center gap-2 mt-3">
                <a class="btn btn-white btn-lg" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Donate Now</a>
                <a class="btn btn-outline btn-lg" style="border-color:#fff;color:#fff;" href="<?= e(url('volunteer')) ?>">Volunteer With Us</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
