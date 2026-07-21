<?php
defined('ESK') || exit('No direct access.');
$siteName = (string) setting('site_name', APP_NAME);
$tagline = (string) setting('site_tagline', 'Empowering communities through education and skills.');
$phone = (string) setting('contact_phone', '');
$whatsapp = (string) setting('whatsapp_number', '');
?>
  </main>

  <footer class="site-footer">
    <div class="container-site py-12">
      <div class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-4">
        <div class="sm:col-span-2 lg:col-span-1">
          <div class="flex items-center gap-2.5">
            <span class="grid h-9 w-9 place-items-center rounded-lg bg-brand-600 font-display text-base font-extrabold text-on-brand">E</span>
            <span class="font-display text-base font-bold text-content"><?= e($siteName) ?></span>
          </div>
          <p class="mt-3 max-w-xs text-sm leading-relaxed text-content-muted"><?= e($tagline) ?></p>
        </div>
        <div>
          <h3 class="footer-heading">Quick Links</h3>
          <a href="<?= e(url('about.php')) ?>" class="footer-link">About Us</a>
          <a href="<?= e(url('programs.php')) ?>" class="footer-link">Programmes</a>
          <a href="<?= e(url('campaigns.php')) ?>" class="footer-link">Campaigns</a>
          <a href="<?= e(url('contact.php')) ?>" class="footer-link">Contact</a>
        </div>
        <div>
          <h3 class="footer-heading">Legal</h3>
          <a href="<?= e(url('privacy-policy.php')) ?>" class="footer-link">Privacy Policy</a>
          <a href="<?= e(url('terms.php')) ?>" class="footer-link">Terms &amp; Conditions</a>
          <a href="<?= e(url('faq.php')) ?>" class="footer-link">FAQ</a>
        </div>
        <div>
          <h3 class="footer-heading">Get Involved</h3>
          <a href="<?= e(url('donate.php')) ?>" class="footer-link">Donate</a>
          <a href="<?= e(url('volunteer.php')) ?>" class="footer-link">Volunteer</a>
          <a href="<?= e(url('careers.php')) ?>" class="footer-link">Careers</a>
        </div>
      </div>
      <div class="mt-10 flex flex-col items-center justify-between gap-3 border-t border-edge pt-6 text-sm text-content-muted sm:flex-row">
        <p>&copy; <?= date('Y') ?> <?= e($siteName) ?>. All rights reserved.</p>
        <p class="text-xs">Registered under the Income Tax Act (12A &amp; 80G) · Donations are tax-exempt.</p>
      </div>
    </div>
  </footer>

  <!-- Floating WhatsApp + back-to-top -->
  <?php if ($whatsapp !== ''): ?>
    <a href="https://wa.me/<?= e(preg_replace('/\D/', '', $whatsapp)) ?>" target="_blank" rel="noopener"
       class="fixed bottom-5 left-5 z-sticky grid h-12 w-12 place-items-center rounded-full bg-[#25D366] text-white shadow-pop" aria-label="WhatsApp">
      <i class="fa-brands fa-whatsapp text-2xl"></i>
    </a>
  <?php endif; ?>
  <button id="esk-top" type="button" aria-label="Back to top" class="fixed bottom-5 right-5 z-sticky hidden h-11 w-11 place-items-center rounded-full bg-brand-600 text-on-brand shadow-pop hover:bg-brand-700">
    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
  </button>

  <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
  <script src="<?= e(asset('js/app.js')) ?>"></script>
  <script>
    if (window.AOS) AOS.init({ once: true, duration: 600, offset: 40 });
  </script>
  <?php if (!empty($page_scripts)) echo $page_scripts; ?>
</body>
</html>
