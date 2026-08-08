# EDUSKILL INDIA FOUNDATION — Developer Contract

**Every page and module MUST follow this file.** It documents the spine that is
already built (config, DB, includes, layout, CSS, JS). Do not invent new helpers,
new CSS classes, or new table columns — use what is defined here. If something is
genuinely missing, prefer the closest existing helper.

Stack: **PHP 8.2+ (no 8.3-only syntax), PDO+MySQL, static CSS, vanilla JS.**
No framework, no Composer, no Node. CDN allowed only for SweetAlert2 (forms) and
Chart.js (admin dashboard).

---

## 1. File layout

```
config.php                 constants (DB, URLs, paths)
includes/                  the spine (bootstrap, functions, auth, ...)
admin/                     admin panel pages (require_admin())
api/v1/                    JSON REST endpoints
forms/                     AJAX POST handlers (return JSON)
assets/{css,js,images}
uploads/<folder>/          user uploads (stored path is relative to /uploads)
database/                  schema.sql, sample_data.sql
```

## 2. Page template pattern (public pages, project root)

```php
<?php
require_once __DIR__ . '/includes/bootstrap.php';

// (optional) load data
$programs = db_all("SELECT * FROM programs WHERE status='active' ORDER BY sort_order");

// SEO — always set a title; page_key pulls admin overrides from seo_meta
seo_set(['title' => 'Our Programs', 'description' => '...', 'page_key' => 'programs']);

// Optional coloured hero banner with breadcrumb
$page_hero = ['title' => 'Our Programs', 'subtitle' => '...', 'breadcrumb' => [['label' => 'Programs']]];

include __DIR__ . '/includes/header.php';   // opens <html><head><body>, navbar, <main>
?>

<section class="section">
  <div class="container">
    ...page content, all DB-driven, all output escaped with e()...
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php';   // closes </main>, footer, scripts ?>
```

Rules:
- **First line is always** `require_once __DIR__ . '/includes/bootstrap.php';`
  (admin/api pages use `__DIR__ . '/../includes/bootstrap.php'`).
- Never open `<html>`/`<head>` yourself — `header.php` does it.
- Never echo untrusted data without `e()`.
- Query only with the helpers below (prepared statements only).

## 3. Available functions (already defined — DO NOT redefine)

### Database (`functions.php`)
- `db_all($sql, $params=[])` → array of rows
- `db_row($sql, $params=[])` → one row or null
- `db_value($sql, $params=[])` → single scalar or null
- `db_query($sql, $params=[])` → PDOStatement
- `db_insert($table, $assoc)` → new id
- `db_update($table, $assoc, $whereSql, $whereParams=[])` → affected rows
- `db_delete($table, $whereSql, $whereParams=[])` → affected rows
- `db_count($table, $whereSql='', $params=[])` → int
- `find($table, $id)` / `find_by($table, $col, $val)` → row or null
- `get_all($table, $conds=[], $orderBy='id DESC', $limit=null)` → rows
- Params use named placeholders, e.g. `db_all("... WHERE slug=:s", [':s'=>$slug])`.

### Settings
- `get_setting($key, $default=null)`, `set_setting($key,$val,$group,$type)`, `all_settings()`

### Output / text
- `e($val)` — HTML-escape (use for EVERY echoed value)
- `clean($val)` — strip tags + trim (input)
- `excerpt($text, $words=30)`
- `slugify($text)`, `unique_slug($table, $text, $ignoreId=null)`
- `format_date($d, 'd M Y')`, `format_datetime($d)`, `time_ago($d)`
- `money($amount, '₹', $decimals=0)`, `number_short($n)`
- `star_rating($int)`, `json_column($jsonString)`, `youtube_id($urlOrId)`

### Request / response
- `is_post()`, `post($k,$def)`, `get($k,$def)`, `request($k,$def)`, `is_ajax()`, `client_ip()`
- `json_success($msg, $extra=[], $status=200)`, `json_error($msg, $extra=[], $status=422)`, `json_response($arr,$status)`
- `redirect($path)`, `back()`, `set_flash($type,$msg)`, `old($key,$def)`, `flash_old($input)`
- `validate($data, ['field'=>'required|email|min:3|max:50|numeric'])` → errors array

### URLs / assets (`helper.php`)
- `url($path)` — internal link `/pwf/<path>`
- `abs_url($path)` — absolute (canonical/OG/email)
- `admin_url($path)`, `asset('css/x.css')` (auto `?v=`), `upload_url($storedPath)`, `image_url($path,$type)`
- `active_menu($path)`, `is_current($path)`, `current_path()`
- `breadcrumb([['label'=>'','url'=>'']])`

