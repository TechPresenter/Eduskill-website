<?php
/**
 * =============================================================================
 *  Campaigns — fundraising appeals.
 *
 *  Two modes in one file:
 *    • ?slug=<campaign>  → single campaign detail (image, prose story,
 *                          progress bar, live totals, Donate CTA).
 *    • (no slug)         → listing of active + completed campaigns as cards,
 *                          each with a progress bar, totals and days-left.
 *
 *  Everything is DB-driven with graceful fallbacks so the page never looks
 *  broken before content has been seeded.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* ---- Shared display helpers (page-local closures) --------------------- */

/** Funded percentage (0–100, capped) from goal/raised amounts. */
$pctFunded = static function (array $c): int {
    $goal   = (float) ($c['goal_amount'] ?? 0);
    $raised = (float) ($c['raised_amount'] ?? 0);
    if ($goal <= 0) {
        return 0;
    }
    return (int) min(100, max(0, round($raised / $goal * 100)));
};

/** Human "days left" label based on status + end_date. */
$timeLabel = static function (array $c): string {
    if (($c['status'] ?? '') === 'completed') {
        return 'Goal reached';
    }
    if (empty($c['end_date'])) {
        return 'Ongoing appeal';
    }
    $end  = strtotime($c['end_date'] . ' 23:59:59');
    $days = (int) ceil(($end - time()) / 86400);
    if ($days < 0)  return 'Campaign ended';
    if ($days === 0) return 'Last day to give';
    if ($days === 1) return '1 day left';
    return $days . ' days left';
};

$slug = clean(get('slug', ''));

/* =============================================================================
 |  MODE 1 — SINGLE CAMPAIGN DETAIL
 |============================================================================*/
