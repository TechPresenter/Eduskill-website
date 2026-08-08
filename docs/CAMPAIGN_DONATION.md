# Campaign Management + Donation System (5.8 / 5.9)

Extends the existing `campaigns` / `donations` with live payment gateways,
80G receipts & certificates, recurring donations, refunds, campaign gallery /
updates / volunteers / reports, and an embeddable widget. No new dependencies —
gateway calls use cURL; receipts/certificates print via the browser.

---

## 1. Install / upgrade

```bash
C:\xampp\mysql\bin\mysql.exe -u root pwf < database\schema_v12.sql
```

Adds campaign fields (`category`, `location`, `milestones`, `donor_count`,
`closed_at`), donation fields (`receipt_no`, `gateway`, `gateway_order_id`,
`gateway_payment_id`, `subscription_id`, `refunded_at`, `refund_amount`), and the
tables `donation_subscriptions`, `donation_refunds`, `campaign_media`,
`campaign_updates`, `campaign_volunteers`; plus Razorpay/Stripe/PayPal + 80G
settings.

---

## 2. Payment gateways (5.9)

Configure at **Admin → Payment Settings** (`admin/payment-settings.php`). All are
**disabled until keyed** (secrets preserved when the field is left blank):

| Gateway | Flow | Webhook URL |
|---|---|---|
| **Razorpay** | Order → Checkout.js → signature verify | `…/api/v1/razorpay-webhook` |
| **Stripe** | Hosted Checkout redirect | `…/api/v1/stripe-webhook` |
| **PayPal** | Orders v2 (approve → capture) | `…/api/v1/paypal-webhook` |
| Cashfree | (reused from memberships) Payment Link | `…/api/v1/cashfree-webhook` |

Donation flow: **`donate-pay.php`** creates a *pending* donation, then dispatches
to the chosen gateway. Completion happens only after the gateway confirms —
`donation-verify.php` (Razorpay signature), `donation-return.php` (Stripe/PayPal/
Cashfree, re-checked with the gateway), and the signed webhooks — all call
`donation_mark_paid()`, which recomputes the campaign total, assigns a receipt
number, and emails the 80G receipt. Webhook URLs are **extensionless** (the site
redirects `.php`). Adapters live in `includes/gateways/`.

**Receipts & 80G:** every completed donation gets an official **80G receipt**
(`admin/donation-receipt.php`; donor-accessible via a token-gated
`donation-receipt.php?id=…&t=…` link in the email). The annual **80G/12A tax
certificate** aggregates a donor's yearly donations (`admin/tax-certificates.php`
→ `admin/tax-certificate-view.php`). Set your org's 80G / 12A / PAN numbers in
Payment Settings.

**Recurring:** `donate-pay.php` records a `donation_subscriptions` row for
monthly/quarterly/annual pledges and processes the first charge now.

**Refunds:** from a donation's detail page (`admin/donations.php`) — calls the
gateway refund API (Razorpay/Stripe/PayPal), writes a `donation_refunds` audit
row, flips the donation to `refunded`, and recomputes the campaign total.

---

## 3. Campaign Management (5.8)

`admin/campaigns.php` now captures **category**, **location** and
**milestone markers** (comma-separated ₹ amounts). Each campaign row links to:

| Feature | Page |
|---|---|
| Progress + donor count | recomputed from completed donations (`campaign_recompute`) |
| **Updates** (post + email donors) | `admin/campaign-updates.php` |
| **Gallery** (image / video) | `admin/campaign-gallery.php` |
| **Volunteer assignment** | `admin/campaign-volunteers.php` |
| **Reports** (top donors, daily trend chart) | `admin/campaign-reports.php` |
| **Donation reports** (donor/campaign/date, CSV export) | `admin/donation-reports.php` |
| **Share / embed widget** | `campaign-embed.php?slug=…` (iframe-embeddable) |
| **Auto-expiry** (goal reached / end date → thank-you email) | `campaign_check_expiry()` |

Embed snippet for a third-party site:
```html
<iframe src="https://your-site/campaign-embed?slug=<campaign-slug>"
        width="380" height="320" style="border:0" loading="lazy"></iframe>
```

---

## 4. Notes

- **Fee due reminders / campaign expiry** can be wired into a daily cron by
  calling `campaign_check_expiry()` per active campaign.
- Currency defaults to INR (`donation_currency` setting); `donation_symbol()`
  maps INR/USD/EUR/GBP symbols.
- The original `donate.php` (offline pledge via `forms/donate`) is unchanged;
  the new `donate-pay.php` is the secure online-payment path.