### Pagination (`pagination.php`)
```php
$p = paginate("SELECT * FROM blogs WHERE status='published' ORDER BY published_at DESC", [], 9);
foreach ($p['items'] as $row) { ... }
echo $p['links'];   // pre-rendered pager HTML
```

### CSRF (`csrf.php`)
- In every form: `<?= csrf_field() ?>`
- In handlers: `require_csrf();` (call before writing to DB)
- JS reads token from `<meta name="csrf-token">` (already in header) or a hidden field.

### Auth (`auth.php`) — admin only
- `require_admin();` at top of every admin page
- `current_user()`, `current_user_id()`, `is_logged_in()`, `user_can($slug)`
- `login($email,$pass)`, `logout()`, `log_activity($action,$module,$desc)`

### Uploads (`upload.php`)
- `upload_image($_FILES['x'], 'blogs')` / `upload_document($_FILES['x'], 'documents')`
  → `['success'=>bool,'path'=>'blogs/xxx.jpg','error'=>'']`. Save `path` in DB.
- `delete_upload($storedPath)`, `human_filesize($bytes)`

### SEO (`seo.php`)
- `seo_set([...])` before header. Keys: title, description, keywords, image, canonical, type, page_key, robots
- `json_ld_article($blogRow)`, `json_ld_breadcrumb($items)` for extra schema

### Mail (`mailer.php`)
- `send_mail($to,$subject,$htmlBody)`, `send_template($slug,$to,$vars=[])`

## 4. CSS class vocabulary (from `assets/css/tailwind.css`)

Layout: `container` (`container-wide|container-narrow`), `section` (`section-sm|section-soft|section-alt|section-brand|section-dark`), `section-head`(+`left`) with `section-title`/`section-subtitle`, `eyebrow`.
Grid/flex: `grid grid-2|grid-3|grid-4|grid-auto|grid-sidebar`, `flex items-center justify-between flex-col flex-wrap`, `gap-1..4`, `mt-1..4`, `mb-0..4`, `text-center`, `text-white`, `text-muted`, `text-gradient`.
Buttons: `btn` + `btn-primary|btn-accent|btn-secondary|btn-outline|btn-white|btn-ghost|btn-danger|btn-success`, sizes `btn-lg|btn-sm|btn-block`.
Cards: `card`>`card-media`(img)+`card-body`>`card-title`/`card-text`; premium `card-3d`, `glass`, `icon-badge`(+`accent`).
Badges: `badge badge-brand|badge-accent|badge-success|badge-danger|badge-muted`, `chip`, `divider`.
Forms: `form-group`, `form-row`, `form-label`(+`.req`), `form-control`, `form-select`, `form-textarea`, `form-error`, `form-hint`, `checkbox`. Invalid: add `is-invalid`.
Alerts: `alert alert-success|alert-error|alert-warning|alert-info`.
Sections/widgets: `page-hero`, `hero`/`hero-slide`/`hero-content`/`hero-title`/`hero-subtitle`/`hero-actions`/`hero-dots`, `counter-grid`/`counter-item`/`counter-value`/`counter-label`, `gallery-grid`/`gallery-item`, `progress`/`progress-bar`, `map-embed`, `countdown`, `empty-state`, `table-wrap`>`table`, `prose` (blog/page body), `list-check`, `pagination`/`page-link`, `breadcrumb`, `stars`.

## 5. JS hooks (from `assets/js/main.js` — attributes, no per-page JS needed)

- Theme toggle button: `data-theme-toggle`
- Reveal on scroll: add class `reveal` (+ `delay-1|2|3`)
- Animated counter: `<span data-counter="1200" data-decimals="0">0</span>`
- Hero slider wrapper: `data-hero-slider` containing `.hero-slide` (first gets `.is-active` auto)
- Carousel: `data-carousel` > `[data-carousel-track]` + `[data-carousel-prev]`/`[data-carousel-next]`
- Lightbox: any trigger with `data-lightbox="/full/image.jpg"`
- Lazy image: `<img data-src="..." alt="">` (use real `src` for above-the-fold/LCP)
- Parallax: `data-parallax="0.3"`
- Countdown: `<div data-countdown="2026-08-01 10:00"><span data-cd-d></span>...d/h/m/s</div>`
- AJAX form: `<form data-ajax-form data-endpoint="<?= url('forms/contact') ?>">` (handled in forms.js, Phase 7)

## 6. Form handler pattern (`forms/<name>.php`, returns JSON)

```php
<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if (!is_post()) json_error('Method not allowed', [], 405);
require_csrf();
$errors = validate($_POST, ['name'=>'required', 'email'=>'required|email', 'message'=>'required|min:5']);
if ($errors) json_error('Please fix the errors below.', ['errors'=>$errors]);
db_insert('contact_messages', [
    'name'=>clean(post('name')), 'email'=>clean(post('email')),
    'message'=>clean(post('message')), 'ip_address'=>client_ip(),
]);
json_success('Thank you! We will get back to you soon.');
```

