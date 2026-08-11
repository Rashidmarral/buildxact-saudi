# Sales Care Plus MZG

Corporate portfolio website for **Sales Care Plus MZG**, a pharmaceutical distribution company
headquartered in Muzaffargarh, Punjab, Pakistan. Built with **Laravel** (PHP) and **MySQL**,
styled with Tailwind CSS in a navy/sky-blue theme adapted from the client's reference brand
(a Multan-based distributor site named "SalesCare+") for their own identity, domain
(`salescareplusmzg.com`) and Muzaffargarh-based coverage area.

## Tech stack

- Laravel 12 (requires **PHP 8.2+** — deliberately pinned below the latest framework release so
  it runs on typical shared-hosting PHP 8.2 environments)
- MySQL (via `pdo_mysql`)
- Blade templates + Tailwind CSS v4 (compiled via Vite, no external CDN/font dependency)
- Plain vanilla JS for the mobile nav toggle, scroll-reveal animations and stat counters — no
  frontend framework required

## Pages

| Route | Purpose |
|---|---|
| `/` | Home — hero, stats bar, services, "how we work" process, trusted manufacturers teaser, coverage area, testimonials, CTA |
| `/about` | Company story, stats, quality checklist, certifications strip, mission/vision/values, leadership team |
| `/principals` | Manufacturer partners we're an authorized distributor for, with partnership stats and "why partner with us" |
| `/catalog` | Therapeutic categories, featured products, and the full searchable/filterable product catalog |
| `/catalog/{slug}` | Individual product detail page |
| `/contact` | Two-column contact form (with subject dropdown) + contact info + map placeholder — form submissions are stored in the database |

## Data model

- `product_categories` — 8 therapeutic categories (Analgesics, Antibiotics, Cardiovascular, Diabetic, Gastro, Respiratory, Vitamins & Supplements, Mother & Child Care)
- `products` — belongs to a category; name, generic name, pack size, sourcing note, featured flag
- `principals` — manufacturer/principal partners shown on Home and `/principals` (**fictional demo
  names** — replace with your real principal agreements before launch, see note below)
- `testimonials`, `certifications`, `team_members` — content shown on Home/About pages
- `contact_messages` — every contact form submission is validated and stored here

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

## What's intentionally out of scope

- **Real principal/manufacturer names**: `database/seeders/PrincipalSeeder.php` uses fictional
  pharmaceutical manufacturer names for demo purposes, so the site doesn't imply undisclosed
  business relationships with real companies. Replace with your actual principal agreements.
- **Real photography**: illustrations (`resources/views/components/illustrations/*`) stand in for
  product/warehouse photography and the Contact page map — swap in real photos/an embedded map
  when available.
- **Admin panel**: products/categories/principals/testimonials/certifications/team are managed via
  the seeders (`database/seeders/`) for now. Add a simple authenticated `/admin` CRUD if the
  content needs to change without a deploy.
- **Contact form notifications**: submissions are saved to `contact_messages` but no email/SMS
  notification is wired up yet — plug a `Mail` or `Notification` class into
  `ContactController::store()` if you want an alert on every enquiry.
