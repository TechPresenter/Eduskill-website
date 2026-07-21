<?php
defined('ESK') || exit('No direct access.');
$siteName = (string) setting('site_name', APP_NAME);

// Header menu from DB, with a sensible default so the site is never nav-less.
$items = [];
try {
    $rows = db_all(
        "SELECT mi.label, mi.url, p.slug AS page_slug
         FROM menu_items mi JOIN menus m ON m.id = mi.menu_id
         LEFT JOIN pages p ON p.id = mi.page_id AND p.deleted_at IS NULL
         WHERE m.location = 'header' AND mi.parent_id IS NULL
         ORDER BY mi.position ASC, mi.id ASC"
    );
    foreach ($rows as $r) {
        $href = !empty($r['page_slug'])
            ? url($r['page_slug'] === 'home' ? 'index.php' : $r['page_slug'] . '.php')
            : (preg_match('#^https?://#', (string) $r['url']) ? (string) $r['url'] : url(ltrim((string) $r['url'], '/')));
        $items[] = ['label' => $r['label'], 'href' => $href];
    }
} catch (Throwable) {
}
if ($items === []) {
    $items = [
        ['label' => 'Home', 'href' => url('index.php')],
        ['label' => 'About', 'href' => url('about.php')],
        ['label' => 'Programmes', 'href' => url('programs.php')],
        ['label' => 'Campaigns', 'href' => url('campaigns.php')],
        ['label' => 'Blog', 'href' => url('blog.php')],
        ['label' => 'Contact', 'href' => url('contact.php')],
    ];
}
?>
<header id="esk-header" class="site-header">
  <div class="container-site">
    <div class="flex h-16 items-center justify-between gap-4">
      <a href="<?= e(url('index.php')) ?>" class="flex items-center gap-2.5">
        <span class="grid h-9 w-9 place-items-center rounded-lg bg-brand-600 font-display text-base font-extrabold text-on-brand">E</span>
        <span class="font-display text-base font-bold tracking-tight text-content sm:text-lg"><?= e($siteName) ?></span>
      </a>

      <nav class="hidden items-center gap-1 lg:flex">
        <?php foreach ($items as $it): ?>
          <a href="<?= e($it['href']) ?>" class="nav-link"><?= e($it['label']) ?></a>
        <?php endforeach; ?>
      </nav>

      <div class="flex items-center gap-2">
        <a href="<?= e(url('donate.php')) ?>" class="hidden rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-on-brand transition hover:bg-brand-700 sm:inline-block">Donate</a>
        <button id="esk-burger" type="button" aria-label="Menu" class="grid h-10 w-10 place-items-center rounded-lg text-content hover:bg-surface-sunken lg:hidden">
          <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
      </div>
    </div>
  </div>
  <div id="esk-drawer" class="hidden border-t border-edge bg-surface lg:hidden">
    <nav class="container-site flex flex-col py-3">
      <?php foreach ($items as $it): ?>
        <a href="<?= e($it['href']) ?>" class="rounded-lg px-3 py-2.5 text-sm font-medium text-content-muted hover:bg-surface-sunken hover:text-content"><?= e($it['label']) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(url('donate.php')) ?>" class="mt-2 rounded-lg bg-brand-600 px-3 py-2.5 text-center text-sm font-semibold text-on-brand">Donate</a>
    </nav>
  </div>
</header>
