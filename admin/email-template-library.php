<?php
/**
 * =============================================================================
 *  Admin — Email Template Library
 *  A premium, browsable gallery of the seeded email_templates. Category filter
 *  chips + live search, a responsive card grid grouped by category, and a
 *  desktop/mobile preview modal (iframe srcdoc, populated from data-* attrs).
 *
 *  Read-only gallery — CRUD lives in email-templates.php (linked from each
 *  card's Edit action + the New Template button). Everything is dynamic from
 *  the DB via ec_templates_by_category().
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
require_once __DIR__ . '/../includes/email_center.php';

/* ---- Data: templates grouped by category (already sorted category, sort_order, name) */
$byCat  = ec_templates_by_category();
$total  = 0;
foreach ($byCat as $rows) { $total += count($rows); }
$premiumCount = 0;
foreach ($byCat as $rows) { foreach ($rows as $r) { if (!empty($r['is_premium'])) { $premiumCount++; } } }
$catCount = count($byCat);

/** Sanitize a stored thumbnail hex, falling back to a neutral slate. */
$bandColor = static function (?string $hex): string {
    $hex = trim((string) $hex);
    return preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $hex) ? $hex : '#64748b';
};

/** Minimal HTML document rendering a template body — used as the card thumbnail (scaled iframe). */
$thumbDoc = static function (string $body): string {
    $body = trim($body) !== '' ? $body : '<p style="color:#9ca3af;font-style:italic;padding-top:40px;text-align:center">No content yet</p>';
    return '<!doctype html><html><head><meta charset="utf-8">'
        . '<style>html,body{margin:0;background:#fff;font-family:Arial,Helvetica,sans-serif;color:#1f2937;font-size:14px;line-height:1.55}'
        . 'body{padding:20px}.tw{max-width:560px;margin:0 auto}img{max-width:100%;height:auto}a{color:#0B4E3D;text-decoration:none}table{max-width:100%}</style>'
        . '</head><body><div class="tw">' . $body . '</div></body></html>';
};

