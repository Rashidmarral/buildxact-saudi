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
| `/faq` | Common questions grouped by category (ordering, delivery, products, partnerships, account), accordion UI |
| `/contact` | Two-column contact form (with subject dropdown) + contact info + map placeholder |

Secondary pages (Services, Quality, Gallery, Careers, FAQs) live under a **"More" dropdown** in
the main nav to keep the primary nav short — see `resources/views/components/layout.blade.php`.

## Data model

- `product_categories` / `products` — 8 therapeutic categories, ~26 products
- `principals` — manufacturer/principal partners shown on Home and `/principals` (**fictional demo
  names** — replace with your real principal agreements before launch, see note below)
- `testimonials`, `certifications`, `team_members` — content shown on Home/About/Quality pages
- `faqs` — grouped by category, shown on `/faq`
- `gallery_images` — photos shown on `/gallery`, manageable from the admin panel
- `contact_messages` — every contact form submission is validated and stored here
- `newsletter_subscribers` — footer newsletter signup (`POST /newsletter`) stores emails here
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
| **Branding & Logo** | Upload/replace the site logo (shown in the header, footer and admin sidebar — falls back to a text wordmark if none is set) |
| **Theme Colors** | Pick a primary and accent brand color (native color pickers) — the entire site's color palette (11 shades of each, buttons, links, backgrounds, everywhere) regenerates instantly, no rebuild needed. This is the fastest way to re-skin the whole site for a different company/brand |
| **Site Settings** | Company name, short name, legal name, tagline, contact email/phone/WhatsApp, address, business hours, footer "about" blurb, homepage stats, coverage areas, social media links, and a homepage hero video banner (upload a file or paste an `.mp4` URL) |
| **My Profile** | Update your own admin name, email and password |
| **Navigation** | Header menu, header "More" dropdown, and both footer link columns — add/remove/reorder links, point them at built-in pages, custom pages, or external URLs, and control which open in a new tab |
| **Pages** | Create brand-new pages with their own URL (e.g. `/our-story`), built from stackable sections — see full list below — each with its own heading, text, image/video upload, button, background style and **entrance animation** (fade, zoom, 3D tilt, 3D flip, or fade + float) |
| **Products / Categories** | Full catalog CRUD, including product photos — the public product page shows full specs in a table and the real uploaded photo (falls back to an illustration if none is set) |
| **Principals** | Manufacturer partners, including logos |
| **Testimonials, Certifications, Team Members, FAQs** | Full CRUD, including team photos |
| **Gallery** | Upload/replace/reorder photos shown on the public Gallery page |
| **Contact Messages** | View and manage every contact form submission |
| **Newsletter Subscribers** | View and remove footer newsletter signups |

**Page builder section types:** Hero, Hero with Video Banner, Rich Text, Image + Text, Call to
Action banner, Card Grid, Image Gallery, Stats Row, Featured Quote, Team Members (pulled live from
the Team Members list), Testimonials (pulled live), and an FAQ Accordion (pulled live from FAQs) —
the last three stay in sync automatically since they pull from the same data the rest of the site
uses, rather than needing content pasted twice.

Uploaded images/videos are stored in `storage/app/public` and served via the `public/storage`
symlink — run `php artisan storage:link` once after a fresh deploy if it isn't already linked.

Behind the scenes: site-wide text is stored in a `settings` key/value table and merged into
`config('company.*')` at boot (`app/Providers/SettingsServiceProvider.php`), so existing Blade
views didn't need to change — they just automatically pick up admin-edited values, falling back to
`config/company.php` defaults if a setting is empty. Theme colors work the same way: the two brand
hex values are expanded into full 50–950 shade ramps by `app/Support/ColorPalette.php` and injected
as CSS custom-property overrides in the page `<head>`, which is all Tailwind utility classes read
from — so no CSS rebuild is required when an admin changes the brand color.

### Rebranding this site for a different company

Because branding, theme colors, contact info, navigation and every content section are database-
driven, turning this into a different company's site is mostly point-and-click:

1. Log in to `/admin`, open **Site Settings**, and update the company name/tagline/contact details,
   upload the new logo, and pick the two new brand colors.
2. Update or replace the seeded demo content (Products, Principals, Team, Testimonials, etc.) from
   their respective admin screens.
3. Adjust **Navigation** and **Pages** if the new company needs a different set of pages.

No Blade templates, CSS, or `config/company.php` edits are required for a rebrand — those are only
the *fallback* defaults used before the admin sets anything.

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

- **Real principal/manufacturer names**: `database/seeders/PrincipalSeeder.php` uses fictional
  pharmaceutical manufacturer names for demo purposes, so the site doesn't imply undisclosed
  business relationships with real companies. Replace with your actual principal agreements.
- **Real photography**: illustrations (`resources/views/components/illustrations/*`) stand in for
  product/warehouse photography and the Contact page map — swap in real photos/an embedded map
  when available (or upload real photos directly from the admin panel wherever an image field
  exists).
- **Contact/newsletter notifications**: submissions are saved to the database but no email/SMS
  notification is wired up yet — plug a `Mail` or `Notification` class into
  `ContactController::store()` / `NewsletterController::store()` if you want an alert on every
  enquiry or subscription.

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
