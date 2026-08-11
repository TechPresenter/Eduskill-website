<?php
/**
 * =============================================================================
 *  Member authentication — public member accounts (separate from admin `users`).
 * =============================================================================
 *  Session key: $_SESSION['member']  (admin uses $_SESSION['user']).
 *
 *  Registration & recovery flows are backed by the member_tokens table:
 *    - verify_email   : emailed link  /verify-email?email=&token=
 *    - reset_password : emailed link  /reset-password?email=&token=
 *    - otp            : 6-digit code emailed for /verify-otp
 *
 *  Page usage (each auth page handles its own POST):
 *    $r = member_register_unified($_POST); // unified signup (all public roles)
 *    $r = member_register($_POST);         // back-compat alias for the above
 *    $r = member_login($email, $password); // login
 *    request_password_reset($email);       // forgot
 *    reset_member_password($email,$token,$pass);
 *    verify_member_email($email,$token);
 *    verify_member_otp($email,$code);
 * =============================================================================
 */

declare(strict_types=1);

/* -------------------------------------------------------------- SESSION STATE */

function current_member(): ?array
{
    return $_SESSION['member'] ?? null;
}

function is_member_logged_in(): bool
{
    return !empty($_SESSION['member']['id']);
}

function require_member(string $redirect = '/login'): void
{
    if (!is_member_logged_in()) {
        $_SESSION['_member_intended'] = $_SERVER['REQUEST_URI'] ?? null;
        set_flash('error', 'Please sign in to continue.');
        redirect($redirect);
    }
}

function member_set_session(array $member): void
{
    session_regenerate_id(true);
    $_SESSION['member'] = [
        'id'     => (int) $member['id'],
        'name'   => $member['name'],
        'email'  => $member['email'],
        'avatar' => $member['avatar'] ?? null,
    ];
}

/* =============================================================================
 |  UNIFIED REGISTRATION — policy
 | =============================================================================
 |  Two settings decide how much friction a new account meets. Both default to
 |  '0' (OFF) and are read on every registration, so an admin can change the
 |  policy without a deploy — and an unattended deploy is never silently open.
 |  Surfaced in admin/registration-settings.php with the risk spelled out in the
 |  UI, not just here.
 | ========================================================================== */

/**
 * registration_auto_verify — mark the address verified at registration, without
 * the person ever opening the emailed link.
 *
 * THIS IS THE DANGEROUS ONE. members.email_verified_at is the only proof that a
 * registrant controls the address they typed, and member_login() refuses to seat
 * a session without it. Turning this on means:
 *   - anyone can register in somebody else's name and immediately hold a
 *     working account on that address, and
 *   - the registration endpoint becomes an account MINT, so the per-IP rate
 *     limit stops being a nuisance control and becomes the only wall.
 * Because of that, when this is on the limiter gets a tighter budget AND stops
 * failing open (see member_register_unified()), and the address owner is
 * emailed regardless so a silent takeover is not possible.
 */
function reg_auto_verify_enabled(): bool
{
    return (string) get_setting('registration_auto_verify', '0') === '1';
}

/**
 * registration_auto_approve — give the new account status='active' immediately
 * instead of parking it in the 'pending' approval queue.
 *
 * On its own this opens nothing: an active account still cannot sign in until
 * email_verified_at is set. It is the pairing with auto_verify that produces the
 * "register and you are straight in" flow.
 *
 * It NEVER reaches a role whose approval is 'admin' in auth_roles() — school is
 * hardcoded there and this setting cannot override it.
 *
 * READ BY TWO PLACES, and it has to be both or the setting is decorative:
 *   member_register_unified()   — what status a new row is created with
 *   member_apply_verification() — whether verifying the address is allowed to
 *                                 promote 'pending' to 'active'
 * The second one was missing. Verification promoted every non-school role
 * regardless, so switching this OFF changed nothing except the wording of one
 * email: the registrant clicked the link (or entered an OTP) and was active. The
 * approval queue only ever held School accounts, while the admin screen claimed
 * "Nobody has to click Approve" was the ON behaviour.
 */
function reg_auto_approve_enabled(): bool
{
    return (string) get_setting('registration_auto_approve', '0') === '1';
}

/**
 * registration_hourly_cap — a ceiling on NEW self-registrations per hour across
 * the whole site, independent of IP address.
 *
 * WHY THIS EXISTS. pwf_throttle() keys every bucket on (action, client IP):
 *
 *     $file = .../ substr(hash('sha256', $action.'|'.$ip), 0, 24) . '.json'
 *
 * so 'member-register' is 3/hour PER IP and 'member-register:<email>' is
 * 3/hour per (IP, address) — the address dimension is not global either. There
 * is no aggregate counter anywhere, which means total throughput is
 * 3 x (number of IPs the attacker has). A commodity rotating-proxy pool makes
 * that linear and effectively unbounded: tens of thousands of accounts an hour
 * from one script. Per-IP limits cannot fix a per-IP-limit bypass.
 *
 * With auto-verify ON every one of those is a usable account and a piece of
 * outbound mail to an address the attacker chose, so an aggregate ceiling is
 * the only control in the flow that IP rotation does not walk around.
 *
 * It is counted from data the application already keeps rather than from a new
 * counter file: no schema, no lock contention, and nothing to get out of step
 * with what actually happened. See reg_recent_signup_count() for which record,
 * and why it is not simply `SELECT COUNT(*) FROM members`.
 *
 * 0 disables it, but NOT while auto-verify is on: there it is the last wall, so
 * an explicit 0 is floored back to a working value rather than honoured.
 */
function reg_hourly_cap(): int
{
    $cap = (int) get_setting('registration_hourly_cap', '60');
    if ($cap < 0) {
        $cap = 0;
    }
    if ($cap === 0 && reg_auto_verify_enabled()) {
        return 60;
    }
    return $cap;
}

/**
 * How many accounts were SELF-REGISTERED in the window. Separate from the check
 * so the admin screen can show the same number the limiter is acting on.
 *
 * Counted from the activity log, not from `members`, and the difference matters:
 * `members` also grows when an admin adds a member by hand, imports a CSV of 300
 * students, or enrols an admission (student_link_member). Counting the table
 * would let a legitimate school intake trip a control aimed at scripted abuse
 * and pause the public form for an hour — a self-inflicted outage during exactly
 * the week the client is busiest. Only member_register_unified() writes the
 * 'member_register' action (verified: it is the single occurrence in the
 * codebase), so this counts the public form and nothing else.
 *
 * If the log is unreadable we fall back to counting the table. That is stricter,
 * not looser: over-counting pauses sign-ups, under-counting would silently raise
 * the ceiling, and for a control whose whole job is to be the last wall the
 * conservative direction is the correct one.
 */
function reg_recent_signup_count(int $seconds = 3600): int
{
    try {
        return (int) db_value(
            "SELECT COUNT(*) FROM activity_logs
              WHERE action = 'member_register' AND created_at >= (NOW() - INTERVAL :s SECOND)",
            [':s' => $seconds]
        );
    } catch (Throwable $e) {
        error_log('[reg cap] activity_logs unreadable, falling back to members: ' . $e->getMessage());
        return (int) db_value(
            'SELECT COUNT(*) FROM members WHERE created_at >= (NOW() - INTERVAL :s SECOND)',
            [':s' => $seconds]
        );
    }
}

