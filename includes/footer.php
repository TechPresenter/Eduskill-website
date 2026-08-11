<?php
/**
 * =============================================================================
 *  Shared footer — closes <main>, renders the site footer, scripts and popups.
 *  All contact details / links are DB-driven (settings, menus, social_links).
 * =============================================================================
 */
if (!defined('BASE_PATH')) {
    require_once __DIR__ . '/bootstrap.php';
}

$siteName = $siteName ?? get_setting('site_name', SITE_NAME);
$email    = get_setting('contact_email', SITE_EMAIL);
$phone    = get_setting('contact_phone', SITE_PHONE);
$address  = get_setting('contact_address', SITE_ADDRESS);

// Footer quick links (menus location footer/both) or default set.
$footerMenus = [];
try {
    $footerMenus = db_all(
        "SELECT * FROM menus WHERE status = 1 AND location IN ('footer','both') AND parent_id IS NULL
         ORDER BY sort_order ASC, id ASC"
    );
} catch (Throwable $e) {}
if (empty($footerMenus)) {
    $footerMenus = [
        ['title' => 'About Us', 'url' => 'about'],
        ['title' => 'Our Programs', 'url' => 'programs'],
        ['title' => 'Events', 'url' => 'events'],
        ['title' => 'Blogs', 'url' => 'blogs'],
        ['title' => 'Gallery', 'url' => 'gallery'],
        ['title' => 'Contact', 'url' => 'contact'],
    ];
}

// Top programs for the footer.
$footerPrograms = [];
try {
    $footerPrograms = db_all("SELECT title, slug FROM programs WHERE status='active' ORDER BY sort_order ASC LIMIT 5");
} catch (Throwable $e) {}

// Social links.
$socials = [];
try {
    $socials = db_all("SELECT * FROM social_links WHERE status = 1 ORDER BY sort_order ASC");
} catch (Throwable $e) {}
$socialIcons = ['facebook' => 'fa-facebook-f', 'twitter' => 'fa-x-twitter', 'x' => 'fa-x-twitter', 'instagram' => 'fa-instagram', 'linkedin' => 'fa-linkedin-in', 'youtube' => 'fa-youtube', 'whatsapp' => 'fa-whatsapp'];

