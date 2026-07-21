<?php
/**
 * Content helpers — campaign presentation + the dynamic homepage section renderer.
 */

declare(strict_types=1);

defined('ESK') || exit('No direct access.');

function campaign_progress(array $c): int
{
    $goal = (int) ($c['goal_amount'] ?? 0);
    $raised = (int) ($c['raised_amount'] ?? 0);
    return $goal <= 0 ? 0 : (int) min(100, (int) round($raised * 100 / $goal));
}

function campaign_days_left(array $c): ?int
{
    if (empty($c['ends_at'])) {
        return null;
    }
    $ts = strtotime((string) $c['ends_at'] . ' 23:59:59');
    if ($ts === false) {
        return null;
    }
    $d = (int) ceil(($ts - time()) / 86400);
    return $d >= 0 ? $d : null;
}

/** A campaign card, reused by the listing page and the campaign_list section. */
function campaign_card(array $c): string
{
    $pct = campaign_progress($c);
    $days = campaign_days_left($c);
    ob_start(); ?>
    <a href="<?= e(url('campaign-details.php?slug=' . urlencode((string) $c['slug']))) ?>" class="content-card group">
      <div class="content-card-media">
        <?php if (!empty($c['image'])): ?>
          <img src="<?= e(asset($c['image'])) ?>" alt="" loading="lazy">
        <?php else: ?>
          <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-brand-500 to-brand-700 text-on-brand"><?= icon('campaigns', 'h-10 w-10 opacity-70') ?></div>
        <?php endif; ?>
        <?php if (!empty($c['category'])): ?><span class="absolute left-3 top-3 rounded-full bg-surface/90 px-2.5 py-1 text-2xs font-semibold text-content backdrop-blur"><?= e($c['category']) ?></span><?php endif; ?>
        <?php if (($c['status'] ?? '') === 'completed'): ?><span class="absolute right-3 top-3 rounded-full bg-success-500 px-2.5 py-1 text-2xs font-semibold text-white">Goal reached</span><?php endif; ?>
      </div>
      <div class="content-card-body">
        <h3 class="content-card-title"><?= e($c['title']) ?></h3>
        <?php if (!empty($c['summary'])): ?><p class="content-card-excerpt"><?= e($c['summary']) ?></p><?php endif; ?>
        <div class="mt-auto pt-4">
          <div class="h-2 overflow-hidden rounded-full bg-surface-sunken"><div class="h-full rounded-full bg-brand-600" style="width: <?= $pct ?>%"></div></div>
          <div class="mt-2 flex items-baseline justify-between">
            <span class="text-sm font-semibold text-content">₹<?= e(inr((int) $c['raised_amount'])) ?></span>
            <span class="text-xs font-medium text-content-muted"><?= $pct ?>% of ₹<?= e(inr((int) $c['goal_amount'])) ?></span>
          </div>
          <div class="mt-1 text-xs text-content-subtle"><?= (int) $c['donor_count'] ?> donor<?= (int) $c['donor_count'] === 1 ? '' : 's' ?><?php if ($days !== null): ?> · <?= $days ?> day<?= $days === 1 ? '' : 's' ?> left<?php endif; ?></div>
        </div>
      </div>
    </a>
    <?php return (string) ob_get_clean();
}

/** A CMS-backed content page: render its sections, or a friendly placeholder if not published yet. */
function cms_page(string $slug, string $title): void
{
    $exists = (int) db_val("SELECT COUNT(*) FROM pages WHERE slug = ? AND status = 'published' AND deleted_at IS NULL", [$slug]) > 0;
    if ($exists) {
        render_sections($slug);
    } else {
        echo '<section class="section"><div class="container-site"><div class="mx-auto max-w-3xl">'
            . '<h1 class="section-heading">' . e($title) . '</h1>'
            . '<p class="section-subheading">This page is being prepared. Please check back soon.</p></div></div></section>';
    }
}

/**
 * Render a page's dynamic sections (hero, counters, campaign_list, …) by including the matching
 * partial from includes/sections/. Allowlisted types only; each section is fault-isolated.
 */
function render_sections(string $slug): void
{
    $page = db_one("SELECT id FROM pages WHERE slug = ? AND status = 'published' AND deleted_at IS NULL LIMIT 1", [$slug]);
    if ($page === null) {
        return;
    }
    $rows = db_all(
        'SELECT type, settings_json FROM page_sections WHERE page_id = ? AND is_visible = 1 ORDER BY position ASC, id ASC',
        [(int) $page['id']]
    );
    $allowed = ['hero', 'rich_text', 'features', 'counters', 'cta_banner', 'faq', 'campaign_list', 'team_grid', 'testimonial_slider'];
    foreach ($rows as $row) {
        $type = (string) $row['type'];
        if (!in_array($type, $allowed, true)) {
            continue;
        }
        $file = INCLUDES_PATH . '/sections/' . $type . '.php';
        if (!is_file($file)) {
            continue;
        }
        $s = json_decode((string) ($row['settings_json'] ?? '{}'), true);
        if (!is_array($s)) {
            $s = [];
        }
        try {
            include $file;   // partial reads $s
        } catch (Throwable $e) {
            error_log('[section:' . $type . '] ' . $e->getMessage());
        }
    }
}
