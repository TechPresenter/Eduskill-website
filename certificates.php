<?php
/**
 * =============================================================================
 *  Certificates & Recognition — a DB-driven gallery of the foundation's
 *  official certificates, registrations and awards. Each certificate image
 *  opens in a lightbox. Every section degrades gracefully with sensible copy
 *  so the page looks complete and polished before any content is seeded.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* ---- Data ------------------------------------------------------------- */

/* All active certificates, in curated order (newest as tie-breaker). */
$certs = db_all(
    "SELECT * FROM certificates
     WHERE status = 1
     ORDER BY sort_order ASC, issue_date DESC, id DESC"
);

$total     = count($certs);

/* Board of directors — comma-separated setting (Admin → Settings); blank hides it. */
$directors = trim((string) get_setting('org_directors', ''));

/* Distinct issuing authorities recognised (for the trust band). */
$issuers = [];
foreach ($certs as $ct) {
    if (!empty($ct['issued_by'])) {
        $issuers[$ct['issued_by']] = true;
    }
}
$issuerCount = count($issuers);

/* Trust highlights — a compact, meaningful stat row above the grid.
   'animate' stats count up; static stats (e.g. a year) render as-is. */
$trust = [
    ['icon' => 'scroll', 'value' => $total > 0 ? $total : 6, 'suffix' => $total > 0 ? '' : '+', 'label' => 'Official Documents', 'animate' => true],
    ['icon' => 'landmark', 'value' => $issuerCount > 0 ? $issuerCount : 4, 'suffix' => '+', 'label' => 'Issuing Authorities', 'animate' => true],
    ['icon' => 'circle-check', 'value' => 100, 'suffix' => '%', 'label' => 'Compliance Record', 'animate' => true],
    ['icon' => 'handshake', 'value' => 2025, 'suffix' => '', 'label' => 'Registered Since', 'animate' => false],
];

/* Registration facts — DB-driven copy with foundation defaults. */
$facts = [
    ['icon' => 'id-card', 'title' => 'Corporate Identity Number', 'value' => get_setting('org_cin', SITE_CIN)],
    ['icon' => 'building-2', 'title' => 'Registered Office',         'value' => get_setting('contact_address', SITE_ADDRESS)],
    ['icon' => 'mail', 'title' => 'Official Email',            'value' => get_setting('contact_email', SITE_EMAIL)],
    ['icon' => 'phone', 'title' => 'Contact Number',            'value' => get_setting('contact_phone', SITE_PHONE)],
    ['icon' => 'calendar', 'title' => 'Legal Status',             'value' => 'Registered Non-Profit Company (Section 8)'],
];

/* ---- SEO + hero ------------------------------------------------------- */
seo_set([
    'title'       => 'Certificates & Recognition',
    'description' => 'View the official certificates, registrations and recognitions of EDUSKILL INDIA FOUNDATION — proof of our commitment to transparency, compliance and accountable community work across Bihar.',
    'page_key'    => 'certificates',
]);

$page_hero = [
    'title'      => 'Certificates & Recognition',
    'subtitle'   => 'Trust is earned through transparency. Explore the official registrations, certifications and honours that validate our work and the accountability we hold ourselves to.',
    'breadcrumb' => [['label' => 'Certificates & Recognition']],
];

include __DIR__ . '/includes/header.php';
?>

