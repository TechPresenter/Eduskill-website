<?php
/**
 * =============================================================================
 *  Government registrations & legal compliance
 * =============================================================================
 *  Single source of truth for the organisation's statutory details. Every value
 *  falls back to the config constant but is overridable from the settings table
 *  (Admin → Website Settings), so the details stay admin-editable.
 *
 *    org_registrations()        -> flat array of the statutory details
 *    render_registrations_grid()-> the "Government Registrations & Legal
 *                                  Compliance" card section (About/Legal pages)
 *    render_registration_strip()-> compact footer credential block
 * =============================================================================
 */

declare(strict_types=1);

/** All statutory details, settings-first with config fallbacks. */
function org_registrations(): array
{
    return [
        'name'         => get_setting('site_name', SITE_NAME),
        'legal_status' => get_setting('legal_status', defined('SITE_LEGAL_STATUS') ? SITE_LEGAL_STATUS : 'Section 8 Company (Non-Profit Organization)'),
        'incorporated' => get_setting('incorporation_date', defined('SITE_INCORP_DATE') ? SITE_INCORP_DATE : ''),
        'cin'          => get_setting('cin', SITE_CIN),
        'pan'          => get_setting('pan', defined('SITE_PAN') ? SITE_PAN : ''),
        'tan'          => get_setting('tan', defined('SITE_TAN') ? SITE_TAN : ''),
        'darpan_id'    => get_setting('ngo_darpan_id', defined('SITE_DARPAN_ID') ? SITE_DARPAN_ID : ''),
        'reg_office'   => get_setting('registered_office', defined('SITE_REG_OFFICE') ? SITE_REG_OFFICE : ''),
    ];
}

/**
 * The full "Government Registrations & Legal Compliance" section.
 * Renders modern responsive cards with official icons and check-marked facts.
 *
 * @param bool $withHeading Include the section heading block.
 */
