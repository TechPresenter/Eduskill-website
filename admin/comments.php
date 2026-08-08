<?php
/**
 * =============================================================================
 *  Admin — Blog Comments (INBOX module).
 *  Moderation queue for reader comments on blog posts:
 *    list + view + status change (pending / approved / spam) + delete.
 *  NO create/edit form — comments are submitted from the public site.
 *  Follows the standard admin pattern: CSRF on every POST handler, prepared
 *  statements, e() on output, paginate() list with search + status filter,
 *  activity logging, flash + redirect.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table   = 'blog_comments';
$statuses = ['pending', 'approved', 'spam'];
$action  = get('action', 'list');
$id      = (int) get('id', 0);

/* -------------------------------------------------------------- shared filter state */
$statusFilter = (string) get('status', 'all');
if (!in_array($statusFilter, ['all', 'pending', 'approved', 'spam'], true)) {
    $statusFilter = 'all';
}
$search = trim((string) get('q', ''));

/** Build the list URL to return to after an action, preserving the active filter. */
$backTo = static function (): string {
    $qs = [];
    $fs = (string) post('f_status', '');
    if (in_array($fs, ['pending', 'approved', 'spam'], true)) {
        $qs['status'] = $fs;
    }
    $fq = clean(post('f_q', ''));
    if ($fq !== '') {
        $qs['q'] = $fq;
    }
    return '/admin/comments' . ($qs ? '?' . http_build_query($qs) : '');
};

/* -------------------------------------------------------------- STATUS CHANGE */
if (is_post() && post('_do') === 'status') {
    require_csrf();
    $cid = (int) post('id', 0);
    $new = (string) post('status', '');
    if (!in_array($new, $statuses, true)) {
        set_flash('error', 'Invalid status.');
        redirect($backTo());
    }
    $row = find($table, $cid);
    if ($row) {
        db_update($table, ['status' => $new], 'id = :id', [':id' => $cid]);
        log_activity('update', 'comments', 'Set comment #' . $cid . ' status to ' . $new);
        set_flash('success', 'Comment marked as ' . $new . '.');
    } else {
        set_flash('error', 'Comment not found.');
    }
    redirect($backTo());
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row = find($table, $delId);
    if ($row) {
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'comments', 'Deleted comment #' . $delId);
        set_flash('success', 'Comment deleted.');
    }
    redirect($backTo());
}

/* -------------------------------------------------------------- REPLY (admin publishes a threaded reply) */
if (is_post() && post('_do') === 'reply') {
    require_csrf();
    $cid    = (int) post('id', 0);
    $parent = find($table, $cid);
    $body   = clean(post('reply', ''));
    if ($parent && $body !== '') {
        $me = current_user();
        db_insert($table, [
            'blog_id'    => (int) $parent['blog_id'],
            'parent_id'  => $cid,
            'name'       => $me['name'] ?? get_setting('site_name', SITE_NAME),
            'email'      => $me['email'] ?? ('admin@' . (parse_url(APP_URL, PHP_URL_HOST) ?: 'localhost')),
            'comment'    => $body,
            'status'     => 'approved',
            'ip_address' => client_ip(),
        ]);
        if ($parent['status'] === 'pending') {
            db_update($table, ['status' => 'approved'], 'id = :id', [':id' => $cid]);
        }
        log_activity('create', 'comments', 'Replied to comment #' . $cid);
        set_flash('success', 'Reply posted and published.');
    } else {
        set_flash('error', 'Reply cannot be empty.');
    }
    redirect('/admin/comments?action=view&id=' . $cid);
}

/* -------------------------------------------------------------- reusable action forms */
/** Render a small POST form that changes a comment's status. */
$statusForm = static function (int $cid, string $newStatus, string $icon, string $title, string $btnClass = '') use ($statusFilter, $search): void {
    ?>
    <form method="post" action="<?= e(admin_url('comments')) ?>" style="display:inline;">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="status">
        <input type="hidden" name="id" value="<?= $cid ?>">
        <input type="hidden" name="status" value="<?= e($newStatus) ?>">
        <input type="hidden" name="f_status" value="<?= e($statusFilter) ?>">
        <input type="hidden" name="f_q" value="<?= e($search) ?>">
        <button class="icon-btn <?= e($btnClass) ?>" type="submit" title="<?= e($title) ?>"><?= $icon ?></button>
    </form>
    <?php
};

