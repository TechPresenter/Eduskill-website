<?php
/**
 * =============================================================================
 *  Community Coordinator applications — shared vocabulary
 * =============================================================================
 *  One source of truth for the option lists, document checklist and workflow
 *  statuses used by the three places that touch this module:
 *
 *      coordinator-apply.php                 public application form
 *      forms/coordinator-apply.php           submission handler
 *      admin/coordinator-applications.php    admin inbox + office-use panel
 *
 *  Keeping them here means a new focus area or document slot is added once and
 *  the form, the validator and the admin detail view all pick it up — the
 *  checklist in particular has to agree across all three, or an upload lands in
 *  a slot the admin screen never renders.
 * =============================================================================
 */

declare(strict_types=1);

/** The three coordinator levels, with the responsibilities from the brief. */
function coord_positions(): array
{
    return [
        'panchayat' => [
            'label'  => 'Panchayat Coordinator',
            'icon'   => 'home',
            'scope'  => 'Village & Panchayat level',
            'duties' => [
                'Coordinate activities at Panchayat/village level.',
                'Identify eligible beneficiaries and community needs.',
                'Conduct field visits and awareness activities.',
                'Maintain beneficiary and activity records.',
                'Coordinate with volunteers and local stakeholders.',
                'Submit regular reports to the Block Coordinator.',
            ],
        ],
        'block' => [
            'label'  => 'Block Coordinator',
            'icon'   => 'layers',
            'scope'  => 'Block level',
            'duties' => [
                'Supervise Panchayat Coordinators.',
                'Coordinate programs across the Block.',
                'Monitor field activities and beneficiary verification.',
                'Organize meetings, camps and awareness drives.',
                'Consolidate reports and submit them to the District Coordinator.',
                'Maintain block-level records and documentation.',
            ],
        ],
        'district' => [
            'label'  => 'District Coordinator',
            'icon'   => 'map',
            'scope'  => 'District level',
            'duties' => [
                'Coordinate foundation activities across the district.',
                'Supervise Block Coordinators.',
                'Plan and monitor district-level programs.',
                'Coordinate with partners, institutions and stakeholders.',
                'Review field reports, impact data and documentation.',
                'Prepare periodic district-level progress reports.',
            ],
        ],
    ];
}

/** Human label for a stored position key. */
function coord_position_label(?string $key): string
{
    return coord_positions()[(string) $key]['label'] ?? '—';
}

/**
 * The yes/no availability questions in section 8, keyed by their column.
 * Rendering them from one list keeps the form, the handler and the admin view
 * in step — adding a question here adds it in all three places.
 */
function coord_availability(): array
{
    return [
        'field_visits' => ['label' => 'Willing to undertake field visits',   'icon' => 'footprints'],
        'can_travel'   => ['label' => 'Able to travel within assigned area', 'icon' => 'route'],
        'two_wheeler'  => ['label' => 'Two-wheeler available',               'icon' => 'bike'],
        'has_licence'  => ['label' => 'Holds a driving licence',             'icon' => 'id-card'],
    ];
}

/**
 * Section 10 — the single reference, keyed by its column (each is a real
 * `ref_*` column, not JSON, because the office rings this person and needs to
 * be able to search on the number). One list drives the form, the handler and
 * the admin view.
 */
function coord_reference_fields(): array
{
    return [
        'ref_name'         => ['label' => 'Reference Person Name',      'icon' => 'user',       'max' => 128],
        'ref_designation'  => ['label' => 'Designation / Role',         'icon' => 'briefcase',  'max' => 128],
        'ref_organization' => ['label' => 'Organization / Institution', 'icon' => 'building-2', 'max' => 191],
        'ref_mobile'       => ['label' => 'Mobile Number',              'icon' => 'phone',      'max' => 32],
        'ref_relationship' => ['label' => 'Relationship with Applicant','icon' => 'users',      'max' => 96],
    ];
}

/** Rows of the educational-qualification table (section 3). */
function coord_education_levels(): array
{
    return ['10th', '12th', 'Graduation', 'Post Graduation', 'Other'];
}

/** Computer-knowledge checkboxes (section 3). */
function coord_computer_skills(): array
{
    return ['Basic Computer', 'MS Office', 'Internet & Email', 'Google Workspace', 'Data Entry', 'Social Media'];
}

