<?php
/**
 * =============================================================================
 *  Become a Partner — corporate, CSR, NGO and individual partnership landing
 *  page + application form.
 *
 *  Every section is DB-driven with strong, NGO-appropriate fallbacks so the
 *  page looks complete and polished before any content is seeded. The tiers
 *  come from the JSON setting `partner_tiers`; the application form posts (AJAX)
 *  to forms/partner and maps 1:1 to the `partner_applications` table columns.
 *
 *  Sections: overview · impact counters · why partner (icon-box) ·
 *  partnership tiers (pricing cards) · benefits (list-check) · how it works ·
 *  application form · existing partners marquee · CTA.
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

/* ---- Why partner (value proposition icon-box grid) --------------------- */
$reasons = $list_setting('partner_reasons', [
    ['icon' => 'target', 'cls' => '',  'title' => 'Measurable Impact',      'text' => 'Every rupee and hour is tracked from commitment to delivery, with transparent reporting you can share with your board and stakeholders.'],
    ['icon' => 'handshake', 'cls' => 'p', 'title' => 'Trusted On The Ground',  'text' => 'A registered non-profit with deep community roots across Bihar — your programmes reach the people who need them most.'],
    ['icon' => 'sprout', 'cls' => 'g', 'title' => 'Sustainable Change',     'text' => 'We design initiatives for lasting outcomes, not one-off charity — building capability that continues long after we arrive.'],
    ['icon' => 'megaphone', 'cls' => 'o', 'title' => 'Brand & Visibility',     'text' => 'Meaningful CSR storytelling, co-branded campaigns and recognition that showcase your commitment to social good.'],
    ['icon' => 'receipt', 'cls' => '',  'title' => 'Compliance Ready',       'text' => 'CSR-eligible engagements with proper documentation, utilisation certificates and audit-friendly transparency.'],
    ['icon' => 'rocket', 'cls' => 'p', 'title' => 'Scale With Confidence',  'text' => 'A dedicated relationship team, clear milestones and the operational capacity to grow your impact year on year.'],
    ['icon' => 'users', 'cls' => 'g', 'title' => 'Employee Engagement',    'text' => 'Volunteering days, field visits and skill-based involvement that connect your team directly to the cause.'],
    ['icon' => 'award', 'cls' => 'o', 'title' => 'Recognition',            'text' => 'Certificates, impact reports and public acknowledgement across our website, events and annual report.'],
]);

/* ---- Partnership impact counters --------------------------------------- */
$stats = $list_setting('partner_stats', [
    ['icon' => 'handshake', 'value' => 60,    'suffix' => '+', 'title' => 'Active Partners',    'description' => 'Corporates, NGOs & institutions'],
    ['icon' => 'users', 'value' => 25000, 'suffix' => '+', 'title' => 'Lives Impacted',     'description' => 'Through partner-backed programmes'],
    ['icon' => 'home', 'value' => 60,    'suffix' => '+', 'title' => 'Villages Reached',   'description' => 'Across Bihar and beyond'],
    ['icon' => 'bar-chart-3', 'value' => 100,   'suffix' => '%', 'title' => 'Fund Transparency',  'description' => 'Every contribution accounted for'],
]);

/* ---- Partnership tiers (pricing-style cards) --------------------------- */
$tiers = $list_setting('partner_tiers', [
    [
        'name'     => 'Strategic Partner',
        'icon'     => 'globe',
        'accent'   => 'p',
        'tagline'  => 'For corporates & large institutions',
        'price'    => 'Custom',
        'period'   => 'multi-year engagement',
        'featured' => false,
        'features' => [
            'Co-designed, multi-year impact programme',
            'Dedicated relationship & reporting manager',
            'Quarterly impact reviews and field visits',
            'Co-branded campaigns and joint PR',
            'Prominent recognition across all channels',
        ],
    ],
    [
        'name'     => 'CSR Partner',
        'icon'     => 'building-2',
        'accent'   => '',
        'tagline'  => 'For CSR-mandated companies',
        'price'    => 'CSR Funded',
        'period'   => 'per project / annual',
        'featured' => true,
        'features' => [
            'CSR-eligible projects with full compliance',
            'Utilisation certificates & audited reports',
            'Employee volunteering & engagement days',
            'Impact dashboard and photo documentation',
            'Logo & mention in our annual report',
        ],
    ],
    [
        'name'     => 'NGO Collaboration',
        'icon'     => 'handshake',
        'accent'   => 'g',
        'tagline'  => 'For fellow non-profits & networks',
        'price'    => 'Mutual',
        'period'   => 'programme-based',
        'featured' => false,
        'features' => [
            'Joint programmes & shared field resources',
            'Knowledge, training and capacity exchange',
            'Co-applications for grants and funding',
            'Wider reach through combined networks',
            'Cross-promotion of shared initiatives',
        ],
    ],
    [
        'name'     => 'Individual Champion',
        'icon'     => 'hand',
        'accent'   => 'o',
        'tagline'  => 'For philanthropists & professionals',
        'price'    => 'Flexible',
        'period'   => 'monthly or one-time',
        'featured' => false,
        'features' => [
            'Support a cause or programme you care about',
            'Regular impact updates on your contribution',
            'Invitations to events and field visits',
            'Recognition as a valued supporter',
            'Pro-bono skills & mentorship opportunities',
        ],
    ],
]);