/** Render the delete POST form (with confirm). */
$deleteForm = static function (int $cid) use ($statusFilter, $search): void {
    ?>
    <form method="post" action="<?= e(admin_url('comments')) ?>" data-confirm="Delete this comment permanently?" style="display:inline;">
        <?= csrf_field() ?>
        <input type="hidden" name="_do" value="delete">
        <input type="hidden" name="id" value="<?= $cid ?>">
        <input type="hidden" name="f_status" value="<?= e($statusFilter) ?>">
        <input type="hidden" name="f_q" value="<?= e($search) ?>">
        <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
    </form>
    <?php
};

/** Map a status to its pill colour class. */
$pillClass = static function (string $s): string {
    return match ($s) {
        'approved' => 'pill-green',
        'spam'     => 'pill-red',
        default    => 'pill-amber',
    };
};

/* -------------------------------------------------------------- VIEW (single comment) */
if ($action === 'view') {
    $row = db_row(
        "SELECT bc.*, b.title AS blog_title, b.slug AS blog_slug
           FROM blog_comments bc
           JOIN blogs b ON b.id = bc.blog_id
          WHERE bc.id = :id",
        [':id' => $id]
    );
    if (!$row) {
        set_flash('error', 'Comment not found.');
        redirect('/admin/comments');
    }

    $page_title = 'View Comment';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1>Comment #<?= (int) $row['id'] ?></h1><span class="muted">Blog Comments / View</span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('comments')) ?>">← Back to inbox</a>
    </div>

    <div class="panel">
        <div class="panel-head">
            <h3>On &ldquo;<a href="<?= e(admin_url('blogs?action=edit&id=' . (int) $row['blog_id'])) ?>"><?= e($row['blog_title']) ?></a>&rdquo;</h3>
            <span class="pill <?= e($pillClass($row['status'])) ?>"><?= e(ucfirst($row['status'])) ?></span>
        </div>
        <div class="panel-body">
            <div class="table-wrap">
                <table class="admin-table">
                    <tbody>
                        <tr><th style="width:180px;">Author</th><td><strong><?= e($row['name']) ?></strong></td></tr>
                        <tr><th>Email</th><td><a href="mailto:<?= e($row['email']) ?>"><?= e($row['email']) ?></a></td></tr>
                        <?php if (!empty($row['website'])): $siteHref = safe_href($row['website']); ?>
                        <tr><th>Website</th><td>
                            <?php if ($siteHref !== ''): ?>
                                <a href="<?= e($siteHref) ?>" target="_blank" rel="noopener nofollow noreferrer"><?= e($row['website']) ?></a>
                            <?php else: /* Commenter-supplied and not a safe http(s) URL — show it, never link it. */ ?>
                                <span class="muted"><?= e($row['website']) ?></span>
                                <span class="badge badge-danger" title="Blocked: not a valid http(s) address">unsafe link</span>
                            <?php endif; ?>
                        </td></tr>
                        <?php endif; ?>
                        <?php if (!empty($row['parent_id'])): ?>
                        <tr><th>Reply to</th><td>Comment #<?= (int) $row['parent_id'] ?></td></tr>
                        <?php endif; ?>
                        <tr><th>Submitted</th><td><?= e(format_datetime($row['created_at'])) ?> <span class="muted">(<?= e(time_ago($row['created_at'])) ?>)</span></td></tr>
                        <?php if (!empty($row['ip_address'])): ?>
                        <tr><th>IP address</th><td><?= e($row['ip_address']) ?></td></tr>
                        <?php endif; ?>
                        <tr><th>Comment</th><td style="white-space:pre-wrap;"><?= e($row['comment']) ?></td></tr>
                    </tbody>
                </table>
            </div>

            <div class="form-actions" style="margin-top:1.25rem;">
                <?php
                if ($row['status'] !== 'approved') $statusForm((int) $row['id'], 'approved', lucide('circle-check') . ' Approve', 'Approve', '');
                if ($row['status'] !== 'pending')  $statusForm((int) $row['id'], 'pending',  lucide('undo-2') . ' Pending', 'Mark pending', '');
                if ($row['status'] !== 'spam')     $statusForm((int) $row['id'], 'spam',     lucide('ban') . ' Spam',    'Mark as spam', '');
                $deleteForm((int) $row['id']);
                ?>
            </div>

            <div class="divider"></div>
            <h4 class="mb-2"><?= lucide('reply') ?> Reply publicly</h4>
            <form method="post" action="<?= e(admin_url('comments')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="_do" value="reply">
                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                <div class="form-group">
                    <textarea class="form-textarea" name="reply" rows="4" required placeholder="Write a reply — it publishes immediately under your name, threaded beneath this comment."></textarea>
                </div>
                <button class="btn btn-primary" type="submit"><?= lucide('send') ?> Post reply</button>
            </form>
        </div>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST (inbox) */
$page_title = 'Blog Comments';

// Status counts for the stat cards / filter tabs.
$counts = [
    'pending'  => db_count($table, "status = :s", [':s' => 'pending']),
    'approved' => db_count($table, "status = :s", [':s' => 'approved']),
    'spam'     => db_count($table, "status = :s", [':s' => 'spam']),
];
$counts['all'] = $counts['pending'] + $counts['approved'] + $counts['spam'];

// Build the filtered query.
$where  = '1=1';
$params = [];
if ($statusFilter !== 'all') {
    $where .= ' AND bc.status = :st';
    $params[':st'] = $statusFilter;
}
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', bc.name, bc.email, bc.comment, b.title) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}

$p = paginate(
    "SELECT bc.*, b.title AS blog_title, b.slug AS blog_slug
       FROM blog_comments bc
       JOIN blogs b ON b.id = bc.blog_id
      WHERE $where
      ORDER BY bc.created_at DESC",
    $params,
    15
);

// Filter tabs (label => [status, count, bg]).
$tabs = [
    'all'      => ['All',      $counts['all'],      'bg-blue',  'message-square'],
    'pending'  => ['Pending',  $counts['pending'],  'bg-amber', 'hourglass'],
    'approved' => ['Approved', $counts['approved'], 'bg-green', 'circle-check'],
    'spam'     => ['Spam',     $counts['spam'],     'bg-rose',  'ban'],
];

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Blog Comments</h1><span class="muted"><?= (int) $counts['all'] ?> total · <?= (int) $counts['pending'] ?> awaiting moderation</span></div>
</div>

<!-- Status cards double as filter links -->
<div class="stat-grid">
    <?php foreach ($tabs as $key => $tab): ?>
        <a class="stat-card" href="<?= e(admin_url('comments' . ($key === 'all' ? '' : '?status=' . $key))) ?>" style="<?= $statusFilter === $key ? 'outline:2px solid var(--brand-600);outline-offset:2px;' : '' ?>">
            <div class="stat-icon <?= e($tab[2]) ?>"><?= lucide($tab[3]) ?></div>
            <div>
                <div class="stat-value"><?= e(number_format((int) $tab[1])) ?></div>
                <div class="stat-label"><?= e($tab[0]) ?></div>
            </div>
        </a>
    <?php endforeach; ?>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('comments')) ?>">
                <?php if ($statusFilter !== 'all'): ?>
                    <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
                <?php endif; ?>
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search author, email, comment or post…">
            </form>
            <?php if ($statusFilter !== 'all' || $search !== ''): ?>
                <a class="btn btn-ghost" href="<?= e(admin_url('comments')) ?>">Clear filters</a>
            <?php endif; ?>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Author</th><th>Comment</th><th>On Post</th><th>Date</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td>
                            <strong><?= e($r['name']) ?></strong><br>
                            <small class="text-muted"><?= e($r['email']) ?></small>
                        </td>
                        <td>
                            <a href="<?= e(admin_url('comments?action=view&id=' . (int) $r['id'])) ?>"><?= e(excerpt($r['comment'], 16)) ?></a>
                            <?php if (!empty($r['parent_id'])): ?><br><small class="text-muted">↳ reply</small><?php endif; ?>
                        </td>
                        <td><small><?= e(excerpt($r['blog_title'], 8)) ?></small></td>
                        <td><small class="text-muted"><?= e(format_date($r['created_at'], 'd M Y')) ?></small></td>
                        <td><span class="pill <?= e($pillClass($r['status'])) ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('comments?action=view&id=' . (int) $r['id'])) ?>" title="View"><?= lucide('eye') ?></a>
                                <?php
                                if ($r['status'] !== 'approved') $statusForm((int) $r['id'], 'approved', lucide('circle-check'), 'Approve');
                                if ($r['status'] !== 'spam')     $statusForm((int) $r['id'], 'spam',     lucide('ban'), 'Mark as spam');
                                $deleteForm((int) $r['id']);
                                ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= $p['links'] ?>
        <?php else: ?>
            <div class="empty-state"><div class="icon"><?= lucide('message-square') ?></div>No comments<?= $statusFilter !== 'all' ? ' with status “' . e($statusFilter) . '”' : '' ?><?= $search !== '' ? ' matching “' . e($search) . '”' : '' ?>.</div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
