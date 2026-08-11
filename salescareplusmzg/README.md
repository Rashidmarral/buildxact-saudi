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
| `/gallery` | Illustrated overview of warehouse/operations (swap in real photos when available) |
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
- `contact_messages` — every contact form submission is validated and stored here
- `newsletter_subscribers` — footer newsletter signup (`POST /newsletter`) stores emails here

All content is seeded via `database/seeders/*` with real, company-specific copy — no Lorem Ipsum.

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
links) live in `config/company.php`, pulling contact details from `.env`
(`COMPANY_CONTACT_EMAIL`, `COMPANY_CONTACT_PHONE`, `COMPANY_WHATSAPP`, `COMPANY_ADDRESS`).
**Update these before going live** — they currently contain realistic Muzaffargarh-based
placeholders, not your real phone/email/address, and `config('company.stats')` /
`coverage_areas` are illustrative.

Theme colors (teal primary, coral accent) are defined as Tailwind design tokens in
`resources/css/app.css` (`--color-teal-*`, `--color-coral-*`) — change the hex values there to
retheme the entire site without touching any Blade view.

## What's intentionally out of scope

- **Real principal/manufacturer names**: `database/seeders/PrincipalSeeder.php` uses fictional
  pharmaceutical manufacturer names for demo purposes, so the site doesn't imply undisclosed
  business relationships with real companies. Replace with your actual principal agreements.
- **Real photography**: illustrations (`resources/views/components/illustrations/*`) stand in for
  product/warehouse photography and the Contact page map — swap in real photos/an embedded map
  when available.
- **Admin panel**: products/categories/principals/testimonials/certifications/team/FAQs are
  managed via the seeders (`database/seeders/`) for now. Add a simple authenticated `/admin` CRUD
  if the content needs to change without a deploy.
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
- **Admin panel** — CRUD for products, principals, FAQs and viewing contact/newsletter submissions
  without touching code or re-seeding.
- **Real testimonials with photos**, and a **case studies** page for larger institutional clients
  (hospitals) once you have a few strong examples to showcase.
