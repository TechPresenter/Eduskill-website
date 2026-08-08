<?php
/**
 * =============================================================================
 *  Privacy Policy — CMS-driven with a complete, real fallback.
 *
 *  If an admin has published a `pages` row with slug 'privacy-policy', its body
 *  is rendered in a `.prose` block. Otherwise a full, generic NGO privacy
 *  policy (collection, use, cookies, third parties, security, rights, contact)
 *  is shown so the page is never blank before content is seeded.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* ---- Organisation identity (settings first, then config constants) ----- */
$siteName = get_setting('site_name', SITE_NAME);
$email    = get_setting('contact_email', SITE_EMAIL);
$phone    = get_setting('contact_phone', SITE_PHONE);
$address  = get_setting('contact_address', SITE_ADDRESS);

/* ---- CMS page (admin-managed) with graceful fallback ------------------- */
$page = db_row(
    "SELECT * FROM pages WHERE slug = :slug AND status = 'published' LIMIT 1",
    [':slug' => 'privacy-policy']
);

$hasContent   = $page && trim((string) ($page['content'] ?? '')) !== '';
$pageTitle    = $page['title']    ?? 'Privacy Policy';
$pageSubtitle = $page['subtitle'] ?? 'Your trust matters to us. This policy explains what information we collect, why we collect it, and the choices you have over it.';

/* Last-updated stamp — from the CMS row when present, else a settings default. */
$updatedRaw = !empty($page['updated_at']) ? $page['updated_at'] : get_setting('privacy_updated_at', '2026-01-01');
$updated    = format_date($updatedRaw, 'd F Y');
if ($updated === '') {
    $updated = (string) $updatedRaw;
}

/* ---- Privacy commitments (at-a-glance cards) --------------------------- */
$commitments = [
    ['icon' => 'lock', 'title' => 'Protected by Design', 'text' => 'Your personal data travels over encrypted connections and is stored on secured, access-controlled systems.'],
    ['icon' => 'target', 'title' => 'Used Only as Stated', 'text' => 'We use your information solely to run our programmes, respond to you and improve the work we do.'],
    ['icon' => 'ban', 'title' => 'Never Sold',           'text' => 'We never sell, rent or trade your personal information to advertisers or data brokers — ever.'],
    ['icon' => 'scale', 'title' => 'You Stay in Control',  'text' => 'Access, correct or delete your data and opt out of our communications whenever you choose.'],
];

/* ---- On-this-page navigation (used only for the fallback policy) ------- */
$toc = [
    'introduction' => 'Introduction',
    'collect'      => 'Information We Collect',
    'use'          => 'How We Use Your Information',
    'cookies'      => 'Cookies & Tracking',
    'sharing'      => 'How We Share Information',
    'security'     => 'Data Security',
    'retention'    => 'Data Retention',
    'rights'       => 'Your Rights & Choices',
    'children'     => 'Children’s Privacy',
    'links'        => 'Third-Party Links',
    'changes'      => 'Changes to This Policy',
    'contact'      => 'Contact Us',
];

/* ---- SEO --------------------------------------------------------------- */
seo_set([
    'title'       => 'Privacy Policy',
    'description' => 'How ' . $siteName . ' collects, uses, shares and protects your personal information — '
        . 'including cookies, third-party services, data security, retention and your privacy rights.',
    'page_key'    => 'privacy-policy',
    'type'        => 'website',
]);

$page_hero = [
    'title'      => $pageTitle,
    'subtitle'   => $pageSubtitle,
    'breadcrumb' => [['label' => 'Privacy Policy']],
];

include __DIR__ . '/includes/header.php';
?>

