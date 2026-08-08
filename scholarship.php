<?php
/**
 * =============================================================================
 *  Scholarships — public landing page listing open scholarships + application
 *  form. Every section is DB-driven with strong, NGO-appropriate fallbacks so
 *  the page is complete and polished before any content is seeded.
 *
 *  The form posts (AJAX, multipart) to forms/scholarship and maps 1:1 to the
 *  `scholarship_applications` table columns (name, email, phone, institution,
 *  course, guardian_name, annual_income, document, message, scholarship_id).
 *
 *  Sections: overview · what we cover · open scholarships · how to apply ·
 *  impact counters · application form + status note · FAQs · CTA.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* ---- Data -------------------------------------------------------------- */
$scholarships = db_all(
    "SELECT * FROM scholarships
     WHERE status = 'open'
     ORDER BY sort_order ASC, deadline IS NULL ASC, deadline ASC, id ASC"
);

/* Fallback scholarships so the page never looks empty before seeding. Deadlines
 * are computed relative to today so the countdown stays meaningful over time. */
if (!$scholarships) {
    $scholarships = [
        [
            'id'          => null,
            'title'       => 'Merit Scholarship for Undergraduates',
            'level'       => 'Undergraduate',
            'amount'      => 'Up to ₹25,000 / year',
            'eligibility' => 'Students from families with an annual income below ₹2.5 lakh who secured 75% or above in Class 12. Open to all streams pursuing a recognised bachelor\'s degree.',
            'deadline'    => date('Y-m-d', strtotime('+45 days')),
        ],
        [
            'id'          => null,
            'title'       => 'Girl Child Education Grant',
            'level'       => 'School (Class 9-12)',
            'amount'      => '₹12,000 / year',
            'eligibility' => 'Girl students of Class 9 to 12 from underserved communities across Bihar. Priority to first-generation learners and single-parent households.',
            'deadline'    => date('Y-m-d', strtotime('+30 days')),
        ],
        [
            'id'          => null,
            'title'       => 'Postgraduate Research Fellowship',
            'level'       => 'Postgraduate',
            'amount'      => '₹40,000 / year',
            'eligibility' => 'Meritorious postgraduate students engaged in research or study in social sciences, public health, education or rural development.',
            'deadline'    => date('Y-m-d', strtotime('+60 days')),
        ],
        [
            'id'          => null,
            'title'       => 'Vocational & Skill Development Aid',
            'level'       => 'Vocational / ITI',
            'amount'      => 'Up to ₹18,000',
            'eligibility' => 'Youth aged 16-25 enrolled in ITI, polytechnic or certified skill-training programmes that lead to sustainable employment.',
            'deadline'    => date('Y-m-d', strtotime('+21 days')),
        ],
        [
            'id'          => null,
            'title'       => 'Special Needs Support Scholarship',
            'level'       => 'All Levels',
            'amount'      => '₹20,000 / year',
            'eligibility' => 'Students with disabilities (40% or more) pursuing any level of education. Assistance also covers assistive devices and learning aids.',
            'deadline'    => null,
        ],
        [
            'id'          => null,
            'title'       => 'Merit-cum-Means Higher Studies Grant',
            'level'       => 'Undergraduate / Postgraduate',
            'amount'      => 'Up to ₹35,000 / year',
            'eligibility' => 'Academically strong students from economically weaker sections pursuing professional courses such as engineering, medicine, law or commerce.',
            'deadline'    => date('Y-m-d', strtotime('+90 days')),
        ],
    ];
}

/* ---- What our scholarships cover --------------------------------------- */
$coverage = [
    ['cls' => '',  'icon' => 'graduation-cap', 'title' => 'Tuition & Fees',        'text' => 'Direct support towards admission, tuition and examination fees so a lack of money never ends a bright student\'s education.'],
    ['cls' => 'p', 'icon' => 'book',           'title' => 'Books & Materials',     'text' => 'Grants for textbooks, stationery, uniforms and essential learning materials for the full academic year.'],
    ['cls' => 'o', 'icon' => 'laptop',         'title' => 'Digital Learning',      'text' => 'Support for devices, data and online resources so students can keep learning from anywhere.'],
    ['cls' => 'g', 'icon' => 'home',           'title' => 'Living & Hostel',       'text' => 'Assistance with hostel, transport and living costs for students who must study away from home.'],
];

