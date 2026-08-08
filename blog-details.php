<?php
/**
 * =============================================================================
 *  Blog Details — single published article.
 *
 *  Requires ?slug=<blog-slug>. Loads the published post (joined with its
 *  category and author), increments the view counter, and renders the featured
 *  image, meta, body (.prose), tags, social share, related posts and an
 *  approved threaded comment thread with a moderated comment form.
 *
 *  Everything is DB-driven with graceful fallbacks so the page never looks
 *  broken before content has been seeded.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

$slug = clean(get('slug', ''));

/* ---- Load the published article (with category + author) -------------- */
$blog = $slug !== '' ? db_row(
    "SELECT b.*,
            c.name AS category_name, c.slug AS category_slug,
            u.name AS author_name, u.avatar AS author_avatar, u.bio AS author_bio
     FROM blogs b
     LEFT JOIN blog_categories c ON c.id = b.category_id
     LEFT JOIN users u           ON u.id = b.author_id
     WHERE b.slug = :s
       AND b.status = 'published'
       AND b.deleted_at IS NULL
       AND (b.published_at IS NULL OR b.published_at <= NOW())
     LIMIT 1",
    [':s' => $slug]
) : null;

/* =============================================================================
 |  NOT FOUND — 404 inside the normal layout
 |============================================================================*/
if (!$blog) {
    http_response_code(404);

    seo_set([
        'title'       => 'Article Not Found',
        'description' => 'The article you are looking for is no longer available. Explore more stories, news and updates from EDUSKILL INDIA FOUNDATION.',
        'page_key'    => 'blog-details',
        'robots'      => 'noindex,follow',
    ]);
    $page_hero = [
        'title'      => 'Article Not Found',
        'subtitle'   => 'This story may have been moved or unpublished. Browse our latest posts instead.',
        'breadcrumb' => [
            ['label' => 'Blog', 'url' => url('blogs')],
            ['label' => 'Not Found'],
        ],
    ];

    include __DIR__ . '/includes/header.php';
    ?>
    <section class="section">
        <div class="container">
            <div class="empty-state">
                <div class="icon-badge"><?= lucide('search') ?></div>
                <h3>We couldn't find that article</h3>
                <p class="text-muted">The link may be outdated, or the post has been unpublished. Explore our blog for the latest stories, news and updates from the field.</p>
                <div class="flex flex-wrap justify-center gap-2 mt-3">
                    <a class="btn btn-primary" href="<?= e(url('blogs')) ?>">Read Our Blog</a>
                    <a class="btn btn-outline" href="<?= e(url('/')) ?>">Back to Home</a>
                </div>
            </div>
        </div>
    </section>
    <?php
    include __DIR__ . '/includes/footer.php';
    return;
}

/* ---- Count the view -------------------------------------------------- */
db_update('blogs', ['views' => (int) $blog['views'] + 1], 'id = :id', [':id' => (int) $blog['id']]);
$views = (int) $blog['views'] + 1;

/* ---- Derived display values ------------------------------------------ */
$readingTime = (int) ($blog['reading_time'] ?? 0);
if ($readingTime <= 0) {
    $wordCount   = str_word_count(strip_tags((string) $blog['content']));
    $readingTime = max(1, (int) ceil($wordCount / 200));
}
$publishedRaw = $blog['published_at'] ?: $blog['created_at'];
$summary      = $blog['excerpt'] ?: excerpt($blog['content'] ?? '', 30);

/* Absolute, encoded URLs for social sharing. */
$shareUrl      = abs_url('blog-details?slug=' . $blog['slug']);
$shareUrlEnc   = rawurlencode($shareUrl);
$shareTitleEnc = rawurlencode($blog['title']);

/* ---- Tags ------------------------------------------------------------ */
$tags = db_all(
    "SELECT t.name, t.slug
     FROM blog_tag_map m
     JOIN blog_tags t ON t.id = m.tag_id
     WHERE m.blog_id = :id
     ORDER BY t.name ASC",
    [':id' => $blog['id']]
);

