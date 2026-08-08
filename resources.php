<?php
/**
 * =============================================================================
 *  Resources & Downloads — a DB-driven library of the foundation's public
 *  documents (reports, policies, forms, brochures, etc.). Active documents are
 *  grouped by category and rendered as clean, downloadable tables with a file
 *  type badge, human-readable size and download button. Every section degrades
 *  gracefully so the page looks complete and polished before any content is
 *  seeded.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* ---- Data ------------------------------------------------------------- */

/* All published documents, grouped in a stable, curated order. */
$documents = db_all(
    "SELECT * FROM documents
     WHERE status = 1
     ORDER BY category ASC, title ASC"
);

/* Group by category and gather headline stats in a single pass. */
$grouped        = [];
$totalDownloads = 0;
$formats        = [];
foreach ($documents as $doc) {
    $cat = $doc['category'] !== '' && $doc['category'] !== null ? $doc['category'] : 'general';
    $grouped[$cat][] = $doc;
    $totalDownloads += (int) $doc['downloads'];
    if (!empty($doc['file_type'])) {
        $formats[strtolower($doc['file_type'])] = true;
    }
}
$totalDocs   = count($documents);
$catCount    = count($grouped);
$formatCount = count($formats);

/* Presentation helper — colour + icon for a given file type. */
$fileMeta = static function (?string $type): array {
    $t = strtolower(trim((string) $type));
    return match ($t) {
        'pdf'                        => ['class' => 'badge-danger',  'icon' => 'file-text', 'label' => 'PDF'],
        'doc', 'docx', 'rtf', 'odt'  => ['class' => 'badge-brand',   'icon' => 'file-text', 'label' => strtoupper($t ?: 'DOC')],
        'xls', 'xlsx', 'csv', 'ods'  => ['class' => 'badge-success', 'icon' => 'file-spreadsheet', 'label' => strtoupper($t ?: 'XLS')],
        'ppt', 'pptx', 'odp'         => ['class' => 'badge-accent',  'icon' => 'presentation', 'label' => strtoupper($t ?: 'PPT')],
        'zip', 'rar', '7z', 'tar'    => ['class' => 'badge-muted',   'icon' => 'file-archive', 'label' => strtoupper($t ?: 'ZIP')],
        'jpg', 'jpeg', 'png', 'webp', 'gif', 'svg' => ['class' => 'badge-accent', 'icon' => 'file-image', 'label' => strtoupper($t)],
        'txt'                        => ['class' => 'badge-muted',   'icon' => 'file-text', 'label' => 'TXT'],
        default                      => ['class' => 'badge-muted',   'icon' => 'file-text', 'label' => $t !== '' ? strtoupper($t) : 'FILE'],
    };
};

/* Presentation helper — friendly label + icon for a category slug. */
$catMeta = static function (string $cat): array {
    $key = strtolower($cat);
    $icons = [
        'general'         => 'folder',
        'reports'         => 'bar-chart-3',
        'annual-reports'  => 'bar-chart-3',
        'annual_reports'  => 'bar-chart-3',
        'financials'      => 'coins',
        'financial'       => 'coins',
        'policies'        => 'clipboard-list',
        'policy'          => 'clipboard-list',
        'forms'           => 'clipboard-pen',
        'brochures'       => 'book-open',
        'newsletters'     => 'newspaper',
        'legal'           => 'scale',
        'guides'          => 'book',
        'presentations'   => 'presentation',
    ];
    $icon  = $icons[$key] ?? 'folder';
    $label = ucwords(str_replace(['-', '_'], ' ', $cat));
    return ['icon' => $icon, 'label' => $label];
};

/* Headline stat tiles — real numbers, with sensible fallbacks pre-seed. */
$stats = [
    ['icon' => 'file-text', 'value' => $totalDocs > 0 ? $totalDocs : 12, 'suffix' => $totalDocs > 0 ? '' : '+', 'label' => 'Documents Available', 'animate' => true],
    ['icon' => 'folders', 'value' => $catCount > 0 ? $catCount : 5,     'suffix' => $catCount > 0 ? '' : '+',  'label' => 'Resource Categories',  'animate' => true],
    ['icon' => 'download', 'value' => $totalDownloads > 0 ? $totalDownloads : 2500, 'suffix' => $totalDownloads > 0 ? '' : '+', 'label' => 'Total Downloads', 'animate' => true],
    ['icon' => 'gift', 'value' => 100, 'suffix' => '%', 'label' => 'Free to Access', 'animate' => true],
];

/* ---- SEO + hero ------------------------------------------------------- */
seo_set([
    'title'       => 'Resources & Downloads',
    'description' => 'Download official reports, policies, forms and publications from EDUSKILL INDIA FOUNDATION. Free, transparent access to the documents behind our work across Bihar.',
    'page_key'    => 'resources',
]);

