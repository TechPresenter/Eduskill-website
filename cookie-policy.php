<?php
/**
 * =============================================================================
 *  Cookie Policy — CMS-driven with a complete, real fallback.
 *
 *  If an admin has published a `pages` row with slug 'cookie-policy', its body
 *  is rendered in a `.prose` block. Otherwise a full, genuine cookie policy
 *  (what cookies are, the types we use, why, managing/disabling, third parties,
 *  contact) is shown so the page is never blank before content is seeded.
 *
 *  Consent itself is handled by the single site-wide banner in the footer.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* ---- Organisation identity (settings first, then config constants) ----- */
$siteName = get_setting('site_name', SITE_NAME);
$email    = get_setting('contact_email', SITE_EMAIL);
$phone    = get_setting('contact_phone', SITE_PHONE);
$address  = get_setting('contact_address', SITE_ADDRESS);
$phoneTel = preg_replace('/[^0-9+]/', '', (string) $phone);

/* ---- CMS page (admin-managed) with graceful fallback ------------------- */
$page = db_row(
    "SELECT * FROM pages WHERE slug = :slug AND status = 'published' LIMIT 1",
    [':slug' => 'cookie-policy']
);

$hasContent   = $page && trim((string) ($page['content'] ?? '')) !== '';
$pageTitle    = $page['title']    ?? 'Cookie Policy';
$pageSubtitle = $page['subtitle'] ?? 'How and why ' . $siteName . ' uses cookies and similar technologies — and the simple controls you have over them.';

/* Last-updated stamp — from the CMS row when present, else a settings default. */
$updatedRaw = !empty($page['updated_at']) ? $page['updated_at'] : get_setting('cookie_updated_at', '2026-01-01');
$updated    = format_date($updatedRaw, 'd F Y');
if ($updated === '') {
    $updated = (string) $updatedRaw;
}

/* ---- Cookie categories, at a glance (summary cards) -------------------- */
$cookieTypes = [
    ['icon' => 'wrench', 'title' => 'Strictly Necessary', 'text' => 'Keep the site secure and working — sessions, form protection and remembering your cookie choice. These cannot be switched off.'],
    ['icon' => 'settings', 'title' => 'Preference',          'text' => 'Remember the choices you make, such as light or dark theme, so the site feels the way you left it.'],
    ['icon' => 'bar-chart-3', 'title' => 'Analytics',           'text' => 'Help us understand, in aggregate, how visitors use our pages so we can keep improving them.'],
    ['icon' => 'target', 'title' => 'Third-Party',         'text' => 'Set by embedded services like maps, videos and payment gateways to make that content work.'],
];

/* ---- Cookie reference table (category → purpose → examples → duration) -- */
$cookieTable = [
    [
        'category' => 'Strictly necessary',
        'purpose'  => 'Session management, cross-site request forgery (CSRF) protection for forms, and remembering your cookie-consent choice.',
        'examples' => 'PWF_SESSION, csrf_token, pwf-cookie-consent',
        'duration' => 'Session – up to 1 year',
    ],
    [
        'category' => 'Preference',
        'purpose'  => 'Remember interface settings you select, such as the light or dark colour theme.',
        'examples' => 'pwf-theme',
        'duration' => 'Up to 1 year',
    ],
    [
        'category' => 'Analytics',
        'purpose'  => 'Measure traffic and understand usage patterns in aggregate to improve our content (only if analytics is enabled).',
        'examples' => '_ga, _gid',
        'duration' => 'Up to 2 years',
    ],
    [
        'category' => 'Third-party',
        'purpose'  => 'Enable embedded content and services — Google Maps, YouTube videos and payment gateways.',
        'examples' => 'Set by the respective provider',
        'duration' => 'Varies by provider',
    ],
];

/* ---- Browser help links for managing cookies --------------------------- */
$browserLinks = [
    ['name' => 'Google Chrome',    'url' => 'https://support.google.com/chrome/answer/95647'],
    ['name' => 'Mozilla Firefox',  'url' => 'https://support.mozilla.org/en-US/kb/enhanced-tracking-protection-firefox-desktop'],
    ['name' => 'Apple Safari',     'url' => 'https://support.apple.com/en-in/guide/safari/sfri11471/mac'],
    ['name' => 'Microsoft Edge',   'url' => 'https://support.microsoft.com/en-us/microsoft-edge/delete-cookies-in-microsoft-edge-63947406-40ac-c3b8-57b9-2a946a29ae09'],
];

/* ---- On-this-page navigation (used only for the fallback policy) ------- */
$toc = [
    'introduction' => 'Introduction',
    'what'         => 'What Are Cookies?',
    'use'          => 'How We Use Cookies',
    'types'        => 'Types of Cookies We Use',
    'third-party'  => 'Third-Party Cookies',
    'managing'     => 'Managing & Disabling Cookies',
    'consent'      => 'Your Consent',
    'changes'      => 'Changes to This Policy',
    'contact'      => 'Contact Us',
];