/* ---- Related posts (same category → fallback to latest) -------------- */
/* Score each candidate by shared category (+1) plus number of shared tags. */
$related = db_all(
    "SELECT b.slug, b.title, b.excerpt, b.featured_image, b.published_at, b.created_at,
            ((b.category_id IS NOT NULL AND b.category_id = :cid)
             + (SELECT COUNT(*) FROM blog_tag_map m1
                  JOIN blog_tag_map m2 ON m2.tag_id = m1.tag_id AND m2.blog_id = :id2
                 WHERE m1.blog_id = b.id)) AS score
     FROM blogs b
     WHERE b.status = 'published'
       AND b.deleted_at IS NULL
       AND (b.published_at IS NULL OR b.published_at <= NOW())
       AND b.id <> :id
     ORDER BY score DESC, b.published_at DESC, b.id DESC
     LIMIT 3",
    [':cid' => (int) ($blog['category_id'] ?? 0), ':id2' => (int) $blog['id'], ':id' => (int) $blog['id']]
);

/* ---- Previous / next article (by publish date) ---------------------- */
$navDate  = $blog['published_at'] ?: $blog['created_at'];
$prevPost = db_row(
    "SELECT slug, title FROM blogs
     WHERE status='published' AND deleted_at IS NULL AND id <> :id
       AND (published_at IS NULL OR published_at <= NOW())
       AND COALESCE(published_at, created_at) < :d
     ORDER BY COALESCE(published_at, created_at) DESC LIMIT 1",
    [':id' => $blog['id'], ':d' => $navDate]
);
$nextPost = db_row(
    "SELECT slug, title FROM blogs
     WHERE status='published' AND deleted_at IS NULL AND id <> :id
       AND (published_at IS NULL OR published_at <= NOW())
       AND COALESCE(published_at, created_at) > :d
     ORDER BY COALESCE(published_at, created_at) ASC LIMIT 1",
    [':id' => $blog['id'], ':d' => $navDate]
);

/* ---- Approved comments, grouped by parent for threading -------------- */
$comments = db_all(
    "SELECT id, parent_id, name, website, comment, created_at
     FROM blog_comments
     WHERE blog_id = :id AND status = 'approved'
     ORDER BY created_at ASC, id ASC",
    [':id' => $blog['id']]
);
$commentCount = count($comments);
$byParent = [];
foreach ($comments as $cm) {
    $byParent[(int) ($cm['parent_id'] ?? 0)][] = $cm;
}

/**
 * Recursive comment renderer (approved thread, indented by depth).
 */
$renderComments = function (int $parentKey, int $depth = 0) use (&$renderComments, $byParent): void {
    if (empty($byParent[$parentKey])) {
        return;
    }
    foreach ($byParent[$parentKey] as $cm):
        $indent = $depth > 0 ? 'margin-left:clamp(1rem,5vw,3rem);' : '';
        ?>
        <div class="card" style="margin-bottom:1rem;<?= $indent ?>">
            <div class="card-body">
                <div class="flex items-center gap-2 mb-2">
                    <span class="icon-badge" style="width:44px;height:44px;font-size:1rem;flex:0 0 auto;">
                        <?= e(strtoupper(mb_substr((string) $cm['name'], 0, 1) ?: '?')) ?>
                    </span>
                    <div style="min-width:0;">
                        <strong>
                            <?php $cmSite = safe_href($cm['website'] ?? ''); ?>
                            <?php if ($cmSite !== ''): ?>
                                <a href="<?= e($cmSite) ?>" target="_blank" rel="nofollow noopener" style="color:inherit;"><?= e($cm['name']) ?></a>
                            <?php else: ?>
                                <?= e($cm['name']) ?>
                            <?php endif; ?>
                        </strong><br>
                        <small class="text-muted"><?= e(time_ago($cm['created_at'])) ?></small>
                    </div>
                    <button type="button" class="btn btn-ghost btn-sm" style="margin-left:auto;"
                            onclick="var f=document.getElementById('comment-form');if(f){document.getElementById('parent_id').value='<?= (int) $cm['id'] ?>';var r=document.getElementById('reply-indicator');if(r){r.hidden=false;}f.scrollIntoView({behavior:'smooth'});}"><?= lucide('reply') ?> Reply</button>
                </div>
                <p class="mb-0" style="color:var(--text-soft);"><?= nl2br(e($cm['comment'])) ?></p>
            </div>
        </div>
        <?php
        $renderComments((int) $cm['id'], $depth + 1);
    endforeach;
};

/* ---- SEO (article) --------------------------------------------------- */
$seo = [
    'title'       => !empty($blog['meta_title']) ? $blog['meta_title'] : $blog['title'],
    'description' => !empty($blog['meta_description']) ? $blog['meta_description'] : $summary,
    'image'       => !empty($blog['og_image']) ? upload_url($blog['og_image'])
                     : (!empty($blog['featured_image']) ? upload_url($blog['featured_image']) : null),
    'type'        => 'article',
    'page_key'    => 'blog:' . $blog['slug'],
];
if (!empty($blog['canonical_url'])) {
    $seo['canonical'] = $blog['canonical_url'];
}
seo_set($seo);

