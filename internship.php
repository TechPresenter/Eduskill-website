<?php
/**
 * =============================================================================
 *  Internship Program — public landing page + application form.
 *
 *  Every section is DB-driven with strong, NGO-appropriate fallbacks so the
 *  page looks complete and polished before any content is seeded. The form
 *  posts (AJAX, multipart) to forms/internship and maps 1:1 to the
 *  `internships` table columns.
 *
 *  Sections: overview · perks · focus areas · how it works · impact counters ·
 *  eligibility · application form · FAQs · CTA.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* ---- Helpers ----------------------------------------------------------- */
/**
 * Read a JSON-encoded list setting, decode it, and fall back to a curated
 * default when the key is missing or holds invalid/empty JSON.
 *
 * @param string $key      settings key_name
 * @param array  $fallback default structure returned when nothing usable
 * @return array
 */
$list_setting = static function (string $key, array $fallback): array {
    $raw = get_setting($key);
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_column($raw);
        if (is_array($decoded) && $decoded) {
            return $decoded;
        }
    }
    return $fallback;
};

/* ---- Perks (what interns get) ------------------------------------------ */
$perks = $list_setting('internship_perks', [
    ['icon' => 'scroll',       'title' => 'Completion Certificate', 'text' => 'An official, verifiable internship certificate and a letter of recommendation on successful completion.'],
    ['icon' => 'compass',      'title' => 'Mentorship',            'text' => 'Guidance from experienced programme leaders and field staff who invest in your growth.'],
    ['icon' => 'globe',        'title' => 'Real Field Exposure',    'text' => 'Work on live community projects across Bihar — not busywork, but tasks that create real impact.'],
    ['icon' => 'wrench',       'title' => 'Skill Building',          'text' => 'Hands-on learning in project management, communications, research, fundraising and grassroots outreach.'],
    ['icon' => 'handshake',    'title' => 'Professional Network',    'text' => 'Connect with changemakers, partners and fellow interns who share your passion for social good.'],
    ['icon' => 'trending-up',  'title' => 'Portfolio & Experience',  'text' => 'Build a meaningful portfolio and CV with documented contributions to real social-impact work.'],
    ['icon' => 'clock',        'title' => 'Flexible Duration',       'text' => 'Choose a term that fits your studies — from a short 1-3 month stint to a full 6+ month immersion.'],
    ['icon' => 'lightbulb',    'title' => 'Leadership Pathway',      'text' => 'Outstanding interns are invited to continue as volunteers, fellows or team members.'],
]);

/* ---- Focus areas (interest tracks) ------------------------------------- */
/* Prefer active programmes so tracks stay in sync with our work; fall back to
 * a curated set of NGO departments so the section is never empty. */
$programs = db_all(
    "SELECT title, icon, short_description, color
     FROM programs
     WHERE status = 'active' AND deleted_at IS NULL
     ORDER BY is_featured DESC, sort_order ASC
     LIMIT 6"
);
if (!$programs) {
    $programs = [
        ['title' => 'Education & Teaching',        'icon' => 'book',          'short_description' => 'Support classes, learning kits and scholarship drives for underprivileged children.', 'color' => '#0B4E3D'],
        ['title' => 'Healthcare & Camps',          'icon' => 'stethoscope',   'short_description' => 'Help organise medical camps, health awareness and community wellbeing drives.',        'color' => '#174D3D'],
        ['title' => 'Women Empowerment',           'icon' => 'user',          'short_description' => 'Assist skill training, self-help groups and livelihood programmes for women.',         'color' => '#F15A24'],
        ['title' => 'Communications & Content',    'icon' => 'pen-line',      'short_description' => 'Create stories, social media, photography and reports that amplify our impact.',       'color' => '#8b5cf6'],
        ['title' => 'Research & Documentation',    'icon' => 'search',        'short_description' => 'Field surveys, impact assessment and data that sharpen our programmes.',              'color' => '#2F8065'],
        ['title' => 'Fundraising & Events',        'icon' => 'party-popper',  'short_description' => 'Plan campaigns, donor outreach and events that fuel the mission.',                    'color' => '#dc2626'],
    ];
}

