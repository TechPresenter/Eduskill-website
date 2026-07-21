<?php
require __DIR__ . '/includes/config.php';
if (http_response_code() === 200) {
    http_response_code(404);
}
$page_title = 'Page not found';
require __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="container-site">
    <div class="mx-auto max-w-lg py-16 text-center">
      <p class="font-display text-7xl font-extrabold text-brand-600">404</p>
      <h1 class="mt-4 font-display text-2xl font-bold text-content">We couldn't find that page</h1>
      <p class="mt-2 text-content-muted">The link may be broken or the page may have moved.</p>
      <a href="<?= e(url('index.php')) ?>" class="mt-6 inline-block rounded-lg bg-brand-600 px-6 py-3 text-sm font-semibold text-on-brand hover:bg-brand-700">Back to home</a>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
