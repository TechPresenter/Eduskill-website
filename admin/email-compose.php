<?php
/**
 * =============================================================================
 *  Admin — Email Composer (rich CKEditor 5 composer + preview + scheduling).
 * =============================================================================
 *  Actions (POST _do): save_draft | send | schedule | test.  Opens with
 *  ?reply=<id> | ?forward=<id> | ?draft=<id> | ?template=<slug> | ?to=<email>.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
require_once __DIR__ . '/../includes/email_center.php';

$accounts   = db_all("SELECT * FROM email_accounts WHERE status = 1 ORDER BY is_default DESC, id ASC");
$signatures = db_all("SELECT * FROM email_signatures ORDER BY is_default DESC, id ASC");
$templates  = db_all("SELECT id, slug, name, subject, body, category FROM email_templates WHERE status = 1 ORDER BY category, name");
$defAccount = $accounts[0] ?? null;

/* -------------------------------------------------- POST actions */
if (is_post()) {
    require_csrf();
    $do   = post('_do');
    $mid  = (int) post('id', 0);

    $atts = [];
    if (!empty($_FILES['attachments']['name'][0])) {
        require_once __DIR__ . '/../includes/upload.php';
        $n = min(5, count($_FILES['attachments']['name']));
        for ($i = 0; $i < $n; $i++) {
            if (($_FILES['attachments']['error'][$i] ?? 4) !== UPLOAD_ERR_OK) continue;
            $f = ['name' => $_FILES['attachments']['name'][$i], 'type' => $_FILES['attachments']['type'][$i],
                  'tmp_name' => $_FILES['attachments']['tmp_name'][$i], 'error' => $_FILES['attachments']['error'][$i],
                  'size' => $_FILES['attachments']['size'][$i]];
            $up = upload_document($f, 'email');
            if ($up['success']) { $atts[] = ['name' => $f['name'], 'path' => $up['path'], 'size' => (int) $f['size']]; }
        }
    }
    // Carry forward existing attachments on an edited draft.
    if ($mid && !$atts) {
        $prev = find('email_messages', $mid);
        if ($prev && !empty($prev['attachments'])) { $atts = json_decode($prev['attachments'], true) ?: []; }
    }

    $schedRaw = trim((string) post('scheduled_at', ''));
    $sched    = $schedRaw !== '' && strtotime($schedRaw) ? date('Y-m-d H:i:s', strtotime($schedRaw)) : null;

    $data = [
        'account_id'   => (int) post('account_id', 0) ?: ($defAccount['id'] ?? null),
        'to_email'     => clean(post('to_email', '')),
        'cc'           => clean(post('cc', '')) ?: null,
        'bcc'          => clean(post('bcc', '')) ?: null,
        'subject'      => clean(post('subject', '')) ?: '(no subject)',
        'body_html'    => post('body', ''),
        'from_name'    => clean(post('from_name', '')) ?: ($defAccount['name'] ?? null),
        'from_email'   => clean(post('from_email', '')) ?: ($defAccount['email'] ?? null),
        'reply_to'     => clean(post('reply_to', '')) ?: null,
        'priority'     => post('priority', 'normal'),
        'read_receipt' => post('read_receipt') ? 1 : 0,
        'attachments'  => $atts,
    ];

    // Send a one-off test copy to the admin without touching the mailbox.
    if ($do === 'test') {
        $to = clean(post('test_to', '')) ?: (current_user()['email'] ?? '');
        if (is_email($to)) {
            send_mail($to, '[TEST] ' . $data['subject'], $data['body_html'], array_filter([
                'from_email' => $data['from_email'], 'from_name' => $data['from_name'],
            ]));
            set_flash('success', 'Test email sent to ' . e($to) . '.');
        } else {
            set_flash('error', 'Enter a valid test address.');
        }
        redirect('/admin/email-compose' . ($mid ? '?draft=' . $mid : ''));
    }

    // "Schedule" with no valid time must NOT land in the scheduled folder with a
    // null time (it would never be picked up by the queue) — fall back to draft.
    $isSchedule = $do === 'schedule' && $sched !== null;
    $data['folder']       = $isSchedule ? 'scheduled' : ($do === 'send' ? 'outbox' : 'drafts');
    $data['scheduled_at'] = $isSchedule ? $sched : null;

    $id = ec_save_message($data, $mid ?: null);

    if ($do === 'send') {
        $ok = ec_deliver($id);
        set_flash($ok ? 'success' : 'warning', $ok ? 'Message sent.' : 'Could not reach the mail server — saved to Outbox to retry. Configure SMTP in Email Settings.');
        redirect('/admin/email-mailbox?folder=' . ($ok ? 'sent' : 'outbox'));
    }
    if ($do === 'schedule') {
        set_flash('success', $sched ? 'Scheduled for ' . date('d M Y, g:i A', strtotime($sched)) . '.' : 'Saved as draft (no valid time given).');
        redirect('/admin/email-mailbox?folder=' . ($sched ? 'scheduled' : 'drafts'));
    }
    set_flash('success', 'Draft saved.');
    redirect('/admin/email-compose?draft=' . $id);
}