$page_title = 'Template Library';
include __DIR__ . '/partials/head.php';
?>
<div class="etl-wrap adm-rise">

  <div class="admin-page-head">
    <div>
      <h1>Template Library</h1>
      <span class="muted"><?= (int) $total ?> ready-to-send templates across <?= (int) $catCount ?> categories &middot; <?= (int) $premiumCount ?> premium</span>
    </div>
    <a class="btn btn-primary" href="<?= e(admin_url('email-templates?action=create')) ?>"><?= lucide('plus') ?> New Template</a>
  </div>

  <!-- Toolbar: search + category chips -->
  <div class="etl-toolbar">
    <div class="etl-search">
      <?= lucide('search') ?>
      <input type="search" id="etlSearch" placeholder="Search templates by name or description&hellip;" autocomplete="off" spellcheck="false" aria-label="Search templates">
    </div>
    <div class="etl-chips" id="etlChips">
      <button type="button" class="filter-chip is-active" data-cat="*"><?= lucide('layout-grid') ?> All <span class="etl-chip-n"><?= (int) $total ?></span></button>
      <?php foreach ($byCat as $cat => $rows): ?>
        <button type="button" class="filter-chip" data-cat="<?= e($cat) ?>"><?= e($cat) ?> <span class="etl-chip-n"><?= count($rows) ?></span></button>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Grouped grid -->
  <?php if ($total > 0): ?>
    <?php foreach ($byCat as $cat => $rows): ?>
      <section class="etl-cat" data-cat="<?= e($cat) ?>">
        <div class="dash-section-head">
          <h2><?= e($cat) ?></h2>
          <span class="muted"><?= count($rows) ?> template<?= count($rows) === 1 ? '' : 's' ?></span>
        </div>
        <div class="etl-grid">
          <?php foreach ($rows as $r):
            $slug   = (string) ($r['slug'] ?? '');
            $name   = (string) ($r['name'] ?? 'Untitled');
            $subject = (string) ($r['subject'] ?? '');
            $desc   = trim((string) ($r['description'] ?? ''));
            if ($desc === '') { $desc = ec_preview((string) ($r['body'] ?? '')) ?: $subject; }
            $band   = $bandColor($r['thumbnail'] ?? null);
            $vars   = array_values(array_filter(array_map('trim', explode(',', (string) ($r['variables'] ?? '')))));
            $shownVars = array_slice($vars, 0, 4);
            $moreVars  = max(0, count($vars) - count($shownVars));
            $haystack = strtolower($name . ' ' . $desc . ' ' . $subject . ' ' . $cat . ' ' . implode(' ', $vars));
          ?>
          <article class="etl-card" data-cat="<?= e($cat) ?>" data-search="<?= e($haystack) ?>">
            <div class="etl-thumb">
              <iframe class="etl-thumb-fr" loading="lazy" sandbox="" tabindex="-1" aria-hidden="true" title="" srcdoc="<?= e($thumbDoc((string) ($r['body'] ?? ''))) ?>"></iframe>
              <span class="etl-thumb-cat"><?= e($cat) ?></span>
              <?php if (!empty($r['is_premium'])): ?><span class="etl-band-pro"><?= lucide('sparkles') ?> Premium</span><?php endif; ?>
              <button type="button" class="etl-thumb-zoom etl-preview"
                      data-name="<?= e($name) ?>" data-subject="<?= e($subject) ?>" data-cat="<?= e($cat) ?>"
                      data-body="<?= e(json_encode((string) ($r['body'] ?? ''), JSON_UNESCAPED_SLASHES)) ?>" aria-label="Preview <?= e($name) ?>"><?= lucide('maximize-2') ?></button>
            </div>
            <div class="etl-card-body">
              <h3 class="etl-name"><?= e($name) ?></h3>
              <p class="etl-desc"><?= e($desc !== '' ? excerpt($desc, 22) : 'No description provided.') ?></p>
              <?php if ($shownVars): ?>
                <div class="etl-vars">
                  <?php foreach ($shownVars as $v): ?>
                    <span class="etl-var"><?= e($v) ?></span>
                  <?php endforeach; ?>
                  <?php if ($moreVars > 0): ?><span class="etl-var etl-var-more">+<?= (int) $moreVars ?></span><?php endif; ?>
                </div>
              <?php else: ?>
                <div class="etl-vars"><span class="etl-var etl-var-none">No variables</span></div>
              <?php endif; ?>
            </div>
            <div class="etl-actions">
              <?php if ($slug !== ''): ?>
                <a class="btn btn-primary btn-sm" href="<?= e(admin_url('email-compose?template=' . rawurlencode($slug))) ?>"><?= lucide('send') ?> Use</a>
              <?php endif; ?>
              <button type="button" class="btn btn-outline btn-sm etl-preview"
                      data-name="<?= e($name) ?>"
                      data-subject="<?= e($subject) ?>"
                      data-cat="<?= e($cat) ?>"
                      data-body="<?= e(json_encode((string) ($r['body'] ?? ''), JSON_UNESCAPED_SLASHES)) ?>"><?= lucide('eye') ?> Preview</button>
              <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('email-templates?action=edit&id=' . (int) $r['id'])) ?>" title="Edit template"><?= lucide('pen-line') ?></a>
            </div>
          </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>

    <!-- No-match state (shown by JS when search/filter yields nothing) -->
    <div class="empty-state" id="etlEmpty" hidden>
      <div class="icon"><?= lucide('search-x') ?></div>
      No templates match your search. <button type="button" class="btn btn-ghost btn-sm" id="etlReset">Clear filters</button>
    </div>
  <?php else: ?>
    <div class="empty-state">
      <div class="icon"><?= lucide('layout-template') ?></div>
      No email templates yet. <a href="<?= e(admin_url('email-templates?action=create')) ?>">Create your first template</a>.
    </div>
  <?php endif; ?>
</div>

