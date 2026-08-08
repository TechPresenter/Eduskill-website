<?php
/**
 * =============================================================================
 *  Admin — Email Mailbox (webmail-style: all folders, threads, labels, bulk).
 * =============================================================================
 *  ?folder=inbox|sent|drafts|outbox|scheduled|archive|spam|trash
 *  ?view=starred|important|incoming|outgoing   ?label=<id>   ?id=<message>
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
require_once __DIR__ . '/../includes/email_center.php';

/* -------------------------------------------------- POST actions (single + bulk) */
if (is_post()) {
    require_csrf();
    $do  = post('_do');
    $ids = (array) post('ids', []);
    $one = (int) post('id', 0);
    if ($one) { $ids[] = $one; }

    if ($do === 'bulk' && ($act = post('action'))) {
        $n = ec_bulk($ids, (string) $act);
        set_flash('success', "$n message(s) updated.");
    } elseif ($do === 'toggle' && $one) {
        ec_toggle($one, (string) post('flag'));
    } elseif ($do === 'move' && $one) {
        ec_move($one, (string) post('folder'));
        set_flash('success', 'Message moved.');
    } elseif ($do === 'label' && $one) {
        $lid = (int) post('label_id');
        if (post('attach')) { db_query("INSERT IGNORE INTO email_message_labels (message_id,label_id) VALUES (:m,:l)", [':m' => $one, ':l' => $lid]); }
        else { db_delete('email_message_labels', 'message_id=:m AND label_id=:l', [':m' => $one, ':l' => $lid]); }
    } elseif ($do === 'process_queue') {
        $r = ec_process_queue(50);
        set_flash('success', "Queue processed: {$r['sent']} sent, {$r['failed']} failed.");
    } elseif ($do === 'sync') {
        $acc = ec_default_account();
        $res = $acc ? ec_imap_sync($acc) : ['ok' => false, 'reason' => 'no-account'];
        set_flash($res['ok'] ? 'success' : 'warning',
            $res['ok'] ? "Synced {$res['fetched']} new message(s)." : 'Incoming sync unavailable — enable the PHP imap extension and configure an account in Email Settings.');
    }
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    redirect('/admin/email-mailbox' . ($qs ? '?' . preg_replace('/&?_do=[^&]*/', '', $qs) : ''));
}

$counts  = ec_folder_counts();
$labels  = db_all("SELECT * FROM email_labels ORDER BY sort_order, name");
$folder  = (string) get('folder', '');
$viewKey = (string) get('view', '');
$labelId = (int) get('label', 0);
$openId  = (int) get('id', 0);
$q       = trim((string) get('q', ''));

// Resolve the active view.
$view = $labelId ? 'label:' . $labelId : ($viewKey ?: ($folder ?: 'inbox'));
$viewLabel = 'Inbox'; $viewIcon = 'inbox';
if ($labelId) { $l = find('email_labels', $labelId); $viewLabel = $l['name'] ?? 'Label'; $viewIcon = 'tag'; }
elseif ($viewKey === 'starred')   { $viewLabel = 'Starred'; $viewIcon = 'star'; }
elseif ($viewKey === 'important') { $viewLabel = 'Important'; $viewIcon = 'bookmark'; }
elseif ($viewKey === 'incoming')  { $viewLabel = 'Incoming'; $viewIcon = 'download'; }
elseif ($viewKey === 'outgoing')  { $viewLabel = 'Outgoing'; $viewIcon = 'upload'; }
elseif (array_key_exists($folder ?: 'inbox', ec_folders())) { [$viewLabel, $viewIcon] = ec_folders()[$folder ?: 'inbox']; }

// Opened message (+ mark read + thread).
$open = $openId ? ec_message($openId) : null;
$thread = [];
if ($open) {
    if (!$open['is_read']) { ec_mark_read($openId, true); $open['is_read'] = 1; }
    $thread = ec_thread((string) $open['thread_id']);
    $openLabels = ec_message_labels($openId);
}

$page  = ec_mailbox($view, ['q' => $q, 'per' => 20]);
$items = $page['items'];

$sidebarLink = static function (string $key, string $qs) use ($view, $viewKey, $folder, $labelId): string {
    return admin_url('email-mailbox?' . $qs);
};

