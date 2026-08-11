<?php
/**
 * =============================================================================
 *  Admission Application — public online admission form
 * =============================================================================
 *  Applicants apply here; the submission lands in `admissions` (status 'new')
 *  for admin review → enrolment. Supports document uploads.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

$siteName = get_setting('site_name', SITE_NAME);
$schools  = db_all("SELECT id, name FROM schools WHERE status = 'active' ORDER BY name");
$programs = school_programs();
$done     = false;
$errors   = [];

if (is_post()) {
    require_csrf();
    $name  = clean(post('name'));
    $email = strtolower(clean(post('email')));
    $phone = clean(post('phone'));

    if ($name === '')                    { $errors[] = 'Please enter the applicant name.'; }
    if ($email !== '' && !is_email($email)) { $errors[] = 'Please enter a valid email address.'; }
    if ($phone === '' && $email === '')  { $errors[] = 'Please provide a phone number or an email.'; }

    if (!$errors) {
        // Optional document uploads (up to 3).
        $docs = [];
        $docErrors = [];
        if (!empty($_FILES['documents']) && is_array($_FILES['documents']['name'])) {
            $count = min(3, count($_FILES['documents']['name']));
            for ($i = 0; $i < $count; $i++) {
                $errCode = $_FILES['documents']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
                if ($errCode === UPLOAD_ERR_NO_FILE) {
                    continue; // slot left empty — fine
                }
                $fname = (string) ($_FILES['documents']['name'][$i] ?? 'document');
                if ($errCode !== UPLOAD_ERR_OK) {
                    $docErrors[] = $fname . ' could not be uploaded'
                        . ($errCode === UPLOAD_ERR_INI_SIZE || $errCode === UPLOAD_ERR_FORM_SIZE ? ' (file too large)' : '') . '.';
                    continue;
                }
                $file = [
                    'name'     => $_FILES['documents']['name'][$i],
                    'type'     => $_FILES['documents']['type'][$i],
                    'tmp_name' => $_FILES['documents']['tmp_name'][$i],
                    'error'    => $_FILES['documents']['error'][$i],
                    'size'     => $_FILES['documents']['size'][$i],
                ];
                // The form advertises accept=".pdf,.jpg,.jpeg,.png", so the server
                // whitelist must match it. upload_document()'s default is
                // ALLOWED_DOC_EXT (office formats only, no images), which rejected
                // the normal case for a school admission — a photographed birth
                // certificate or marksheet.
                $up = upload_document($file, 'admissions', ['allowed' => 'pdf,jpg,jpeg,png']);
                if ($up['success']) {
                    $docs[] = ['title' => $file['name'], 'path' => $up['path']];
                } else {
                    // Surface WHY (wrong type / too big) instead of silently dropping it.
                    // upload_file() returns 'error' — there is no 'message' key, so the
                    // old fallback discarded every real reason.
                    $docErrors[] = $fname . ': ' . ($up['error'] ?: 'upload failed.');
                }
            }
        }
        if ($docErrors) {
            // A document the applicant attached failed — stop and tell them,
            // rather than accepting the application with the file silently missing.
            $errors = array_merge($errors, $docErrors);
        }

        $schoolId  = (int) post('school_id') ?: null;
        $programId = (int) post('program_id') ?: null;
        if ($schoolId && !find('schools', $schoolId)) { $schoolId = null; }
        if ($programId && !array_key_exists($programId, $programs)) { $programId = null; }

        if (!$errors):
        $id = db_insert('admissions', [
            'school_id'       => $schoolId,
            'name'            => $name,
            'dob'             => clean(post('dob')) ?: null,
            'gender'          => in_array(post('gender'), ['male', 'female', 'other'], true) ? post('gender') : null,
            'father_name'     => clean(post('father_name')) ?: null,
            'mother_name'     => clean(post('mother_name')) ?: null,
            'guardian_name'   => clean(post('guardian_name')) ?: null,
            'phone'           => $phone ?: null,
            'email'           => $email ?: null,
            'address'         => clean(post('address')) ?: null,
            'city'            => clean(post('city')) ?: null,
            'state'           => clean(post('state')) ?: null,
            'pincode'         => clean(post('pincode')) ?: null,
            'grade_applying'  => clean(post('grade_applying')) ?: null,
            'previous_school' => clean(post('previous_school')) ?: null,
            'program_id'      => $programId,
            'message'         => clean(post('message')) ?: null,
            'documents'       => $docs ? json_encode($docs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'status'          => 'new',
        ]);
        db_update('admissions', ['application_no' => admission_generate_no($id)], 'id = :id', [':id' => $id]);
        log_activity('admission_apply', 'admissions', 'New admission application #' . $id);
        $done = true;
        endif;
    }
}

seo_set([
    'title'       => 'Admission Application',
    'description' => 'Apply for admission to ' . $siteName . ' programs.',
    'page_key'    => 'admission-apply',
]);
$page_hero = [
    'title'      => 'Admission Application',
    'subtitle'   => 'Apply online — our team will review your application and get in touch.',
    'breadcrumb' => [['label' => 'Admission']],
];
include __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container" style="max-width:820px;">
        <?php if ($done): ?>
            <div class="card-3d reveal" style="text-align:center;padding:2.5rem;">
                <div class="icon-badge" style="margin:0 auto 1rem;background:var(--grad-green,#2F8065);color:#fff;font-size:2rem;"><?= lucide('check') ?></div>
                <h2 class="mb-1">Application received</h2>
                <p class="text-muted">Thank you for applying to <?= e($siteName) ?>. Our admissions team will review your application and contact you soon.</p>
                <a class="btn btn-primary mt-2" href="<?= e(url('/')) ?>">Back to home</a>
            </div>
        <?php else: ?>
            <?php if ($errors): ?><div class="alert alert-warning"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
            <form class="glass-card reveal" method="post" action="<?= e(url('admission-apply')) ?>" enctype="multipart/form-data" style="padding:1.8rem;">
                <?= csrf_field() ?>
                <h3 class="mb-3">Applicant details</h3>
                <div class="grid grid-2 gap-3">
                    <div class="form-group"><label class="form-label">Full name <span class="req">*</span></label><input class="form-control" name="name" required value="<?= e(post('name', '')) ?>"></div>
                    <div class="form-group"><label class="form-label">Date of birth</label><input class="form-control" type="date" name="dob" value="<?= e(post('dob', '')) ?>"></div>
                    <div class="form-group"><label class="form-label">Gender</label><select class="form-select" name="gender"><option value="">—</option><option value="male">Male</option><option value="female">Female</option><option value="other">Other</option></select></div>
                    <div class="form-group"><label class="form-label">Grade / class applying for</label><input class="form-control" name="grade_applying" value="<?= e(post('grade_applying', '')) ?>"></div>
                    <div class="form-group"><label class="form-label">Preferred school</label><select class="form-select" name="school_id"><option value="">— Any —</option><?php foreach ($schools as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label class="form-label">Program</label><select class="form-select" name="program_id"><option value="">— Any —</option><?php foreach ($programs as $pid => $t): ?><option value="<?= (int) $pid ?>"><?= e($t) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label class="form-label">Previous school</label><input class="form-control" name="previous_school" value="<?= e(post('previous_school', '')) ?>"></div>
                </div>

                <h3 class="mb-3 mt-3">Family & contact</h3>
                <div class="grid grid-2 gap-3">
                    <div class="form-group"><label class="form-label">Father's name</label><input class="form-control" name="father_name" value="<?= e(post('father_name', '')) ?>"></div>
                    <div class="form-group"><label class="form-label">Mother's name</label><input class="form-control" name="mother_name" value="<?= e(post('mother_name', '')) ?>"></div>
                    <div class="form-group"><label class="form-label">Guardian name</label><input class="form-control" name="guardian_name" value="<?= e(post('guardian_name', '')) ?>"></div>
                    <div class="form-group"><?= country_field(['name' => 'phone', 'label' => 'Phone']) ?></div>
                    <div class="form-group"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?= e(post('email', '')) ?>"></div>
                    <div class="form-group"><label class="form-label">Pincode</label><input class="form-control" name="pincode" value="<?= e(post('pincode', '')) ?>"></div>
                </div>
                <div class="form-group"><label class="form-label">Address</label><input class="form-control" name="address" value="<?= e(post('address', '')) ?>"></div>
                <div class="grid grid-2 gap-3">
                    <div class="form-group"><label class="form-label">City</label><input class="form-control" name="city" value="<?= e(post('city', '')) ?>"></div>
                    <div class="form-group"><label class="form-label">State</label><input class="form-control" name="state" value="<?= e(post('state', '')) ?>"></div>
                </div>
                <div class="form-group"><label class="form-label">Documents (birth certificate, previous marksheet, photo — up to 3)</label>
                    <input class="form-control" type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png">
                    <small class="form-hint">PDF or image, up to 5&nbsp;MB each.</small></div>
                <div class="form-group"><label class="form-label">Message (optional)</label><textarea class="form-textarea" name="message" style="min-height:90px;"><?= e(post('message', '')) ?></textarea></div>

                <button class="btn btn-3d btn-lg" type="submit"><?= lucide('send') ?> Submit application</button>
            </form>
        <?php endif; ?>
    </div>
</section>
<?php echo country_field_assets();
include __DIR__ . '/includes/footer.php'; ?>
