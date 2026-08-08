# EDUSKILL INDIA FOUNDATION — Security Assessment (VAPT) Report

**Engagement:** Authorized white-box VAPT + secure code review + remediation
**Target:** EDUSKILL INDIA FOUNDATION web application (`c:\xampp\htdocs\pwf`)
**Stack:** PHP 8.2 (no framework, no Composer), MariaDB 10.4, Apache + mod_rewrite
**Method:** OWASP Top 10 (2021) + OWASP Testing Guide, white-box (source + live)
**Approach:** Find → prove safely → assess → **fix** → **retest** → report
**Date:** 2026-08-08
**Environment tested:** local (`http://localhost/pwf/`) with the live application DB

> **Status headline:** 9 exploitable weaknesses were found, proven with safe
> proof-of-concept, fixed in source, and retested to confirmed non-exploitable.
> No Critical (RCE / auth-bypass / DB-dump) vulnerability was found. The
> remaining items are production-configuration hardening (HTTPS enforcement,
> a CDN library upgrade) with clearly stated residual risk. **This assessment
> does not claim the application is "unhackable"** — it is an evidence-based
> snapshot of the posture after remediation.

---

## 1. Executive Summary

The application is architecturally sound on the highest-impact vulnerability
classes. Database access is **uniformly parameterized** (PDO with emulation off),
output is consistently escaped, CSRF is enforced on every state-changing action,
file uploads regenerate filenames and cannot execute, and all four payment
webhooks verify HMAC signatures **and** re-query the gateway before crediting an
order. These are the controls that separate a robust application from an easily
compromised one, and they held up under both static and live testing.

The defects found clustered in two themes:

1. **Missing rate-limits on guess-able secrets** — the 2FA code, the email OTP,
   and the public API had no brute-force / abuse ceiling.
2. **Access-control edges** — the RBAC gate could be side-stepped with URL
   spelling variants, 26 admin pages were outside its map, and four upload
   directories holding PII were served directly by Apache.

All were fixed at the root cause (not by input-blocking band-aids) and retested.

| Metric | Result |
|---|---|
| Total findings | 9 fixed + 5 residual/informational |
| Critical | 0 |
| High | 5 (all fixed & retested) |
| Medium | 3 (all fixed & retested) |
| Low | 1 fixed + 3 residual |
| Informational | 1 |
| Categories tested fully clean | SQLi, IDOR/BOLA, CSRF, file-upload RCE, path traversal/LFI, command injection, SSRF, secrets exposure, business logic |

---

## 2. Scope

**In scope (all owned by EDUSKILL INDIA FOUNDATION):**

- Public website — 88 root PHP pages
- Admin panel — 137 pages (`/admin/*`)
- Public form handlers — 15 (`/forms/*`)
- REST API v1 — 15 endpoints (`/api/v1/*`)
- Authentication: admin login, member login/signup, password reset, email OTP, 2FA (TOTP), OAuth (Google/Facebook), remember-me
- Payment + webhook endpoints (Cashfree, Razorpay, Stripe, PayPal)
- File upload + document/certificate generation + verification
- Cron endpoints, static assets, configuration, database

**Out of scope / not performed:** destructive testing, DoS/stress, production
traffic flooding, third-party service attacks, real-user credential theft, DNS
takeover. Live testing used disposable probe records that were deleted after each test.

---

## 3. Methodology

White-box review of the full source tree combined with live black/grey-box
probing against the running application. Every finding was **proven** with a
minimal, non-destructive proof-of-concept (a repro script or `curl` sequence),
**fixed** at the root cause, then **retested** with the identical PoC to confirm
the exploit no longer works and legitimate functionality still does. Probe data
(disposable users, tokens, comments, payment rows) was removed immediately after
each test; the application DB was returned to its pre-test row counts.

---

## 4. Findings Summary