/** Is the site-wide hourly ceiling reached? */
function reg_mint_ceiling_reached(): bool
{
    $cap = reg_hourly_cap();
    return $cap > 0 && reg_recent_signup_count() >= $cap;
}

/**
 * The address, reduced to the identity a MAILBOX actually has, for rate-limiting
 * only.
 *
 * a+1@gmail.com, a+2@gmail.com and a.b.c@gmail.com are three different strings —
 * three different values for the UNIQUE key on members.email, and three separate
 * 'member-register:<email>' buckets — but ONE inbox. Without this, the
 * per-address budget is multiplied by however many variants an attacker can
 * spell, which is unbounded.
 *
 * NOT used for the uniqueness key or for anything stored: collapsing distinct
 * addresses into one account would need a migration and would change who can
 * register, which is a different decision. This only decides which counter a
 * request is charged to, so being slightly aggressive costs a legitimate
 * plus-addressing user nothing worse than sharing their own hourly budget with
 * themselves. Dots are only meaningful-to-strip at Google, so only Google is
 * treated that way.
 */
function reg_email_rate_key(string $email): string
{
    $email = strtolower(trim($email));
    $at    = strrpos($email, '@');
    if ($at === false) {
        return $email;
    }
    $local  = substr($email, 0, $at);
    $domain = substr($email, $at + 1);

    // Sub-addressing: everything from the first '+' is a tag, not an identity.
    $plus = strpos($local, '+');
    if ($plus !== false) {
        $local = substr($local, 0, $plus);
    }
    if (in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
        $local  = str_replace('.', '', $local);
        $domain = 'gmail.com';
    }
    return ($local !== '' ? $local : '_') . '@' . $domain;
}

/**
 * members.type for a role.
 *
 * `type` predates `role` and is read by the membership/card/CSV code, whose
 * allowed values are fixed at member|donor|volunteer|intern|partner|supporter
 * (admin/members.php:27). Two self-registerable roles have no matching type, so
 * they are mapped onto the nearest VALID one rather than widening a list other
 * screens validate against:
 *   student -> member   (a student is a member of the foundation; the fact that
 *                        they are a student is carried by members.role, which is
 *                        what this whole change exists to make real)
 *   school  -> partner  (an institution, not an individual supporter)
 */
function reg_type_for_role(string $role): string
{
    $map = [
        'member'    => 'member',
        'donor'     => 'donor',
        'volunteer' => 'volunteer',
        'student'   => 'member',
        'school'    => 'partner',
    ];
    $type = $map[strtolower(trim($role))] ?? 'member';

    // Belt and braces: never write outside the list the other screens accept.
    $allowedTypes = ['member', 'donor', 'volunteer', 'intern', 'partner', 'supporter'];
    return in_array($type, $allowedTypes, true) ? $type : 'member';
}

/**
 * The profile a role actually needs — nothing more.
 *
 * A donor is never asked for a date of birth and a student is never asked for a
 * postal address. Each entry is [column => [label, required, maxlength]] and
 * ONLY these columns are read from the request for that role: a field that is
 * not in the chosen role's list cannot be written even if it is posted, which
 * is what stops the form from being a mass-assignment surface.
 *
 * Every column here already exists on `members`. Where a role wants something
 * the table cannot hold (a student's institution, a school's affiliation board)
 * it is deliberately NOT crammed into a column that means something else — that
 * needs a migration, and faking it would corrupt the admin screens that read
 * these columns by their real names.
 */
function reg_profile_fields(string $role): array
{
    $dob     = ['label' => 'Date of birth',            'type' => 'date'];
    $gender  = ['label' => 'Gender',                   'type' => 'gender'];
    $city    = ['label' => 'City / Town',              'type' => 'text', 'max' => 96];
    $state   = ['label' => 'State',                    'type' => 'text', 'max' => 96];
    $address = ['label' => 'Address',                  'type' => 'text', 'max' => 255];
    $pincode = ['label' => 'PIN code',                 'type' => 'text', 'max' => 12];

    return match (strtolower(trim($role))) {
        // Programme eligibility is age-banded, so DOB is the one thing we must have.
        'student' => [
            'dob'    => $dob + ['required' => true, 'hint' => 'Many programmes are age-banded, so we need this to match you to the right one.'],
            'gender' => $gender + ['required' => false],
            'city'   => $city + ['required' => true],
            'state'  => $state + ['required' => true],
        ],
        // Where they can serve, and what they can bring to it.
        'volunteer' => [
            'occupation' => ['label' => 'Current occupation', 'type' => 'text', 'max' => 128, 'required' => false,
                             'hint' => 'Helps us match you to work that suits your skills.'],
            'city'       => $city + ['required' => true],
            'state'      => $state + ['required' => true],
            'dob'        => $dob + ['required' => false],
            'gender'     => $gender + ['required' => false],
        ],
        // Printed on the membership ID card.
        'member' => [
            'dob'     => $dob + ['required' => true, 'hint' => 'Printed on your membership ID card.'],
            'gender'  => $gender + ['required' => false],
            'address' => $address + ['required' => false],
            'city'    => $city + ['required' => true],
            'state'   => $state + ['required' => true],
            'pincode' => $pincode + ['required' => false],
        ],
        // A postal address is a statutory requirement on an 80G receipt.
        'donor' => [
            'address' => $address + ['required' => true, 'hint' => 'Required on your 80G tax-exemption receipt.'],
            'city'    => $city + ['required' => true],
            'state'   => $state + ['required' => true],
            'pincode' => $pincode + ['required' => true],
        ],
        // `name` carries the institution; `occupation` carries the designation of
        // the human filling the form in — which is genuinely their occupation.
        'school' => [
            'occupation' => ['label' => 'Your designation at the institution', 'type' => 'text', 'max' => 128,
                             'required' => true, 'hint' => 'e.g. Principal, Coordinator, Administrator.'],
            'address'    => $address + ['required' => true],
            'city'       => $city + ['required' => true],
            'state'      => $state + ['required' => true],
            'pincode'    => $pincode + ['required' => true],
        ],
        default => [],
    };
}

/** Human copy for the account-type chooser. Keyed by the same role slugs. */
function reg_role_copy(string $role): array
{
    return match (strtolower(trim($role))) {
        'student'   => ['name' => 'Student',   'tag' => 'Learn',      'blurb' => 'Join courses, track assignments and collect certificates.'],
        'volunteer' => ['name' => 'Volunteer', 'tag' => 'Give time',  'blurb' => 'Take on campaigns and events, and log your volunteer hours.'],
        'member'    => ['name' => 'Member',    'tag' => 'Belong',     'blurb' => 'Get a digital ID card, member benefits and event access.'],
        'donor'     => ['name' => 'Donor',     'tag' => 'Give',       'blurb' => 'Track donations and download your 80G tax receipts.'],
        'school'    => ['name' => 'School / Institution', 'tag' => 'Partner',
                        'blurb' => 'Enrol your students, manage batches, staff and results.'],
        default     => ['name' => ucfirst($role), 'tag' => '', 'blurb' => ''],
    };
}

