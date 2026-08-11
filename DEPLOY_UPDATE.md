# Deploying the update to eduskillindia.org

Step-by-step guide to update the **live** site from `ASSET_VERSION 5.3.3` to `5.7.0`.

> Your app is **already live** at https://eduskillindia.org. This is an **update**,
> not a first install. The live database holds real members, donations and
> contact messages — every step below is written to protect them.

---

## ⚠️ Read this first

There is now **one** SQL file, `database/eduskill.sql`, and it has **two
sections**. Which one you run matters enormously.

**Never run Section 1 on production.** It starts every table with
`DROP TABLE IF EXISTS` — it builds a *fresh* database and would delete all of
your live data.

**Run Section 2 only.** Scroll to the `SECTION 2 — LIVE UPDATE` banner, select
from there to the end of the file, and paste that into phpMyAdmin's SQL tab.
Section 2 was verified to contain:

| Statement | Count |
|---|---|
| `DROP TABLE` | **0** |
| `TRUNCATE` | **0** |
| `DELETE FROM` | **0** |
| `ALTER TABLE` | 2 (both `IF NOT EXISTS`) |
| `INSERT … ON DUPLICATE KEY UPDATE` | 79 |
| `UPDATE` | 16 |

Verified by dry-run against a throwaway copy of the live-style schema, with real
member and contact rows planted first: import clean, **rows preserved**, member
code migrated `PWF-00042` → `EIF-00042`, `members.role` added and backfilled,
69 theme tokens applied — and re-running it a second time changed nothing.

---

## What you need

Both are in your project folder:

| File | Size | What it is |
|---|---|---|
| `eduskill-hostinger-UPDATE.zip` | 5.6 MB | app files |
| `database/eduskill.sql` | 438 KB | the single SQL file — **run Section 2** |

---

## Step 1 — Take a backup (do not skip)

1. Log in to **hPanel**.
2. **Files → Backups**.
3. Create a **new backup** of both files and database. Wait for it to finish.

This is a live site with real data. If anything goes wrong in the next steps,
this backup is how you get back.

---

## Step 2 — Note your database name

1. hPanel → **Databases → MySQL Databases**.
2. Write down the database name (looks like `u623595918_eduskill`).

You will need it in Step 5.

---

## Step 3 — Upload the files

1. hPanel → **Files → File Manager**.
2. Open **`public_html`**.
3. Click **Upload** and choose **`eduskill-hostinger-UPDATE.zip`**.
4. When the upload finishes, right-click the ZIP → **Extract** → into the
   current folder (`public_html`).
5. Confirm **Overwrite** when asked — you are replacing old app files with new ones.
6. Delete the ZIP file afterwards.

### What this does and does not touch

| | |
|---|---|
| ✅ Replaces | app pages, `includes/`, `assets/`, `.htaccess`, `robots.txt` |
| ✅ Adds | `assets/css/design-system.css`, `portals.php`, `includes/megamenu.php`, `includes/portal-sidebar.php`, `includes/auth_router.php`, `account-profile.php` |
| 🚫 Does **not** include `config.php` | your production DB password and `APP_KEY` stay exactly as they are |
| 🚫 Does **not** delete visitor uploads | extracting only adds/overwrites by name |

`config.php` is deliberately excluded. Overwriting it would take the site down,
and a changed `APP_KEY` would make every already-encrypted value permanently
unreadable.

---

## Step 4 — Check the site still loads

Before touching the database, open https://eduskillindia.org in a **private/incognito**
window.

- The page should load (it may still look like the old design — that is expected,
  the theme lives in the database and arrives in Step 5).
- If you get a **500 error**, stop and restore the backup from Step 1.

---

## Step 5 — Run the database update

1. hPanel → **Databases → phpMyAdmin**.
2. In the left sidebar, **click into your database** (the name from Step 2).
   This matters — the script has no `USE` statement, so it applies to whichever
   database you have selected.
3. Open `database/eduskill.sql` in a text editor and scroll to the banner that
   reads `SECTION 2 — LIVE UPDATE`. Select from that banner to the **end of the
   file** and copy it.
4. In phpMyAdmin click the **SQL** tab, paste, and click **Go**.

> Use the SQL tab, not Import. Importing the whole file would run Section 1,
> which drops every table.

You should see a success message. This applies:

- the forest-green theme (69 tokens: colours, Manrope/Inter, type + spacing scale)
- the Kolkata registered address
- `EIF` member-code and receipt prefixes (and converts existing `PWF-` codes)
- `api_cors_origin` locked to your domain
- the `members.role` column (migration v29/v30)
- emoji → lucide icon slugs
- **`rbac_enforce` → `1`** (see below)

### About the RBAC change

Role-based access control was previously **off** (`rbac_enforce = '0'`). With it
off, every active row in `users` could reach every admin screen — assigning
someone the "Editor" role changed the menu but not what they could actually
open. This update turns it on.

Verified over HTTP before shipping:

| Role | Can open | Gets 403 on |
|---|---|---|
| Editor | `/admin/blogs`, `/admin/pages`, `/admin/settings` | `security`, `users`, `roles` |
| Teacher | `/admin/courses`, `/admin/exams` | `security`, `donations` |
| School | `/admin/school-students` | `users` |
| Staff | `/admin/campaigns` | `security`, `roles` |
| Super Admin (#1) | everything | — |

Your own account (#1) is a super admin and bypasses the gate entirely, so this
**cannot lock you out**. If it causes trouble, turn it off at
**Admin → Roles → Enforcement**.

---

## Step 6 — Purge the cache

**This step is required, not optional.**

1. hPanel → **Advanced → Cache Manager** (or LiteSpeed Cache).
2. **Purge all**.

Your homepage currently returns `X-LiteSpeed-Cache: hit`, and the cached copy
still contains **WordPress markup** (Astra theme, `wp-json`) left over from a
previous install. Some visitors are being served that stale page right now.
Purging fixes it whether or not you deploy.

---

## Step 7 — Verify the update landed

In a private window, check each of these:

| Check | Expected |
|---|---|
| https://eduskillindia.org | loads, **forest-green** header (not navy) |
| View source → search `design-system.css` | present, `?v=5.7.0` |
| https://eduskillindia.org/portals | loads (was 404 before) |
| https://eduskillindia.org/login/member | shows a **"Member Portal"** badge |
| https://eduskillindia.org/contact | address reads **Kolkata**, not Patna/Vaishali |
| Header menu | Home · About Us · Programs · Media · Support Us · Portals · Contact |
| "Sign in" in the header | opens the portal drawer with 8 role logins |
| Admin → Roles | "Enforcement" reads **on** |
| `?lang[]=1` on any page | loads normally (does not 500) |

---

## Step 8 — Change the admin password (do this immediately)

1. Go to https://eduskillindia.org/admin/login
2. Sign in: `admin@eduskillindia.org` / `Admin@123`
3. The site will now **force you to a password form before anything else loads**
   — set a new password there.

`Admin@123` is published in your **public GitHub repo** (it is the seeded admin
hash in `database/eduskill.sql`). Anyone who finds your site can search the repo
for it.

That is why the shipped file now sets `must_change_password = 1` on the seeded
admin. `includes/security.php:718` redirects to `/admin/profile#password` before
any other admin page renders, so the published default cannot be left in place —
a written instruction is not a control, this is.

While you are there, consider enabling **2FA** (Admin → Security).

---

## Step 9 — Remove the test accounts

Test role accounts exist with passwords that were shared in chat. First **look at
what you have**, so you do not delete a real colleague's account:

```sql
SELECT id, name, email, status FROM users ORDER BY id;
```

Then delete only the test rows, **by exact address**:

```sql
DELETE FROM users WHERE email IN (
  'editor@eduskillindia.org',
  'staff@eduskillindia.org',
  'school@eduskillindia.org',
  'teacher@eduskillindia.org'
) AND id <> 1;

DELETE FROM members WHERE email = 'portal.member@eduskillindia.org';
```

> Do **not** use `DELETE FROM users WHERE email LIKE '%@eduskillindia.org'`.
> That is your own organisation's domain — it would delete every real staff
> account you create later on it.

---

## If something goes wrong

| Symptom | Fix |
|---|---|
| 500 error after upload | Restore the Step 1 backup. Check `logs/php-error.log` in File Manager. |
| Site loads but unstyled | Cache — repeat Step 6, then hard-refresh (`Ctrl+F5`). |
| Old colours persist | The theme lives in the DB — Step 5 did not run. Re-import. |
| "Unknown database" on import | You did not click *into* the database first (Step 5.2). |
| Login broken | `config.php` was overwritten. Restore it from the backup. |

---

## Known gaps after this deploy

Be aware these ship in this state:

1. **Seven role dashboards do not exist** — `/member/dashboard`,
   `/student/dashboard`, `/teacher/dashboard`, `/school/dashboard`,
   `/volunteer/dashboard`, `/donor/dashboard`, `/staff/dashboard` all 404.
   The router falls back to `/account` (members) or `/admin/dashboard` (staff),
   so nobody hits a dead end — but five roles share one generic page.

2. **Seeded demo content** — the blog post *"From Beneficiary to Entrepreneur:
   Anita's Story"* pairs a named beneficiary with a **stock photograph**, which
   your own `IMAGE_SOURCES.md` forbids. It is now featured on the homepage.
   Either replace it with a real story or unpublish it — the section hides itself
   automatically when no qualifying post exists.

3. **Payment gateways are not configured** — all keys are empty, so donations
   cannot be taken until they are entered in Admin → Payment Settings.

4. **HTTPS redirect is still commented out** in `.htaccess` (lines 55-56).
   Hostinger's "Force HTTPS" toggle covers this; uncomment only if you prefer
   the app to enforce it.

---

*Generated 2026-08-10 · update package for eduskillindia.org (5.3.3 → 5.7.0)*