/* Tier names power the application form's select, so the two always match. */
$tierOptions = [];
foreach ($tiers as $t) {
    if (!empty($t['name'])) {
        $tierOptions[] = (string) $t['name'];
    }
}
if (!in_array('Other / Not Sure', $tierOptions, true)) {
    $tierOptions[] = 'Other / Not Sure';
}

/* ---- Benefits (what partners receive) ---------------------------------- */
$benefits = $list_setting('partner_benefits', [
    'Transparent, audit-ready reporting on every rupee and outcome',
    'CSR compliance support with utilisation and 80G documentation',
    'Co-branded campaigns, case studies and social-media features',
    'Employee volunteering, field visits and skill-based engagement',
    'Recognition on our website, at events and in our annual report',
    'A dedicated point of contact for a smooth, responsive partnership',
    'Flexible engagement models — from one project to multi-year programmes',
    'Real stories and photo documentation to share with your stakeholders',
]);

/* ---- How the partnership works (process steps) ------------------------- */
$process = $list_setting('partner_process', [
    ['title' => 'Reach Out',        'text' => 'Share your interest and goals through the form below. Tell us what impact matters most to your organisation.'],
    ['title' => 'Discovery Call',   'text' => 'We meet to understand your objectives, CSR focus and budget, and explore where we can create the most value together.'],
    ['title' => 'Co-Design',        'text' => 'Our team proposes a tailored programme with clear milestones, timelines, deliverables and a transparent budget.'],
    ['title' => 'Launch & Deliver', 'text' => 'We roll out the initiative on the ground, keeping you updated at every step with data and stories.'],
    ['title' => 'Report & Grow',    'text' => 'You receive detailed impact and utilisation reports — and together we plan how to scale the difference we make.'],
]);

/* ---- Existing partners & sponsors (logo marquee) ----------------------- */
$partners = get_all('partners', ['status' => 1], 'sort_order ASC');
$sponsors = get_all('sponsors', ['status' => 1], 'sort_order ASC');
$logos    = array_merge($partners ?: [], $sponsors ?: []);

/* Honest category labels for the marquee when no partner logos are seeded
 * yet — these are partnership types, not fabricated organisations. */
$logoFallback = [
    'Corporate CSR', 'Government Bodies', 'Educational Institutions',
    'Healthcare Partners', 'Grassroots NGOs', 'Community Groups',
    'Individual Champions', 'Foundations & Trusts',
];

/* ---- SEO --------------------------------------------------------------- */
seo_set([
    'title'       => 'Become a Partner',
    'description' => 'Partner with EDUSKILL INDIA FOUNDATION in Patna, Bihar. Explore CSR, strategic, NGO and individual partnership tiers to create measurable, transparent and lasting social impact across education, healthcare and community development.',
    'page_key'    => 'become-partner',
    'type'        => 'website',
]);

/* ---- Hero banner ------------------------------------------------------- */
$page_hero = [
    'title'      => 'Become a Partner',
    'subtitle'   => 'Join hands with EDUSKILL INDIA FOUNDATION to turn intent into measurable, lasting impact for communities across Bihar.',
    'breadcrumb' => [
        ['label' => 'Get Involved', 'url' => url('volunteer')],
        ['label' => 'Become a Partner'],
    ],
];