/* =============================================================================
 |  UNIFIED REGISTRATION — the one write path
 | ========================================================================== */

/**
 * Register any self-registerable identity. ONE flow for student, volunteer,
 * member, donor and school.
 *
 * Returns:
 *   ['success' => bool, 'message' => string, 'errors' => [field => msg],
 *    'member_id' => ?int, 'role' => string,
 *    'auto_verified' => bool,        // email_verified_at set without the link
 *    'needs_verification' => bool,   // person must open the link / enter the OTP
 *    'needs_approval' => bool,       // parked in the approval queue
 *    'log_in_now' => bool]           // caller may seat the session
 *
 * SECURITY PROPERTIES, all unconditional — none of them depend on the policy
 * settings, because the settings only decide how much friction a LEGITIMATE
 * registrant meets, never how much an attacker meets:
 *
 *   ROLE IS NEVER TRUSTED FROM THE REQUEST. The posted role is checked against
 *   auth_self_registerable_roles(). A posted 'super-admin', 'staff', 'editor' or
 *   'teacher' is REJECTED and logged — not quietly coerced to 'member', because
 *   a silent coercion turns a probe into a successful-looking registration and
 *   hides the attempt.
 *
 *   RATE LIMITED per IP and per (email, IP), on the app's own pwf_throttle().
 *
 *   THE ADDRESS OWNER IS ALWAYS EMAILED, including on the auto-verify path,
 *   where it is the only remaining signal that an account was created in their
 *   name.
 *
 *   THE POLICY PATH IS RECORDED so an incident can be reconstructed from
 *   activity_logs: which role, auto-verified or verified-by-token, auto-approved
 *   or queued.
 *
 *   THE PASSWORD IS HASHED AND NEVER LOGGED. Nothing in this function writes
 *   $data wholesale — every column is named explicitly.
 */
