<?php
defined('ESK') || exit('No direct access.');
/**
 * PREMIUM footer — multi-column links, newsletter subscribe, social icons, certifications,
 * back-to-top and WhatsApp float. Drop-in replacement for footer.php (keeps the </main> close,
 * #esk-top id for app.js, and the $page_scripts hook). Loads app.js + premium.js.
 */
$siteName = (string) setting('site_name', APP_NAME);
$tagline = (string) setting('site_tagline', 'Empowering communities through education, skills and opportunity.');
$email = (string) setting('contact_email', 'hello@eduskill.org.in');
$phone = (string) setting('contact_phone', '+91 00000 00000');
$address = (string) setting('contact_address', 'New Delhi, India');
$whatsapp = (string) setting('whatsapp_number', '');
$socials = [
    ['fa-facebook-f', (string) setting('social_facebook', '#')],
    ['fa-instagram', (string) setting('social_instagram', '#')],
    ['fa-x-twitter', (string) setting('social_twitter', '#')],
    ['fa-linkedin-in', (string) setting('social_linkedin', '#')],
    ['fa-youtube', (string) setting('social_youtube', '#')],
];
$explore = [['About Us', 'about.php'], ['Our Programmes', 'programs.php'], ['Campaigns', 'campaigns.php'], ['Events', 'events.php'], ['Gallery', 'gallery.php'], ['Blog', 'blog.php']];
$involved = [['Donate', 'donate.php'], ['Volunteer', 'volunteer.php'], ['Become a Partner', 'partners.php'], ['Careers', 'careers.php'], ['Scholarships', 'scholarships.php'], ['Internships', 'internships.php']];
$legal = [['Privacy Policy', 'privacy-policy.php'], ['Terms & Conditions', 'terms.php'], ['Refund Policy', 'refund-policy.php'], ['Disclaimer', 'disclaimer.php'], ['Cookie Policy', 'cookie-policy.php'], ['Sitemap', 'sitemap.php']];
$col = static function (string $title, array $links): void { ?>
  <div>
    <h3 class="footer-heading"><?= e($title) ?></h3>
    <?php foreach ($links as $l): ?><a href="<?= e(url($l[1])) ?>" class="footer-link"><?= e($l[0]) ?></a><?php endforeach; ?>
  </div>
<?php };
?>
  </main>

  <footer class="site-footer">
    <div class="container-site py-14">
      <div class="grid grid-cols-1 gap-10 md:grid-cols-2 lg:grid-cols-6">
        <div class="lg:col-span-2">
          <div class="flex items-center gap-2.5">
            <span class="grid h-10 w-10 place-items-center rounded-xl bg-brand-600 font-display text-lg font-extrabold text-on-brand">E</span>
            <span class="font-display text-lg font-bold text-content"><?= e($siteName) ?></span>
          </div>
          <p class="mt-4 max-w-xs text-sm leading-relaxed text-content-muted"><?= e($tagline) ?></p>
          <div class="mt-5 space-y-2 text-sm text-content-muted">
            <p><i class="fa-solid fa-location-dot mr-2 text-brand-600"></i><?= e($address) ?></p>
            <p><i class="fa-solid fa-envelope mr-2 text-brand-600"></i><a href="mailto:<?= e($email) ?>" class="hover:text-brand-600"><?= e($email) ?></a></p>
            <p><i class="fa-solid fa-phone mr-2 text-brand-600"></i><a href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>" class="hover:text-brand-600"><?= e($phone) ?></a></p>
          </div>
          <div class="mt-5 flex gap-2">
            <?php foreach ($socials as $s): ?><a href="<?= e($s[1]) ?>" target="_blank" rel="noopener" class="pm-social" aria-label="Social link"><i class="fa-brands <?= e($s[0]) ?>"></i></a><?php endforeach; ?>
          </div>
        </div>

        <?php $col('Explore', $explore); ?>
        <?php $col('Get Involved', $involved); ?>
        <?php $col('Legal', $legal); ?>

        <div>
          <h3 class="footer-heading">Newsletter</h3>
          <p class="text-sm text-content-muted">Get impact stories &amp; updates in your inbox.</p>
          <form id="pm-newsletter" class="mt-3">
            <?= csrf_field() ?>
            <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
            <div class="flex overflow-hidden rounded-lg border border-edge bg-surface focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-500/25">
              <input type="email" name="email" required placeholder="Your email" class="min-w-0 flex-1 bg-transparent px-3 py-2.5 text-sm text-content placeholder:text-content-subtle focus:outline-none">
              <button type="submit" class="shrink-0 bg-brand-600 px-4 text-sm font-semibold text-on-brand transition hover:bg-brand-700" aria-label="Subscribe"><i class="fa-solid fa-paper-plane"></i></button>
            </div>
            <p id="pm-nl-status" class="mt-2 min-h-[1rem] text-xs text-content-muted" role="status" aria-live="polite"></p>
          </form>
        </div>
      </div>

      <!-- Certifications / trust -->
      <div class="mt-12 flex flex-wrap items-center justify-center gap-3 border-t border-edge pt-8 text-center">
        <?php foreach (['12A Registered', '80G Tax Exemption', 'CSR-1 Registered', 'Transparent &amp; Audited'] as $cert): ?>
          <span class="inline-flex items-center gap-2 rounded-full bg-surface-sunken px-4 py-2 text-xs font-semibold text-content-muted ring-1 ring-inset ring-edge"><i class="fa-solid fa-shield-halved text-success-600"></i><?= $cert ?></span>
        <?php endforeach; ?>
      </div>

      <div class="mt-10 flex flex-col items-center justify-between gap-3 border-t border-edge pt-6 text-sm text-content-muted sm:flex-row">
        <p>&copy; <?= date('Y') ?> <?= e($siteName) ?>. All rights reserved.</p>
        <p class="text-xs">Donations are eligible for tax exemption under Section 80G.</p>
      </div>
    </div>
  </footer>

  <?php if ($whatsapp !== ''): ?>
    <a href="https://wa.me/<?= e(preg_replace('/\D/', '', $whatsapp)) ?>" target="_blank" rel="noopener" class="fixed bottom-5 left-5 z-sticky grid h-12 w-12 place-items-center rounded-full bg-[#25D366] text-xl text-white shadow-pop" aria-label="Chat on WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
  <?php endif; ?>
  <button id="esk-top" type="button" aria-label="Back to top" class="fixed bottom-5 right-5 z-sticky hidden h-11 w-11 place-items-center rounded-full bg-brand-600 text-on-brand shadow-pop">
    <i class="fa-solid fa-arrow-up"></i>
  </button>

  <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
  <script src="<?= e(asset('js/app.js')) ?>"></script>
  <script src="<?= e(asset('js/premium.js')) ?>" defer></script>
  <script>if (window.AOS) AOS.init({ once: true, duration: 650, offset: 40, easing: 'ease-out-cubic' });</script>
  <script>(function(){var f=document.getElementById('pm-newsletter');if(!f)return;var s=document.getElementById('pm-nl-status');
    f.addEventListener('submit',function(e){e.preventDefault();var b=f.querySelector('button');b.disabled=true;s.style.color='';s.textContent='Subscribing…';
      fetch('<?= e(url('api/newsletter.php')) ?>',{method:'POST',headers:{Accept:'application/json'},body:new FormData(f)})
        .then(function(r){return r.json();}).then(function(d){b.disabled=false;s.textContent=d.message||(d.ok?'Subscribed!':'Something went wrong.');
          s.style.color=d.ok?'rgb(var(--success-600))':'rgb(var(--danger-600))';if(d.ok)f.reset();})
        .catch(function(){b.disabled=false;s.style.color='rgb(var(--danger-600))';s.textContent='Network error. Please try again.';});});})();</script>
  <?php if (!empty($page_scripts)) echo $page_scripts; ?>
</body>
</html>