$page_title = 'Mailbox';
include __DIR__ . '/partials/head.php';
?>
<div class="mbx adm-rise">
  <!-- Left rail: folders + labels -->
  <aside class="mbx-rail">
    <a class="btn btn-primary btn-block mbx-compose" href="<?= e(admin_url('email-compose')) ?>"><?= lucide('pencil') ?> Compose</a>

    <nav class="mbx-folders">
      <?php foreach (ec_folders() as $slug => [$lbl, $ic]):
        $c = $counts[$slug] ?? ['total' => 0, 'unread' => 0];
        $active = (!$viewKey && !$labelId && ($folder ?: 'inbox') === $slug); ?>
        <a class="mbx-folder <?= $active ? 'is-active' : '' ?>" href="<?= e(admin_url('email-mailbox?folder=' . $slug)) ?>">
          <span class="mf-ic"><?= lucide($ic) ?></span>
          <span class="mf-name"><?= e($lbl) ?></span>
          <?php if ($slug === 'inbox' && !empty($c['unread'])): ?><span class="mf-badge"><?= (int) $c['unread'] ?></span>
          <?php elseif (!empty($c['total'])): ?><span class="mf-count"><?= (int) $c['total'] ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="mbx-rail-sep">Quick views</div>
    <nav class="mbx-folders">
      <a class="mbx-folder <?= $viewKey === 'starred' ? 'is-active' : '' ?>" href="<?= e(admin_url('email-mailbox?view=starred')) ?>"><span class="mf-ic"><?= lucide('star') ?></span><span class="mf-name">Starred</span><?php if (!empty($counts['starred']['total'])): ?><span class="mf-count"><?= (int) $counts['starred']['total'] ?></span><?php endif; ?></a>
      <a class="mbx-folder <?= $viewKey === 'important' ? 'is-active' : '' ?>" href="<?= e(admin_url('email-mailbox?view=important')) ?>"><span class="mf-ic"><?= lucide('bookmark') ?></span><span class="mf-name">Important</span><?php if (!empty($counts['important']['total'])): ?><span class="mf-count"><?= (int) $counts['important']['total'] ?></span><?php endif; ?></a>
      <a class="mbx-folder <?= $viewKey === 'incoming' ? 'is-active' : '' ?>" href="<?= e(admin_url('email-mailbox?view=incoming')) ?>"><span class="mf-ic"><?= lucide('download') ?></span><span class="mf-name">Incoming</span></a>
      <a class="mbx-folder <?= $viewKey === 'outgoing' ? 'is-active' : '' ?>" href="<?= e(admin_url('email-mailbox?view=outgoing')) ?>"><span class="mf-ic"><?= lucide('upload') ?></span><span class="mf-name">Outgoing</span></a>
    </nav>

    <div class="mbx-rail-sep">Labels</div>
    <nav class="mbx-folders">
      <?php foreach ($labels as $l): ?>
        <a class="mbx-folder <?= $labelId === (int) $l['id'] ? 'is-active' : '' ?>" href="<?= e(admin_url('email-mailbox?label=' . (int) $l['id'])) ?>">
          <span class="mf-dot" style="background:<?= e($l['color']) ?>;"></span><span class="mf-name"><?= e($l['name']) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>

    <form method="post" class="mbx-rail-form">
      <?= csrf_field() ?><input type="hidden" name="_do" value="sync">
      <button class="btn btn-ghost btn-sm btn-block" type="submit"><?= lucide('refresh-cw') ?> Sync incoming</button>
    </form>
  </aside>

  <!-- Center: message list -->
  <section class="mbx-list-col <?= $open ? 'has-open' : '' ?>">
    <div class="mbx-toolbar">
      <div class="mbx-tb-left">
        <span class="mbx-view-title"><?= lucide($viewIcon) ?> <?= e($viewLabel) ?></span>
        <span class="muted mbx-count"><?= (int) ($page['total'] ?? count($items)) ?></span>
      </div>
      <form method="get" class="mbx-search" action="<?= e(admin_url('email-mailbox')) ?>">
        <?php if ($folder): ?><input type="hidden" name="folder" value="<?= e($folder) ?>"><?php endif; ?>
        <?php if ($viewKey): ?><input type="hidden" name="view" value="<?= e($viewKey) ?>"><?php endif; ?>
        <?php if ($labelId): ?><input type="hidden" name="label" value="<?= (int) $labelId ?>"><?php endif; ?>
        <span class="mbx-search-ic"><?= lucide('search') ?></span>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search mail…">
      </form>
    </div>

    <form method="post" id="bulkForm">
      <?= csrf_field() ?><input type="hidden" name="_do" value="bulk"><input type="hidden" name="action" id="bulkAction">
      <div class="mbx-bulkbar" id="bulkBar" hidden>
        <label class="checkbox mbx-all"><input type="checkbox" id="checkAll"> <span id="selCount">0</span></label>
        <div class="mbx-bulk-actions">
          <?php foreach ([['read','mail-open','Read'],['unread','mail','Unread'],['star','star','Star'],['archive','archive','Archive'],['spam','shield-alert','Spam'],['trash','trash-2','Trash']] as [$a,$ic,$t]): ?>
            <button type="button" class="btn btn-ghost btn-sm" data-bulk="<?= $a ?>" title="<?= $t ?>"><?= lucide($ic) ?></button>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if ($folder === 'outbox' && $items): ?>
        <div class="mbx-hint"><?= lucide('info') ?> Messages waiting to send.
          <button class="btn btn-primary btn-sm" formaction="<?= e(admin_url('email-mailbox?folder=outbox')) ?>" name="_do" value="process_queue" type="submit"><?= lucide('play') ?> Send queue now</button>
        </div>
      <?php endif; ?>

      <div class="mbx-list">
        <?php if (!$items): ?>
          <div class="empty-state" style="padding:48px 20px;"><div class="icon"><?= lucide($viewIcon) ?></div>
            <p>Nothing in <?= e(strtolower($viewLabel)) ?>.</p>
            <?php if ($view === 'inbox' && !ec_imap_available()): ?><p class="muted" style="font-size:.85rem;">Live incoming mail needs the PHP <code>imap</code> extension. Outbound mail (Sent/Drafts/Scheduled) works now.</p><?php endif; ?>
          </div>
        <?php else: foreach ($items as $m):
          $isIncoming = $m['direction'] === 'incoming';
          $who = $isIncoming ? ($m['from_name'] ?: $m['from_email']) : ($m['to_email'] ?: '(no recipient)');
          $when = $m['sent_at'] ?: $m['created_at'];
          $active = $openId === (int) $m['id']; ?>
          <div class="mbx-item <?= $m['is_read'] ? '' : 'is-unread' ?> <?= $active ? 'is-open' : '' ?> pr-<?= e($m['priority']) ?>">
            <label class="mbx-check checkbox"><input type="checkbox" name="ids[]" value="<?= (int) $m['id'] ?>" class="mbx-cb"></label>
            <button type="button" class="mbx-star <?= $m['is_starred'] ? 'on' : '' ?>" data-star="<?= (int) $m['id'] ?>" title="Star"><?= lucide('star') ?></button>
            <a class="mbx-item-main" href="<?= e(admin_url('email-mailbox?' . ($folder ? 'folder=' . $folder : ($viewKey ? 'view=' . $viewKey : ($labelId ? 'label=' . $labelId : 'folder=inbox'))) . '&id=' . (int) $m['id'])) ?>">
              <span class="mbx-who"><?php if ($m['is_important']): ?><span class="mbx-imp" title="Important"><?= lucide('bookmark') ?></span><?php endif; ?><?= e($who) ?></span>
              <span class="mbx-subj"><?= e($m['subject'] ?: '(no subject)') ?><?php if ($m['has_attachments']): ?> <?= lucide('paperclip') ?><?php endif; ?></span>
              <span class="mbx-prev"><?= e($m['preview']) ?></span>
              <span class="mbx-when"><?php if ($m['priority'] === 'high'): ?><span class="pill pill-red" style="padding:1px 6px;font-size:.6rem;">!</span> <?php endif; ?><?= e(time_ago($when)) ?></span>
            </a>
          </div>
        <?php endforeach; endif; ?>
      </div>
      <?php if (!empty($page['links'])): ?><div class="mbx-pager"><?= $page['links'] ?></div><?php endif; ?>
    </form>
  </section>

  <!-- Right: reading pane -->
  <?php if ($open): ?>
  <section class="mbx-read">
    <div class="mbx-read-head">
      <a class="btn btn-ghost btn-sm mbx-back" href="<?= e(admin_url('email-mailbox?' . ($folder ? 'folder=' . $folder : 'folder=inbox'))) ?>"><?= lucide('arrow-left') ?></a>
      <div class="mbx-read-actions">
        <a class="btn btn-outline btn-sm" href="<?= e(admin_url('email-compose?reply=' . $openId)) ?>"><?= lucide('reply') ?> Reply</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('email-compose?forward=' . $openId)) ?>"><?= lucide('forward') ?> Forward</a>
        <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="_do" value="toggle"><input type="hidden" name="id" value="<?= $openId ?>"><input type="hidden" name="flag" value="is_starred"><button class="btn btn-ghost btn-sm <?= $open['is_starred'] ? 'on-star' : '' ?>" title="Star"><?= lucide('star') ?></button></form>
        <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="_do" value="toggle"><input type="hidden" name="id" value="<?= $openId ?>"><input type="hidden" name="flag" value="is_important"><button class="btn btn-ghost btn-sm" title="Mark important"><?= lucide('bookmark') ?></button></form>
        <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="_do" value="move"><input type="hidden" name="id" value="<?= $openId ?>"><input type="hidden" name="folder" value="archive"><button class="btn btn-ghost btn-sm" title="Archive"><?= lucide('archive') ?></button></form>
        <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="_do" value="move"><input type="hidden" name="id" value="<?= $openId ?>"><input type="hidden" name="folder" value="trash"><button class="btn btn-ghost btn-sm" title="Delete"><?= lucide('trash-2') ?></button></form>
      </div>
    </div>

    <div class="mbx-read-body">
      <h2 class="mbx-read-subj"><?= e($open['subject'] ?: '(no subject)') ?>
        <?php if ($open['priority'] === 'high'): ?><span class="pill pill-red">High priority</span><?php endif; ?>
      </h2>
      <?php if (!empty($openLabels)): ?><div class="mbx-read-labels"><?php foreach ($openLabels as $l): ?><span class="mbx-lbl" style="background:<?= e($l['color']) ?>22;color:<?= e($l['color']) ?>;"><?= e($l['name']) ?></span><?php endforeach; ?></div><?php endif; ?>

      <?php foreach (($thread ?: [$open]) as $tm): ?>
        <article class="mbx-msg">
          <header class="mbx-msg-head">
            <div class="mbx-avatar"><?= e(strtoupper(mb_substr($tm['from_name'] ?: $tm['from_email'] ?: '?', 0, 1))) ?></div>
            <div class="mbx-msg-meta">
              <strong><?= e($tm['from_name'] ?: $tm['from_email']) ?></strong>
              <span class="muted">&lt;<?= e($tm['from_email']) ?>&gt;</span>
              <div class="mbx-msg-to muted">to <?= e($tm['to_email']) ?><?= $tm['cc'] ? ', cc ' . e($tm['cc']) : '' ?></div>
            </div>
            <time class="muted"><?= e(date('d M Y, g:i A', strtotime($tm['sent_at'] ?: $tm['created_at']))) ?></time>
          </header>
          <div class="mbx-msg-body"><?= rich_text($tm['body_html']) ?></div>
          <?php $ta = json_decode($tm['attachments'] ?? '[]', true) ?: []; if ($ta): ?>
            <div class="att-chips">
              <?php foreach ($ta as $a): ?><a class="att-chip" href="<?= e(upload_url($a['path'])) ?>" target="_blank" rel="noopener"><?= lucide('paperclip') ?> <?= e($a['name']) ?></a><?php endforeach; ?>
            </div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>

      <div class="mbx-read-foot">
        <form method="post" class="mbx-label-form"><?= csrf_field() ?><input type="hidden" name="_do" value="label"><input type="hidden" name="id" value="<?= $openId ?>"><input type="hidden" name="attach" value="1">
          <select name="label_id" class="form-control form-sm" onchange="this.form.submit()">
            <option value="">+ Add label…</option>
            <?php foreach ($labels as $l): ?><option value="<?= (int) $l['id'] ?>"><?= e($l['name']) ?></option><?php endforeach; ?>
          </select>
        </form>
      </div>
    </div>
  </section>
  <?php endif; ?>