| ID | Vulnerability | Severity | OWASP | CVSS 3.1 | Status |
|----|---------------|----------|-------|----------|--------|
| F-01 | Stored XSS via rich-text (blacklist sanitizer bypass) | High | A03 | 8.1 | ✅ Fixed & Retested |
| F-02 | Static PII exposure — 4 upload dirs served by Apache | High | A01/A02 | 7.5 | ✅ Fixed & Retested |
| F-03 | RBAC gate bypass via URL spelling variants | High | A01 | 7.1 | ✅ Fixed & Retested |
| F-04 | 2FA code brute-force — no lockout | High | A07 | 7.5 | ✅ Fixed & Retested |
| F-05 | Email OTP brute-force — no verify-side lockout | High | A07 | 7.5 | ✅ Fixed & Retested |
| F-06 | 26 admin pages outside RBAC map (fail-open) | Medium | A01 | 6.5 | ✅ Fixed & Retested |
| F-07 | `javascript:` href XSS from commenter website field | Medium | A03 | 6.1 | ✅ Fixed & Retested |
| F-08 | Public API write endpoints unthrottled | Medium | A04 | 5.3 | ✅ Fixed & Retested |
| F-09 | Password change did not revoke remember-me token | Low | A07 | 3.5 | ✅ Fixed & Retested |
| R-01 | HTTP TRACE enabled (XST) | Low | A05 | 3.1 | ✅ Fixed (server restart pending) |
| R-02 | HSTS header absent | Low | A05 | — | ✅ Fixed (emits on HTTPS) |
| R-03 | CSP allows `script-src 'unsafe-inline'` | Low | A05 | — | ⚠ Residual (tracking-stack tradeoff) |
| R-04 | API CORS `Access-Control-Allow-Origin: *` | Low | A05 | — | ⚠ Residual (no credentials; public data) |
| R-05 | `xlsx` 0.18.5 (CDN) has known CVEs | Info | A06 | — | ⚠ Residual (export-only; CVE path unreachable) |

---

## 5. Detailed Findings (Fixed)

### F-01 — Stored XSS via rich-text content · High · A03

**Location:** `includes/helper.php` → `blog_sanitize_html()` (rich-text stored by blogs, pages, programs, events, campaigns, FAQs).

**Description.** The HTML sanitizer used a regex **blacklist** (strip `<script>`,
strip `on\w+=`). Blacklists miss cases the browser still executes: `<svg/onload=…>`
and `<body/onload=…>` have no whitespace before the attribute, so `\son\w+=` did
not match them.

**Impact.** An author (or anyone who can post stored HTML) could persist
JavaScript that runs in every viewer's session, including an admin reviewing the
content — session theft, admin action forgery.

**Proof (pre-fix).** Of 15 payloads, 2 survived: `<svg/onload=alert(1)>` and
`<body/onload=alert(1)>`.

**Root cause.** Sanitization by pattern-matching bad input instead of allowing
known-good structure.

**Fix.** Replaced with a **DOM allow-list** parser (`DOMDocument`/`DOMXPath`):
elements not on the allow-list are unwrapped (text kept), non-allow-listed
attributes are removed (kills all `on*`), `href`/`src` are routed through a
scheme allow-list, dangerous containers (`script`, `svg`, `iframe`, …) are removed
with contents. Nodes are walked deepest-first so unwrapping a parent cannot skip
a child.

**Retest.** 0 / 15 payloads bypass. 23 stored rich-text values re-checked — 0
lost their visible text. UTF-8/Devanagari round-trips intact.

---

### F-02 — Static PII exposure via upload directories · High · A01/A02

**Location:** `uploads/{admissions,documents,students}` (résumés already closed).
Rendered via `upload_url()` at 8 admin sites.

**Description.** Applicant admission documents, scholarship/employee/certificate
documents, and student personal documents were written under `/uploads/**` and
linked with `upload_url()` — plain Apache-served static paths. A random filename
is **not** access control: URLs leak through referrers, proxy logs, browser
history, and forwarded links, and directories were enumerable.

**Impact.** Anonymous disclosure of PII (ID proofs, personal documents).

**Proof.** Marker-file test — a file dropped into each directory was fetched over
HTTP with no session.