$page_hero = [
    'title'      => $blog['title'],
    'subtitle'   => $summary,
    'breadcrumb' => [
        ['label' => 'Blog', 'url' => url('blogs')],
        ['label' => $blog['title']],
    ],
];

include __DIR__ . '/includes/header.php';

/* Article JSON-LD (schema.org) for rich results. */
echo json_ld_article($blog, $blog['author_name'] ?? null);
?>

<!-- ============================== ARTICLE ============================== -->
<section class="section">
    <div class="container grid grid-sidebar gap-4 items-start">

        <!-- Main column -->
        <article class="reveal" data-article>

            <figure class="card-media" style="border-radius:var(--radius,16px);overflow:hidden;margin:0 0 1.5rem;">
                <img src="<?= e(image_url($blog['featured_image'], 'blog')) ?>" alt="<?= e($blog['title']) ?>" width="900" height="520" style="width:100%;height:auto;display:block;object-fit:cover;">
            </figure>

            <div class="flex items-center gap-2 flex-wrap mb-2">
                <?php if (!empty($blog['category_name'])): ?>
                    <a class="badge badge-brand" href="<?= e(url('blogs?category=' . $blog['category_slug'])) ?>" style="color:#fff;"><?= e($blog['category_name']) ?></a>
                <?php endif; ?>
                <?php if (!empty($blog['is_breaking'])): ?><span class="badge" style="background:#dc2626;color:#fff;"><?= lucide('zap') ?> Breaking</span><?php endif; ?>
                <?php if ($blog['is_featured']): ?><span class="badge badge-accent"><?= lucide('star') ?> Featured</span><?php endif; ?>
            </div>

            <p class="section-title" style="margin-top:.25rem;" role="heading" aria-level="2"><?= e($blog['title']) ?></p>

            <!-- Meta row -->
            <div class="flex items-center gap-3 flex-wrap mt-2 mb-3" style="color:var(--text-muted);font-size:.92rem;">
                <span class="flex items-center gap-1">
                    <img src="<?= e(image_url($blog['author_avatar'] ?? null, 'avatar')) ?>" alt="<?= e($blog['author_name'] ?: SITE_NAME) ?>" width="30" height="30" style="width:30px;height:30px;border-radius:50%;object-fit:cover;">
                    <strong style="color:var(--text);"><?= e($blog['author_name'] ?: SITE_NAME) ?></strong>
                </span>
                <?php if ($publishedRaw): ?><span><?= lucide('calendar-days') ?> <?= e(format_date($publishedRaw, 'd M Y')) ?></span><?php endif; ?>
                <span><?= lucide('clock') ?> <?= (int) $readingTime ?> min read</span>
                <span><?= lucide('eye') ?> <?= e(number_short($views)) ?> views</span>
                <?php if ($commentCount > 0): ?><span><?= lucide('message-square') ?> <?= (int) $commentCount ?> comment<?= $commentCount === 1 ? '' : 's' ?></span><?php endif; ?>
            </div>

            <div class="divider"></div>

            <!-- Body -->
            <div class="prose">
                <?php if (!empty(trim((string) $blog['content']))): ?>
                    <?php if (!empty($blog['excerpt'])): ?><p style="font-size:1.15rem;color:var(--text);"><strong><?= e($blog['excerpt']) ?></strong></p><?php endif; ?>
                    <?= blog_render_content($blog['content']) ?>
                <?php else: ?>
                    <p><?= e($summary ?: 'This story is being written. Please check back soon for the full article.') ?></p>
                <?php endif; ?>
            </div>

            <!-- Tags -->
            <?php if ($tags): ?>
            <div class="mt-4 flex items-center gap-2 flex-wrap">
                <strong style="margin-right:.25rem;">Tags:</strong>
                <?php foreach ($tags as $t): ?>
                    <a class="chip" href="<?= e(url('blogs?tag=' . $t['slug'])) ?>">#<?= e($t['name']) ?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="divider"></div>

            <!-- Share (inline) -->
            <div class="flex items-center gap-2 flex-wrap">
                <strong style="margin-right:.25rem;">Share:</strong>
                <a class="btn btn-secondary btn-sm" href="https://www.facebook.com/sharer/sharer.php?u=<?= e($shareUrlEnc) ?>" target="_blank" rel="noopener" aria-label="Share on Facebook">Facebook</a>
                <a class="btn btn-secondary btn-sm" href="https://twitter.com/intent/tweet?url=<?= e($shareUrlEnc) ?>&amp;text=<?= e($shareTitleEnc) ?>" target="_blank" rel="noopener" aria-label="Share on X">X</a>
                <a class="btn btn-secondary btn-sm" href="https://www.linkedin.com/sharing/share-offsite/?url=<?= e($shareUrlEnc) ?>" target="_blank" rel="noopener" aria-label="Share on LinkedIn">LinkedIn</a>
                <a class="btn btn-secondary btn-sm" href="https://wa.me/?text=<?= e($shareTitleEnc) ?>%20<?= e($shareUrlEnc) ?>" target="_blank" rel="noopener" aria-label="Share on WhatsApp">WhatsApp</a>
                <a class="btn btn-secondary btn-sm" href="mailto:?subject=<?= e($shareTitleEnc) ?>&amp;body=<?= e($shareUrlEnc) ?>" aria-label="Share by email">Email</a>
            </div>

            <div class="mt-4">
                <a class="btn btn-outline" href="<?= e(url('blogs')) ?>"><?= lucide('arrow-left') ?> Back to All Posts</a>
            </div>
        </article>

        <!-- Sidebar -->
        <aside class="reveal delay-1">
          <div class="blog-aside-sticky">
            <!-- Table of contents (auto-built from headings; hidden when none) -->
            <div class="card toc-card" data-toc hidden>
                <div class="card-body">
                    <h4 class="mb-2"><?= lucide('list') ?> On this page</h4>
                    <nav class="toc-nav" data-toc-nav aria-label="Table of contents"></nav>
                </div>
            </div>
            <div class="card-3d">
                <div class="flex items-center gap-2">
                    <img src="<?= e(image_url($blog['author_avatar'] ?? null, 'avatar')) ?>" alt="<?= e($blog['author_name'] ?: SITE_NAME) ?>" width="56" height="56" style="width:56px;height:56px;border-radius:50%;object-fit:cover;flex:0 0 auto;">
                    <div>
                        <span class="eyebrow" style="margin:0;">Written by</span>
                        <strong style="display:block;font-size:1.05rem;"><?= e($blog['author_name'] ?: SITE_NAME) ?></strong>
                    </div>
                </div>
                <p class="text-muted mt-2 mb-0" style="font-size:.92rem;">
                    <?= e($blog['author_bio'] ?: 'Part of the ' . SITE_NAME . ' team, sharing stories of impact, hope and change from communities across Bihar.') ?>
                </p>

                <div class="divider"></div>

                <h4 class="mb-2">Share this story</h4>
                <div class="share-rail-icons flex gap-2 flex-wrap">
                    <a class="share-btn fb" href="https://www.facebook.com/sharer/sharer.php?u=<?= e($shareUrlEnc) ?>" target="_blank" rel="noopener" aria-label="Share on Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a class="share-btn tw" href="https://twitter.com/intent/tweet?url=<?= e($shareUrlEnc) ?>&amp;text=<?= e($shareTitleEnc) ?>" target="_blank" rel="noopener" aria-label="Share on X"><i class="fab fa-x-twitter"></i></a>
                    <a class="share-btn li" href="https://www.linkedin.com/sharing/share-offsite/?url=<?= e($shareUrlEnc) ?>" target="_blank" rel="noopener" aria-label="Share on LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                    <a class="share-btn wa" href="https://wa.me/?text=<?= e($shareTitleEnc) ?>%20<?= e($shareUrlEnc) ?>" target="_blank" rel="noopener" aria-label="Share on WhatsApp"><i class="fab fa-whatsapp"></i></a>
                    <a class="share-btn em" href="mailto:?subject=<?= e($shareTitleEnc) ?>&amp;body=<?= e($shareUrlEnc) ?>" aria-label="Share by email"><?= lucide('mail') ?></a>
                </div>

                <?php if ($tags): ?>
                <div class="divider"></div>
                <h4 class="mb-2">Tags</h4>
                <div class="flex gap-2 flex-wrap">
                    <?php foreach ($tags as $t): ?>
                        <a class="chip" href="<?= e(url('blogs?tag=' . $t['slug'])) ?>">#<?= e($t['name']) ?></a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="divider"></div>
                <a class="btn btn-primary btn-block" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Support Our Work</a>
            </div>
          </div>
        </aside>
    </div>
