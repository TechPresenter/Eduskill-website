# Member Management

Complete membership module for EDUSKILL INDIA FOUNDATION: profiles, tiers,
digital + printable ID cards with QR verification, expiry reminders (email/SMS)
and online renewals via Cashfree. No Composer / external libraries — the QR
encoder, card renderer and PDF writer are all self-contained.

---

## 1. Install / upgrade

Run the migration, then the seeder (idempotent — safe to re-run):

```bash
C:\xampp\mysql\bin\mysql.exe -u root pwf < database\schema_v6.sql
C:\xampp\php\php.exe database\seed_v6.php
```

`schema_v6.sql` adds membership fields to `members`, tier fields to
`membership_plans`, the `membership_renewals`, `membership_reminders` and
`member_audit` tables, and seeds settings. `seed_v6.php` adds four email
templates, generates the cron token, and back-fills membership IDs + QR tokens
onto existing members.

---

## 2. What was added

### New tables / columns
- **`members`** (new columns): `member_code`, `plan_id`, `dob`, `gender`,
  `address`, `city`, `state`, `pincode`, `occupation`, `join_date`,
  `expiry_date`, `qr_token`, `membership_status` (`none|active|expired|cancelled`),
  `auto_renew`.
- **`membership_plans`** (new columns): `color`, `duration_days`, `tier_level`.
- **`membership_renewals`** — one row per renewal/payment (offline or Cashfree).
- **`membership_reminders`** — idempotent log of reminders sent.
- **`member_audit`** — per-member audit trail.

### Shared includes
| File | Purpose |
|------|---------|
| `includes/membership.php` | Domain layer — codes, tiers, status, activation, renewal, audit. Loaded in `bootstrap.php`. |
| `includes/lib/qrcode.php` | Self-contained QR encoder (`PWF_QR`). Verified by 40 independent decode round-trips. |
| `includes/membership_card.php` | GD card renderer + minimal PDF writer + on-screen HTML card. |
| `includes/sms.php` | MSG91 / Fast2SMS adapter (off until configured). |
| `includes/payments.php` | Cashfree Payment-Links adapter (off until configured). |

### Admin pages
- `admin/members.php` — full CRUD, profile + membership fields, photo upload,
  audit trail, renewal history, activate / renew / cancel, **CSV import/export**.
- `admin/member-card.php` — card PDF / PNG / printable page (template picker).
- `admin/membership-renewals.php` — all payments, record offline renewal, mark
  paid / refund, collection stats.
- `admin/membership-plans.php` — extended with tier colour, duration (days), level.
- `admin/membership-settings.php` — cards, reminders, Cashfree, SMS, cron token.

### Public / member pages
- `verify-member.php` — public QR verification target (privacy-limited).
- `member-card.php` — member's own card (view / print / PDF / PNG).
- `membership-renew.php` — self-service renewal (Cashfree or offline) + auto-renew.
- `membership-payment-return.php` — Cashfree return handler.
- `api/v1/cashfree-webhook.php` — signed webhook.
- `account.php` — now shows a membership status panel with card + renew links.

### Cron
- `cron/membership-reminders.php` — daily expiry reminders + lapse handling.

---

## 3. Membership IDs & QR verification

- Every member gets a **membership ID** like `PWF-2026-00042` (prefix is
  configurable in settings) and a random 32-char **QR token**.
- The card QR encodes `…/verify-member?token=<qr_token>`.
- The public verify page also accepts a typed membership ID
  (`…/verify-member?code=PWF-2026-00042`).
- The verify page shows **only** name, photo, tier and validity — never email,
  phone or address.

---

## 4. ID cards

Three templates: **classic**, **modern**, **dark** (default set in settings).
Rendered with GD; the QR is drawn directly from the encoder's module matrix, so
the PDF/PNG contain a real scannable code with no external calls.