<!-- Preview modal -->
<div class="etl-modal" id="etlModal" hidden>
  <div class="etl-modal-card">
    <div class="etl-modal-head">
      <div class="etl-modal-title">
        <span class="etl-modal-ic"><?= lucide('eye') ?></span>
        <div>
          <strong id="etlModalName">Preview</strong>
          <span class="muted" id="etlModalSubject"></span>
        </div>
      </div>
      <div class="etl-modal-tools">
        <div class="etl-pv-toggle" role="group" aria-label="Preview width">
          <button type="button" class="etl-pv-btn is-active" data-pv="desktop"><?= lucide('monitor') ?> Desktop</button>
          <button type="button" class="etl-pv-btn" data-pv="mobile"><?= lucide('smartphone') ?> Mobile</button>
        </div>
        <a class="btn btn-primary btn-sm" id="etlModalUse" href="#"><?= lucide('send') ?> Use</a>
        <button type="button" class="btn btn-ghost btn-sm" id="etlModalClose" aria-label="Close preview"><?= lucide('x') ?></button>
      </div>
    </div>
    <div class="etl-modal-body">
      <iframe id="etlFrame" class="etl-frame desktop" title="Email template preview" sandbox=""></iframe>
    </div>
  </div>
</div>

<style>
/* ===== Email Template Library (scoped .etl-) ===== */
.etl-toolbar{display:flex;flex-wrap:wrap;gap:14px;align-items:center;justify-content:space-between;margin:4px 0 8px;}
.etl-search{position:relative;flex:1;min-width:240px;max-width:420px;}
.etl-search svg{position:absolute;left:12px;top:50%;transform:translateY(-50%);width:17px;height:17px;color:var(--muted);pointer-events:none;}
.etl-search input{width:100%;padding:10px 14px 10px 38px;border:1px solid var(--border);border-radius:12px;background:var(--surface);color:var(--text);font-size:.9rem;transition:.15s;}
.etl-search input:focus{outline:none;border-color:var(--brand-600);box-shadow:0 0 0 3px var(--grad-brand-soft,rgba(11,78,61,.14));}
.etl-chips{display:flex;flex-wrap:wrap;gap:8px;}
.etl-chips .filter-chip{display:inline-flex;align-items:center;gap:6px;cursor:pointer;}
.etl-chips .filter-chip svg{width:14px;height:14px;}
.etl-chip-n{font-size:.72rem;font-weight:700;opacity:.7;background:rgba(148,163,184,.22);border-radius:20px;padding:0 7px;line-height:1.5;}
.filter-chip.is-active .etl-chip-n{background:rgba(255,255,255,.28);opacity:1;}

.etl-cat{margin-top:18px;}
.etl-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(268px,1fr));gap:16px;}

.etl-card{display:flex;flex-direction:column;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius,14px);overflow:hidden;box-shadow:var(--shadow-sm);transition:transform .16s ease,box-shadow .16s ease,border-color .16s ease;}
.etl-card:hover{transform:translateY(-3px);box-shadow:var(--shadow);border-color:var(--brand-600);}

