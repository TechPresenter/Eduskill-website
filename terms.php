<?php
/**
 * =============================================================================
 *  Terms & Conditions
 * -----------------------------------------------------------------------------
 *  If an admin has authored a published `terms` page in the CMS, its content is
 *  rendered in a .prose block. Otherwise a complete, real generic Terms &
 *  Conditions is shown so the page is never blank before seeding. Both modes
 *  share the coloured hero banner and a supporting "at a glance" trust grid.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* ---- CMS page (optional admin override) -------------------------------- */
$page = db_row(
    "SELECT * FROM pages WHERE slug = :slug AND status = 'published' LIMIT 1",
    [':slug' => 'terms']
);

/* ---- Organisation identity (settings first, then config constants) ------ */
$siteName = get_setting('site_name', SITE_NAME);
$email    = get_setting('contact_email', SITE_EMAIL);
$phone    = get_setting('contact_phone', SITE_PHONE);
$address  = get_setting('contact_address', SITE_ADDRESS);
$cin      = get_setting('cin', SITE_CIN);
$website  = get_setting('site_url', 'https://eduskillindia.org');
$phoneTel = preg_replace('/[^0-9+]/', '', (string) $phone);

/* ---- Jurisdiction (DB-driven with sensible legal defaults) -------------- */
$govState = get_setting('legal_state', 'Bihar');
$govCity  = get_setting('legal_jurisdiction_city', 'Patna');

/* ---- "Last updated" — prefer the CMS timestamp, then a setting, then now - */
$effectiveSetting = get_setting('terms_effective_date');
if ($page && !empty($page['updated_at'])) {
    $lastUpdated = format_date($page['updated_at'], 'd F Y');
} elseif (!empty($effectiveSetting)) {
    $lastUpdated = $effectiveSetting;
} else {
    $lastUpdated = format_date(date('Y-m-d'), 'd F Y');
}

/* ---- Page title / subtitle (CMS values win where present) --------------- */
$pageTitle    = $page['title']    ?? 'Terms & Conditions';
$pageSubtitle = $page['subtitle'] ?? 'The rules of the road for using our website, supporting our work and interacting with ' . $siteName . '.';

/* ---- SEO --------------------------------------------------------------- */
seo_set([
    'title'       => 'Terms & Conditions',
    'description' => 'Read the Terms & Conditions governing your use of the ' . $siteName
        . ' website — covering acceptable use, donations, intellectual property, disclaimers, limitation of liability and governing law (India / ' . $govState . ').',
    'page_key'    => 'terms',
    'type'        => 'website',
]);

$page_hero = [
    'title'      => $pageTitle,
    'subtitle'   => $pageSubtitle,
    'breadcrumb' => [['label' => 'Terms & Conditions']],
];

include __DIR__ . '/includes/header.php';
?>

<!-- ============================== AT A GLANCE ============================== -->
<section class="section section-sm">
    <div class="container">
        <div class="flex items-center justify-between flex-wrap gap-2 mb-4">
            <span class="chip"><?= lucide('file-text') ?> Last updated: <?= e($lastUpdated) ?></span>
            <span class="chip"><?= lucide('landmark') ?> CIN: <?= e($cin) ?></span>
        </div>

        <div class="grid grid-3">
            <div class="card-3d reveal text-center">
                <div class="icon-badge" style="margin-inline:auto;"><?= lucide('handshake') ?></div>
                <h3 class="card-title" style="font-size:1.05rem;">Fair &amp; Transparent</h3>
                <p class="card-text">Plain-language terms written so you always know your rights and our responsibilities.</p>
            </div>
            <div class="card-3d reveal delay-1 text-center">
                <div class="icon-badge accent" style="margin-inline:auto;"><?= lucide('heart') ?></div>
                <h3 class="card-title" style="font-size:1.05rem;">Donations With Trust</h3>
                <p class="card-text">Every contribution is applied to our charitable objectives and duly receipted.</p>
            </div>
            <div class="card-3d reveal delay-2 text-center">
                <div class="icon-badge" style="margin-inline:auto;"><?= lucide('scale') ?></div>
                <h3 class="card-title" style="font-size:1.05rem;">Governed by Indian Law</h3>
                <p class="card-text">These terms are governed by the laws of India, with jurisdiction in <?= e($govCity) ?>, <?= e($govState) ?>.</p>
            </div>
        </div>
    </div>
</section>