- On-screen: crisp HTML/SVG card (`card_html`).
- Download: PDF (card-sized page) or PNG.
- **Fonts:** the renderer looks for a bundled TTF in `assets/fonts/`
  (`Inter-Regular.ttf` / `Inter-Bold.ttf` / `Inter-SemiBold.ttf`), then common
  system fonts, then falls back to GD's bitmap font. For pixel-perfect cards on
  Linux hosting, drop those three TTFs into `assets/fonts/`.

---

## 5. Membership tiers

Tiers are the existing **Membership Plans**. Each plan now has:
- `duration_days` — used to compute expiry on activation/renewal,
- `tier_level` — ordering (Basic 1 → Platinum 4),
- `color` — accent colour on the card and tier badge.

Activating a member (from their profile) sets `plan_id`, `join_date`,
`expiry_date = start + duration_days`, and `membership_status = active`.

---

## 6. Renewals & payments (Cashfree)

Renewal terms **stack** on any unexpired time (new expiry = current expiry +
duration if still active, else today + duration).

**Offline:** admins record renewals from a member's profile or the Renewals page
— the membership is extended immediately and a `paid` record is stored.

**Online (Cashfree):** enable in **Membership Settings** and paste the App ID +
Secret. The renewal flow creates a Cashfree Payment Link and redirects the
member; on completion the return handler **and** the webhook both re-check the
payment status via the Cashfree API before applying the renewal (the redirect /
webhook body is never trusted on its own; the webhook HMAC signature is verified).

- Webhook URL: `…/api/v1/cashfree-webhook` (extensionless — the site redirects
  `.php` URLs, so callbacks must use the clean form).
- Environment toggle: sandbox / production.

---

## 7. Expiry reminders (email + SMS)

Reminders go out at the configured thresholds (default **30/15/7** days) plus an
"expired" notice when a term lapses. Each send is logged uniquely per
`(member, expiry_date, stage, channel)` so re-running never double-sends.

Email uses the existing mailer + templates (`membership_expiry_reminder`,
`membership_expired`, `membership_welcome`, `membership_renewed`). SMS is sent
only when enabled (MSG91 needs a DLT template id; Fast2SMS uses the quick route
if no sender is set).

### Scheduling the cron

**Windows Task Scheduler (local):** create a daily task running
```
"C:\xampp\php\php.exe" "C:\xampp\htdocs\pwf\cron\membership-reminders.php"
```

**Cron (Linux host):**
```
0 8 * * * /usr/bin/php /path/to/pwf/cron/membership-reminders.php
```

**By URL (token-protected)** — for cron-URL services (use the clean URL, no `.php`):
```
https://your-site/cron/membership-reminders?token=<cron token from settings>
```

---

## 8. CSV import / export

- **Export:** Members → *Export* downloads all members as CSV.
- **Import:** Members → *Import*. Header row required. Recognised columns:
  `name, email, phone, type, plan, join_date, expiry_date, status,
  membership_status, address, city, state, pincode, occupation, gender, dob`.
  Rows missing `name`/valid `email` are skipped; existing members (matched by
  email) are updated, others created with a random password. `plan` accepts a
  plan name or id; dates accept `YYYY-MM-DD` or `DD/MM/YYYY`. A sample file is
  downloadable from the import page.

---

## 9. Settings reference (Admin → Membership Settings)

| Group | Keys |
|-------|------|
| Membership | `membership_code_prefix`, `membership_card_template`, `membership_reminders_enabled`, `membership_reminder_days`, `membership_cron_token` |
| Payments | `cashfree_enabled`, `cashfree_env`, `cashfree_app_id`, `cashfree_secret_key`, `cashfree_webhook_secret` |
| SMS | `sms_enabled`, `sms_provider`, `msg91_authkey`, `msg91_sender`, `msg91_route`, `msg91_dlt_template_id`, `fast2sms_key`, `fast2sms_sender` |

Secret fields are preserved when the form is submitted with them left blank.