/* -------------------------------------------------- Prefill (draft / reply / forward / template) */
$msg = ['to_email' => clean(get('to', '')), 'cc' => '', 'bcc' => '', 'subject' => '', 'body' => '',
        'priority' => 'normal', 'read_receipt' => 0, 'id' => 0, 'account_id' => $defAccount['id'] ?? 0,
        'scheduled_at' => ''];

if ($did = (int) get('draft', 0)) {
    $d = ec_message($did);
    if ($d) {
        $msg = ['to_email' => $d['to_email'], 'cc' => $d['cc'], 'bcc' => $d['bcc'], 'subject' => $d['subject'],
                'body' => $d['body_html'], 'priority' => $d['priority'], 'read_receipt' => (int) $d['read_receipt'],
                'id' => (int) $d['id'], 'account_id' => (int) $d['account_id'], 'scheduled_at' => $d['scheduled_at']];
    }
} elseif ($rid = (int) get('reply', 0)) {
    $r = ec_message($rid);
    if ($r) {
        $msg['to_email'] = $r['from_email'];
        $msg['subject']  = 'Re: ' . preg_replace('/^(re|fwd):\s*/i', '', (string) $r['subject']);
        $msg['body']     = '<br><br><blockquote style="border-left:3px solid #d1d5db;padding-left:12px;color:#6b7280;">' . $r['body_html'] . '</blockquote>';
    }
} elseif ($fid = (int) get('forward', 0)) {
    $f = ec_message($fid);
    if ($f) {
        $msg['subject'] = 'Fwd: ' . preg_replace('/^(re|fwd):\s*/i', '', (string) $f['subject']);
        $msg['body']    = '<br><br>---------- Forwarded message ----------<br>' . $f['body_html'];
    }
} elseif ($tslug = clean(get('template', ''))) {
    $t = find_by('email_templates', 'slug', $tslug);
    if ($t) { $msg['subject'] = $t['subject']; $msg['body'] = $t['body']; }
}

// Append the default signature to a fresh compose.
if ($msg['id'] === 0 && $msg['body'] === '' && ($sig = ec_default_signature()) !== '') {
    $msg['body'] = '<br><br>--<br>' . $sig;
}

