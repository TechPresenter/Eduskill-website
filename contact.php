<?php
/**
 * =============================================================================
 *  Contact Us — reach the foundation. All content is DB-driven (settings, faqs,
 *  social_links) with sensible fallbacks so the page looks complete pre-seeding.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* ---- Contact details (settings with SITE_* fallbacks) ----------------- */
$address = get_setting('contact_address', SITE_ADDRESS);
$email   = get_setting('contact_email',   SITE_EMAIL);
$phone   = get_setting('contact_phone',   SITE_PHONE);
$cin     = get_setting('cin',             SITE_CIN);
$telHref = preg_replace('/[^0-9+]/', '', $phone);

/* ---- Office hours (settings; "Label | Time" per line, else defaults) -- */
$defaultHours = [
    ['Monday – Friday', '9:00 AM – 6:00 PM'],
    ['Saturday',        '10:00 AM – 4:00 PM'],
    ['Sunday',          'Closed'],
];
$hoursSetting = trim((string) get_setting('office_hours', ''));
$hours = [];
if ($hoursSetting !== '') {
    foreach (preg_split('/\r\n|\r|\n/', $hoursSetting) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $parts   = array_map('trim', explode('|', $line, 2));
        $hours[] = [$parts[0], $parts[1] ?? ''];
    }
}
if (!$hours) {
    $hours = $defaultHours;
}

/* ---- Contact method cards --------------------------------------------- */
$contactCards = [
    [
        'icon'   => 'map-pin',
        'title'  => 'Visit Our Office',
        'value'  => $address,
        'action' => ['label' => 'Get Directions →', 'href' => 'https://www.google.com/maps?q=' . urlencode($address), 'blank' => true],
    ],
    [
        'icon'   => 'mail',
        'title'  => 'Email Us',
        'value'  => $email,
        'action' => ['label' => 'Send an Email →', 'href' => 'mailto:' . $email, 'blank' => false],
    ],
    [
        'icon'   => 'phone',
        'title'  => 'Call Us',
        'value'  => $phone,
        'action' => ['label' => 'Call Now →', 'href' => 'tel:' . $telHref, 'blank' => false],
    ],
    [
        'icon'   => 'landmark',
        'title'  => 'Registered NGO',
        'value'  => 'CIN: ' . $cin,
        'action' => ['label' => 'View Legal Details →', 'href' => url('ngo-details'), 'blank' => false],
    ],
];

/* ---- Subject options for the enquiry form ----------------------------- */
$subjects = [
    'General Enquiry',
    'Volunteering',
    'Donations & Partnerships',
    'Programs & Projects',
    'Media & Press',
    'Careers & Internships',
    'Other',
];

/* ---- Social links ------------------------------------------------------ */
$socials = get_all('social_links', ['status' => 1], 'sort_order ASC');

/* ---- FAQs (status = 1) with graceful fallback ------------------------- */
$faqs = db_all("SELECT * FROM faqs WHERE status = 1 ORDER BY sort_order ASC, id ASC");
if (!$faqs) {
    $faqs = [
        ['question' => 'How can I donate to EDUSKILL INDIA FOUNDATION?', 'answer' => 'You can contribute securely through our Donate page, or contact our office for bank-transfer and other options. Every contribution is acknowledged and tracked transparently — from donation to delivery.'],
        ['question' => 'Are donations eligible for tax benefits?', 'answer' => 'We issue a receipt for every donation. For details on the latest tax-exemption status and the documents you will receive, please email us or call the office and our team will assist you.'],
        ['question' => 'How can I volunteer or apply for an internship?', 'answer' => 'We would love to have you on board. Visit our Volunteer or Internship pages to submit an application, or simply mention your interest through the contact form and we will guide you through the next steps.'],
        ['question' => 'How soon will I receive a response?', 'answer' => 'We aim to reply to every enquiry within 1–2 business days. For urgent matters, calling the office during working hours is the fastest way to reach us.'],
        ['question' => 'Where does EDUSKILL INDIA FOUNDATION work?', 'answer' => 'We are headquartered in Patna, Bihar and run education, healthcare, skill-development and relief programs across communities in the region. Reach out to learn how we can collaborate in your area.'],
    ];
}

/* ---- SEO -------------------------------------------------------------- */
seo_set([
    'title'       => 'Contact Us',
    'description' => 'Get in touch with EDUSKILL INDIA FOUNDATION, Patna. Reach us by phone, email or visit our office. Send a message and our team will respond within 1–2 business days.',
    'page_key'    => 'contact',
]);

