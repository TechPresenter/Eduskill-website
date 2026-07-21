<?php
/** Team members listing. */
require __DIR__ . '/includes/config.php';
$page_title = 'Our Team';
$members = db_all("SELECT * FROM team_members WHERE deleted_at IS NULL AND is_active = 1 ORDER BY position ASC, id ASC");
require __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="container-site">
    <div class="mx-auto mb-12 max-w-2xl text-center" data-aos="fade-up">
      <span class="eyebrow">The people behind Eduskill</span>
      <h1 class="section-heading">Our team</h1>
      <p class="section-subheading mx-auto">Educators, organisers, and volunteers working alongside the communities we serve.</p>
    </div>
    <?php if ($members === []): ?>
      <div class="rounded-card border border-dashed border-edge p-12 text-center text-content-muted">Team members will be listed here soon.</div>
    <?php else: ?>
      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <?php foreach ($members as $i => $m): ?>
          <?php $social = json_decode((string) ($m['socials_json'] ?? '{}'), true) ?: []; ?>
          <div class="rounded-card border border-edge bg-surface p-6 text-center shadow-card transition hover:shadow-pop" data-aos="fade-up" data-aos-delay="<?= ($i % 4) * 80 ?>">
            <div class="mx-auto grid h-24 w-24 place-items-center overflow-hidden rounded-full bg-brand-50 text-2xl font-bold text-brand-600">
              <?php if (!empty($m['photo'])): ?>
                <img src="<?= e(asset($m['photo'])) ?>" alt="<?= e($m['name']) ?>" class="h-full w-full object-cover" loading="lazy">
              <?php else: ?>
                <?= e(mb_strtoupper(mb_substr((string) $m['name'], 0, 1))) ?>
              <?php endif; ?>
            </div>
            <h3 class="mt-4 font-display text-base font-semibold text-content"><?= e($m['name']) ?></h3>
            <?php if (!empty($m['role_title'])): ?><p class="text-sm text-brand-600"><?= e($m['role_title']) ?></p><?php endif; ?>
            <?php if (!empty($m['bio'])): ?><p class="mt-2 text-sm leading-relaxed text-content-muted"><?= e($m['bio']) ?></p><?php endif; ?>
            <?php if ($social): ?>
              <div class="mt-3 flex justify-center gap-3 text-content-subtle">
                <?php foreach ($social as $net => $link): if (empty($link)) continue; ?>
                  <a href="<?= e((string) $link) ?>" target="_blank" rel="noopener" class="hover:text-brand-600" aria-label="<?= e((string) $net) ?>"><i class="fa-brands fa-<?= e(strtolower((string) $net)) ?>"></i></a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