function member_register_unified(array $data): array
{
    /* ---------------------------------------------------------------------
       1. ROLE — first, and from the allowlist only.
       --------------------------------------------------------------------- */
    if (!function_exists('auth_self_registerable_roles')) {
        require_once __DIR__ . '/auth_router.php';
    }

    $roleKeyPresent = array_key_exists('role', $data);
    $rolePosted     = $roleKeyPresent ? strtolower(trim((string) $data['role'])) : '';

    if ($roleKeyPresent && $rolePosted === '') {
        /* The field WAS sent and was empty. That is not the same as a legacy
           caller that has no concept of roles, and treating them the same made
           the two indistinguishable in the audit trail: a direct POST of
           role='' got a successful 'member' registration with nothing logged.
           Refuse and say so. signup.php catches this first with the same
           message, so a real visitor never reaches here. */
        if (!function_exists('pwf_throttle') || pwf_throttle('reg-role-empty', 10, 3600)) {
            log_activity('member_register_rejected', 'members',
                'Rejected sign-up: the role field was submitted empty (no account type chosen)');
        }
        return [
            'success' => false,
            'message' => 'Please choose the type of account you want to create.',
            'errors'  => ['role' => 'Please choose a valid account type.'],
        ];
    }

    if (!$roleKeyPresent) {
        // No role in the request at all. That is a LEGACY CALLER, not an attack
        // (member_register() below routes here), so it defaults rather than
        // failing. A role that IS present but not allowlisted is a different
        // thing entirely and is refused below.
        $role = 'member';
    } elseif (auth_role_is_self_registerable($rolePosted)) {
        $role = $rolePosted;
    } else {
        /* Refused outright. The log line is throttled — not the refusal — so a
           scripted probe cannot turn the audit trail into a disk-fill, while
           every single attempt is still refused. */
        if (!function_exists('pwf_throttle') || pwf_throttle('reg-role-reject', 10, 3600)) {
            $known = function_exists('auth_role_exists') && auth_role_exists($rolePosted);
            if (function_exists('sec_log')) {
                sec_log('registration_role_rejected',
                    'Public sign-up posted role "' . mb_substr($rolePosted, 0, 40) . '" which is not self-registerable'
                    . ($known ? ' (privileged role)' : ' (unknown role)'),
                    'warning');
            }
            log_activity('member_register_rejected', 'members',
                'Rejected sign-up: role "' . mb_substr($rolePosted, 0, 40) . '" is not self-registerable');
        }
        return [
            'success' => false,
            'message' => 'That account type cannot be created here. Please choose one of the account types offered.',
            'errors'  => ['role' => 'Please choose a valid account type.'],
        ];
    }

    /* ---------------------------------------------------------------------
       2. Base fields.
       --------------------------------------------------------------------- */
    $name  = clean($data['name'] ?? '');
    $email = strtolower(clean($data['email'] ?? ''));
    $pass  = (string) ($data['password'] ?? '');

    $errors = validate($data, [
        'name'     => 'required|max:128',
        'email'    => 'required|email',
        'password' => 'required|min:8',
    ]);
    if (mb_strlen($email) > 191) {
        $errors['email'] = 'Please enter a shorter email address.';
    }

    // Confirmation is checked here too, not only in the page: a direct POST that
    // skips signup.php must not be able to set a password the person cannot
    // retype. Only enforced when the field is present, so legacy/API callers
    // that never had it are unaffected.
    if (!isset($errors['password']) && array_key_exists('password_confirm', $data)
        && (string) $data['password_confirm'] !== $pass) {
        $errors['password'] = 'The two passwords do not match.';
    }

    // The member realm enforced min:8 and nothing else while the admin realm ran
    // a configurable policy. Same rule for both now — a no-op at the shipped
    // defaults (min 8, no character classes required), and it tightens the
    // moment an admin tightens Security Center.
    if (!isset($errors['password']) && function_exists('sec_password_validate')) {
        $pwCheck = sec_password_validate($pass);
        if (!$pwCheck['ok']) {
            $errors['password'] = reset($pwCheck['errors']);
        }
    }

    /* Phone through the shared validator, which re-derives the country name and
       dial code from the ISO code server-side — a tampered hidden field cannot
       store a bogus country against a number. Signup used to drop the country
       columns entirely; forms/membership.php already did it this way. */
    $phone    = clean($data['phone'] ?? '');
    $countryCols = [];
    if (function_exists('country_capture') && isset($data['phone_country_iso'])) {
        $cap = country_capture('phone', false);
        if (!$cap['ok']) {
            $errors['phone'] = $cap['error'];
        } else {
            $phone       = $cap['phone'];
            $countryCols = $cap['columns'];
        }
    }

    /* Role-specific profile. Only the chosen role's fields are read. */
    $profile = [];
    foreach (reg_profile_fields($role) as $col => $spec) {
        $raw = clean($data[$col] ?? '');

        if ($spec['type'] === 'gender') {
            $profile[$col] = in_array($raw, ['male', 'female', 'other'], true) ? $raw : null;
        } elseif ($spec['type'] === 'date') {
            $ts = $raw !== '' ? strtotime($raw) : false;
            if ($raw !== '' && ($ts === false || $ts > time())) {
                $errors[$col] = 'Please enter a valid ' . strtolower($spec['label']) . '.';
                continue;
            }
            $profile[$col] = $ts ? date('Y-m-d', $ts) : null;
        } else {
            if (isset($spec['max']) && mb_strlen($raw) > (int) $spec['max']) {
                $raw = mb_substr($raw, 0, (int) $spec['max']);
            }
            $profile[$col] = $raw !== '' ? $raw : null;
        }

        if (!empty($spec['required']) && ($profile[$col] ?? null) === null) {
            $errors[$col] = $spec['label'] . ' is required.';
        }
    }

    if ($errors) {
        return ['success' => false, 'message' => reset($errors), 'errors' => $errors];
    }

    /* ---------------------------------------------------------------------
       3. Rate limiting.
       --------------------------------------------------------------------- */
    $autoVerify = reg_auto_verify_enabled();

    /* With auto-verify ON every success is a usable account, so the budget
       tightens and the limiter stops failing open: pwf_throttle()'s default is
       to ALLOW when it cannot maintain the counter (unwritable logs/throttle,
       full disk), which for an account mint silently means "unlimited". A
       registration form refusing during a disk problem is a far better outcome
       than an unmetered one.

       Both keys go through pwf_throttle(), which composes the action with the
       hardened client IP (sec_client_ip — X-Forwarded-For only honoured when
       trust_proxy=1), so:
         'member-register'          -> per IP, all addresses
         'member-register:<email>'  -> per (IP, address)
       The per-ADDRESS dimension on its own is structurally bounded by the
       UNIQUE key on members.email: one address can only ever hold one account,
       and the second attempt short-circuits below without writing anything. The
       keyed limiter is what stops that short-circuit being used to mail one
       person a thousand "you already have an account" notices. */
    $ipMax    = $autoVerify ? 3 : 5;
    $ipWindow = $autoVerify ? 3600 : 900;
    $failOpen = !$autoVerify;

    if (function_exists('pwf_throttle')) {
        if (!pwf_throttle('member-register', $ipMax, $ipWindow, $failOpen)) {
            return ['success' => false,
                    'message' => 'Too many sign-up attempts from this connection. Please try again in a few minutes.'];
        }
        /* Keyed on the MAILBOX, not the string: 'a+1@gmail.com' and
           'a.1+x@gmail.com' are the same inbox and must share one budget, or the
           per-address limit is defeated by spelling. */
        if (!pwf_throttle('member-register:' . reg_email_rate_key($email), 3, 3600, $failOpen)) {
            return ['success' => false,
                    'message' => 'Too many sign-up attempts for that email address. Please try again later.'];
        }
    }

    /* THE AGGREGATE CEILING. Everything above is per-IP, and a per-IP control is
       exactly what an IP-rotating script is built to walk around: 3/hour x N
       proxies is unbounded. This one counts what was actually created, sitewide,
       so rotating the source address does not buy any more of it. See
       reg_hourly_cap(). It is checked AFTER the per-IP limiter so a single
       hammering IP is still refused on its own counter first, and BEFORE the
       duplicate-address branch so that branch cannot be used to probe the cap. */
    if (reg_mint_ceiling_reached()) {
        if (function_exists('sec_log')) {
            sec_log('registration_cap_reached',
                'Sign-up refused: ' . reg_recent_signup_count() . ' registrations in the last hour has reached the '
                . 'site-wide ceiling of ' . reg_hourly_cap()
                . ' (registration_hourly_cap)' . ($autoVerify ? ' — auto-verify is ON' : ''),
                'critical');
        }
        log_activity('member_register_capped', 'members',
            'Sign-up refused by the site-wide hourly ceiling (' . reg_hourly_cap() . '/hour)');
        return ['success' => false,
                'message' => 'We are receiving an unusual number of sign-ups right now, so new registrations '
                           . 'are paused for a short while. Please try again later.'];
    }

    /* ---------------------------------------------------------------------
       4. Duplicate address — answer EXACTLY as we answer a fresh one.
       ---------------------------------------------------------------------
       Returning "an account already exists" would be a one-request,
       no-statistics account-existence oracle on a public form, against a
       members table whose addresses are the same ones the rest of the site
       publishes. The owner of the address is told what happened by email
       instead — the same shape request_password_reset() already uses. The
       returned array carries no member_id and log_in_now is false, so nothing
       downstream can act on an account this caller did not create.

       KNOWN LIMIT, and it is worth being straight about: this branch is
       indistinguishable from a fresh registration ONLY while auto-verify is off.
       With auto-verify on, a genuinely new address ends in a seated session and a
       redirect to the dashboard, while an existing address lands on this card —
       so the two are trivially distinguishable and the enumeration oracle is
       back. That is inherent to "log the registrant straight in": you cannot seat
       a session for an account the caller has not proved they own. It is listed
       as one of the costs of auto-verify on the admin Registration Policy page
       rather than papered over here. */
    /* The answer is identical whether the address is found up front or the
       UNIQUE key catches it a microsecond later, so it is written once. */
    $duplicateAnswer = static function (string $email, string $role, string $existingName, string $how): array {
        send_member_duplicate_signup_notice($email, $existingName);
        log_activity('member_register_duplicate', 'members',
            'Sign-up attempt on existing address ' . $email . ' (role requested: ' . $role . '; detected: ' . $how . ')');

        /* EVERY flag is computed from (role, policy) — the same inputs a fresh
           registration of this role uses — and none from the existing row. That
           is the whole point of this branch: signup.php renders a "what happens
           next" checklist FROM these flags, so a flag that differs from the
           fresh case is a visible difference on screen, i.e. an
           account-existence oracle. Hardcoding needs_approval => false here was
           exactly that once auto-approve began holding accounts: a fresh sign-up
           listed three steps and a duplicate listed two.

           log_in_now is the one irreducible difference and it only bites while
           auto-verify is on — see the note above. */
        $needsAdmin = auth_role_requires_admin_approval($role);
        $autoVerify = reg_auto_verify_enabled();

        return [
            'success'            => true,
            'message'            => reg_pending_message($role, !$autoVerify),
            'role'               => $role,
            'auto_verified'      => $autoVerify,
            'needs_verification' => !$autoVerify,
            'needs_approval'     => $needsAdmin || !reg_auto_approve_enabled(),
            'log_in_now'         => false,
        ];
    };

    $existing = find_by('members', 'email', $email);
    if ($existing) {
        return $duplicateAnswer($email, $role, (string) ($existing['name'] ?? ''), 'pre-check');
    }

    /* ---------------------------------------------------------------------
       5. Decide the policy path.
       --------------------------------------------------------------------- */
    $needsAdminApproval = auth_role_requires_admin_approval($role);   // school: always
    $approvedNow        = !$needsAdminApproval && reg_auto_approve_enabled();
    $status             = $approvedNow ? 'active' : 'pending';
    $verifiedAt         = $autoVerify ? date('Y-m-d H:i:s') : null;

    /* ---------------------------------------------------------------------
       6. Insert. Every column named explicitly — $data is never spread.
       ---------------------------------------------------------------------
       `document` used to be written straight from $data with no validation and
       no form field feeding it, i.e. an unvalidated public write into a column
       the admin screens display. It is gone; documents belong to an
       authenticated, validated upload path. */
    $row = [
        'name'              => $name,
        'email'             => $email,
        'password'          => password_hash($pass, PASSWORD_DEFAULT),
        'phone'             => $phone !== '' ? $phone : null,
        'role'              => $role,                       // <- the column this change exists for
        'type'              => reg_type_for_role($role),
        'status'            => $status,
        'email_verified_at' => $verifiedAt,
        'membership_status' => 'none',
        'join_date'         => date('Y-m-d'),
    ] + $countryCols + $profile;

    /* find_by() above and this insert are not atomic, and two simultaneous
       registrations of the SAME address both pass the check. The UNIQUE key on
       members.email is what actually stops the second row — but PDO runs in
       exception mode and db_insert() does not catch, so the loser of the race
       used to raise an uncaught PDOException: a 500 page for a person who did
       nothing wrong, and a stack trace in the log instead of a diagnosis. The
       row is still never duplicated; only the response was bad. Catch the
       constraint and give exactly the answer the pre-check gives. */
    try {
        $id = db_insert('members', $row);
    } catch (PDOException $e) {
        $isDuplicate = ($e->errorInfo[0] ?? '') === '23000'
            || (int) ($e->errorInfo[1] ?? 0) === 1062
            || str_contains($e->getMessage(), 'uq_members_email');
        if (!$isDuplicate) {
            throw $e;
        }
        $raced = find_by('members', 'email', $email);
        return $duplicateAnswer($email, $role, (string) ($raced['name'] ?? ''), 'unique-key race');
    }

    // member_code + qr_token: signup never generated them, so a self-registered
    // member had no membership ID until an admin opened their record.
    if (function_exists('member_ensure_identity')) {
        try {
            member_ensure_identity(array_merge(['id' => $id], $row));
        } catch (Throwable $e) {
            error_log('[member_register identity] ' . $e->getMessage());
        }
    }

    /* ---------------------------------------------------------------------
       7. Email the address owner — on BOTH paths.
       ---------------------------------------------------------------------
       On the auto-verify path there is no link to click, so this is not a
       verification mail: it is the notice that tells the real owner an account
       now exists on their address and how to shut it down. It is the cheapest
       mitigation available for auto-verification and it costs the legitimate
       user nothing. */
    if ($autoVerify) {
        send_member_autoverified_notice($email, $name, $role, !$approvedNow);
    } else {
        send_member_verification($email, $id, $name);
    }

    // Attribute the signup to a captured referral code, if any.
    if (function_exists('ref_record')) {
        ref_record('signup', ['name' => $name, 'email' => $email]);
    }

    /* ---------------------------------------------------------------------
       8. Audit. Which policy path was taken, so an incident is reconstructable.
          log_activity() records the IP and user agent on the row itself; note
          that user_id is NULL for every member-realm event (there is no member
          attribution column on activity_logs), which is why the email is in the
          description. No password material appears anywhere here.
       --------------------------------------------------------------------- */
    $verifyPath  = $autoVerify ? 'auto-verified' : 'verify-by-token';
    $approvePath = $approvedNow ? 'auto-approved'
                                : ($needsAdminApproval ? 'admin-approval-required' : 'pending-approval');
    log_activity('member_register', 'members',
        'New member #' . $id . ' ' . $email . ' role=' . $role
        . ' policy=' . $verifyPath . '/' . $approvePath);

    if (function_exists('member_audit_log')) {
        member_audit_log($id, 'registered', [
            'role'   => [null, $role],
            'status' => [null, $status],
            'verify' => [null, $verifyPath],
        ], 'Self-registered via the public sign-up form');
    }

    // An auto-verified registration is a security-relevant event even when it is
    // legitimate: it is an account that exists without anyone proving they own
    // the address. sec_log() uses the non-spoofable client IP.
    if ($autoVerify && function_exists('sec_log')) {
        sec_log('registration_auto_verified',
            'Member #' . $id . ' (' . $email . ', role ' . $role . ') was marked email-verified by policy, '
            . 'without confirming the address',
            'warning');
    }

    /* ---------------------------------------------------------------------
       9. What the caller should do next.
       --------------------------------------------------------------------- */
    $logInNow = $autoVerify && $status === 'active';

    return [
        'success'            => true,
        'message'            => $logInNow
            ? 'Welcome aboard, ' . $name . '! Your account is ready.'
            : reg_pending_message($role, !$autoVerify),
        'member_id'          => $id,
        'role'               => $role,
        'auto_verified'      => $autoVerify,
        'needs_verification' => !$autoVerify,
        'needs_approval'     => $status === 'pending',
        'log_in_now'         => $logInNow,
    ];
}