$page_title = 'Compose Email';
include __DIR__ . '/partials/head.php';
?>
<div class="compose-wrap adm-rise">
  <form method="post" action="<?= e(admin_url('email-compose')) ?>" enctype="multipart/form-data" id="composeForm" class="compose-shell">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $msg['id'] ?>">
    <input type="hidden" name="_do" id="composeDo" value="send">

    <!-- Editor column -->
    <div class="panel compose-editor">
      <div class="panel-head">
        <h3 class="panel-title"><?= lucide('mail-plus') ?> <?= $msg['id'] ? 'Edit Draft' : 'New Message' ?></h3>
        <div class="compose-head-actions">
          <select name="template_pick" id="tplPick" class="form-control form-sm" aria-label="Insert template">
            <option value="">Insert template…</option>
            <?php foreach ($templates as $t): ?>
              <option value="<?= e($t['slug']) ?>" data-subject="<?= e($t['subject']) ?>" data-body="<?= e($t['body']) ?>"><?= e($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="button" class="btn btn-ghost btn-sm" id="previewBtn"><?= lucide('eye') ?> Preview</button>
        </div>
      </div>
      <div class="panel-body">
        <div class="form-row form-2">
          <div class="form-group">
            <label class="form-label">From account</label>
            <select name="account_id" class="form-control">
              <?php foreach ($accounts as $a): ?>
                <option value="<?= (int) $a['id'] ?>" <?= $msg['account_id'] == $a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?> &lt;<?= e($a['email']) ?>&gt;</option>
              <?php endforeach; ?>
              <?php if (!$accounts): ?><option value="0">Default sender</option><?php endif; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Priority</label>
            <select name="priority" class="form-control">
              <option value="normal" <?= $msg['priority'] === 'normal' ? 'selected' : '' ?>>Normal</option>
              <option value="high" <?= $msg['priority'] === 'high' ? 'selected' : '' ?>>High</option>
              <option value="low" <?= $msg['priority'] === 'low' ? 'selected' : '' ?>>Low</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">To <span class="req">*</span></label>
          <div class="recipient-row">
            <input class="form-control" type="text" name="to_email" id="toField" value="<?= e($msg['to_email']) ?>" placeholder="name@example.com, another@example.com" required>
            <button type="button" class="btn btn-ghost btn-sm" data-toggle-cc>Cc/Bcc</button>
          </div>
        </div>
        <div class="form-row form-2 cc-row" id="ccRow" <?= ($msg['cc'] || $msg['bcc']) ? '' : 'hidden' ?>>
          <div class="form-group"><label class="form-label">Cc</label><input class="form-control" type="text" name="cc" value="<?= e($msg['cc']) ?>"></div>
          <div class="form-group"><label class="form-label">Bcc</label><input class="form-control" type="text" name="bcc" value="<?= e($msg['bcc']) ?>"></div>
        </div>

        <div class="form-group">
          <label class="form-label">Subject <span class="req">*</span></label>
          <input class="form-control" type="text" name="subject" id="subjField" value="<?= e($msg['subject']) ?>" placeholder="Subject line" required>
        </div>

        <div class="form-group">
          <label class="form-label">Message</label>
          <textarea name="body" id="composeBody" data-wysiwyg rows="14"><?= e($msg['body']) ?></textarea>
        </div>

        <div class="form-group">
          <label class="form-label"><?= lucide('paperclip') ?> Attachments <span class="muted">(up to 5 files)</span></label>
          <input class="form-control" type="file" name="attachments[]" multiple>
          <?php $prevAtts = $msg['id'] ? (json_decode(ec_message($msg['id'])['attachments'] ?? '[]', true) ?: []) : []; ?>
          <?php if ($prevAtts): ?>
            <div class="att-chips"><?php foreach ($prevAtts as $a): ?><span class="att-chip"><?= lucide('file') ?> <?= e($a['name']) ?></span><?php endforeach; ?></div>
          <?php endif; ?>
        </div>

        <label class="checkbox"><input type="checkbox" name="read_receipt" value="1" <?= $msg['read_receipt'] ? 'checked' : '' ?>> Request read receipt</label>
      </div>
    </div>

    <!-- Sidebar column: send options -->
    <aside class="compose-side">
      <div class="panel">
        <div class="panel-head"><h3 class="panel-title"><?= lucide('send') ?> Delivery</h3></div>
        <div class="panel-body compose-actions">
          <button type="submit" class="btn btn-primary btn-block btn-lg" data-do="send"><?= lucide('send') ?> Send Now</button>
          <button type="submit" class="btn btn-outline btn-block" data-do="save_draft"><?= lucide('save') ?> Save Draft</button>

          <div class="sched-box">
            <label class="form-label"><?= lucide('clock') ?> Schedule for later</label>
            <input class="form-control" type="datetime-local" name="scheduled_at" value="<?= $msg['scheduled_at'] ? date('Y-m-d\TH:i', strtotime($msg['scheduled_at'])) : '' ?>">
            <button type="submit" class="btn btn-secondary btn-block" data-do="schedule"><?= lucide('calendar-clock') ?> Schedule Send</button>
          </div>

          <div class="test-box">
            <label class="form-label"><?= lucide('flask-conical') ?> Send a test</label>
            <input class="form-control" type="email" name="test_to" placeholder="<?= e(current_user()['email'] ?? 'you@example.com') ?>">
            <button type="submit" class="btn btn-ghost btn-block" data-do="test"><?= lucide('beaker') ?> Send Test</button>
          </div>
        </div>
      </div>

      <div class="panel">
        <div class="panel-head"><h3 class="panel-title"><?= lucide('info') ?> Variables</h3></div>
        <div class="panel-body">
          <p class="muted" style="font-size:.82rem;margin:0 0 8px;">Use these placeholders — they're filled per recipient:</p>
          <div class="var-chips">
            <?php foreach (['{{name}}','{{email}}','{{site_name}}','{{action_url}}','{{year}}'] as $v): ?>
              <button type="button" class="var-chip" data-var="<?= e($v) ?>"><?= e($v) ?></button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </aside>
  </form>
</div>

<!-- Preview modal -->
<div class="cmp-modal" id="previewModal" hidden>
  <div class="cmp-modal-card">
    <div class="cmp-modal-head">
      <strong><?= lucide('eye') ?> Preview</strong>
      <div class="preview-toggle">
        <button type="button" class="pv-btn is-active" data-pv="desktop"><?= lucide('monitor') ?> Desktop</button>
        <button type="button" class="pv-btn" data-pv="mobile"><?= lucide('smartphone') ?> Mobile</button>
      </div>
      <button type="button" class="btn btn-ghost btn-sm" id="previewClose"><?= lucide('x') ?></button>
    </div>
    <div class="cmp-modal-body">
      <iframe id="previewFrame" class="preview-frame desktop" title="Email preview"></iframe>
    </div>
  </div>
</div>

<style>
.compose-wrap{max-width:1200px;margin:0 auto;}
.compose-shell{display:grid;grid-template-columns:1fr 320px;gap:18px;align-items:start;}
.form-row.form-2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.recipient-row{display:flex;gap:8px;}.recipient-row .form-control{flex:1;}
.compose-head-actions{display:flex;gap:8px;align-items:center;}
.form-sm{max-width:190px;padding:6px 10px;font-size:.85rem;}
.compose-actions{display:flex;flex-direction:column;gap:10px;}
.sched-box,.test-box{border-top:1px solid var(--border);padding-top:12px;margin-top:4px;display:flex;flex-direction:column;gap:8px;}
.att-chips{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;}
.att-chip{display:inline-flex;align-items:center;gap:5px;background:var(--surface-2);border:1px solid var(--border);border-radius:8px;padding:4px 10px;font-size:.8rem;}
.var-chips,.preview-toggle{display:flex;flex-wrap:wrap;gap:6px;}
.var-chip{background:var(--grad-brand-soft,#eff6ff);color:var(--brand-600);border:1px solid var(--border);border-radius:20px;padding:4px 12px;font-size:.78rem;font-family:monospace;cursor:pointer;transition:.15s;}
.var-chip:hover{background:var(--brand-600);color:#fff;}
.cmp-modal{position:fixed;inset:0;z-index:1000;background:rgba(11,78,61,.6);display:grid;place-items:center;padding:20px;backdrop-filter:blur(3px);}
/* `display:grid` above beats the [hidden] attribute, which made the modal
   show on page load and made the close button do nothing. */
.cmp-modal[hidden]{display:none;}
.cmp-modal-card{background:var(--surface);border-radius:16px;width:100%;max-width:860px;max-height:90vh;display:flex;flex-direction:column;box-shadow:var(--shadow-lg);overflow:hidden;}
.cmp-modal-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 18px;border-bottom:1px solid var(--border);}
.cmp-modal-body{padding:18px;background:#f3f4f6;overflow:auto;display:grid;place-items:center;}
.pv-btn{background:var(--surface-2);border:1px solid var(--border);border-radius:8px;padding:5px 12px;font-size:.8rem;cursor:pointer;}
.pv-btn.is-active{background:var(--brand-600);color:#fff;border-color:var(--brand-600);}
.preview-frame{background:#fff;border:0;border-radius:10px;box-shadow:var(--shadow);transition:width .25s;}
.preview-frame.desktop{width:100%;min-height:60vh;}
.preview-frame.mobile{width:390px;min-height:60vh;}
@media(max-width:900px){.compose-shell{grid-template-columns:1fr;}}
</style>

<script>
(function () {
  var form = document.getElementById('composeForm');
  // Route each submit button to the right _do without native required blocking test/draft.
  form.querySelectorAll('[data-do]').forEach(function (b) {
    b.addEventListener('click', function () {
      document.getElementById('composeDo').value = b.getAttribute('data-do');
      var soft = (b.getAttribute('data-do') === 'save_draft' || b.getAttribute('data-do') === 'test');
      form.querySelectorAll('[required]').forEach(function (f) { soft ? f.removeAttribute('required') : f.setAttribute('required', 'required'); });
    });
  });

  // Cc/Bcc toggle
  var ccBtn = document.querySelector('[data-toggle-cc]');
  if (ccBtn) ccBtn.addEventListener('click', function () { var r = document.getElementById('ccRow'); r.hidden = !r.hidden; });

  // Template insert
  var tpl = document.getElementById('tplPick');
  if (tpl) tpl.addEventListener('change', function () {
    var o = tpl.options[tpl.selectedIndex]; if (!o.value) return;
    if (!document.getElementById('subjField').value) document.getElementById('subjField').value = o.dataset.subject || '';
    var ta = document.getElementById('composeBody');
    if (ta._ckeditor) ta._ckeditor.setData(o.dataset.body || ''); else ta.value = o.dataset.body || '';
    tpl.selectedIndex = 0;
  });

  // Variable chips -> insert into editor
  document.querySelectorAll('.var-chip').forEach(function (c) {
    c.addEventListener('click', function () {
      var ta = document.getElementById('composeBody'), v = c.getAttribute('data-var');
      if (ta._ckeditor) { var ed = ta._ckeditor; ed.model.change(function (w) { ed.model.insertContent(w.createText(v), ed.model.document.selection.getFirstPosition()); }); }
      else { ta.value += ' ' + v; }
    });
  });

  // Preview (desktop/mobile)
  function currentBody() { var ta = document.getElementById('composeBody'); return ta._ckeditor ? ta._ckeditor.getData() : ta.value; }
  var modal = document.getElementById('previewModal'), frame = document.getElementById('previewFrame');
  function openPreview() {
    var doc = '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><body style="font-family:Arial,sans-serif;color:#1f2937;padding:20px;margin:0;">' + currentBody() + '</body>';
    frame.srcdoc = doc; modal.hidden = false;
  }
  document.getElementById('previewBtn').addEventListener('click', openPreview);
  document.getElementById('previewClose').addEventListener('click', function () { modal.hidden = true; });
  modal.addEventListener('click', function (e) { if (e.target === modal) modal.hidden = true; });
  document.querySelectorAll('.pv-btn').forEach(function (b) {
    b.addEventListener('click', function () {
      document.querySelectorAll('.pv-btn').forEach(function (x) { x.classList.remove('is-active'); });
      b.classList.add('is-active'); frame.className = 'preview-frame ' + b.getAttribute('data-pv');
    });
  });
})();
</script>
<?php include __DIR__ . '/partials/foot.php'; ?>