<!-- ============================== MAIN CONTENT ============================== -->
<section class="section section-soft">
    <div class="container container-narrow">

        <?php if ($page && !empty(trim((string) $page['content']))): ?>
            <!-- Admin-authored terms from the CMS -->
            <div class="prose">
                <?= rich_text($page['content']) ?>
            </div>
        <?php else: ?>
            <!-- Complete generic Terms & Conditions (shown until CMS content is seeded) -->

            <!-- Quick navigation -->
            <nav class="flex flex-wrap gap-2 mb-4" aria-label="Terms sections">
                <a class="chip" href="#acceptance">1. Acceptance</a>
                <a class="chip" href="#use-of-site">2. Use of the Site</a>
                <a class="chip" href="#donations">3. Donations</a>
                <a class="chip" href="#intellectual-property">4. Intellectual Property</a>
                <a class="chip" href="#third-party">5. Third-Party Links</a>
                <a class="chip" href="#disclaimers">6. Disclaimers</a>
                <a class="chip" href="#liability">7. Limitation of Liability</a>
                <a class="chip" href="#indemnity">8. Indemnity</a>
                <a class="chip" href="#privacy">9. Privacy</a>
                <a class="chip" href="#changes">10. Changes</a>
                <a class="chip" href="#governing-law">11. Governing Law</a>
                <a class="chip" href="#contact">12. Contact</a>
            </nav>

            <div class="prose">
                <p>
                    Welcome to the official website of <strong><?= e($siteName) ?></strong>
                    (&ldquo;<?= e($siteName) ?>&rdquo;, &ldquo;we&rdquo;, &ldquo;us&rdquo; or &ldquo;our&rdquo;), a non-profit
                    organisation registered in India (CIN <?= e($cin) ?>) with its registered office at <?= e($address) ?>.
                    These Terms &amp; Conditions (&ldquo;Terms&rdquo;) govern your access to and use of this website and any of
                    the content, features, forms and services made available through it (together, the &ldquo;Site&rdquo;).
                    Please read them carefully.
                </p>
                <p><em>Last updated: <?= e($lastUpdated) ?>.</em></p>

                <h2 id="acceptance">1. Acceptance of These Terms</h2>
                <p>
                    By accessing, browsing or otherwise using the Site — including by making a donation, subscribing to our
                    newsletter, registering for an event, or submitting any form — you confirm that you have read, understood
                    and agree to be bound by these Terms and by our Privacy Policy. If you do not agree with any part of these
                    Terms, please do not use the Site. If you are using the Site on behalf of an organisation, you represent
                    that you are authorised to accept these Terms on its behalf.
                </p>

                <h2 id="use-of-site">2. Use of the Site</h2>
                <p>You may use the Site only for lawful purposes and in accordance with these Terms. You agree that you will not:</p>
                <ul class="list-check">
                    <li>Use the Site in any way that breaches any applicable local, national or international law or regulation.</li>
                    <li>Submit false, misleading or fraudulent information through any form, donation or registration.</li>
                    <li>Attempt to gain unauthorised access to the Site, its server, or any system or network connected to it.</li>
                    <li>Introduce viruses, trojans, worms, or any other material that is malicious or technologically harmful.</li>
                    <li>Copy, scrape, harvest or republish content from the Site except as expressly permitted below.</li>
                    <li>Impersonate <?= e($siteName) ?>, our staff, volunteers, or any other person or entity.</li>
                    <li>Use the Site to transmit spam, or to collect the personal data of other users.</li>
                </ul>
                <p>
                    We reserve the right, at our sole discretion, to restrict, suspend or terminate your access to all or part
                    of the Site — without notice and without liability — if we reasonably believe you have breached these Terms.
                    You are responsible for ensuring that everyone who accesses the Site through your internet connection is
                    aware of these Terms and complies with them.
                </p>

                <h2 id="donations">3. Donations &amp; Contributions</h2>
                <p>
                    Donations made through the Site support the charitable objectives of <?= e($siteName) ?>. By donating, you
                    confirm that the funds are from a lawful source, that the payment details you provide are your own or that
                    you are authorised to use them, and that the information you supply is accurate.
                </p>
                <ul class="list-check">
                    <li><strong>Application of funds:</strong> Donations are applied towards our programmes and operations. Unless a
                        contribution is made to a specific campaign, we may allocate funds where the need is greatest.</li>
                    <li><strong>Receipts &amp; tax exemption:</strong> Eligible donations are issued an official receipt. Please share
                        the details we request (such as your name, address and PAN) so we can provide a valid receipt for your records.</li>
                    <li><strong>Voluntary &amp; non-refundable:</strong> Donations are voluntary and generally non-refundable once
                        processed. If a genuine error has occurred (for example, a duplicate or incorrect amount), contact us promptly
                        and we will review your request in good faith.</li>
                    <li><strong>Payments:</strong> Online payments are processed by third-party payment providers. We do not store your
                        full card or banking credentials. Your use of a payment provider is also subject to its own terms and privacy policy.</li>
                    <li><strong>No goods or services:</strong> A donation is a gift in support of our mission and does not entitle you to
                        any goods, services or commercial benefit.</li>
                </ul>

                <h2 id="intellectual-property">4. Intellectual Property</h2>
                <p>
                    All content on the Site — including text, graphics, logos, the name and marks of <?= e($siteName) ?>,
                    photographs, videos, illustrations, reports and the design and arrangement of the Site — is owned by or
                    licensed to us and is protected by copyright, trademark and other intellectual-property laws.
                </p>
                <p>
                    You may view, download and print pages from the Site for your own personal, non-commercial reference, and you
                    may share links to our pages. You may not otherwise reproduce, distribute, modify, publicly display or create
                    derivative works from any part of the Site without our prior written permission. Any use of our name, logo or
                    materials for fundraising, endorsement or commercial purposes requires our express written consent. All rights
                    not expressly granted are reserved.
                </p>

                <h2 id="third-party">5. Third-Party Links &amp; Services</h2>
                <p>
                    The Site may contain links to third-party websites, tools or services (such as payment gateways, social media
                    platforms and mapping services) that are not owned or controlled by <?= e($siteName) ?>. These links are
                    provided for your convenience only. We have no control over, and accept no responsibility for, the content,
                    policies or practices of any third-party site or service. Accessing them is at your own risk and subject to
                    their respective terms and privacy policies.
                </p>

                <h2 id="disclaimers">6. Disclaimers</h2>
                <p>
                    The Site and its content are provided on an &ldquo;as is&rdquo; and &ldquo;as available&rdquo; basis. While we
                    take reasonable care to keep information accurate and up to date, we make no warranties or representations,
                    express or implied, that the Site will be uninterrupted, error-free, secure, or free of viruses, or that the
                    content is complete, current or fit for any particular purpose.
                </p>
                <p>
                    Nothing on the Site constitutes legal, financial, medical or professional advice. Any reliance you place on
                    information published on the Site is strictly at your own risk. We may change, suspend or withdraw all or any
                    part of the Site at any time without notice.
                </p>

                <h2 id="liability">7. Limitation of Liability</h2>
                <p>
                    To the fullest extent permitted by applicable law, <?= e($siteName) ?>, its directors, employees, volunteers
                    and agents shall not be liable for any indirect, incidental, special, consequential or punitive loss or damage,
                    or for any loss of data, profit, goodwill or opportunity, arising out of or in connection with your use of — or
                    inability to use — the Site, even if we have been advised of the possibility of such loss.
                </p>
                <p>
                    Nothing in these Terms excludes or limits our liability for death or personal injury caused by our negligence,
                    for fraud or fraudulent misrepresentation, or for any other liability that cannot be excluded or limited under
                    applicable law.
                </p>

                <h2 id="indemnity">8. Indemnity</h2>
                <p>
                    You agree to indemnify and hold harmless <?= e($siteName) ?> and its directors, employees, volunteers and
                    agents from and against any claims, liabilities, damages, losses and expenses (including reasonable legal fees)
                    arising out of or in any way connected with your breach of these Terms or your misuse of the Site.
                </p>

                <h2 id="privacy">9. Privacy &amp; Data</h2>
                <p>
                    We respect your privacy. Any personal information you provide through the Site — for example when you donate,
                    volunteer, register for an event, subscribe or contact us — is handled in accordance with our Privacy Policy and
                    applicable data-protection laws. By using the Site you consent to such processing and you warrant that all data
                    you provide is accurate.
                </p>

                <h2 id="changes">10. Changes to These Terms &amp; to the Site</h2>
                <p>
                    We may revise these Terms from time to time to reflect changes in our services, our practices or the law. The
                    updated version will be posted on this page with a revised &ldquo;Last updated&rdquo; date, and it takes effect
                    as soon as it is posted. Your continued use of the Site after any change constitutes acceptance of the revised
                    Terms. We encourage you to review this page periodically.
                </p>

                <h2 id="governing-law">11. Governing Law &amp; Jurisdiction</h2>
                <p>
                    These Terms, and any dispute or claim arising out of or in connection with them or the Site (including
                    non-contractual disputes or claims), are governed by and construed in accordance with the laws of India. You
                    agree that the courts at <?= e($govCity) ?>, <?= e($govState) ?>, India shall have exclusive jurisdiction to
                    settle any such dispute or claim.
                </p>

                <h2 id="contact">12. Contact Us</h2>
                <p>If you have any questions about these Terms, or need to reach us for any reason, please get in touch:</p>
                <ul class="list-check">
                    <li><strong><?= e($siteName) ?></strong> (CIN <?= e($cin) ?>)</li>
                    <li>Registered office: <?= e($address) ?></li>
                    <li>Email: <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li>
                    <li>Phone: <a href="tel:<?= e($phoneTel) ?>"><?= e($phone) ?></a></li>
                </ul>
                <p>
                    By using this Site you acknowledge that you have read and understood these Terms &amp; Conditions and agree to
                    be bound by them.
                </p>
            </div>
        <?php endif; ?>

    </div>
</section>

<!-- ============================== HELP / CTA ============================== -->
<section class="section section-brand">
    <div class="container text-center reveal">
        <h2 class="text-white">Questions About These Terms?</h2>
        <p style="color:rgba(255,255,255,.9);max-width:640px;margin-inline:auto;">
            Our team is happy to clarify anything about how you can use this site, donate, or partner with us. Reach out any time.
        </p>
        <div class="flex justify-center flex-wrap gap-2 mt-4">
            <a class="btn btn-white btn-lg" href="mailto:<?= e($email) ?>"><?= lucide('mail') ?> Email Us</a>
            <a class="btn btn-outline btn-lg" style="border-color:#fff;color:#fff;" href="<?= e(url('contact')) ?>">Contact Page</a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