/**
 * What happens next, in the registrant's words.
 *
 * Whether approval is still coming is DERIVED here rather than passed in, for
 * one specific reason: this function is also what answers a sign-up on an
 * address that already has an account, and that answer has to be
 * byte-identical to the answer a fresh sign-up would get or the form becomes an
 * account-existence oracle. Deriving it from (role, policy) — the only two
 * things a fresh registration of the same role would use — keeps the two in step
 * automatically instead of relying on two call sites passing matching flags.
 *
 * It used to promise that confirming the address "activates your account",
 * which stopped being true the moment registration_auto_approve started
 * governing the promotion: a member could confirm and still be refused at login.
 */
function reg_pending_message(string $role, bool $needsVerification): string
{
    $needsApproval = auth_role_requires_admin_approval($role) || !reg_auto_approve_enabled();

    if ($needsVerification && $needsApproval) {
        return 'Account created. Please check your email and confirm your address. Your account then goes to '
             . 'our team for approval — we will email you as soon as it is reviewed.';
    }
    if ($needsVerification) {
        return 'Account created. Please check your email and confirm your address to activate your account.';
    }
    if (auth_role_requires_admin_approval($role)) {
        return 'Account created. A ' . strtolower(reg_role_copy($role)['name'])
             . ' account is reviewed by our team before it opens — we will email you as soon as it is approved.';
    }
    return 'Account created. Your account is awaiting approval; we will email you as soon as it is reviewed.';
}

/* =============================================================================
 |  SOCIAL SIGN-IN — the same policy, the same gate
 | =============================================================================
 |  includes/oauth.php:oauth_login_member() is a THIRD way to create and admit a
 |  members row, and it agreed with neither of the other two:
 |
 |    - it wrote no `role` at all, so every Google/Facebook account landed on the
 |      DB default and no social user could ever be a student/volunteer/donor;
 |    - it hardcoded status='active' and consulted neither
 |      reg_auto_approve_enabled() nor auth_role_requires_admin_approval(), so
 |      the entire registration policy was bypassable by clicking "Continue with
 |      Google";
 |    - it gated login on `status !== 'active'` alone, never on
 |      email_verified_at, so a row the password path refuses forever
 |      (status=active + email_verified_at NULL) was admitted forever — and
 |      login.php went on offering that same person a "verify your email" link;
 |    - the link branch wrote only oauth_provider/oauth_uid, so it never
 |      backfilled the verification it had just proved.
 |
 |  These three functions are the whole fix. They live here, next to the policy
 |  they have to obey, so there is one description of "how a members row is
 |  created and admitted" rather than two that drift. oauth.php calls them; see
 |  the reported edits.
 | ========================================================================== */

