<?php
/**
 * =============================================================================
 *  Kanya Daan Project — shared vocabulary
 * =============================================================================
 *  One source of truth for the option lists, document checklist and workflow
 *  statuses used by the three places that touch this module:
 *
 *      kanyadaan-apply.php                  public application form
 *      forms/kanyadaan-apply.php            submission handler
 *      admin/kanyadaan-applications.php     verification -> approval -> distribution
 *
 *  Legal ages live here too. This project assists marriages, so refusing to
 *  record one below the statutory age is a compliance control, not a
 *  preference — see kd_min_age() and the check in the handler.
 * =============================================================================
 */

declare(strict_types=1);

/** Who is filling the form in, relative to the bride. */
function kd_relationships(): array
{
    return [
        'bride'    => 'The bride herself',
        'father'   => 'Father',
        'mother'   => 'Mother',
        'guardian' => 'Guardian',
        'other'    => 'Other',
    ];
}

/**
 * Statutory minimum age of marriage in India — bride 18, groom 21
 * (Prohibition of Child Marriage Act, 2006). The handler rejects anything
 * below these outright rather than storing it for someone to catch later.
 */
function kd_min_age(string $who): int
{
    return $who === 'groom' ? 21 : 18;
}

/** Construction of the family home — a standard poverty indicator. */
function kd_house_types(): array
{
    return ['kutcha' => 'Kutcha', 'semi_pucca' => 'Semi-Pucca', 'pucca' => 'Pucca'];
}

/** Section 12 — the assistance an applicant may request. */
function kd_support_items(): array
{
    return [
        'Clothing', 'Bed / Mattress', 'Almirah', 'Sewing Machine', 'Utensils',
        'Kitchen Equipment', 'Electrical Appliance', 'Household Essentials',
        'Livelihood Kit', 'Skill Development Support', 'Limited Financial Assistance',
    ];
}

/** Columns captured for each row of the family table (section 10). */
function kd_family_fields(): array
{
    return [
        'name'         => ['label' => 'Family member', 'max' => 128],
        'age'          => ['label' => 'Age',           'max' => 3],
        'relationship' => ['label' => 'Relationship',  'max' => 64],
        'occupation'   => ['label' => 'Occupation',    'max' => 96],
        'income'       => ['label' => 'Monthly income','max' => 12],
    ];
}

/** Upload folder for Kanya Daan documents (denied to Apache; see its .htaccess). */
const KD_DOC_DIR = 'kanyadaan-docs';

/**
 * Section 13 — the document checklist.
 *
 * Only the two that establish identity and legal age are required at submission;
 * everything else can follow at verification, so a family without a scanner is
 * not shut out of applying.
 */
function kd_documents(): array
{
    return [
        'bride_id'        => ['label' => "Bride's Identity Proof",       'icon' => 'id-card',        'required' => true,  'image' => false],
        'bride_age'       => ['label' => "Bride's Age Proof",            'icon' => 'calendar-check', 'required' => true,  'image' => false],
        'groom_age'       => ['label' => "Groom's Age Proof",            'icon' => 'calendar-check', 'required' => false, 'image' => false],
        'residence'       => ['label' => 'Residence Proof',              'icon' => 'home',           'required' => false, 'image' => false],
        'income'          => ['label' => 'Income Certificate / Declaration', 'icon' => 'receipt-indian-rupee', 'required' => false, 'image' => false],
        'bank'            => ['label' => 'Bank Account Details',         'icon' => 'landmark',       'required' => false, 'image' => false],
        'family_photo'    => ['label' => 'Family Photograph',            'icon' => 'users',          'required' => false, 'image' => true],
        'marriage_doc'    => ['label' => 'Marriage Document / Invitation', 'icon' => 'mail-open',    'required' => false, 'image' => false, 'note' => 'where applicable'],
        'applicant_id'    => ['label' => 'Guardian / Applicant ID',      'icon' => 'user-round',     'required' => false, 'image' => false],
        'other'           => ['label' => 'Other Supporting Document',    'icon' => 'paperclip',      'required' => false, 'image' => false],
    ];
}

/** Extensions accepted for a slot. Scans are usually photographed, so images count. */
function kd_doc_allowed(bool $imageOnly): string
{
    return $imageOnly ? 'jpg,jpeg,png,webp' : 'pdf,jpg,jpeg,png,webp,doc,docx';
}

/**
 * The case workflow, in order, with the admin pill colour for each state.
 * 'step' drives the progress indicator on the detail screen; rejected and
 * waitlisted sit outside the ladder and carry step 0.
 */
function kd_statuses(): array
{
    return [
        'new'         => ['label' => 'New',                 'pill' => 'pill-blue',  'step' => 1],
        'verifying'   => ['label' => 'Pending Verification','pill' => 'pill-cyan',  'step' => 2],
        'verified'    => ['label' => 'Verified',            'pill' => 'pill-violet','step' => 3],
        'approved'    => ['label' => 'Approved',            'pill' => 'pill-amber', 'step' => 4],
        'distributed' => ['label' => 'Distributed',         'pill' => 'pill-green', 'step' => 5],
        'waitlisted'  => ['label' => 'Waitlisted',          'pill' => 'pill-gray',  'step' => 0],
        'rejected'    => ['label' => 'Rejected',            'pill' => 'pill-red',   'step' => 0],
    ];
}

/** Field-verification states (section 6 of the project brief). */
function kd_verification_states(): array
{
    return ['pending' => 'Pending', 'scheduled' => 'Scheduled', 'completed' => 'Completed'];
}

/** Decode a JSON column written by the handler; always returns an array. */
function kd_json(?string $raw): array
{
    if (empty($raw)) {
        return [];
    }
    $out = json_decode($raw, true);
    return is_array($out) ? $out : [];
}

/**
 * Reveal a stored ID / bank number for the admin detail view.
 * Rows written while APP_KEY was configured hold AES-256-GCM ciphertext; rows
 * written without it hold the value as typed. A ciphertext that no longer
 * decrypts returns null rather than a wall of base64.
 */
function kd_reveal(?string $stored): ?string
{
    if ($stored === null || $stored === '') {
        return null;
    }
    return sec_is_encrypted($stored) ? sec_decrypt($stored) : $stored;
}

/**
 * The compliance statement. Shown on the scheme page, on the form and in the
 * confirmation email — one string so all three can never drift apart.
 */
function kd_policy_statement(): string
{
    return 'Kanya Daan Project is a voluntary social-welfare initiative and is not a dowry scheme. '
         . SITE_NAME . ' does not support, facilitate or encourage dowry demands, child marriage or any '
         . 'unlawful marriage practice. Assistance is subject to eligibility, verification, available '
         . 'resources and applicable law.';
}