</section>

<?php if ($prevPost || $nextPost): ?>
<!-- ============================== PREV / NEXT ============================== -->
<section class="section-sm" style="padding-top:0;">
    <div class="container container-narrow">
        <nav class="post-nav" aria-label="More articles">
            <?php if ($prevPost): ?>
                <a class="post-nav-item prev" href="<?= e(url('blog-details?slug=' . $prevPost['slug'])) ?>">
                    <span class="pn-dir"><?= lucide('arrow-left') ?> Previous</span>
                    <span class="pn-title"><?= e($prevPost['title']) ?></span>
                </a>
            <?php else: ?><span></span><?php endif; ?>
            <?php if ($nextPost): ?>
                <a class="post-nav-item next" href="<?= e(url('blog-details?slug=' . $nextPost['slug'])) ?>">
                    <span class="pn-dir">Next <?= lucide('arrow-right') ?></span>
                    <span class="pn-title"><?= e($nextPost['title']) ?></span>
                </a>
            <?php endif; ?>
        </nav>
    </div>
</section>
<?php endif; ?>

<?php if ($related): ?>
<!-- ============================== RELATED POSTS ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Keep Reading</span>
            <h2 class="section-title">Related Stories</h2>
            <p class="section-subtitle">More stories, news and updates you may find inspiring.</p>
        </div>
        <div class="grid grid-3">
            <?php foreach ($related as $i => $r): ?>
                <article class="card reveal <?= 'delay-' . (($i % 3) + 1) ?>">
                    <a href="<?= e(url('blog-details?slug=' . $r['slug'])) ?>" class="card-media">
                        <img src="<?= e(image_url($r['featured_image'], 'blog')) ?>" alt="<?= e($r['title']) ?>" loading="lazy" width="600" height="375">
                    </a>
                    <div class="card-body">
                        <h3 class="card-title"><a href="<?= e(url('blog-details?slug=' . $r['slug'])) ?>" style="color:inherit;"><?= e($r['title']) ?></a></h3>
                        <p class="card-text"><?= e(excerpt($r['excerpt'] ?: $r['title'], 18)) ?></p>
                        <small class="text-muted"><?= e(format_date($r['published_at'] ?: $r['created_at'], 'd M Y')) ?></small>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================== COMMENTS ============================== -->
