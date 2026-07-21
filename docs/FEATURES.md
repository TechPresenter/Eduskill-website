# Features — Eduskill India Foundation

A complete inventory of what ships in Phase 1.

---

## 🌐 Public website — 30 pages

| # | Page | Type | Key features |
|---|------|------|--------------|
| 1 | Home | Dynamic | Hero, animated counters, featured campaigns, testimonials, features, CTA, FAQ |
| 2 | About Us | Dynamic | Story, impact, team grid |
| 3 | Mission & Vision | CMS | Editable rich content |
| 4 | Team Members | Dynamic | Profile cards, roles, photos, social links |
| 5 | Our Programmes | Dynamic | Programme cards with icons & summaries |
| 6 | Schemes & Initiatives | CMS | Government schemes / initiatives |
| 7 | Campaigns | Dynamic | Progress bars, raised/goal, donor count, days left |
| 8 | Events | Dynamic | Date badges, upcoming flags, detail pages |
| 9 | Gallery (Photos) | Dynamic | Responsive grid + lightbox |
| 10 | Gallery (Videos) | Dynamic | YouTube/Vimeo thumbnails + play overlay |
| 11 | Blog | Dynamic | Categories, tags, detail pages, view counter |
| 12 | News & Media | Dynamic | Press releases / media coverage |
| 13 | FAQ | Dynamic | Accordion (native `<details>`) |
| 14 | Testimonials | Dynamic | Star ratings, photos, video links |
| 15 | Contact | Dynamic | Info + form → inbox (SweetAlert2 feedback) |
| 16 | Volunteer | Form | Registration → leads inbox |
| 17 | Become a Partner | Form | Partnership tiers + enquiry |
| 18 | Careers | Dynamic + Form | Job listings + application |
| 19 | Donate | CMS | Offline bank/UPI (online giving = Phase 3) |
| 20 | Scholarships | Dynamic + Form | Listings, eligibility, apply |
| 21 | Internships | Dynamic + Form | Listings, duration, apply |
| 22 | Verify Certificate | Utility | Live certificate lookup by number |
| 23 | Download Center | Dynamic | Documents grouped by category |
| 24 | Privacy Policy | CMS | Editable legal page |
| 25 | Terms & Conditions | CMS | Editable legal page |
| 26 | Refund Policy | CMS | Editable legal page |
| 27 | Disclaimer | CMS | Editable legal page |
| 28 | Cookie Policy | CMS | Editable legal page |
| 29 | 404 | System | Branded not-found |
| 30 | Sitemap | Auto | HTML sitemap generated from live content |

Plus detail pages: `campaign-details`, `event-details`, `blog-details`.

## 🎨 Frontend features

- **Premium UI/UX** — glassmorphism sticky header, animated **mega menu**, off-canvas mobile drawer
- **Rich footer** — multi-column links, newsletter subscribe, social icons, certifications, back-to-top
- **Motion** — scroll-reveal (AOS + custom), animated counters, hover lifts, gradient sheen, ripple, parallax
- **Buttons** — gradient, glass, ripple, hover animation, loading states, icon support
- **Typography** — Plus Jakarta Sans + Inter, tuned hierarchy & spacing
- **Cards** — responsive content/campaign/testimonial/event/program cards with hover effects
- **Dark / light mode** — token-based, localStorage-persisted
- **Responsive** — mobile-first across desktop / tablet / mobile
- **SEO** — clean slugs, per-page meta & Open Graph, robots control, sitemap
- **Forms** — honeypot spam protection, CSRF, async submit with friendly feedback
- **WhatsApp float** + tel/mailto links (settings-driven)

## 🛠️ Admin panel

- **Dashboard** — real KPI cards, Chart.js charts (fund/content), recent messages, activity log, quick actions
- **Page builder** — drag-and-drop sections, drafts, optimistic-lock publishing, revisions
- **Content modules (full CRUD)** — campaigns, programmes, events, team, testimonials, FAQs, blog
- **Media library** — uploads with previews
- **Forms inbox** — contact & enquiry submissions, read/unread
- **Newsletter** — subscriber management
- **Users & roles** — RBAC management (8 roles, granular permissions)
- **Settings** — brand colours, site name/tagline, contact details, social, SEO toggles
- **UX** — collapsible sidebar with active indicators, glass topbar, profile dropdown, theme switch,
  breadcrumbs, live clock, SweetAlert2 confirmations, responsive off-canvas mobile nav

## 🔐 Roles (RBAC)

`super_admin` · `staff` · `school` · `teacher` · `student` · `volunteer` · `member` · `donor`

In Phase 1 only **super_admin** and **staff** have admin access; the others are provisioned for the
self-service portals arriving in later phases.

## 🧩 CMS section types

`hero` · `rich_text` · `features` · `counters` · `cta_banner` · `faq` · `campaign_list` ·
`team_grid` · `testimonial_slider`

Each is an allow-listed, fault-isolated partial in `includes/sections/`.