/* ---- Coloured hero banner --------------------------------------------- */
$page_hero = [
    'title'      => 'Contact Us',
    'subtitle'   => 'We would love to hear from you. Whether you want to support our work, partner with us, or simply learn more — reach out and let us create change together.',
    'breadcrumb' => [['label' => 'Contact Us']],
];

include __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="<?= e(asset('css/contact.css')) ?>">

<!-- ============================== CONTACT METHOD CARDS ============================== -->
<section class="section ct-methods" aria-labelledby="ctReach">
    <span class="ct-blob ct-b1" aria-hidden="true"></span>
    <span class="ct-blob ct-b2" aria-hidden="true"></span>
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Reach Out</span>
            <h2 class="section-title">Let's Start a Conversation</h2>
            <p class="section-subtitle">Choose whichever way is easiest for you — every message reaches our team directly.</p>
        </div>
        <div class="grid grid-4">
            <?php foreach ($contactCards as $i => $c): ?>
                <div class="ct-card reveal <?= 'delay-' . (($i % 4) + 1) ?>" style="--ct-i:<?= $i % 4 ?>">
                    <span class="ct-card-glow" aria-hidden="true"></span>
                    <div class="ct-ico"><?= lucide($c['icon']) ?></div>
                    <h3 class="ct-card-title"><?= e($c['title']) ?></h3>
                    <p class="ct-card-text"><?= e($c['value']) ?></p>
                    <a class="btn btn-ghost btn-sm mt-1" href="<?= e($c['action']['href']) ?>"<?= $c['action']['blank'] ? ' target="_blank" rel="noopener"' : '' ?>><?= e($c['action']['label']) ?></a>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Official office address, exactly as registered -->
        <div class="office-block reveal">
            <div class="ob-card">
                <span class="ob-ico"><?= lucide('building-2') ?></span>
                <div class="ob-body">
                    <span class="eyebrow">Our Office</span>
                    <address class="ob-addr">
                        <a class="ob-phone" href="tel:<?= e($telHref) ?>"><?= lucide('phone') ?> <?= e($phone) ?></a>
                        <span>International Financial Hub (IFH)</span>
                        <span>Plot No. IIF/04, Action Area II</span>
                        <span>New Town, Kolkata &ndash; 700156</span>
                        <span>West Bengal, India</span>
                        <a class="ob-mail" href="mailto:<?= e($email) ?>"><?= lucide('mail') ?> <?= e($email) ?></a>
                    </address>
                </div>
            </div>
            <?php
            // Registered office is optional — it is blank by default. Render the
            // heading and <address> only when one is configured, otherwise the card
            // falls back to showing just the statutory identifiers below.
            $regOffice = trim((string) get_setting('registered_office', SITE_REG_OFFICE));
            $regLines  = $regOffice === '' ? [] : array_filter(array_map('trim', explode(',', $regOffice)));
            ?>
            <div class="ob-card ob-reg">
                <span class="ob-ico green"><?= lucide('landmark') ?></span>
                <div class="ob-body">
                    <span class="eyebrow"><?= $regLines ? 'Registered Office' : 'Registration Details' ?></span>
                    <?php if ($regLines): ?>
                    <address class="ob-addr">
                        <?php foreach ($regLines as $line): ?>
                            <span><?= e(rtrim($line, '.')) ?></span>
                        <?php endforeach; ?>
                    </address>
                    <?php endif; ?>
                    <div class="ob-ids">
                        <span><?= lucide('id-card') ?> CIN: <b><?= e(get_setting('cin', SITE_CIN)) ?></b></span>
                        <span><?= lucide('badge-check') ?> NGO Darpan: <b><?= e(get_setting('ngo_darpan_id', SITE_DARPAN_ID)) ?></b></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
    .office-block{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1.4rem;margin-top:2.2rem}
    .ob-card{display:flex;gap:1.1rem;align-items:flex-start;padding:1.6rem;border-radius:18px;background:var(--surface,#fff);
        border:1px solid var(--border,#e5e7eb);box-shadow:0 1px 2px rgba(11,78,61,.05),0 18px 40px -30px rgba(11,78,61,.45);
        transition:transform .25s ease,box-shadow .25s ease,border-color .25s ease}
    .ob-card:hover{transform:translateY(-4px);border-color:var(--primary,#0B4E3D);box-shadow:0 24px 52px -28px rgba(11,78,61,.5)}
    .ob-ico{flex:0 0 auto;width:52px;height:52px;border-radius:15px;display:grid;place-items:center;
        background:rgba(11,78,61,.1);color:var(--primary,#0B4E3D)}
    .ob-ico.green{background:rgba(47,128,101,.13);color:var(--green,#2F8065)}
    .ob-ico svg{width:25px;height:25px}
    .ob-addr{display:flex;flex-direction:column;gap:.3rem;font-style:normal;margin:.4rem 0 0;line-height:1.55}
    .ob-addr span{color:var(--text-soft,#374151);font-weight:600}
    .ob-phone,.ob-mail{display:inline-flex;align-items:center;gap:.45rem;font-weight:800;color:var(--primary,#0B4E3D);text-decoration:none;transition:color .2s}
    .ob-phone:hover,.ob-mail:hover{color:var(--secondary,#174D3D)}
    .ob-phone svg,.ob-mail svg{width:1.05em;height:1.05em}
    .ob-mail{margin-top:.3rem}
    .ob-ids{display:flex;flex-wrap:wrap;gap:.4rem 1rem;margin-top:.85rem;padding-top:.85rem;border-top:1px solid var(--border,#e5e7eb)}
    .ob-ids span{display:inline-flex;align-items:center;gap:.4rem;font-size:.82rem;color:var(--muted,#6b7280)}
    .ob-ids svg{width:1em;height:1em}
    .ob-ids b{color:var(--text,#0B4E3D);font-weight:800}
    </style>
</section>

<!-- ============================== FORM + MAP ============================== -->
<section class="section ct-formsec" aria-labelledby="ctWrite">
    <span class="ct-blob ct-b3" aria-hidden="true"></span>
    <div class="container grid grid-2 gap-4 items-start">
        <!-- Enquiry form -->
        <div class="reveal">
            <span class="eyebrow">Send a Message</span>
            <h2 class="section-title">Write to Us</h2>
            <p class="text-muted">Fill in the form below and our team will get back to you within 1–2 business days. Fields marked <span class="req">*</span> are required.</p>

            <form data-ajax-form data-endpoint="<?= e(url('forms/contact')) ?>" class="mt-3">
                <?= csrf_field() ?>
                <input type="text" name="website_hp" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;height:0;width:0;overflow:hidden">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label req" for="cf-name">Full Name</label>
                        <input class="form-control" id="cf-name" name="name" type="text" maxlength="128" placeholder="Your name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label req" for="cf-email">Email Address</label>
                        <input class="form-control" id="cf-email" name="email" type="email" placeholder="you@example.com" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <?= country_field(['name' => 'phone', 'label' => 'Phone Number']) ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="cf-subject">Subject</label>
                        <select class="form-select" id="cf-subject" name="subject">
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?= e($s) ?>"><?= e($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label req" for="cf-message">Your Message</label>
                    <textarea class="form-textarea" id="cf-message" name="message" rows="5" minlength="5" maxlength="5000" placeholder="How can we help you?" required></textarea>
                    <span class="form-hint">We respect your privacy — your details are never shared with third parties.</span>
                </div>
                <button class="btn btn-primary btn-lg btn-block" type="submit">Send Message <?= lucide('mail') ?></button>
            </form>
        </div>

        <!-- Map + office hours -->
        <div class="grid gap-3 reveal delay-1">
            <div class="map-embed">
                <iframe title="Our location on Google Maps" loading="lazy" allowfullscreen
                    src="https://www.google.com/maps?q=<?= e(urlencode(get_setting('contact_address', SITE_ADDRESS))) ?>&output=embed"></iframe>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="icon-badge accent" style="width:40px;height:40px;font-size:1rem;"><?= lucide('clock') ?></span>
                        <h3 class="card-title mb-0">Office Hours</h3>
                    </div>
                    <table class="table">
                        <tbody>
                            <?php foreach ($hours as $h): ?>
                                <tr>
                                    <th style="text-align:left;font-weight:600;"><?= e($h[0]) ?></th>
                                    <td style="text-align:right;<?= (stripos($h[1], 'closed') !== false) ? 'color:var(--danger-600,#dc2626);font-weight:600;' : '' ?>"><?= e($h[1]) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="text-muted mt-2" style="font-size:.9rem;">All times are in India Standard Time (IST).</p>

                    <?php if ($socials): ?>
                        <div class="divider"></div>
                        <strong>Connect With Us</strong>
                        <div class="contact-socials mt-2">
                            <?php foreach ($socials as $s): ?>
                                <a class="contact-social <?= e(strtolower($s['platform'])) ?>" href="<?= e(safe_href($s['url'])) ?>" target="_blank" rel="noopener" aria-label="<?= e(ucfirst($s['platform'])) ?>" title="<?= e(ucfirst($s['platform'])) ?>"><?= social_svg($s['platform']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================== HELP CTA BAND ============================== -->
<section class="section">
    <div class="container grid grid-2 gap-4">
        <div class="card-3d reveal" style="background:var(--grad-brand);color:#fff;">
            <div class="icon-badge" style="background:rgba(255,255,255,.18);"><?= lucide('handshake') ?></div>
            <h2 class="text-white">Want to Get Involved?</h2>
            <p style="color:rgba(255,255,255,.9);">Volunteer your time or apply for an internship and become part of a community of changemakers across Bihar.</p>
            <div class="flex flex-wrap gap-2 mt-2">
                <a class="btn btn-white" href="<?= e(url('volunteer')) ?>">Become a Volunteer</a>
                <a class="btn btn-outline" style="border-color:#fff;color:#fff;" href="<?= e(url('internship')) ?>">Apply for Internship</a>
            </div>
        </div>
        <div class="card-3d reveal delay-1" style="background:var(--grad-accent);color:#fff;">
            <div class="icon-badge accent" style="background:rgba(255,255,255,.18);"><?= lucide('heart') ?></div>
            <h2 class="text-white">Support Our Mission</h2>
            <p style="color:rgba(255,255,255,.92);">Every contribution helps us reach more children, families and communities with the resources they need to thrive.</p>
            <a class="btn btn-white btn-lg mt-2" href="<?= e(url('donate')) ?>">Donate Now</a>
        </div>
    </div>
</section>

<!-- ============================== FAQ ACCORDION ============================== -->
<section class="section section-soft">
    <div class="container container-narrow">
        <div class="section-head">
            <span class="eyebrow">Good to Know</span>
            <h2 class="section-title">Frequently Asked Questions</h2>
            <p class="section-subtitle">Quick answers to the questions we hear most often. Still curious? Send us a message above.</p>
        </div>

        <div class="contact-faqs reveal">
            <?php foreach ($faqs as $i => $f): ?>
                <details class="contact-faq"<?= $i === 0 ? ' open' : '' ?>>
                    <summary><?= e($f['question']) ?></summary>
                    <div class="faq-a"><?= rich_text($f['answer']) ?></div>
                </details>
            <?php endforeach; ?>
        </div>

        <p class="text-center text-muted mt-4">
            Can't find what you're looking for?
            <a class="link-action" href="mailto:<?= e($email) ?>" style="color:var(--brand-600);font-weight:700;">Email us directly</a>.
        </p>
    </div>
</section>

<!-- One-off styling for the FAQ accordion (no documented equivalent) -->
<style>
    .contact-faq {
        border: 1px solid var(--border, #e5e7eb);
        border-radius: 14px;
        background: var(--surface, #fff);
        margin-bottom: .85rem;
        overflow: hidden;
        transition: box-shadow .2s ease, border-color .2s ease;
    }
    .contact-faq[open] {
        border-color: var(--brand-300, #7BB8EC);
        box-shadow: var(--shadow-md, 0 10px 30px rgba(2, 6, 23, .07));
    }
    .contact-faq summary {
        list-style: none;
        cursor: pointer;
        padding: 1.15rem 1.35rem;
        font-weight: 700;
        color: var(--text, #0f172a);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }
    .contact-faq summary::-webkit-details-marker { display: none; }
    .contact-faq summary::after {
        content: '+';
        font-size: 1.5rem;
        line-height: 1;
        color: var(--brand-600, #0B4E3D);
        transition: transform .2s ease;
        flex: 0 0 auto;
    }
    .contact-faq[open] summary::after { transform: rotate(45deg); }
    .contact-faq .faq-a {
        padding: 0 1.35rem 1.25rem;
        color: var(--text-soft, #475569);
        line-height: 1.7;
    }
</style>

<?php echo country_field_assets();
include __DIR__ . '/includes/footer.php'; ?>