/* Interest options for the application form's select (titles + safe extras). */
$interestOptions = [];
foreach ($programs as $pr) {
    if (!empty($pr['title'])) {
        $interestOptions[] = $pr['title'];
    }
}
foreach (['Communications & Content', 'Research & Documentation', 'Fundraising & Events', 'Volunteer Coordination'] as $extra) {
    if (!in_array($extra, $interestOptions, true)) {
        $interestOptions[] = $extra;
    }
}
$interestOptions[] = 'Other';

/* ---- How it works (application steps) ---------------------------------- */
$steps = $list_setting('internship_process', [
    ['title' => 'Apply Online',       'text' => 'Fill in the application form below and attach your résumé. It takes just a few minutes.',              'color' => '#0B4E3D'],
    ['title' => 'Screening Call',     'text' => 'Shortlisted candidates have a short conversation with our team to match interests and availability.', 'color' => '#174D3D'],
    ['title' => 'Onboarding',         'text' => 'Join an orientation, meet your mentor and get set up with your project and goals.',                    'color' => '#F15A24'],
    ['title' => 'Learn & Contribute', 'text' => 'Work on real assignments, submit your report and receive your certificate on completion.',             'color' => '#2F8065'],
]);

/* ---- Impact counters --------------------------------------------------- */
$stats = $list_setting('internship_stats', [
    ['icon' => 'graduation-cap', 'value' => 350, 'suffix' => '+', 'title' => 'Interns Trained',   'description' => 'Students who grew with us'],
    ['icon' => 'school',         'value' => 40,  'suffix' => '+', 'title' => 'Partner Colleges',  'description' => 'Institutions we work with'],
    ['icon' => 'sprout',         'value' => 6,   'suffix' => '',  'title' => 'Focus Areas',       'description' => 'Tracks to choose from'],
    ['icon' => 'star',           'value' => 95,  'suffix' => '%', 'title' => 'Would Recommend',   'description' => 'Interns who loved the experience'],
]);

/* ---- FAQs -------------------------------------------------------------- */
$faqs = db_all(
    "SELECT question, answer FROM faqs
     WHERE status = 1 AND category = :cat
     ORDER BY sort_order ASC, id ASC
     LIMIT 8",
    [':cat' => 'internship']
);
if (!$faqs) {
    $faqs = [
        ['question' => 'Who can apply for an internship?',           'answer' => 'Any student or recent graduate with a genuine passion for social work can apply. We welcome interns from every discipline — you bring the enthusiasm, we provide the learning.'],
        ['question' => 'Is the internship paid?',                    'answer' => 'Our internships are learning-focused and generally unpaid, but we provide mentorship, a certificate, a letter of recommendation and real field experience. Select project-based roles may offer a stipend.'],
        ['question' => 'Can I intern remotely?',                     'answer' => 'Many roles — especially communications, research and content — can be done partly or fully remote. Field-based tracks require presence in and around Patna, Bihar.'],
        ['question' => 'How long is the internship?',                'answer' => 'You choose: a short 1-3 month term, a 3-6 month term, or a longer 6+ month immersion. We work around your academic calendar wherever possible.'],
        ['question' => 'Will I receive a certificate?',              'answer' => 'Yes. On successful completion of your term and final report, you receive an official internship certificate, and top performers get a personalised letter of recommendation.'],
    ];
}

/* ---- SEO --------------------------------------------------------------- */
seo_set([
    'title'       => 'Internship Program',
    'description' => 'Intern with EDUSKILL INDIA FOUNDATION in Patna, Bihar. Gain real field experience, mentorship and a certificate while creating change through education, healthcare and community programs. Apply online today.',
    'page_key'    => 'internship',
    'type'        => 'website',
]);

/* ---- Hero banner ------------------------------------------------------- */
$page_hero = [
    'title'      => 'Internship Program',
    'subtitle'   => 'Learn by doing. Grow while giving back. Join a hands-on internship that turns your energy into real impact for communities across Bihar.',
    'breadcrumb' => [
        ['label' => 'Get Involved', 'url' => url('volunteer')],
        ['label' => 'Internship Program'],
    ],
];

