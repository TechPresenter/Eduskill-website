<?php
/** Certificate verification — look up a certificate by its ID/number. Self-contained (no API). */
require __DIR__ . '/includes/config.php';
$page_title = 'Verify Certificate';

$number = trim((string) ($_GET['number'] ?? $_POST['number'] ?? ''));
$searched = $number !== '';
$cert = null;
if ($searched) {
    $cert = db_one("SELECT * FROM certificates WHERE certificate_number = ? LIMIT 1", [$number]);
}
$valid = $cert !== null && strtolower((string) ($cert['status'] ?? 'valid')) !== 'revoked';
require __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="container-site">
    <div class="mx-auto max-w-xl">
      <div class="mb-8 text-center" data-aos="fade-up">
        <span class="eyebrow">Authenticity check</span>
        <h1 class="section-heading">Verify a certificate</h1>
        <p class="section-subheading mx-auto">Enter the certificate number printed on the document (or scan its QR code).</p>
      </div>
      <form method="get" action="<?= e(url('verify-certificate.php')) ?>" class="flex flex-col gap-3 rounded-card border border-edge bg-surface p-5 shadow-card sm:flex-row">
        <input name="number" value="<?= e($number) ?>" required placeholder="e.g. ESK-2026-000123" class="flex-1 rounded-lg border border-edge bg-surface px-4 py-3 text-sm text-content focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-3 text-sm font-semibold text-on-brand transition hover:bg-brand-700"><i class="fa-solid fa-magnifying-glass mr-1"></i> Verify</button>
      </form>

      <?php if ($searched): ?>
        <div class="mt-6" data-aos="fade-up">
          <?php if ($valid): ?>
            <div class="rounded-card border border-success-200 bg-success-50 p-6">
              <div class="flex items-center gap-3"><span class="grid h-11 w-11 place-items-center rounded-full bg-success-500 text-white"><i class="fa-solid fa-check text-lg"></i></span><div><div class="font-display text-lg font-bold text-success-800">Certificate verified</div><div class="text-sm text-success-700">This is a genuine, valid certificate.</div></div></div>
              <dl class="mt-5 grid grid-cols-1 gap-x-6 gap-y-3 border-t border-success-200 pt-5 text-sm sm:grid-cols-2">
                <div><dt class="text-content-muted">Certificate no.</dt><dd class="font-semibold text-content"><?= e($cert['certificate_number']) ?></dd></div>
                <div><dt class="text-content-muted">Recipient</dt><dd class="font-semibold text-content"><?= e($cert['recipient_name']) ?></dd></div>
                <?php if (!empty($cert['program_name'])): ?><div><dt class="text-content-muted">Programme</dt><dd class="font-semibold text-content"><?= e($cert['program_name']) ?></dd></div><?php endif; ?>
                <?php if (!empty($cert['type'])): ?><div><dt class="text-content-muted">Type</dt><dd class="font-semibold text-content"><?= e($cert['type']) ?></dd></div><?php endif; ?>
                <?php if (!empty($cert['issue_date'])): ?><div><dt class="text-content-muted">Issued</dt><dd class="font-semibold text-content"><?= e(date('d M Y', strtotime((string) $cert['issue_date']))) ?></dd></div><?php endif; ?>
              </dl>
              <?php if (!empty($cert['file_path'])): ?><a href="<?= e(asset((string) $cert['file_path'])) ?>" target="_blank" rel="noopener" class="mt-4 inline-flex items-center gap-2 text-sm font-medium text-brand-600 hover:text-brand-700"><i class="fa-solid fa-file-arrow-down"></i> View certificate</a><?php endif; ?>
            </div>
          <?php elseif ($cert !== null): ?>
            <div class="rounded-card border border-warning-200 bg-warning-50 p-6 text-center">
              <span class="grid h-11 w-11 mx-auto place-items-center rounded-full bg-warning-500 text-white"><i class="fa-solid fa-triangle-exclamation text-lg"></i></span>
              <div class="mt-3 font-display text-lg font-bold text-warning-800">This certificate has been revoked</div>
              <p class="mt-1 text-sm text-warning-700">Certificate <?= e($cert['certificate_number']) ?> is no longer valid.</p>
            </div>
          <?php else: ?>
            <div class="rounded-card border border-danger-200 bg-danger-50 p-6 text-center">
              <span class="grid h-11 w-11 mx-auto place-items-center rounded-full bg-danger-500 text-white"><i class="fa-solid fa-xmark text-lg"></i></span>
              <div class="mt-3 font-display text-lg font-bold text-danger-800">No certificate found</div>
              <p class="mt-1 text-sm text-danger-700">We couldn't find a certificate with the number &ldquo;<?= e($number) ?>&rdquo;. Please check and try again.</p>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