## 7. Admin page pattern (`admin/<module>.php`)

```php
<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$page_title = 'Programs';
// handle POST (create/update/delete) with require_csrf() ...
include __DIR__ . '/partials/head.php';    // admin <head> + sidebar (built in Phase 9)
// ... list/table + form ...
include __DIR__ . '/partials/foot.php';
```
Admin uses its own head/sidebar/foot partials (Phase 9) — NOT the public header/footer.

## 8. Schema quick reference (table → key columns)

All tables have `id`, `created_at`; most have `updated_at`; content tables have
`status` and a unique `slug`. Common columns below (see `database/schema.sql` for full):

- **settings**(group_name,key_name,value,type) · **users**(role_id,name,email,password,status,avatar)
- **roles**(name,slug) · **permissions**(name,slug,module) · **role_permissions**(role_id,permission_id)
- **menus**(parent_id,title,url,location['header'|'footer'|'both'],sort_order,status)
- **pages**(title,slug,subtitle,content,banner_image,status) · **hero_slides**(title,subtitle,description,image,button_text,button_url,text_align,sort_order,status)
- **programs**(title,slug,short_description,description,icon,image,color,is_featured,sort_order,status['active'|'inactive'])
- **projects**(program_id,title,slug,summary,description,image,location,start_date,end_date,budget,beneficiaries,progress,status)
- **blog_categories**(name,slug) · **blog_tags**(name,slug) · **blog_tag_map**(blog_id,tag_id)
- **blogs**(category_id,author_id,title,slug,excerpt,content,featured_image,views,is_featured,status['draft'|'published'|'archived'],published_at)
- **blog_comments**(blog_id,parent_id,name,email,comment,status['pending'|'approved'|'spam'])
- **gallery_albums**(title,slug,description,cover_image,event_date,status) · **gallery_media**(album_id,title,file_path,type,caption,sort_order)
- **videos**(title,slug,youtube_id,video_url,thumbnail,category,status)
- **achievements**(title,description,icon,value,suffix,prefix,sort_order,status) · **certificates**(title,image,issued_by,issue_date,status)
- **events**(title,slug,description,excerpt,image,location,venue,start_datetime,end_datetime,capacity,registration_required,status) · **event_registrations**(event_id,name,email,phone,guests,message,status)
- **awareness_calendar**(title,description,event_date,end_date,category,color,status)
- **team_members**(name,slug,designation,department,bio,photo,email,phone,socials[JSON],is_leadership,sort_order,status)
- **testimonials**(name,designation,photo,message,rating,status) · **partners**(name,logo,website,status) · **sponsors**(name,logo,website,tier,status)
- **faqs**(question,answer,category,status)
- **volunteers**(name,email,phone,area_of_interest,availability,message,resume,status) · **internships**(name,email,phone,education,duration,area_of_interest,cover_letter,resume,status)
- **campaigns**(title,slug,short_description,description,image,goal_amount,raised_amount,start_date,end_date,status) · **donations**(campaign_id,donor_name,email,phone,amount,currency,payment_method,message,is_anonymous,status)
- **newsletter_subscribers**(name,email,token,status) · **feedback**(name,email,subject,message,rating,status) · **contact_messages**(name,email,phone,subject,message,status)
- **media_library**(file_name,file_path,file_type,mime_type,file_size,alt_text,folder,uploaded_by) · **documents**(title,slug,description,file_path,file_type,file_size,category,downloads,status)
- **seo_meta**(page_key,meta_title,meta_description,og_image,canonical,robots,schema_json) · **redirects**(source_url,target_url,status_code,hits) · **activity_logs**(user_id,action,module,description,ip_address) · **login_attempts**(email,ip_address,success)
- **email_templates**(name,slug,subject,body,variables) · **social_links**(platform,url,icon,sort_order) · **popups**(title,content,image,button_text,button_url,delay_seconds,status) · **announcements**(message,link_text,link_url,bg_color,text_color,status)

## 9. Hard rules
1. Prepared statements only — never concatenate user input into SQL.
2. `e()` on every echoed dynamic value.
3. `require_csrf()` in every POST handler; `csrf_field()` in every form.
4. `require_admin()` at the very top of every `admin/` page.
5. Store only the relative upload path (`folder/file.ext`) in the DB.
6. No PHP 8.3-only syntax. No TODOs, no placeholders, no dead buttons.
7. Match the existing code style: 4-space indent, PHPDoc on functions, comments.