/* ---- How to apply (process steps) -------------------------------------- */
$steps = [
    ['title' => 'Check Eligibility',   'text' => 'Read the eligibility for each open scholarship below and pick the one that best matches your level and background.'],
    ['title' => 'Prepare Documents',   'text' => 'Keep your ID proof, latest marksheet and income certificate ready — you can attach a single combined PDF with your application.'],
    ['title' => 'Submit the Form',     'text' => 'Fill in the application form on this page and upload your supporting document. It takes only a few minutes.'],
    ['title' => 'Review & Award',      'text' => 'Our committee reviews every application on merit and need. Selected students are notified by email and phone.'],
];

/* ---- Impact counters --------------------------------------------------- */
$stats = [
    ['icon' => 'graduation-cap', 'value' => 1200, 'suffix' => '+', 'title' => 'Students Supported', 'description' => 'across schools & colleges'],
    ['icon' => 'coins',          'value' => 85,   'suffix' => 'L+', 'title' => 'Disbursed in Aid',  'description' => 'directly to beneficiaries'],
    ['icon' => 'user',           'value' => 62,   'suffix' => '%', 'title' => 'Girl Beneficiaries', 'description' => 'we prioritise equity'],
    ['icon' => 'home',           'value' => 40,   'suffix' => '+', 'title' => 'Districts Reached',  'description' => 'across Bihar & beyond'],
];

/* ---- FAQs -------------------------------------------------------------- */
$faqs = db_all(
    "SELECT question, answer FROM faqs
     WHERE status = 1 AND category = :cat
     ORDER BY sort_order ASC, id ASC
     LIMIT 8",
    [':cat' => 'scholarship']
);
if (!$faqs) {
    $faqs = [
        ['question' => 'Who can apply for a scholarship?',                'answer' => 'Any deserving student from an economically weaker or underserved background can apply, provided they meet the eligibility criteria listed for a specific scholarship. We welcome applicants from every community across Bihar and beyond.'],
        ['question' => 'Is there any application fee?',                   'answer' => 'No. Applying for any EDUSKILL INDIA FOUNDATION scholarship is completely free. We will never ask you to pay a fee at any stage of the process.'],
        ['question' => 'What documents do I need to attach?',            'answer' => 'Please keep a photo ID, your most recent marksheet and a family income certificate ready. You can combine them into a single PDF and upload it with your application. Additional documents may be requested during review.'],
        ['question' => 'Can I apply for more than one scholarship?',      'answer' => 'You may apply for the scholarship that best fits your situation. If you are unsure which one suits you, choose the closest match and mention your details in the message box — our team will guide you.'],
        ['question' => 'How and when will I hear back?',                 'answer' => 'Every application is reviewed on merit and need. Shortlisted students are contacted by email and phone, usually within a few weeks of the deadline. You can also check your status by contacting our team.'],
        ['question' => 'How is the scholarship amount paid?',            'answer' => 'Approved amounts are disbursed directly to the student or the institution, depending on the scholarship. Our team will share the exact process once your application is approved.'],
    ];
}

/* ---- Helpers ----------------------------------------------------------- */
/** Human-friendly deadline label. */
$fmtDeadline = static function ($d): string {
    if (empty($d)) {
        return 'Rolling — apply anytime';
    }
    return format_date($d, 'd M Y');
};
/** Whole days left until a deadline (null when there is no deadline). */
$daysLeft = static function ($d): ?int {
    if (empty($d)) {
        return null;
    }
    $ts = strtotime((string) $d);
    if ($ts === false) {
        return null;
    }
    return (int) ceil(($ts - strtotime('today')) / 86400);
};
/** Stable <option>/anchor value: real id when seeded, synthetic token otherwise. */
$optValue = static function (array $s, int $i): string {
    return !empty($s['id']) ? (string) (int) $s['id'] : ('fb-' . $i);
};