<section class="section">
    <div class="container container-narrow">
        <div class="section-head left">
            <span class="eyebrow">Join the Conversation</span>
            <h2 class="section-title mb-0"><?= (int) $commentCount ?> Comment<?= $commentCount === 1 ? '' : 's' ?></h2>
        </div>

        <?php if ($comments): ?>
            <div class="mt-3">
                <?php $renderComments(0); ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon-badge"><?= lucide('message-square') ?></div>
                <h3>No comments yet</h3>
                <p class="text-muted">Be the first to share your thoughts on this story.</p>
            </div>
        <?php endif; ?>

        <!-- Comment form -->
        <div class="card mt-4" id="comment-form">
            <div class="card-body">
                <h3 class="mb-1">Leave a Comment</h3>
                <p class="text-muted" style="font-size:.9rem;">Your email address will not be published. Comments appear after moderation. Required fields are marked <span class="req">*</span></p>

                <div id="reply-indicator" class="alert alert-info flex justify-between items-center gap-2" hidden>
                    <span>You are replying to a comment.</span>
                    <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('parent_id').value='';this.closest('#reply-indicator').hidden=true;">Cancel reply</button>
                </div>

                <form data-ajax-form data-endpoint="<?= e(url('forms/comment')) ?>" class="mt-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="blog_id" value="<?= (int) $blog['id'] ?>">
                    <input type="hidden" name="parent_id" id="parent_id" value="">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="c-name">Name <span class="req">*</span></label>
                            <input class="form-control" id="c-name" name="name" maxlength="128" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="c-email">Email <span class="req">*</span></label>
                            <input class="form-control" id="c-email" type="email" name="email" maxlength="191" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="c-website">Website</label>
                        <input class="form-control" id="c-website" type="url" name="website" maxlength="191" placeholder="https://">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="c-comment">Comment <span class="req">*</span></label>
                        <textarea class="form-textarea" id="c-comment" name="comment" rows="5" minlength="3" maxlength="2000" required></textarea>
                    </div>
                    <button class="btn btn-primary" type="submit">Post Comment</button>
                </form>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
