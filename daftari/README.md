# Daftari

A subscription-based VAT invoicing & accounting SaaS platform for Saudi Arabia — built with
**Laravel 12 (PHP 8.2+)** and **MySQL** (SQLite for zero-setup local development). Bilingual
Arabic/English with full right-to-left layout, SAR pricing, and ZATCA-style QR codes on every
tax invoice.

> This product ships under its own original name and branding ("Daftari"). It was built as a
> comparable, independently-designed alternative in the Saudi VAT e-invoicing / accounting SaaS
> category — the code, copy, and design here are original and not copied from any existing
> commercial product.

The platform has three parts, all in this Laravel app:

1. **Public marketing website** — home, features, pricing (SAR, monthly/yearly), about, contact.
   Bilingual (English/Arabic) with full RTL layout support.
2. **User panel** (`/app`) — the software each subscribing company uses day to day: dashboard,
   clients, item catalog, VAT-compliant invoices (with line items, QR codes, and payment
   tracking), expenses, a VAT summary report, team management, billing/subscription, and company
   settings. Fully multi-tenant: every company only ever sees its own data.
3. **Admin panel** (`/admin`) — the platform owner's back office: MRR/revenue overview, company
   management (activate/suspend), subscription plan management, a platform-wide payment ledger,
   and admin user management.

## Tech stack

