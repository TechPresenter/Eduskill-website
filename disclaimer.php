<?php
/**
 * =============================================================================
 *  Disclaimer — CMS-driven with a complete, real fallback.
 * -----------------------------------------------------------------------------
 *  If an admin has published a `pages` row with slug 'disclaimer', its body is
 *  rendered in a `.prose` block. Otherwise a full, generic NGO disclaimer
 *  (general information, no professional advice, external links, donations,
 *  limitation of liability, contact) is shown so the page is never blank
 *  before content is seeded. Both modes share the coloured hero banner, an
 *  "at a glance" trust grid and a sticky helper sidebar.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* ---- Organisation identity (settings first, then config constants) ------ */
$siteName = get_setting('site_name', SITE_NAME);
$email    = get_setting('contact_email', SITE_EMAIL);
$phone    = get_setting('contact_phone', SITE_PHONE);
$address  = get_setting('contact_address', SITE_ADDRESS);
$cin      = get_setting('cin', SITE_CIN);
$phoneTel = preg_replace('/[^0-9+]/', '', (string) $phone);

/* ---- CMS page (admin-managed) with graceful fallback ------------------- */
$page = db_row(
    "SELECT * FROM pages WHERE slug = :slug AND status = 'published' LIMIT 1",
    [':slug' => 'disclaimer']
);

$hasContent   = $page && trim((string) ($page['content'] ?? '')) !== '';
$pageTitle    = $page['title']    ?? 'Disclaimer';
$pageSubtitle = $page['subtitle'] ?? 'Please read this disclaimer carefully before relying on any information published on this website.';

/* Last-updated stamp — CMS row, else the setting; omitted entirely when unknown. */
$updatedRaw = !empty($page['updated_at']) ? $page['updated_at'] : (string) get_setting('disclaimer_updated_at', '');
$updated    = $updatedRaw !== '' ? format_date($updatedRaw, 'd F Y') : '';
if ($updated === '') {
    $updated = (string) $updatedRaw;
}

/* ---- Disclaimer highlights (at-a-glance cards) ------------------------- */
$highlights = [
    ['icon' => 'info',         'title' => 'General Information',  'text' => 'Everything here is shared for general awareness about our mission and work — not as formal advice.'],
    ['icon' => 'stethoscope',  'title' => 'Not Professional Advice', 'text' => 'Content is no substitute for qualified legal, medical, financial or professional guidance.'],
    ['icon' => 'link',         'title' => 'External Links',        'text' => 'We link to third-party sites for convenience; we do not control or endorse their content.'],
    ['icon' => 'handshake',    'title' => 'Good-Faith Effort',     'text' => 'We take reasonable care to keep information accurate, current and honestly presented.'],
];

/* ---- On-this-page navigation (used only for the fallback disclaimer) --- */
$toc = [
    'general'      => 'General Information',
    'no-advice'    => 'No Professional Advice',
    'accuracy'     => 'Accuracy of Content',
    'external'     => 'External Links',
    'donations'    => 'Donations & Fundraising',
    'testimonials' => 'Views & Testimonials',
    'media'        => 'Photographs & Media',
    'liability'    => 'Limitation of Liability',
    'changes'      => 'Changes to This Disclaimer',
    'contact'      => 'Contact Us',
];

/* ---- SEO --------------------------------------------------------------- */
seo_set([
    'title'       => 'Disclaimer',
    'description' => 'The official disclaimer for the ' . $siteName . ' website — covering general information, '
        . 'no professional advice, external links, donations, limitation of liability and how to contact us.',
    'page_key'    => 'disclaimer',
    'type'        => 'website',
]);

$page_hero = [
    'title'      => $pageTitle,
    'subtitle'   => $pageSubtitle,
    'breadcrumb' => [['label' => 'Disclaimer']],
];

include __DIR__ . '/includes/header.php';
?>