if ($slug !== '') {

    $campaign = db_row(
        "SELECT * FROM campaigns
         WHERE slug = :s
           AND deleted_at IS NULL
           AND status IN ('active', 'completed', 'paused')
         LIMIT 1",
        [':s' => $slug]
    );

    /* ---- Not found: graceful, indexable-safe fallback ---- */
    if (!$campaign) {
        http_response_code(404);
        seo_set([
            'title'       => 'Campaign Not Found',
            'description' => 'The campaign you are looking for is no longer available. Explore our other active fundraising appeals at EDUSKILL INDIA FOUNDATION.',
            'page_key'    => 'campaigns',
            'robots'      => 'noindex,follow',
        ]);
        $page_hero = [
            'title'      => 'Campaign Not Found',
            'subtitle'   => 'This appeal may have ended or been moved. Discover the causes we are raising for right now.',
            'breadcrumb' => [
                ['label' => 'Campaigns', 'url' => url('campaigns')],
                ['label' => 'Not Found'],
            ],
        ];
        include __DIR__ . '/includes/header.php';
        ?>
        <section class="section">
            <div class="container">
                <div class="empty-state">
                    <div class="icon-badge"><?= lucide('search') ?></div>
                    <h3>We couldn't find that campaign</h3>
                    <p class="text-muted">The link may be outdated, or the campaign has been completed. Please browse our current appeals — every one of them changes lives.</p>
                    <div class="flex flex-wrap justify-center gap-2 mt-3">
                        <a class="btn btn-primary" href="<?= e(url('campaigns')) ?>">View All Campaigns</a>
                        <a class="btn btn-outline" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Donate Now</a>
                    </div>
                </div>
            </div>
        </section>
        <?php
        include __DIR__ . '/includes/footer.php';
        return;
    }

    /* ---- Derived values ---- */
    $goal     = (float) $campaign['goal_amount'];
    $raised   = (float) $campaign['raised_amount'];
    $pct      = $pctFunded($campaign);
    $remaining = max(0, $goal - $raised);
    $donateUrl = url('donate?campaign=' . (int) $campaign['id']);

    /* ---- Supporting figures ---- */
    $donorCount = (int) db_value(
        "SELECT COUNT(*) FROM donations WHERE campaign_id = :id AND status = 'completed'",
        [':id' => $campaign['id']]
    );

    /* ---- 5.8 detail features: gallery, updates feed, milestone markers ---- */
    $cMedia = db_all(
        "SELECT * FROM campaign_media WHERE campaign_id = :c ORDER BY sort_order ASC, id ASC",
        [':c' => $campaign['id']]
    );
    $cUpdates = db_all(
        "SELECT * FROM campaign_updates WHERE campaign_id = :c ORDER BY created_at DESC, id DESC LIMIT 10",
        [':c' => $campaign['id']]
    );
    $cMilestones = function_exists('campaign_milestones') ? campaign_milestones($campaign) : [];
    $shareUrl   = abs_url('campaigns?slug=' . rawurlencode($campaign['slug']));
    $shareText  = $campaign['title'] . ' — support this campaign by ' . get_setting('site_name', SITE_NAME);
    $embedCode  = '<iframe src="' . abs_url('campaign-embed?slug=' . rawurlencode($campaign['slug']))
        . '" width="380" height="320" style="border:0" loading="lazy" title="' . e($campaign['title']) . '"></iframe>';

    /* ---- Related campaigns ---- */
    $related = db_all(
        "SELECT * FROM campaigns
         WHERE status IN ('active', 'completed')
           AND deleted_at IS NULL
           AND id <> :id
         ORDER BY is_featured DESC, id DESC
         LIMIT 3",
        [':id' => $campaign['id']]
    );

    seo_set([
        'title'       => $campaign['title'],
        'description' => $campaign['short_description'] ?: excerpt($campaign['description'] ?? '', 30),
        'image'       => !empty($campaign['image']) ? upload_url($campaign['image']) : null,
        'page_key'    => 'campaign:' . $campaign['slug'],
        'type'        => 'article',
    ]);

    $page_hero = [
        'title'      => $campaign['title'],
        'subtitle'   => $campaign['short_description'] ?: 'Join us in making this cause a reality — every contribution counts.',
        'breadcrumb' => [
            ['label' => 'Campaigns', 'url' => url('campaigns')],
            ['label' => $campaign['title']],
        ],
    ];

    include __DIR__ . '/includes/header.php';
    ?>

    <!-- ============================== CAMPAIGN DETAIL ============================== -->
    <section class="section">
        <div class="container grid grid-sidebar gap-4 items-start">

            <!-- Main story -->
            <article class="reveal">
                <figure class="card-media" style="border-radius:var(--radius,16px);overflow:hidden;margin:0 0 1.5rem;">
                    <img src="<?= e(image_url($campaign['image'])) ?>" alt="<?= e($campaign['title']) ?>" width="900" height="560" style="width:100%;height:auto;display:block;object-fit:cover;">
                </figure>

                <div class="flex items-center gap-2 flex-wrap mb-2">
                    <?php if ($campaign['status'] === 'completed'): ?>
                        <span class="badge badge-success">Successfully Funded</span>
                    <?php elseif ($campaign['status'] === 'paused'): ?>
                        <span class="badge badge-warning" style="background:#fef3c7;color:#92400e;">Temporarily Paused</span>
                    <?php else: ?>
                        <span class="badge badge-accent">Active Campaign</span>
                    <?php endif; ?>
                    <span class="badge badge-muted"><?= e($timeLabel($campaign)) ?></span>
                    <?php if (!empty($campaign['start_date'])): ?>
                        <span class="chip">Started <?= e(format_date($campaign['start_date'], 'd M Y')) ?></span>
                    <?php endif; ?>
                </div>

                <h2 class="section-title" style="margin-top:.25rem;"><?= e($campaign['title']) ?></h2>

                <div class="prose mt-3">
                    <?php if (!empty($campaign['description'])): ?>
                        <?= rich_text($campaign['description']) ?>
                    <?php elseif (!empty($campaign['short_description'])): ?>
                        <p><?= e($campaign['short_description']) ?></p>
                    <?php else: ?>
                        <p>Your generosity powers real change on the ground. Contribute to this campaign and help <?= e(SITE_NAME) ?> reach families and communities across Bihar who need it most.</p>
                    <?php endif; ?>
                </div>

                <?php if ($cMilestones): ?>
                    <!-- Milestone markers -->
                    <div class="glass-card mt-4" style="padding:1.2rem 1.4rem;">
                        <h3 class="mb-2" style="font-size:1.05rem;"><?= lucide('milestone') ?> Campaign Milestones</h3>
                        <ul style="list-style:none;margin:0;padding:0;display:grid;gap:.55rem;">
                            <?php foreach ($cMilestones as $ms): ?>
                                <li class="flex items-center gap-2">
                                    <span class="icon-badge" style="width:28px;height:28px;font-size:.8rem;<?= $ms['reached'] ? '' : 'background:#e5e7eb;color:#6b7280;' ?>">
                                        <?= lucide($ms['reached'] ? 'check' : 'flag') ?>
                                    </span>
                                    <span><strong><?= e(money($ms['amount'])) ?></strong>
                                        <small class="text-muted">(<?= e((string) $ms['percent']) ?>% of goal)</small>
                                        <?= $ms['reached'] ? '<span class="badge badge-success" style="font-size:.65rem;">Reached</span>' : '' ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($cMedia): ?>
                    <!-- Campaign gallery (images open in the shared lightbox) -->
                    <div class="mt-4">
                        <h3 class="mb-2" style="font-size:1.05rem;"><?= lucide('image') ?> Gallery</h3>
                        <div class="grid grid-3 gap-2">
                            <?php foreach ($cMedia as $gm):
                                $gsrc = $gm['type'] === 'video' ? '' : upload_url($gm['path'] ?: $gm['url']); ?>
                                <?php if ($gm['type'] === 'video' && !empty($gm['url'])): ?>
                                    <a class="card-media" href="<?= e(safe_href($gm['url'])) ?>" target="_blank" rel="noopener" style="aspect-ratio:4/3;border-radius:10px;overflow:hidden;position:relative;display:block;background:#111827;">
                                        <span class="gal-play" style="position:absolute;inset:0;display:grid;place-items:center;color:#fff;font-size:1.6rem;"><?= lucide('play') ?></span>
                                        <?php if (!empty($gm['caption'])): ?><span class="sr-only"><?= e($gm['caption']) ?></span><?php endif; ?>
                                    </a>
                                <?php else: ?>
                                    <a class="card-media" data-lightbox="<?= e($gsrc) ?>" data-caption="<?= e($gm['caption'] ?? '') ?>" style="aspect-ratio:4/3;border-radius:10px;overflow:hidden;display:block;">
                                        <img src="<?= e($gsrc) ?>" alt="<?= e($gm['caption'] ?: $campaign['title']) ?>" loading="lazy" width="400" height="300" style="width:100%;height:100%;object-fit:cover;">
                                    </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($cUpdates): ?>
                    <!-- Updates feed -->
                    <div class="mt-4">
                        <h3 class="mb-2" style="font-size:1.05rem;"><?= lucide('megaphone') ?> Campaign Updates</h3>
                        <div style="display:grid;gap:1rem;">
                            <?php foreach ($cUpdates as $up): ?>
                                <article class="glass-card" style="padding:1.1rem 1.3rem;">
                                    <div class="flex items-center justify-between gap-2 flex-wrap">
                                        <strong><?= e($up['title']) ?></strong>
                                        <small class="text-muted"><?= e(format_date($up['created_at'], 'd M Y')) ?></small>
                                    </div>
                                    <div class="prose mt-1" style="font-size:.95rem;"><?= rich_text($up['body']) ?></div>
                                    <?php if (!empty($up['image'])): ?>
                                        <img src="<?= e(upload_url($up['image'])) ?>" alt="" loading="lazy" width="700" height="400" style="width:100%;height:auto;border-radius:10px;margin-top:.6rem;">
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="flex flex-wrap gap-2 mt-4">
                    <a class="btn btn-primary btn-lg" href="<?= e($donateUrl) ?>"><?= lucide('heart') ?> Donate to This Campaign</a>
                    <a class="btn btn-outline btn-lg" href="<?= e(url('campaigns')) ?>"><?= lucide('arrow-left') ?> All Campaigns</a>
                </div>
            </article>

            <!-- Donation sidebar -->
            <aside class="reveal delay-1">
                <div class="card-3d sticky-aside" style="position:sticky;top:90px;">
                    <div class="counter-value" style="font-size:1.9rem;line-height:1.1;"><?= e(money($raised)) ?></div>
                    <p class="text-muted mb-2" style="margin-top:.15rem;">raised of <?= e(money($goal)) ?> goal</p>

                    <div class="progress" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Funding progress">
                        <div class="progress-bar" style="width:<?= $pct ?>%;"></div>
                    </div>
                    <div class="flex justify-between items-center mt-1">
                        <strong style="color:var(--brand-600,#0B4E3D);"><?= $pct ?>% funded</strong>
                        <small class="text-muted"><?= e($timeLabel($campaign)) ?></small>
                    </div>

                    <div class="grid grid-2 gap-2 mt-3 text-center">
                        <div>
                            <div class="counter-value" style="font-size:1.15rem;"><?= e(number_short($donorCount)) ?></div>
                            <small class="text-muted">Supporters</small>
                        </div>
                        <div>
                            <div class="counter-value" style="font-size:1.15rem;"><?= e(money($remaining)) ?></div>
                            <small class="text-muted">Still needed</small>
                        </div>
                    </div>

                    <?php if ($campaign['status'] === 'completed'): ?>
                        <a class="btn btn-success btn-block btn-lg mt-3" href="<?= e($donateUrl) ?>"><?= lucide('heart') ?> Give in Gratitude</a>
                        <p class="text-center text-muted mt-2 mb-0" style="font-size:.85rem;">This goal has been reached — thank you! You can still contribute to sustain the impact.</p>
                    <?php else: ?>
                        <a class="btn btn-primary btn-block btn-lg mt-3" href="<?= e($donateUrl) ?>"><?= lucide('heart') ?> Donate Now</a>
                        <p class="text-center text-muted mt-2 mb-0" style="font-size:.85rem;">Secure &amp; transparent — 100% of your gift funds the mission.</p>
                    <?php endif; ?>

                    <div class="divider"></div>
                    <ul class="list-check" style="font-size:.9rem;">
                        <li>Registered non-profit · CIN <?= e(SITE_CIN) ?></li>
                        <li>Every rupee tracked from donation to delivery</li>
                        <li>Regular updates on your impact</li>
                    </ul>

                    <div class="divider"></div>
                    <!-- Share this campaign -->
                    <p class="mb-1" style="font-weight:700;font-size:.9rem;"><?= lucide('send') ?> Share this campaign</p>
                    <div class="flex gap-2 flex-wrap">
                        <a class="btn btn-outline btn-sm" href="https://wa.me/?text=<?= e(rawurlencode($shareText . ' ' . $shareUrl)) ?>" target="_blank" rel="noopener" aria-label="Share on WhatsApp"><?= social_svg('whatsapp') ?> WhatsApp</a>
                        <a class="btn btn-outline btn-sm" href="https://www.facebook.com/sharer/sharer.php?u=<?= e(rawurlencode($shareUrl)) ?>" target="_blank" rel="noopener" aria-label="Share on Facebook"><?= social_svg('facebook') ?> Share</a>
                        <a class="btn btn-outline btn-sm" href="https://twitter.com/intent/tweet?text=<?= e(rawurlencode($shareText)) ?>&url=<?= e(rawurlencode($shareUrl)) ?>" target="_blank" rel="noopener" aria-label="Share on X"><?= social_svg('twitter') ?> Post</a>
                        <button class="btn btn-outline btn-sm" type="button" data-copy="<?= e($shareUrl) ?>" aria-label="Copy campaign link"><?= lucide('paperclip') ?> Copy link</button>
                    </div>

                    <!-- Embed widget snippet -->
                    <details class="mt-3" style="font-size:.85rem;">
                        <summary style="cursor:pointer;font-weight:700;"><?= lucide('layout-grid') ?> Embed on your website</summary>
                        <p class="text-muted" style="margin:.5rem 0 .4rem;">Paste this snippet to show a live progress widget:</p>
                        <textarea readonly rows="4" class="form-control" style="font-family:monospace;font-size:.75rem;" onclick="this.select()"><?= e($embedCode) ?></textarea>
                    </details>
                </div>
            </aside>
            <script>
                // Copy-link button (uses execCommand fallback for older browsers).
                document.addEventListener('click', function (e) {
                    var b = e.target.closest('[data-copy]');
                    if (!b) return;
                    var url = b.getAttribute('data-copy');
                    (navigator.clipboard ? navigator.clipboard.writeText(url) : Promise.reject()).then(function () {
                        b.innerHTML = 'Copied!';
                    }).catch(function () {
                        var t = document.createElement('textarea');
                        t.value = url; document.body.appendChild(t); t.select();
                        try { document.execCommand('copy'); b.innerHTML = 'Copied!'; } catch (err) {}
                        document.body.removeChild(t);
                    });
                });
            </script>
        </div>
    </section>

    <?php if ($related): ?>
    <!-- ============================== RELATED CAMPAIGNS ============================== -->
    <section class="section section-soft">
        <div class="container">
            <div class="section-head">
                <span class="eyebrow">More Ways to Help</span>
                <h2 class="section-title">Other Campaigns</h2>
                <p class="section-subtitle">Every appeal below supports real communities. Explore more causes and find the one closest to your heart.</p>
            </div>
            <div class="grid grid-3">
                <?php foreach ($related as $i => $rc): $rpct = $pctFunded($rc); ?>
                    <article class="card reveal <?= 'delay-' . (($i % 3) + 1) ?>">
                        <a href="<?= e(url('campaigns?slug=' . $rc['slug'])) ?>" class="card-media">
                            <img src="<?= e(image_url($rc['image'])) ?>" alt="<?= e($rc['title']) ?>" loading="lazy" width="600" height="375">
                        </a>
                        <div class="card-body">
                            <?php if ($rc['status'] === 'completed'): ?>
                                <span class="badge badge-success mb-1">Funded</span>
                            <?php else: ?>
                                <span class="badge badge-accent mb-1"><?= e($timeLabel($rc)) ?></span>
                            <?php endif; ?>
                            <h3 class="card-title"><a href="<?= e(url('campaigns?slug=' . $rc['slug'])) ?>" style="color:inherit;"><?= e($rc['title']) ?></a></h3>
                            <div class="progress mt-1"><div class="progress-bar" style="width:<?= $rpct ?>%;"></div></div>
                            <div class="flex justify-between items-center mt-1">
                                <small class="text-muted"><?= e(money((float) $rc['raised_amount'])) ?> raised</small>
                                <small class="text-muted"><?= $rpct ?>%</small>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ============================== CTA BAND ============================== -->
    <section class="section">
        <div class="container">
            <div class="card-3d reveal text-center" style="background:var(--grad-brand);color:#fff;">
                <span class="eyebrow" style="justify-content:center;color:#dbeafe;"><?= e(SITE_TAGLINE) ?></span>
                <h2 class="text-white">Be the Reason Someone Smiles Today</h2>
                <p style="color:rgba(255,255,255,.9);max-width:640px;margin-inline:auto;">Whether you give, volunteer, or spread the word — your support turns compassion into concrete change for the communities we serve.</p>
                <div class="flex flex-wrap justify-center gap-2 mt-4">
                    <a class="btn btn-white btn-lg" href="<?= e($donateUrl) ?>"><?= lucide('heart') ?> Donate Now</a>
                    <a class="btn btn-outline btn-lg" style="border-color:#fff;color:#fff;" href="<?= e(url('volunteer')) ?>"><?= lucide('handshake') ?> Become a Volunteer</a>
                </div>
            </div>
        </div>
    </section>

    <?php
    include __DIR__ . '/includes/footer.php';
    return;
}