include __DIR__ . '/includes/header.php';
?>

<!-- ============================== OVERVIEW ============================== -->
<section class="section">
    <div class="container grid grid-2 items-center gap-4">
        <div class="reveal">
            <span class="eyebrow">Internship Program</span>
            <h2 class="section-title">Start Your Journey in Social Impact</h2>
            <p class="lead">An internship at <?= e(SITE_NAME) ?> is more than a line on your CV — it is a chance to work shoulder-to-shoulder with communities and see real change take shape.</p>
            <p class="text-muted">From classrooms and health camps to research, storytelling and fundraising, our interns take on meaningful responsibilities under the guidance of experienced mentors. You will build practical skills, grow your professional network and leave with the confidence — and the certificate — to launch a career that matters. Whether you can give a few months or a full term, there is a place for you in this movement.</p>
            <ul class="list-check mt-3">
                <li>Hands-on work on live community projects, not busywork</li>
                <li>Dedicated mentor, orientation and structured learning goals</li>
                <li>Certificate, recommendation letter and a pathway to keep contributing</li>
            </ul>
            <div class="flex flex-wrap gap-2 mt-4">
                <a class="btn btn-primary" href="#apply">Apply for Internship</a>
                <a class="btn btn-outline" href="<?= e(url('volunteer')) ?>">Explore Volunteering</a>
            </div>
        </div>
        <div class="reveal delay-1">
            <div class="card-3d" style="background:var(--grad-brand);color:#fff;">
                <div class="icon-badge" style="background:rgba(255,255,255,.2);"><?= lucide('graduation-cap') ?></div>
                <h3 class="card-title text-white">Interns Power Our Mission</h3>
                <p style="color:rgba(255,255,255,.92);">“The best way to find yourself is to lose yourself in the service of others.” Our interns discover their strengths by putting them to work where they are needed most.</p>
                <div class="flex flex-wrap gap-2 mt-3">
                    <span class="chip" style="background:rgba(255,255,255,.16);color:#fff;"><?= lucide('map-pin') ?> Patna, Bihar</span>
                    <span class="chip" style="background:rgba(255,255,255,.16);color:#fff;"><?= lucide('clock') ?> 1-6+ months</span>
                    <span class="chip" style="background:rgba(255,255,255,.16);color:#fff;"><?= lucide('globe') ?> On-site &amp; remote</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================== PERKS ============================== -->
<section class="section section-soft perks-section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="justify-content:center;">Why Intern With Us</span>
            <h2 class="section-title">What You Gain</h2>
            <p class="section-subtitle">We invest in our interns the way we invest in the communities we serve — with care, mentorship and real opportunity.</p>
        </div>
        <div class="perk-grid">
            <?php
            $perkGrads = [
                'linear-gradient(135deg,#0B4E3D,#174D3D)',
                'linear-gradient(135deg,#174D3D,#22c197)',
                'linear-gradient(135deg,#1F5C48,#4ca832)',
                'linear-gradient(135deg,#c99416,#f4cd6a)',
            ];
            foreach ($perks as $i => $perk): ?>
                <div class="perk-card reveal <?= 'delay-' . (($i % 4) + 1) ?>">
                    <span class="perk-num"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                    <div class="perk-icon" style="background:<?= $perkGrads[$i % count($perkGrads)] ?>;"><?= lucide($perk['icon'] ?? 'star') ?></div>
                    <h3 class="perk-title"><?= e($perk['title'] ?? '') ?></h3>
                    <p class="perk-text"><?= e($perk['text'] ?? '') ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<style>