**Fix.**
- `.htaccess` `Require all denied` in `uploads/{admissions,documents,student-documents}` (résumés already had it).
- All 8 admin render sites switched to `secure_upload_url()` → `admin/secure-file.php`, which enforces `require_admin()`, validates the path against an allow-list, proves containment with `realpath()` (defeats `../` and symlink escape), and streams with `Content-Disposition: attachment` + `nosniff`.
- **Public/PII split:** the student photograph (rendered publicly by `verify-student.php`) stays in the public `uploads/students`; personal documents moved to the denied `uploads/student-documents`. The Document Hub ID photo (shown on the public `document.php`) moved from `documents` to `media` so it still renders.

**Retest.** Per-directory canary test: 4 PII dirs `HTTP 404 / leaked=0`; public
dirs (`students`, `media`, `images`) still `200`. Traversal probes
(`../config.php`, encoded, symlink) leak `0` config bytes. `.svg` deliberately
excluded from the served MIME map.

---

### F-03 — RBAC gate bypass via URL spelling variants · High · A01

**Location:** `includes/rbac.php` → `rbac_current_slug()`.

**Description.** The gate derived the page "slug" from the raw request path with
no normalization. On a case-insensitive filesystem (Windows/macOS) and with
Apache serving `blogs.php` directly, these all reached the same page but produced
a slug the gate did not recognise — so it treated the page as un-gated:
`/admin/blogs.php`, `/admin/BLOGS`, `/admin/Blogs.php`, `/admin/./blogs`,
`/admin/blogs.php/`, `/admin/ADMIN/blogs`.

**Impact.** A restricted admin (non-super role) could reach modules their role
forbids by changing the URL's spelling.

**Proof (pre-fix).** 7 of 12 URL variants bypassed the gate.

**Fix.** `rbac_current_slug()` now drops the query/fragment, URL-decodes,
collapses empty/`.`/`..` segments, matches the `admin` directory
case-insensitively, strips a trailing `.php`, lower-cases, and whitelists
`[a-z0-9_-]`. `rbac_can_slug()` now **fails closed**: an unknown slug that names
a real `admin/*.php` file is denied rather than served.

**Retest.** 0 / 12 variants bypass.

---

### F-04 — 2FA code brute-force (no lockout) · High · A07

**Location:** `includes/auth.php` → `finish_2fa_login()`.

**Description.** After a correct password, the TOTP step accepted unlimited code
attempts. A 6-digit TOTP is only ~10⁶ wide and recovery codes share the path.

**Impact.** An attacker with a valid password (phished/reused) could brute-force
the second factor, defeating 2FA.

**Proof (pre-fix).** 300 wrong codes submitted, 300 accepted for checking, never blocked.

**Fix.** The 2FA step now shares the password stage's lockout budget
(`sec_lockout_max`/`LOGIN_MAX_ATTEMPTS`): every wrong code is recorded in
`login_attempts`, the account/IP locks at the threshold, and the handshake has a
bounded TTL (`TWOFA_HANDSHAKE_SECS`, 10 min) so an abandoned handshake cannot be
ground on. The pending login is no longer pre-recorded as a success.

**Retest.** Brute force blocks at attempt 5; genuine code still logs in; a valid
code after handshake expiry is refused; a couple of typos then the correct code
still works.

---

### F-05 — Email OTP brute-force (no verify-side lockout) · High · A07

**Location:** `includes/member_auth.php` → `verify_member_otp()` (used by `verify-otp.php`).

**Description.** OTP **send** was throttled (3/10 min) but OTP **verify** was not.
A 6-digit code (10⁶) with a 15-minute lifetime could be brute-forced; wrong
guesses did not consume the real code.

**Impact.** Account email-verification / OTP-gated flow takeover for a known email.

**Proof (pre-fix).** 200 wrong guesses submitted, 200 accepted, real code still valid.

**Fix.** `verify_member_otp()` now caps wrong attempts per email+IP (5 / 15 min
via `pwf_throttle`); on hitting the cap it **burns all outstanding codes** for
that email, forcing a fresh (throttled) send.

**Retest.** Blocks at attempt 6; real code burned after cap; correct-first-try and
2-typos-then-correct still succeed.

---

### F-06 — 26 admin pages outside the RBAC map (fail-open) · Medium · A01

