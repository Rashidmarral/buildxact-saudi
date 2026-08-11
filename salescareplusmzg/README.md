# Sales Care Plus MZG

Corporate portfolio website for **Sales Care Plus MZG**, a pharmaceutical distribution company
headquartered in Muzaffargarh, Punjab, Pakistan. Built with **Laravel** (PHP) and **MySQL**,
styled with Tailwind CSS in an original **teal + coral** theme, with a page structure inspired by
(but visually distinct from) the client's reference brand.

## Tech stack

- Laravel 12 (requires **PHP 8.2+** — deliberately pinned below the latest framework release so
  it runs on typical shared-hosting PHP 8.2 environments)
- MySQL (via `pdo_mysql`)
- Blade templates + Tailwind CSS v4 (compiled via Vite, no external CDN/font dependency)
- Plain vanilla JS for the mobile nav toggle, "More" dropdown, scroll-reveal animations and stat
  counters — no frontend framework required

## Pages

| Route | Purpose |
|---|---|
| `/` | Home — hero, stats bar, services, "how we work" process, trusted manufacturers teaser, coverage area, testimonials, CTA |
| `/about` | Company story, stats, quality checklist, certifications strip, mission/vision/values, leadership team |
| `/principals` | Manufacturer partners we're an authorized distributor for, with partnership stats and "why partner with us" |
| `/catalog` | Therapeutic categories, featured products, and the full searchable/filterable product catalog |
| `/catalog/{slug}` | Individual product detail page |
| `/services` | Distribution services offered + "how it works" process |
| `/quality` | Certifications, licences and cold-chain quality standards |
| `/gallery` | Photo grid of warehouse/operations, fully managed from the admin panel (ships with placeholder illustrations until real photos are uploaded) |
| `/careers` | Open roles and how to apply |
| `/careers/apply` | Job application form (resume upload) — works for a specific listed role or as a general application |
| `/faq` | Common questions grouped by category (ordering, delivery, products, partnerships, account), accordion UI |
| `/contact` | Two-column contact form (with subject dropdown) + contact info + map placeholder |
| `/search` | Site-wide search across Products, Principals, custom Pages and FAQs |
| `/privacy-policy`, `/terms-of-service` | Legal pages (admin-editable via the page builder — seeded with generic starter text, not legal advice) |
| `/sitemap.xml` | Auto-generated sitemap covering every static page, product and published custom page |

Secondary pages (Services, Quality, Gallery, Careers, FAQs) live under a **"More" dropdown** in
the main nav to keep the primary nav short — see `resources/views/components/layout.blade.php`.
A floating WhatsApp click-to-chat button (using the WhatsApp number in Settings) appears on every
public page.

## Data model

- `product_categories` / `products` — 8 therapeutic categories, ~26 products
- `principals` — manufacturer/principal partners shown on Home and `/principals` (**fictional demo
  names** — replace with your real principal agreements before launch, see note below)
