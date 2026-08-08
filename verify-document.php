<?php
/**
 * =============================================================================
 *  Verify Document — public, self-service authenticity check for any document
 *  issued through the Document Hub. Accepts ?t=<qr_token> (the QR links here)
 *  or a manual document-number lookup (?no=DOC-2026-0001). Renders a
 *  verified / revoked / not-found card. Every echoed value is escaped.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/document_hub.php';

$siteName = get_setting('site_name', SITE_NAME);
$token    = preg_replace('/[^a-f0-9]/', '', (string) get('t', ''));
$docNo    = clean(get('no', ''));
$hasQuery = ($token !== '' || array_key_exists('no', $_GET));

$issued = null; $throttled = false;
if ($hasQuery && ($token !== '' || $docNo !== '')) {
    if (!pwf_throttle('verify-document', 12, 300)) {
        $throttled = true;
    } elseif ($token !== '') {
        $issued = dh_verify_token($token);
    } elseif ($docNo !== '') {
        $issued = dh_find_no($docNo);
    }
}

seo_set([
    'title'       => 'Verify Document',
    'description' => 'Instantly verify the authenticity of any certificate or document issued by ' . $siteName . '.',
    'page_key'    => 'verify-document',
    'type'        => 'website',
]);
$page_hero = [
    'title'      => 'Verify a Document',
    'subtitle'   => 'Confirm the authenticity of any document issued by ' . $siteName . ' in seconds — no login required.',
    'breadcrumb' => [['label' => 'Verify Document']],
];
include __DIR__ . '/includes/header.php';
?>
<section class="section" style="position:relative;overflow:hidden;">
    <span class="blob b1" style="top:-80px;left:-60px;"></span>
    <span class="blob b3" style="bottom:-90px;right:-70px;"></span>
    <div class="container" style="position:relative;z-index:1;">
        <div class="section-head">
            <span class="eyebrow">Authenticity Check</span>
            <h2 class="section-title">Verify Document</h2>
            <p class="section-subtitle">Enter the document number exactly as printed, or scan the QR code on the document.</p>
        </div>

        <div class="glass-card reveal" style="max-width:680px;margin-inline:auto;">
            <form method="get" action="<?= e(url('verify-document')) ?>#result" novalidate>
                <div class="field-float" style="margin-bottom:1rem;">
                    <input type="text" id="no" name="no" value="<?= e($docNo) ?>" placeholder=" "
                           autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="64" required>
                    <label for="no">Document Number</label>
                </div>
                <p class="form-hint mb-3">Example format: <strong>DOC-2026-0001</strong></p>
                <button class="btn btn-3d btn-block btn-lg" type="submit"><?= lucide('shield-check') ?> Verify Now</button>
            </form>
        </div>

        <?php if ($hasQuery): ?>
        <div id="result" style="max-width:760px;margin:2.75rem auto 0;">
            <?php if ($throttled): ?>
                <div class="alert alert-warning reveal"><strong>Too many verification attempts.</strong> Please wait a few minutes and try again.</div>

            <?php elseif ($issued && $issued['status'] === 'issued'):
                $data = json_decode((string) $issued['data'], true) ?: [];
            ?>
                <div class="card-3d reveal" style="padding:0;overflow:hidden;border:1px solid rgba(88,164,47,.35);">
                    <div style="background:linear-gradient(135deg,#308629,#58A42F);color:#fff;padding:1.6rem 1.8rem;display:flex;align-items:center;gap:1.1rem;flex-wrap:wrap;">
                        <div class="icon-badge" style="background:rgba(255,255,255,.22);font-size:1.9rem;flex:0 0 auto;box-shadow:none;"><?= lucide('check') ?></div>
                        <div style="flex:1 1 240px;">
                            <span class="badge" style="background:rgba(255,255,255,.22);color:#fff;">Verified Document</span>
                            <h3 class="text-white mb-0" style="margin-top:.4rem;">This document is genuine</h3>
                            <p style="color:rgba(255,255,255,.92);margin:.25rem 0 0;">Issued and recognised by <?= e($siteName) ?>.</p>
                        </div>
                    </div>
                    <div style="padding:1.9rem;">
                        <div class="grid grid-2 gap-3">
                            <div><small class="text-muted">Document Number</small><div><strong><?= e($issued['doc_no']) ?></strong></div></div>
                            <div><small class="text-muted">Document Type</small><div><strong><?= e($issued['doc_type'] ?: '—') ?></strong></div></div>
                            <div><small class="text-muted">Issued To</small><div><strong><?= e($issued['recipient_name'] ?: '—') ?></strong></div></div>
                            <div><small class="text-muted">Issued On</small><div><strong><?= e(format_date($issued['created_at'], 'd M Y')) ?></strong></div></div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 mt-4">
                            <a class="btn btn-primary" href="<?= e(url('document?t=' . $issued['qr_token'])) ?>" target="_blank"><?= lucide('file-text') ?> View document</a>
                        </div>
                    </div>
                </div>

            <?php elseif ($issued && $issued['status'] === 'revoked'): ?>
                <div class="card-3d reveal" style="padding:1.8rem;border:1px solid rgba(220,38,38,.35);">
                    <div class="flex items-center gap-2" style="color:#dc2626;"><?= lucide('shield-x') ?> <strong>This document has been revoked.</strong></div>
                    <p class="text-muted mt-2 mb-0">Document <strong><?= e($issued['doc_no']) ?></strong> was cancelled after issue and is no longer valid.</p>
                </div>

            <?php else: ?>
                <div class="alert alert-warning reveal"><strong>No matching document found.</strong> Check the number for typing mistakes (extra spaces, or a confused 0/O). If it still fails, the document may not have been issued by us.</div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
