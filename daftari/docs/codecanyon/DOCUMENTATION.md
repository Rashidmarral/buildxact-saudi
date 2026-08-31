# Daftari — Documentation

A subscription-based VAT invoicing & accounting SaaS platform for Saudi Arabia, built on
Laravel 12. This document covers everything needed to install, configure, run, and customize
a purchased copy of Daftari.

## Table of contents

1. [Requirements](#1-requirements)
2. [Installation](#2-installation)
3. [Configuration](#3-configuration)
4. [Database](#4-database)
5. [Admin setup](#5-admin-setup)
6. [Company setup](#6-company-setup)
7. [Plans](#7-plans)
8. [Payments](#8-payments)
9. [Email](#9-email)
10. [Storage](#10-storage)
11. [ZATCA (e-invoicing compliance)](#11-zatca-e-invoicing-compliance)
12. [API](#12-api)
13. [Webhooks](#13-webhooks)
14. [Cron (scheduled tasks)](#14-cron-scheduled-tasks)
15. [Queue](#15-queue)
16. [Backup](#16-backup)
17. [Translation](#17-translation)
18. [RTL (right-to-left layout)](#18-rtl-right-to-left-layout)
19. [Customization](#19-customization)
20. [Troubleshooting](#20-troubleshooting)
21. [Updating](#21-updating)

---

## 1. Requirements

| | Requirement |
|---|---|
| PHP | **8.2 or newer** (`composer.json` requires `^8.2`, which Laravel 12 itself requires). Developed against this constraint and verified running here on PHP 8.4. |
| Laravel | **12.x** (built and tested against Laravel 12.67). |
| Database | **MySQL 8.0+ or MariaDB 10.6+** is the intended production target (all foreign keys, JSON columns, and generated migrations use syntax compatible with both). SQLite is also supported and is what local development and this project's own automated test suite run on. |
| Node.js | **18+** — only needed if you'll compile front-end assets yourself (the `-source` package, or after customizing Blade/CSS/JS). Not needed at runtime; the `-full` package ships pre-compiled assets in `public/build/`. |
| Web server | Apache with `mod_rewrite` (a `public/.htaccess` is included) or Nginx (see [Installation](#2-installation) for a sample server block). |

**Required PHP extensions**: `pdo`, `pdo_mysql`, `openssl`, `mbstring`, `dom`, `xml`, `gd`,
`curl`, `fileinfo`, `bcmath`, `ctype`, `json`, `tokenizer`. The installation wizard's Step 1
checks every one of these automatically before letting you continue — see
[Installation](#2-installation).

> **A note on compatibility claims.** This documentation states only what has actually been
> exercised: the full PHP/Laravel test suite runs against SQLite in the development
> environment this product was built in. MySQL is the intended and supported production
> database (the schema, seeders, and every migration are written for it), but running the full
> application against a real MySQL server has not been exercised in that same automated way —
> **test your specific MySQL/MariaDB version end to end before going live** (see
> [Troubleshooting](#20-troubleshooting) and the "items to manually test" list that ships
> alongside this documentation).

---

## 2. Installation

### Option A — the installation wizard (recommended)

1. Upload the contents of the package to your server so that your domain's document root
   points at the `public/` directory (not the project root).
2. Make sure `storage/`, `bootstrap/cache/`, and the project root (for writing `.env`) are
   writable by your web server user.
3. Run `composer install --no-dev` **only if** you used the `-source` package — the `-full`
   package already includes a production `vendor/` directory.
4. Run `php artisan key:generate` once, from a terminal (SSH or your host's "Run command"
   tool). The wizard checks for this key but deliberately does not generate one itself — see
   Step 1 of the wizard for why.
5. Visit `https://your-domain.com/install` in a browser and follow the 6 steps:
   1. **Requirements** — a live pass/warning/fail check of everything in [Requirements](#1-requirements).
   2. **Database** — host, port, database name, username, password; tested live before you can continue.
   3. **Application** — name, URL, timezone, language, currency.
   4. **Admin** — the name/email/password for your first platform administrator.
   5. **Installation** — runs migrations, seeds required reference data (currencies, default
      subscription plans, admin permission roles), and sets up file storage, all in one step.
   6. **Complete** — shows your admin panel URL and a login button.
6. For security, `/install` refuses to run again once it completes. If you ever need to
   re-run it, use `php artisan installer:enable` from the server (see
   [Troubleshooting](#20-troubleshooting)).

### Option B — manual installation (for scripted/CLI deployments)

```bash
composer install --no-dev --optimize-autoloader   # -source package only
cp .env.example .env
php artisan key:generate
# Edit .env: DB_*, APP_URL, APP_ENV=production, APP_DEBUG=false, MAIL_*, etc.
php artisan migrate --force
php artisan db:seed --class=Database\\Seeders\\CurrencySeeder --force
php artisan db:seed --class=Database\\Seeders\\PlanSeeder --force
php artisan db:seed --class=Database\\Seeders\\AdminRoleSeeder --force
php artisan storage:link
php artisan tinker   # create your own super_admin User row, or temporarily
                      # run AdminSeeder and change its password immediately
```

`npm run build` (only for the `-source` package) compiles `resources/css`/`resources/js` into
`public/build/`.

### Nginx sample server block

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/daftari/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;   # match your installed PHP-FPM version
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

## 3. Configuration

All environment-specific values live in `.env` (never in source — see `.env.example` for the
full template with every key documented inline). The most commonly changed:

| Key | Purpose |
|---|---|
| `APP_NAME`, `APP_URL`, `APP_TIMEZONE`, `APP_LOCALE` | Set by the installation wizard's Step 3; editable later in Admin → Platform Settings. |
| `APP_ENV`, `APP_DEBUG` | Must be `production` / `false` on a live server. The wizard sets these for you. |
| `DB_*` | Database connection — set by the wizard's Step 2. |
| `TRIAL_DAYS` | Default free-trial length for new companies (default 14). |
| `DEFAULT_CURRENCY` | Default currency code for new installs (default `SAR`). |
| `PAYMENT_GATEWAY` | Placeholder default when no real gateway is configured yet (`manual`). |
| `MAIL_*` | See [Email](#9-email). |
| `AWS_*` | Only needed if using S3-compatible storage — see [Storage](#10-storage). |
| `SENTRY_LARAVEL_DSN` | Leave empty to disable error monitoring entirely (the default). Set to your own Sentry DSN to enable it. |
| `DAFTARI_BILLING_*` | Your own platform operator's legal/billing identity, shown on the subscription receipts issued to companies for their plan payments — set your own values here, not the placeholders shipped in `.env.example`. |

Runtime, database-backed settings (as opposed to `.env` values) are managed from
**Admin → Platform Settings** once you're logged in — platform name/logo, default timezone,
maintenance mode, and more.

---

## 4. Database

- **Engine**: MySQL 8.0+/MariaDB 10.6+ in production; SQLite works for local development.
- **Migrations**: `database/migrations/` — run with `php artisan migrate` (the installer does
  this for you). Every migration is idempotent to re-run safely on a fresh database.
- **Seeders** (`database/seeders/`):
  - `CurrencySeeder`, `PlanSeeder`, `AdminRoleSeeder` — reference data every install needs;
    run automatically by the installer, or manually via `php artisan db:seed --class=...`.
  - `AdminSeeder` — creates a fallback platform admin (`admin@daftari.local` /
    `Admin@12345`) for quick local testing. **Change or remove this account before going
    live** — the installer's own Step 4 creates a proper first administrator instead, so you
    don't need this seeder at all for a real deployment.
  - `DemoSeeder` — builds a complete, self-contained sample company ("Al Rashid Trading Co.")
    for demos. Install/remove it any time with `php artisan demo:install` /
    `php artisan demo:reset` (see [Customization](#19-customization) and Module 23's demo-mode
    guarantees — deletions, real payments, and real ZATCA submissions are all refused for this
    company). Never runs automatically outside `APP_ENV=local`/`testing` unless you set
    `SEED_DEMO=true`.
  - `RealCompanySeeder` — **not called by anything by default.** It contains one specific
    operator's real company records. Ignore it unless you specifically know why it's there.
- **Resetting**: `php artisan migrate:fresh --seed` rebuilds the schema and reseeds reference
  data from scratch. This deletes all data — never run it against a live database with real
  companies in it.

---

## 5. Admin setup

The platform administrator (`role = super_admin`) manages the whole platform — companies,
subscription plans, payments, ZATCA oversight, support tickets, and platform settings — from
`/admin`. It is a separate account type from a company's own users (`company_id` is `null` for
admins).

- **Create your first admin**: via the installation wizard's Step 4 (recommended), or by
  changing `AdminSeeder`'s password immediately after seeding it.
- **Add more admins**: Admin → Team (or Admins) → Add Admin — choose `super_admin` (full
  access) or `admin_staff` (permission-scoped via Admin Roles).
- **Admin roles**: `AdminRoleSeeder` creates a starter set of permission groups for
  `admin_staff` accounts (support, billing, etc.) — manage these under Admin → Roles.

---

## 6. Company setup

A "company" is one paying tenant — every table with business data is scoped to a
`company_id`, and no company can ever see another's data (enforced by a global Eloquent scope,
independent of anything in the UI).

- **Self-service signup**: `/register` — collects company name, VAT/CR numbers, address,
  contact details, and creates the company's first user as its `owner`. Gated behind
  `registration.open` middleware — disable public signup from Admin → Platform Settings if
  you're running this as an internal tool rather than an open SaaS.
- **Admin-created companies**: Admin → Companies → Add Company, for onboarding a customer
  yourself (sales-assisted signup, migrations from another system, etc.).
- **Per-company settings**: each company manages its own VAT number, address, branch/warehouse
  structure, invoice numbering, ZATCA onboarding, bank accounts, and team from its own
  Settings page — none of this is shared across companies.

---

## 7. Plans

Subscription plans are rows in the `plans` table (`database/seeders/PlanSeeder.php` seeds the
defaults), each carrying:

- **Pricing**: monthly/yearly price, plus an optional "original price" pair for showing a
  discount on the pricing page.
- **Limits**: `max_users`, `max_invoices_per_month`, `max_customers`, `max_suppliers`,
  `max_items`, `max_warehouses`, `max_branches`, `max_bank_accounts`, `max_invoice_templates`,
  `max_storage_mb`, `max_api_calls_per_month`.
- **Feature flags**: `has_recurring_invoices`, `has_quotations`, `has_stamps`,
  `has_financial_statements`, `has_vat_return_report`, `has_cost_centers`,
  `has_purchase_orders`, `has_debit_notes`, `has_roles_permissions`, `has_zatca_phase2`,
  `has_api`, `has_whatsapp`.

Manage plans from Admin → Plans (create/edit/archive, set which plan is the public signup
default). A company that exceeds a limit or lacks a feature flag is blocked at the point of
use with an upgrade prompt — this is enforced server-side, not just hidden in the UI.

---

## 8. Payments

Two ways a company can pay its own subscription invoice:

1. **Manual bank transfer** — the company sees your bank details and uploads/references a
   transfer; an admin confirms it from Admin → Payments.
2. **Online gateways** — `PaymentGateway` rows (configured under Admin → Payment Gateway
   Settings) support **Moyasar**, **HyperPay**, **Tap**, and **PayTabs** out of the box, each
   via its own driver in `app/Services/Payments/Drivers/`. Gateway credentials are stored
   **encrypted** in the database (`credentials` is an `encrypted:array` cast column, and never
   serialized back to the frontend) — never hardcode a real key anywhere in source.

All four gateways post back to a single inbound endpoint, `/payments/webhook/{provider}`, which
verifies each provider's own signature scheme before trusting the payload (see each driver's
`verifyWebhook()`). This is a different concept from the outbound, per-company webhooks
described in [Webhooks](#13-webhooks) below.

Adding a fifth gateway means implementing `PaymentGatewayDriver` and registering it in
`PaymentGatewayManager` — see the existing four drivers as a template.

---

## 9. Email

`MAIL_MAILER=log` by default (`.env.example`) — outgoing mail writes to
`storage/logs/laravel.log` instead of actually sending, so nothing breaks before you configure
a real mailer. Set real SMTP (or Postmark/SES/Resend — see `config/services.php`) credentials
in `.env` before relying on any of the following in production:

- Welcome email on signup, team invite emails, password reset
- Invoice/quotation emails (with a PDF attached)
- Payment receipt emails
- Overdue-invoice reminders (scheduled — see [Cron](#14-cron-scheduled-tasks))
- Subscription expiring-soon reminders

None of the built-in `Mailable` classes implement `ShouldQueue`, so they send synchronously
within the request/command that triggers them — the [queue](#15-queue) is used for other
background work (ZATCA submission, outbound webhook delivery), not mail, unless you add
queueing yourself for a high-volume mailer.

---

## 10. Storage

`FILESYSTEM_DISK=local` by default — uploaded files (company logos, invoice attachments,
platform branding assets, invoice-template letterheads) are written to `storage/app/public`
and served through the `public/storage` symlink (`php artisan storage:link`, run automatically
by the installer's Step 5).

For S3-compatible storage (AWS S3, DigitalOcean Spaces, Cloudflare R2, MinIO), set
`FILESYSTEM_DISK=s3` and the `AWS_*` keys in `.env` (`config/filesystems.php`'s `s3` disk is
already wired to read every one of them — no code changes needed).

---

## 11. ZATCA (e-invoicing compliance)

Daftari implements ZATCA Phase 2 (Fatoora) e-invoicing: XML generation (UBL), hash chaining,
XAdES digital signing, QR code embedding, and submission to ZATCA's clearance/reporting
endpoints.

- **Environments**: `developer` (ZATCA's sandbox, the default for a new company), `simulation`,
  and `production` — set per company under its own Settings → ZATCA tab.
- **Onboarding flow** (per company, from its own Settings → ZATCA page): generate a CSR →
  request a compliance CSID (OTP from the Fatoora Portal) → run the compliance check → request
  the production CSID. Every step's readiness is checked up front (VAT number, address fields,
  etc.) so a rejected OTP or a technically-invalid CSR doesn't surprise you several steps in.
- **Sync**: invoices/credit notes submit automatically on a schedule
  (`zatca:sync-invoices --frequency=hourly|daily|weekly`, configurable per company — see
  [Cron](#14-cron-scheduled-tasks)) or on demand from the invoice/credit-note screen.
- **Gating**: ZATCA Phase 2 is a plan feature (`has_zatca_phase2`) — a company on a plan
  without it can't onboard or sync, matching [Plans](#7-plans) above.
- **Demo safety**: a company flagged `is_demo` (see `demo:install` in [Database](#4-database))
  never actually submits to ZATCA even if "onboarded" — every submission attempt is
  short-circuited with a clear "demo mode" log entry instead, so a public demo can never spend
  real ZATCA sandbox quota or, more importantly, never accidentally clears/reports a fake
  document as if it were real.

**This module has not been certified by ZATCA** — it implements the published technical
specification, but going live with real e-invoicing is the operator's own responsibility to
verify against ZATCA's current requirements before relying on it for VAT compliance.

---

## 12. API

A versioned REST API under `/api/v1`, authenticated with Laravel Sanctum personal access
tokens (Settings → API Tokens, per company user). Each token carries `read` and optionally
`write` abilities and can never do more than its owning user could already do in the web UI
(every endpoint enforces the same `permission:` middleware as its web-panel equivalent).

Available today: `GET /api/v1/me`, `GET/POST /api/v1/clients`, `GET /api/v1/clients/{id}`,
`GET /api/v1/items`, `GET /api/v1/items/{id}`, `GET /api/v1/invoices`,
`GET /api/v1/invoices/{id}`. API access itself is a plan feature (`has_api`) and every call
counts against the company's `max_api_calls_per_month` limit.

---

## 13. Webhooks

Distinct from the inbound payment-gateway webhook in [Payments](#8-payments) — this is an
**outbound** webhook system a company configures for itself under Settings → Webhooks: create
an endpoint URL, get a signing secret, toggle it on/off, send a test delivery, or regenerate
the secret. Deliveries are queued (`SendWebhookDelivery` job) rather than sent inline — see
[Queue](#15-queue).

---

## 14. Cron (scheduled tasks)

Add exactly one cron entry on your server, pointing at Laravel's own scheduler (this is the
only line you ever need to add manually — every scheduled task itself is already registered
in `routes/console.php`):

```
* * * * * cd /path/to/daftari && php artisan schedule:run >> /dev/null 2>&1
```

What runs on that schedule: ZATCA sync at each company's chosen frequency, overdue-invoice
reminders, recurring invoice/expense generation, subscription-expiring reminders,
cancelled-subscription expiry, subscription lifecycle rules, asset depreciation, low-stock
checks, and a once-a-minute heartbeat the "System health" panel on the Admin Dashboard uses
to show whether your cron entry is actually configured — a real, useful way to confirm this
step worked, rather than just hoping it's running.

---

## 15. Queue

`QUEUE_CONNECTION=database` by default — queued jobs (ZATCA async submission from the
interactive "sync now" action, and outbound webhook delivery — see [Webhooks](#13-webhooks))
are written to the `jobs` table and need a worker process to actually run:

```bash
php artisan queue:work --tries=3
```

Run this under a process supervisor (Supervisor, systemd, or your host's process manager) so
it restarts if it crashes and survives deploys. A minimal Supervisor config:

```ini
[program:daftari-queue]
command=php /path/to/daftari/artisan queue:work --tries=3 --sleep=3
autostart=true
autorestart=true
numprocs=1
user=www-data
```

Without a running worker, queued jobs simply sit unprocessed in the `jobs` table until one
starts — nothing is lost, but ZATCA sync-now clicks and webhook deliveries won't complete.

---

## 16. Backup

No backup package is bundled — back up the same two things any Laravel app needs, on
whatever schedule your data's importance calls for:

1. **The database** — `mysqldump` (or your host's managed-database backup feature).
2. **`storage/app/public`** (uploaded logos, attachments, letterheads) — `.env` and the
   application code itself are recoverable from your own deployment process, but
   user-uploaded files are not.

A simple daily cron example:

```bash
0 2 * * * mysqldump -u USER -pPASSWORD daftari | gzip > /backups/daftari-$(date +\%F).sql.gz
0 2 * * * tar -czf /backups/daftari-storage-$(date +\%F).tar.gz /path/to/daftari/storage/app/public
```

If you want a more complete, restore-tested backup solution, `spatie/laravel-backup` is a
well-established package that installs cleanly on top of Laravel 12 — it is not included here,
so as not to impose a specific backup destination/retention policy on every buyer.

---

## 17. Translation

Daftari ships English and Arabic. Every string in the codebase is wrapped in `__('...')`, and
English is the literal source text — `lang/ar.json` supplies the Arabic translation for each
one (Laravel's default-locale fallback means no separate `lang/en.json` is needed; the English
string *is* the key).

To add a third language:

1. Add it to `App\Support\Locales::LIST` (`app/Support/Locales.php`) with its label, native
   name, and text direction (`ltr`/`rtl`).
2. Create `lang/{code}.json` with the same keys as `lang/ar.json`, translated.
3. It appears automatically everywhere the app already reads from `Locales::LIST` — the
   installer's Step 3 language picker, the login-page locale switcher, and per-company Settings.

To translate a string you've added yourself, just wrap it in `__('Your string')` and add the
matching key to `lang/ar.json` (and any other language file) — no code changes needed beyond
that.

---

## 18. RTL (right-to-left layout)

Arabic renders fully right-to-left, driven by one source of truth
(`App\Support\Locales::dir()`) rather than scattered `=== 'ar'` checks: every layout sets
`<html dir="{{ Locales::dir(app()->getLocale()) }}">`, and the CSS throughout uses Tailwind's
logical properties (`ms-`/`me-`/`ps-`/`pe-`/`start-`/`end-` instead of `ml-`/`mr-`/`left-`/
`right-`), so the entire UI mirrors correctly without a separate RTL stylesheet. This applies
uniformly to the marketing site, the company user panel, and the admin panel.

---

## 19. Customization

- **Branding**: Admin → Platform Settings → Branding — platform name, logo (login page, PDF
  documents, transactional emails separately), primary/secondary/sidebar colors, favicon.
  Applies without touching code.
- **Invoice/quotation/bill layouts**: Settings → Invoice Templates — 5 built-in print layouts,
  a letterhead upload, and per-document-type defaults, all data-driven (no Blade editing
  required for the common cases).
- **Views**: `resources/views/` — organized by area (`site/` marketing pages, `user/` the
  company panel, `admin/` the admin panel, `installer/` the setup wizard, `portal/` the
  client-facing self-service portal). Standard Blade — no build step needed for view-only
  changes if you're running the `-full` package.
- **Styling**: `resources/css/app.css` + `tailwind.config.js` — requires `npm run build`
  (or `npm run dev` while iterating) after any change.
- **Sample/demo data**: `database/seeders/DemoSeeder.php`, wrapped by
  `php artisan demo:install` / `demo:reset` — edit this seeder to change what a fresh demo
  company looks like.
- **Adding a payment gateway or ZATCA-adjacent integration**: see [Payments](#8-payments)'s
  driver pattern — every integration point in this app is written as a small, replaceable
  service class rather than inlined into controllers, specifically so this kind of extension
  doesn't require touching unrelated code.

---

## 20. Troubleshooting

| Symptom | Likely cause / fix |
|---|---|
| Blank page or 500 error immediately after upload | `APP_KEY` missing — run `php artisan key:generate`. Check `storage/logs/laravel.log` for the real error once `APP_DEBUG=true` temporarily (never leave it on in production). |
| `/install` says a directory isn't writable | `chmod`/`chown` `storage/`, `bootstrap/cache/`, and the project root (for `.env`) to your web server user. |
| `/install` redirects straight to the login page | The app is already installed (`storage/app/installed.lock` exists). Run `php artisan installer:enable` from the server if you genuinely need to reinstall — this is deliberate, not a bug (see [Installation](#2-installation)). |
| Uploaded logos/attachments 404 | Storage symlink missing — `php artisan storage:link`. |
| Scheduled tasks (ZATCA sync, reminders) never run | The single cron entry from [Cron](#14-cron-scheduled-tasks) isn't installed, or is pointing at the wrong PHP binary/path. Check the "System health" panel on the Admin Dashboard — it shows the last time the scheduler actually ran. |
| "Sync now" / webhook deliveries never complete | No queue worker running — see [Queue](#15-queue). |
| ZATCA CSR/CSID requests fail | Confirm the company's `zatca_environment` matches the credentials you're using (developer/simulation/production are separate ZATCA endpoints with separate onboarding), and that the company's National Address fields are complete — the onboarding page's readiness checklist tells you exactly what's missing before you submit. |
| Emails never arrive | `MAIL_MAILER=log` is still the active mailer (the default) — check `storage/logs/laravel.log`, then configure real SMTP credentials. |
| A company hits a plan limit unexpectedly | Check Admin → Companies → (company) → plan overrides — an admin can grant one company a limit above its plan's default without changing the plan itself. |

---

## 21. Updating

There is no built-in auto-updater. To apply a new release:

1. **Back up first** — database and `storage/app/public` (see [Backup](#16-backup)).
2. Put the site in maintenance mode: `php artisan down`.
3. Replace application files with the new release **except**: your own `.env`, and anything
   under `storage/` (uploaded files, logs) — never overwrite these with the release package's
   versions.
4. `composer install --no-dev --optimize-autoloader` (`-source` package only — the `-full`
   package's `vendor/` is already current).
5. `php artisan migrate --force` — every migration in this project is additive/idempotent, so
   this is safe to run even if nothing changed for your version.
6. `php artisan config:clear && php artisan view:clear && php artisan cache:clear`.
7. `npm run build` if you customized front-end assets (`-source` package or any Blade/CSS/JS
   changes you've made).
8. `php artisan up`.

Check the changelog that ships with your specific release for anything version-specific beyond
this general procedure.