/* Internship perks — premium green cards */
.perks-section .perk-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; }
.perk-card { position: relative; background: var(--surface); border: 1px solid var(--border); border-radius: 20px; padding: 2rem 1.5rem 1.75rem; overflow: hidden; box-shadow: var(--shadow-sm); transition: transform .35s cubic-bezier(.16,1,.3,1), box-shadow .35s, border-color .35s; }
.perk-card::before { content: ""; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg,#0B4E3D,#174D3D,#F15A24); transform: scaleX(0); transform-origin: left; transition: transform .45s cubic-bezier(.16,1,.3,1); }
.perk-card:hover { transform: translateY(-8px); box-shadow: 0 26px 50px -20px rgba(6,78,59,.38); border-color: transparent; }
.perk-card:hover::before { transform: scaleX(1); }
.perk-num { position: absolute; top: 1rem; right: 1.3rem; font-family: var(--font-display,'Outfit'); font-size: 2.4rem; font-weight: 800; line-height: 1; color: rgba(11,78,61,.08); }
.perk-icon { width: 58px; height: 58px; border-radius: 16px; display: grid; place-items: center; color: #fff; margin-bottom: 1.15rem; box-shadow: 0 12px 24px rgba(23,77,61,.28); transition: transform .35s cubic-bezier(.16,1,.3,1); }
.perk-icon svg.lucide { width: 26px; height: 26px; }
.perk-card:hover .perk-icon { transform: scale(1.08) rotate(-6deg); }
.perk-title { font-size: 1.12rem; margin: 0 0 .5rem; }
.perk-text { color: var(--muted); font-size: .92rem; line-height: 1.65; margin: 0; }
:root[data-theme="dark"] .perk-num { color: rgba(255,255,255,.06); }
@media (max-width: 992px) { .perks-section .perk-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 560px) { .perks-section .perk-grid { grid-template-columns: 1fr; } }

/* ---------------------------- Application section: balanced two-column ---- */
.intern-apply-grid {
    display: grid;
    grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
    gap: clamp(1.5rem, 3.5vw, 3rem);
}
/* The card carries its own comfortable inner padding at this width. */
.intern-apply-grid .card-body { padding: clamp(1.4rem, 2.4vw, 2.1rem); }

/* Two-up fields, collapsing to one column only when genuinely narrow. */
.intern-apply-grid .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}
.intern-apply-grid .form-group { margin-bottom: 1rem; }
.intern-apply-grid .form-control,
.intern-apply-grid .form-select,
.intern-apply-grid .form-textarea { width: 100%; }

@media (max-width: 992px) {
    .intern-apply-grid { grid-template-columns: 1fr; }
}
@media (max-width: 560px) {
    .intern-apply-grid .form-row { grid-template-columns: 1fr; gap: 0; }
}
</style>

<!-- ============================== FOCUS AREAS ============================== -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="justify-content:center;">Choose Your Track</span>
            <h2 class="section-title">Internship Focus Areas</h2>
            <p class="section-subtitle">Tell us where your interest lies and we will match you to a track where your skills make the biggest difference.</p>
        </div>
        <div class="grid grid-3">
            <?php foreach ($programs as $i => $pr): ?>
                <div class="card-3d reveal <?= 'delay-' . (($i % 3) + 1) ?>">
                    <div class="icon-badge" style="background:linear-gradient(135deg,<?= e($pr['color'] ?: '#0B4E3D') ?>,#174D3D);"><?= lucide($pr['icon'] ?: 'star') ?></div>
                    <h3 class="card-title"><?= e($pr['title']) ?></h3>
                    <p class="card-text"><?= e(excerpt($pr['short_description'] ?? '', 22)) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== HOW IT WORKS ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="justify-content:center;">How It Works</span>
            <h2 class="section-title">From Application to Certificate</h2>
            <p class="section-subtitle">A simple, supportive process designed to get you contributing — and learning — quickly.</p>
        </div>
        <div class="grid grid-4">
            <?php foreach ($steps as $i => $step): $num = $i + 1; ?>
                <div class="card-3d reveal <?= 'delay-' . (($i % 4) + 1) ?>">
                    <div class="icon-badge" style="background:linear-gradient(135deg,<?= e($step['color'] ?? '#0B4E3D') ?>,#174D3D);font-weight:800;"><?= e(str_pad((string) $num, 2, '0', STR_PAD_LEFT)) ?></div>
                    <h3 class="card-title"><?= e($step['title'] ?? '') ?></h3>
                    <p class="card-text"><?= e($step['text'] ?? '') ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== IMPACT COUNTERS ============================== -->
<section class="section section-brand">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="justify-content:center;color:#dbeafe;">Our Intern Community</span>
            <h2 class="section-title text-white">A Growing Movement of Changemakers</h2>
            <p class="section-subtitle" style="color:rgba(255,255,255,.9);">Every intern adds their energy to our cause — and takes away skills and memories that last a lifetime.</p>
        </div>
        <div class="counter-grid">
            <?php foreach ($stats as $c): ?>
                <div class="counter-item reveal">
                    <div class="icon-badge" style="background:rgba(255,255,255,.18);"><?= lucide($c['icon'] ?? 'star') ?></div>
                    <div class="counter-value"><?= e($c['prefix'] ?? '') ?><span data-counter="<?= (int) ($c['value'] ?? 0) ?>">0</span><?= e($c['suffix'] ?? '') ?></div>
                    <div class="counter-label"><?= e($c['title'] ?? '') ?></div>
                    <?php if (!empty($c['description'])): ?>
                        <p style="color:rgba(255,255,255,.8);font-size:.85rem;margin-top:.35rem;"><?= e($c['description']) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== ELIGIBILITY + APPLICATION FORM ============================== -->
<section class="section" id="apply">
    <?php /* Not .grid-sidebar — that pins the second column to 320px, which
             squeezed every .form-row in the application card down to a single
             cramped column. A balanced split lets the two-up fields breathe. */ ?>
    <div class="container intern-apply-grid items-start">
        <!-- Eligibility / who should apply -->
        <div class="reveal">
            <span class="eyebrow">Before You Apply</span>
            <h2 class="section-title">Who Should Apply</h2>
            <p class="text-muted">Our internships are open to anyone with the drive to serve. You do not need prior experience — just commitment, curiosity and a willingness to learn.</p>
            <ul class="list-check mt-3">
                <li>Students &amp; recent graduates from any field of study</li>
                <li>A genuine interest in social work and community development</li>
                <li>Ability to commit to your chosen term (1-3, 3-6 or 6+ months)</li>
                <li>Basic communication skills and a collaborative mindset</li>
                <li>Reliable access to email for on-site or remote coordination</li>
            </ul>
            <div class="card mt-4" style="background:var(--grad-accent);color:#fff;">
                <div class="card-body">
                    <h3 class="card-title text-white">Need Help?</h3>
                    <p style="color:rgba(255,255,255,.92);">Questions about the programme or your application? Reach our team directly.</p>
                    <p class="mt-2" style="color:#fff;">
                        <?= lucide('mail') ?> <a href="mailto:<?= e(get_setting('contact_email', SITE_EMAIL)) ?>" style="color:#fff;text-decoration:underline;"><?= e(get_setting('contact_email', SITE_EMAIL)) ?></a><br>
                        <?= lucide('phone') ?> <a href="tel:<?= e(preg_replace('/\s+/', '', get_setting('contact_phone', SITE_PHONE))) ?>" style="color:#fff;text-decoration:underline;"><?= e(get_setting('contact_phone', SITE_PHONE)) ?></a>
                    </p>
                </div>
            </div>
        </div>

        <!-- Application form -->
        <div class="card reveal delay-1">
            <div class="card-body">
                <h2 class="section-title mb-1">Internship Application</h2>
                <p class="text-muted mb-3">Fill in your details below. Fields marked <span class="req">*</span> are required. We usually respond within a week.</p>

                <form data-ajax-form data-endpoint="<?= e(url('forms/internship')) ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="text" name="pwf_zq" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;height:0;width:0;overflow:hidden">

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="in-name">Full Name <span class="req">*</span></label>
                            <input class="form-control" id="in-name" name="name" type="text" maxlength="128" placeholder="Your full name" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="in-email">Email <span class="req">*</span></label>
                            <input class="form-control" id="in-email" name="email" type="email" maxlength="191" placeholder="you@example.com" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <?= country_field(['name' => 'phone', 'label' => 'Phone', 'required' => true]) ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="in-education">Education / Qualification <span class="req">*</span></label>
                            <input class="form-control" id="in-education" name="education" type="text" maxlength="191" placeholder="e.g. B.A. Sociology (2nd year)" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="in-institution">College / Institution</label>
                        <input class="form-control" id="in-institution" name="institution" type="text" maxlength="191" placeholder="Name of your college or university">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="in-duration">Preferred Duration</label>
                            <select class="form-select" id="in-duration" name="duration">
                                <option value="">Select duration</option>
                                <option value="1-3 months">1 - 3 months</option>
                                <option value="3-6 months">3 - 6 months</option>
                                <option value="6+ months">6+ months</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="in-start">Preferred Start Date</label>
                            <input class="form-control" id="in-start" name="start_date" type="date" min="<?= e(date('Y-m-d')) ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="in-area">Area of Interest</label>
                        <select class="form-select" id="in-area" name="area_of_interest">
                            <option value="">Select a focus area</option>
                            <?php foreach ($interestOptions as $opt): ?>
                                <option value="<?= e($opt) ?>"><?= e($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="in-cover">Cover Letter / Why You Want to Intern</label>
                        <textarea class="form-textarea" id="in-cover" name="cover_letter" rows="5" placeholder="Tell us a little about yourself, your motivation and what you hope to learn."></textarea>
                        <small class="form-hint">A short paragraph is perfect — we care about your intent, not word count.</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="in-resume">Résumé / CV</label>
                        <input class="form-control" id="in-resume" name="resume" type="file" accept=".pdf,.doc,.docx">
                        <small class="form-hint">PDF, DOC or DOCX. Optional, but strongly recommended.</small>
                    </div>

                    <button class="btn btn-primary btn-block btn-lg" type="submit">Submit Application</button>
                    <p class="text-muted mt-2" style="font-size:.85rem;">By applying you agree to be contacted by our team about this internship. We respect your privacy and never share your details.</p>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- ============================== FAQ ============================== -->
<section class="section section-alt">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="justify-content:center;">Good to Know</span>
            <h2 class="section-title">Frequently Asked Questions</h2>
            <p class="section-subtitle">Answers to the questions applicants ask us most.</p>
        </div>
        <div class="grid" style="max-width:820px;margin-inline:auto;">
            <?php foreach ($faqs as $i => $faq): ?>
                <details class="card reveal <?= 'delay-' . (($i % 3) + 1) ?>" style="margin-bottom:.9rem;" <?= $i === 0 ? 'open' : '' ?>>
                    <summary style="cursor:pointer;padding:1rem 1.25rem;font-weight:700;list-style:none;"><?= e($faq['question']) ?></summary>
                    <div style="padding:0 1.25rem 1.25rem;">
                        <p class="text-muted" style="margin:0;"><?= e($faq['answer']) ?></p>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== CTA ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="card-3d reveal text-center" style="background:var(--grad-brand);color:#fff;max-width:900px;margin-inline:auto;">
            <span class="eyebrow" style="justify-content:center;color:#dbeafe;"><?= e(SITE_TAGLINE) ?></span>
            <h2 class="text-white">Ready to Make Your Time Count?</h2>
            <p style="color:rgba(255,255,255,.92);max-width:640px;margin-inline:auto;">Join a community of interns who are learning, leading and changing lives across Bihar. Your journey in social impact starts with a single application.</p>
            <div class="flex flex-wrap justify-center gap-2 mt-4">
                <a class="btn btn-white btn-lg" href="#apply"><?= lucide('graduation-cap') ?> Apply Now</a>
                <a class="btn btn-outline btn-lg" style="border-color:#fff;color:#fff;" href="<?= e(url('volunteer')) ?>"><?= lucide('handshake') ?> Volunteer Instead</a>
            </div>
        </div>
    </div>
</section>

<?php echo country_field_assets();
include __DIR__ . '/includes/footer.php'; ?>
