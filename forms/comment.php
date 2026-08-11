<?php
/**
 * Blog comment handler — inserts a comment pending moderation.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

if (!is_post()) json_error('Method not allowed.', [], 405);
require_csrf();

$errors = validate($_POST, [
    'name'    => 'required|max:128',
    'email'   => 'required|email',
    'comment' => 'required|min:3|max:2000',
]);
$blogId = (int) post('blog_id', 0);
$blog   = $blogId ? find('blogs', $blogId) : null;
if (!$blog) {
    json_error('The post you are commenting on could not be found.', [], 404);
}
if ($errors) {
    json_error('Please correct the highlighted fields.', ['errors' => $errors]);
}

$parentId = (int) post('parent_id', 0) ?: null;
if ($parentId) {
    $parent = find('blog_comments', $parentId);
    if (!$parent || (int) $parent['blog_id'] !== $blogId) {
        $parentId = null;
    }
}

$name    = clean(post('name'));
$email   = clean(post('email'));
$website = clean(post('website', ''));
$comment = clean(post('comment'));

// Heuristic spam filter — auto-quarantine obvious spam (no tip-off to the user).
$status = blog_comment_is_spam($name, $comment, $website) ? 'spam' : 'pending';

$cid = db_insert('blog_comments', [
    'blog_id'    => $blogId,
    'parent_id'  => $parentId,
    'name'       => $name,
    'email'      => $email,
    'website'    => $website,
    'comment'    => $comment,
    'status'     => $status,
    'ip_address' => client_ip(),
]);

// Notify an admin about a genuine comment awaiting moderation.
if ($status === 'pending') {
    $notify = (string) get_setting('site_email', '');
    if ($notify !== '' && filter_var($notify, FILTER_VALIDATE_EMAIL)) {
        $body = '<p>A new comment is awaiting moderation on <strong>' . e($blog['title']) . '</strong>.</p>'
              . '<p><strong>' . e($name) . '</strong> &lt;' . e($email) . '&gt; wrote:</p>'
              . '<blockquote style="border-left:3px solid #0B4E3D;padding-left:12px;color:#475569;">' . nl2br(e($comment)) . '</blockquote>'
              . '<p><a href="' . e(abs_url('admin/comments?action=view&id=' . $cid)) . '">Review it in the admin panel &rarr;</a></p>';
        try { send_mail($notify, 'New blog comment awaiting moderation', $body); } catch (Throwable $e) { /* never block the reader */ }
    }
}

json_success('Thank you! Your comment has been submitted and will appear once approved.');
