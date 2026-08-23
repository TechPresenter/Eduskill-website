<?php
/**
 * =============================================================================
 *  Schemes & Initiatives — welfare schemes offered / facilitated by the
 *  Foundation and its partners.
 *
 *  Two modes in one file:
 *    - ?slug=<scheme>  -> single scheme detail (prose description, eligibility,
 *                        benefits, documents required as a list-check, deadline
 *                        and an Apply CTA -> apply_url, else the contact page).
 *    - (no slug)       -> listing of active schemes with a category tab filter,
 *                        each card showing title, department, short description,
 *                        an eligibility snippet, the deadline and an Apply CTA.
 *
 *  Every section is DB-driven with graceful fallbacks (curated default schemes
 *  or an empty-state) so the page looks complete before any content is seeded.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* ---- Shared display helpers (page-local closures) --------------------- */

/**
 * Split a free-text field (one item per line, or bullet/semicolon separated)
 * into a clean array of items — used for eligibility, benefits and documents.
 */
$toList = static function (?string $text): array {
    $text = trim((string) $text);
    if ($text === '') {
        return [];
    }
    $parts = preg_split('/\r\n|\r|\n|•|;/u', $text) ?: [];
    $out = [];
    foreach ($parts as $p) {
        $p = trim($p, " \t\r\n-–—*·•▪ ");
        if ($p !== '') {
            $out[] = $p;
        }
    }
    return $out;
};

/** Deadline -> human badge info: text + badge class + whether still open. */
$deadlineInfo = static function (?string $d): array {
    if (empty($d) || $d === '0000-00-00') {
        return ['text' => 'Rolling · always open', 'class' => 'badge-success', 'open' => true];
    }
    $ts = strtotime($d . ' 23:59:59');
    if ($ts === false) {
        return ['text' => 'Open for applications', 'class' => 'badge-success', 'open' => true];
    }
    $nice = format_date($d, 'd M Y');
    $days = (int) ceil(($ts - time()) / 86400);
    if ($days < 0)   return ['text' => 'Closed · ' . $nice,        'class' => 'badge-danger',  'open' => false];
    if ($days === 0) return ['text' => 'Closes today · ' . $nice,  'class' => 'badge-accent',  'open' => true];
    if ($days === 1) return ['text' => '1 day left · ' . $nice,    'class' => 'badge-accent',  'open' => true];
    if ($days <= 14) return ['text' => $days . ' days left · ' . $nice, 'class' => 'badge-accent', 'open' => true];
    return ['text' => 'Apply by ' . $nice, 'class' => 'badge-brand', 'open' => true];
};

/** Resolve the Apply URL: apply_url (absolute or internal) else contact page. */
$applyLink = static function (array $s): string {
    $u = trim((string) ($s['apply_url'] ?? ''));
    if ($u !== '') {
        return preg_match('#^https?://#i', $u) ? $u : url($u);
    }
    return url('contact');
};

/** True when apply_url points to an external site (open in a new tab). */
$applyExternal = static function (array $s): bool {
    return (bool) preg_match('#^https?://#i', trim((string) ($s['apply_url'] ?? '')));
};

/** Icon + gradient for a category (keyword match, rotating fallback). */
$catMeta = static function (string $cat, int $i = 0): array {
    $key = strtolower(trim($cat));
    $map = [
        'scholarship' => ['icon' => 'graduation-cap', 'grad' => 'var(--grad-brand)'],
        'education'   => ['icon' => 'graduation-cap', 'grad' => 'var(--grad-brand)'],
        'health'      => ['icon' => 'stethoscope', 'grad' => 'var(--grad-green)'],
        'medical'     => ['icon' => 'stethoscope', 'grad' => 'var(--grad-green)'],
        'women'       => ['icon' => 'user', 'grad' => 'var(--grad-purple)'],
        'girl'        => ['icon' => 'user', 'grad' => 'var(--grad-purple)'],
        'empower'     => ['icon' => 'zap', 'grad' => 'var(--grad-purple)'],
        'skill'       => ['icon' => 'wrench', 'grad' => 'var(--grad-orange)'],
        'employ'      => ['icon' => 'briefcase', 'grad' => 'var(--grad-orange)'],
        'livelihood'  => ['icon' => 'wheat', 'grad' => 'var(--grad-green)'],
        'agri'        => ['icon' => 'wheat', 'grad' => 'var(--grad-green)'],
        'farmer'      => ['icon' => 'wheat', 'grad' => 'var(--grad-green)'],
        'housing'     => ['icon' => 'home', 'grad' => 'var(--grad-ocean)'],
        'water'       => ['icon' => 'droplet', 'grad' => 'var(--grad-ocean)'],
        'senior'      => ['icon' => 'user', 'grad' => 'var(--grad-sunset)'],
        'elder'       => ['icon' => 'user', 'grad' => 'var(--grad-sunset)'],
        'disab'       => ['icon' => 'accessibility', 'grad' => 'var(--grad-purple)'],
        'financ'      => ['icon' => 'coins', 'grad' => 'var(--grad-brand)'],
        'pension'     => ['icon' => 'coins', 'grad' => 'var(--grad-brand)'],
        'child'       => ['icon' => 'baby', 'grad' => 'var(--grad-sunset)'],
        'relief'      => ['icon' => 'life-buoy', 'grad' => 'var(--grad-sunset)'],
        'welfare'     => ['icon' => 'handshake', 'grad' => 'var(--grad-ocean)'],
        'social'      => ['icon' => 'handshake', 'grad' => 'var(--grad-ocean)'],
        'general'     => ['icon' => 'clipboard-list', 'grad' => 'var(--grad-brand)'],
    ];
    foreach ($map as $needle => $meta) {
        if (str_contains($key, $needle)) {
            return $meta;
        }
    }
    $grads = ['var(--grad-brand)', 'var(--grad-purple)', 'var(--grad-orange)', 'var(--grad-green)', 'var(--grad-ocean)', 'var(--grad-sunset)'];
    return ['icon' => 'pin', 'grad' => $grads[$i % count($grads)]];
};

