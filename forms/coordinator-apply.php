<?php
/**
 * =============================================================================
 *  Community Coordinator application handler.
 * -----------------------------------------------------------------------------
 *  Backs coordinator-apply.php. Multipart: up to ten checklist documents come
 *  with the text fields, so the order of work matters —
 *
 *    1. method + CSRF + honeypot + throttle
 *    2. every text/scalar rule, so a typo never costs an upload
 *    3. the files, tracking what landed on disk
 *    4. the insert; if it throws, the files already written are removed again
 *
 *  Option lists (positions, skills, focus areas, document slots) all come from
 *  includes/coordinator.php, and every submitted value is checked against them
 *  rather than stored as sent — the checkbox groups post arrays, which is the
 *  one input shape validate() deliberately refuses.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';

if (!is_post()) json_error('Method not allowed.', [], 405);
require_csrf();

$DONE = 'Your application has been received. Our team reviews every application and will contact you shortly.';

/* Honeypot — a hidden field humans never fill. A filled one means a bot, so the
   submission is dropped while the sender is told it worked.

   It is LOGGED, because that trade is only safe while the field is genuinely
   untouchable by humans. This field used to be called 'website_hp', and
   Chrome and password managers autofill anything whose name starts with
   'website' — so real applications were being destroyed silently, and the
   only visible symptom was a success message with no reference number.
   If these lines ever start appearing for real people, the name is wrong
   again. */
if (trim((string) post('pwf_zq')) !== '') {
    error_log('[honeypot] ' . basename(__FILE__) . ' dropped a submission from ' . client_ip());
    json_success($DONE);
}
if (!pwf_throttle('coordinator-form', 4, 600)) {
    json_error('Too many submissions — please try again in a few minutes.', [], 429);
}

/* -------------------------------------------------------------- 1. scalars */
$errors = validate($_POST, [
    'name'              => 'required|max:128',
    'email'             => 'required|email',
    'phone'             => 'required|max:32',
    'guardian_name'     => 'max:128',
    'current_address'   => 'max:500',
    'permanent_address' => 'max:500',
    'community_note'    => 'max:2000',
    'ngo_details'       => 'max:2000',
]);

$positions = coord_positions();
$position  = (string) post('position', '');
if (!isset($positions[$position])) {
    $errors['position'] = 'Please choose the position you are applying for.';
}

// The declaration is the whole point of section 11 — it cannot be implied.
if ((int) post('consent', 0) !== 1) {
    $errors['consent'] = 'Please confirm the declaration to submit your application.';
}