**Location:** `includes/rbac.php` → `rbac_slug_group()`.

**Description.** 26 admin pages (detail/print/sub-resource views —
`exam-questions`, `payslip`, `certificate-view`, `campaign-reports`, …) had no
nav-map entry, and unmapped pages were treated as reachable by any admin.

**Impact.** A restricted admin could open sensitive sub-resources of modules
their role forbids.

**Fix.** Added `rbac_slug_aliases()` mapping each orphan page to its parent
group's permission, plus a `rbac_core_slugs()` allow-list for genuinely-shared
endpoints (`secure-file`, `ckeditor-upload`, which carry their own auth). Combined
with F-03's fail-closed default, every admin page now resolves to a group or is
denied.

**Retest.** All 137 admin pages resolve to a group/alias/core; 0 unresolved.

---

### F-07 — `javascript:` href XSS from commenter website · Medium · A03

**Location:** `admin/comments.php:176` (+ 9 other unguarded href sites).

**Description.** A **public commenter's** `website` field was emitted as a raw
`href` in the admin moderation view. A `javascript:` URL would execute in the
admin's session when clicked.

**Fix.** Routed every stored/user-supplied href through `safe_href()` (permits
only http/https/site-relative; blocks `javascript:`, `data:`, `vbscript:`,
control chars, and case/whitespace evasions). For the genuinely-untrusted
commenter field, an unsafe value now renders as inert text with an "unsafe link"
badge instead of a link. Applied at 10 sites (comments, campaign-gallery,
partners, sponsors, social-links, become-partner, contact, footer, navbar).

**Retest.** 0 / 17 payloads produce an executable href; legitimate URLs
unaffected; end-to-end test with a hostile comment row confirms inert output.

---

### F-08 — Public API write endpoints unthrottled · Medium · A04

**Location:** `api/v1/{contact,donate,volunteer}.php`.

**Description.** The site's own forms throttle submissions (`pwf_throttle`,
5/300 s) but the equivalent API endpoints did not — an unauthenticated client
could mass-insert contact messages, pending donations, and volunteer records.

**Proof (pre-fix).** 30 rapid POSTs → 30 rows inserted, 0 rejected.

**Fix.** Added `api_throttle()` to the API bootstrap (shared IP-keyed budget,
namespaced `api-*`, returns `429` + `Retry-After`) and applied it to the three
write endpoints.

**Retest.** 30 POSTs → 5 × `201`, 25 × `429`; donate and volunteer likewise cap
at 5. Probe rows deleted; `contact_messages` restored to its original count.

---

### F-09 — Password change did not revoke remember-me token · Low · A07

**Location:** `admin/profile.php` (password-change handler).