/** Community & field experience checkboxes (section 5). */
function coord_focus_areas(): array
{
    return [
        'Education', 'Skill Development', 'Women Empowerment', 'Youth Development',
        'Healthcare', 'Rural Development', 'Environment', 'WASH',
        'Relief & Rehabilitation', 'Government/Community Schemes',
        'Survey/Data Collection', 'Awareness Campaigns',
    ];
}

/** Languages known (section 6). */
function coord_languages(): array
{
    return ['Hindi', 'English', 'Local Language'];
}

/** Preferred work mode (section 7). */
function coord_work_modes(): array
{
    return ['Full Time', 'Part Time', 'Field Based', 'Flexible'];
}

/** Upload folder for coordinator documents (denied to Apache; see its .htaccess). */
const COORD_DOC_DIR = 'coordinator-docs';

/**
 * The document checklist (section 9).
 *
 * 'required' marks the two documents an application cannot be assessed without;
 * every other slot is optional and labelled as such on the form. 'image'
 * restricts a slot to a photograph rather than any document.
 */
function coord_documents(): array
{
    return [
        'photo'           => ['label' => 'Passport-size Photograph',         'icon' => 'user-round',     'required' => true,  'image' => true],
        'id_proof'        => ['label' => 'Aadhaar Card / Valid ID Proof',    'icon' => 'id-card',        'required' => true,  'image' => false],
        'address_proof'   => ['label' => 'Address / Residence Proof',        'icon' => 'map-pin',        'required' => false, 'image' => false],
        'education_certs' => ['label' => 'Educational Certificates',         'icon' => 'graduation-cap', 'required' => false, 'image' => false],
        'experience_cert' => ['label' => 'Experience Certificate',           'icon' => 'briefcase',      'required' => false, 'image' => false, 'note' => 'if applicable'],
        'resume'          => ['label' => 'Updated Resume / CV',              'icon' => 'file-text',      'required' => false, 'image' => false],
        'bank_proof'      => ['label' => 'Bank Account / Cancelled Cheque',  'icon' => 'landmark',       'required' => false, 'image' => false],
        'pan_card'        => ['label' => 'PAN Card',                         'icon' => 'credit-card',    'required' => false, 'image' => false, 'note' => 'if applicable'],
        'driving_licence' => ['label' => 'Driving Licence',                  'icon' => 'car',            'required' => false, 'image' => false, 'note' => 'if applicable'],
        'other'           => ['label' => 'Other Supporting Document',        'icon' => 'paperclip',      'required' => false, 'image' => false],
    ];
}

/** Extensions accepted for a slot. Scans are usually photographed, so images count. */
function coord_doc_allowed(bool $imageOnly): string
{
    return $imageOnly ? 'jpg,jpeg,png,webp' : 'pdf,jpg,jpeg,png,webp,doc,docx';
}

/** Workflow statuses and their admin pill colours. */
function coord_statuses(): array
{
    return [
        'new'          => ['label' => 'New',          'pill' => 'pill-blue'],
        'under_review' => ['label' => 'Under Review', 'pill' => 'pill-cyan'],
        'shortlisted'  => ['label' => 'Shortlisted',  'pill' => 'pill-amber'],
        'interview'    => ['label' => 'Interview',    'pill' => 'pill-violet'],
        'approved'     => ['label' => 'Approved',     'pill' => 'pill-green'],
        'rejected'     => ['label' => 'Rejected',     'pill' => 'pill-red'],
    ];
}

/** Decode a JSON column written by the handler; always returns an array. */
function coord_json(?string $raw): array
{
    if (empty($raw)) {
        return [];
    }
    $out = json_decode($raw, true);
    return is_array($out) ? $out : [];
}

/**
 * Reveal a stored ID number for the admin detail view.
 *
 * Rows written while APP_KEY was configured hold AES-256-GCM ciphertext; rows
 * written without it hold the number as typed. Both cases return something
 * displayable, and a ciphertext that no longer decrypts (key rotated, row
 * tampered with) returns null rather than a wall of base64.
 */
function coord_reveal_id(?string $stored): ?string
{
    if ($stored === null || $stored === '') {
        return null;
    }
    if (!sec_is_encrypted($stored)) {
        return $stored;
    }
    return sec_decrypt($stored);
}
