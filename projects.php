<?php
/**
 * =============================================================================
 *  Projects — public listing of on-the-ground projects (+ ?slug= detail view).
 *  Linked from About ("See Our Work"), Causes and Skill Development CTAs.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* =============================================================================
 |  PROJECT DETAIL (?slug=…)
 |============================================================================*/
$slug = clean(get('slug', ''));
if ($slug !== '') {
    $project = db_row(
        "SELECT pr.*, p.title AS program_title, p.slug AS program_slug
         FROM projects pr
         LEFT JOIN programs p ON p.id = pr.program_id
         WHERE pr.slug = :s AND pr.deleted_at IS NULL LIMIT 1",
        [':s' => $slug]
    );

    if (!$project) {
        http_response_code(404);
        seo_set(['title' => 'Project Not Found', 'page_key' => 'projects', 'robots' => 'noindex,nofollow']);
        $page_hero = ['title' => 'Project Not Found', 'subtitle' => 'The project you are looking for does not exist.', 'breadcrumb' => [['label' => 'Projects', 'url' => url('projects')], ['label' => 'Not found']]];
        include __DIR__ . '/includes/header.php'; ?>
        <section class="section"><div class="container" style="max-width:560px;text-align:center;">
            <div class="card-3d reveal" style="padding:2.2rem;">
                <div class="icon-badge" style="margin:0 auto 1rem;font-size:1.6rem;"><?= lucide('search-x') ?></div>
                <h2 class="mb-1">We couldn't find that project</h2>
                <p class="text-muted">It may have been completed and archived. Browse all our projects instead.</p>
                <a class="btn btn-primary mt-2" href="<?= e(url('projects')) ?>"><?= lucide('arrow-left') ?> All Projects</a>
            </div>
        </div></section>
        <?php include __DIR__ . '/includes/footer.php'; exit;
    }

    $statusLabel = ucfirst((string) ($project['status'] ?: 'ongoing'));
    seo_set([
        'title'       => $project['title'],
        'description' => excerpt($project['summary'] ?: strip_tags((string) $project['description']), 28),
        'page_key'    => 'projects',
        'image'       => $project['image'] ?: null,
    ]);
    $page_hero = [
        'title'      => $project['title'],
        'subtitle'   => $project['summary'] ?? '',
        'breadcrumb' => [['label' => 'Projects', 'url' => url('projects')], ['label' => $project['title']]],
    ];
    include __DIR__ . '/includes/header.php'; ?>

    <section class="section">
        <div class="container" style="max-width:960px;">
            <div class="grid" style="grid-template-columns:2fr 1fr;gap:2rem;align-items:start;">
                <article class="reveal">
                    <?php if (!empty($project['image'])): ?>
                        <figure class="card-media mb-3" style="border-radius:14px;overflow:hidden;">
                            <img src="<?= e(image_url($project['image'])) ?>" alt="<?= e($project['title']) ?>" width="900" height="500" style="width:100%;height:auto;">
                        </figure>
                    <?php endif; ?>
                    <div class="prose">
                        <?= rich_text($project['description'] ?: $project['summary']) ?>
                    </div>
                </article>
                <aside class="card-3d reveal delay-1" style="position:sticky;top:90px;">
                    <h3 class="card-title">Project Facts</h3>
                    <ul class="list-unstyled" style="display:grid;gap:.65rem;margin:.8rem 0 0;padding:0;list-style:none;">
                        <li><?= lucide('signal') ?> <strong>Status:</strong> <?= e($statusLabel) ?></li>
                        <?php if (!empty($project['program_title'])): ?>
                            <li><?= lucide('target') ?> <strong>Program:</strong>
                                <a href="<?= e(url('programs?slug=' . $project['program_slug'])) ?>"><?= e($project['program_title']) ?></a></li>
                        <?php endif; ?>
                        <?php if (!empty($project['location'])): ?><li><?= lucide('map-pin') ?> <strong>Location:</strong> <?= e($project['location']) ?></li><?php endif; ?>
                        <?php if (!empty($project['start_date'])): ?><li><?= lucide('calendar') ?> <strong>Started:</strong> <?= e(date('M Y', strtotime($project['start_date']))) ?></li><?php endif; ?>
                        <?php if (!empty($project['beneficiaries'])): ?><li><?= lucide('users') ?> <strong>Beneficiaries:</strong> <?= e(number_format((float) $project['beneficiaries'])) ?>+</li><?php endif; ?>
                        <?php if (!empty($project['budget']) && (float) $project['budget'] > 0): ?><li><?= lucide('coins') ?> <strong>Budget:</strong> <?= e(money($project['budget'])) ?></li><?php endif; ?>
                    </ul>
                    <?php if (isset($project['progress']) && (int) $project['progress'] > 0): ?>
                        <div class="mt-3">
                            <div class="flex" style="justify-content:space-between;font-size:.85rem;"><span>Progress</span><strong><?= (int) $project['progress'] ?>%</strong></div>
                            <div class="progress mt-1" aria-label="Project progress <?= (int) $project['progress'] ?>%"><div class="progress-bar" style="width:<?= (int) $project['progress'] ?>%;"></div></div>
                        </div>
                    <?php endif; ?>
                    <a class="btn btn-primary btn-block mt-3" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Support This Work</a>
                </aside>
            </div>
        </div>
    </section>

    <?php include __DIR__ . '/includes/footer.php'; exit;
}