**Description.** Changing the password updated the hash but left the DB
`remember_token` intact, so a pre-existing "remember me" cookie (potentially an
attacker's) kept working after the very action meant to lock them out.

**Fix.** On password change, `remember_forget()` nulls the token (revoking all
persistent-login cookies; the user re-opts-in on next login) and
`session_regenerate_id(true)` rotates the current session id. The live session
stays valid so the user is not logged out of the device they just used.

**Retest.** `remember_token` is `NULL` after change; a stale remember cookie can
no longer restore a session.

---

## 6. Hardening Applied (R-01, R-02)

- **R-01 HTTP TRACE / XST** — `TraceEnable off` added to `httpd.conf` (a `.htaccess`
  rule cannot block TRACE because Apache core answers it before mod_rewrite;
  `TRACK`/`DEBUG` are refused in `.htaccess`). **Takes effect on the next Apache
  restart** (operator-controlled). Backup: `httpd.conf.pwf-vapt-bak`.
- **R-02 HSTS** — `Strict-Transport-Security: max-age=31536000; includeSubDomains`
  now emitted, gated to HTTPS only (so it is correctly absent during local HTTP
  development and active in production).

---

## 7. Categories Verified Clean (no exploitable defect found)

| Category | Evidence |
|---|---|
| **SQL Injection (A03)** | Every query uses PDO named placeholders (emulation off); `IN()`/`LIMIT`/`OFFSET` values are `int`-cast or clamped; dynamic `$where` fragments are static strings with bound params. Live probes (single-quote, boolean, `SLEEP()` time-based, error-based) showed no injection, no timing delay, no error leak. |
| **IDOR / BOLA (A01)** | Receipts are HMAC-token-gated (`hash_equals`); documents use 128-bit `random_bytes` `qr_token`; payment order IDs carry a 32-bit random suffix and reconcile against the live gateway; user dashboards use server-side `current_user_id()` with no manipulable object ref. Public verification pages expose only intended-public fields (no email/phone/address). |
| **CSRF (A01)** | `require_csrf()` on every `is_post()` state-change; session cookie `HttpOnly` + `SameSite=Lax` + conditional `Secure`; remember-me token stored as SHA-256, compared with `hash_equals`. |
| **File Upload → RCE** | `uploads/.htaccess` disables the PHP engine, removes handlers, denies script extensions, and forces SVG to download under a sandbox CSP. Uploads validate extension **and** real MIME (`mime_content_type`) **and** `getimagesize()`, then store under a fully regenerated random filename (kills traversal, double-extension, null-byte, overwrite). Live `.php`/`.php.jpg`/`.jpg.php` canaries did not execute. |
| **Path Traversal / LFI** | `secure-file` proves `realpath()` containment; the download endpoint ignores client path input; every dynamic `include`/`require` is anchored to `__DIR__`/`BASE_PATH`; the gateway loader strips all but `[a-z]`. Live traversal probes leaked 0 config bytes. |
| **Command Injection** | The only `exec()` (DB backup) builds its command entirely from `escapeshellarg()`-wrapped constants and server timestamps; admin-only. No `eval`/`assert`/`create_function`/`unserialize`/`extract($_GET)` anywhere. |
| **SSRF** | Every outbound `curl` targets a fixed host (gateway/provider bases from operator settings; `ip-api.com` with a `urlencode`'d IP path segment) with `FOLLOWLOCATION=false` (no redirect pivot). |
| **Secrets / Info Disclosure** | No secrets in frontend JS or public files; `.env`/`.git`/dumps/config/`includes`/`logs`/`storage` all return 404/denied over HTTP (verified with canaries); DB credentials only in the Apache-denied `config.php`. Custom 404, no stack traces. |
| **Business Logic (A04)** | Charged amounts are server-derived from DB prices (fail-closed if the gateway is disabled); discounts come from server-side coupon validation or the ownership-checked stored order; fulfillment is idempotent and gated on gateway-confirmed `paid`. All four payment webhooks verify HMAC signatures **and** re-query the gateway — a forged Cashfree webhook was rejected `401` live. Certificate/document issuance is admin-only (RBAC-gated); verification is read-only. |

---

## 8. Authentication & Session Assessment

| Control | Status |
|---|---|
| Password hashing | bcrypt (`PASSWORD_DEFAULT`) + `password_needs_rehash` upgrade on login ✅ |
| Password policy | min 8 + configurable policy + reuse-history check ✅ |
| Login lockout | IP+email budget, loopback-exempt ✅ |
| 2FA brute-force | **Fixed (F-04)** ✅ |
| OTP brute-force | **Fixed (F-05)** ✅ |
| User/email enumeration | Uniform "Invalid email or password"; generic "if an account exists" on reset ✅ |
| Reset tokens | 256-bit `random_bytes`, SHA-256 at rest, single-use (`used_at`), TTL-bounded, prior tokens invalidated ✅ |
| Session fixation | `session_regenerate_id(true)` on login + periodic rotation; `use_strict_mode`, `use_only_cookies` ✅ |
| Remember-me | 512-bit token, SHA-256 at rest, `hash_equals`, 2FA accounts excluded, **revoked on password change (F-09)** ✅ |
| Cookie flags | `HttpOnly` + `SameSite=Lax` + `Secure` (on HTTPS) ✅ |

---

## 9. API Security Assessment

- **AuthN/AuthZ:** public read endpoints by design; write endpoints (contact/donate/volunteer) are unauthenticated by design but now **throttled (F-08)**; optional JWT (HS256) scaffold with `hash_equals` verification available.
- **CORS:** `Access-Control-Allow-Origin: *` with **no** `Access-Control-Allow-Credentials` → browsers never send cookies, so `*` exposes only already-public data. Restrictable per environment via the `api_cors_origin` setting (**R-04**, Low).
- **Webhooks:** all four gateways verify signatures before acting and re-query the gateway API; forged callback rejected `401` (verified live).
- **Excessive data / mass assignment:** write endpoints map named fields explicitly via `clean()`; no `$row = $_POST` mass-assignment.

---

## 10. Infrastructure & Headers

**Present:** `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`,
`Referrer-Policy: strict-origin-when-cross-origin`, `X-XSS-Protection`,
`Permissions-Policy`, a scoped `Content-Security-Policy`, and now **HSTS** (HTTPS).
Directory listing is off (`Options -Indexes`). Default admin credentials are
non-functional (bcrypt hashes in DB).

**Residual (production config):**
- **R-03** CSP includes `script-src 'unsafe-inline'` to support the analytics/CDN stack. This weakens CSP's XSS mitigation; the application's primary XSS defense is the DOM sanitizer (F-01) + output escaping, which are in place. Consider nonce/hash-based CSP as a future hardening. *Low.*
- **R-01** TRACE disabled in config; **requires an Apache restart** to take effect.
- **HTTPS is not yet enforced** — the site runs on local HTTP. The `.htaccess` HTTP→HTTPS redirect is present but commented; enable it with a valid certificate in production. Until then, `Secure` cookies and HSTS are inert.

---

## 11. Dependency Security (A06)

- **No Composer / npm manifests** — self-contained by design; nothing to audit server-side.
- **Bundled PHP:** `includes/lib/qrcode.php` (self-contained, no external deps, no known CVE).
- **CDN JavaScript (version-pinned):** fontawesome 6.5.2, chart.js 4.4.1, jspdf 2.5.2, lucide 0.469.0, sweetalert2 @11, **xlsx 0.18.5**.
  - **R-05** `xlsx` 0.18.5 has CVE-2023-30533 (prototype pollution) and CVE-2024-22363 (ReDoS). **Both are only reachable on the spreadsheet-parse path.** This app uses `xlsx` **export-only** (`book_new`/`aoa_to_sheet` in `assets/js/analytics.js`) — there is no `XLSX.read`/`readFile`/`sheet_to_json` anywhere, and the input is the admin's own analytics data. The vulnerable path is not reachable. *Informational — upgrade to ≥ 0.20.2 for hygiene.*
  - **No Subresource Integrity (SRI)** on CDN scripts. The CSP `script-src` host allow-list mitigates, but adding `integrity=` hashes (or self-hosting) would be defense-in-depth. *Low.*

---

## 12. Remediation Summary (files changed)

**Source fixes**
- `includes/helper.php` — DOM allow-list sanitizer (F-01) + `safe_href` routing (F-07)
- `includes/rbac.php` — slug normalization + fail-closed default + alias map (F-03, F-06)
- `includes/auth.php`, `config.php` — 2FA lockout + handshake TTL (F-04)
- `includes/member_auth.php` — OTP verify throttle (F-05)
- `admin/profile.php` — remember-token revoke + session rotation on password change (F-09)
- `api/v1/bootstrap.php`, `api/v1/{contact,donate,volunteer}.php` — `api_throttle()` (F-08)
- `admin/secure-file.php`, `admin/document-hub.php`, `admin/admissions.php`, `admin/documents.php`, `admin/employee-documents.php`, `admin/issued-certificates.php`, `admin/student-profile.php`, `admin/scholarship-applications.php` — PII routed via `secure-file` + photo/document split (F-02)
- `admin/comments.php`, `admin/campaign-gallery.php`, `admin/partners.php`, `admin/sponsors.php`, `admin/social-links.php`, `become-partner.php`, `contact.php`, `includes/footer.php`, `includes/navbar.php` — `safe_href` (F-07)

**Config / infra**
- `.htaccess` — HSTS (HTTPS-gated), TRACK/DEBUG block (R-01, R-02)
- `C:\xampp\apache\conf\httpd.conf` — `TraceEnable off` (R-01; restart pending; backup kept)
- `uploads/{admissions,documents,student-documents}/.htaccess` — `Require all denied` (F-02)

---

## 13. Retesting Results

| ID | Vulnerable | Fixed | Retested | Result |
|----|:---:|:---:|:---:|:---:|
| F-01 | 2/15 payloads bypass | DOM allow-list | 0/15 bypass, 0 content lost | **PASS** |
| F-02 | PII fetched anonymously | 4 dirs denied + gate | 4 dirs 404, public dirs 200, 0 traversal leak | **PASS** |
| F-03 | 7/12 variants bypass | slug normalization | 0/12 bypass | **PASS** |
| F-04 | 300/300 codes checked | shared lockout | blocks at #5 | **PASS** |
| F-05 | 200/200 codes checked | verify throttle | blocks at #6, code burned | **PASS** |
| F-06 | 26 pages unmapped | alias map + fail-closed | 0 unresolved | **PASS** |
| F-07 | js: href in admin | `safe_href` | 0/17 executable | **PASS** |
| F-08 | 30/30 inserts | `api_throttle` | 5×201 / 25×429 | **PASS** |
| F-09 | token survives change | revoke + rotate | token NULL after change | **PASS** |

**Regression:** 27 modified files lint-clean; 25 public/API/auth endpoints return
no 5xx; fix-touched pages render without PHP errors; 0 application fatal/uncaught
errors in the log (only CLI test-script artifacts). All probe data removed; DB
row counts restored.

---

## 14. Remaining Risks (honest)

1. **HTTPS not enforced in this environment.** `Secure` cookies and HSTS only
   take effect once the site is served over TLS. **Action:** obtain a certificate,
   uncomment the HTTP→HTTPS redirect in `.htaccess`.
2. **TRACE** remains until the next Apache restart applies `TraceEnable off`.
3. **CSP `unsafe-inline` (R-03)** — residual XSS defense-in-depth gap, mitigated by
   the sanitizer + escaping; move to nonce/hash CSP when the tracking stack allows.
4. **`xlsx` 0.18.5 (R-05)** — unreachable CVE path today; upgrade to ≥ 0.20.2 so a
   future import feature can't reintroduce risk.
5. **CDN scripts without SRI (R-05)** — add `integrity=` or self-host.
6. This assessment reflects the codebase **as tested on 2026-08-08**; new code
   needs re-review. Security is a process, not a one-time state.

---

## 15. Production Security Checklist

- [ ] Enable HTTPS + valid certificate; uncomment the HTTP→HTTPS redirect in `.htaccess`
- [ ] Restart Apache to apply `TraceEnable off`
- [ ] Set `api_cors_origin` to the real front-end origin(s) (drop `*`)
- [ ] Turn on RBAC enforcement (`rbac_enforce=1`) and assign least-privilege roles
- [ ] Confirm `display_errors=Off` and error logging to a non-web path in production `php.ini`
- [ ] Rotate any credentials that were present during development (DB, SMTP, gateway keys)
- [ ] Upgrade CDN `xlsx` to ≥ 0.20.2; add SRI hashes to CDN `<script>` tags
- [ ] Schedule the token-gated DB backup cron; verify backups restore
- [ ] Configure SPF / DKIM / DMARC for the sending domain
- [ ] Move toward nonce/hash-based CSP (remove `script-src 'unsafe-inline'`)
- [ ] Re-run this assessment after significant feature changes

---

## 16. Conclusion

After remediation, **EDUSKILL INDIA FOUNDATION is in a strong security posture
for production**, contingent on the production checklist above (chiefly enabling
HTTPS). No Critical vulnerability was found; the 9 exploitable weaknesses
identified were fixed at root cause and retested to confirmed non-exploitable,
and the remaining items are clearly-scoped configuration/hygiene tasks with
stated residual risk.

**EDUSKILL INDIA FOUNDATION — SECURE & PRODUCTION READY** *(subject to the
production checklist in §15; not claimed "unhackable").*

---
*Report generated 2026-08-08 · authorized white-box VAPT with remediation and retesting.*