/* ---- SEO --------------------------------------------------------------- */
seo_set([
    'title'       => 'Scholarships',
    'description' => 'Explore open scholarships from EDUSKILL INDIA FOUNDATION for school, undergraduate, postgraduate and vocational students across Bihar. Merit-and-need based financial aid — apply online, free of cost.',
    'page_key'    => 'scholarship',
    'type'        => 'website',
]);

/* ---- Hero banner ------------------------------------------------------- */
$page_hero = [
    'title'      => 'Scholarships',
    'subtitle'   => 'Financial support that keeps bright, deserving students in school and college. Explore open scholarships and apply online — always free of cost.',
    'breadcrumb' => [
        ['label' => 'Get Involved', 'url' => url('programs')],
        ['label' => 'Scholarships'],
    ],
];

include __DIR__ . '/includes/header.php';
?>

<!-- ============================== OVERVIEW ============================== -->
<section class="section">
    <div class="container grid grid-2 items-center gap-4">
        <div class="reveal">
            <span class="eyebrow">Education for All</span>
            <h2 class="section-title">No Dream Should End for the <span class="text-grad-ocean">Want of a Fee</span></h2>
            <p class="lead">Talent is everywhere, but opportunity is not. Our scholarships put financial aid directly into the hands of students who have the will to learn but not the means.</p>
            <p class="text-muted">From school children and first-generation college students to postgraduate researchers and skill-training youth, we support learners at every stage. Every award is decided purely on merit and need — transparently, and with dignity. Explore the open scholarships below and apply in a few simple steps.</p>
            <ul class="list-check mt-3">
                <li>Merit-and-need based awards — no application fee, ever</li>
                <li>Support for tuition, books, devices, hostel and living costs</li>
                <li>Priority to girls, first-generation learners and underserved communities</li>
            </ul>
            <div class="flex flex-wrap gap-2 mt-4">
                <a class="btn btn-3d" href="#scholarships">Browse Scholarships</a>
                <a class="btn btn-outline" href="#apply">Apply Now</a>
            </div>
        </div>
        <div class="reveal delay-1">
            <div class="glass-card" style="position:relative;overflow:hidden;">
                <span class="blob b1" style="top:-50px;right:-40px;"></span>
                <span class="blob b3" style="bottom:-60px;left:-40px;"></span>
                <div style="position:relative;">
                    <div class="icon-badge" style="background:var(--grad-brand);"><?= lucide('graduation-cap') ?></div>
                    <h3 class="card-title mb-1">Scholarships that reach the student</h3>
                    <p class="card-text mb-3">Every rupee is tracked from donation to disbursement, so support arrives where it is needed most.</p>
                    <div class="divider"></div>
                    <div class="grid grid-2 gap-3 mt-3 text-center">
                        <div>
                            <div class="counter-value" style="color:var(--brand-600);"><span data-counter="1200">0</span>+</div>
                            <small class="text-muted">Students supported</small>
                        </div>
                        <div>
                            <div class="counter-value" style="color:var(--brand-600);">₹<span data-counter="85">0</span>L+</div>
                            <small class="text-muted">Disbursed in aid</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================== WHAT WE COVER ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="justify-content:center;">What Your Scholarship Covers</span>
            <h2 class="section-title">More Than Just Tuition</h2>
            <p class="section-subtitle">Real access to education means covering every cost that stands between a student and their classroom.</p>
        </div>
        <div class="grid grid-4">
            <?php foreach ($coverage as $i => $c): ?>
                <div class="icon-box glass-card reveal <?= 'delay-' . (($i % 4) + 1) ?> <?= e($c['cls']) ?>">
                    <div class="ib-icon"><?= lucide($c['icon']) ?></div>
                    <h3 class="card-title"><?= e($c['title']) ?></h3>
                    <p class="card-text"><?= e($c['text']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== OPEN SCHOLARSHIPS ============================== -->
<section class="section" id="scholarships">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="justify-content:center;">Now Accepting Applications</span>
            <h2 class="section-title">Open Scholarships</h2>
            <p class="section-subtitle">Find the scholarship that matches your level and background, then apply using the form below. New scholarships are added through the year.</p>
        </div>

        <?php if ($scholarships): ?>
        <div class="grid grid-3">
            <?php foreach ($scholarships as $i => $s):
                $val   = $optValue($s, $i);
                $left  = $daysLeft($s['deadline'] ?? null);
                $elig  = trim((string) ($s['eligibility'] ?? ''));
            ?>
                <article class="card-3d reveal <?= 'delay-' . (($i % 3) + 1) ?>" style="display:flex;flex-direction:column;">
                    <div class="flex items-center justify-between gap-2 flex-wrap mb-2">
                        <span class="badge badge-brand"><?= e($s['level'] ?: 'Open to all') ?></span>
                        <?php if ($left !== null && $left >= 0 && $left <= 14): ?>
                            <span class="badge badge-danger"><?= lucide('hourglass') ?> <?= (int) $left ?> day<?= $left === 1 ? '' : 's' ?> left</span>
                        <?php elseif (empty($s['deadline'])): ?>
                            <span class="badge badge-success">Rolling</span>
                        <?php endif; ?>
                    </div>

                    <h3 class="card-title mb-1"><?= e($s['title']) ?></h3>

                    <div class="text-grad-ocean" style="font-family:var(--font-display,inherit);font-size:1.35rem;font-weight:800;margin:.25rem 0 .5rem;">
                        <?= e($s['amount'] ?: 'Grant-in-aid') ?>
                    </div>

                    <?php if ($elig !== ''): ?>
                        <p class="card-text" style="flex:1 1 auto;"><?= e(excerpt($elig, 26)) ?></p>
                    <?php else: ?>
                        <p class="card-text text-muted" style="flex:1 1 auto;">Open to eligible students who meet the general scholarship criteria. Contact us for full details.</p>
                    <?php endif; ?>

                    <div class="divider" style="margin:.75rem 0;"></div>
                    <div class="flex items-center justify-between gap-2 flex-wrap">
                        <span class="chip"><?= lucide('calendar-days') ?> <?= e($fmtDeadline($s['deadline'] ?? null)) ?></span>
                        <a class="btn btn-primary btn-sm" href="#apply"
                           onclick="var el=document.getElementById('sch-scholarship');if(el){el.value='<?= e($val) ?>';}">
                            Apply <?= lucide('arrow-right') ?>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="icon-badge"><?= lucide('graduation-cap') ?></div>
            <h3>No open scholarships right now</h3>
            <p class="text-muted">There are no scholarships accepting applications at the moment. New opportunities are added through the year — check back soon or register your interest below.</p>
            <a class="btn btn-primary mt-2" href="#apply">Register Your Interest</a>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ============================== HOW TO APPLY ============================== -->
<section class="section section-alt">
    <div class="container grid grid-2 gap-4 items-center">
        <div class="reveal">
            <span class="eyebrow">Simple &amp; Transparent</span>
            <h2 class="section-title">How to Apply</h2>
            <p class="text-muted mb-3">Four straightforward steps stand between you and the support you deserve. There is no cost, and our team is here to help at every stage.</p>
            <div class="process-steps">
                <?php foreach ($steps as $step): ?>
                    <div class="process-step">
                        <h3 class="card-title mb-1"><?= e($step['title']) ?></h3>
                        <p class="card-text"><?= e($step['text']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="reveal delay-1">
            <div class="card-3d" style="background:var(--grad-brand);color:#fff;">
                <div class="icon-badge" style="background:rgba(255,255,255,.2);"><?= lucide('file-text') ?></div>
                <h3 class="card-title text-white">Documents to Keep Ready</h3>
                <p style="color:rgba(255,255,255,.92);">Having these ready makes your application quick and complete. You can combine them into one PDF to upload.</p>
                <ul class="list-check mt-3" style="--check-color:#fff;">
                    <li>Photo ID proof (Aadhaar / school or college ID)</li>
                    <li>Latest marksheet or result</li>
                    <li>Family income certificate</li>
                    <li>Proof of admission / enrolment</li>
                    <li>A recent passport-size photograph</li>
                </ul>
                <div class="flex flex-wrap gap-2 mt-3">
                    <span class="chip" style="background:rgba(255,255,255,.16);color:#fff;"><?= lucide('gift') ?> No application fee</span>
                    <span class="chip" style="background:rgba(255,255,255,.16);color:#fff;"><?= lucide('lock') ?> Your data stays private</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================== IMPACT COUNTERS ============================== -->
<section class="section section-brand">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="justify-content:center;color:#dbeafe;">Our Scholarship Impact</span>
            <h2 class="section-title text-white">Education That Changes Lives</h2>
            <p class="section-subtitle" style="color:rgba(255,255,255,.9);">Every scholarship is a student who stayed in school, a family that dared to dream bigger.</p>
        </div>
        <div class="counter-grid">
            <?php foreach ($stats as $c): ?>
                <div class="counter-item reveal">
                    <div class="icon-badge" style="background:rgba(255,255,255,.18);"><?= lucide($c['icon']) ?></div>
                    <div class="counter-value"><span data-counter="<?= (int) $c['value'] ?>">0</span><?= e($c['suffix']) ?></div>
                    <div class="counter-label"><?= e($c['title']) ?></div>
                    <?php if (!empty($c['description'])): ?>
                        <p style="color:rgba(255,255,255,.8);font-size:.85rem;margin-top:.35rem;"><?= e($c['description']) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== APPLICATION FORM + STATUS NOTE ============================== -->
<section class="section" id="apply">
    <div class="container grid grid-sidebar gap-4 items-start">
        <!-- Application status note + help -->
        <div class="reveal">
            <span class="eyebrow">Before You Submit</span>
            <h2 class="section-title">Track Your Application</h2>
            <p class="text-muted">After you apply, our committee reviews your details on merit and need. Here is exactly what happens next, so you always know where you stand.</p>

            <div class="timeline mt-4">
                <div class="timeline-item">
                    <span class="tl-date">Step 1 · Received</span>
                    <h3 class="card-title mb-0" style="font-size:1.05rem;">Application Submitted</h3>
                    <p class="card-text">You will see an instant confirmation. Your application is logged with the status <strong>New</strong>.</p>
                </div>
                <div class="timeline-item">
                    <span class="tl-date">Step 2 · In Progress</span>
                    <h3 class="card-title mb-0" style="font-size:1.05rem;">Under Review</h3>
                    <p class="card-text">Our team verifies your documents and eligibility. This is the <strong>Under Review</strong> stage.</p>
                </div>
                <div class="timeline-item">
                    <span class="tl-date">Step 3 · Decision</span>
                    <h3 class="card-title mb-0" style="font-size:1.05rem;">Approved or Not Selected</h3>
                    <p class="card-text">Selected students are contacted by email and phone with next steps for disbursement.</p>
                </div>
            </div>

            <div class="alert alert-info mt-3">
                <strong>Checking your status?</strong> Applications are reviewed after each scholarship deadline. If you have not heard back, email
                <a href="mailto:<?= e(get_setting('contact_email', SITE_EMAIL)) ?>"><?= e(get_setting('contact_email', SITE_EMAIL)) ?></a>
                or call <a href="tel:<?= e(preg_replace('/\s+/', '', get_setting('contact_phone', SITE_PHONE))) ?>"><?= e(get_setting('contact_phone', SITE_PHONE)) ?></a>
                with your name and email to hear where your application stands.
            </div>
        </div>

        <!-- Application form -->
        <div class="card reveal delay-1">
            <div class="card-body">
                <h2 class="section-title mb-1">Scholarship Application</h2>
                <p class="text-muted mb-3">Fill in your details below. Fields marked <span class="req">*</span> are required. Applying is free.</p>

                <form data-ajax-form data-endpoint="<?= e(url('forms/scholarship')) ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>

                    <div class="form-group">
                        <label class="form-label req" for="sch-scholarship">Scholarship You're Applying For</label>
                        <select class="form-select" id="sch-scholarship" name="scholarship_id" required>
                            <option value="">— Choose a scholarship —</option>
                            <?php foreach ($scholarships as $i => $s): ?>
                                <option value="<?= e($optValue($s, $i)) ?>">
                                    <?= e($s['title']) ?><?= !empty($s['amount']) ? ' · ' . e($s['amount']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="general">Not sure yet / general enquiry</option>
                        </select>
                        <small class="form-hint">Pick the closest match — you can add details in the message box below.</small>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label req" for="sch-name">Full Name</label>
                            <input class="form-control" id="sch-name" name="name" type="text" maxlength="128" placeholder="Student's full name" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label req" for="sch-email">Email</label>
                            <input class="form-control" id="sch-email" name="email" type="email" maxlength="191" placeholder="you@example.com" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <?= country_field(['name' => 'phone', 'label' => 'Phone', 'required' => true]) ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="sch-guardian">Parent / Guardian Name</label>
                            <input class="form-control" id="sch-guardian" name="guardian_name" type="text" maxlength="128" placeholder="Name of parent or guardian">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="sch-institution">School / College / Institution</label>
                            <input class="form-control" id="sch-institution" name="institution" type="text" maxlength="191" placeholder="Where you currently study">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="sch-course">Class / Course</label>
                            <input class="form-control" id="sch-course" name="course" type="text" maxlength="191" placeholder="e.g. Class 11, B.Sc. 2nd year">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="sch-income">Annual Family Income</label>
                        <select class="form-select" id="sch-income" name="annual_income">
                            <option value="">Select income range</option>
                            <option value="Below ₹1,00,000">Below ₹1,00,000</option>
                            <option value="₹1,00,000 – ₹2,50,000">₹1,00,000 – ₹2,50,000</option>
                            <option value="₹2,50,000 – ₹5,00,000">₹2,50,000 – ₹5,00,000</option>
                            <option value="Above ₹5,00,000">Above ₹5,00,000</option>
                            <option value="Prefer not to say">Prefer not to say</option>
                        </select>
                        <small class="form-hint">Helps us prioritise need-based support. Kept strictly confidential.</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="sch-document">Supporting Document</label>
                        <input class="form-control" id="sch-document" name="document" type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <small class="form-hint">Upload a single PDF/image with your ID, latest marksheet and income certificate. PDF, JPG, PNG, DOC or DOCX.</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="sch-message">Message / Your Story</label>
                        <textarea class="form-textarea" id="sch-message" name="message" rows="5" placeholder="Tell us briefly about your situation, your goals and why this scholarship would help."></textarea>
                        <small class="form-hint">A short, honest note is perfect — it helps our committee understand your need.</small>
                    </div>

                    <button class="btn btn-3d btn-block btn-lg" type="submit">Submit Application</button>
                    <p class="text-muted mt-2" style="font-size:.85rem;">By applying you agree to be contacted by our team about this scholarship. We respect your privacy and never share your details or charge any fee.</p>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- ============================== FAQ ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="justify-content:center;">Good to Know</span>
            <h2 class="section-title">Frequently Asked Questions</h2>
            <p class="section-subtitle">Answers to the questions students and parents ask us most.</p>
        </div>
        <div style="max-width:820px;margin-inline:auto;">
            <?php foreach ($faqs as $i => $faq): ?>
                <details class="accordion-item reveal" <?= $i === 0 ? 'open' : '' ?>>
                    <summary><?= e($faq['question']) ?></summary>
                    <div class="acc-body"><?= e($faq['answer']) ?></div>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== CTA ============================== -->
<section class="section">
    <div class="container">
        <div class="card-3d reveal text-center" style="background:var(--grad-brand);color:#fff;max-width:900px;margin-inline:auto;">
            <span class="eyebrow" style="justify-content:center;color:#dbeafe;"><?= e(SITE_TAGLINE) ?></span>
            <h2 class="text-white">Help a Student Stay in School</h2>
            <p style="color:rgba(255,255,255,.92);max-width:640px;margin-inline:auto;">Your donation funds the next scholarship — a book, a fee, a future. Together we can make sure no talented child is left behind for the want of a rupee.</p>
            <div class="flex flex-wrap justify-center gap-2 mt-4">
                <a class="btn btn-white btn-lg" href="#apply"><?= lucide('graduation-cap') ?> Apply for a Scholarship</a>
                <a class="btn btn-outline btn-lg" style="border-color:#fff;color:#fff;" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Fund a Scholarship</a>
            </div>
        </div>
    </div>
</section>

<?php echo country_field_assets();
include __DIR__ . '/includes/footer.php'; ?>
