<?php
/**
 * =============================================================================
 *  Verify Award — public verification of a student certificate OR marksheet
 * =============================================================================
 *  ?serial= : looks up student_certificates by serial, then marksheets by
 *  serial, and shows a verified / revoked / not-found result.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

$siteName = get_setting('site_name', SITE_NAME);
$serial   = strtoupper(clean(get('serial', '')));
$hasQuery = array_key_exists('serial', $_GET);

$kind = null; $doc = null; $student = null; $course = null; $throttled = false;
/* Own bucket, not the shared 'verify-lookup' one. Five public verifiers used the
   same key, so a visitor checking one certificate spent the budget of the member,
   employee and student lookups too — and a member-code enumerator could exhaust
   the award verifier for everybody. One register, one window. */
if ($serial !== '' && !pwf_throttle('verify-award', 10, 300)) {
    $throttled = true;
} elseif ($serial !== '') {
    $doc = db_row('SELECT * FROM student_certificates WHERE serial = :s LIMIT 1', [':s' => $serial]);
    if ($doc) {
        $kind    = 'certificate';
        $student = find('school_students', (int) $doc['student_id']);
        $course  = $doc['course_id'] ? find('courses', (int) $doc['course_id']) : null;
    } else {
        $doc = db_row('SELECT * FROM marksheets WHERE serial = :s LIMIT 1', [':s' => $serial]);
        if ($doc) {
            $kind    = 'marksheet';
            $student = find('school_students', (int) $doc['student_id']);
        }
    }
}
$revoked = $kind === 'certificate' && ($doc['status'] ?? '') === 'revoked';

seo_set([
    'title'       => 'Verify Certificate / Marksheet',
    'description' => 'Verify a ' . $siteName . ' certificate or marksheet by its serial number.',
    'page_key'    => 'verify-award',
    'robots'      => 'noindex,follow',
]);
$page_hero = [
    'title'      => 'Verify Document',
    'subtitle'   => 'Confirm a ' . $siteName . ' certificate or marksheet.',
    'breadcrumb' => [['label' => 'Verify Document']],
];
include __DIR__ . '/includes/header.php';
?>
<section class="section" style="position:relative;overflow:hidden;">
    <span class="blob b1" style="top:-80px;left:-60px;"></span>
    <div class="container" style="position:relative;z-index:1;">
        <div class="section-head">
            <span class="eyebrow">Authenticity Check</span>
            <h2 class="section-title">Verify a Document</h2>
            <p class="section-subtitle">Enter the serial number printed on the certificate or marksheet.</p>
        </div>

        <div class="glass-card reveal" style="max-width:600px;margin-inline:auto;">
            <form method="get" action="<?= e(url('verify-award')) ?>#result" novalidate>
                <div class="field-float" style="margin-bottom:1rem;">
                    <input type="text" id="serial" name="serial" value="<?= e($serial) ?>" placeholder=" " autocomplete="off" autocapitalize="characters" maxlength="48" required>
                    <label for="serial">Serial number</label>
                </div>
                <button class="btn btn-3d btn-block btn-lg" type="submit"><?= lucide('shield-check') ?> Verify</button>
            </form>
        </div>

        <?php if ($hasQuery): ?>
        <div id="result" style="max-width:640px;margin:2.75rem auto 0;">
            <?php if ($throttled): ?>
                <div class="alert alert-warning reveal"><strong>Too many verification attempts.</strong> Please wait a few minutes and try again.</div>
            <?php elseif (!$doc): ?>
                <div class="alert alert-warning reveal"><strong>No matching document found.</strong> Check the serial for typos, or it may not have been issued by us.</div>
            <?php else:
                [$c1, $c2, $icon, $title] = $revoked
                    ? ['#b91c1c', '#ef4444', 'x', 'Revoked — no longer valid']
                    : ['#1F5C48', '#2F8065', 'check', 'Genuine — verified'];
            ?>
                <div class="card-3d reveal" style="padding:0;overflow:hidden;">
                    <div style="background:linear-gradient(135deg,<?= $c1 ?>,<?= $c2 ?>);color:#fff;padding:1.5rem 1.7rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                        <div class="icon-badge" style="background:rgba(255,255,255,.22);font-size:1.8rem;flex:0 0 auto;box-shadow:none;"><?= lucide($icon) ?></div>
                        <div style="flex:1 1 240px;">
                            <span class="badge" style="background:rgba(255,255,255,.22);color:#fff;"><?= e(ucfirst($kind)) ?></span>
                            <h3 class="text-white mb-0" style="margin-top:.4rem;"><?= e($title) ?></h3>
                            <p style="color:rgba(255,255,255,.92);margin:.25rem 0 0;">Issued by <?= e($siteName) ?>.</p>
                        </div>
                    </div>
                    <div style="padding:1.7rem;"><div class="grid grid-2 gap-3">
                        <div><small class="text-muted">Holder</small><div><strong><?= e($student['name'] ?? '—') ?></strong></div></div>
                        <div><small class="text-muted">Serial</small><div><strong><?= e($doc['serial']) ?></strong></div></div>
                        <?php if ($kind === 'certificate'): ?>
                            <div><small class="text-muted">Title</small><div><strong><?= e($doc['title']) ?></strong></div></div>
                            <div><small class="text-muted">Type</small><div><strong><?= e(ucfirst($doc['type'])) ?></strong></div></div>
                            <?php if ($course): ?><div><small class="text-muted">Course</small><div><strong><?= e($course['title']) ?></strong></div></div><?php endif; ?>
                            <div><small class="text-muted">Issued</small><div><strong><?= $doc['issue_date'] ? e(format_date($doc['issue_date'])) : '—' ?></strong></div></div>
                        <?php else: ?>
                            <div><small class="text-muted">Marksheet</small><div><strong><?= e($doc['title']) ?></strong></div></div>
                            <div><small class="text-muted">Percentage</small><div><strong><?= e($doc['percentage']) ?>%</strong></div></div>
                            <div><small class="text-muted">Grade</small><div><strong><?= e($doc['grade'] ?? '—') ?></strong></div></div>
                            <div><small class="text-muted">Result</small><div><strong><?= e(ucfirst($doc['result'])) ?></strong></div></div>
                        <?php endif; ?>
                    </div></div>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