$slug = clean(get('slug', ''));

/* =============================================================================
 |  MODE 1 — SINGLE SCHEME DETAIL
 |============================================================================*/
if ($slug !== '') {

    $scheme = db_row(
        "SELECT * FROM schemes WHERE slug = :s LIMIT 1",
        [':s' => $slug]
    );

    /* ---- Not found: graceful, indexable-safe fallback ---- */
    if (!$scheme) {
        seo_set([
            'title'       => 'Scheme Not Found',
            'description' => 'The scheme you are looking for is no longer available. Browse all active welfare schemes and initiatives at EDUSKILL INDIA FOUNDATION.',
            'page_key'    => 'schemes',
            'robots'      => 'noindex,follow',
        ]);
        $page_hero = [
            'title'      => 'Scheme Not Found',
            'subtitle'   => 'This scheme may have closed or been moved. Explore the initiatives we are running right now.',
            'breadcrumb' => [
                ['label' => 'Schemes', 'url' => url('schemes')],
                ['label' => 'Not Found'],
            ],
        ];
        include __DIR__ . '/includes/header.php';
        ?>
        <section class="section">
            <div class="container">
                <div class="empty-state">
                    <div class="icon-badge"><?= lucide('search') ?></div>
                    <h3>We couldn't find that scheme</h3>
                    <p class="text-muted">The link may be outdated, or the scheme has closed. Please browse our current schemes and initiatives — there may be one that's right for you.</p>
                    <div class="flex flex-wrap justify-center gap-2 mt-3">
                        <a class="btn btn-primary" href="<?= e(url('schemes')) ?>">View All Schemes</a>
                        <a class="btn btn-outline" href="<?= e(url('contact')) ?>">Ask Our Team</a>
                    </div>
                </div>
            </div>
        </section>
        <?php
        include __DIR__ . '/includes/footer.php';
        return;
    }

    /* ---- Derived values ---- */
    $catName   = trim((string) ($scheme['category'] ?? '')) !== '' ? $scheme['category'] : 'General';
    $cm        = $catMeta($catName);
    $dl        = $deadlineInfo($scheme['deadline'] ?? null);
    $isClosed  = ($scheme['status'] ?? 'active') === 'closed' || !$dl['open'];
    $apply     = $applyLink($scheme);
    $applyExt  = $applyExternal($scheme);
    $eligItems = $toList($scheme['eligibility'] ?? '');
    $benItems  = $toList($scheme['benefits'] ?? '');
    $docItems  = $toList($scheme['documents_required'] ?? '');

    /* Extended project sections — parsed by includes/scheme.php so the admin
       form and this page agree on the stored format. Each is empty for a scheme
       that has not filled it in, and its block is skipped entirely. */
    $objectives   = scheme_list($scheme['objectives'] ?? '');
    $steps        = scheme_list($scheme['process_steps'] ?? '');
    $partners     = scheme_list($scheme['partnership'] ?? '');
    $transparency = scheme_list($scheme['transparency'] ?? '');
    $budgetRows   = scheme_budget_rows($scheme['support_items'] ?? '');
    $faqs         = scheme_faq($scheme['faq'] ?? '');
    $downloads    = scheme_brochures($scheme);

    /* Optional second CTA for donors / CSR partners. */
    $donateRaw = trim((string) ($scheme['donate_url'] ?? ''));
    $donateExt = (bool) preg_match('#^https?://#i', $donateRaw);
    $donate    = $donateRaw === '' ? '' : ($donateExt ? $donateRaw : url($donateRaw));

    /* ---- Related schemes (same category first, then any active) ---- */
    $related = db_all(
        "SELECT * FROM schemes
         WHERE status = 'active' AND id <> :id
         ORDER BY (category = :cat) DESC, is_featured DESC, sort_order ASC, id DESC
         LIMIT 3",
        [':id' => $scheme['id'], ':cat' => $catName]
    );

    seo_set([
        'title'       => $scheme['title'],
        'description' => $scheme['short_description'] ?: excerpt($scheme['description'] ?? '', 30),
        'image'       => !empty($scheme['image']) ? upload_url($scheme['image']) : null,
        'page_key'    => 'scheme:' . $scheme['slug'],
        'type'        => 'article',
    ]);

    /* The tagline leads when there is one — for a project page it carries the
       identity (often in Hindi) far better than the summary paragraph does. */
    $heroSub = trim((string) ($scheme['subtitle'] ?? ''));
    if ($heroSub !== '' && !empty($scheme['short_description'])) {
        $heroSub .= ' · ' . $scheme['short_description'];
    }
    $page_hero = [
        'title'      => $scheme['title'],
        'subtitle'   => $heroSub ?: ($scheme['short_description'] ?: 'Everything you need to know to check your eligibility and apply.'),
        'breadcrumb' => [
            ['label' => 'Schemes', 'url' => url('schemes')],
            ['label' => $scheme['title']],
        ],
    ];

    include __DIR__ . '/includes/header.php';
    ?>

<?php /* Styles for the extended project blocks (budget table, process,
         FAQ, guidelines, downloads). Only the detail view needs them. */ ?>
<link rel="stylesheet" href="<?= e(asset('css/scheme-detail.css')) ?>">

    <!-- ============================== SCHEME DETAIL ============================== -->
    <section class="section">
        <div class="container grid grid-sidebar gap-4 items-start">

            <!-- Main content -->
            <article class="reveal">
                <?php if (!empty($scheme['image'])): ?>
                    <figure class="card-media" style="border-radius:var(--radius,16px);overflow:hidden;margin:0 0 1.6rem;">
                        <img src="<?= e(upload_url($scheme['image'])) ?>" alt="<?= e($scheme['title']) ?>" width="900" height="500" style="width:100%;height:auto;display:block;object-fit:cover;">
                    </figure>
                <?php endif; ?>

                <div class="flex items-center gap-2 flex-wrap mb-2">
                    <span class="badge badge-brand"><?= lucide($cm['icon']) ?> <?= e($catName) ?></span>
                    <?php if (!empty($scheme['department'])): ?>
                        <span class="chip"><?= lucide('landmark') ?> <?= e($scheme['department']) ?></span>
                    <?php endif; ?>
                    <span class="badge <?= e($dl['class']) ?>"><?= e($dl['text']) ?></span>
                </div>

                <h2 class="section-title" style="margin-top:.25rem;"><?= e($scheme['title']) ?></h2>

                <div class="prose mt-3">
                    <?php if (!empty($scheme['description'])): ?>
                        <?= rich_text($scheme['description']) ?>
                    <?php elseif (!empty($scheme['short_description'])): ?>
                        <p><?= e($scheme['short_description']) ?></p>
                    <?php else: ?>
                        <p>This scheme is part of <?= e(SITE_NAME) ?>'s ongoing effort to bring welfare, opportunity and support to the communities we serve across Bihar. Review the eligibility and benefits below, then apply or reach out to our team for guidance.</p>
                    <?php endif; ?>
                </div>

                <!-- Eligibility -->
                <?php if ($eligItems || !empty($scheme['eligibility'])): ?>
                    <div class="card-3d mt-4">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="icon-badge" style="flex:0 0 auto;"><?= lucide('circle-check') ?></div>
                            <h3 class="card-title mb-0">Who Can Apply</h3>
                        </div>
                        <?php if (count($eligItems) > 1): ?>
                            <ul class="list-check mt-2">
                                <?php foreach ($eligItems as $it): ?><li><?= e($it) ?></li><?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="card-text mt-1"><?= rich_text($scheme['eligibility']) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Benefits -->
                <?php if ($benItems || !empty($scheme['benefits'])): ?>
                    <div class="card-3d mt-4">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="icon-badge accent" style="flex:0 0 auto;"><?= lucide('gift') ?></div>
                            <h3 class="card-title mb-0">Benefits You Receive</h3>
                        </div>
                        <?php if (count($benItems) > 1): ?>
                            <ul class="list-check mt-2">
                                <?php foreach ($benItems as $it): ?><li><?= e($it) ?></li><?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="card-text mt-1"><?= rich_text($scheme['benefits']) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Documents required -->
                <?php if ($docItems): ?>
                    <div class="card-3d mt-4">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="icon-badge" style="flex:0 0 auto;"><?= lucide('file-text') ?></div>
                            <h3 class="card-title mb-0">Documents Required</h3>
                        </div>
                        <ul class="list-check mt-2">
                            <?php foreach ($docItems as $it): ?><li><?= e($it) ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php /* ================= Extended project sections =================
                         Everything below is optional and driven entirely from the
                         admin form: a scheme that has not filled a field simply
                         does not render that block, so the simple schemes on this
                         site look exactly as they did before these were added. */ ?>

                <!-- Objectives -->
                <?php if ($objectives): ?>
                    <div class="card-3d mt-4">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="icon-badge" style="flex:0 0 auto;"><?= lucide('target') ?></div>
                            <h3 class="card-title mb-0">Project Objectives</h3>
                        </div>
                        <ul class="list-check mt-2">
                            <?php foreach ($objectives as $it): ?><li><?= e($it) ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Indicative support budget -->
                <?php if ($budgetRows): ?>
                    <div class="card-3d mt-4">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="icon-badge" style="flex:0 0 auto;"><?= lucide('indian-rupee') ?></div>
                            <h3 class="card-title mb-0">Indicative Support Package</h3>
                        </div>
                        <div class="table-scroll mt-2">
                            <table class="sch-budget">
                                <tbody>
                                    <?php foreach ($budgetRows as $b): ?>
                                        <tr<?= $b['total'] ? ' class="is-total"' : '' ?>>
                                            <th scope="row"><?= e($b['label']) ?></th>
                                            <td><?= e($b['amount']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (!empty($scheme['budget_note'])): ?>
                            <p class="text-muted mt-2" style="font-size:.9rem;white-space:pre-line;"><?= e($scheme['budget_note']) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Selection process -->
                <?php if ($steps): ?>
                    <div class="card-3d mt-4">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="icon-badge" style="flex:0 0 auto;"><?= lucide('list-ordered') ?></div>
                            <h3 class="card-title mb-0">How the Selection Works</h3>
                        </div>
                        <ol class="sch-steps mt-2">
                            <?php foreach ($steps as $i => $it): ?>
                                <li><span class="sch-step-no"><?= $i + 1 ?></span><span><?= e($it) ?></span></li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                <?php endif; ?>

                <!-- CSR / donor partnership + transparency -->
                <?php if ($partners || $transparency): ?>
                    <div class="grid grid-2 gap-3 mt-4">
                        <?php if ($partners): ?>
                            <div class="card-3d">
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="icon-badge" style="flex:0 0 auto;"><?= lucide('handshake') ?></div>
                                    <h3 class="card-title mb-0">CSR &amp; Donor Partnership</h3>
                                </div>
                                <ul class="list-check mt-2">
                                    <?php foreach ($partners as $it): ?><li><?= e($it) ?></li><?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        <?php if ($transparency): ?>
                            <div class="card-3d">
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="icon-badge" style="flex:0 0 auto;"><?= lucide('shield-check') ?></div>
                                    <h3 class="card-title mb-0">Transparency &amp; Accountability</h3>
                                </div>
                                <ul class="list-check mt-2">
                                    <?php foreach ($transparency as $it): ?><li><?= e($it) ?></li><?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- FAQ -->
                <?php if ($faqs): ?>
                    <div class="card-3d mt-4">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="icon-badge" style="flex:0 0 auto;"><?= lucide('circle-help') ?></div>
                            <h3 class="card-title mb-0">Frequently Asked Questions</h3>
                        </div>
                        <div class="sch-faq mt-2">
                            <?php foreach ($faqs as $i => $f): ?>
                                <details<?= $i === 0 ? ' open' : '' ?>>
                                    <summary><?= e($f['q']) ?></summary>
                                    <p><?= e($f['a']) ?></p>
                                </details>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Important guidelines / safeguards -->
                <?php if (!empty($scheme['guidelines'])): ?>
                    <div class="card-3d mt-4 sch-guidelines">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="icon-badge" style="flex:0 0 auto;"><?= lucide('info') ?></div>
                            <h3 class="card-title mb-0">Important Guidelines</h3>
                        </div>
                        <div class="mt-1"><?= rich_text($scheme['guidelines']) ?></div>
                    </div>
                <?php endif; ?>

                <div class="flex flex-wrap gap-2 mt-4">
                    <?php if ($isClosed): ?>
                        <a class="btn btn-outline btn-lg" href="<?= e(url('schemes')) ?>"><?= lucide('arrow-left') ?> Browse Open Schemes</a>
                        <a class="btn btn-primary btn-lg" href="<?= e(url('contact')) ?>">Ask About This Scheme</a>
                    <?php else: ?>
                        <a class="btn btn-3d btn-lg" href="<?= e($apply) ?>" <?= $applyExt ? 'target="_blank" rel="noopener"' : '' ?>><?= lucide('clipboard-pen') ?> Apply Now</a>
                        <?php if ($donate !== ''): ?>
                            <a class="btn btn-primary btn-lg" href="<?= e($donate) ?>" <?= $donateExt ? 'target="_blank" rel="noopener"' : '' ?>><?= lucide('heart') ?> Support This Project</a>
                        <?php endif; ?>
                        <a class="btn btn-outline btn-lg" href="<?= e(url('schemes')) ?>"><?= lucide('arrow-left') ?> All Schemes</a>
                    <?php endif; ?>
                </div>
            </article>

            <!-- Sidebar -->
            <aside class="reveal delay-1">
                <div class="card-3d sticky-aside" style="position:sticky;top:90px;">
                    <div class="icon-badge" style="background:<?= e($cm['grad']) ?>;font-size:1.6rem;"><?= lucide($cm['icon']) ?></div>
                    <h3 class="card-title mb-2">Scheme at a Glance</h3>

                    <ul class="list-check" style="font-size:.95rem;">
                        <li><strong>Category:</strong> <?= e($catName) ?></li>
                        <?php if (!empty($scheme['department'])): ?>
                            <li><strong>Department:</strong> <?= e($scheme['department']) ?></li>
                        <?php endif; ?>
                        <li><strong>Status:</strong> <?= $isClosed ? 'Closed' : 'Accepting applications' ?></li>
                        <li><strong>Deadline:</strong> <?= e($dl['text']) ?></li>
                    </ul>

                    <?php /* Brochure downloads. upload_url(), not
                             secure_upload_url(): these are published documents in
                             uploads/brochures, meant to be handed to anyone. */ ?>
                    <?php if (!empty($scheme['brochure']) || $downloads): ?>
                        <div class="divider"></div>
                        <p class="text-muted mb-1" style="font-size:.85rem;">Download</p>
                        <div class="sch-dl">
                            <?php if (!empty($scheme['brochure'])): ?>
                                <a class="sch-dl-item is-primary" href="<?= e(upload_url($scheme['brochure'])) ?>"
                                   target="_blank" rel="noopener" download>
                                    <?= lucide('file-down') ?>
                                    <span><strong>Project Brochure</strong>
                                        <small><?= e(strtoupper(pathinfo($scheme['brochure'], PATHINFO_EXTENSION))) ?></small></span>
                                </a>
                            <?php endif; ?>
                            <?php foreach ($downloads as $d): ?>
                                <a class="sch-dl-item" href="<?= e(upload_url($d['path'])) ?>" target="_blank" rel="noopener" download>
                                    <?= lucide('paperclip') ?>
                                    <span><strong><?= e($d['label']) ?></strong>
                                        <?php if ($d['size'] > 0): ?><small><?= e(human_filesize($d['size'])) ?></small><?php endif; ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php
                    /* Live countdown when a real future deadline exists. */
                    if (!$isClosed && !empty($scheme['deadline']) && $scheme['deadline'] !== '0000-00-00'
                        && strtotime($scheme['deadline'] . ' 23:59:59') > time()): ?>
                        <div class="divider"></div>
                        <p class="text-muted mb-1" style="font-size:.85rem;">Applications close in</p>
                        <div class="countdown" data-countdown="<?= e(date('Y-m-d', strtotime($scheme['deadline'])) . ' 23:59') ?>">
                            <div><span data-cd-d>0</span><small>Days</small></div>
                            <div><span data-cd-h>0</span><small>Hrs</small></div>
                            <div><span data-cd-m>0</span><small>Min</small></div>
                            <div><span data-cd-s>0</span><small>Sec</small></div>
                        </div>
                    <?php endif; ?>

                    <div class="divider"></div>

                    <?php if ($isClosed): ?>
                        <a class="btn btn-secondary btn-block btn-lg" href="<?= e(url('contact')) ?>">Enquire About This Scheme</a>
                        <p class="text-center text-muted mt-2 mb-0" style="font-size:.85rem;">This scheme is currently closed — contact us and we'll notify you when it reopens.</p>
                    <?php else: ?>
                        <a class="btn btn-3d btn-block btn-lg" href="<?= e($apply) ?>" <?= $applyExt ? 'target="_blank" rel="noopener"' : '' ?>><?= lucide('clipboard-pen') ?> Apply Now</a>
                        <p class="text-center text-muted mt-2 mb-0" style="font-size:.85rem;">
                            <?= $applyExt ? 'You will be taken to the official application portal.' : 'Need help applying? Our team will guide you at no cost.' ?>
                        </p>
                    <?php endif; ?>

                    <div class="divider"></div>
                    <p class="text-muted mb-1" style="font-size:.85rem;">Have a question about this scheme?</p>
                    <a class="btn btn-outline btn-block btn-sm" href="<?= e(url('contact')) ?>">Talk to Our Team</a>
                </div>
            </aside>
        </div>
    </section>

    <!-- ============================== HOW TO APPLY ============================== -->
    <section class="section section-soft">
        <div class="container">
            <div class="section-head">
                <span class="eyebrow">Simple &amp; Supported</span>
                <h2 class="section-title">How to Apply</h2>
                <p class="section-subtitle">We keep the process straightforward — and our team is here to help at every step.</p>
            </div>
            <div class="process-steps" style="max-width:720px;margin-inline:auto;">
                <div class="process-step reveal">
                    <h3 class="card-title mb-0">Check your eligibility</h3>
                    <p class="card-text">Review the "Who Can Apply" criteria above to confirm this scheme is right for you.</p>
                </div>
                <div class="process-step reveal delay-1">
                    <h3 class="card-title mb-0">Gather your documents</h3>
                    <p class="card-text">Keep the listed documents ready so your application can be processed without delay.</p>
                </div>
                <div class="process-step reveal delay-2">
                    <h3 class="card-title mb-0">Submit your application</h3>
                    <p class="card-text">Use the Apply button to start your application, or contact us for hands-on assistance.</p>
                </div>
                <div class="process-step reveal delay-3">
                    <h3 class="card-title mb-0">Track &amp; receive support</h3>
                    <p class="card-text">We help you follow up until the benefit reaches you — that's our commitment.</p>
                </div>
            </div>
        </div>
    </section>

    <?php if ($related): ?>
    <!-- ============================== RELATED SCHEMES ============================== -->
    <section class="section">
        <div class="container">
            <div class="section-head">
                <span class="eyebrow">Explore More</span>
                <h2 class="section-title">Related Schemes</h2>
                <p class="section-subtitle">Other initiatives you or your family may be eligible for.</p>
            </div>
            <div class="grid grid-3">
                <?php foreach ($related as $i => $rs):
                    $rcm = $catMeta(trim((string) ($rs['category'] ?? '')) !== '' ? $rs['category'] : 'General', $i);
                    $rdl = $deadlineInfo($rs['deadline'] ?? null);
                ?>
                    <article class="card reveal <?= 'delay-' . (($i % 3) + 1) ?>">
                        <a href="<?= e(url('schemes?slug=' . $rs['slug'])) ?>" class="card-media" style="height:120px;background:<?= e($rcm['grad']) ?>;display:grid;place-items:center;">
                            <span style="font-size:2.6rem;filter:drop-shadow(0 4px 8px rgba(0,0,0,.2));"><?= lucide($rcm['icon']) ?></span>
                        </a>
                        <div class="card-body flex flex-col">
                            <span class="badge <?= e($rdl['class']) ?> mb-1"><?= e($rdl['text']) ?></span>
                            <h3 class="card-title"><a href="<?= e(url('schemes?slug=' . $rs['slug'])) ?>" style="color:inherit;"><?= e($rs['title']) ?></a></h3>
                            <p class="card-text"><?= e(excerpt($rs['short_description'] ?: ($rs['description'] ?? ''), 18)) ?></p>
                            <a class="btn btn-outline btn-sm mt-auto" href="<?= e(url('schemes?slug=' . $rs['slug'])) ?>">View Details</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ============================== CTA BAND ============================== -->
    <section class="section section-alt">
        <div class="container">
            <div class="card-3d reveal text-center" style="background:var(--grad-brand);color:#fff;">
                <span class="eyebrow" style="justify-content:center;color:#dbeafe;"><?= e(SITE_TAGLINE) ?></span>
                <h2 class="text-white">Not Sure Which Scheme Fits You?</h2>
                <p style="color:rgba(255,255,255,.9);max-width:640px;margin-inline:auto;">Our team helps families across Bihar identify the right welfare schemes and apply — completely free. Reach out and we'll guide you.</p>
                <div class="flex flex-wrap justify-center gap-2 mt-4">
                    <a class="btn btn-white btn-lg" href="<?= e(url('contact')) ?>"><?= lucide('message-square') ?> Get Free Guidance</a>
                    <a class="btn btn-outline btn-lg" style="border-color:#fff;color:#fff;" href="<?= e(url('schemes')) ?>">Browse All Schemes</a>
                </div>
            </div>
        </div>
    </section>

    <?php
    include __DIR__ . '/includes/footer.php';
    return;
}

/* =============================================================================
 |  MODE 2 — SCHEMES LISTING
 |============================================================================*/

$schemes = db_all(
    "SELECT * FROM schemes
     WHERE status = 'active'
     ORDER BY is_featured DESC, sort_order ASC, (deadline IS NULL), deadline ASC, id DESC"
);

/* Graceful fallback: curated default schemes so the page is complete pre-seed. */
$isFallback = false;
if (!$schemes) {
    $isFallback = true;
    $schemes = [
        [
            'title' => 'Girl Child Education Support', 'slug' => '', 'category' => 'Education',
            'department' => 'Education & Learning', 'apply_url' => '', 'deadline' => null,
            'short_description' => 'Scholarships, learning kits and mentorship to keep girls in school through secondary and higher education.',
            'eligibility' => 'Girl students from low-income families in Bihar currently enrolled in a recognised school or college.',
        ],
        [
            'title' => 'Free Health Camp & Care Access', 'slug' => '', 'category' => 'Healthcare',
            'department' => 'Health & Wellbeing', 'apply_url' => '', 'deadline' => null,
            'short_description' => 'Free check-ups, essential medicines and referrals through our rural and urban medical camps.',
            'eligibility' => 'Open to all residents of the communities we serve; priority to elderly, women and children.',
        ],
        [
            'title' => 'Women Skill & Livelihood Grant', 'slug' => '', 'category' => 'Women Empowerment',
            'department' => 'Livelihoods', 'apply_url' => '', 'deadline' => null,
            'short_description' => 'Vocational training, tools and seed support to help women start and grow their own livelihoods.',
            'eligibility' => 'Women aged 18–45 from economically weaker households, willing to complete a skill programme.',
        ],
        [
            'title' => 'Youth Skill Development Programme', 'slug' => '', 'category' => 'Skill Development',
            'department' => 'Skill & Employment', 'apply_url' => '', 'deadline' => null,
            'short_description' => 'Industry-aligned vocational training and placement support that prepares youth for sustainable jobs.',
            'eligibility' => 'Unemployed youth aged 18–30 who have completed at least Class 8.',
        ],
        [
            'title' => 'Senior Citizen Welfare Support', 'slug' => '', 'category' => 'Social Welfare',
            'department' => 'Social Welfare', 'apply_url' => '', 'deadline' => null,
            'short_description' => 'Health, companionship and essential-needs assistance for elderly members of the community.',
            'eligibility' => 'Residents aged 60 and above with limited family or financial support.',
        ],
        [
            'title' => 'Emergency Relief & Rehabilitation', 'slug' => '', 'category' => 'Relief',
            'department' => 'Disaster Relief', 'apply_url' => '', 'deadline' => null,
            'short_description' => 'Rapid food, shelter and rehabilitation support for families affected by disaster or crisis.',
            'eligibility' => 'Families and individuals affected by floods, fire or other emergencies in our operating areas.',
        ],
    ];
}

/* Group active schemes by category (for the tab filter). */
$byCat = [];
foreach ($schemes as $s) {
    $cat = trim((string) ($s['category'] ?? ''));
    $cat = $cat !== '' ? $cat : 'General';
    $byCat[$cat][] = $s;
}
$categories = array_keys($byCat);
$catCount   = count($categories);

/* Distinct departments (for the marquee). */
$departments = [];
foreach ($schemes as $s) {
    $d = trim((string) ($s['department'] ?? ''));
    if ($d !== '') {
        $departments[$d] = true;
    }
}
$departments = array_keys($departments);

/* Headline stat tiles — real figures with sensible pre-seed fallbacks. */
$totalSchemes = count($schemes);
$closedCount  = $isFallback ? 0 : (int) db_value("SELECT COUNT(*) FROM schemes WHERE status = 'closed'");
$stats = [
    ['icon' => 'clipboard-list', 'value' => $totalSchemes, 'suffix' => $isFallback ? '+' : '', 'label' => 'Active Schemes'],
    ['icon' => 'folders', 'value' => $catCount,     'suffix' => '',                     'label' => 'Categories'],
    ['icon' => 'landmark', 'value' => max(1, count($departments)), 'suffix' => '', 'label' => 'Departments'],
    ['icon' => 'gift', 'value' => 100, 'suffix' => '%', 'label' => 'Free Guidance'],
];

/**
 * Render a single scheme card (shared between the "All" tab and category tabs).
 */
$renderCard = static function (array $s, int $i) use ($catMeta, $deadlineInfo, $applyLink, $applyExternal, $isFallback): void {
    $catName = trim((string) ($s['category'] ?? '')) !== '' ? $s['category'] : 'General';
    $cm      = $catMeta($catName, $i);
    $dl      = $deadlineInfo($s['deadline'] ?? null);
    $hasSlug = trim((string) ($s['slug'] ?? '')) !== '';
    $detail  = $hasSlug ? url('schemes?slug=' . $s['slug']) : null;
    $apply   = $applyLink($s);
    $applyExt = $applyExternal($s);
    $elig    = trim((string) ($s['eligibility'] ?? ''));
    ?>
    <article class="card reveal <?= 'delay-' . (($i % 3) + 1) ?>">
        <?php if (!empty($s['image'])): ?>
            <?php if ($detail): ?><a href="<?= e($detail) ?>" class="card-media"><?php else: ?><div class="card-media"><?php endif; ?>
                <img src="<?= e(upload_url($s['image'])) ?>" alt="<?= e($s['title']) ?>" loading="lazy" width="600" height="340">
                <span class="badge <?= e($dl['class']) ?>" style="position:absolute;top:12px;left:12px;"><?= e($dl['text']) ?></span>
            <?php if ($detail): ?></a><?php else: ?></div><?php endif; ?>
        <?php else: ?>
            <?php if ($detail): ?><a href="<?= e($detail) ?>" class="card-media" style="height:140px;background:<?= e($cm['grad']) ?>;display:grid;place-items:center;"><?php else: ?><div class="card-media" style="height:140px;background:<?= e($cm['grad']) ?>;display:grid;place-items:center;"><?php endif; ?>
                <span style="font-size:3rem;filter:drop-shadow(0 4px 10px rgba(0,0,0,.22));"><?= lucide($cm['icon']) ?></span>
                <span class="badge <?= e($dl['class']) ?>" style="position:absolute;top:12px;left:12px;"><?= e($dl['text']) ?></span>
            <?php if ($detail): ?></a><?php else: ?></div><?php endif; ?>
        <?php endif; ?>

        <div class="card-body flex flex-col">
            <div class="flex items-center gap-2 flex-wrap mb-1">
                <span class="badge badge-brand"><?= lucide($cm['icon']) ?> <?= e($catName) ?></span>
                <?php if (!empty($s['department'])): ?>
                    <span class="chip" style="font-size:.72rem;"><?= lucide('landmark') ?> <?= e($s['department']) ?></span>
                <?php endif; ?>
            </div>

            <h3 class="card-title mb-1">
                <?php if ($detail): ?>
                    <a href="<?= e($detail) ?>" style="color:inherit;"><?= e($s['title']) ?></a>
                <?php else: ?>
                    <?= e($s['title']) ?>
                <?php endif; ?>
            </h3>

            <p class="card-text"><?= e(excerpt($s['short_description'] ?: ($s['description'] ?? ''), 20)) ?></p>

            <?php if ($elig !== ''): ?>
                <p class="text-muted" style="font-size:.85rem;margin:.25rem 0 0;">
                    <strong>Eligibility:</strong> <?= e(excerpt($elig, 16)) ?>
                </p>
            <?php endif; ?>

            <div class="flex gap-2 mt-auto" style="padding-top:1rem;">
                <a class="btn btn-primary btn-sm" href="<?= e($apply) ?>" <?= $applyExt ? 'target="_blank" rel="noopener"' : '' ?>>
                    <?= lucide('clipboard-pen') ?> <?= $isFallback ? 'Enquire' : 'Apply' ?>
                </a>
                <?php if ($detail): ?>
                    <a class="btn btn-outline btn-sm" href="<?= e($detail) ?>">Details</a>
                <?php endif; ?>
            </div>
        </div>
    </article>
    <?php
};

seo_set([
    'title'       => 'Schemes & Initiatives',
    'description' => 'Explore welfare schemes and initiatives from EDUSKILL INDIA FOUNDATION — education, healthcare, skill development, women empowerment and social welfare support across Bihar. Check eligibility and apply free.',
    'page_key'    => 'schemes',
]);

$page_hero = [
    'title'      => 'Schemes & Initiatives',
    'subtitle'   => 'Welfare schemes that bring education, health, livelihood and social support within reach — check your eligibility and apply, with free guidance every step of the way.',
    'breadcrumb' => [['label' => 'Schemes & Initiatives']],
];

include __DIR__ . '/includes/header.php';
?>

<!-- ============================== STAT HIGHLIGHTS ============================== -->
<section class="section section-brand section-sm">
    <div class="container">
        <div class="counter-grid">
            <?php foreach ($stats as $st): ?>
                <div class="counter-item reveal">
                    <div class="icon-badge" style="background:rgba(255,255,255,.18);"><?= lucide($st['icon']) ?></div>
                    <div class="counter-value"><span data-counter="<?= (int) $st['value'] ?>">0</span><?= e($st['suffix']) ?></div>
                    <div class="counter-label"><?= e($st['label']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== INTRO / WHY ============================== -->
<section class="section">
    <div class="container grid grid-2 items-center gap-4">
        <div class="reveal">
            <span class="eyebrow">Support Within Reach</span>
            <h2 class="section-title"><?= e(get_setting('schemes_intro_title', 'Welfare that reaches the people who need it most')) ?></h2>
            <p class="text-muted"><?= e(get_setting('schemes_intro_text', SITE_NAME . ' runs and facilitates welfare schemes across education, health, livelihoods and social support. We help families in Bihar understand which schemes they qualify for, gather the right documents, and apply — all at no cost.')) ?></p>
            <ul class="list-check mt-3">
                <li>Clear eligibility and benefits for every scheme</li>
                <li>Free, hands-on help to prepare and submit your application</li>
                <li>We follow up until the benefit actually reaches you</li>
            </ul>
            <div class="flex flex-wrap gap-2 mt-4">
                <a class="btn btn-primary" href="#scheme-list">Browse Schemes</a>
                <a class="btn btn-outline" href="<?= e(url('contact')) ?>">Get Free Guidance</a>
            </div>
        </div>
        <div class="grid gap-3 reveal delay-1">
            <div class="icon-box g glass-card">
                <div class="ib-icon"><?= lucide('handshake') ?></div>
                <h3 class="card-title">Guided, Not Gatekept</h3>
                <p class="card-text">We walk beside you through every form and document — no jargon, no runaround, no fees.</p>
            </div>
            <div class="icon-box glass-card">
                <div class="ib-icon"><?= lucide('search') ?></div>
                <h3 class="card-title">Transparent &amp; Fair</h3>
                <p class="card-text">Every scheme lists exactly who can apply and what you receive, so there are no surprises.</p>
            </div>
        </div>
    </div>
</section>

<?php if ($departments): ?>
<!-- ============================== DEPARTMENTS MARQUEE ============================== -->
<section class="section section-soft section-sm">
    <div class="container">
        <p class="text-center text-muted mb-2" style="font-weight:600;">Schemes across our focus departments</p>
        <div class="marquee">
            <div class="marquee-track">
                <?php foreach ($departments as $d): ?>
                    <span class="chip"><?= lucide('landmark') ?> <?= e($d) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================== SCHEME LISTING (TAB FILTER) ============================== -->
<section class="section" id="scheme-list">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Find Your Scheme</span>
            <h2 class="section-title">Schemes &amp; Initiatives</h2>
            <p class="section-subtitle">Filter by category to quickly find the support that fits you or your family.</p>
        </div>

        <?php if ($isFallback): ?>
            <div class="alert alert-info reveal">
                We're finalising our current list of schemes. The initiatives below reflect the kinds of support we provide — <a href="<?= e(url('contact')) ?>">reach out</a> and our team will help you find and apply for the right one.
            </div>
        <?php endif; ?>

        <div class="tabs">
            <div class="tab-nav">
                <button class="tab-btn is-active" data-tab="tab-all">All <span class="text-muted">(<?= (int) $totalSchemes ?>)</span></button>
                <?php foreach ($categories as $ci => $cat): $cm = $catMeta($cat, $ci); ?>
                    <button class="tab-btn" data-tab="tab-<?= e(slugify($cat)) ?>"><?= lucide($cm['icon']) ?> <?= e($cat) ?> <span class="text-muted">(<?= count($byCat[$cat]) ?>)</span></button>
                <?php endforeach; ?>
            </div>

            <!-- All schemes -->
            <div class="tab-panel is-active" id="tab-all">
                <div class="grid grid-3">
                    <?php foreach ($schemes as $i => $s) { $renderCard($s, $i); } ?>
                </div>
            </div>

            <!-- Per-category panels -->
            <?php foreach ($categories as $cat): ?>
                <div class="tab-panel" id="tab-<?= e(slugify($cat)) ?>">
                    <div class="grid grid-3">
                        <?php foreach ($byCat[$cat] as $i => $s) { $renderCard($s, $i); } ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== HOW IT WORKS ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">From Enquiry to Benefit</span>
            <h2 class="section-title">How We Help You Apply</h2>
            <p class="section-subtitle">A simple, supported journey — we stay with you until the benefit reaches your hands.</p>
        </div>
        <div class="grid grid-2 gap-4 items-start">
            <div class="process-steps reveal">
                <div class="process-step">
                    <h3 class="card-title mb-0">Tell us about you</h3>
                    <p class="card-text">Share your situation and we'll match you to the schemes you're eligible for.</p>
                </div>
                <div class="process-step">
                    <h3 class="card-title mb-0">Prepare together</h3>
                    <p class="card-text">We help you collect the required documents and fill in every form correctly.</p>
                </div>
                <div class="process-step">
                    <h3 class="card-title mb-0">Apply with confidence</h3>
                    <p class="card-text">Submit through the official channel — with our team double-checking your application.</p>
                </div>
                <div class="process-step">
                    <h3 class="card-title mb-0">Follow up to the finish</h3>
                    <p class="card-text">We track your application and support you until the benefit is delivered.</p>
                </div>
            </div>
            <div class="reveal delay-1">
                <div class="section-head left mb-3">
                    <span class="eyebrow">Good to Know</span>
                    <h3 class="section-title mb-0" style="font-size:1.4rem;">Frequently Asked Questions</h3>
                </div>
                <details class="accordion-item" open>
                    <summary>Does it cost anything to get help applying?</summary>
                    <div class="acc-body">No. Our guidance is completely free. We never charge families for helping them access welfare schemes.</div>
                </details>
                <details class="accordion-item">
                    <summary>Who is eligible for these schemes?</summary>
                    <div class="acc-body">Eligibility varies by scheme. Each scheme page lists exactly who can apply — and if you're unsure, our team will assess it with you.</div>
                </details>
                <details class="accordion-item">
                    <summary>What documents will I need?</summary>
                    <div class="acc-body">Commonly an identity proof, address proof, income certificate and a passport photo. Every scheme lists its own document checklist.</div>
                </details>
                <details class="accordion-item">
                    <summary>How long does approval take?</summary>
                    <div class="acc-body">Timelines depend on the scheme and department. We keep you updated and follow up on your behalf throughout the process.</div>
                </details>
            </div>
        </div>
    </div>
</section>

<!-- ============================== CTA BAND ============================== -->
<section class="section section-alt">
    <div class="container">
        <div class="card-3d reveal text-center" style="background:var(--grad-brand);color:#fff;">
            <span class="eyebrow" style="justify-content:center;color:#dbeafe;"><?= e(SITE_TAGLINE) ?></span>
            <h2 class="text-white">Let's Find the Right Scheme for You</h2>
            <p style="color:rgba(255,255,255,.9);max-width:640px;margin-inline:auto;">Whether it's education, healthcare, a livelihood grant or emergency relief — tell us what you need and we'll help you access it. Free, friendly and confidential.</p>
            <div class="flex flex-wrap justify-center gap-2 mt-4">
                <a class="btn btn-white btn-lg" href="<?= e(url('contact')) ?>"><?= lucide('message-square') ?> Get Free Guidance</a>
                <a class="btn btn-outline btn-lg" style="border-color:#fff;color:#fff;" href="<?= e(url('volunteer')) ?>"><?= lucide('handshake') ?> Become a Volunteer</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