/**
 * The column set for a member created from a social profile.
 *
 * Provider verification IS verification — the provider asserts the person
 * controls the address, which is exactly what the emailed link proves — so
 * email_verified_at comes from the provider and registration_auto_verify does
 * not apply. status DOES follow the policy, identically to the form path.
 *
 * $p is the normalised provider profile (uid/email/name/avatar/verified). It
 * carries no role field and never will: there is nothing on the provider side
 * that could authorise one, so social accounts are always the lowest-privilege
 * 'member' and are upgraded by an admin like any other.
 */
function member_oauth_new_row(string $provider, array $p): array
{
    $role       = 'member';
    $verified   = !empty($p['verified']);
    $needsHuman = auth_role_requires_admin_approval($role);
    $approved   = !$needsHuman && reg_auto_approve_enabled();

    return [
        'name'              => ($p['name'] ?? '') !== '' ? $p['name'] : 'Member',
        'email'             => strtolower(trim((string) ($p['email'] ?? ''))),
        // No usable password: entry is via the provider until they set one
        // through the ordinary reset-password flow.
        'password'          => password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT),
        'oauth_provider'    => $provider,
        'oauth_uid'         => (string) ($p['uid'] ?? ''),
        'avatar_url'        => ($p['avatar'] ?? '') !== '' ? $p['avatar'] : null,
        'role'              => $role,
        'type'              => reg_type_for_role($role),
        'status'            => $approved ? 'active' : 'pending',
        'email_verified_at' => $verified ? date('Y-m-d H:i:s') : null,
        'membership_status' => 'none',
        'join_date'         => date('Y-m-d'),
    ];
}

/**
 * Link a provider to an existing members row, and backfill the verification the
 * link just proved.
 *
 * oauth.php only reaches the link branch for a provider-VERIFIED address, so an
 * unverified row being linked has, at that moment, been proved. Writing the
 * timestamp is what stops the two realms disagreeing forever: without it a row
 * created while auto-approve was on and auto-verify off (status=active,
 * email_verified_at NULL) can sign in with Google but never with its password.
 *
 * Returns the refreshed row so the caller gates on what is now true.
 */
function member_oauth_link(array $member, string $provider, array $p): array
{
    $update = [
        'oauth_provider' => $provider,
        'oauth_uid'      => (string) ($p['uid'] ?? ''),
    ];
    if (empty($member['email_verified_at']) && !empty($p['verified'])) {
        $update['email_verified_at'] = date('Y-m-d H:i:s');
        log_activity('member_verified', 'members',
            'Verified ' . (string) ($member['email'] ?? '') . ' via ' . $provider
            . ' (provider-confirmed address, backfilled on account link)');
    }
    db_update('members', $update, 'id = :id', [':id' => (int) $member['id']]);
    return array_merge($member, $update);
}

/**
 * May this member row be admitted? The SAME gate the password path uses.
 *
 * Returns ['ok' => bool, 'code' => string, 'message' => string].
 */
function member_oauth_gate(array $member): array
{
    return function_exists('auth_status_gate')
        ? auth_status_gate($member, 'members')
        : ['ok' => ($member['status'] ?? 'active') === 'active' && !empty($member['email_verified_at']),
           'code' => 'legacy', 'message' => 'That account cannot sign in yet.'];
}

/**
 * Register a new member — BACK-COMPATIBLE ENTRY POINT.
 *
 * Kept because signup.php and the docs call it with the raw request array. It
 * now delegates to member_register_unified(), so the role allowlist, rate
 * limits, policy layer and audit trail apply to every caller including old
 * ones. Absent 'role' means 'member', exactly as before; a PRESENT but
 * disallowed role is refused rather than coerced.
 *
 * Two behaviour changes worth knowing about, both closing mass-assignment:
 *   - 'type' is no longer taken from the request; it is derived from the role.
 *     A crafted POST could previously set members.type on a form that renders
 *     no such field.
 *   - 'document' is no longer written from the request at all.
 */
function member_register(array $data): array
{
    return member_register_unified($data);
}

/**
 * Authenticate a member. Returns [success, message].
 *
 * The usable-account decision is delegated to auth_status_gate() so the members
 * realm and anything else that authenticates share one definition, instead of
 * this function carrying its own copy that drifts.
 */
function member_login(string $email, string $password): array
{
    $email = strtolower(trim($email));

    if (is_locked_out($email)) {
        return ['success' => false, 'message' => 'Too many attempts. Please try again later.'];
    }

    $member = db_row('SELECT * FROM members WHERE email = :e LIMIT 1', [':e' => $email]);
    // The no-row branch verifies against a dummy hash of the same cost, so an
    // unregistered address takes as long as a registered one. Without it the
    // response time alone told an attacker which addresses have accounts:
    // 63-96 ms with a row against 2.3-2.8 ms without, no overlap.
    // pwf_equalise_login_time() lives in auth.php, which bootstrap loads first.
    $ok = $member ? password_verify($password, $member['password'])
                  : pwf_equalise_login_time($password);
    if (!$ok) {
        record_login_attempt($email, false);
        return ['success' => false, 'message' => 'Invalid email or password.'];
    }

    $gate = function_exists('auth_status_gate')
        ? auth_status_gate($member, 'members')
        : ['ok' => $member['status'] !== 'suspended' && !empty($member['email_verified_at']),
           'code' => empty($member['email_verified_at']) ? 'email_unverified' : 'suspended',
           'message' => 'This account cannot sign in yet.'];

    if (!$gate['ok']) {
        $out = ['success' => false, 'message' => $gate['message'], 'code' => $gate['code']];
        // login.php turns this into the "Verify now" link, so the shape matters.
        if ($gate['code'] === 'email_unverified') {
            $out['unverified'] = true;
            $out['email']      = $email;
        }
        if ($gate['code'] === 'awaiting_approval') {
            $out['awaiting_approval'] = true;
        }
        return $out;
    }

    member_set_session($member);
    db_update('members',
        ['last_login_at' => date('Y-m-d H:i:s'), 'last_login_ip' => client_ip()],
        'id = :id', [':id' => $member['id']]);
    record_login_attempt($email, true);

    return ['success' => true, 'message' => 'Welcome back, ' . $member['name'] . '!', 'member_id' => (int) $member['id']];
}

function member_logout(): void
{
    if (function_exists('remember_forget') && is_member_logged_in()) {
        remember_forget('members', 'pwf_member_remember', (int) ($_SESSION['member']['id'] ?? 0));
    }
    unset($_SESSION['member']);
}

/* -------------------------------------------------------------- TOKENS */

/**
 * Create a member token. Returns the RAW value to email (token string, or a
 * 6-digit code for OTP). Only its hash is stored.
 */