// Active popup (optional).
$popup = null;
try {
    $popup = db_row(
        "SELECT * FROM popups WHERE status = 1
         AND (start_date IS NULL OR start_date <= CURDATE())
         AND (end_date   IS NULL OR end_date   >= CURDATE())
         ORDER BY id DESC LIMIT 1"
    );
} catch (Throwable $e) {}
?>
    </main><!-- /#main-content -->

    <footer class="footer footer-premium">
        <div class="footer-topline"></div>
        <?php /* The four <span> aurora blobs that used to sit here drove animated,
                 blurred, screen-blended colour washes across the footer. The soft
                 blooms are now part of the footer's own background gradient (see
                 the `.footer` token block in tailwind.css), which needs no DOM,
                 no blur layers and no animation. */ ?>
        <div class="container" style="padding-top:3.5rem;">

            <!-- Footer top: brand + newsletter -->
            <div class="footer-top">
                <div class="footer-brand-col">
                    <div class="nav-brand" style="color:#fff;margin-bottom:1.1rem;">
                        <img class="brand-logo" src="<?= e(brand_logo_url()) ?>" alt="<?= e($siteName) ?> logo" width="56" height="56">
                        <?php /* The kicker used a hardcoded blue-grey (#9fb0cc) left over from
                                 the navy footer; it now reads through the footer's own --f-ink-2
                                 token so it stays a warm neutral on the brand-green field. */ ?>
                        <span class="brand-name" style="color:#fff;line-height:1.15;">
                            <?= e($siteName) ?>
                            <small style="display:block;font-size:.62rem;font-weight:600;letter-spacing:.06em;color:var(--f-ink-2);text-transform:uppercase;">Registered Non-Profit · Bihar</small>
                        </span>
                    </div>
                    <p class="footer-about-text"><?= e(get_setting('footer_about', 'EDUSKILL INDIA FOUNDATION works across Bihar to empower communities through education, healthcare, skill development and relief — spreading hope and creating lasting change.')) ?></p>
                    <ul class="footer-contact">
                        <li><span class="fc-ico"><?= lucide('map-pin') ?></span> <span><?= e($address) ?></span></li>
                        <li><span class="fc-ico"><?= lucide('mail') ?></span> <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li>
                        <li><span class="fc-ico"><?= lucide('phone') ?></span> <a href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>"><?= e($phone) ?></a></li>
                    </ul>
                    <div class="social-links">
                        <?php if ($socials): foreach ($socials as $s): ?>
                            <a href="<?= e(safe_href($s['url'])) ?>" target="_blank" rel="noopener" aria-label="<?= e($s['platform']) ?>"><?= social_svg($s['platform']) ?></a>
                        <?php endforeach; else: ?>
                            <a href="#" aria-label="Facebook"><?= social_svg('facebook') ?></a><a href="#" aria-label="Twitter"><?= social_svg('x') ?></a><a href="#" aria-label="Instagram"><?= social_svg('instagram') ?></a><a href="#" aria-label="YouTube"><?= social_svg('youtube') ?></a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (get_setting('footer_show_newsletter', '1') === '1'): ?>
                <div class="footer-newsletter">
                    <h3><?= lucide('mailbox') ?> Stay in the loop</h3>
                    <p>Impact stories, upcoming events and ways to help — straight to your inbox. No spam, ever.</p>
                    <form class="newsletter-form" data-ajax-form data-endpoint="<?= e(url('forms/newsletter')) ?>">
                        <?= csrf_field() ?>
                        <input type="email" name="email" placeholder="Enter your email address" required aria-label="Email address">
                        <button class="btn btn-primary" type="submit">Subscribe <?= lucide('arrow-right') ?></button>
                    </form>
                    <a class="btn btn-glow btn-block mt-2" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Donate Now</a>
                </div>
                <?php endif; ?>
            </div>

            <!-- 5-column sitemap footer (every page) -->
            <?php
            $footerNav = [
                'Organisation' => [
                    'About Us' => 'about', 'Our Story' => 'our-story', 'Mission & Vision' => 'mission-vision',
                    'Leadership Team' => 'leadership-team', 'Management Body' => 'management-body',
                    'Our Team' => 'team', 'NGO Details' => 'ngo-details', 'Achievements' => 'achievements',
                    'Certificates' => 'certificates',
                    /* The verification pages sit beside Certificates because that
                       is what they are for: someone holding a document we issued,
                       checking it is genuine. Each has its own lookup form and
                       works standalone — they were previously reachable only by
                       scanning the QR printed on the document, so anyone typing
                       the number by hand had nowhere to go.
                       verify-employee is deliberately NOT here: it renders no
                       lookup form without a code and is an internal HR check. */
                    'Verify Membership' => 'verify-member',
                    'Verify Document'   => 'verify-document',
                    'Verify Student ID' => 'verify-student',
                    'Verify Marksheet'  => 'verify-award',
                ],
                'What We Do' => [
                    'Our Programs' => 'programs', 'Causes We Support' => 'causes', 'Schemes & Initiatives' => 'schemes',
                    /* Courses and Admissions are live, working pages that nothing
                       linked to — a crawl from the homepage never reached either.
                       The catalogue lists three published courses; the admission
                       form is a real public application. */
                    'Courses' => 'courses', 'Admissions' => 'admission-apply',
                    'Scholarships' => 'scholarship', 'Skill Development' => 'skill-development',
                    'Campaigns' => 'campaigns', 'Verify Certificate' => 'verify-certificate',
                ],
                /* Renamed from "Get Involved", which is retired sitewide — the
                   primary navigation now groups these under "Support our work". */
                'Support Our Work' => [
                    'Volunteer' => 'volunteer', 'Internship' => 'internship', 'Membership' => 'membership',
                    'Careers' => 'career', 'Become a Partner' => 'become-partner', 'Donate' => 'donate',
                    'Feedback' => 'feedback',
                ],
                'Media & News' => [
                    'Photo Gallery' => 'gallery', 'Videos & Media' => 'media', 'News & Media' => 'news-media',
                    'Blog' => 'blogs', 'Success Stories' => 'success-stories', 'Testimonials' => 'testimonials',
                    'Resources' => 'resources', 'Events' => 'events', 'Awareness Calendar' => 'calendar', 'FAQs' => 'faqs',
                ],
                'Company' => [
                    'Contact Us' => 'contact', 'Search' => 'search', 'Sitemap' => 'sitemap-page',
                    'Sign In' => 'portals', 'Sign Up' => 'signup', 'My Account' => 'account',
                    /* Member pages that existed with no route in. They sit beside
                       "My Account" because they belong to the same journey; each
                       requires a session and sends a signed-out visitor to the
                       login page, which is the correct behaviour for a footer
                       link rather than an error. */
                    'My Learning' => 'my-learning', 'My Payments' => 'my-payments',
                    'Renew Membership' => 'membership-renew',
                    'Privacy Policy' => 'privacy-policy', 'Terms & Conditions' => 'terms',
                    'Refund Policy' => 'refund-policy', 'Disclaimer' => 'disclaimer', 'Cookie Policy' => 'cookie-policy',
                ],
            ];
            ?>
            <div class="footer-links-5">
                <?php foreach ($footerNav as $heading => $links): ?>
                    <div class="footer-col">
                        <h4><?= e($heading) ?></h4>
                        <ul>
                            <?php foreach ($links as $label => $path): ?>
                                <li><a href="<?= e(url($path)) ?>"><?= e($label) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (get_setting('footer_show_trust', '1') === '1'): ?>
            <!-- Trust / credentials strip -->
            <div class="footer-trust">
                <span><?= lucide('shield') ?> Registered Non-Profit (Sec. 8)</span>
                <span><?= lucide('receipt') ?> 80G Tax Exemption</span>
                <span><?= lucide('lock') ?> 100% Secure Donations</span>
                <span><?= lucide('landmark') ?> CIN: <?= e(get_setting('cin', SITE_CIN)) ?></span>
            </div>
            <?php endif; ?>

        </div>
        <?php if (function_exists('get_widgets') && get_widgets('footer')): ?>
        <div class="container footer-widgets"><?php render_widgets('footer'); ?></div>
        <?php endif; ?>

        <div class="footer-bottom">
            <div class="container footer-bottom-inner">
                <?php $footerCopy = get_setting('footer_copyright', '');
                if (trim($footerCopy) !== ''): ?>
                    <p><?= e(strtr($footerCopy, ['{year}' => date('Y'), '{site}' => $siteName])) ?></p>
                <?php else: ?>
                    <p>&copy; <?= date('Y') ?> <?= e($siteName) ?>. All rights reserved. Made with <span class="footer-heart"><?= lucide('heart') ?></span> in Bihar.</p>
                <?php endif; ?>
                <nav class="footer-legal" aria-label="Legal">
                    <a href="<?= e(url('privacy-policy')) ?>">Privacy</a>
                    <a href="<?= e(url('terms')) ?>">Terms</a>
                    <a href="<?= e(url('refund-policy')) ?>">Refund</a>
                    <a href="<?= e(url('disclaimer')) ?>">Disclaimer</a>
                    <a href="<?= e(url('cookie-policy')) ?>">Cookies</a>
                    <a href="<?= e(url('sitemap-page')) ?>">Sitemap</a>
                </nav>
                <?php /* The admin login is intentionally not linked from the public footer —
                         reach it directly at /admin/login. */ ?>
            </div>
        </div>
    </footer>

    <!-- Floating action buttons -->
    <div class="fab-stack">
        <?php
            $waNumber = preg_replace('/\D/', '', get_setting('whatsapp_number', get_setting('contact_phone', SITE_PHONE)));
            if ($waNumber):
        ?>
        <a class="fabtn whatsapp" href="https://wa.me/<?= e($waNumber) ?>" target="_blank" rel="noopener" aria-label="Chat on WhatsApp" title="Chat on WhatsApp"><?= social_svg('whatsapp') ?></a>
        <?php endif; ?>
        <a class="fabtn donate" href="<?= e(url('donate')) ?>" aria-label="Donate" title="Donate Now"><?= lucide('heart') ?></a>
    </div>

    <button class="back-to-top" data-back-to-top aria-label="Back to top"><?= lucide('arrow-up') ?></button>

    <?php if ($popup): ?>
    <!-- Marketing popup (self-contained; remembers dismissal per browser) -->
    <div class="lightbox" id="pwf-popup" role="dialog" aria-modal="true" aria-label="<?= e($popup['title'] ?? 'Announcement') ?>">
        <button class="lightbox-close" onclick="document.getElementById('pwf-popup').classList.remove('is-open')" aria-label="Close">&times;</button>
        <div class="card" style="max-width:440px;">
            <?php if (!empty($popup['image'])): ?>
                <div class="card-media" style="aspect-ratio:16/9;"><img src="<?= e(upload_url($popup['image'])) ?>" alt=""></div>
            <?php endif; ?>
            <div class="card-body text-center">
                <?php if (!empty($popup['title'])): ?><h3><?= e($popup['title']) ?></h3><?php endif; ?>
                <?php if (!empty($popup['content'])): ?><p class="card-text"><?= e($popup['content']) ?></p><?php endif; ?>
                <?php if (!empty($popup['button_url'])): ?>
                    <a class="btn btn-primary" href="<?= e($popup['button_url']) ?>"><?= e($popup['button_text'] ?: 'Learn More') ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
        (function () {
            var key = 'pwf-popup-<?= (int) $popup['id'] ?>';
            if (localStorage.getItem(key)) return;
            var el = document.getElementById('pwf-popup');
            setTimeout(function () { el.classList.add('is-open'); }, <?= (int) ($popup['delay_seconds'] ?? 3) * 1000 ?>);
            el.addEventListener('click', function (e) {
                if (e.target === el || e.target.classList.contains('lightbox-close')) {
                    localStorage.setItem(key, '1');
                }
            });
        })();
    </script>
    <?php endif; ?>

    <!-- Cookie consent banner (site-wide, GDPR-style) -->
    <div class="cookie-consent" id="cookieConsent" role="dialog" aria-label="Cookie consent" hidden>
        <p><?= e(t('cookie.message', 'We use cookies to improve your experience. By continuing, you accept our cookie policy.')) ?>
           <a href="<?= e(url('cookie-policy')) ?>">Learn more</a>.</p>
        <div class="cookie-actions">
            <button class="btn btn-sm btn-ghost" data-cookie="decline"><?= e(t('cookie.decline', 'Decline')) ?></button>
            <button class="btn btn-sm btn-primary" data-cookie="accept"><?= e(t('cookie.accept', 'Accept')) ?></button>
        </div>
    </div>
    <script>
        (function () {
            var key = 'pwf-cookie-consent', bar = document.getElementById('cookieConsent');
            if (!bar) return;
            if (!localStorage.getItem(key)) { bar.hidden = false; }
            bar.querySelectorAll('[data-cookie]').forEach(function (b) {
                b.addEventListener('click', function () {
                    localStorage.setItem(key, b.dataset.cookie); bar.hidden = true;
                });
            });
        })();
        // Register the service worker (PWA offline support)
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('<?= e(url('sw.js')) ?>').catch(function () {});
            });
        }
    </script>

    <!-- SweetAlert2 (self-hosted) — used by AJAX forms for success/error dialogs -->
    <script src="<?= e(asset('vendor/sweetalert2.all.min.js')) ?>"></script>
    <script src="<?= e(asset('js/main.js')) ?>"></script>
    <script src="<?= e(asset('js/premium.js')) ?>"></script>
    <script src="<?= e(asset('js/premium-ux.js')) ?>"></script>
    <script src="<?= e(asset('js/forms.js')) ?>"></script>

    <!-- Lucide icons (self-hosted — core UI must not depend on a CDN) -->
    <script src="<?= e(asset('vendor/lucide.min.js')) ?>"></script>
    <script>
        (function () {
            function draw() { try { window.lucide && window.lucide.createIcons(); } catch (e) {} }
            draw();
            document.addEventListener('DOMContentLoaded', draw);
            // Re-render icons after theme toggle or dynamic UI changes.
            window.PWFdrawIcons = draw;
        })();
    </script>
    <?php // Country-selector JS + data, emitted only when a field was rendered.
    echo country_field_assets();
    // Premium FAQ behaviour, emitted only when a section was rendered.
    echo faq_section_assets(); ?>
    <?php $customJsFooter = (string) get_setting('custom_js_footer', '');
    if (trim($customJsFooter) !== ''): ?><script id="pwf-custom-js-footer"><?= $customJsFooter ?></script><?php endif; ?>
<?php /* Mega-menu overlays live here, NOT inside <header class="navbar">:
         that element sets backdrop-filter (making it the containing block
         for position:fixed children) and z-index:900 (a stacking context),
         which broke the scrim, the dropdown offsets and the layering. */
if (function_exists('mega_menu_overlays')) { echo mega_menu_overlays(); } ?>
<?php /* Portal drawer: one organised home for the eight role logins. */
require_once __DIR__ . '/portal-sidebar.php';
echo portal_sidebar_render(); ?>
</body>
</html>
