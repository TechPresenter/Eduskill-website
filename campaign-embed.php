<?php
/**
 * =============================================================================
 *  Campaign embed widget — standalone iframe for third-party websites
 * =============================================================================
 *  ?slug=<campaign> (or ?id=). Shows the progress bar + a Donate button that
 *  opens the donation page in the top window. No site chrome; safe to iframe.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

$slug = clean(get('slug', ''));
$id   = (int) get('id', 0);
// Only publicly-visible campaigns are embeddable (no drafts/paused/deleted).
$c = $slug !== ''
    ? db_row("SELECT * FROM campaigns WHERE slug = :s AND deleted_at IS NULL AND status IN ('active','completed') LIMIT 1", [':s' => $slug])
    : ($id ? db_row("SELECT * FROM campaigns WHERE id = :i AND deleted_at IS NULL AND status IN ('active','completed') LIMIT 1", [':i' => $id]) : null);

header('Content-Type: text/html; charset=utf-8');
// Allow embedding anywhere (this page is intentionally embeddable).
header_remove('X-Frame-Options');
header("Content-Security-Policy: frame-ancestors *;");

if (!$c) {
    exit('<!doctype html><meta charset="utf-8"><div style="font-family:sans-serif;padding:16px;color:#6b7280">Campaign not found.</div>');
}
$p    = campaign_progress($c);
$sym  = donation_symbol(get_setting('donation_currency', 'INR'));
$link = abs_url('donate-pay?campaign=' . (int) $c['id']);
$img  = image_url($c['image'] ?? null, 'blog');
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($c['title']) ?></title>
<style>
*{box-sizing:border-box;} body{margin:0;font-family:system-ui,Segoe UI,Roboto,sans-serif;color:#1f2937;background:#fff;}
.w{max-width:360px;margin:0 auto;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;box-shadow:0 6px 24px rgba(0,0,0,.06);}
.w .thumb{aspect-ratio:16/9;background:#eef2f7 center/cover no-repeat;}
.w .b{padding:14px 16px;}
.w h3{margin:0 0 8px;font-size:1.05rem;line-height:1.25;}
.bar{background:#e5e7eb;border-radius:99px;height:9px;overflow:hidden;margin:6px 0;}
.bar span{display:block;height:100%;background:linear-gradient(90deg,#0B4E3D,#2F8065);}
.row{display:flex;justify-content:space-between;font-size:.82rem;color:#6b7280;}
.row strong{color:#111827;}
.btn{display:block;text-align:center;margin-top:12px;background:#0B4E3D;color:#fff;text-decoration:none;font-weight:700;padding:11px;border-radius:9px;}
.credit{text-align:center;font-size:.68rem;color:#9ca3af;margin-top:8px;}
</style></head><body>
<div class="w">
    <div class="thumb" style="background-image:url('<?= e($img) ?>');"></div>
    <div class="b">
        <h3><?= e($c['title']) ?></h3>
        <div class="row"><span><strong><?= e(money($p['raised'], $sym, 0)) ?></strong> raised</span><span>of <?= e(money($p['goal'], $sym, 0)) ?></span></div>
        <div class="bar"><span style="width:<?= (int) round($p['percent']) ?>%"></span></div>
        <div class="row"><span><?= (int) round($p['percent']) ?>% funded</span><span><?= (int) $p['donors'] ?> donors</span></div>
        <a class="btn" href="<?= e($link) ?>" target="_top">Donate now</a>
        <div class="credit">Powered by <?= e(get_setting('site_name', SITE_NAME)) ?></div>
    </div>
</div>
</body></html>
