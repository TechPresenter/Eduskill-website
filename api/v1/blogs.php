<?php
/**
 * GET /api/v1/blogs — list published blogs (page, per_page, q, category)
 * GET /api/v1/blogs?slug=... — single post with tags.
 */
require_once __DIR__ . '/bootstrap.php';
api_require_method('GET');

// Single post.
if (!empty($_GET['slug'])) {
    $blog = db_row(
        "SELECT b.*, c.name AS category_name, c.slug AS category_slug, u.name AS author_name
         FROM blogs b
         LEFT JOIN blog_categories c ON c.id = b.category_id
         LEFT JOIN users u ON u.id = b.author_id
         WHERE b.slug = :slug AND b.status = 'published' AND b.deleted_at IS NULL
           AND (b.published_at IS NULL OR b.published_at <= NOW()) LIMIT 1",
        [':slug' => clean((string) $_GET['slug'])]
    );
    if (!$blog) {
        api_fail('Blog post not found.', 404);
    }
    $blog['tags'] = db_all(
        "SELECT t.name, t.slug FROM blog_tags t
         JOIN blog_tag_map m ON m.tag_id = t.id WHERE m.blog_id = :id",
        [':id' => $blog['id']]
    );
    $blog['featured_image_url'] = $blog['featured_image'] ? upload_url($blog['featured_image']) : null;
    api_ok($blog);
}

// List.
$where  = ["b.status = 'published'", 'b.deleted_at IS NULL', '(b.published_at IS NULL OR b.published_at <= NOW())'];
$params = [];
if (!empty($_GET['q'])) {
    $where[] = "CONCAT_WS(' ', b.title, b.excerpt) LIKE :q";
    $params[':q'] = '%' . clean((string) $_GET['q']) . '%';
}
if (!empty($_GET['category'])) {
    $where[] = 'c.slug = :cat';
    $params[':cat'] = clean((string) $_GET['category']);
}

$sql = "SELECT b.id, b.title, b.slug, b.excerpt, b.featured_image, b.views, b.published_at,
               c.name AS category_name, c.slug AS category_slug
        FROM blogs b
        LEFT JOIN blog_categories c ON c.id = b.category_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY b.published_at DESC, b.id DESC";

api_paginated($sql, $params, 9);
