<?php
defined('ESK') || exit('No direct access.');
$here = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$u = current_user();
$initial = strtoupper(mb_substr((string) ($u['name'] ?? '?'), 0, 1));
$roleLabel = has_role('super_admin') ? 'Super Admin' : (current_roles()[0] ?? 'Staff');

// Unread contact messages → red badge on the inbox item.
$unread = 0;
try { $unread = (int) db_val("SELECT COUNT(*) FROM contacts WHERE is_read = 0"); } catch (Throwable) {}

// Full NGO management nav. Item = [file, label, fa-icon, icon-color, permission, built, badge].
$nav = [
    'Main' => [
        ['dashboard.php', 'Dashboard', 'fa-gauge-high', 'text-sky-400', 'dashboard.view', true, 0],
    ],
    'Fundraising' => [
        ['campaigns.php', 'Campaigns', 'fa-bullhorn', 'text-orange-400', 'campaigns.manage', true, 0],
        ['donations.php', 'Donations', 'fa-hand-holding-dollar', 'text-emerald-400', 'campaigns.manage', false, 0],
        ['donors.php', 'Donors', 'fa-hand-holding-heart', 'text-rose-400', 'campaigns.manage', false, 0],
        ['recurring.php', 'Recurring Gifts', 'fa-arrows-rotate', 'text-teal-400', 'campaigns.manage', false, 0],
    ],
    'Programmes' => [
        ['programs.php', 'Programmes', 'fa-graduation-cap', 'text-purple-400', 'programs.manage', true, 0],
        ['events.php', 'Events', 'fa-calendar-days', 'text-cyan-400', 'events.manage', true, 0],
        ['scholarships.php', 'Scholarships', 'fa-award', 'text-amber-400', 'programs.manage', false, 0],
        ['certificates.php', 'Certificates', 'fa-certificate', 'text-lime-400', 'certificates.manage', false, 0],
    ],
    'People' => [
        ['volunteers.php', 'Volunteers', 'fa-people-group', 'text-sky-400', 'team.manage', false, 0],
        ['members.php', 'Members', 'fa-id-card', 'text-indigo-400', 'team.manage', false, 0],
        ['team.php', 'Team', 'fa-users', 'text-pink-400', 'team.manage', true, 0],
    ],
    'CMS / Website' => [
        ['pages.php', 'Pages', 'fa-file-lines', 'text-blue-400', 'pages.view', true, 0],
        ['blogs.php', 'Blog & News', 'fa-newspaper', 'text-purple-400', 'blog.view', true, 0],
        ['gallery.php', 'Gallery', 'fa-images', 'text-rose-400', 'gallery.manage', true, 0],
        ['media.php', 'Media Library', 'fa-photo-film', 'text-teal-400', 'media.view', true, 0],
        ['testimonials.php', 'Testimonials', 'fa-quote-left', 'text-amber-400', 'testimonials.manage', true, 0],
        ['faqs.php', 'FAQs', 'fa-circle-question', 'text-cyan-400', 'faqs.manage', true, 0],
        ['banners.php', 'Banners', 'fa-image', 'text-lime-400', 'banners.manage', false, 0],
    ],
    'Communications' => [
        ['contacts.php', 'Contact Inbox', 'fa-inbox', 'text-emerald-400', 'submissions.view', true, $unread],
        ['newsletter.php', 'Newsletter', 'fa-paper-plane', 'text-sky-400', 'newsletter.manage', true, 0],
        ['emails.php', 'Email History', 'fa-envelope', 'text-indigo-400', 'newsletter.manage', false, 0],
    ],
    'Marketing & Reports' => [
        ['reports.php', 'Reports', 'fa-chart-column', 'text-purple-400', 'audit.view', false, 0],
        ['analytics.php', 'Analytics', 'fa-chart-line', 'text-orange-400', 'audit.view', false, 0],
    ],
    'Configuration' => [
        ['settings.php', 'Settings', 'fa-gear', 'text-blue-400', 'settings.manage', true, 0],
        ['seo.php', 'SEO', 'fa-magnifying-glass', 'text-teal-400', 'seo.manage', false, 0],
        ['users.php', 'Users & Roles', 'fa-user-shield', 'text-rose-400', 'users.view', true, 0],
        ['profile.php', 'My Profile', 'fa-user', 'text-amber-400', 'dashboard.view', false, 0],
    ],
];
?>
<aside id="esk-sidebar" class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col bg-slate-900 text-slate-300 shadow-2xl transition-transform duration-200 lg:translate-x-0">
  <!-- Brand -->
  <div class="flex h-16 shrink-0 items-center gap-3 border-b border-white/10 px-5">
    <span class="grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br from-brand-500 to-accent-500 font-display text-lg font-extrabold text-white shadow-lg">E</span>
    <div class="leading-tight">
      <div class="font-display text-sm font-bold text-white"><?= e(setting('site_name', 'Eduskill')) ?></div>
      <div class="text-2xs text-slate-400">NGO Management System</div>
    </div>
  </div>

  <!-- User card -->
  <div class="mx-3 mt-3 shrink-0 rounded-xl bg-gradient-to-br from-brand-600/30 to-accent-500/20 p-3 ring-1 ring-inset ring-white/10">
    <div class="flex items-center gap-3">
      <span class="grid h-10 w-10 place-items-center rounded-full bg-gradient-to-br from-brand-500 to-accent-500 text-sm font-bold text-white"><?= e($initial) ?></span>
      <div class="min-w-0"><div class="truncate text-sm font-semibold text-white"><?= e($u['name'] ?? '') ?></div>
        <span class="mt-0.5 inline-flex items-center gap-1 rounded-full bg-amber-400/20 px-2 py-0.5 text-2xs font-semibold text-amber-300"><i class="fa-solid fa-crown text-[8px]"></i> <?= e($roleLabel) ?></span>
      </div>
    </div>
  </div>

  <!-- Nav -->
  <nav class="flex-1 overflow-y-auto overscroll-contain px-3 py-4">
    <?php foreach ($nav as $group => $items): ?>
      <?php $visible = array_filter($items, fn ($i) => user_can($i[4])); if ($visible === []) continue; ?>
      <p class="px-3 pb-1.5 pt-4 text-2xs font-bold uppercase tracking-[0.12em] text-slate-500 first:pt-0"><?= e($group) ?></p>
      <?php foreach ($visible as $it): [$file, $label, $ic, $col, $perm, $built, $badge] = $it; $active = $here === $file; ?>
        <?php if ($built): ?>
          <a href="<?= e(url('admin/' . $file)) ?>" class="group mb-0.5 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition <?= $active ? 'bg-gradient-to-r from-brand-600 to-brand-500 text-white shadow-lg shadow-brand-600/30' : 'text-slate-300 hover:bg-white/5 hover:text-white' ?>">
            <i class="fa-solid <?= e($ic) ?> w-4 text-center <?= $active ? 'text-white' : e($col) ?>"></i>
            <span><?= e($label) ?></span>
            <?php if ($badge > 0): ?><span class="ml-auto rounded-full bg-rose-500 px-1.5 py-0.5 text-2xs font-bold leading-none text-white"><?= (int) $badge ?></span><?php endif; ?>
          </a>
        <?php else: ?>
          <span class="mb-0.5 flex cursor-default items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-500" title="Coming soon">
            <i class="fa-solid <?= e($ic) ?> w-4 text-center opacity-50"></i><span><?= e($label) ?></span>
            <span class="ml-auto rounded-full bg-white/5 px-1.5 py-0.5 text-2xs font-semibold text-slate-500 ring-1 ring-inset ring-white/10">Soon</span>
          </span>
        <?php endif; ?>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>

  <!-- Logout -->
  <div class="shrink-0 border-t border-white/10 p-3">
    <a href="<?= e(url('admin/logout.php')) ?>" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold text-rose-400 transition hover:bg-rose-500/10"><i class="fa-solid fa-right-from-bracket w-4 text-center"></i> Logout</a>
  </div>
</aside>
<div id="esk-sidebar-backdrop" class="fixed inset-0 z-30 hidden bg-slate-900/50 lg:hidden"></div>