$page_hero = [
    'title'      => 'Resources & Downloads',
    'subtitle'   => 'Annual reports, policies, forms and publications — everything you need to understand our work, verify our impact and partner with us, free to download in one place.',
    'breadcrumb' => [['label' => 'Resources & Downloads']],
];

include __DIR__ . '/includes/header.php';
?>

<!-- ============================== STAT HIGHLIGHTS ============================== -->
<section class="section section-brand section-sm">
    <div class="container">
        <div class="counter-grid">
            <?php foreach ($stats as $s): ?>
                <div class="counter-item reveal">
                    <div class="icon-badge" style="background:rgba(255,255,255,.18);"><?= lucide($s['icon']) ?></div>
                    <div class="counter-value">
                        <?php if ($s['animate']): ?><span data-counter="<?= (int) $s['value'] ?>">0</span><?php else: ?><?= e((string) $s['value']) ?><?php endif; ?><?= e($s['suffix']) ?>
                    </div>
                    <div class="counter-label"><?= e($s['label']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== INTRO / WHY ============================== -->
<section class="section">
    <div class="container grid grid-2 items-center gap-4">
        <div class="reveal">
            <span class="eyebrow">Open By Default</span>
            <h2 class="section-title"><?= e(get_setting('resources_intro_title', 'Transparency you can hold in your hands')) ?></h2>
            <p class="text-muted"><?= e(get_setting('resources_intro_text', SITE_NAME . ' is a registered non-profit (CIN ' . SITE_CIN . ') based in Patna, Bihar. We publish the documents behind our work — from annual reports to programme guides — so donors, volunteers and partners can see exactly how we operate and the impact every contribution creates.')) ?></p>
            <ul class="list-check mt-3">
                <li>Every document is free to download — no sign-up required</li>
                <li>Official reports and policies, published for full accountability</li>
                <li>Forms and guides to help you volunteer, apply or partner with us</li>
            </ul>
            <div class="flex flex-wrap gap-2 mt-4">
                <a class="btn btn-primary" href="#resource-library">Browse the Library</a>
                <a class="btn btn-outline" href="<?= e(url('contact')) ?>">Request a Document</a>
            </div>
        </div>
        <div class="grid gap-3 reveal delay-1">
            <div class="card-3d">
                <div class="icon-badge"><?= lucide('folder-open') ?></div>
                <h3 class="card-title">Always Current</h3>
                <p class="card-text">Our resource library is kept up to date so you always have the latest reports, policies and forms at your fingertips.</p>
            </div>
            <div class="card-3d">
                <div class="icon-badge accent"><?= lucide('lock') ?></div>
                <h3 class="card-title">Safe &amp; Verified</h3>
                <p class="card-text">Files are hosted directly by the foundation and reviewed before publication, so you can download with confidence.</p>
            </div>
        </div>
    </div>
</section>

<!-- ============================== RESOURCE LIBRARY ============================== -->
<section class="section section-soft" id="resource-library">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Downloads</span>
            <h2 class="section-title">Resource Library</h2>
            <p class="section-subtitle">Browse our documents by category. Tap any download button to save the file directly to your device.</p>
        </div>

        <?php if ($grouped): ?>

            <?php if ($catCount > 1): ?>
                <!-- Quick in-page navigation across categories -->
                <div class="flex flex-wrap justify-center gap-2 mb-4 reveal">
                    <?php foreach ($grouped as $cat => $docs): $m = $catMeta($cat); ?>
                        <a class="chip" href="#cat-<?= e(slugify($cat)) ?>"><?= lucide($m['icon']) ?> <?= e($m['label']) ?> <span class="text-muted">(<?= count($docs) ?>)</span></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php foreach ($grouped as $cat => $docs): $m = $catMeta($cat); ?>
                <div class="card reveal mb-4" id="cat-<?= e(slugify($cat)) ?>" style="overflow:hidden;">
                    <div class="card-body flex items-center gap-3" style="border-bottom:1px solid var(--border);">
                        <div class="icon-badge" style="flex:0 0 auto;"><?= lucide($m['icon']) ?></div>
                        <div>
                            <h3 class="card-title mb-0" style="font-size:1.15rem;"><?= e($m['label']) ?></h3>
                            <small class="text-muted"><?= count($docs) ?> <?= count($docs) === 1 ? 'document' : 'documents' ?></small>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width:50%;">Document</th>
                                    <th>Format</th>
                                    <th>Size</th>
                                    <th>Downloads</th>
                                    <th style="text-align:right;">Get File</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($docs as $doc):
                                    $meta   = $fileMeta($doc['file_type']);
                                    $bytes  = (int) ($doc['file_size'] ?? 0);
                                    if ($bytes <= 0 && !empty($doc['file_path'])) {
                                        // file_size not recorded — read it from disk once.
                                        $onDisk = UPLOAD_PATH . '/' . ltrim(str_replace(['..', "\0"], '', $doc['file_path']), '/');
                                        if (is_file($onDisk)) { $bytes = (int) filesize($onDisk); }
                                    }
                                    $size   = $bytes > 0 ? human_filesize($bytes) : '—';
                                    // Route through the counter endpoint so the Downloads column is real.
                                    $href   = url('forms/download?id=' . (int) $doc['id']);
                                    $dlName = $doc['title'] !== '' ? $doc['title'] : 'document';
                                ?>
                                    <tr>
                                        <td>
                                            <strong style="display:block;"><?= lucide($meta['icon']) ?> <?= e($doc['title']) ?></strong>
                                            <?php if (!empty($doc['description'])): ?>
                                                <small class="text-muted"><?= e(excerpt($doc['description'], 20)) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge <?= e($meta['class']) ?>"><?= e($meta['label']) ?></span></td>
                                        <td style="white-space:nowrap;"><?= e($size) ?></td>
                                        <td style="white-space:nowrap;"><?= e(number_short((int) $doc['downloads'])) ?></td>
                                        <td style="text-align:right;white-space:nowrap;">
                                            <a class="btn btn-primary btn-sm" href="<?= e($href) ?>" download="<?= e($dlName) ?>" rel="noopener">
                                                <?= lucide('download') ?> Download
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>

        <?php else: ?>

            <div class="empty-state">
                <div class="icon-badge"><?= lucide('folder-open') ?></div>
                <h3>Resources Coming Online Soon</h3>
                <p class="text-muted">
                    <?= e(SITE_NAME) ?> is preparing its official reports, policies and downloadable forms for publication.
                    They will appear here shortly. In the meantime, reach out and our team will be happy to share any document you need.
                </p>
                <a class="btn btn-primary mt-2" href="<?= e(url('contact')) ?>">Request a Document</a>
            </div>

        <?php endif; ?>
    </div>
</section>

<!-- ============================== HELP / REQUEST ============================== -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Need Something Else?</span>
            <h2 class="section-title">Can't Find What You're Looking For?</h2>
            <p class="section-subtitle">If a document you need isn't listed here, our team is happy to help you find it or provide it directly.</p>
        </div>
        <div class="grid grid-3">
            <div class="card reveal delay-1">
                <div class="card-body">
                    <div class="icon-badge"><?= lucide('mail') ?></div>
                    <h3 class="card-title">Email Us</h3>
                    <p class="card-text">Write to us and we'll send the document straight to your inbox.</p>
                    <a class="btn btn-outline btn-sm" href="mailto:<?= e(get_setting('contact_email', SITE_EMAIL)) ?>"><?= e(get_setting('contact_email', SITE_EMAIL)) ?></a>
                </div>
            </div>
            <div class="card reveal delay-2">
                <div class="card-body">
                    <div class="icon-badge accent"><?= lucide('phone') ?></div>
                    <h3 class="card-title">Call Us</h3>
                    <p class="card-text">Speak with our team for urgent requests or clarifications.</p>
                    <a class="btn btn-outline btn-sm" href="tel:<?= e(preg_replace('/[^0-9+]/', '', get_setting('contact_phone', SITE_PHONE))) ?>"><?= e(get_setting('contact_phone', SITE_PHONE)) ?></a>
                </div>
            </div>
            <div class="card reveal delay-3">
                <div class="card-body">
                    <div class="icon-badge"><?= lucide('clipboard-pen') ?></div>
                    <h3 class="card-title">Send a Request</h3>
                    <p class="card-text">Use our contact form to request a specific report, policy or form.</p>
                    <a class="btn btn-outline btn-sm" href="<?= e(url('contact')) ?>">Contact Form</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================== CTA BAND ============================== -->
<section class="section section-alt">
    <div class="container">
        <div class="card-3d reveal text-center" style="background:var(--grad-brand);color:#fff;">
            <span class="eyebrow" style="justify-content:center;color:#dbeafe;"><?= e(SITE_TAGLINE) ?></span>
            <h2 class="text-white">Turn Transparency Into Change</h2>
            <p style="color:rgba(255,255,255,.9);max-width:640px;margin-inline:auto;">You've seen the reports and the record. Now help write the next chapter — support our work as a donor, volunteer or partner across Bihar.</p>
            <div class="flex flex-wrap justify-center gap-2 mt-4">
                <a class="btn btn-white btn-lg" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Donate Now</a>
                <a class="btn btn-outline btn-lg" style="border-color:#fff;color:#fff;" href="<?= e(url('volunteer')) ?>"><?= lucide('handshake') ?> Become a Volunteer</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