/* =============================================================================
 |  MODE 2 — CAMPAIGN LISTING
 |============================================================================*/

$campaigns = db_all(
    "SELECT * FROM campaigns
     WHERE status IN ('active', 'completed')
       AND deleted_at IS NULL
     ORDER BY (status = 'active') DESC, is_featured DESC, id DESC"
);

/* Aggregate impact figures (only meaningful once seeded). */
$stats = db_row(
    "SELECT COUNT(*) AS cnt,
            COALESCE(SUM(goal_amount), 0)   AS goal,
            COALESCE(SUM(raised_amount), 0) AS raised
     FROM campaigns
     WHERE status IN ('active', 'completed') AND deleted_at IS NULL"
) ?: ['cnt' => 0, 'goal' => 0, 'raised' => 0];

$totalDonors = (int) db_value("SELECT COUNT(*) FROM donations WHERE status = 'completed'");

seo_set([
    'title'       => 'Campaigns',
    'description' => 'Support an active fundraising campaign at EDUSKILL INDIA FOUNDATION. Fund education, healthcare, relief and empowerment work across Bihar — every contribution creates real change.',
    'page_key'    => 'campaigns',
]);

$page_hero = [
    'title'      => 'Our Campaigns',
    'subtitle'   => 'Real causes, real people, real impact. Choose a campaign that speaks to you and help us turn hope into lasting change across Bihar.',
    'breadcrumb' => [['label' => 'Campaigns']],
];

