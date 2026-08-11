<?php
/**
 * =============================================================================
 *  My Membership Card — member self-service (view / flip / print / download)
 * =============================================================================
 *  Behind require_member(): only ever renders the signed-in member's own card,
 *  so no id is accepted from the request. ?format=pdf|png|print.
 *
 *  The page used to render its own bare <html> with a cool grey #eef2f7 field
 *  and system-ui buttons — a page from a different product than the site it
 *  belongs to. It now uses the site header/footer and the shared design system,
 *  and carries the membership details in the page itself so nothing on the back
 *  of the card is reachable only by flipping it.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/membership_card.php';
require_member('/login');

$sess   = current_member();
$member = find('members', (int) ($sess['id'] ?? 0));
if (!$member) {
    redirect('/account');
}
$member   = member_ensure_identity($member);
$format   = (string) get('format', 'print');
/* card_template_id() validates against card_templates() and resolves the
   pre-4-template 'dark' id, so an existing setting keeps working. */
$template = card_template_id((string) get('template', card_default_template()));
$fileBase = 'membership-' . ($member['member_code'] ?: $member['id']);

/* ---- Binary formats short-circuit before any HTML is sent --------------- */
if ($format === 'pdf') {
    $pdf = card_pdf($member, $template);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $fileBase . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}
if ($format === 'png') {
    $png = card_png($member, $template);
    header('Content-Type: image/png');
    header('Content-Disposition: inline; filename="' . $fileBase . '.png"');
    echo $png;
    exit;
}

$templates = card_templates();
$plan      = membership_plan((int) ($member['plan_id'] ?? 0));
$status    = member_effective_status($member);
/* ONE status for the whole screen. The panel used to print member_effective_status()
   raw while the card's chip prints card_status_key(), which promotes an account
   hold over the membership state — so the same member read "Pending" on the card
   and "Not enrolled" in the panel eighteen pixels away. Both were true; neither
   was reconciled. The card is the credential, so the card's answer wins. */
$statusKey = card_status_key($member);
$revoked   = in_array($statusKey, ['suspended', 'cancelled'], true);
$enrolled  = in_array($status, ['active', 'expired'], true);
$daysLeft  = null;
if (!empty($member['expiry_date'])) {
    $daysLeft = (int) floor((strtotime((string) $member['expiry_date']) - time()) / 86400);
}

/* Membership facts, as a definition list. This is the third panel the card's
   back would otherwise hide behind a flip, and it stays readable without JS.

   NO PLACEHOLDERS. This panel was the one place the "no value, no row" rule had
   not been applied: it printed '—' for a missing membership code and join date,
   and — worse — 'Valid until: Lifetime' for anyone whose expiry_date was null,
   INCLUDING members who have never enrolled. So a card that correctly dropped
   its VALID THRU row sat beside a panel inventing a lifetime membership. The same
   bug was fixed inside card_data() and inside verify-member.php and left here. */
$details = [];
if (!empty($member['member_code'])) {
    $details['Membership ID'] = $member['member_code'];
}
$tierName = trim((string) ($plan['name'] ?? ''));
if ($tierName !== '') {
    $details['Tier'] = $tierName;           // no plan, no invented "Member"
}
$details['Status'] = membership_status_label($statusKey);
if (!empty($member['join_date'])) {
    $details['Joined'] = format_date($member['join_date'], 'd M Y');
}
if (!$revoked) {
    if (!empty($member['expiry_date'])) {
        $details['Valid until'] = format_date($member['expiry_date'], 'd M Y');
    } elseif ($enrolled) {
        $details['Valid until'] = 'Lifetime';
    }
}
if (!empty($plan['price'])) {
    $details['Tier fee'] = money((float) $plan['price']);
}

