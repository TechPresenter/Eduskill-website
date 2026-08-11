<?php
/**
 * =============================================================================
 *  Verify Certificate — public, self-service verification of certificates
 *  issued by EDUSKILL INDIA FOUNDATION (volunteer, internship, participation,
 *  appreciation, training). A GET search looks up `issued_certificates` by its
 *  unique certificate_number and renders a verified / revoked / not-found card.
 *  Fully DB-driven; every echoed value is escaped with e().
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* ---- Lookup ----------------------------------------------------------- */
$siteName   = get_setting('site_name', SITE_NAME);
$hasQuery   = array_key_exists('certificate_number', $_GET);      // form was submitted
$certNumber = clean(get('certificate_number', ''));               // strip tags + trim
$cert       = null;

$throttled = false;
if ($hasQuery && $certNumber !== '') {
    /* Own bucket — see the note in verify-award.php. The five public verifiers
       shared one 'verify-lookup' key, so they spent each other's budget. */
    if (!pwf_throttle('verify-certificate', 10, 300)) {
        $throttled = true;
    } else {
        $cert = db_row(
            "SELECT * FROM issued_certificates WHERE certificate_number = :n LIMIT 1",
            [':n' => $certNumber]
        );
    }
}

/* Human labels for the certificate `type` enum */
$typeLabels = [
    'volunteer'     => 'Volunteer Certificate',
    'internship'    => 'Internship Certificate',
    'participation' => 'Certificate of Participation',
    'appreciation'  => 'Certificate of Appreciation',
    'training'      => 'Training Certificate',
];

/* Certificate categories (curated — used for the info grid) */
$types = [
    ['icon' => 'handshake',      'cls' => '',  'title' => 'Volunteer',     'text' => 'Issued to volunteers who contribute their time and effort to our field programs.'],
    ['icon' => 'graduation-cap', 'cls' => 'p', 'title' => 'Internship',    'text' => 'Awarded to interns on successful completion of a structured internship with us.'],
    ['icon' => 'scroll',         'cls' => 'o', 'title' => 'Participation',  'text' => 'Given to participants of our events, camps, workshops and awareness drives.'],
    ['icon' => 'sparkles',       'cls' => 'g', 'title' => 'Appreciation',   'text' => 'Recognises outstanding contribution, service or support to our mission.'],
    ['icon' => 'wrench',         'cls' => '',  'title' => 'Training',       'text' => 'Certifies completion of a skill-development or capacity-building training.'],
];

/* Verification FAQ */
$faqs = [
    ['q' => 'Where do I find my certificate number?',        'a' => 'The certificate number is printed on the certificate itself, usually near the bottom or in the footer — for example EIF-2026-000123. Enter it exactly as shown.'],
    ['q' => 'My certificate says valid but shows expired.',  'a' => 'Some certificates carry a validity period. A certificate can be genuine (valid record) yet past its expiry date. The result card shows both the status and the expiry date.'],
    ['q' => 'The number is not found — what should I do?',   'a' => 'Check for typing mistakes, extra spaces or a confused 0/O or 1/l. If it still fails, the certificate may not have been issued by us. Contact our team with a photo of the certificate.'],
    ['q' => 'Why does a certificate show as revoked?',       'a' => 'A certificate is revoked when it was cancelled or superseded after issue. A revoked certificate is no longer valid and should not be relied upon.'],
];

seo_set([
    'title'       => 'Verify Certificate',
    'description' => 'Instantly verify the authenticity of any certificate issued by ' . $siteName . '. Enter the certificate number to confirm the holder, type, program and validity.',
    'page_key'    => 'verify-certificate',
    'type'        => 'website',
]);

$page_hero = [
    'title'      => 'Verify a Certificate',
    'subtitle'   => 'Confirm the authenticity of any certificate issued by ' . $siteName . ' in seconds — no login required.',
    'breadcrumb' => [['label' => 'Verify Certificate']],
];

include __DIR__ . '/includes/header.php';
?>