</div>

<?php
// small relative-time helper local fallback
?>
<style>
.mbx{display:grid;grid-template-columns:210px 1fr;gap:0;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;min-height:72vh;}
.mbx.has-open,.mbx:has(.mbx-read){grid-template-columns:210px minmax(300px,1fr) minmax(360px,1.4fr);}
.mbx-list-col.has-open .mbx-prev{display:none;}
.mbx-rail{border-right:1px solid var(--border);padding:14px 10px;display:flex;flex-direction:column;gap:4px;background:var(--surface-2);}
.mbx-compose{margin-bottom:10px;}
.mbx-folders{display:flex;flex-direction:column;gap:2px;}
.mbx-folder{display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:9px;color:var(--text-soft);font-size:.88rem;font-weight:500;transition:.15s;}
.mbx-folder:hover{background:var(--surface);color:var(--text);}
.mbx-folder.is-active{background:var(--grad-brand,#063566);color:#fff;box-shadow:var(--shadow-sm);}
.mbx-folder.is-active .mf-ic{color:#fff;}
.mf-ic{display:grid;place-items:center;width:18px;}.mf-ic svg{width:17px;height:17px;}
.mf-name{flex:1;}
.mf-count{font-size:.72rem;opacity:.7;}
.mf-badge{background:#ef4444;color:#fff;border-radius:20px;padding:1px 7px;font-size:.7rem;font-weight:700;}
.mbx-folder.is-active .mf-count{opacity:.85;}
.mf-dot{width:9px;height:9px;border-radius:50%;}
.mbx-rail-sep{font-size:.66rem;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin:12px 8px 4px;font-weight:700;}
.mbx-rail-form{margin-top:auto;padding-top:10px;}
.mbx-list-col{display:flex;flex-direction:column;border-right:1px solid var(--border);min-width:0;}
.mbx-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 16px;border-bottom:1px solid var(--border);}
.mbx-view-title{font-weight:700;display:inline-flex;align-items:center;gap:7px;}
.mbx-count{background:var(--surface-2);border-radius:20px;padding:1px 9px;font-size:.75rem;}
.mbx-search{display:flex;align-items:center;gap:6px;background:var(--surface-2);border:1px solid var(--border);border-radius:20px;padding:4px 12px;}
.mbx-search input{border:0;background:transparent;outline:none;font-size:.85rem;color:var(--text);width:150px;}
.mbx-search-ic svg{width:15px;height:15px;color:var(--muted);}
.mbx-bulkbar{display:flex;align-items:center;gap:14px;padding:8px 16px;border-bottom:1px solid var(--border);background:var(--surface-2);}
.mbx-bulk-actions{display:flex;gap:2px;}
.mbx-hint{display:flex;align-items:center;gap:10px;padding:10px 16px;background:#fffbeb;border-bottom:1px solid #fde68a;font-size:.85rem;color:#92400e;}
.mbx-list{overflow-y:auto;flex:1;}
.mbx-item{display:flex;align-items:center;gap:8px;padding:10px 14px;border-bottom:1px solid var(--border-soft);transition:.12s;position:relative;}
.mbx-item:hover{background:var(--surface-2);}
.mbx-item.is-open{background:var(--grad-brand-soft,#eff6ff);}
.mbx-item.is-unread .mbx-who,.mbx-item.is-unread .mbx-subj{font-weight:800;color:var(--text);}
.mbx-item.pr-high{box-shadow:inset 3px 0 0 #ef4444;}
.mbx-check{margin:0;}
.mbx-star{background:none;border:0;cursor:pointer;color:var(--muted);padding:2px;}
.mbx-star svg{width:16px;height:16px;}
.mbx-star.on{color:#E67B1D;}.mbx-star.on svg{fill:#E67B1D;}
.mbx-item-main{display:grid;grid-template-columns:150px 1fr auto;gap:10px;align-items:center;flex:1;min-width:0;color:inherit;}
.mbx-who{font-size:.86rem;color:var(--text-soft);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:inline-flex;align-items:center;gap:5px;}
.mbx-imp svg{width:13px;height:13px;color:#E67B1D;}
.mbx-subj{font-size:.88rem;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.mbx-subj svg{width:13px;height:13px;color:var(--muted);vertical-align:middle;}
.mbx-prev{grid-column:2;font-size:.8rem;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.mbx-when{font-size:.75rem;color:var(--muted);white-space:nowrap;}
.mbx-pager{padding:12px 16px;}
.mbx-read{display:flex;flex-direction:column;min-width:0;}
.mbx-read-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 18px;border-bottom:1px solid var(--border);flex-wrap:wrap;}
.mbx-read-actions{display:flex;gap:4px;flex-wrap:wrap;}
.on-star svg,.mbx-read-actions .on-star{color:#E67B1D;}
.mbx-read-body{padding:18px 22px;overflow-y:auto;}
.mbx-read-subj{font-size:1.3rem;margin:0 0 12px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.mbx-read-labels{display:flex;gap:6px;margin-bottom:14px;flex-wrap:wrap;}
.mbx-lbl{font-size:.72rem;padding:2px 10px;border-radius:20px;font-weight:600;}
.mbx-msg{border:1px solid var(--border);border-radius:12px;margin-bottom:14px;overflow:hidden;}
.mbx-msg-head{display:flex;align-items:center;gap:12px;padding:12px 16px;background:var(--surface-2);border-bottom:1px solid var(--border);}
.mbx-avatar{width:38px;height:38px;border-radius:50%;background:var(--grad-brand,#063566);color:#fff;display:grid;place-items:center;font-weight:700;}
.mbx-msg-meta{flex:1;min-width:0;}
.mbx-msg-to{font-size:.78rem;}
.mbx-msg-body{padding:18px 16px;line-height:1.7;color:var(--text);}
.mbx-msg-body img{max-width:100%;height:auto;}
.mbx-read-foot{padding-top:8px;}
@media(max-width:1100px){.mbx.has-open,.mbx:has(.mbx-read){grid-template-columns:1fr;}.mbx-rail{display:none;}.mbx-list-col{border:0;}.mbx-item-main{grid-template-columns:1fr auto;}.mbx-prev{display:none;}}
</style>

<script>
(function () {
  // Star toggle via async POST (progressive; falls back to form).
  document.querySelectorAll('[data-star]').forEach(function (b) {
    b.addEventListener('click', function () {
      var id = b.getAttribute('data-star');
      var fd = new FormData(); fd.append('_do', 'toggle'); fd.append('id', id); fd.append('flag', 'is_starred');
      var m = document.querySelector('meta[name="csrf-token"]'); if (m) fd.append('csrf_token', m.getAttribute('content'));
      fetch(location.href, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function () { b.classList.toggle('on'); });
    });
  });

  // Bulk selection bar
  var boxes = document.querySelectorAll('.mbx-cb'), bar = document.getElementById('bulkBar'),
      sel = document.getElementById('selCount'), all = document.getElementById('checkAll'),
      form = document.getElementById('bulkForm');
  function refresh() {
    var n = document.querySelectorAll('.mbx-cb:checked').length;
    if (sel) sel.textContent = n + ' selected';
    if (bar) bar.hidden = n === 0;
  }
  boxes.forEach(function (c) { c.addEventListener('change', refresh); });
  if (all) all.addEventListener('change', function () { boxes.forEach(function (c) { c.checked = all.checked; }); refresh(); });
  document.querySelectorAll('[data-bulk]').forEach(function (b) {
    b.addEventListener('click', function () {
      if (!document.querySelectorAll('.mbx-cb:checked').length) return;
      document.getElementById('bulkAction').value = b.getAttribute('data-bulk');
      form.submit();
    });
  });
})();
</script>
<?php include __DIR__ . '/partials/foot.php'; ?>