function create_member_token(string $email, string $type, ?int $memberId = null, int $ttlMinutes = 60): string
{
    // Invalidate previous unused tokens of the same type.
    db_query('UPDATE member_tokens SET used_at = NOW() WHERE email = :e AND type = :t AND used_at IS NULL',
        [':e' => $email, ':t' => $type]);

    $raw = ($type === 'otp')
        ? str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT)
        : bin2hex(random_bytes(32));

    db_insert('member_tokens', [
        'member_id'  => $memberId,
        'email'      => $email,
        'type'       => $type,
        'token'      => hash('sha256', $raw),
        'expires_at' => date('Y-m-d H:i:s', time() + $ttlMinutes * 60),
    ]);
    return $raw;
}

/**
 * Verify (and consume) a member token. Returns the token row or null.
 */
function verify_member_token(string $email, string $type, string $rawValue): ?array
{
    $row = db_row(
        'SELECT * FROM member_tokens
         WHERE email = :e AND type = :t AND token = :tok AND used_at IS NULL AND expires_at >= NOW()
         ORDER BY id DESC LIMIT 1',
        [':e' => strtolower(trim($email)), ':t' => $type, ':tok' => hash('sha256', trim($rawValue))]
    );
    if (!$row) {
        return null;
    }
    db_update('member_tokens', ['used_at' => date('Y-m-d H:i:s')], 'id = :id', [':id' => $row['id']]);
    return $row;
}

/* -------------------------------------------------------------- EMAIL SENDERS */

function send_member_verification(string $email, ?int $memberId = null, string $name = ''): void
{
    $token = create_member_token($email, 'verify_email', $memberId, 1440);
    $link  = abs_url('verify-email?email=' . urlencode($email) . '&token=' . $token);
    try {
        send_mail($email, 'Verify your email address',
            '<p>Hi ' . e($name) . ',</p><p>Welcome to ' . e(get_setting('site_name', SITE_NAME)) . '! '
            . 'Please confirm your email address by clicking the button below.</p>'
            . '<p><a href="' . e($link) . '" style="display:inline-block;padding:12px 22px;background:#0B4E3D;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;">Verify Email</a></p>'
            . '<p>Or copy this link:<br>' . e($link) . '</p>');
    } catch (Throwable $e) { error_log('[member verify mail] ' . $e->getMessage()); }
}

/**
 * Told to the address owner — and only to them — when somebody submits the
 * signup form for an address that already has an account. It is what lets
 * member_register() answer a duplicate identically to a fresh registration
 * without leaving a real person confused about the email that never arrived.
 * Carries no token and grants nothing: both links are ordinary public pages.
 */
function send_member_duplicate_signup_notice(string $email, string $name = ''): void
{
    $site  = get_setting('site_name', SITE_NAME);
    $login = abs_url('login');
    $reset = abs_url('forgot-password');
    try {
        send_mail($email, 'You already have an account',
            '<p>Hi ' . e($name) . ',</p>'
            . '<p>Someone just used your email address on the ' . e($site) . ' sign-up form. '
            . 'You already have an account with us, so we did not create a second one.</p>'
            . '<p><a href="' . e($login) . '" style="display:inline-block;padding:12px 22px;background:#0B4E3D;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;">Sign in</a></p>'
            . '<p>Forgotten your password? Reset it here:<br>' . e($reset) . '</p>'
            . '<p>If this was not you, no action is needed — nothing about your account has changed.</p>');
    } catch (Throwable $e) { error_log('[member duplicate signup mail] ' . $e->getMessage()); }
}

/**
 * Sent INSTEAD of the verification link when registration_auto_verify is on.
 *
 * With auto-verify there is nothing for the person to click — the account is
 * already usable. That removes the only signal the real owner of the address
 * would otherwise get, so this mail exists specifically to put it back: it says
 * an account was created on their address, what it can do, and how to stop it.
 * It is the single cheapest mitigation for auto-verification and it costs a
 * legitimate registrant nothing.
 *
 * Carries no token and grants nothing: every link is an ordinary public page.
 */
function send_member_autoverified_notice(string $email, string $name, string $role, bool $pendingApproval): void
{
    $site  = get_setting('site_name', SITE_NAME);
    $login = abs_url('login');
    $reset = abs_url('forgot-password');
    $help  = abs_url('contact');
    $what  = function_exists('reg_role_copy') ? strtolower(reg_role_copy($role)['name']) : $role;

    $state = $pendingApproval
        ? '<p>Your account is <strong>awaiting approval</strong> by our team. We will email you again once it is reviewed.</p>'
        : '<p>Your account is <strong>active</strong> — you can sign in straight away.</p>';

    try {
        send_mail($email, 'Your ' . $site . ' account has been created',
            '<p>Hi ' . e($name) . ',</p>'
            . '<p>A <strong>' . e($what) . '</strong> account has just been created on ' . e($site)
            . ' using this email address.</p>'
            . $state
            . '<p><a href="' . e($login) . '" style="display:inline-block;padding:12px 22px;background:#0B4E3D;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;">Sign in</a></p>'
            . '<p style="margin-top:22px;padding:12px 14px;background:#FEF3C7;border-radius:8px;">'
            . '<strong>Did you not create this account?</strong><br>'
            . 'Reset the password to lock it immediately — ' . e($reset) . ' — and then tell us at '
            . e($help) . ' so we can remove it.</p>');
    } catch (Throwable $e) {
        error_log('[member autoverified mail] ' . $e->getMessage());
    }
}

function send_member_otp(string $email): string
{
    $code = create_member_token($email, 'otp', null, 15);
    try {
        send_mail($email, 'Your verification code',
            '<p>Your one-time verification code is:</p>'
            . '<p style="font-size:32px;font-weight:800;letter-spacing:8px;color:#0B4E3D;">' . e($code) . '</p>'
            . '<p>This code expires in 15 minutes.</p>');
    } catch (Throwable $e) { error_log('[member otp mail] ' . $e->getMessage()); }
    return $code;
}

/**
 * Sent when an admin approves a pending account (admin/members.php, _do=approve).
 *
 * Approval and verification are separate facts, so the copy is too: if the
 * address is still unverified the mail says the account is open but the person
 * has one thing left to do, rather than inviting them to sign in and hit a
 * refusal they cannot explain.
 */
function send_member_approved_notice(string $email, string $name, bool $stillUnverified): void
{
    $site  = get_setting('site_name', SITE_NAME);
    $login = abs_url('login');
    $verify = abs_url('verify-otp?email=' . urlencode($email));
    try {
        send_mail($email, 'Your ' . $site . ' account has been approved',
            '<p>Hi ' . e($name) . ',</p>'
            . '<p>Good news — your ' . e($site) . ' account has been approved.</p>'
            . ($stillUnverified
                ? '<p>One step left: confirm this email address, then you can sign in.</p>'
                  . '<p><a href="' . e($verify) . '" style="display:inline-block;padding:12px 22px;background:#0B4E3D;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;">Verify my email</a></p>'
                : '<p><a href="' . e($login) . '" style="display:inline-block;padding:12px 22px;background:#0B4E3D;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;">Sign in</a></p>'));
    } catch (Throwable $e) {
        error_log('[member approved mail] ' . $e->getMessage());
    }
}