include __DIR__ . '/includes/header.php';
?>

<!-- ============================== OVERVIEW ============================== -->
<section class="section">
    <div class="container grid grid-2 items-center gap-4">
        <div class="reveal">
            <span class="eyebrow">Partnership</span>
            <h2 class="section-title">Greater Impact, <span class="text-grad-ocean">Achieved Together</span></h2>
            <p class="lead">Real, lasting change is never built alone. When purpose-driven organisations and individuals join us, communities move further, faster.</p>
            <p class="text-muted">At <?= e(SITE_NAME) ?>, partnership is more than funding — it is a shared commitment to dignity, transparency and results. Whether you are a company fulfilling its CSR mandate, a fellow non-profit seeking collaboration, or an individual who wants to give back, we build engagements that are meaningful for you and transformative for the people we serve. Every partnership is grounded in measurable outcomes, open reporting and genuine care.</p>
            <ul class="list-check mt-3">
                <li>Registered non-profit with proven grassroots delivery</li>
                <li>Transparent, audit-ready reporting on every contribution</li>
                <li>Flexible models — from single projects to multi-year programmes</li>
            </ul>
            <div class="flex flex-wrap gap-2 mt-4">
                <a class="btn btn-3d" href="#apply">Start a Partnership</a>
                <a class="btn btn-outline" href="#tiers">Explore Tiers</a>
            </div>
        </div>
        <div class="reveal delay-1">
            <div class="glass-card" style="position:relative;overflow:hidden;padding:2rem;">
                <span class="blob b2" style="top:-50px;right:-40px;"></span>
                <div style="position:relative;">
                    <span class="eyebrow">Why It Matters</span>
                    <h3 class="card-title mb-2">Partnership, With Purpose</h3>
                    <p class="card-text mb-3">“Alone we can do so little; together we can do so much.” Our partners help us reach one more child, one more family, one more village — with accountability at every step.</p>
                    <div class="divider"></div>
                    <div class="grid grid-2 gap-3 mt-3">
                        <div>
                            <div class="counter-value text-grad-purple" style="font-size:1.9rem;font-weight:800;line-height:1;"><span data-counter="60">0</span>+</div>
                            <small class="text-muted">Active partners</small>
                        </div>
                        <div>
                            <div class="counter-value text-grad-ocean" style="font-size:1.9rem;font-weight:800;line-height:1;">₹<span data-counter="100">0</span>%</div>
                            <small class="text-muted">Funds accounted for</small>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2 mt-4">
                        <span class="chip">CSR Ready</span>
                        <span class="chip">80G Documentation</span>
                        <span class="chip">Impact Reports</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================== IMPACT COUNTERS ============================== -->
<section class="section section-brand">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="justify-content:center;color:#dbeafe;">The Difference We Make Together</span>
            <h2 class="section-title text-white">Partnership That Adds Up</h2>
            <p class="section-subtitle" style="color:rgba(255,255,255,.9);">Behind every figure is a partner who chose to turn commitment into real change on the ground.</p>
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

<!-- ============================== WHY PARTNER ============================== -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="justify-content:center;">Why Partner With Us</span>
            <h2 class="section-title">Built for Trust, Designed for Impact</h2>
            <p class="section-subtitle">We make it easy for you to do good well — with the transparency, capacity and care your organisation deserves.</p>
        </div>
        <div class="grid grid-4">
            <?php foreach ($reasons as $i => $r): ?>
                <div class="icon-box glass-card reveal <?= 'delay-' . (($i % 4) + 1) ?> <?= e($r['cls'] ?? '') ?>">
                    <div class="ib-icon"><?= lucide($r['icon'] ?? 'star') ?></div>
                    <h3 class="card-title"><?= e($r['title'] ?? '') ?></h3>
                    <p class="card-text"><?= e($r['text'] ?? '') ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== PARTNERSHIP TIERS ============================== -->
