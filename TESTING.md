# Manual Test Checklist — EDUSKILL INDIA FOUNDATION

Base URL (local): `http://localhost/pwf`

## 1. Public pages (each should load, be styled, and show content)

- [ ] `/` Home — hero slider, counters animate, programs, events, blogs, testimonials, gallery, partners, map, contact
- [ ] `/about` · `/our-story` · `/mission-vision` · `/leadership-team` · `/management-body` · `/team` · `/ngo-details`
- [ ] `/programs` · `/causes` · `/schemes` (+ `?slug=`) · `/scholarship` · `/skill-development` · `/campaigns` (+ `?slug=`) · `/achievements` · `/certificates`
- [ ] `/gallery` (+ `?album=`) lightbox · `/media` · `/news-media` · `/blogs` (search + category + pagination) · `/blog-details?slug=children-return-to-school` (views++, comments) · `/success-stories` · `/testimonials` · `/resources` (download)
- [ ] `/events` (+ `?slug=` countdown + registration) · `/calendar`
- [ ] `/volunteer` · `/internship` · `/membership` (multi-step) · `/career` (+ `?slug=`) · `/become-partner` · `/donate` · `/scholarship`
- [ ] `/verify-certificate?certificate_number=PWF-VOL-2026-0001` → **Valid**; a random number → **Not found**
- [ ] `/contact` (map + form + FAQ) · `/feedback` · `/faqs`
- [ ] `/privacy-policy` · `/terms` · `/refund-policy` · `/disclaimer` · `/cookie-policy` (consent bar) · `/sitemap-page`
- [ ] A bad URL (e.g. `/nope`) → branded **404**

## 2. Forms (AJAX → SweetAlert success, row appears in admin)

- [ ] Contact → Admin ▸ Contact Messages
- [ ] Newsletter (footer) → Admin ▸ Newsletter; duplicate email handled gracefully
- [ ] Volunteer (with résumé) → Admin ▸ Volunteers
- [ ] Internship (with résumé) → Admin ▸ Internships
- [ ] Donation → Admin ▸ Donations (status pending)
- [ ] Feedback (star rating) → Admin ▸ Feedback
- [ ] Event registration → Admin ▸ Event Registrations
- [ ] Blog comment → Admin ▸ Comments (pending moderation)
- [ ] Partner / Scholarship / Career / Membership applications → respective admin inboxes
- [ ] Submit with empty required fields → inline errors, no DB write
- [ ] Upload a `.php` as a résumé → rejected

## 3. Member auth

- [ ] `/signup` → account created, verification email logged (`logs/php-error.log` on local)
- [ ] `/verify-otp?email=...` → enter code (from OTP email/log) → verified
- [ ] `/login` before verify → “please verify” message
- [ ] `/login` after verify → `/account`; navbar shows the member’s name
- [ ] `/forgot-password` → generic success (no user enumeration)
- [ ] `/reset-password?email=&token=` → set new password → login works
- [ ] `/logout` → session cleared
- [ ] `/account` when logged out → redirected to `/login`

## 4. REST API (`/api/v1`)

- [ ] `GET /api/v1/` → endpoint list
- [ ] `GET /api/v1/blogs` → paginated envelope (`data` + `meta`)
- [ ] `GET /api/v1/blogs?slug=children-return-to-school` → single (or 404)
- [ ] `GET /api/v1/programs`, `/events`, `/gallery`, `/team`, `/testimonials`
- [ ] `POST /api/v1/contact` (JSON) → 201; missing fields → 422

## 5. Admin panel (`/admin/login`, `admin@eduskillindia.org` / `Admin@123`)

- [ ] Login throttling: 5 wrong passwords → temporary lockout
- [ ] Dashboard — stat cards show real counts; both charts render; recent activity + messages
- [ ] For each **CRUD** module (Programs, Blogs, Events, Team, Testimonials, Partners,
      Sponsors, Hero Slides, Menus, Pages, Projects, Gallery Albums/Media, Videos,
      Documents, Campaigns, Awareness Calendar, FAQs, Categories/Tags, Email Templates,
      SEO, Redirects, Social Links, Announcements, Popups, Careers, Membership Plans,
      Schemes, Scholarships, Issued Certificates, Users, Roles):
  - [ ] List loads with pagination + search
  - [ ] Create (with image where relevant) → success + appears in list
  - [ ] Edit → changes persist
  - [ ] Delete (confirm dialog) → removed (and file cleaned up)
- [ ] For each **inbox** (Comments, Event Regs, Donations, Volunteers, Internships,
      Contact Messages, Feedback, Newsletter, Members, Job/Membership/Scholarship/Partner
      applications, Activity Logs): list, filter by status, change status, delete
- [ ] **Settings** → change site name / contact → reflected on the public site
- [ ] **Sitemap** → regenerate; **Robots** editor saves
- [ ] **My Profile** → update name + change password (re-login works)
- [ ] Every admin URL while logged out → redirect to `/admin/login`

## 6. Security & performance spot checks

- [ ] `includes/functions.php`, `config.php`, `database/schema.sql` via browser → 403
- [ ] Uploaded file URL with `.php` → not executed (served as text / denied)
- [ ] Response headers include `X-Frame-Options`, `X-Content-Type-Options`, CSP
- [ ] Page source shows `<link ... tailwind.css?v=…>` (cache-busting)
- [ ] Dark/light toggle persists across reloads
- [ ] Mobile (≤768px): mega menu collapses to drawer; layouts stack; no horizontal scroll
