# Sales Care Plus MZG

Corporate portfolio website for **Sales Care Plus MZG**, a pharmaceutical distribution company
based in Muzaffargarh, Punjab, Pakistan. Built with **Laravel** (PHP) and **MySQL**, styled with
Tailwind CSS in a nature/green theme that reflects the reference brand
([salescareplus.org](https://salescareplus.org/)) while being fully re-themed with the client's
own identity, domain (`salescareplusmzg.com`) and content.

## Tech stack

- Laravel 12 (PHP 8.4+)
- MySQL (via `pdo_mysql`)
- Blade templates + Tailwind CSS v4 (compiled via Vite, no external CDN/font dependency)
- Plain vanilla JS for the mobile nav toggle — no frontend framework required

## Pages

| Route | Purpose |
|---|---|
| `/` | Home — hero, trust bar, about preview, product categories, featured products, certifications, testimonials, CTA |
| `/about` | Company story, mission/vision, values, leadership team |
| `/products` | Full product catalogue with category filter, search, and pagination |
| `/products/{slug}` | Individual product detail page |
| `/services` | Distribution services offered + "how it works" process |
| `/quality` | Certifications, licences and quality standards |
| `/gallery` | Visual overview of warehouse/operations (icon-based placeholders — swap for real photos) |
| `/careers` | Open roles and how to apply |
| `/contact` | Contact details + working contact form (stores submissions in the database) |

## Data model

- `product_categories` — 8 therapeutic categories (Analgesics, Antibiotics, Cardiovascular, Diabetic, Gastro, Respiratory, Vitamins & Supplements, Mother & Child Care)
- `products` — belongs to a category; name, generic name, pack size, sourcing note, featured flag
- `testimonials`, `certifications`, `team_members` — content shown on Home/About/Quality pages
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

Company-wide details (name, tagline, contact info, business hours, social links) live in
`config/company.php`, pulling contact details from `.env` (`COMPANY_CONTACT_EMAIL`,
`COMPANY_CONTACT_PHONE`, `COMPANY_WHATSAPP`, `COMPANY_ADDRESS`). **Update these before going live**
— they currently contain realistic Muzaffargarh-based placeholders, not your real phone/email/address.

## What's intentionally out of scope

- **Real photography**: the gallery and product cards use icon-based placeholders rather than
  stock or scraped images — drop real product/warehouse photos into `public/images` and update
  the relevant Blade views (`resources/views/pages/gallery.blade.php`,
  `resources/views/pages/products/*.blade.php`) when available.
- **Admin panel**: products/categories/testimonials/certifications/team are managed via the
  seeders (`database/seeders/`) for now. Add a simple authenticated `/admin` CRUD if the content
  needs to change without a deploy.
- **Contact form notifications**: submissions are saved to `contact_messages` but no email/SMS
  notification is wired up yet — plug a `Mail` or `Notification` class into
  `ContactController::store()` if you want an alert on every enquiry.