<section class="section section-soft" id="tiers">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="justify-content:center;">Ways to Partner</span>
            <h2 class="section-title">Partnership Tiers</h2>
            <p class="section-subtitle">Choose the engagement that fits your goals and capacity. Every tier is flexible — we shape the details around you.</p>
        </div>
        <div class="grid grid-4 items-stretch tier-grid">
            <?php foreach ($tiers as $i => $t):
                $accent   = (string) ($t['accent'] ?? '');
                $featured = !empty($t['featured']);
                $features = (isset($t['features']) && is_array($t['features'])) ? $t['features'] : [];
                ?>
                <div class="glass-card reveal <?= 'delay-' . (($i % 4) + 1) ?>"
                     style="display:flex;flex-direction:column;position:relative;<?= $featured ? 'outline:2px solid var(--brand-600);box-shadow:var(--shadow-brand);' : '' ?>">
                    <?php if ($featured): ?>
                        <span class="badge badge-accent" style="position:absolute;top:-12px;right:1.25rem;">Most Popular</span>
                    <?php endif; ?>
                    <div class="icon-box <?= e($accent) ?>" style="padding:.5rem 0 1rem;">
                        <div class="ib-icon"><?= lucide($t['icon'] ?? 'handshake') ?></div>
                    </div>
                    <h3 class="card-title text-center mb-1"><?= e($t['name'] ?? 'Partner') ?></h3>
                    <?php if (!empty($t['tagline'])): ?>
                        <p class="text-muted text-center" style="font-size:.9rem;"><?= e($t['tagline']) ?></p>
                    <?php endif; ?>
                    <div class="text-center mt-2 mb-2">
                        <span class="text-gradient" style="font-size:1.6rem;font-weight:800;"><?= e($t['price'] ?? 'Custom') ?></span>
                        <?php if (!empty($t['period'])): ?>
                            <div><small class="text-muted"><?= e($t['period']) ?></small></div>
                        <?php endif; ?>
                    </div>
                    <div class="divider"></div>
                    <?php if ($features): ?>
                        <ul class="list-check mt-3" style="flex:1;">
                            <?php foreach ($features as $f): ?>
                                <li><?= e((string) $f) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <a class="btn <?= $featured ? 'btn-3d' : 'btn-outline' ?> btn-block mt-4"
                       href="#apply"
                       onclick="var s=document.getElementById('pf-tier');if(s){s.value=this.getAttribute('data-tier');}"
                       data-tier="<?= e($t['name'] ?? '') ?>">Choose <?= e($t['name'] ?? 'This') ?></a>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="text-center text-muted mt-4" style="max-width:640px;margin-inline:auto;">Not sure which tier fits? That is completely fine — reach out below and our team will help you find the right way to get involved.</p>
    </div>
</section>

<!-- ============================== BENEFITS ============================== -->
<section class="section">
    <div class="container grid grid-2 items-center gap-4">
        <div class="reveal">
            <span class="eyebrow">What You Get</span>
            <h2 class="section-title">Benefits of Partnering With Us</h2>
            <p class="text-muted">A partnership with <?= e(SITE_NAME) ?> is designed to be as rewarding for you as it is for the communities we serve — professional, transparent and genuinely impactful.</p>
            <ul class="list-check mt-3">
                <?php foreach ($benefits as $b): ?>
                    <li><?= e((string) $b) ?></li>
                <?php endforeach; ?>
            </ul>
            <div class="flex flex-wrap gap-2 mt-4">
                <a class="btn btn-3d" href="#apply">Become a Partner</a>
                <a class="btn btn-outline" href="<?= e(url('contact')) ?>">Talk to Our Team</a>
            </div>
        </div>
        <div class="reveal delay-1">
            <div class="card-3d" style="background:var(--grad-brand);color:#fff;">
                <div class="icon-badge" style="background:rgba(255,255,255,.2);"><?= lucide('message-square') ?></div>
                <h3 class="card-title text-white">A Partnership You Can Trust</h3>
                <p style="color:rgba(255,255,255,.92);">“The best partnerships are built on transparency. With EduSkill India, every contribution is documented and every outcome is shared — openly and honestly.”</p>
                <div class="divider" style="border-color:rgba(255,255,255,.25);"></div>
                <div class="flex flex-wrap gap-2 mt-3">
                    <span class="chip" style="background:rgba(255,255,255,.16);color:#fff;"><?= lucide('circle-check') ?> Utilisation Certificates</span>
                    <span class="chip" style="background:rgba(255,255,255,.16);color:#fff;"><?= lucide('trending-up') ?> Impact Dashboards</span>
                    <span class="chip" style="background:rgba(255,255,255,.16);color:#fff;"><?= lucide('handshake') ?> Dedicated Manager</span>
                </div>
                <p class="mt-3" style="color:rgba(255,255,255,.92);">Questions first? Email
                    <a href="mailto:<?= e(get_setting('contact_email', SITE_EMAIL)) ?>" style="color:#fff;text-decoration:underline;"><?= e(get_setting('contact_email', SITE_EMAIL)) ?></a>
                    or call
                    <a href="tel:<?= e(preg_replace('/\s+/', '', get_setting('contact_phone', SITE_PHONE))) ?>" style="color:#fff;text-decoration:underline;"><?= e(get_setting('contact_phone', SITE_PHONE)) ?></a>.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ============================== HOW IT WORKS ============================== -->