include __DIR__ . '/includes/header.php';
?>

<!-- ============================== INTRO + IMPACT STATS ============================== -->
<section class="section">
    <div class="container">
        <div class="grid grid-2 items-center gap-4">
            <div class="reveal">
                <span class="eyebrow">Why Campaigns Matter</span>
                <h2 class="section-title"><?= e(get_setting('campaigns_intro_title', 'Focused fundraising for the change that can\'t wait')) ?></h2>
                <p class="text-muted"><?= e(get_setting('campaigns_intro_text', 'Each campaign is a promise to a community — a school kit for a child, a health camp for a village, relief for a family in crisis. When you give to a campaign, you can see exactly where your generosity goes and the goal it helps us reach.')) ?></p>
                <ul class="list-check mt-3">
                    <li>Transparent goals with live progress tracking</li>
                    <li>Every rupee tracked from donation to delivery</li>
                    <li>Regular updates on the impact you create</li>
                </ul>
                <a class="btn btn-primary mt-4" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Donate Now</a>
            </div>
            <div class="grid grid-2 gap-3 reveal delay-1">
                <div class="card-3d text-center">
                    <div class="counter-value" style="font-size:1.6rem;"><span data-counter="<?= (int) $stats['cnt'] ?>">0</span></div>
                    <div class="counter-label">Campaigns</div>
                </div>
                <div class="card-3d text-center">
                    <div class="counter-value" style="font-size:1.6rem;"><span data-counter="<?= (int) $stats['raised'] ?>">0</span></div>
                    <div class="counter-label">Raised (₹)</div>
                </div>
                <div class="card-3d text-center">
                    <div class="counter-value" style="font-size:1.6rem;"><span data-counter="<?= (int) $stats['goal'] ?>">0</span></div>
                    <div class="counter-label">Goal (₹)</div>
                </div>
                <div class="card-3d text-center">
                    <div class="counter-value" style="font-size:1.6rem;"><span data-counter="<?= $totalDonors > 0 ? $totalDonors : 1200 ?>">0</span>+</div>
                    <div class="counter-label">Supporters</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================== CAMPAIGN CARDS ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Join a Cause</span>
            <h2 class="section-title">Active &amp; Recent Campaigns</h2>
            <p class="section-subtitle">Pick a campaign, track its progress, and be part of the story of change.</p>
        </div>

        <?php if ($campaigns): ?>
            <div class="pcard-grid">
                <?php foreach ($campaigns as $i => $c):
                    $pct        = $pctFunded($c);
                    $cUrl       = url('campaigns?slug=' . $c['slug']);
                    $cDonateUrl = url('donate?campaign=' . (int) $c['id']);
                    $isDone     = $c['status'] === 'completed';
                ?>
                    <article class="pcard reveal <?= 'delay-' . (($i % 3) + 1) ?>">
                        <a class="pcard-media" href="<?= e($cUrl) ?>" aria-label="<?= e($c['title']) ?>">
                            <img src="<?= e(image_url($c['image'])) ?>" alt="<?= e($c['title']) ?>" loading="lazy" width="600" height="412">
                            <span class="pcard-badge <?= $isDone ? 'is-funded' : 'is-live' ?>">
                                <?= $isDone ? lucide('check') . ' Funded' : lucide('clock') . ' ' . e($timeLabel($c)) ?>
                            </span>
                        </a>
                        <a class="pcard-fav" href="<?= e($cDonateUrl) ?>" aria-label="Donate to <?= e($c['title']) ?>"><?= lucide('heart') ?></a>

                        <div class="pcard-body">
                            <span class="pcard-eyebrow"><?= $pct ?>% funded</span>
                            <h3 class="pcard-title"><a href="<?= e($cUrl) ?>"><?= e($c['title']) ?></a></h3>
                            <p class="pcard-text"><?= e($c['short_description'] ? excerpt($c['short_description'], 16) : excerpt($c['description'] ?? '', 16)) ?></p>

                            <div class="pcard-prog" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Funding progress">
                                <span style="width:<?= $pct ?>%;"></span>
                            </div>
                            <div class="pcard-figs">
                                <span><b><?= e(money((float) $c['raised_amount'])) ?></b> <span class="muted">raised</span></span>
                                <span class="muted">of <?= e(money((float) $c['goal_amount'])) ?></span>
                            </div>
                        </div>

                        <div class="pcard-strip">
                            <span class="ps-col"><span class="ps-k">Goal</span><span class="ps-v"><?= e(money((float) $c['goal_amount'])) ?></span></span>
                            <span class="ps-col"><span class="ps-k"><?= $isDone ? 'Status' : 'Time left' ?></span><span class="ps-v"><?= $isDone ? 'Complete' : e($timeLabel($c)) ?></span></span>
                            <a class="ps-cta" href="<?= e($cDonateUrl) ?>"><?= lucide('heart') ?> Donate</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon-badge"><?= lucide('target') ?></div>
                <h3>New Campaigns Are on the Way</h3>
                <p class="text-muted">We're preparing our next fundraising appeals. In the meantime, your general donation still powers education, healthcare and relief work across Bihar — every gift makes a difference.</p>
                <div class="flex flex-wrap justify-center gap-2 mt-3">
                    <a class="btn btn-primary" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Donate Now</a>
                    <a class="btn btn-outline" href="<?= e(url('contact')) ?>">Talk to Our Team</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ============================== HOW YOUR GIFT HELPS ============================== -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Your Impact</span>
            <h2 class="section-title">Where Every Rupee Goes</h2>
            <p class="section-subtitle">Transparency is at the heart of everything we do. Here's how your contribution creates change on the ground.</p>
        </div>
        <div class="grid grid-3">
            <div class="card-3d reveal">
                <div class="icon-badge"><?= lucide('book') ?></div>
                <h3 class="card-title">Educate a Child</h3>
                <p class="card-text">Fund learning kits, scholarships and safe classrooms so no child is left behind.</p>
            </div>
            <div class="card-3d reveal delay-1">
                <div class="icon-badge accent"><?= lucide('stethoscope') ?></div>
                <h3 class="card-title">Deliver Healthcare</h3>
                <p class="card-text">Support free medical camps, essential medicines and health awareness in rural areas.</p>
            </div>
            <div class="card-3d reveal delay-2">
                <div class="icon-badge"><?= lucide('life-buoy') ?></div>
                <h3 class="card-title">Provide Relief</h3>
                <p class="card-text">Bring rapid food, shelter and rehabilitation to families facing crisis and disaster.</p>
            </div>
        </div>
    </div>
</section>

<!-- ============================== CTA BAND ============================== -->
<section class="section section-alt">
    <div class="container">
        <div class="card-3d reveal text-center" style="background:var(--grad-brand);color:#fff;">
            <span class="eyebrow" style="justify-content:center;color:#dbeafe;"><?= e(SITE_TAGLINE) ?></span>
            <h2 class="text-white">Together, We Can Reach Every Goal</h2>
            <p style="color:rgba(255,255,255,.9);max-width:640px;margin-inline:auto;">Start a ripple of hope today. Give to a campaign, rally your friends, or volunteer your time — change begins the moment you choose to act.</p>
            <div class="flex flex-wrap justify-center gap-2 mt-4">
                <a class="btn btn-white btn-lg" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Donate Now</a>
                <a class="btn btn-outline btn-lg" style="border-color:#fff;color:#fff;" href="<?= e(url('volunteer')) ?>"><?= lucide('handshake') ?> Become a Volunteer</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
