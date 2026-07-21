<?php
require __DIR__ . '/includes/auth.php';
require_admin('submissions.view');
$admin_title = 'Contact Inbox';

// Server-side actions (mark read / delete) via form POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['_csrf'] ?? null)) {
    $id = (int) ($_POST['id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    if ($id > 0 && $action === 'read') {
        db_exec("UPDATE contacts SET is_read = 1 WHERE id = ?", [$id]);
        flash('success', 'Marked as read.');
    } elseif ($id > 0 && $action === 'delete') {
        db_exec("DELETE FROM contacts WHERE id = ?", [$id]);
        flash('success', 'Message deleted.');
    }
    redirect('admin/contacts.php');
}

$rows = db_all("SELECT * FROM contacts ORDER BY is_read ASC, id DESC");
require __DIR__ . '/includes/header.php';
?>
<div class="page-header">
  <div><h2 class="page-title">Contact inbox</h2><p class="page-subtitle"><?= count($rows) ?> message<?= count($rows) === 1 ? '' : 's' ?></p></div>
</div>

<?php if ($rows === []): ?>
  <div class="rounded-2xl border border-dashed border-edge bg-surface p-12 text-center text-content-muted"><i class="fa-solid fa-inbox mb-2 text-3xl opacity-40"></i><p>No messages yet.</p></div>
<?php else: ?>
  <div class="space-y-3">
    <?php foreach ($rows as $m): ?>
      <details class="group overflow-hidden rounded-xl border border-edge bg-surface shadow-card <?= (int) $m['is_read'] === 0 ? 'ring-1 ring-brand-500/20' : '' ?>">
        <summary class="flex cursor-pointer list-none items-center gap-4 px-5 py-4 hover:bg-surface-sunken">
          <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-500 to-accent-500 text-sm font-bold text-white"><?= e(mb_strtoupper(mb_substr((string) $m['name'], 0, 1))) ?></span>
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2"><span class="font-semibold text-content"><?= e($m['name']) ?></span><?php if ((int) $m['is_read'] === 0): ?><span class="rounded-full bg-amber-50 px-2 py-0.5 text-2xs font-semibold text-amber-700">New</span><?php endif; ?></div>
            <div class="truncate text-sm text-content-muted"><?= e($m['subject'] ?: '(no subject)') ?> — <?= e(mb_substr((string) $m['message'], 0, 60)) ?>…</div>
          </div>
          <span class="hidden shrink-0 text-xs text-content-subtle sm:block"><?= e(date('d M Y', strtotime((string) $m['created_at']))) ?></span>
          <i class="fa-solid fa-chevron-down text-content-subtle transition group-open:rotate-180"></i>
        </summary>
        <div class="border-t border-edge px-5 py-4">
          <div class="grid gap-2 text-sm sm:grid-cols-2">
            <p><span class="text-content-muted">Email:</span> <a href="mailto:<?= e($m['email']) ?>" class="text-brand-600 hover:underline"><?= e($m['email']) ?></a></p>
            <?php if ($m['phone']): ?><p><span class="text-content-muted">Phone:</span> <?= e($m['phone']) ?></p><?php endif; ?>
          </div>
          <p class="mt-3 whitespace-pre-line rounded-lg bg-surface-sunken p-4 text-sm leading-relaxed text-content"><?= e($m['message']) ?></p>
          <div class="mt-4 flex gap-2">
            <?php if ((int) $m['is_read'] === 0): ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="action" value="read"><button class="rounded-lg bg-brand-600 px-3.5 py-2 text-sm font-semibold text-on-brand hover:bg-brand-700"><i class="fa-solid fa-check mr-1"></i>Mark read</button></form>
            <?php endif; ?>
            <a href="mailto:<?= e($m['email']) ?>" class="rounded-lg border border-edge bg-surface px-3.5 py-2 text-sm font-medium text-content hover:bg-surface-sunken"><i class="fa-solid fa-reply mr-1"></i>Reply</a>
            <form method="post" onsubmit="return confirm('Delete this message?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="action" value="delete"><button class="rounded-lg px-3.5 py-2 text-sm font-medium text-danger-600 hover:bg-danger-50"><i class="fa-solid fa-trash mr-1"></i>Delete</button></form>
          </div>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