/** Accept a date only in the form the date input posts it, else null. */
$date = static function (string $key): ?string {
    $v = trim((string) post($key, ''));
    if ($v === '') {
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', $v);
    return ($d && $d->format('Y-m-d') === $v) ? $v : null;
};

$dob = $date('dob');
if (trim((string) post('dob', '')) !== '' && $dob === null) {
    $errors['dob'] = 'Please enter a valid date of birth.';
} elseif ($dob !== null) {
    $age = (new DateTime($dob))->diff(new DateTime('today'))->y;
    if ($age < 18) {
        $errors['dob'] = 'Applicants must be at least 18 years old.';
    } elseif ($age > 75) {
        $errors['dob'] = 'Please check the date of birth you entered.';
    }
}

$gender = (string) post('gender', '');
if (!in_array($gender, ['male', 'female', 'other'], true)) {
    $gender = '';
}

$workMode = (string) post('work_mode', '');
if (!in_array($workMode, coord_work_modes(), true)) {
    $workMode = '';
}

$availableFrom = $date('available_from');
if (trim((string) post('available_from', '')) !== '' && $availableFrom === null) {
    $errors['available_from'] = 'Please enter a valid joining date.';
}

if ($errors) json_error('Please correct the highlighted fields.', ['errors' => $errors]);

/* Country selector: validate the number against the chosen country and keep the
   country name / ISO / dial code with it. The WhatsApp number is optional and
   reuses the same control, so it is only validated when something was typed. */
$pwfCountry = country_capture('phone', true);
if (!$pwfCountry['ok']) {
    json_error($pwfCountry['error'], ['errors' => ['phone' => $pwfCountry['error']]]);
}
$waCountry = country_capture('whatsapp', false);
if (!$waCountry['ok']) {
    json_error($waCountry['error'], ['errors' => ['whatsapp' => $waCountry['error']]]);
}

/**
 * Read a checkbox group. Only values on the whitelist survive, plus one
 * free-text "Other" the paper form allows; the result is a display-ready CSV.
 */
$checkGroup = static function (string $key, array $allowed, string $otherKey = '') : string {
    $raw = $_POST[$key] ?? [];
    if (!is_array($raw)) {
        $raw = [];
    }
    $picked = [];
    foreach ($raw as $v) {
        if (is_string($v) && in_array($v, $allowed, true) && !in_array($v, $picked, true)) {
            $picked[] = $v;
        }
    }
    if ($otherKey !== '') {
        $other = clean(post($otherKey, ''));
        if ($other !== '') {
            $picked[] = mb_substr($other, 0, 64);
        }
    }
    return mb_substr(implode(', ', $picked), 0, 500);
};

$computerSkills = $checkGroup('computer_skills', coord_computer_skills(), 'computer_other');
$focusAreas     = $checkGroup('focus_areas', coord_focus_areas());
$languages      = $checkGroup('languages', coord_languages(), 'language_other');

/**
 * Section 3 — the qualification table. One row per level, in the order the
 * form renders them; rows the applicant left completely blank are dropped so
 * the admin view is not padded with empty lines.
 */
$eduRows = [];
foreach (coord_education_levels() as $i => $level) {
    $row = [
        'level' => $level,
        'board' => mb_substr(clean($_POST['edu_board'][$i] ?? ''), 0, 128),
        'year'  => mb_substr(clean($_POST['edu_year'][$i] ?? ''), 0, 16),
        'grade' => mb_substr(clean($_POST['edu_grade'][$i] ?? ''), 0, 32),
    ];
    if ($row['board'] !== '' || $row['year'] !== '' || $row['grade'] !== '') {
        $eduRows[] = $row;
    }
}

/**
 * Section 10 — the single reference. Each field is its own column, and the
 * list comes from includes/coordinator.php so the form and this loop cannot
 * drift apart.
 */
$reference = [];
foreach (coord_reference_fields() as $key => $meta) {
    $reference[$key] = mb_substr(clean(post($key, '')), 0, (int) $meta['max']) ?: null;
}

/* -------------------------------------------------------------- 2. uploads */
$stored  = [];   // slot => uploads-relative path
$written = [];   // flat list, for rollback

/** Remove anything already saved, then fail the request. */
$abort = static function (string $msg, array $errs) use (&$written): void {
    foreach ($written as $path) {
        delete_upload($path);
    }
    json_error($msg, ['errors' => $errs]);
};

foreach (coord_documents() as $slot => $doc) {
    $file = $_FILES[$slot] ?? null;
    $sent = is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE && (string) ($file['name'] ?? '') !== '';

    if (!$sent) {
        if (!empty($doc['required'])) {
            $abort('Please attach the required documents.', [$slot => $doc['label'] . ' is required.']);
        }
        continue;
    }

    $up = upload_file($file, COORD_DOC_DIR, [
        'allowed'    => coord_doc_allowed((bool) $doc['image']),
        'image_only' => (bool) $doc['image'],
    ]);
    if (!$up['success']) {
        $abort($doc['label'] . ': ' . $up['error'], [$slot => $up['error']]);
    }
    $stored[$slot] = $up['path'];
    $written[]     = $up['path'];
}

/* -------------------------------------------------------------- 3. persist */

/* The ID number is Aadhaar-class PII. Store the ciphertext when the app key is
   configured (Security Center) and keep only the last four digits in the clear,
   which is all the admin list needs to match a person to their paperwork. */
$idNumber = preg_replace('/[^A-Za-z0-9]/', '', (string) post('id_proof_no', ''));
$idStored = null;
$idLast4  = null;
if ($idNumber !== '') {
    $idNumber = mb_substr($idNumber, 0, 32);
    $idLast4  = mb_substr($idNumber, -4);
    $idStored = sec_has_encryption() ? (sec_encrypt($idNumber) ?? $idNumber) : $idNumber;
}

/** A yes/no answer posted as 1 or 0. */
$flag = static fn(string $key): int => (int) post($key, 0) === 1 ? 1 : 0;

$expected = trim((string) post('expected_honorarium', ''));

$data = [
    'position'             => $position,
    'preferred_panchayat'  => mb_substr(clean(post('preferred_panchayat', '')), 0, 128) ?: null,
    'village_coverage'     => mb_substr(clean(post('village_coverage', '')), 0, 255) ?: null,
    'preferred_block'      => mb_substr(clean(post('preferred_block', '')), 0, 128) ?: null,
    'block_district'       => mb_substr(clean(post('block_district', '')), 0, 128) ?: null,
    'preferred_district'   => mb_substr(clean(post('preferred_district', '')), 0, 128) ?: null,
    'district_state'       => mb_substr(clean(post('district_state', '')), 0, 128) ?: null,

    'name'                 => clean(post('name')),
    'guardian_name'        => clean(post('guardian_name', '')) ?: null,
    'dob'                  => $dob,
    'gender'               => $gender ?: null,
    'phone'                => $pwfCountry['phone'],
    'country_name'         => $pwfCountry['columns']['country_name'],
    'country_iso'          => $pwfCountry['columns']['country_iso'],
    'country_dial'         => $pwfCountry['columns']['country_dial'],
    'whatsapp'             => $waCountry['phone'] ?: null,
    'email'                => clean(post('email')),
    'id_proof_no'          => $idStored,
    'id_proof_last4'       => $idLast4,
    'current_address'      => clean(post('current_address', '')) ?: null,
    'permanent_address'    => clean(post('permanent_address', '')) ?: null,
    'state'                => mb_substr(clean(post('state', '')), 0, 96) ?: null,
    'district'             => mb_substr(clean(post('district', '')), 0, 96) ?: null,
    'block'                => mb_substr(clean(post('block', '')), 0, 96) ?: null,
    'panchayat'            => mb_substr(clean(post('panchayat', '')), 0, 96) ?: null,
    'village'              => mb_substr(clean(post('village', '')), 0, 128) ?: null,

    'education'            => $eduRows ? json_encode($eduRows, JSON_UNESCAPED_UNICODE) : null,
    'computer_skills'      => $computerSkills ?: null,

    'experience_years'     => min(60, max(0, (int) post('experience_years', 0))),
    'experience_months'    => min(11, max(0, (int) post('experience_months', 0))),
    'ngo_experience'       => (int) post('ngo_experience', 0) === 1 ? 1 : 0,
    'ngo_details'          => clean(post('ngo_details', '')) ?: null,

    'community_experience' => (int) post('community_experience', 0) === 1 ? 1 : 0,
    'focus_areas'          => $focusAreas ?: null,
    'community_note'       => clean(post('community_note', '')) ?: null,

    'languages'            => $languages ?: null,

    'field_visits'         => $flag('field_visits'),
    'can_travel'           => $flag('can_travel'),
    'two_wheeler'          => $flag('two_wheeler'),
    'has_licence'          => $flag('has_licence'),
    'work_mode'            => $workMode ?: null,
    // Clamped, not just cast: the number input's min="0" is a client-side hint.
    'expected_honorarium'  => is_numeric($expected) ? round(max(0.0, (float) $expected), 2) : null,
    'available_from'       => $availableFrom,

    'documents'            => $stored ? json_encode($stored, JSON_UNESCAPED_UNICODE) : null,

    'declared_place'       => mb_substr(clean(post('declared_place', '')), 0, 128) ?: null,
    'declared_on'          => $date('declared_on') ?? date('Y-m-d'),
    'consent'              => 1,

    'status'               => 'new',
    'ip_address'           => client_ip(),
] + $reference;   // ref_name, ref_designation, ref_organization, ref_mobile, ref_relationship

try {
    $id = db_insert('coordinator_applications', $data);
} catch (Throwable $e) {
    error_log('[coordinator-apply] ' . $e->getMessage());
    foreach ($written as $path) {
        delete_upload($path);
    }
    json_error('We could not save your application. Please try again in a moment.', [], 500);
}

/* The reference is derived from the row id, so it is unique without a second
   round trip to check for collisions. */
$applicationNo = 'CC-' . date('Y') . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
db_update('coordinator_applications', ['application_no' => $applicationNo], 'id = :id', [':id' => $id]);

/* -------------------------------------------------------------- 4. notify */
$who   = clean(post('name'));
$level = $positions[$position]['label'];

// In-app notification for the admin bell (broadcast — no user_id).
notify('New ' . $level . ' application', [
    'body' => $who . ' applied from ' . (clean(post('district', '')) ?: 'an unspecified district') . ' · ' . $applicationNo,
    'url'  => admin_url('coordinator-applications?action=view&id=' . $id),
    'icon' => 'user-plus',
    'type' => 'info',
]);

/* The notification carries the WHOLE application, not a summary, so the office
   can read and act on it without opening the panel. It is rendered from the
   stored row rather than from $_POST, which means what gets mailed is exactly
   what got saved — if a value were dropped on the way into the database, the
   email would show that too instead of masking it. */
$saved = find('coordinator_applications', $id) ?: [];

// Best-effort mail — neither failure may block the applicant's response.
try {
    send_mail(
        get_setting('contact_email', SITE_EMAIL),
        'New ' . $level . ' application — ' . $applicationNo . ' — ' . $who,
        '<p><strong>' . e($who) . '</strong> has applied for the <strong>' . e($level) . '</strong> position.</p>'
        . '<p><a href="' . e(admin_url('coordinator-applications?action=view&id=' . $id)) . '">'
        . 'Open the application in the admin panel</a> — the uploaded documents are there, '
        . 'behind the admin login.</p>'
        . coord_application_html($saved),
        ['reply_to' => clean(post('email'))]
    );
} catch (Throwable $e) { /* ignore mail failures on local/dev */ }

try {
    send_mail(
        clean(post('email')),
        'We received your application — ' . $applicationNo,
        '<p>Dear ' . e($who) . ',</p>'
        . '<p>Thank you for applying for the <strong>' . e($level) . '</strong> position with ' . e(SITE_NAME) . '.</p>'
        . '<p>Your application reference is <strong>' . e($applicationNo) . '</strong>. Please quote it in any correspondence with us.</p>'
        . '<p>Our team verifies the documents and details of every application before shortlisting. '
        . 'We will contact you on the number and email you provided once your application has been reviewed.</p>'
        . '<p>Warm regards,<br>' . e(SITE_NAME) . '</p>'
    );
} catch (Throwable $e) { /* ignore mail failures on local/dev */ }

json_success($DONE, ['application_no' => $applicationNo]);