<!-- ============================== SEARCH + RESULT ============================== -->
<section class="section" style="position:relative;overflow:hidden;">
    <span class="blob b1" style="top:-80px;left:-60px;"></span>
    <span class="blob b3" style="bottom:-90px;right:-70px;"></span>
    <div class="container" style="position:relative;z-index:1;">
        <div class="section-head">
            <span class="eyebrow">Authenticity Check</span>
            <h2 class="section-title">Verify Certificate</h2>
            <p class="section-subtitle">Enter the certificate number exactly as printed on your document to instantly confirm its validity.</p>
        </div>

        <div class="glass-card reveal" style="max-width:680px;margin-inline:auto;">
            <form method="get" action="<?= e(url('verify-certificate')) ?>#result" novalidate>
                <div class="field-float" style="margin-bottom:1rem;">
                    <input type="text" id="certificate_number" name="certificate_number"
                           value="<?= e($certNumber) ?>" placeholder=" "
                           autocomplete="off" autocapitalize="characters" spellcheck="false"
                           maxlength="64" required>
                    <label for="certificate_number">Certificate Number</label>
                </div>
                <p class="form-hint mb-3">Example format: <strong>EIF-2026-000123</strong></p>
                <button class="btn btn-3d btn-block btn-lg" type="submit"><?= lucide('search') ?> Verify Now</button>
            </form>
        </div>

        <?php if ($hasQuery): ?>
        <div id="result" style="max-width:760px;margin:2.75rem auto 0;">
            <?php if ($certNumber === ''): ?>
                <!-- Empty submission -->
                <div class="alert alert-warning reveal">
                    <strong>Please enter a certificate number.</strong> Type the number printed on your certificate and select “Verify Now”.
                </div>

            <?php elseif ($throttled): ?>
                <!-- Rate limited -->
                <div class="alert alert-warning reveal">
                    <strong>Too many verification attempts.</strong> Please wait a few minutes and try again.
                </div>

            <?php elseif ($cert && $cert['status'] === 'valid'):
                $isExpired = !empty($cert['expiry_date']) && strtotime($cert['expiry_date']) < strtotime(date('Y-m-d'));
                $typeLabel = $typeLabels[$cert['type']] ?? ucfirst((string) $cert['type']);
            ?>
                <!-- VALID -->
                <div class="card-3d reveal" style="padding:0;overflow:hidden;border:1px solid rgba(47,128,101,.35);">
                    <div style="background:linear-gradient(135deg,#1F5C48,#2F8065);color:#fff;padding:1.6rem 1.8rem;display:flex;align-items:center;gap:1.1rem;flex-wrap:wrap;">
                        <div class="icon-badge" style="background:rgba(255,255,255,.22);font-size:1.9rem;flex:0 0 auto;box-shadow:none;"><?= lucide('check') ?></div>
                        <div style="flex:1 1 240px;">
                            <span class="badge" style="background:rgba(255,255,255,.22);color:#fff;">Verified Certificate</span>
                            <h3 class="text-white mb-0" style="margin-top:.4rem;">This certificate is genuine</h3>
                            <p style="color:rgba(255,255,255,.92);margin:.25rem 0 0;">Issued and recognised by <?= e($siteName) ?>.</p>
                        </div>
                    </div>
                    <div style="padding:1.9rem;">
                        <div class="grid grid-2 gap-3">
                            <div>
                                <small class="text-muted">Certificate Number</small>
                                <div><strong><?= e($cert['certificate_number']) ?></strong></div>
                            </div>
                            <div>
                                <small class="text-muted">Certificate Holder</small>
                                <div><strong><?= e($cert['holder_name']) ?></strong></div>
                            </div>
                            <div>
                                <small class="text-muted">Certificate Type</small>
                                <div><strong><?= e($typeLabel) ?></strong></div>
                            </div>
                            <div>
                                <small class="text-muted">Program</small>
                                <div><strong><?= e($cert['program'] ?: '—') ?></strong></div>
                            </div>
                            <div>
                                <small class="text-muted">Issue Date</small>
                                <div><strong><?= e($cert['issue_date'] ? format_date($cert['issue_date'], 'd M Y') : '—') ?></strong></div>
                            </div>
                            <div>
                                <small class="text-muted">Valid Until</small>
                                <div>
                                    <strong><?= e($cert['expiry_date'] ? format_date($cert['expiry_date'], 'd M Y') : 'No expiry') ?></strong>
                                    <?php if ($isExpired): ?>
                                        <span class="badge badge-danger" style="margin-left:.4rem;">Expired</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 mt-4">
                            <span class="badge badge-success">● Status: Valid</span>
                            <?php if (!empty($cert['file_path'])): ?>
                                <a class="btn btn-outline btn-sm" href="<?= e(upload_url($cert['file_path'])) ?>" target="_blank" rel="noopener"><?= lucide('arrow-down') ?> View Certificate</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            <?php elseif ($cert && $cert['status'] === 'revoked'):
                $typeLabel = $typeLabels[$cert['type']] ?? ucfirst((string) $cert['type']);
            ?>
                <!-- REVOKED -->
                <div class="card-3d reveal" style="padding:0;overflow:hidden;border:1px solid rgba(220,38,38,.35);">
                    <div style="background:linear-gradient(135deg,#b91c1c,#ef4444);color:#fff;padding:1.6rem 1.8rem;display:flex;align-items:center;gap:1.1rem;flex-wrap:wrap;">
                        <div class="icon-badge" style="background:rgba(255,255,255,.22);font-size:1.9rem;flex:0 0 auto;box-shadow:none;"><?= lucide('ban') ?></div>
                        <div style="flex:1 1 240px;">
                            <span class="badge" style="background:rgba(255,255,255,.22);color:#fff;">Revoked Certificate</span>
                            <h3 class="text-white mb-0" style="margin-top:.4rem;">This certificate has been revoked</h3>
                            <p style="color:rgba(255,255,255,.92);margin:.25rem 0 0;">It is no longer valid and should not be relied upon.</p>
                        </div>
                    </div>
                    <div style="padding:1.9rem;">
                        <div class="grid grid-2 gap-3">
                            <div>
                                <small class="text-muted">Certificate Number</small>
                                <div><strong><?= e($cert['certificate_number']) ?></strong></div>
                            </div>
                            <div>
                                <small class="text-muted">Certificate Holder</small>
                                <div><strong><?= e($cert['holder_name']) ?></strong></div>
                            </div>
                            <div>
                                <small class="text-muted">Certificate Type</small>
                                <div><strong><?= e($typeLabel) ?></strong></div>
                            </div>
                            <div>
                                <small class="text-muted">Program</small>
                                <div><strong><?= e($cert['program'] ?: '—') ?></strong></div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <span class="badge badge-danger">● Status: Revoked</span>
                        </div>
                        <p class="text-muted mt-3 mb-0">If you believe this is an error, please <a href="<?= e(url('contact')) ?>">contact our team</a> with the certificate details.</p>
                    </div>
                </div>

            <?php else: ?>
                <!-- NOT FOUND -->
                <div class="card-3d reveal" style="padding:0;overflow:hidden;border:1px solid rgba(241,90,36,.4);">
                    <div style="background:linear-gradient(135deg,#F15A24,#F15A24);color:#fff;padding:1.6rem 1.8rem;display:flex;align-items:center;gap:1.1rem;flex-wrap:wrap;">
                        <div class="icon-badge" style="background:rgba(255,255,255,.22);font-size:1.9rem;flex:0 0 auto;box-shadow:none;"><?= lucide('triangle-alert') ?></div>
                        <div style="flex:1 1 240px;">
                            <span class="badge" style="background:rgba(255,255,255,.22);color:#fff;">No Record Found</span>
                            <h3 class="text-white mb-0" style="margin-top:.4rem;">We couldn’t verify this number</h3>
                            <p style="color:rgba(255,255,255,.92);margin:.25rem 0 0;">No certificate matches <strong><?= e($certNumber) ?></strong>.</p>
                        </div>
                    </div>
                    <div style="padding:1.9rem;">
                        <p class="mb-2"><strong>Before you try again, please check:</strong></p>
                        <ul class="list-check">
                            <li>The number is entered exactly as printed, with no extra spaces.</li>
                            <li>You haven’t confused similar characters (0 and O, 1 and l).</li>
                            <li>The certificate was issued by <?= e($siteName) ?>.</li>
                        </ul>
                        <div class="flex flex-wrap gap-2 mt-3">
                            <a class="btn btn-outline btn-sm" href="#certificate_number"><?= lucide('rotate-ccw') ?> Try Again</a>
                            <a class="btn btn-primary btn-sm" href="<?= e(url('contact')) ?>">Contact Support</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<div class="wave-divider" aria-hidden="true">
    <svg viewBox="0 0 1440 60" preserveAspectRatio="none"><path fill="var(--surface-soft, #f1f5f9)" d="M0,32L60,29.3C120,27,240,21,360,26.7C480,32,600,48,720,48C840,48,960,32,1080,24C1200,16,1320,16,1380,16L1440,16L1440,60L0,60Z"></path></svg>