<section class="section section-alt">
    <div class="container grid grid-2 gap-4 items-center">
        <div class="reveal">
            <span class="eyebrow">Simple &amp; Transparent</span>
            <h2 class="section-title">How the Partnership Works</h2>
            <p class="text-muted">From first conversation to measurable impact, we keep the journey clear, collaborative and accountable at every step.</p>
            <a class="btn btn-primary mt-3" href="#apply">Begin the Conversation</a>
        </div>
        <div class="reveal delay-1">
            <div class="process-steps">
                <?php foreach ($process as $step): ?>
                    <div class="process-step">
                        <h3 class="card-title mb-1"><?= e($step['title'] ?? '') ?></h3>
                        <p class="card-text"><?= e($step['text'] ?? '') ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- ============================== APPLICATION FORM ============================== -->
<section class="section" id="apply">
    <?php /* Not .grid-sidebar — that pins the second column to 320px, which
             crushed every .form-row in the enquiry card into a single cramped
             stack. A balanced split gives the form room to breathe. */ ?>
    <div class="container partner-apply-grid items-start">
        <!-- Context / reassurance -->
        <div class="reveal">
            <span class="eyebrow">Let's Talk</span>
            <h2 class="section-title">Start Your Partnership</h2>
            <p class="text-muted">Tell us a little about your organisation and what you would like to achieve. Our partnerships team will review your enquiry and get back to you — usually within a few working days.</p>
            <ul class="list-check mt-3">
                <li>No obligation — this is the start of a conversation</li>
                <li>Tailored proposals shaped around your goals and budget</li>
                <li>Your details are kept private and never shared</li>
            </ul>
            <div class="card mt-4" style="background:var(--grad-accent);color:#fff;">
                <div class="card-body">
                    <h3 class="card-title text-white">Prefer to Reach Out Directly?</h3>
                    <p style="color:rgba(255,255,255,.92);">Our team is happy to answer any questions before you apply.</p>
                    <p class="mt-2" style="color:#fff;">
                        <?= lucide('mail') ?> <a href="mailto:<?= e(get_setting('contact_email', SITE_EMAIL)) ?>" style="color:#fff;text-decoration:underline;"><?= e(get_setting('contact_email', SITE_EMAIL)) ?></a><br>
                        <?= lucide('phone') ?> <a href="tel:<?= e(preg_replace('/\s+/', '', get_setting('contact_phone', SITE_PHONE))) ?>" style="color:#fff;text-decoration:underline;"><?= e(get_setting('contact_phone', SITE_PHONE)) ?></a>
                    </p>
                </div>
            </div>
        </div>

        <!-- Partner application form -->
        <div class="glass-card reveal delay-1 partner-form-card">
            <h2 class="section-title mb-1">Partnership Enquiry</h2>
            <p class="text-muted mb-3">Fields marked <span class="req">*</span> are required.</p>

            <form data-ajax-form data-endpoint="<?= e(url('forms/partner')) ?>">
                <?= csrf_field() ?>
                <input type="text" name="website_hp" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;height:0;width:0;overflow:hidden">

                <div class="field-float">
                    <input type="text" id="pf-organization" name="organization" placeholder=" " maxlength="191" required>
                    <label for="pf-organization">Organisation / Company Name *</label>
                </div>

                <div class="form-row">
                    <div class="field-float">
                        <input type="text" id="pf-contact" name="contact_name" placeholder=" " maxlength="128" required>
                        <label for="pf-contact">Contact Person *</label>
                    </div>
                    <div class="field-float">
                        <input type="email" id="pf-email" name="email" placeholder=" " maxlength="191" required>
                        <label for="pf-email">Email Address *</label>
                    </div>
                </div>

                <div class="form-row">
                    <?php /* country_field() renders its own <label> and control.
                             It must NOT sit inside .field-float, whose absolutely
                             positioned label was overlapping the country selector,
                             and the stray <label for="pf-phone"> here pointed at an
                             input id that does not exist. */ ?>
                    <div class="pa-country">
                        <?= country_field(['name' => 'phone', 'label' => 'Phone Number']) ?>
                    </div>
                    <div class="field-float">
                        <input type="url" id="pf-website" name="website" placeholder=" " maxlength="191">
                        <label for="pf-website">Website</label>
                    </div>
                </div>

                <div class="field-float">
                    <select id="pf-tier" name="tier">
                        <option value="">Select a partnership tier</option>
                        <?php foreach ($tierOptions as $opt): ?>
                            <option value="<?= e($opt) ?>"><?= e($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="pf-tier">Preferred Partnership Tier</label>
                </div>

                <div class="field-float">
                    <textarea id="pf-message" name="message" placeholder=" " rows="5" maxlength="2000" required></textarea>
                    <label for="pf-message">How would you like to partner with us? *</label>
                </div>

                <button class="btn btn-3d btn-block btn-lg" type="submit">Submit Partnership Enquiry</button>
                <p class="text-muted mt-2" style="font-size:.85rem;">By submitting, you agree to be contacted by our team about this enquiry. We respect your privacy and never share your details.</p>
            </form>
        </div>
    </div>
</section>

<!-- ============================== EXISTING PARTNERS MARQUEE ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="justify-content:center;">In Good Company</span>
            <h2 class="section-title">Organisations We Work With</h2>
            <p class="section-subtitle">We are proud to collaborate with partners and supporters who share our commitment to lasting change.</p>
        </div>
    </div>
    <div class="marquee">
        <div class="marquee-track">
            <?php if ($logos): ?>
                <?php foreach ($logos as $org): ?>
                    <div class="logo-slide">
                        <?php if (!empty($org['logo'])): ?>
                            <?php if (!empty($org['website'])): ?>
                                <a href="<?= e(safe_href($org['website'])) ?>" target="_blank" rel="noopener" title="<?= e($org['name']) ?>">
                                    <img src="<?= e(upload_url($org['logo'])) ?>" alt="<?= e($org['name']) ?>" loading="lazy">
                                </a>
                            <?php else: ?>
                                <img src="<?= e(upload_url($org['logo'])) ?>" alt="<?= e($org['name']) ?>" loading="lazy">
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="chip" style="font-size:1rem;"><?= e($org['name']) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <?php foreach ($logoFallback as $label): ?>
                    <div class="logo-slide">
                        <span class="chip" style="font-size:1rem;"><?= e($label) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php if (!$logos): ?>
        <div class="container text-center mt-4">
            <p class="text-muted">Your organisation could be the next name here.
                <a href="#apply" style="font-weight:700;">Become a founding partner <?= lucide('arrow-right') ?></a>
            </p>
        </div>
    <?php endif; ?>
</section>

<!-- ============================== CTA ============================== -->
<section class="section">
    <div class="container">
        <div class="card-3d reveal text-center" style="background:var(--grad-brand);color:#fff;max-width:900px;margin-inline:auto;">
            <span class="eyebrow" style="justify-content:center;color:#dbeafe;"><?= e(SITE_TAGLINE) ?></span>
            <h2 class="text-white">Let's Build Something That Lasts</h2>
            <p style="color:rgba(255,255,255,.92);max-width:640px;margin-inline:auto;">Every great partnership begins with a single conversation. Take the first step today, and together we will create change that reaches further and lasts longer.</p>
            <div class="flex flex-wrap justify-center gap-2 mt-4">
                <a class="btn btn-white btn-lg" href="#apply"><?= lucide('handshake') ?> Become a Partner</a>
                <a class="btn btn-outline btn-lg" style="border-color:#fff;color:#fff;" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Make a Donation</a>
            </div>
        </div>
    </div>
</section>

<style>
/* ---------------------------------------------- Partnership tier cards ----
 * .glass-card carries no padding of its own, so tier content was sitting
 * flush against the card edge and reading as cramped. These rules are scoped
 * to .tier-grid so the ~40 other .glass-card uses across the site are
 * untouched.
 */
.tier-grid {
    /* Room for the "Most Popular" badge, which sits at top:-12px and was being
       clipped by the section edge. */
    padding-top: 1.25rem;
    gap: 1.5rem;
    align-items: stretch;
}
.tier-grid > .glass-card {
    padding: 1.75rem 1.5rem 1.5rem;
    overflow: visible;                 /* never clip the badge */
    transition: transform .28s ease, box-shadow .28s ease;
}
.tier-grid > .glass-card:hover { transform: translateY(-4px); }

/* Icon sat in a 2rem-padded box inside an already-padded card — double spacing. */
.tier-grid .icon-box { padding: 0 0 1rem !important; }
.tier-grid .icon-box .ib-icon { width: 56px; height: 56px; margin-bottom: .85rem; border-radius: 16px; }
.tier-grid .icon-box .ib-icon svg { width: 24px; height: 24px; }

.tier-grid .card-title { font-size: 1.06rem; margin-bottom: .3rem; }
.tier-grid .divider { margin: 1.15rem 0 .25rem; }

/* Feature list: more breathing room between items and a tighter tick. */
.tier-grid .list-check { gap: .85rem; margin-top: 1.1rem; }
.tier-grid .list-check li { padding-left: 1.7rem; font-size: .88rem; line-height: 1.5; }
.tier-grid .list-check li::before { width: 1.15rem; height: 1.15rem; font-size: .7rem; top: .12rem; }

.tier-grid .btn-block { margin-top: 1.5rem; }

/* The badge overlaps the card edge, so give it a solid ground and lift it clear. */
.tier-grid .badge-accent {
    top: -13px; right: 1.25rem; z-index: 3;
    padding: .34rem .8rem; border-radius: 999px;
    font-size: .68rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase;
    box-shadow: 0 6px 16px -6px rgba(230,123,29,.7);
    white-space: nowrap;
}

/* ------------------------------------------------ Partnership enquiry form -
 * The form previously sat in a 320px .grid-sidebar column, which forced every
 * two-up .form-row into one cramped stack and squeezed the country selector.
 */
.partner-apply-grid {
    display: grid;
    grid-template-columns: minmax(0, 0.85fr) minmax(0, 1.15fr);
    gap: clamp(1.75rem, 4vw, 3.5rem);
    max-width: 1240px;          /* wider than the default container */
    margin-inline: auto;
}
.partner-form-card { padding: clamp(1.75rem, 3vw, 2.75rem) !important; }
.partner-form-card .section-title { font-size: 1.5rem; }

/* Two-up fields, stacking only when genuinely narrow. */
.partner-form-card .form-row {
    display: grid; grid-template-columns: 1fr 1fr; gap: 1.15rem;
}
.partner-form-card .field-float,
.partner-form-card .pa-country { margin-bottom: 1.15rem; min-width: 0; }
.partner-form-card .field-float input,
.partner-form-card .field-float textarea,
.partner-form-card .field-float select { width: 100%; }
.partner-form-card textarea { min-height: 130px; }

/* Country selector: match the height and radius of the floating-label fields. */
.partner-form-card .pa-country .cs-label { font-size: .82rem; font-weight: 600; margin-bottom: .35rem; }
.partner-form-card .pa-country .cs-control { border-radius: 12px; }
.partner-form-card .pa-country .cs-phone { padding-block: .78rem; }

.partner-form-card .btn-block { margin-top: .5rem; }

@media (max-width: 1100px) { .tier-grid { grid-template-columns: repeat(2, 1fr); gap: 1.75rem; } }
@media (max-width: 620px)  { .tier-grid { grid-template-columns: 1fr; } }
@media (max-width: 992px)  { .partner-apply-grid { grid-template-columns: 1fr; } }
@media (max-width: 560px)  { .partner-form-card .form-row { grid-template-columns: 1fr; gap: 0; } }
@media (prefers-reduced-motion: reduce) {
    .tier-grid > .glass-card { transition: none; }
    .tier-grid > .glass-card:hover { transform: none; }
}
</style>

<?php echo country_field_assets();
include __DIR__ . '/includes/footer.php'; ?>