function render_registrations_grid(bool $withHeading = true): void
{
    $r = org_registrations();

    $cards = [
        [
            'icon'  => 'landmark',
            'tone'  => 'blue',
            'title' => 'Ministry of Corporate Affairs (MCA)',
            'badge' => 'MCA Registered',
            'lead'  => 'Registered under the Companies Act, 2013 as a ' . $r['legal_status'] . '.',
            'facts' => array_values(array_filter([
                'Registered under Companies Act, 2013',
                'Section 8 Non-Profit Organization',
                $r['cin'] ? 'CIN: ' . $r['cin'] : '',
                $r['incorporated'] ? 'Incorporated: ' . $r['incorporated'] : '',
            ])),
        ],
        [
            'icon'  => 'receipt-indian-rupee',
            'tone'  => 'violet',
            'title' => 'Income Tax Department',
            'badge' => 'PAN & TAN Allotted',
            'lead'  => 'Statutory tax registrations issued in the name of the Foundation.',
            'facts' => array_values(array_filter([
                $r['pan'] ? 'PAN: ' . $r['pan'] : '',
                $r['tan'] ? 'TAN: ' . $r['tan'] : '',
            ])),
        ],
        [
            'icon'  => 'badge-check',
            'tone'  => 'green',
            'title' => 'NITI Aayog — NGO Darpan',
            'badge' => 'Darpan Registered',
            'lead'  => 'Enrolled on the NITI Aayog NGO Darpan Portal, Government of India.',
            'facts' => array_values(array_filter([
                'Registered with NITI Aayog NGO Darpan Portal',
                $r['darpan_id'] ? 'NGO Darpan ID: ' . $r['darpan_id'] : '',
            ])),
        ],
    ];
    ?>
    <div class="reg-wrap">
        <?php if ($withHeading): ?>
            <div class="section-head">
                <span class="eyebrow"><?= lucide('shield-check') ?> Verified &amp; Compliant</span>
                <h2 class="section-title">Government Registrations &amp; Legal Compliance</h2>
                <p class="section-subtitle"><?= e($r['name']) ?> is a Government-registered <?= e($r['legal_status']) ?>, operating in full compliance with Indian law.</p>
            </div>
        <?php endif; ?>

        <div class="reg-grid">
            <?php foreach ($cards as $c): if (!$c['facts']) continue; ?>
                <article class="reg-card reg-<?= e($c['tone']) ?>">
                    <div class="reg-card-top">
                        <span class="reg-ico"><?= lucide($c['icon']) ?></span>
                        <span class="reg-badge"><?= lucide('check') ?> <?= e($c['badge']) ?></span>
                    </div>
                    <h3 class="reg-title"><?= e($c['title']) ?></h3>
                    <p class="reg-lead"><?= e($c['lead']) ?></p>
                    <ul class="reg-facts">
                        <?php foreach ($c['facts'] as $f): ?>
                            <li><span class="reg-tick"><?= lucide('check') ?></span><span><?= e($f) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($r['reg_office']): ?>
            <div class="reg-office">
                <span class="reg-office-ico"><?= lucide('map-pin') ?></span>
                <div>
                    <strong>Registered Office</strong>
                    <p><?= e($r['reg_office']) ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <style>
    .reg-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(290px,1fr));gap:1.4rem}
    .reg-card{position:relative;background:var(--surface,#fff);border:1px solid var(--border,#e2e8f0);border-radius:18px;padding:1.6rem;
        box-shadow:0 1px 2px rgba(11,78,61,.04),0 18px 40px -30px rgba(11,78,61,.45);overflow:hidden;
        transition:transform .2s ease,box-shadow .2s ease,border-color .2s ease}
    .reg-card::before{content:"";position:absolute;inset:0 0 auto 0;height:4px;background:var(--rc,#0B4E3D)}
    .reg-card:hover{transform:translateY(-5px);box-shadow:0 1px 2px rgba(11,78,61,.05),0 28px 56px -28px rgba(11,78,61,.55);border-color:var(--rc,#0B4E3D)}
    .reg-blue{--rc:#0B4E3D;--rcs:rgba(11,78,61,.12)}
    .reg-violet{--rc:#174D3D;--rcs:rgba(23,77,61,.12)}
    .reg-green{--rc:#1F5C48;--rcs:rgba(31,92,72,.12)}
    .reg-card-top{display:flex;align-items:center;justify-content:space-between;gap:.6rem;margin-bottom:1rem}
    .reg-ico{width:52px;height:52px;border-radius:15px;display:grid;place-items:center;background:var(--rcs);color:var(--rc)}
    .reg-ico svg{width:25px;height:25px}
    .reg-badge{display:inline-flex;align-items:center;gap:.3rem;padding:.32rem .7rem;border-radius:999px;background:var(--rcs);color:var(--rc);
        font-size:.72rem;font-weight:800;letter-spacing:.02em;white-space:nowrap}
    .reg-badge svg{width:.95em;height:.95em;stroke-width:3}
    .reg-title{font-size:1.08rem;font-weight:800;margin:0 0 .4rem;letter-spacing:-.01em}
    .reg-lead{font-size:.88rem;color:var(--muted,#64748b);line-height:1.6;margin:0 0 1rem}
    .reg-facts{list-style:none;margin:0;padding:0;display:grid;gap:.55rem}
    .reg-facts li{display:flex;align-items:flex-start;gap:.55rem;font-size:.9rem;font-weight:600;color:var(--text-soft,#334155);line-height:1.45}
    .reg-tick{flex:0 0 auto;width:20px;height:20px;border-radius:50%;display:grid;place-items:center;background:var(--rc,#0B4E3D);color:#fff;margin-top:1px}
    .reg-tick svg{width:12px;height:12px;stroke-width:3.5}
    .reg-office{display:flex;gap:1rem;align-items:flex-start;margin-top:1.5rem;padding:1.2rem 1.4rem;border-radius:16px;
        background:var(--surface-2,#f8fafc);border:1px solid var(--border,#e2e8f0)}
    .reg-office-ico{flex:0 0 auto;width:44px;height:44px;border-radius:13px;display:grid;place-items:center;background:rgba(11,78,61,.12);color:#0B4E3D}
    .reg-office strong{display:block;font-size:.78rem;text-transform:uppercase;letter-spacing:.07em;color:var(--muted,#64748b);margin-bottom:.2rem}
    .reg-office p{margin:0;font-weight:650;color:var(--text,#0f172a);line-height:1.55}
    @media (max-width:640px){.reg-card{padding:1.3rem}.reg-card-top{flex-direction:column;align-items:flex-start}}
    </style>
    <?php
}

/** Compact credential block for the footer. */
function render_registration_strip(): void
{
    $r = org_registrations();
    ?>
    <div class="footer-gov">
        <div class="fg-head"><span class="fg-ico"><?= lucide('shield-check') ?></span>
            <div><strong>Government Registered NGO</strong>
                <span>Section 8 Company &nbsp;|&nbsp; MCA Registered &nbsp;|&nbsp; NITI Aayog NGO Darpan Registered</span>
            </div>
        </div>
        <div class="fg-ids">
            <?php if ($r['cin']): ?><span><?= lucide('landmark') ?> CIN: <b><?= e($r['cin']) ?></b></span><?php endif; ?>
            <?php if ($r['darpan_id']): ?><span><?= lucide('badge-check') ?> NGO Darpan ID: <b><?= e($r['darpan_id']) ?></b></span><?php endif; ?>
        </div>
    </div>
    <style>
    .footer-gov{margin-top:1.4rem;padding:1.1rem 1.3rem;border-radius:14px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.12);
        display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:1rem}
    .fg-head{display:flex;align-items:center;gap:.8rem}
    .fg-ico{flex:0 0 auto;width:42px;height:42px;border-radius:12px;display:grid;place-items:center;background:rgba(47,128,101,.18);color:#4CA383}
    .fg-ico svg{width:21px;height:21px}
    .fg-head strong{display:block;font-size:.95rem;font-weight:800;line-height:1.3}
    .fg-head span{font-size:.8rem;opacity:.82;line-height:1.5}
    .fg-ids{display:flex;flex-wrap:wrap;gap:.5rem .9rem}
    .fg-ids span{display:inline-flex;align-items:center;gap:.4rem;font-size:.8rem;opacity:.9}
    .fg-ids svg{width:1em;height:1em;opacity:.85}
    .fg-ids b{font-weight:800;letter-spacing:.02em}
    @media (max-width:700px){.footer-gov{flex-direction:column;align-items:flex-start}}
    </style>
    <?php
}