seo_set(['title' => 'My Membership Card', 'robots' => 'noindex,nofollow']);
$page_hero = [
    'title'      => 'My Membership Card',
    'subtitle'   => 'Your card is verified by QR — scan it, or share the verification link with anyone who asks.',
    'breadcrumb' => ['My Account' => url('account'), 'Membership Card' => ''],
];
include __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="container mcard-layout">

        <!-- ------------------------------------------------ the card itself -->
        <div class="mcard-stage">
            <?= card_html($member, $template) ?>

            <?php
            /* The copy follows the state instead of telling everybody to "renew".
               A member with no plan was being asked to renew a membership they
               have never held, under the sentence "This membership is not
               enrolled." */
            $warn = '';
            if ($statusKey === 'none') {
                $warn = 'You do not have an active membership plan yet. Activate one to make this card verifiable.';
            } elseif ($statusKey === 'pending') {
                $warn = 'Your account is still being verified. Your card becomes verifiable once that is confirmed.';
            } elseif ($statusKey === 'expired') {
                $warn = 'Your membership has expired. Renew to keep your card valid for verification.';
            } elseif ($statusKey === 'suspended') {
                $warn = 'Your membership is suspended, so this card will not verify. Please contact our team.';
            } elseif ($statusKey === 'cancelled') {
                $warn = 'Your membership has been cancelled, so this card will not verify.';
            }
            ?>
            <?php if ($warn !== ''): ?>
                <p class="mcard-warn">
                    <?= lucide('alert-triangle') ?>
                    <span><?= e($warn) ?></span>
                </p>
            <?php elseif ($daysLeft !== null && $daysLeft <= 30): ?>
                <p class="mcard-warn is-soon">
                    <?= lucide('clock') ?>
                    <span>Your membership expires in <strong><?= (int) $daysLeft ?> days</strong>.</span>
                </p>
            <?php endif; ?>
        </div>

        <!-- ---------------------------------------------------- side panel -->
        <aside class="mcard-side">

            <div class="mcard-panel">
                <h2 class="mcard-panel-title"><?= lucide('id-card') ?> Membership details</h2>
                <dl class="mcard-dl">
                    <?php foreach ($details as $label => $value): ?>
                        <dt><?= e($label) ?></dt>
                        <dd><?= e((string) $value) ?></dd>
                    <?php endforeach; ?>
                </dl>
            </div>

            <div class="mcard-panel">
                <h2 class="mcard-panel-title"><?= lucide('download') ?> Get your card</h2>
                <div class="mcard-actions">
                    <button class="btn btn-primary btn-block" type="button" onclick="window.print()">
                        <?= lucide('printer') ?> Print card
                    </button>
                    <a class="btn btn-outline btn-block" href="?format=pdf&amp;template=<?= e($template) ?>">
                        <?= lucide('file-text') ?> Download PDF
                    </a>
                    <a class="btn btn-outline btn-block" href="?format=png&amp;template=<?= e($template) ?>" download>
                        <?= lucide('image') ?> Download PNG
                    </a>
                    <button class="btn btn-ghost btn-block" type="button"
                            data-copy="<?= e(member_verify_url($member)) ?>">
                        <?= lucide('link') ?> <span>Copy verification link</span>
                    </button>
                </div>

                <?php if (count($templates) > 1): ?>
                    <form method="get" class="mcard-template">
                        <label for="cardTemplate">Card design</label>
                        <select id="cardTemplate" name="template" onchange="this.form.submit()">
                            <?php foreach ($templates as $k => $label): ?>
                                <option value="<?= e($k) ?>"<?= $k === $template ? ' selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <noscript><button class="btn btn-sm btn-outline" type="submit">Apply</button></noscript>
                    </form>
                <?php endif; ?>
            </div>

            <div class="mcard-panel">
                <h2 class="mcard-panel-title"><?= lucide('settings') ?> Manage membership</h2>
                <div class="mcard-actions">
                    <a class="btn btn-secondary btn-block" href="<?= e(url('membership-renew')) ?>">
                        <?= lucide('refresh-cw') ?> <?= $status === 'active' ? 'Renew membership' : 'Activate membership' ?>
                    </a>
                    <a class="btn btn-outline btn-block" href="<?= e(url('my-payments')) ?>">
                        <?= lucide('receipt') ?> Payments &amp; receipts
                    </a>
                    <a class="btn btn-outline btn-block" href="<?= e(url('donate')) ?>">
                        <?= lucide('heart') ?> Make a donation
                    </a>
                    <a class="btn btn-ghost btn-block" href="<?= e(url('account')) ?>">
                        <?= lucide('arrow-left') ?> Back to my account
                    </a>
                </div>
            </div>

        </aside>
    </div>