/* Real template thumbnail (scaled iframe of the actual email) */
.etl-thumb{position:relative;height:170px;overflow:hidden;background:#fff;border-bottom:1px solid var(--border);}
.etl-thumb-fr{position:absolute;top:0;left:0;width:600px;height:440px;border:0;transform:scale(.5);transform-origin:top left;pointer-events:none;background:#fff;}
.etl-thumb::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,transparent 62%,rgba(11,78,61,.06));pointer-events:none;}
.etl-thumb-cat{position:absolute;top:10px;left:10px;z-index:2;color:#fff;font-size:.66rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;background:rgba(11,78,61,.62);border:1px solid rgba(255,255,255,.22);padding:3px 9px;border-radius:20px;backdrop-filter:blur(3px);}
.etl-thumb-zoom{position:absolute;bottom:10px;right:10px;z-index:2;width:32px;height:32px;display:grid;place-items:center;border:0;border-radius:9px;cursor:pointer;
  background:rgba(11,78,61,.62);color:#fff;opacity:0;transform:translateY(4px);transition:opacity .16s ease,transform .16s ease,background .16s ease;backdrop-filter:blur(3px);}
.etl-thumb-zoom svg{width:16px;height:16px;}
.etl-card:hover .etl-thumb-zoom{opacity:1;transform:none;}
.etl-thumb-zoom:hover{background:var(--brand-600);}
.etl-band-pro{position:absolute;right:10px;top:10px;z-index:2;display:inline-flex;align-items:center;gap:4px;color:#78350f;background:linear-gradient(135deg,#fde68a,#fbbf24);font-size:.66rem;font-weight:800;letter-spacing:.03em;text-transform:uppercase;padding:3px 8px;border-radius:20px;box-shadow:0 1px 3px rgba(0,0,0,.25);}
.etl-band-pro svg{width:12px;height:12px;}

.etl-card-body{padding:14px 16px 10px;flex:1;display:flex;flex-direction:column;gap:7px;}
.etl-name{margin:0;font-size:1rem;font-weight:700;color:var(--text);line-height:1.25;}
.etl-desc{margin:0;font-size:.83rem;color:var(--text-soft);line-height:1.45;flex:1;}
.etl-vars{display:flex;flex-wrap:wrap;gap:5px;margin-top:2px;}
.etl-var{font-size:.7rem;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;color:var(--brand-600);background:var(--grad-brand-soft,#eff6ff);border:1px solid var(--border-soft,var(--border));border-radius:6px;padding:2px 7px;}
.etl-var-more{color:var(--muted);background:var(--surface-2);}
.etl-var-none{color:var(--muted);background:var(--surface-2);font-family:inherit;}

.etl-actions{display:flex;align-items:center;gap:7px;padding:10px 14px 14px;border-top:1px solid var(--border-soft,var(--border));}
.etl-actions .btn-primary,.etl-actions .btn-outline{flex:1;justify-content:center;}
.etl-actions .btn svg{width:14px;height:14px;}

/* Modal */
.etl-modal{position:fixed;inset:0;z-index:1200;background:rgba(11,78,61,.62);display:grid;place-items:center;padding:20px;backdrop-filter:blur(3px);}
.etl-modal[hidden]{display:none;}
.etl-modal-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;width:100%;max-width:900px;max-height:92vh;display:flex;flex-direction:column;box-shadow:var(--shadow-lg,var(--shadow));overflow:hidden;}
.etl-modal-head{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:13px 16px;border-bottom:1px solid var(--border);flex-wrap:wrap;}
.etl-modal-title{display:flex;align-items:center;gap:10px;min-width:0;}
.etl-modal-ic{display:grid;place-items:center;width:34px;height:34px;border-radius:10px;background:var(--grad-brand-soft,#eff6ff);color:var(--brand-600);flex:none;}
.etl-modal-ic svg{width:18px;height:18px;}
.etl-modal-title strong{display:block;color:var(--text);font-size:.95rem;line-height:1.2;}
.etl-modal-title .muted{display:block;font-size:.78rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:360px;}
.etl-modal-tools{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.etl-pv-toggle{display:inline-flex;background:var(--surface-2);border:1px solid var(--border);border-radius:10px;padding:2px;}
.etl-pv-btn{display:inline-flex;align-items:center;gap:5px;background:transparent;border:0;color:var(--text-soft);font-size:.78rem;font-weight:600;padding:5px 11px;border-radius:8px;cursor:pointer;}
.etl-pv-btn svg{width:14px;height:14px;}
.etl-pv-btn.is-active{background:var(--brand-600);color:#fff;}
.etl-modal-body{padding:18px;background:#eef1f5;overflow:auto;display:grid;place-items:start center;}
.etl-frame{background:#fff;border:0;border-radius:10px;box-shadow:var(--shadow);transition:width .25s ease;}
.etl-frame.desktop{width:100%;min-height:64vh;}
.etl-frame.mobile{width:390px;min-height:64vh;}

@media (max-width:640px){
  .etl-toolbar{flex-direction:column;align-items:stretch;}
  .etl-search{max-width:none;}
  .etl-modal-title .muted{max-width:180px;}
}
</style>

<script>
(function () {
  var search = document.getElementById('etlSearch');
  var chips  = Array.prototype.slice.call(document.querySelectorAll('#etlChips .filter-chip'));
  var cards  = Array.prototype.slice.call(document.querySelectorAll('.etl-card'));
  var cats   = Array.prototype.slice.call(document.querySelectorAll('.etl-cat'));
  var empty  = document.getElementById('etlEmpty');
  var activeCat = '*';

  function applyFilter() {
    var q = (search.value || '').trim().toLowerCase();
    var anyVisible = false;
    cards.forEach(function (card) {
      var okCat = (activeCat === '*' || card.getAttribute('data-cat') === activeCat);
      var okQ   = (q === '' || (card.getAttribute('data-search') || '').indexOf(q) !== -1);
      var show  = okCat && okQ;
      card.hidden = !show;
      if (show) { anyVisible = true; }
    });
    // Hide category sections that have no visible cards.
    cats.forEach(function (sec) {
      var visible = sec.querySelectorAll('.etl-card:not([hidden])').length;
      sec.hidden = (visible === 0);
    });
    if (empty) { empty.hidden = anyVisible; }
  }

  chips.forEach(function (chip) {
    chip.addEventListener('click', function () {
      chips.forEach(function (c) { c.classList.remove('is-active'); });
      chip.classList.add('is-active');
      activeCat = chip.getAttribute('data-cat');
      applyFilter();
    });
  });
  if (search) { search.addEventListener('input', applyFilter); }

  var resetBtn = document.getElementById('etlReset');
  if (resetBtn) {
    resetBtn.addEventListener('click', function () {
      search.value = '';
      activeCat = '*';
      chips.forEach(function (c) { c.classList.toggle('is-active', c.getAttribute('data-cat') === '*'); });
      applyFilter();
    });
  }

  /* ---- Preview modal ---- */
  var modal   = document.getElementById('etlModal');
  var frame   = document.getElementById('etlFrame');
  var mName   = document.getElementById('etlModalName');
  var mSubj   = document.getElementById('etlModalSubject');
  var mUse    = document.getElementById('etlModalUse');
  var useBase = <?= json_encode(admin_url('email-compose?template=')) ?>;

  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];
  }); }

  function buildDoc(name, subject, body) {
    return '<!doctype html><html><head><meta charset="utf-8">' +
      '<meta name="viewport" content="width=device-width,initial-scale=1">' +
      '<style>body{margin:0;background:#eef1f5;font-family:Arial,Helvetica,sans-serif;color:#1f2937;}' +
      '.wrap{max-width:600px;margin:0 auto;padding:20px;}' +
      '.card{background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08);}' +
      '.subj{padding:14px 22px;border-bottom:1px solid #e5e7eb;font-size:13px;color:#6b7280;}' +
      '.subj b{color:#111827;}' +
      '.content{padding:22px;line-height:1.55;font-size:15px;}' +
      '.content img{max-width:100%;height:auto;}' +
      '.content a{color:#0B4E3D;}' +
      '.foot{padding:14px 22px;color:#9ca3af;font-size:12px;text-align:center;}' +
      '</style></head><body><div class="wrap"><div class="card">' +
      (subject ? '<div class="subj"><b>Subject:</b> ' + esc(subject) + '</div>' : '') +
      '<div class="content">' + (body || '<em style="color:#9ca3af">This template has no body content.</em>') + '</div>' +
      '</div><div class="foot">Template preview &middot; ' + esc(name) + '</div></div></body></html>';
  }

  function openPreview(btn) {
    var name = btn.getAttribute('data-name') || 'Template';
    var subject = btn.getAttribute('data-subject') || '';
    var body = '';
    try { body = JSON.parse(btn.getAttribute('data-body') || '""'); } catch (e) { body = ''; }
    mName.textContent = name;
    mSubj.textContent = subject;
    var use = btn.parentNode.querySelector('a.btn-primary');
    mUse.setAttribute('href', use ? use.getAttribute('href') : useBase);
    frame.srcdoc = buildDoc(name, subject, body);
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
  }
  function closePreview() { modal.hidden = true; document.body.style.overflow = ''; }

  document.querySelectorAll('.etl-preview').forEach(function (b) {
    b.addEventListener('click', function () { openPreview(b); });
  });
  document.getElementById('etlModalClose').addEventListener('click', closePreview);
  modal.addEventListener('click', function (e) { if (e.target === modal) closePreview(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) closePreview(); });

  document.querySelectorAll('.etl-pv-btn').forEach(function (b) {
    b.addEventListener('click', function () {
      document.querySelectorAll('.etl-pv-btn').forEach(function (x) { x.classList.remove('is-active'); });
      b.classList.add('is-active');
      frame.className = 'etl-frame ' + b.getAttribute('data-pv');
    });
  });
})();
</script>
<?php include __DIR__ . '/partials/foot.php'; ?>