function request_password_reset(string $email): bool
{
    $email  = strtolower(trim($email));
    $member = find_by('members', 'email', $email);
    if (!$member) {
        return false; // caller shows a generic message either way (no user enumeration)
    }
    $token = create_member_token($email, 'reset_password', (int) $member['id'], 30);
    $link  = abs_url('reset-password?email=' . urlencode($email) . '&token=' . $token);
    try {
        send_mail($email, 'Reset your password',
            '<p>We received a request to reset your password.</p>'
            . '<p><a href="' . e($link) . '" style="display:inline-block;padding:12px 22px;background:#0B4E3D;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;">Reset Password</a></p>'
            . '<p>If you did not request this, you can safely ignore this email. The link expires in 30 minutes.</p>');
    } catch (Throwable $e) { error_log('[member reset mail] ' . $e->getMessage()); }
    return true;
}

/* -------------------------------------------------------------- VERIFY/RESET */

/**
 * Apply a successful email/OTP verification to the account.
 *
 * Verification proves ONE thing: the person controls the address. Both entry
 * points used to write `status = 'active'` unconditionally alongside
 * email_verified_at, by email, which conflated two different facts and had two
 * consequences:
 *
 *   1. A SUSPENDED member who clicked an old verification link was silently
 *      un-suspended. Suspension is an administrative decision and a stale token
 *      must not undo it.
 *   2. There was no way to hold an account for review, because verifying always
 *      activated — which is why members.status='pending' had never meant
 *      anything.
 *
 * So: email_verified_at is always set; 'pending' is promoted to 'active' only
 * when the POLICY says a human is not needed, and 'suspended' is never touched.
 *
 * "The policy says a human is not needed" means BOTH of:
 *   - the role is not approval-restricted (school always is), AND
 *   - registration_auto_approve is ON.
 *
 * The second condition was missing, and its absence made the whole setting
 * decorative: with auto-approve OFF a member registered as 'pending', clicked
 * the link, and was promoted to 'active' anyway. Only School ever reached the
 * queue, so "waiting for approval" was never a state the admin could actually
 * choose — while the Registration Policy screen advertised auto-approve as the
 * switch that skips it. A setting that does nothing is worse than no setting:
 * the admin believes accounts are being reviewed.
 *
 * Consequence worth being explicit about: with both switches at their shipped
 * OFF defaults, a new member now verifies their address and then waits for an
 * admin to approve them. That is what the label says it does, the queue is
 * visible on admin/registration-settings.php and admin/members.php, and the
 * approve action emails them (send_member_approved_notice). A client who wants
 * the frictionless flow turns auto-approve on, which is the deliberate act the
 * defaults exist to require.
 *
 * Returns the message describing what actually happened.
 */
function member_apply_verification(string $email): string
{
    $email  = strtolower(trim($email));
    $member = find_by('members', 'email', $email);

    // No such row: the token was valid for an address with no account (possible
    // for OTPs, which are created with member_id = NULL). Nothing to update, and
    // nothing to disclose.
    if (!$member) {
        return 'Your email has been verified. You can now sign in.';
    }

    $update = ['email_verified_at' => date('Y-m-d H:i:s')];
    $status = strtolower((string) $member['status']);
    $role   = strtolower(trim((string) ($member['role'] ?? ''))) ?: 'member';

    if (!function_exists('auth_role_requires_admin_approval')) {
        require_once __DIR__ . '/auth_router.php';
    }
    // 'admin' approval is a property of the ROLE and cannot be switched off;
    // auto-approve is the POLICY and applies to every other role. Both have to
    // permit it before verification is allowed to empty the queue.
    $roleNeedsHuman = auth_role_requires_admin_approval($role);
    $policyApproves = reg_auto_approve_enabled();
    $promote        = $status === 'pending' && !$roleNeedsHuman && $policyApproves;

    if ($promote) {
        $update['status'] = 'active';
    }

    db_update('members', $update, 'id = :id', [':id' => (int) $member['id']]);

    if (function_exists('member_audit_log')) {
        member_audit_log((int) $member['id'], 'verified',
            $promote ? ['status' => [$status, 'active']] : [],
            'Email address verified');
    }

    /* Say WHY the account was or was not promoted. "status=pending" on its own
       does not tell an incident review whether a human is required by the role
       or by the current policy — and those have different fixes. */
    $why = '';
    if ($status === 'pending' && !$promote) {
        $why = $roleNeedsHuman
            ? ' (held: the ' . $role . ' role always requires admin approval)'
            : ' (held: registration_auto_approve is off)';
    }
    log_activity('member_verified', 'members',
        'Verified ' . $email . ' role=' . $role
        . ' status=' . ($update['status'] ?? $status) . $why);

    if ($status === 'suspended') {
        return 'Your email has been verified, but this account is suspended. Please contact us.';
    }
    if ($status === 'pending' && !$promote) {
        // Driven by the RESULTING status, not by which condition held it back:
        // the account holder does not need to know which, and telling them
        // "you can now sign in" when auth_status_gate() will refuse them is the
        // one thing this message must never do.
        return 'Your email has been verified. Your account is now awaiting approval by our team — '
             . 'we will email you as soon as it is reviewed.';
    }
    return 'Your email has been verified. You can now sign in.';
}

function verify_member_email(string $email, string $token): array
{
    if (!verify_member_token($email, 'verify_email', $token)) {
        return ['success' => false, 'message' => 'This verification link is invalid or has expired.'];
    }
    return ['success' => true, 'message' => member_apply_verification($email)];
}

function verify_member_otp(string $email, string $code): array
{
    // A 6-digit OTP is only 10^6 wide, so unlimited guessing is a brute force.
    // Cap wrong attempts per email+IP within the code's lifetime; once the cap is
    // hit, burn every outstanding code for this email so the attacker must
    // trigger a fresh send (itself throttled to 3/10min) and start over.
    $key = 'otp-verify-' . strtolower(trim($email));
    if (function_exists('pwf_throttle') && !pwf_throttle($key, 5, 900)) {
        db_query('UPDATE member_tokens SET used_at = NOW()
                  WHERE email = :e AND type = :t AND used_at IS NULL',
            [':e' => strtolower(trim($email)), ':t' => 'otp']);
        return ['success' => false, 'message' => 'Too many incorrect codes. Please request a new one.'];
    }

    if (!verify_member_token($email, 'otp', $code)) {
        return ['success' => false, 'message' => 'The code is incorrect or has expired.'];
    }
    return ['success' => true, 'message' => member_apply_verification($email)];
}

function reset_member_password(string $email, string $token, string $newPassword): array
{
    if (mb_strlen($newPassword) < 8) {
        return ['success' => false, 'message' => 'Password must be at least 8 characters.'];
    }
    if (!verify_member_token($email, 'reset_password', $token)) {
        return ['success' => false, 'message' => 'This reset link is invalid or has expired.'];
    }
    db_update('members', ['password' => password_hash($newPassword, PASSWORD_DEFAULT)],
        'email = :e', [':e' => strtolower(trim($email))]);
    return ['success' => true, 'message' => 'Your password has been reset. Please sign in.'];
}