</div>

<!-- ============================== HOW IT WORKS ============================== -->
<section class="section section-soft">
    <div class="container grid grid-2 items-center gap-4">
        <div class="reveal">
            <span class="eyebrow">Simple &amp; Secure</span>
            <h2 class="section-title">How Verification Works</h2>
            <p class="text-muted">Every certificate we issue is recorded in our secure registry with a unique number. Verification is instant, free and needs no account.</p>
            <div class="process-steps mt-4">
                <div class="process-step">
                    <h3 class="card-title mb-1">Locate the number</h3>
                    <p class="card-text mb-0">Find the certificate number printed on your document (e.g. EIF-2026-000123).</p>
                </div>
                <div class="process-step">
                    <h3 class="card-title mb-1">Enter &amp; verify</h3>
                    <p class="card-text mb-0">Type it into the box above and select “Verify Now”.</p>
                </div>
                <div class="process-step">
                    <h3 class="card-title mb-1">Read the result</h3>
                    <p class="card-text mb-0">See the holder, type, program and validity — instantly.</p>
                </div>
            </div>
        </div>
        <div class="reveal delay-1">
            <div class="stat-premium">
                <span class="eyebrow">Sample Certificate Number</span>
                <div class="text-grad-ocean" style="font-family:var(--font-mono, monospace);font-size:1.6rem;font-weight:800;letter-spacing:.04em;margin:.5rem 0 .75rem;">EIF-2026-000123</div>
                <p class="text-muted mb-0" style="position:relative;z-index:1;">Numbers follow the pattern <strong>PWF</strong> — <strong>year</strong> — <strong>serial</strong>. Enter it exactly as shown, including hyphens. The lookup is not case-sensitive to hyphenation but must match the printed serial.</p>
                <div class="divider mt-3 mb-3"></div>
                <div class="flex items-center gap-2">
                    <div class="icon-badge" style="flex:0 0 auto;"><?= lucide('lock') ?></div>
                    <p class="text-muted mb-0">We only reveal the details a certificate already displays — your data stays protected.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================== CERTIFICATE TYPES ============================== -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">What We Issue</span>
            <h2 class="section-title">Types of Certificates</h2>
            <p class="section-subtitle">Any of the certificates below can be verified through this page.</p>
        </div>
        <div class="grid grid-3">
            <?php foreach ($types as $i => $t): ?>
                <div class="icon-box glass-card reveal <?= 'delay-' . (($i % 3) + 1) ?>">
                    <div class="ib-icon <?= e($t['cls']) ?>"><?= lucide($t['icon']) ?></div>
                    <h3 class="card-title"><?= e($t['title']) ?></h3>
                    <p class="card-text mb-0"><?= e($t['text']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== FAQ ============================== -->
<section class="section section-alt">
    <div class="container container-narrow">
        <div class="section-head">
            <span class="eyebrow">Need Help?</span>
            <h2 class="section-title">Verification FAQ</h2>
            <p class="section-subtitle">Answers to the most common questions about verifying a certificate.</p>
        </div>
        <div class="reveal">
            <?php foreach ($faqs as $f): ?>
                <details class="accordion-item">
                    <summary><?= e($f['q']) ?></summary>
                    <div class="acc-body"><?= e($f['a']) ?></div>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== CTA ============================== -->
<section class="section section-brand">
    <div class="container text-center reveal">
        <span class="eyebrow" style="color:#bfdbfe;justify-content:center;">Still Have Questions?</span>
        <h2 class="section-title text-white">Can’t verify your certificate?</h2>
        <p class="section-subtitle" style="color:rgba(255,255,255,.9);">Our team is happy to help confirm the authenticity of any document issued by <?= e($siteName) ?>.</p>
        <div class="flex flex-wrap justify-center gap-2 mt-4">
            <a class="btn btn-white btn-lg" href="<?= e(url('contact')) ?>">Contact Our Team</a>
            <a class="btn btn-outline btn-lg" style="border-color:#fff;color:#fff;" href="<?= e(url('about')) ?>">About the Foundation</a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