</section>

<style>
/* Scoped to this page: layout only — colour and components come from the
   design system so the card page looks like the rest of the site. */
.mcard-layout {
    display: grid; grid-template-columns: minmax(0, 1.35fr) minmax(0, .9fr);
    gap: clamp(1.5rem, 4vw, 3rem); align-items: start;
}
.mcard-stage { position: sticky; top: calc(var(--header-h, 74px) + 1.5rem); }
.mcard-warn {
    display: flex; align-items: flex-start; gap: .6rem;
    margin: 1.25rem auto 0; max-width: 520px;
    padding: .8rem 1rem; border-radius: var(--r-md, 14px);
    background: color-mix(in srgb, var(--danger, #DC2626) 10%, transparent);
    color: #8A1C15; font-size: .9rem; line-height: 1.5;
}
.mcard-warn.is-soon {
    background: var(--yellow, #FFE987); color: var(--primary, #0B4E3D);
}
.mcard-warn svg { flex: none; width: 18px; height: 18px; margin-top: .15rem; }

.mcard-side { display: grid; gap: clamp(1rem, 2vw, 1.5rem); }
.mcard-panel {
    background: var(--surface, #fff); border: 1px solid var(--border, #C1CCB3);
    border-radius: var(--r-lg, 20px); padding: clamp(1.15rem, 2.4vw, 1.6rem);
}
.mcard-panel-title {
    display: flex; align-items: center; gap: .55rem;
    margin: 0 0 1rem; font-size: 1rem; font-weight: 800;
    color: var(--primary, #0B4E3D);
}
.mcard-panel-title svg { width: 18px; height: 18px; }

.mcard-dl { margin: 0; display: grid; grid-template-columns: auto 1fr; gap: .55rem 1rem; }
.mcard-dl dt { font-size: .82rem; font-weight: 600; color: var(--muted, #4B6754); }
.mcard-dl dd {
    margin: 0; font-size: .9rem; font-weight: 700; color: var(--text, #151818);
    text-align: right; word-break: break-word;
}
.mcard-actions { display: grid; gap: .55rem; }
.mcard-template {
    display: flex; align-items: center; gap: .6rem; flex-wrap: wrap;
    margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border, #C1CCB3);
    font-size: .85rem;
}
.mcard-template label { font-weight: 600; color: var(--muted, #4B6754); }
.mcard-template select { flex: 1; min-width: 8rem; padding: .5rem .7rem; }

@media (max-width: 900px) {
    .mcard-layout { grid-template-columns: 1fr; }
    .mcard-stage { position: static; }
}

/* Print: the card alone, at size, with nothing else on the sheet.
   The card's OWN print rules — ID-1 width, print-color-adjust:exact, hiding the
   3D-transformed back face, no shadow, break-inside:avoid — now live in the
   shared shell inside card_html(), so they apply on every page that embeds a
   card instead of only this one. What is left here is this page's furniture. */
@media print {
    .topbar-utility, .navbar, .announcement-bar, .page-hero, .footer,
    .fab-stack, .back-to-top, .cookie-consent, .mcard-side,
    .mcard-warn { display: none !important; }
    .mcard-layout { display: block; }
    .mcard-stage { position: static; }
    .section { padding: 0 !important; }
    @page { margin: 12mm; }
}
</style>

<script>
/* Copy-to-clipboard for the verification link, with a visible result. */
(function () {
    document.querySelectorAll('[data-copy]').forEach(function (btn) {
        var span = btn.querySelector('span'), original = span ? span.textContent : '';
        btn.addEventListener('click', function () {
            var done = function () {
                if (!span) return;
                span.textContent = 'Link copied';
                setTimeout(function () { span.textContent = original; }, 2000);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(btn.dataset.copy).then(done, done);
            } else {
                var t = document.createElement('textarea');
                t.value = btn.dataset.copy; document.body.appendChild(t); t.select();
                try { document.execCommand('copy'); } catch (e) {}
                document.body.removeChild(t); done();
            }
        });
    });
})();
</script>
<?= card_html_assets() ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