- `testimonials`, `certifications`, `team_members` — content shown on Home/About/Quality pages
- `faqs` — grouped by category, shown on `/faq`
- `gallery_images` — photos shown on `/gallery`, manageable from the admin panel
- `content_items` — the repeatable cards on Services/Careers/Quality/About (services, "how it
  works" steps, job openings, quality standards, mission/vision/values), grouped by a `group` column
- `client_logos` — "Our Clients" trust-bar logos shown on the homepage
- `contact_messages` — every contact form submission is validated and stored here
- `newsletter_subscribers` — footer newsletter signup (`POST /newsletter`) stores emails here
- `job_applications` — Careers apply-form submissions, including uploaded resumes (stored on the
  **private** disk, downloadable only by an authenticated admin — never web-accessible by URL)
- `settings` — key/value store for all admin-editable site-wide text, branding and theme colors
- `nav_items`, `pages`, `page_sections` — the dynamic navigation and page-builder tables behind the
  admin CMS (see Admin Panel section below)

All content is seeded via `database/seeders/*` with real, company-specific copy — no Lorem Ipsum.
Seeded content is a starting point — once the site is running, everything below is editable from
the admin panel without touching code.

## Admin Panel (CMS)

The whole site is manageable from a built-in admin panel — no code changes or redeploys needed to
update content.

**Login:** `/admin/login`

**Default credentials** (created by `AdminUserSeeder`, runs with `php artisan migrate --seed`):

```
Email:    admin@salescareplusmzg.com
Password: AdminSCP@2026
```

⚠️ **Change this password immediately after your first login in production** — go to
`/admin/profile` while logged in and set a new password (current password required), no code or
tinker session needed.

**What the admin can manage:**

| Area | What it controls |
|---|---|
| **Maintenance Mode** | One checkbox in Site Settings shows a branded "we'll be right back" page to every visitor (503 status, so search engines don't deindex the site), with a custom message you control. You stay logged in as admin and can keep browsing/editing the live site while it's on — only the public sees the maintenance page |
| **Branding & Logo** | Upload/replace the site logo (shown in the header, footer and admin sidebar — falls back to a text wordmark if none is set) |
| **Theme Colors** | Pick a primary and accent brand color (native color pickers) — the entire site's color palette (11 shades of each, buttons, links, backgrounds, everywhere) regenerates instantly, no rebuild needed. This is the fastest way to re-skin the whole site for a different company/brand |
| **Site Settings** | Company name, short name, legal name, tagline, contact email/phone/WhatsApp, address, business hours, footer "about" blurb, homepage stats, coverage areas, social media links, a homepage hero video banner (upload a file or paste an `.mp4` URL), and an SEO social-share image used for Open Graph/Twitter link previews |
| **My Profile** | Update your own admin name, email and password |
| **Navigation** | Header menu, header "More" dropdown, and both footer link columns — add/remove/reorder links, point them at built-in pages, custom pages, or external URLs, and control which open in a new tab |
| **Pages** | Create brand-new pages with their own URL (e.g. `/our-story`), built from stackable sections — see full list below — each with its own heading, text, image/video upload, button, background style and **entrance animation** (fade, zoom, 3D tilt, 3D flip, or fade + float) |
| **Page Content** | The text/headings and repeatable cards on the built-in **Services, Careers, Quality and About** pages — service cards, "how it works" steps, job openings, quality standards, and mission/vision/values — all editable, nothing hardcoded |
| **Products / Categories** | Full catalog CRUD, including product photos — the public product page shows full specs in a table and the real uploaded photo (falls back to an illustration if none is set) |
| **Principals** | Manufacturer partners, including logos |
| **Our Clients** | A "trusted by" client logo strip shown on the homepage (and addable to any custom page) |
| **Testimonials, Certifications, Team Members, FAQs** | Full CRUD, including team photos |
| **Gallery** | Upload/replace/reorder photos shown on the public Gallery page |
| **Contact Messages** | View and manage every contact form submission |
| **Newsletter Subscribers** | View and remove footer newsletter signups |
| **Job Applications** | View submitted applications (per role or general), including a secure resume download — never accessible by direct URL |

**Every public page is now fully admin-editable** — Home, About, Services, Quality, Careers,
Principals, Catalog, Gallery, FAQ, Contact, plus unlimited custom Pages. None of them require a
code change to update copy, swap images, or restructure content for a new client. This includes
**Privacy Policy and Terms of Service** — they're ordinary rows in **Pages**, not special-cased, so
editing them (or adding more legal pages) works exactly like editing/adding any other page.

**Page builder section types:** Hero, Hero with Video Banner, Rich Text, Image + Text, Call to
Action banner, Card Grid, Image Gallery, Stats Row, Featured Quote, Team Members (pulled live from
the Team Members list), Testimonials (pulled live), an FAQ Accordion (pulled live from FAQs), and a
Client Logo Strip (pulled live from Our Clients) — the live-pull types stay in sync automatically
since they pull from the same data the rest of the site uses, rather than needing content pasted
twice.

Uploaded images/videos are stored in `storage/app/public` and served via the `public/storage`
symlink — run `php artisan storage:link` once after a fresh deploy if it isn't already linked.

Behind the scenes: site-wide text is stored in a `settings` key/value table and merged into
`config('company.*')` at boot (`app/Providers/SettingsServiceProvider.php`), so existing Blade
views didn't need to change — they just automatically pick up admin-edited values, falling back to
`config/company.php` defaults if a setting is empty. Theme colors work the same way: the two brand
hex values are expanded into full 50–950 shade ramps by `app/Support/ColorPalette.php` and injected
as CSS custom-property overrides in the page `<head>`, which is all Tailwind utility classes read
from — so no CSS rebuild is required when an admin changes the brand color.

### SEO & site features

- **Sitemap** at `/sitemap.xml`, auto-generated from every static page, product and published
  custom page — referenced from `public/robots.txt`.
- **Open Graph / Twitter Card meta tags + JSON-LD `LocalBusiness` schema** on every page, built from
  Site Settings (name, address, phone, email, social links) and the SEO social-share image, so links
  shared on WhatsApp/Facebook/Twitter show a proper preview card and Google can show rich snippets.
- **Canonical URLs** on every page.
- **WhatsApp floating chat button** on every public page, using the WhatsApp number from Settings.
- **Site-wide search** (`/search`) across Products, Principals, custom Pages and FAQs.
- **Spam protection** on the Contact, Newsletter and Careers-apply forms: an invisible honeypot
  field plus a minimum-time trap (rejects submissions faster than a human could type), and rate
  limiting (5 submissions/minute/IP) — no CAPTCHA or third-party service required. Both silently
  succeed from the bot's perspective so it doesn't know to retry differently.
- **Custom branded 404 page** instead of Laravel's default error page.
- **Lazy-loaded images** on galleries, catalogs and card grids for faster page loads.

### Reusing this codebase for a new client

This is the intended workflow if you're standing up a site for a different company on this same
codebase — deploy once, then do everything else from `/admin`, no code changes:

1. **Branding** — Site Settings: company name/short name/legal name/tagline, upload the new logo,
   pick the two brand colors, set contact details, business hours and the footer "about" blurb.
2. **Core content** — Products & Categories, Principals, Our Clients, Testimonials, Certifications,
   Team Members, FAQs, Gallery: clear or edit the seeded demo rows and add the new company's real
   data from each admin screen.
3. **Page Content** — update the Services/Careers/Quality/About text and card lists to match the
   new company's actual services, open roles, quality standards and story.
4. **Navigation & Pages** — adjust the menu, or add brand-new pages via the page builder, if the
   new company needs a different site structure.
5. **Admin access** — change the seeded admin password (or create additional admin users via
   `php artisan tinker`) before handing the site to the client.

Nothing in `resources/views`, `resources/css`, or `config/company.php` needs to be touched for a
routine rebrand — those only supply first-boot fallback values. A second company's site is a new
Laravel deployment (new `.env`/database) pointed at the *same* codebase, configured entirely from
the admin panel above.

## Local setup

```bash
cp .env.example .env
# Edit .env: set DB_DATABASE / DB_USERNAME / DB_PASSWORD for a MySQL database you've created,
# and update COMPANY_CONTACT_EMAIL / COMPANY_CONTACT_PHONE / COMPANY_WHATSAPP / COMPANY_ADDRESS
# with your real business details (these currently contain placeholders).

composer install
php artisan key:generate
php artisan migrate --seed

npm install
npm run build      # production assets
# or: npm run dev   # Vite dev server with hot reload

php artisan serve
```

Visit `http://127.0.0.1:8000`.

## Configuration

Company-wide details (name, tagline, contact info, business hours, stats, coverage areas, social
links, logo, theme colors) all live in the database and are editable from `/admin` — see the Admin
Panel section above. `config/company.php` (pulling initial contact details from `.env`:
`COMPANY_CONTACT_EMAIL`, `COMPANY_CONTACT_PHONE`, `COMPANY_WHATSAPP`, `COMPANY_ADDRESS`) only
supplies the fallback values used the first time the app boots, before `SettingSeeder` /
the admin panel populate the `settings` table — you generally shouldn't need to touch it after the
first deploy.

The base theme colors (teal primary, coral accent) are still defined as Tailwind design tokens in
`resources/css/app.css` (`--color-teal-*`, `--color-coral-*`) as the **compiled-CSS fallback** —
but in normal use, change colors from **Site Settings → Theme Colors** in the admin panel instead,
which overrides them at runtime for every visitor without a rebuild.

## What's intentionally out of scope

- **Real principal/manufacturer and client names**: `database/seeders/PrincipalSeeder.php` and
  `ClientLogoSeeder.php` use fictional names for demo purposes, so the site doesn't imply
  undisclosed business relationships with real companies. Replace with your actual partners/clients.
- **Real photography**: illustrations (`resources/views/components/illustrations/*`) stand in for
  product/warehouse photography and the Contact page map — swap in real photos/an embedded map
  when available (or upload real photos directly from the admin panel wherever an image field
  exists).
- **Contact/newsletter/job-application notifications**: submissions are saved to the database but
  no email/SMS notification is wired up yet — plug a `Mail` or `Notification` class into
  `ContactController::store()`, `NewsletterController::store()` or `JobApplicationController::store()`
  if you want an alert on every enquiry, subscription, or application.
- **Privacy Policy / Terms of Service content**: `database/seeders/LegalPageSeeder.php` seeds
  generic starter text so the pages aren't empty — this is placeholder boilerplate, not legal
  advice. Have a lawyer review and replace it before relying on it, especially if you run ads or
  operate under specific data-protection regulations.

## Ideas for further growth

Beyond what's built, a few additions worth considering as the company grows:

- **Blog / News & Insights** — health awareness articles or company announcements; helps SEO and
  positions the company as a knowledgeable local authority.
- **Order/quote request portal** — a lightweight authenticated area where partner pharmacies can
  view their account, request quotes, and track order history online instead of by phone/WhatsApp.
- **Downloadable resources** — a PDF price list / catalog download, and printable certificates on
  the Quality page.
- **Admin roles/permissions** — the current `is_admin` flag is all-or-nothing; a "Content Editor"
  role with narrower access would help once more staff need admin access.
- **Real testimonials with photos**, and a **case studies** page for larger institutional clients
  (hospitals) once you have a few strong examples to showcase.
- **True multi-tenancy** — today, reusing this for a second company means a separate deployment
  (own `.env`/database) pointed at the same codebase, which is simple and safe but means N
  deployments for N clients. If you're running many client sites, a shared install with a
  `tenants` table and per-request tenant resolution would let one codebase serve all of them from
  a single deploy — a bigger architectural step up from what's built here.
