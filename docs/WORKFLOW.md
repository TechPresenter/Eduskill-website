# Workflow Guide — Eduskill India Foundation

How the site is developed, styled, and managed day-to-day.

---

## 1. Content workflow (for the non-technical owner)

Everything is managed from **`/admin`** — no code, no developer needed.

```
Log in → Dashboard
   ├─ Pages        → build/edit pages by dragging sections (hero, features, counters, …)
   │                  Save Draft → preview → Publish (creates a revision)
   ├─ Campaigns    → add a cause, goal, raised amount, image → shows on the site with a progress bar
   ├─ Programmes / Events / Team / Testimonials / FAQs / Blog → add/edit/delete
   ├─ Media        → upload images used across the site
   ├─ Forms inbox  → read contact / volunteer / partner / application enquiries
   ├─ Newsletter   → view subscribers
   ├─ Users & Roles→ invite staff, control what they can access
   └─ Settings     → brand colour, site name/tagline, contact details, social links, SEO
```

**Golden rules for the owner**
- Unbuilt admin items are labelled **“Soon”** — that's honest, not broken.
- Changing the **brand colour** in Settings re-themes the whole site instantly (no rebuild).
- Publishing uses an **optimistic lock** — if two people edit the same page, the stale save is
  rejected so no work is silently overwritten.

## 2. Developer workflow

### Adding a public page
1. Create `newpage.php` at the project root.
2. Start it with the standard preamble:
   ```php
   <?php
   require __DIR__ . '/includes/config.php';
   $page_title = 'New Page';
   require __DIR__ . '/includes/header.php';
   // …page body (use design-system classes: .section, .container-site, .content-card …)
   require __DIR__ . '/includes/footer.php';
   ```
3. For a CMS-editable page, call `cms_page('slug', 'Title')` instead of hand-writing content.

### Adding an API endpoint
Create `api/thing.php`:
```php
<?php require __DIR__ . '/../includes/config.php';
if (request_method() !== 'POST') json_error('Method not allowed.', 405);
$in = api_body();
if (!verify_csrf($in['_csrf'] ?? null)) json_error('Session expired.', 400);
// …validate, persist…
json_ok([/* data */], 'Done.');
```

### Conventions (see [TRD.md](TRD.md) §9)
- `defined('ESK') || exit('No direct access.');` atop every include.
- Escape output with `e()`; use **prepared statements** for all SQL.
- Money in **paise**; format with `inr()`.

## 3. CSS / design workflow

The compiled CSS is **committed** — the owner never builds. Developers changing the design:

```bash
cd src-build
npm install                        # first time only
# edit src/tokens.css | components.css | app.css | admin.css
npx tailwindcss -i ./src/app.css   -o ../assets/css/app.css   --minify
npx tailwindcss -i ./src/admin.css -o ../assets/css/admin.css --minify
```

> ⚠️ **Critical:** `tailwind.config.js` `content` globs **must** scan `../*.php`, `../includes/**`,
> `../admin/**`, `../api/**`. If the admin markup isn't scanned, its utility classes get purged and
> the admin layout breaks (this exact regression has happened — the globs are the fix).

The premium theme (`assets/css/premium.css`, `premium-admin.css`) is **hand-written plain CSS**
layered *after* the compiled files — edit it directly, no build step.

## 4. Database workflow

- Schema changes as idempotent files in `database/migrations/`, applied with `tools/migrate.php`.
- Keep everything **InnoDB / utf8mb4_unicode_ci**; never `_0900_` collations (shared-host safe).
- Regenerate the consolidated dumps (`schema.sql`, `seed.sql`, `eduskill_full.sql`) after changes.

## 5. Git workflow

- `main` is the source of truth. Branch for features; open PRs for review.
- **Never commit** `config/config.php` (secrets), `src-build/node_modules/`, `storage/logs/*`, or
  user uploads — all are git-ignored.
- Commit the **compiled CSS** (it ships to production).

## 6. Release / deploy

1. Rebuild CSS if the design changed; regenerate DB dumps if the schema changed.
2. Verify locally (home, a CMS page, admin login, a form submission).
3. Deploy by upload (see [SETUP-GUIDE.md](SETUP-GUIDE.md) §B).
