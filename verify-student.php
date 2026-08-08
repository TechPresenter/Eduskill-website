<?php
/**
 * =============================================================================
 *  Verify Student — public verification of a student ID card (QR target)
 * =============================================================================
 *  Looks a student up by qr_token (?token=) or student code (?code=) and shows a
 *  privacy-conscious result: name, photo, student ID, school and class only.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

$siteName = get_setting('site_name', SITE_NAME);
$token    = clean(get('token', ''));
$code     = strtoupper(clean(get('code', '')));
$hasQuery = $token !== '' || array_key_exists('code', $_GET);

$student   = null;
$throttled = false;
if ($token !== '' || $code !== '') {
    if (!pwf_throttle('verify-lookup', 10, 300)) {
        $throttled = true;
    } elseif ($token !== '') {
        $student = db_row('SELECT * FROM school_students WHERE qr_token = :t LIMIT 1', [':t' => $token]);
    } else {
        $student = db_row('SELECT * FROM school_students WHERE student_code = :c LIMIT 1', [':c' => $code]);
    }
}
$school = $student ? find('schools', (int) $student['school_id']) : null;
$active = $student && in_array($student['status'], ['enrolled', 'active'], true);

seo_set([
    'title'       => 'Verify Student',
    'description' => 'Verify a ' . $siteName . ' student ID card.',
    'page_key'    => 'verify-student',
    'robots'      => 'noindex,follow',
]);
$page_hero = [
    'title'      => 'Verify Student ID',
    'subtitle'   => 'Confirm a ' . $siteName . ' student identity card.',
    'breadcrumb' => [['label' => 'Verify Student']],
];
include __DIR__ . '/includes/header.php';
?>
<section class="section" style="position:relative;overflow:hidden;">
    <span class="blob b1" style="top:-80px;left:-60px;"></span>
    <div class="container" style="position:relative;z-index:1;">
        <div class="section-head">
            <span class="eyebrow">Authenticity Check</span>
            <h2 class="section-title">Verify a Student</h2>
            <p class="section-subtitle">Enter the student ID exactly as printed on the card, or scan its QR code.</p>
        </div>

        <div class="glass-card reveal" style="max-width:600px;margin-inline:auto;">
            <form method="get" action="<?= e(url('verify-student')) ?>#result" novalidate>
                <div class="field-float" style="margin-bottom:1rem;">
                    <input type="text" id="code" name="code" value="<?= e($code) ?>" placeholder=" " autocomplete="off" autocapitalize="characters" maxlength="32" required>
                    <label for="code">Student ID</label>
                </div>
                <button class="btn btn-3d btn-block btn-lg" type="submit"><?= lucide('shield-check') ?> Verify</button>
            </form>
        </div>

        <?php if ($hasQuery): ?>
        <div id="result" style="max-width:640px;margin:2.75rem auto 0;">
            <?php if ($throttled): ?>
                <div class="alert alert-warning reveal"><strong>Too many verification attempts.</strong> Please wait a few minutes and try again.</div>
            <?php elseif (!$student): ?>
                <div class="alert alert-warning reveal"><strong>No matching student found.</strong> Check the ID for typos, or the card may not have been issued by us.</div>
            <?php else:
                [$c1, $c2, $icon, $title] = $active
                    ? ['#308629', '#58A42F', 'check', 'Verified Student']
                    : ['#b45309', '#E67B1D', 'user', 'Former / Inactive Student'];
            ?>
                <div class="card-3d reveal" style="padding:0;overflow:hidden;">
                    <div style="background:linear-gradient(135deg,<?= $c1 ?>,<?= $c2 ?>);color:#fff;padding:1.5rem 1.7rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                        <div class="icon-badge" style="background:rgba(255,255,255,.22);font-size:1.8rem;flex:0 0 auto;box-shadow:none;"><?= lucide($icon) ?></div>
                        <div style="flex:1 1 240px;">
                            <span class="badge" style="background:rgba(255,255,255,.22);color:#fff;"><?= e(ucfirst($student['status'])) ?></span>
                            <h3 class="text-white mb-0" style="margin-top:.4rem;"><?= e($title) ?></h3>
                        </div>
                    </div>
                    <div style="padding:1.7rem;display:flex;gap:1.4rem;flex-wrap:wrap;align-items:center;">
                        <img src="<?= e(image_url($student['photo'] ?? null, 'avatar')) ?>" alt="" style="width:104px;height:104px;border-radius:14px;object-fit:cover;border:3px solid <?= $c2 ?>;flex:none;">
                        <div style="flex:1 1 260px;"><div class="grid grid-2 gap-3">
                            <div><small class="text-muted">Name</small><div><strong><?= e($student['name']) ?></strong></div></div>
                            <div><small class="text-muted">Student ID</small><div><strong><?= e($student['student_code']) ?></strong></div></div>
                            <div><small class="text-muted">School</small><div><strong><?= e($school['name'] ?? '—') ?></strong></div></div>
                            <div><small class="text-muted">Class</small><div><strong><?= e(trim((string) $student['grade'] . (!empty($student['section']) ? ' - ' . $student['section'] : '')) ?: '—') ?></strong></div></div>
                        </div></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