/* ---- SEO --------------------------------------------------------------- */
seo_set([
    'title'       => 'Cookie Policy',
    'description' => 'Learn how ' . $siteName . ' uses cookies and similar technologies — the types of cookies we set, why we use them, third-party cookies, and how to manage or disable them in your browser.',
    'page_key'    => 'cookie-policy',
    'type'        => 'website',
]);

$page_hero = [
    'title'      => $pageTitle,
    'subtitle'   => $pageSubtitle,
    'breadcrumb' => [['label' => 'Cookie Policy']],
];

include __DIR__ . '/includes/header.php';
?>

<!-- ============================== COOKIES AT A GLANCE ============================== -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">The Short Version</span>
            <h2 class="section-title">Cookies, at a Glance</h2>
            <p class="section-subtitle">
                Like most websites, <?= e($siteName) ?> uses small files called cookies to keep the site secure,
                remember your preferences and understand what is working. Here is the essence — the full policy follows below.
            </p>
        </div>

        <div class="grid grid-4">
            <?php foreach ($cookieTypes as $i => $c): ?>
                <div class="card-3d reveal <?= 'delay-' . (($i % 4) + 1) ?> text-center">
                    <div class="icon-badge<?= $i % 2 ? ' accent' : '' ?>" style="margin-inline:auto;"><?= lucide($c['icon']) ?></div>
                    <h3 class="card-title" style="font-size:1.05rem;"><?= e($c['title']) ?></h3>
                    <p class="card-text"><?= e($c['text']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== COOKIE REFERENCE TABLE ============================== -->
<section class="section section-alt">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Full Transparency</span>
            <h2 class="section-title">Which Cookies We Set</h2>
            <p class="section-subtitle">A plain-language breakdown of the cookie categories on this website, what they do and how long they last.</p>
        </div>
        <div class="table-wrap reveal">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Category</th>
                        <th scope="col">Purpose</th>
                        <th scope="col">Examples</th>
                        <th scope="col">Duration</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cookieTable as $row): ?>
                        <tr>
                            <td><strong><?= e($row['category']) ?></strong></td>
                            <td><?= e($row['purpose']) ?></td>
                            <td><code><?= e($row['examples']) ?></code></td>
                            <td><?= e($row['duration']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="text-muted text-center mt-3" style="font-size:.92rem;">
            Cookie names are indicative and may change as we improve the site. Strictly necessary cookies are always active
            because the website cannot function securely without them.
        </p>
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
                    <!-- Complete generic cookie policy fallback -->
                    <div class="prose">
                        <h2 id="introduction">1. Introduction</h2>
                        <p>
                            This Cookie Policy explains how <?= e($siteName) ?> (&ldquo;we&rdquo;, &ldquo;us&rdquo; or
                            &ldquo;our&rdquo;) uses cookies and similar technologies on this website. It should be read
                            together with our
                            <a href="<?= e(url('privacy-policy')) ?>">Privacy Policy</a>, which explains how we handle
                            personal information more broadly. By continuing to use our website, or by selecting
                            &ldquo;Accept&rdquo; on our cookie banner, you agree to the use of cookies as described here.
                        </p>

                        <h2 id="what">2. What Are Cookies?</h2>
                        <p>
                            Cookies are small text files that a website places on your computer, tablet or phone when you
                            visit. They are widely used to make websites work, to make them work more efficiently, and to
                            provide information to the site owner. Similar technologies — such as pixels, local storage and
                            device identifiers — perform comparable functions, and we refer to all of them as
                            &ldquo;cookies&rdquo; in this policy.
                        </p>
                        <p>
                            Cookies may be <strong>&ldquo;first-party&rdquo;</strong> (set by us) or
                            <strong>&ldquo;third-party&rdquo;</strong> (set by another service we embed). They may also be
                            <strong>&ldquo;session&rdquo;</strong> cookies, which are deleted when you close your browser,
                            or <strong>&ldquo;persistent&rdquo;</strong> cookies, which remain until they expire or you
                            remove them.
                        </p>

                        <h2 id="use">3. How We Use Cookies</h2>
                        <p>We use cookies for a limited set of clearly defined purposes:</p>
                        <ul>
                            <li><strong>To keep the site secure and working</strong> — maintaining your session and
                                protecting our forms against cross-site request forgery (CSRF).</li>
                            <li><strong>To remember your preferences</strong> — such as your choice of light or dark theme,
                                and your response to this cookie banner.</li>
                            <li><strong>To understand and improve the site</strong> — measuring, in aggregate, which pages
                                are visited so we can make the experience better.</li>
                            <li><strong>To enable embedded content</strong> — such as maps, videos and secure payment
                                gateways that we rely on trusted partners to provide.</li>
                        </ul>

                        <h2 id="types">4. Types of Cookies We Use</h2>
                        <p>The cookies on our website fall into four categories:</p>
                        <ul>
                            <li><strong>Strictly necessary cookies</strong> are essential for you to move around the site
                                and use its features, such as submitting forms securely. Without these, services you have
                                asked for cannot be provided, so they are always active.</li>
                            <li><strong>Preference cookies</strong> allow the website to remember choices you make — for
                                example your theme setting — to give you a more personal experience.</li>
                            <li><strong>Analytics cookies</strong> collect anonymous, aggregated information about how
                                visitors use the site, helping us improve how it works. They never identify you personally.</li>
                            <li><strong>Third-party cookies</strong> are set by the external services we embed, and are
                                governed by those providers&rsquo; own policies.</li>
                        </ul>
                        <p>The table above lists representative cookie names, their purpose and how long each one lasts.</p>

                        <h2 id="third-party">5. Third-Party Cookies</h2>
                        <p>
                            Some cookies are placed by third-party services that appear on our pages. We use these
                            responsibly and only where they add genuine value:
                        </p>
                        <ul>
                            <li><strong>Google Maps</strong> — to show our location on interactive maps.</li>
                            <li><strong>YouTube</strong> — to embed videos about our work and impact.</li>
                            <li><strong>Payment gateways</strong> — to process donations securely; we never store your full
                                card or banking details.</li>
                            <li><strong>Analytics providers</strong> — where enabled, to help us understand site usage in
                                aggregate.</li>
                        </ul>
                        <p>
                            We do not control the setting of these cookies, so please review the relevant third party&rsquo;s
                            privacy and cookie policies for more information about their practices.
                        </p>

                        <h2 id="managing">6. Managing &amp; Disabling Cookies</h2>
                        <p>
                            You are always in control of cookies. Most web browsers let you view, delete and block cookies
                            through their settings, and you can usually set your browser to warn you before a cookie is
                            stored. Here is where to manage cookies in popular browsers:
                        </p>
                        <ul>
                            <?php foreach ($browserLinks as $b): ?>
                                <li>
                                    <a href="<?= e($b['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($b['name']) ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p>
                            Please note that if you disable or delete cookies, some parts of this website may not function
                            correctly — for example, secure form submission relies on strictly necessary cookies. You can
                            also change your consent at any time using the &ldquo;Accept&rdquo; or &ldquo;Decline&rdquo;
                            choices in our cookie banner, or by clearing this site&rsquo;s data in your browser.
                        </p>

                        <h2 id="consent">7. Your Consent</h2>
                        <p>
                            When you first visit our website, we show a cookie banner so you can accept or decline
                            non-essential cookies. Strictly necessary cookies do not require consent because the site cannot
                            operate securely without them. We remember your choice in your browser so we do not have to ask
                            you on every page. You can withdraw or change your consent whenever you like by clearing this
                            site&rsquo;s stored data.
                        </p>

                        <h2 id="changes">8. Changes to This Policy</h2>
                        <p>
                            We may update this Cookie Policy from time to time to reflect changes in the cookies we use or
                            for operational, legal or regulatory reasons. The revised version will be posted on this page
                            with a new &ldquo;Last updated&rdquo; date. We encourage you to review this page periodically to
                            stay informed.
                        </p>

                        <h2 id="contact">9. Contact Us</h2>
                        <p>
                            If you have any questions about how we use cookies or about this policy, please get in touch:
                        </p>
                        <ul>
                            <li><strong><?= e($siteName) ?></strong></li>
                            <li>Email: <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li>
                            <li>Phone: <a href="tel:<?= e($phoneTel) ?>"><?= e($phone) ?></a></li>
                            <li>Address: <?= e($address) ?></li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sticky helper sidebar -->
            <aside class="reveal delay-1">
                <div class="card-3d" style="position:sticky;top:90px;">
                    <div class="icon-badge accent"><?= lucide('cookie') ?></div>
                    <h3 class="card-title">Your Cookie Choices</h3>
                    <p class="card-text">
                        You decide which non-essential cookies to allow. Manage them any time from your browser or our
                        cookie banner.
                    </p>
                    <ul class="list-check mt-2" style="font-size:.95rem;">
                        <li>Essential cookies keep the site secure</li>
                        <li>Analytics data is always aggregated</li>
                        <li>We never sell your information</li>
                    </ul>
                    <div class="flex flex-col gap-2 mt-4">
                        <a class="btn btn-primary btn-block" href="<?= e(url('privacy-policy')) ?>">Read Privacy Policy</a>
                        <a class="btn btn-outline btn-block" href="<?= e(url('contact')) ?>">Contact Us</a>
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
        <h2 class="text-white">Transparency You Can Trust</h2>
        <p style="color:rgba(255,255,255,.9);max-width:640px;margin-inline:auto;">
            The same openness we bring to cookies, we bring to every rupee and every promise. Explore our official records
            or reach out — we are always glad to answer your questions.
        </p>
        <div class="flex justify-center flex-wrap gap-2 mt-4">
            <a class="btn btn-white btn-lg" href="<?= e(url('privacy-policy')) ?>">Privacy Policy</a>
            <a class="btn btn-outline btn-lg" style="border-color:#fff;color:#fff;" href="<?= e(url('contact')) ?>">Contact Us</a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
