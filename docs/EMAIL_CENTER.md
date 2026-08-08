# Email Marketing & Email Management Module

An enterprise email suite for the admin panel — a unified dashboard, a
webmail-style mailbox, a rich composer, a 26-template premium library, CRM
contacts, IMAP/POP3-ready settings, automation and analytics. It **extends** the
existing v15 email-marketing engine (campaigns, subscribers, SMTP profiles,
open/click tracking) rather than replacing it.

---

## 1. Install / upgrade

```bash
C:\xampp\mysql\bin\mysql.exe -u root pwf < database\schema_v21.sql
C:\xampp\php\php.exe database\seed_email.php
```

`schema_v21.sql` adds the mail store and CRM tables (idempotent). `seed_email.php`
seeds the **26 premium templates**, a default signature, a default mail account,
demo mailbox messages and demo contacts (all additive / safe to re-run; pass
`--force` to overwrite template bodies).

---

## 2. What's where

| Area | Page (`admin/…`) |
|---|---|
| **Dashboard** — 8 KPIs, delivery/open/click/bounce rates, 14-day chart, scheduled, activity timeline | `email-dashboard` |
| **Mailbox** — Inbox/Sent/Drafts/Outbox/Scheduled/Archive/Spam/Trash + Starred/Important + Incoming/Outgoing + labels, thread view, star/bulk actions, search | `email-mailbox` |
| **Composer** — CKEditor 5, desktop/mobile preview, attachments, Cc/Bcc, priority, read-receipt, templates, variables, save-draft, schedule, test-send, signature | `email-compose` |
| **Template Library** — 26+ premium templates, category filter, preview, "Use" | `email-template-library` |
| **Contacts (CRM)** — contacts + lists + smart segments, CSV import/export, history | `email-contacts` |
| **Settings** — SMTP/IMAP/POP3 accounts, signatures, sending defaults + queue, DKIM/SPF, SMTP test tool | `email-settings` |
| **Analytics** — device/browser/OS/geo breakdowns, campaign comparison, CSV export | `email-analytics` |
| Campaigns / Subscribers / SMTP Profiles / Automations (existing v15) | `email-campaigns`, `subscribers`, `smtp-profiles`, `email-automations` |

Domain layer: **`includes/email_center.php`** (loaded on demand by the email
pages + `email-track.php`, not bootstrap). Requires `email_marketing.php`.

---

## 3. How mail actually flows

- **Compose → Send** creates an `email_messages` row and calls `ec_deliver()`,
  which sends through the message's `email_accounts` SMTP (`em_smtp_send()`, or
  the global `send_mail()` fallback), appends the signature + a tracking pixel,
  moves the message to **Sent**, and logs a `sent` event. If the mail server is
  unreachable it lands in **Outbox** to retry (from the Outbox toolbar or
  `ec_process_queue()`).
- **Schedule** stores the message in **Scheduled** with `scheduled_at`;
  `ec_process_queue()` (call from a cron or the Outbox "Send queue now" button)
  sends everything that's due.
- **Opens/clicks** hit `email-track.php` (extensionless `…/email-track`): `?c=open&m=<token>`
  and `?c=click&m=<token>&u=<url>` for mailbox messages, `…&t=<token>` for
  campaign recipients. Both log to `email_events` with parsed device/OS/browser,
  which powers the Analytics breakdowns.

---

## 4. Incoming mail (IMAP/POP3)

The Inbox and `ec_imap_sync()` are **IMAP-ready but dormant** because the PHP
`imap` extension is not enabled on this XAMPP. Everything degrades honestly:
the mailbox shows a banner and simply displays what's stored. To activate live
incoming sync:

1. Enable `extension=imap` in `php.ini` and restart Apache.
2. Configure an account's IMAP host/port/credentials in **Email Settings →
   Accounts**.
3. Use **Mailbox → Sync incoming** (or wire `ec_imap_sync($account)` into a cron).

`ec_imap_available()` gates all of this, so nothing breaks while it's off.

---

## 5. Security

- Account SMTP/IMAP/POP3 passwords are stored **encrypted at rest**
  (`ec_secret_write()` → AES-256-GCM via the Security Center key), decrypted only
  at send time. Editing shows a blank "unchanged" field (preserve-when-blank).
- All state-changing actions are CSRF-protected (`require_csrf()`), output is
  `e()`-escaped, admin-authored HTML runs through `rich_text()`.
- Unsubscribe links are HMAC-token gated (existing `newsletter_unsub_token()`).

---

## 6. The 26 templates

Newsletter · Welcome · Membership Confirmation · Donation Receipt · Donation
Thank-You · Course Enrollment · Course Completion · Certificate Issued · Event
Registration · Event Reminder · Volunteer Registration · Internship Offer ·
Internship Completion · Password Reset · Email Verification · OTP Verification ·
Contact Form Reply · Admin Notification · Partner Invitation · Sponsor Invitation
· Campaign Launch · Announcement · Holiday Greetings · Birthday Wishes ·
Anniversary Greetings · Custom Template.

Each uses `{{variable}}` placeholders (rendered by `mail_render()`), is wrapped in
the branded `mail_layout()` shell at send time, and is editable in the raw
template editor (`email-templates`).