/* =============================================================================
 |  PROJECTS LISTING
 |============================================================================*/
$projects = db_all(
    "SELECT pr.*, p.title AS program_title, p.slug AS program_slug
     FROM projects pr
     LEFT JOIN programs p ON p.id = pr.program_id
     WHERE pr.deleted_at IS NULL
     ORDER BY pr.is_featured DESC, pr.sort_order ASC, pr.id DESC"
);

seo_set([
    'title'       => 'Our Projects',
    'description' => 'On-the-ground projects by EDUSKILL INDIA FOUNDATION — schools, health camps, skill centres and relief work across Bihar.',
    'page_key'    => 'projects',
]);
$page_hero = [
    'title'      => 'Our Projects',
    'subtitle'   => 'Real work, real places, real people — explore what your support builds on the ground.',
    'breadcrumb' => [['label' => 'Projects']],
];
include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">On the Ground</span>
            <h2 class="section-title">Where We Work</h2>
            <p class="section-subtitle">Every project is tied to a program, a place and a measurable outcome.</p>
        </div>
        <?php if ($projects): ?>
            <div class="grid grid-3">
                <?php foreach ($projects as $i => $pj): ?>
                    <article class="card reveal <?= 'delay-' . (($i % 3) + 1) ?>">
                        <div class="card-media">
                            <img src="<?= e(image_url($pj['image'])) ?>" alt="<?= e($pj['title']) ?>" loading="lazy" width="600" height="375">
                            <?php if (!empty($pj['status'])): ?><span class="badge badge-brand" style="position:absolute;top:12px;left:12px;background:#fff;"><?= e(ucfirst($pj['status'])) ?></span><?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($pj['program_title'])): ?><span class="badge badge-muted mb-1"><?= e($pj['program_title']) ?></span><?php endif; ?>
                            <h3 class="card-title"><?= e($pj['title']) ?></h3>
                            <p class="card-text"><?= e(excerpt($pj['summary'] ?: $pj['description'], 18)) ?></p>
                            <?php if (!empty($pj['location'])): ?><small class="text-muted"><?= lucide('map-pin') ?> <?= e($pj['location']) ?></small><?php endif; ?>
                            <div class="mt-2"><a href="<?= e(url('projects?slug=' . $pj['slug'])) ?>" style="color:#063566;font-weight:700;">View project <?= lucide('arrow-right') ?></a></div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state text-center" style="padding:3rem 1rem;">
                <div class="icon-badge" style="margin:0 auto 1rem;"><?= lucide('folder-open') ?></div>
                <p class="text-muted">Projects will appear here soon — see our <a href="<?= e(url('programs')) ?>">programs</a> for what we do.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ============================== CTA ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="card-3d text-center" style="background:linear-gradient(135deg,#063566,#084881);color:#fff;">
            <h2 class="text-white">Help us start the next project</h2>
            <p style="color:rgba(255,255,255,.9);max-width:620px;margin-inline:auto;">Every contribution moves a village closer to education, health and opportunity.</p>
            <div class="flex flex-wrap justify-center gap-2 mt-3">
                <a class="btn btn-white btn-lg" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Donate Now</a>
                <a class="btn btn-outline btn-lg" style="border-color:#fff;color:#fff;" href="<?= e(url('volunteer')) ?>">Volunteer With Us</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