<!-- ============================== AT A GLANCE ============================== -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Please Note</span>
            <h2 class="section-title">The Short Version</h2>
            <p class="section-subtitle">
                <?= e($siteName) ?> publishes this website in good faith to share our work and inspire support.
                Here is what to keep in mind — the full disclaimer follows below.
            </p>
        </div>

        <div class="grid grid-4">
            <?php foreach ($highlights as $i => $h): ?>
                <div class="card-3d reveal <?= 'delay-' . (($i % 4) + 1) ?> text-center">
                    <div class="icon-badge<?= $i % 2 ? ' accent' : '' ?>" style="margin-inline:auto;"><?= lucide($h['icon']) ?></div>
                    <h3 class="card-title" style="font-size:1.05rem;"><?= e($h['title']) ?></h3>
                    <p class="card-text"><?= e($h['text']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== DISCLAIMER BODY ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="grid grid-sidebar items-start">

            <!-- Main disclaimer content -->
            <div class="reveal">
                <div class="flex items-center flex-wrap gap-2 mb-3">
                    <?php if ($updated !== ''): ?>
                        <span class="badge badge-brand">Last updated: <?= e($updated) ?></span>
                    <?php endif; ?>
                    <span class="badge badge-muted">CIN: <?= e($cin) ?></span>
                </div>

                <?php if ($hasContent): ?>
                    <!-- Admin-managed content from the CMS -->
                    <div class="prose">
                        <?= rich_text($page['content']) ?>
                    </div>
                <?php else: ?>
                    <!-- Complete generic NGO disclaimer fallback -->
                    <div class="prose">
                        <p>
                            The information provided by <strong><?= e($siteName) ?></strong>
                            (&ldquo;<?= e($siteName) ?>&rdquo;, &ldquo;we&rdquo;, &ldquo;us&rdquo; or &ldquo;our&rdquo;) on
                            this website is offered in good faith and for general informational purposes only. By accessing
                            or using this website you acknowledge and accept the terms of this Disclaimer.
                        </p>

                        <h2 id="general">1. General Information Only</h2>
                        <p>
                            All content published on this website — including text, graphics, images, reports, statistics
                            and links — is intended to share information about our charitable mission, programmes and
                            activities. It is provided on an &ldquo;as is&rdquo; and &ldquo;as available&rdquo; basis
                            without warranties of any kind, whether express or implied. Nothing on this website creates a
                            professional relationship or any binding commitment between you and <?= e($siteName) ?>.
                        </p>

                        <h2 id="no-advice">2. No Professional Advice</h2>
                        <p>
                            The content of this website is <strong>not</strong> a substitute for professional advice. Nothing
                            published here constitutes legal, financial, medical, tax, psychological or any other form of
                            professional advice, and it should not be relied upon as such. Before acting on anything you read
                            on this website, you should consult an appropriately qualified professional who can consider your
                            individual circumstances. Any reliance you place on the information here is strictly at your own
                            risk, and <?= e($siteName) ?> disclaims all liability arising from such reliance.
                        </p>

                        <h2 id="accuracy">3. Accuracy &amp; Completeness of Content</h2>
                        <p>
                            We make reasonable efforts to ensure that the information on this website is accurate, current and
                            complete at the time of publication. However, we make no representations or warranties that it is
                            free from errors or omissions, or that it will remain up to date. Programme details, impact
                            figures, event dates and other information may change without notice. We reserve the right to
                            correct, add to, modify or remove any content at any time without prior notice.
                        </p>

                        <h2 id="external">4. External &amp; Third-Party Links</h2>
                        <p>
                            This website may contain links to external websites, tools and services — such as payment
                            gateways, social-media platforms, mapping services and partner organisations — that are not
                            owned or controlled by <?= e($siteName) ?>. These links are provided for your convenience only.
                        </p>
                        <ul>
                            <li>We do not monitor, endorse or accept responsibility for the content, accuracy, products,
                                services, policies or practices of any third-party site.</li>
                            <li>A link from our website does not imply any affiliation, sponsorship or endorsement.</li>
                            <li>Accessing any linked resource is entirely at your own risk and is subject to that third
                                party&rsquo;s own terms and privacy policy.</li>
                        </ul>

                        <h2 id="donations">5. Donations &amp; Fundraising</h2>
                        <p>
                            <?= e($siteName) ?> accepts donations solely to further its charitable objectives. We take great
                            care to apply every contribution responsibly, transparently and in line with our mission.
                        </p>
                        <ul>
                            <li>Donations are voluntary and, unless earmarked for a specific campaign, may be allocated where
                                the need is greatest.</li>
                            <li>Online payments are processed by independent third-party payment providers; we do not store
                                your full card or banking credentials, and their handling of your payment is governed by their
                                own terms.</li>
                            <li>We will <strong>never</strong> solicit donations by asking for your passwords, one-time
                                passcodes (OTPs) or full card details over phone, SMS, email or social media. Please verify
                                that you are on our official website before contributing, and report any suspicious request to
                                us immediately.</li>
                            <li>Official receipts, including any applicable tax-exemption benefit, are issued only for genuine
                                donations received through our authorised channels.</li>
                        </ul>

                        <h2 id="testimonials">6. Views, Testimonials &amp; Success Stories</h2>
                        <p>
                            Testimonials, success stories, quotes and opinions featured on this website reflect the personal
                            experiences and views of the individuals who shared them. They are shared with consent for
                            illustrative purposes and are not a guarantee, promise or representation of any particular outcome.
                            Results and experiences vary from person to person and community to community.
                        </p>

                        <h2 id="media">7. Photographs &amp; Media</h2>
                        <p>
                            Photographs, videos and other media on this website depict our programmes, events and the
                            communities we serve, and are published with due care and appropriate consent. Some images may be
                            used for representational purposes. Please do not download, reproduce or reuse any media from this
                            website without our prior written permission, particularly images featuring children or
                            beneficiaries, which are protected under our safeguarding practices.
                        </p>

                        <h2 id="liability">8. Limitation of Liability</h2>
                        <p>
                            To the fullest extent permitted by applicable law, <?= e($siteName) ?>, together with its
                            directors, employees, volunteers and agents, shall not be liable for any loss or damage — whether
                            direct, indirect, incidental, special, consequential or punitive — arising out of or in connection
                            with your access to, use of, or inability to use this website, or your reliance on any information
                            contained in it. This includes, without limitation, any loss of data, profit, goodwill or
                            opportunity, and any damage caused by viruses or other technologically harmful material, even where
                            we have been advised of the possibility of such loss.
                        </p>
                        <p>
                            Nothing in this Disclaimer excludes or limits our liability for death or personal injury caused by
                            our negligence, for fraud or fraudulent misrepresentation, or for any other liability that cannot
                            lawfully be excluded or limited.
                        </p>

                        <h2 id="changes">9. Changes to This Disclaimer</h2>
                        <p>
                            We may update this Disclaimer from time to time to reflect changes in our activities, our website
                            or the law. The revised version will be posted on this page with an updated &ldquo;Last
                            updated&rdquo; date and takes effect as soon as it is posted. Your continued use of the website
                            after any change constitutes acceptance of the revised Disclaimer, so we encourage you to review
                            this page periodically.
                        </p>

                        <h2 id="contact">10. Contact Us</h2>
                        <p>
                            If you have any questions about this Disclaimer, or need to reach us for any reason, please get in
                            touch:
                        </p>
                        <ul>
                            <li><strong><?= e($siteName) ?></strong> (CIN <?= e($cin) ?>)</li>
                            <li>Registered office: <?= e($address) ?></li>
                            <li>Email: <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li>
                            <li>Phone: <a href="tel:<?= e($phoneTel) ?>"><?= e($phone) ?></a></li>
                        </ul>
                        <p>
                            By using this website, you acknowledge that you have read and understood this Disclaimer and agree
                            to its terms.
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sticky helper sidebar -->
            <aside class="reveal delay-1">
                <div class="card-3d sticky-aside" style="position:sticky;top:90px;">
                    <div class="icon-badge accent"><?= lucide('pin') ?></div>
                    <h3 class="card-title">Need Clarity?</h3>
                    <p class="card-text">
                        We are happy to explain anything on this page or point you to the right official record.
                    </p>
                    <ul class="list-check mt-2" style="font-size:.95rem;">
                        <li>Information shared in good faith</li>
                        <li>Always verify before you rely</li>
                        <li>Reach out with any question</li>
                    </ul>
                    <div class="flex flex-col gap-2 mt-4">
                        <a class="btn btn-primary btn-block" href="mailto:<?= e($email) ?>">Email Our Team</a>
                        <a class="btn btn-outline btn-block" href="<?= e(url('contact')) ?>">Contact Page</a>
                    </div>

                    <?php if (!$hasContent): ?>
                        <hr class="divider">
                        <strong style="display:block;margin-bottom:.6rem;">On This Page</strong>
                        <ul class="toc-list">
                            <?php foreach ($toc as $anchor => $label): ?>
                                <li>
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

<!-- ============================== RELATED POLICIES ============================== -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Read The Fine Print</span>
            <h2 class="section-title">Our Other Policies</h2>
            <p class="section-subtitle">Transparency runs through everything we do. Explore the documents that govern how we work.</p>
        </div>
        <div class="grid grid-3">
            <a class="card-3d reveal text-center" href="<?= e(url('privacy-policy')) ?>" style="color:inherit;">
                <div class="icon-badge" style="margin-inline:auto;"><?= lucide('lock') ?></div>
                <h3 class="card-title" style="font-size:1.05rem;">Privacy Policy</h3>
                <p class="card-text">How we collect, use and protect your personal information.</p>
                <span style="color:var(--brand-600);font-weight:700;">Read <?= lucide('arrow-right') ?></span>
            </a>
            <a class="card-3d reveal delay-1 text-center" href="<?= e(url('terms')) ?>" style="color:inherit;">
                <div class="icon-badge accent" style="margin-inline:auto;"><?= lucide('file-text') ?></div>
                <h3 class="card-title" style="font-size:1.05rem;">Terms &amp; Conditions</h3>
                <p class="card-text">The rules for using our website, donating and interacting with us.</p>
                <span style="color:var(--brand-600);font-weight:700;">Read <?= lucide('arrow-right') ?></span>
            </a>
            <a class="card-3d reveal delay-2 text-center" href="<?= e(url('ngo-details')) ?>" style="color:inherit;">
                <div class="icon-badge" style="margin-inline:auto;"><?= lucide('landmark') ?></div>
                <h3 class="card-title" style="font-size:1.05rem;">NGO Details</h3>
                <p class="card-text">Our registration, compliance and official organisational records.</p>
                <span style="color:var(--brand-600);font-weight:700;">Read <?= lucide('arrow-right') ?></span>
            </a>
        </div>
    </div>
</section>

<!-- ============================== CTA ============================== -->
<section class="section section-brand">
    <div class="container text-center reveal">
        <h2 class="text-white">Questions About This Disclaimer?</h2>
        <p style="color:rgba(255,255,255,.9);max-width:640px;margin-inline:auto;">
            Our team is glad to clarify anything about how you can use this website, support our work, or partner with us.
            Reach out any time — transparency is at the heart of everything we do.
        </p>
        <div class="flex justify-center flex-wrap gap-2 mt-4">
            <a class="btn btn-white btn-lg" href="mailto:<?= e($email) ?>"><?= lucide('mail') ?> Email Us</a>
            <a class="btn btn-outline btn-lg" style="border-color:#fff;color:#fff;" href="<?= e(url('contact')) ?>">Contact Page</a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