- Laravel 12, PHP 8.2+
- MySQL in production; SQLite out of the box for zero-setup local development/demo
- Blade + Tailwind CSS (compiled via Vite) — no external CDN dependency
- [`endroid/qr-code`](https://github.com/endroid/qr-code) (GD-based) to render the ZATCA-style
  QR code baked into every tax invoice

## Quick start (local development, SQLite)

```bash
cp .env.example .env
composer install
npm install && npm run build   # or `npm run dev` while developing
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed     # creates tables + seeds plans, admin, and (in local env) demo data
php artisan serve
```

Visit `http://localhost:8000`.

**Demo logins** (also shown on the login page):

| Role | Email | Password |
|---|---|---|
| Platform admin | `admin@daftari.local` | `Admin@12345` |
| Demo company owner | `owner@daftari.local` | `Demo@12345` |
| Demo accountant | `accountant@daftari.local` | `Demo@12345` |
| Demo salesperson | `sales@daftari.local` | `Demo@12345` |

The demo company ("Al Rashid Trading Co.") comes pre-loaded with a client, an item, a
partially-paid invoice, and an expense — so the user panel isn't empty on first login. Demo data
is only seeded automatically when `APP_ENV=local` (or `testing`); set `SEED_DEMO=true` in `.env`
to force it in another environment.

### Demo mode

The demo company is flagged `is_demo` and runs in a safe sandbox: deletions, real payment
processing, and real ZATCA submissions are all refused for it (a "DEMO MODE" banner is shown
throughout the app while logged into it), so it's safe to leave publicly reachable. Manage the
demo company's data with two documented Artisan commands, either of which is always safe to run
against a live database — both only ever touch the company flagged `is_demo`, never any real
customer's data:

```bash
php artisan demo:install          # create/refresh the demo company and all of its sample data
php artisan demo:install --fresh  # same, but wipe any existing demo data first
php artisan demo:reset            # permanently delete the demo company and everything in it
```

## Installation wizard (`/install`)

For a fresh deployment (a real server, not this local quick-start), visit `/install` in a
browser instead of hand-editing `.env` and running `artisan` commands yourself. It's a 6-step
guided setup: requirements check (PHP version/extensions, writable directories, `APP_KEY`) →
database connection (tested live before you can continue) → application settings (name, URL,
timezone, language, currency) → your first administrator account → installation (runs migrations,
seeds required reference data, sets up storage) → done.

Two things to do first: run `composer install && php artisan key:generate` (the wizard checks for
`APP_KEY` but won't generate one for you — see Step 1's own note on why) and make sure
`storage/`, `bootstrap/cache/`, and `.env` are writable by the web server user.

For security, `/install` refuses to run a second time once it completes — every route redirects
to the login page instead. If you ever need to run it again (a botched deploy, a server
migration), re-enable it from the server itself:

```bash
php artisan installer:enable
```

No database credentials or passwords are ever written to source control — the wizard only ever
writes to the gitignored `.env` file on disk.

## Running on MySQL (production)

1. Create a database and user in MySQL.
2. In `.env`, set:
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=daftari
   DB_USERNAME=daftari
   DB_PASSWORD=your-password
   ```
3. Run `php artisan migrate --seed` (drop `--seed` and instead run
   `php artisan db:seed --class=Database\\Seeders\\PlanSeeder --class=Database\\Seeders\\AdminSeeder`
   if you don't want demo data seeded in production — or just leave `APP_ENV=production`, which
   already skips the demo seeder by default).
4. `npm run build` to compile production assets, then serve `public/` with nginx/Apache + PHP-FPM
   as you would any Laravel app.

## Configuration (`.env`)

| Key | Purpose |
|---|---|
| `TRIAL_DAYS` | Length of the free trial offered at signup (default 14) |
| `DEFAULT_CURRENCY` | Currency new companies are set up with (default `SAR`) |
| `PAYMENT_GATEWAY` | Placeholder for a real Saudi payment gateway integration (see below) |
| `SEED_DEMO` | Set `true` to force-seed demo data outside the `local`/`testing` environments |

## Project structure

```
app/
  Http/Controllers/
    Site/       Marketing site (home, features, pricing, about, contact, legal)
    Auth/       Login / registration (registration also creates the company + trial subscription)
    User/       Company subscriber panel (/app/...)
    Admin/      Platform admin panel (/admin/...)
  Http/Middleware/
    SetLocale           Reads the session-stored EN/AR locale on every request
    EnsureCompanyActive Logs a user out if their company has been suspended
    EnsureRole           Route-level role gate (`role:owner`, `role:super_admin`)
  Models/
    Concerns/BelongsToCompany   Global scope + auto-fill for every tenant-scoped model
    Company, Plan, Subscription, Payment, Client, Item, Invoice, InvoiceItem,
    InvoicePayment, Expense, ExpenseCategory, User
  Services/ZatcaQrGenerator.php  Builds the ZATCA-style TLV payload and renders it as a QR PNG
routes/web.php   All route definitions
database/migrations, database/seeders
resources/views/
  layouts/    site / auth / app (user panel) / admin
  site/, auth/, user/, admin/   Blade views per area
lang/         en.json / ar.json — string-keyed translations, used via __('English text')
```

## Data model / multi-tenancy

Every subscriber is a **company**. A company has many **users** (an owner plus invited staff),
and owns its own **clients**, **items** (a reusable product/service catalog), **invoices**
(→ `invoice_items`, `invoice_payments`), and **expenses** (→ `expense_categories`). Every
tenant-scoped model uses the `BelongsToCompany` trait, which adds a global query scope filtering
to the logged-in user's `company_id` and auto-fills `company_id` on create — so a compromised or
guessed record ID from another company simply won't resolve (404), and validation rules that
reference other tenant-scoped records (`Rule::exists(...)->where('company_id', ...)`) are
explicitly scoped too. The **platform admin** (`role = super_admin`, no `company_id`) sits
outside any company and manages the platform itself: companies, plans, and payments.

Note: the `User` model deliberately does **not** use `BelongsToCompany` — applying that scope to
the very model Laravel's auth guard queries to resolve `Auth::user()` causes infinite recursion
(the scope's closure calls `Auth::user()`, which triggers a `User` query, which re-evaluates the
scope...). Team-member routes (`TeamController`) scope `User` queries to `company_id` manually
instead.

Billing is modeled with **plans** (Starter / Professional / Enterprise, monthly+yearly SAR
pricing) and **subscriptions** (a company can have a history of subscriptions as it
upgrades/downgrades); **payments** records each charge. `BillingController::upgrade()` is where
plan changes happen today, and it simulates a successful charge — see below for wiring up a real
gateway.

## ZATCA-style QR codes

Every tax invoice embeds a QR code (`App\Services\ZatcaQrGenerator`) built from the standard
TLV (tag-length-value) fields used by ZATCA Phase-1 simplified e-invoices: seller name, VAT
number, timestamp, invoice total, and VAT total — base64-encoded, then rendered as a scannable
PNG via `endroid/qr-code`. Full ZATCA **Phase 2** compliance (cryptographic stamps, XML/UBL
invoice format, real-time clearance with ZATCA's API) is a substantially larger integration and
is not implemented — see "What's out of scope" below.

## What's intentionally out of scope for this build

This build focuses on standing up the three-part platform (marketing site → subscription → user
panel; admin panel to run it) with the core invoicing workflow (client → item → invoice → VAT
report) fully working end to end, so it's a real foundation to extend rather than a mockup.
Notable gaps to be aware of before going to production:

- **Payment gateway**: `PAYMENT_GATEWAY=manual` is a stub. For Saudi Arabia, wire up
  [Moyasar](https://moyasar.com), [HyperPay](https://hyperpay.com), [PayTabs](https://paytabs.com),
  or [Tap](https://tap.company) in `BillingController::upgrade()` (currently it just records a
  "paid" payment immediately).
- **ZATCA Phase 2 e-invoicing**: see above — only the Phase-1-style QR code is implemented.
- **Email**: no transactional email (welcome email, invoice delivery, password reset) is wired
  up yet — plug in a provider (e.g. an SMTP relay) via Laravel's mail config. Team-member invites
  currently show the temporary password directly in the UI as a placeholder for "send this by
  email."
- **Password reset flow**: not implemented; an admin can only re-invite via the Team page.
- **PDF export**: invoices use a print-friendly view (`window.print()` → "Save as PDF" in the
  browser) rather than a server-rendered PDF library.
- **Legal pages**: Terms of Service and Privacy Policy are clearly-labeled placeholders — have
  them reviewed by legal counsel before launch.

## Security notes

- Passwords are hashed with `password_hash()` (bcrypt) via Laravel's `Hash` facade.
- All state-changing routes go through Laravel's CSRF middleware.
- Blade's `{{ }}` escaping is used throughout (no unescaped user input).
- All queries go through Eloquent/the query builder — no raw string interpolation of user input.
- Every tenant-scoped model is protected by the `BelongsToCompany` global scope (see above), and
  cross-tenant foreign keys in form input (e.g. `client_id`, `item_id` on an invoice) are
  re-validated against the current company with scoped `Rule::exists(...)` checks, not just
  route-model-binding.