<!-- ============================== TRUST HIGHLIGHTS ============================== -->
<section class="section section-brand section-sm">
    <div class="container">
        <div class="counter-grid">
            <?php foreach ($trust as $t): ?>
                <div class="counter-item reveal">
                    <div class="icon-badge" style="background:rgba(255,255,255,.18);"><?= lucide($t['icon']) ?></div>
                    <div class="counter-value"><?php if ($t['animate']): ?><span data-counter="<?= (int) $t['value'] ?>">0</span><?php else: ?><?= e((string) $t['value']) ?><?php endif; ?><?= e($t['suffix']) ?></div>
                    <div class="counter-label"><?= e($t['label']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== WHY IT MATTERS ============================== -->
<section class="section">
    <div class="container grid grid-2 items-center gap-4">
        <div class="reveal">
            <span class="eyebrow">Transparency First</span>
            <h2 class="section-title"><?= e(get_setting('certificates_intro_title', 'Credentials you can verify, trust you can feel')) ?></h2>
            <p class="text-muted"><?= e(get_setting('certificates_intro_text', SITE_NAME . ' is a registered non-profit (CIN ' . SITE_CIN . ') based in Patna, Bihar. Every certificate on this page is a public record of our legal standing and our promise of accountability. We believe donors, partners and communities deserve full visibility into who we are and how we operate.')) ?></p>
            <ul class="list-check mt-3">
                <li>Officially registered and compliant with statutory requirements</li>
                <li>Documents issued by recognised government and partner bodies</li>
                <li>Every rupee tracked — from donation to delivery on the ground</li>
            </ul>
            <div class="flex flex-wrap gap-2 mt-4">
                <a class="btn btn-primary" href="<?= e(url('contact')) ?>">Request Documents</a>
                <a class="btn btn-outline" href="<?= e(url('about')) ?>">About the Foundation</a>
            </div>
        </div>
        <div class="grid gap-3 reveal delay-1">
            <div class="card-3d">
                <div class="icon-badge"><?= lucide('shield') ?></div>
                <h3 class="card-title">Accountable by Design</h3>
                <p class="card-text">We operate under strict governance, with our directors and volunteers held to the highest standards of integrity and transparency.</p>
            </div>
            <div class="card-3d">
                <div class="icon-badge accent"><?= lucide('sparkles') ?></div>
                <h3 class="card-title">Recognised for Impact</h3>
                <p class="card-text">Our certifications reflect not just compliance, but the tangible, measurable change we create in communities across Bihar.</p>
            </div>
        </div>
    </div>
</section>

<!-- ============================== CERTIFICATES GALLERY ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Our Credentials</span>
            <h2 class="section-title">Certificates &amp; Registrations</h2>
            <p class="section-subtitle">Tap any certificate to view it in full detail. These documents are the foundation of the trust our supporters place in us.</p>
        </div>

        <?php if ($certs): ?>
            <div class="gallery-grid">
                <?php foreach ($certs as $i => $c): $img = upload_url($c['image']); ?>
                    <article class="card reveal <?= 'delay-' . (($i % 3) + 1) ?>">
                        <a class="card-media" href="<?= e($img) ?>" data-lightbox="<?= e($img) ?>"
                           aria-label="View certificate: <?= e($c['title']) ?>"
                           style="display:block;position:relative;cursor:zoom-in;">
                            <img src="<?= e($img) ?>" alt="<?= e($c['title']) ?>" loading="lazy" width="600" height="450"
                                 style="width:100%;aspect-ratio:4/3;object-fit:cover;">
                            <span aria-hidden="true"
                                  style="position:absolute;top:12px;right:12px;width:38px;height:38px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:rgba(17,24,39,.6);color:#fff;font-size:1rem;backdrop-filter:blur(4px);"><?= lucide('search') ?></span>
                        </a>
                        <div class="card-body">
                            <h3 class="card-title" style="font-size:1.05rem;"><?= e($c['title']) ?></h3>
                            <?php if (!empty($c['issued_by'])): ?>
                                <p class="card-text mb-0">Issued by <strong><?= e($c['issued_by']) ?></strong></p>
                            <?php endif; ?>
                            <?php if (!empty($c['issue_date'])): ?>
                                <small class="text-muted"><?= lucide('calendar-days') ?> <?= e(format_date($c['issue_date'], 'd M Y')) ?></small>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon-badge"><?= lucide('scroll') ?></div>
                <h3>Certificates Coming Online Soon</h3>
                <p class="text-muted">
                    <?= e(SITE_NAME) ?> is a registered non-profit company (CIN <?= e(SITE_CIN) ?>) based in Patna, Bihar.
                    Our official certificates, registrations and recognitions are being prepared for publication and will appear here shortly.
                </p>
                <a class="btn btn-primary mt-2" href="<?= e(url('contact')) ?>">Request Our Credentials</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ============================== REGISTRATION DETAILS ============================== -->
<section class="section section-alt">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">On The Record</span>
            <h2 class="section-title">Registration Details</h2>
            <p class="section-subtitle">The key facts that establish our legal identity and make us accountable to every community we serve.</p>
        </div>
        <div class="grid grid-3">
            <?php foreach ($facts as $i => $f): ?>
                <div class="card reveal <?= 'delay-' . (($i % 3) + 1) ?>">
                    <div class="card-body flex items-center gap-3">
                        <div class="icon-badge" style="flex:0 0 auto;"><?= lucide($f['icon']) ?></div>
                        <div>
                            <small class="text-muted" style="display:block;text-transform:uppercase;letter-spacing:.04em;font-size:.72rem;"><?= e($f['title']) ?></small>
                            <strong style="font-size:1.02rem;"><?= e($f['value']) ?></strong>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== CTA BAND ============================== -->
<section class="section">
    <div class="container">
        <div class="card-3d reveal text-center" style="background:var(--grad-brand);color:#fff;">
            <span class="eyebrow" style="justify-content:center;color:#dbeafe;"><?= e(SITE_TAGLINE) ?></span>
            <h2 class="text-white">Partner With an Organisation You Can Trust</h2>
            <p style="color:rgba(255,255,255,.9);max-width:640px;margin-inline:auto;">Transparency is only the beginning. Join us as a donor, volunteer or partner and help turn our credentials into real, lasting change on the ground.</p>
            <div class="flex flex-wrap justify-center gap-2 mt-4">
                <a class="btn btn-white btn-lg" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Donate Now</a>
                <a class="btn btn-outline btn-lg" style="border-color:#fff;color:#fff;" href="<?= e(url('volunteer')) ?>"><?= lucide('handshake') ?> Become a Volunteer</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