<!-- ============================== PRIVACY AT A GLANCE ============================== -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Our Promise</span>
            <h2 class="section-title">Your Privacy at a Glance</h2>
            <p class="section-subtitle">
                <?= e($siteName) ?> is committed to protecting the privacy of every donor, volunteer, beneficiary and
                visitor. Here are the principles that guide how we handle your information — the full policy follows below.
            </p>
        </div>

        <div class="grid grid-4">
            <?php foreach ($commitments as $i => $c): ?>
                <div class="card-3d reveal <?= 'delay-' . (($i % 4) + 1) ?> text-center">
                    <div class="icon-badge<?= $i % 2 ? ' accent' : '' ?>" style="margin-inline:auto;"><?= lucide($c['icon']) ?></div>
                    <h3 class="card-title" style="font-size:1.05rem;"><?= e($c['title']) ?></h3>
                    <p class="card-text"><?= e($c['text']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== POLICY BODY ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="grid grid-sidebar items-start">

            <!-- Main policy content -->
            <div class="reveal">
                <div class="flex items-center flex-wrap gap-2 mb-3">
                    <span class="badge badge-brand">Last updated: <?= e($updated) ?></span>
                    <span class="badge badge-muted">Applies to <?= e($siteName) ?> &amp; its website</span>
                </div>

                <?php if ($hasContent): ?>
                    <!-- Admin-managed content from the CMS -->
                    <div class="prose">
                        <?= rich_text($page['content']) ?>
                    </div>
                <?php else: ?>
                    <!-- Complete generic NGO privacy policy fallback -->
                    <div class="prose">
                        <h2 id="introduction">1. Introduction</h2>
                        <p>
                            <?= e($siteName) ?> (&ldquo;we&rdquo;, &ldquo;us&rdquo; or &ldquo;our&rdquo;) is a registered
                            non-profit organisation working across Bihar and beyond. We respect your privacy and are
                            committed to protecting the personal information you share with us. This Privacy Policy
                            explains what we collect, how we use and safeguard it, and the rights and choices available
                            to you. By using our website or engaging with our services, you agree to the practices
                            described here.
                        </p>

                        <h2 id="collect">2. Information We Collect</h2>
                        <p>We collect information only when it is necessary to serve you and further our charitable mission:</p>
                        <ul>
                            <li><strong>Information you give us</strong> — your name, email address, phone number, postal
                                address, PAN and payment details when you donate, volunteer, register for an event,
                                subscribe to our newsletter, apply for an internship, or contact us.</li>
                            <li><strong>Programme &amp; beneficiary information</strong> — details shared with us so that we
                                can deliver education, healthcare, relief and skill-development support responsibly.</li>
                            <li><strong>Information collected automatically</strong> — your IP address, browser type,
                                device information, pages visited and the date/time of your visit, gathered through
                                standard server logs and cookies.</li>
                            <li><strong>Information from third parties</strong> — confirmation of a completed payment from
                                our payment gateways, or details you choose to share through social-media platforms.</li>
                        </ul>

                        <h2 id="use">3. How We Use Your Information</h2>
                        <p>We use the information we collect to:</p>
                        <ul>
                            <li>process donations and issue statutory 80G tax-exemption receipts;</li>
                            <li>register you for events, volunteering or internship opportunities;</li>
                            <li>respond to your enquiries, feedback and support requests;</li>
                            <li>send you updates, newsletters and impact reports where you have opted in;</li>
                            <li>run, evaluate and improve our programmes and this website;</li>
                            <li>maintain security, prevent fraud and comply with our legal and regulatory obligations.</li>
                        </ul>
                        <p>
                            We rely on your consent, the performance of a service you requested, our legitimate
                            organisational interests, and our legal obligations as the bases for processing your data.
                        </p>

                        <h2 id="cookies">4. Cookies &amp; Tracking Technologies</h2>
                        <p>
                            Our website uses cookies and similar technologies to keep the site secure, remember your
                            preferences, and understand how visitors use our pages so we can improve them. Essential
                            cookies are required for the site to function (for example, to protect forms against
                            cross-site request forgery). Analytics cookies help us measure traffic in aggregate.
                        </p>
                        <p>
                            You can control or delete cookies through your browser settings at any time. Disabling
                            essential cookies may affect the functionality of certain features, such as submitting forms.
                        </p>

                        <h2 id="sharing">5. How We Share Information</h2>
                        <p>
                            We do <strong>not</strong> sell, rent or trade your personal information. We share it only in
                            the limited circumstances below, and only to the extent necessary:
                        </p>
                        <ul>
                            <li><strong>Service providers</strong> — trusted payment gateways, email and hosting partners
                                who process data on our behalf under confidentiality obligations.</li>
                            <li><strong>Legal &amp; regulatory authorities</strong> — where disclosure is required by law,
                                court order, or to protect the rights, safety and property of our beneficiaries, staff
                                or organisation.</li>
                            <li><strong>With your consent</strong> — for example, when you agree to be featured in a
                                success story or testimonial.</li>
                        </ul>

                        <h2 id="security">6. Data Security</h2>
                        <p>
                            We apply appropriate technical and organisational measures to protect your information against
                            unauthorised access, alteration, disclosure or destruction. These include encrypted
                            connections, access controls, secure servers and restricted staff access on a need-to-know
                            basis. While no method of transmission over the internet is completely secure, we work
                            continuously to safeguard your data and to respond promptly to any incident.
                        </p>

                        <h2 id="retention">7. Data Retention</h2>
                        <p>
                            We keep your personal information only for as long as necessary to fulfil the purposes it was
                            collected for, including donation and tax-receipt records that we are legally required to
                            retain. When information is no longer needed, we securely delete or anonymise it.
                        </p>

                        <h2 id="rights">8. Your Rights &amp; Choices</h2>
                        <p>Subject to applicable law, you have the right to:</p>
                        <ul>
                            <li>access the personal information we hold about you;</li>
                            <li>request correction of inaccurate or incomplete information;</li>
                            <li>request deletion of your information where there is no overriding legal reason to keep it;</li>
                            <li>withdraw consent or unsubscribe from our communications at any time;</li>
                            <li>object to or restrict certain processing of your data.</li>
                        </ul>
                        <p>
                            To exercise any of these rights, simply write to us at
                            <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a>. We will respond within a reasonable
                            period and may need to verify your identity before acting on your request.
                        </p>

                        <h2 id="children">9. Children’s Privacy</h2>
                        <p>
                            Many of our programmes benefit children, and we treat their information with the utmost care.
                            We do not knowingly collect personal data directly from children through our website without
                            the consent of a parent or guardian. Where we work with children as part of our charitable
                            activities, we do so under strict safeguarding and consent procedures.
                        </p>

                        <h2 id="links">10. Third-Party Links</h2>
                        <p>
                            Our website may contain links to external sites, such as payment gateways or partner
                            organisations. We are not responsible for the privacy practices or content of those sites and
                            encourage you to review their privacy policies before sharing any information.
                        </p>

                        <h2 id="changes">11. Changes to This Policy</h2>
                        <p>
                            We may update this Privacy Policy from time to time to reflect changes in our practices or
                            legal requirements. The revised version will be posted on this page with a new
                            &ldquo;Last updated&rdquo; date. We encourage you to review it periodically.
                        </p>

                        <h2 id="contact">12. Contact Us</h2>
                        <p>
                            If you have any questions, concerns or requests regarding this Privacy Policy or how we handle
                            your information, please reach out to us:
                        </p>
                        <ul>
                            <li><strong><?= e($siteName) ?></strong></li>
                            <li>Email: <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li>
                            <li>Phone: <a href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>"><?= e($phone) ?></a></li>
                            <li>Address: <?= e($address) ?></li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sticky helper sidebar -->
            <aside class="reveal delay-1">
                <div class="card-3d" style="position:sticky;top:90px;">
                    <div class="icon-badge accent"><?= lucide('shield') ?></div>
                    <h3 class="card-title">Questions About Your Data?</h3>
                    <p class="card-text">
                        We are happy to explain anything in this policy or help you access, update or remove your
                        information.
                    </p>
                    <ul class="list-check mt-2" style="font-size:.95rem;">
                        <li>We never sell your personal data</li>
                        <li>Unsubscribe from emails anytime</li>
                        <li>Request your records on demand</li>
                    </ul>
                    <div class="flex flex-col gap-2 mt-4">
                        <a class="btn btn-primary btn-block" href="mailto:<?= e($email) ?>">Email Our Team</a>
                        <a class="btn btn-outline btn-block" href="<?= e(url('contact')) ?>">Contact Page</a>
                    </div>

                    <?php if (!$hasContent): ?>
                        <hr class="divider">
                        <strong style="display:block;margin-bottom:.6rem;">On This Page</strong>
                        <ul class="list-check" style="font-size:.92rem;">
                            <?php foreach ($toc as $anchor => $label): ?>
                                <li style="padding-left:0;">
                                    <a href="#<?= e($anchor) ?>"><?= e($label) ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </aside>

        </div>
    </div>
</section>

<!-- ============================== CTA ============================== -->
<section class="section section-brand">
    <div class="container text-center reveal">
        <h2 class="text-white">Transparency Is at the Heart of What We Do</h2>
        <p style="color:rgba(255,255,255,.9);max-width:640px;margin-inline:auto;">
            The same care we bring to your data, we bring to every rupee and every promise. Explore our official
            records or get in touch — we are always glad to answer your questions.
        </p>
        <div class="flex justify-center flex-wrap gap-2 mt-4">
            <a class="btn btn-white btn-lg" href="<?= e(url('ngo-details')) ?>">View NGO Details</a>
            <a class="btn btn-outline btn-lg" style="border-color:#fff;color:#fff;" href="<?= e(url('contact')) ?>">Contact Us</a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
