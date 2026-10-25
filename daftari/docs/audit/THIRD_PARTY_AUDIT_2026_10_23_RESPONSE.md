# Response to Daftari System Audit (23 October 2026)

Tracks remediation of the 38 findings in the uploaded third-party review (`Daftari_System_Audit_2026-10-23.pdf`: 1 Critical, 25 High, 10 Medium, 2 Low). That report was a **source review only** — no PHP test run, no live provider/ZATCA acceptance, no database-concurrency run — so every finding below was re-verified directly against this codebase before any fix was written, the same discipline used throughout this engagement. Fixes land in delivery batches (A–H); this file is updated as each batch lands. Nothing here claims a finding is "certified fixed" beyond what the stated test coverage actually proves — acceptance checks the original report calls out as "not yet executed" (live ZATCA/payment-gateway sandboxes, true multi-worker MySQL concurrency, browser end-to-end) remain genuinely outstanding unless a batch's notes say otherwise.

## Status legend

- **Fixed** — code changed, regression test added, full suite verified green (same 2 pre-existing/unrelated failures as every run this entire engagement, no new ones).
- **Corrected** — the finding itself was inaccurate on re-verification; noted with the actual, narrower state of the code.
- **Open** — not yet addressed in this response.

## Batch A — Auth/account-recovery safety

| ID | Severity | Finding | Status |
|---|---|---|---|
| SEC-01 | Critical | Password-reset tokens disclosed to the requester when log mail is enabled | **Fixed** |
| SEC-02 | High | The password-reset callback suppresses the normal email notification | **Fixed** |
| SEC-03 | High | Private file serving lacks explicit tenant and document authorization | **Fixed** |
| SEC-04 | High | S3 mode marks the shared business-document disk public | **Fixed** |
| OPS-01 | Medium | Advertised S3 storage adapter is absent from the dependency lock | **Fixed** |
| SEC-09 | Medium | Second-factor and magic-link consumption are not atomic | **Fixed** |
| UX-01 | Low | Company favicon uploads are unreachable through the file allowlist | **Fixed** |

### SEC-01 / SEC-02 — Password-reset token disclosure and suppressed notification

**Confirmed exactly as reported.** `AuthController::sendResetLink()` passed a callback to `PasswordBroker::sendResetLink()` that only captured the reset URL. Laravel's `PasswordBroker::sendResetLink()` returns immediately after calling a supplied callback (`vendor/laravel/framework/.../PasswordBroker.php:100-101`) — so `$user->sendPasswordResetNotification($token)` never ran (SEC-02). Combined with exposing that captured URL to the browser whenever `config('mail.default') === 'log'` — Laravel's own out-of-the-box `MAIL_MAILER` default, not something unique to local dev — any unauthenticated requester could take over any account with zero access to the victim's mailbox, on a production deployment that simply never got SMTP configured.

**Fix:** the callback (and the token exposure it enables) now only exists when `app()->environment(['local', 'testing'])`. In every other environment, no callback is passed, so Laravel's default path always runs and the real notification always sends, regardless of mail configuration. Even in local/testing, the callback now explicitly sends the real notification too, so SEC-02 is closed everywhere, not just in production.

**Files:** `app/Http/Controllers/Auth/AuthController.php`
**Tests:** `tests/Feature/PasswordResetTokenDisclosureTest.php` (4 tests — production sends the real notification and never exposes the token regardless of mail driver, unknown emails get the same generic response, local/testing still gets the dev convenience link and still sends the real notification).

### SEC-03 — Private file serving authorization gap

**Confirmed, and found to be worse than the evidence cited.** The report named `FileServeController`'s reliance on `BelongsToCompany`'s scope. Direct inspection of that trait found the actual root cause: `if (Auth::check() && Auth::user()->company_id) { $builder->where(...); }` — when an authenticated user has **no** `company_id`, the scope applied **no constraint at all**, not a restrictive one. Three real roles on the same `users` table/guard can have `company_id = null`: `super_admin`, `admin_staff`, `partner`. Any of them could download any company's private attachment (receipts, bank statements, CR/VAT certificates) by path, with zero tenant check — not limited to an intentional admin-review flow, just an ordinary authenticated session.

**Fix, two layers, arrived at in two iterations:**
1. **Root cause** (`app/Models/Concerns/BelongsToCompany.php`): a tenantless authenticated user now gets `whereRaw('1 = 0')` by default — sees nothing — instead of an unscoped query. `partner` (and any future tenantless role) gets exactly this: the secure default, with no exception.
2. **super_admin/admin_staff are deliberately exempted from that default, at the scope itself.** First attempt: keep the deny-by-default rule for every tenantless account and only restore admin visibility for implicit route-model binding (`resolveRouteBinding()`). A full-suite run after that first attempt still showed ~33 failing Admin tests — listing pages, relation loads (`$company->subscription`-style accessors), and other ordinary queries across `CompanyManagementTest`, `PaymentManagementTest`, `SubscriptionLifecycleTest`, `TicketManagementTest`, `ZatcaManagementTest`, `ModuleMarketplaceTest` and others all broke, proving admin controllers don't consistently call `withoutGlobalScopes()` at every site — many simply relied on the old no-op-for-tenantless-users behavior everywhere, not just at route binding. Rather than hunt down and patch every individual admin call site (large surface, easy to miss one), the exemption moved into the scope closure itself: when `$user->isSuperAdmin() || $user->isAdminStaff()`, the scope applies no constraint at all, for every query shape — matching what the `/admin/*` route group's own `role:super_admin,admin_staff` middleware already trusts those two roles with, before any controller even runs. `partner` was never covered by that route gate and gets no exemption here either.
3. **Defense in depth, FileServeController specifically**: the route sits outside the normal auth/company-membership/active-company middleware group (a bare top-level route), so `Auth::check()` alone used to be the only gate. Added explicit `$user->company_id` and `$user->status === 'active'` checks, plus an explicit `where('company_id', $user->company_id)` on the Attachment/Item lookup — this denies super_admin/admin_staff here too (the admin panel has no legitimate reason to serve a tenant's private attachment through this specific route), independent of and unaffected by the scope-level exemption above.

**Files:** `app/Models/Concerns/BelongsToCompany.php`, `app/Http/Controllers/FileServeController.php`
**Tests:** `tests/Feature/TenantlessAccountCannotReachCompanyDataTest.php` (10 tests — super_admin/admin_staff/partner all denied a cross-company attachment via FileServeController regardless of the scope exemption; a suspended user denied their own company's attachment; an active user unaffected; a partner account sees zero rows from a tenant-scoped model by default; a super_admin/admin_staff sees every company's rows by default; a public favicon still reachable by a guest; super_admin implicit route binding works; partner implicit route binding stays denied). Full application suite re-verified green after both iterations of this fix (1813 → 1815 passed, same 2 pre-existing/unrelated failures) — this was the highest-blast-radius change in this entire response, the only one needing two full-suite runs to land correctly, and the only one needing a full-suite confirmation before committing at all rather than just a targeted regression sweep.

### SEC-04 / OPS-01 — S3 public disk and missing dependency

**Confirmed.** `AppServiceProvider::configureStorageDriver()` set `'visibility' => 'public'` on the S3-backed `public` disk — the *same* disk private business attachments are uploaded to. On a bucket that honors object ACLs, this gives every uploaded object a public-read ACL by default, making its direct S3 URL fetchable with zero authorization — completely bypassing the FileServeController fix above. `composer.lock` also genuinely lacked `league/flysystem-aws-s3-v3` (the earlier matches were Laravel's own `suggest` block, not an installed package) — switching to S3 on a clean install would fail at runtime.

**Fix:** `'visibility' => 'private'`. `FileServeController` streams objects server-side using the app's own stored S3 credentials regardless of object ACL, so authorized access is completely unaffected — only the public-without-auth path is removed. Added `league/flysystem-aws-s3-v3` (`^3.25`) via `composer require`, which resolved cleanly (verified: only 5 packages added — the adapter plus its real AWS SDK dependencies — nothing else in `composer.lock` changed).

**Files:** `app/Providers/AppServiceProvider.php`, `composer.json`, `composer.lock`
**Tests:** `tests/Feature/S3StorageVisibilityTest.php` (3 tests — S3 mode uses private visibility, an unconfigured install stays on the local disk, S3 mode with missing credentials falls back to local instead of a broken config).

**Not done:** the report's secondary note ("treat storage write failures as errors") — `'throw' => false` is deliberate, pre-existing, and used by many unrelated upload call sites across the app; changing it globally would be a much larger behavioral change (every upload failure becoming a hard error instead of a silently-ignored one) than this pass's scope, and wasn't attempted.

### SEC-09 — Non-atomic 2FA/magic-link consumption

**Confirmed on all three counts.** `TwoFactorChallengeController::consumeRecoveryCode()` was a read-then-update with no lock. `Totp::verify()` had no persisted last-used time step at all — a captured valid code could be resubmitted (by the same or a different request) and would keep authenticating for its whole ~90-second window. `ClientPortalLogin::consume()` had the identical read-then-update race. The pending 2FA challenge (`session('two_factor_user_id')`) had no expiry.

**Fix:**
- New `users.two_factor_last_totp_step` column; `Totp::matchedStep()` returns the absolute time-step a code matched; the login-time challenge locks the user row (`DB::transaction` + `lockForUpdate()`) and rejects any step at or before the last one accepted.
- Recovery-code consumption now runs inside the same locked transaction.
- `ClientPortalLogin::consume()` rewritten so the `UPDATE ... WHERE used_at IS NULL` is itself the atomic check-and-set — a losing concurrent request's update simply affects zero rows, no transaction needed.
- The pending challenge now expires 5 minutes after the password step, and account status/company-suspension is rechecked immediately before `Auth::login()` fires (it could have changed between the password step and completing 2FA).

**Files:** `app/Services/Totp.php`, `app/Http/Controllers/Auth/TwoFactorChallengeController.php`, `app/Http/Controllers/Auth/AuthController.php`, `app/Models/ClientPortalLogin.php`, `app/Models/User.php` (added the new column to `$fillable`)
**Migrations:** `2026_10_25_020000_add_two_factor_last_totp_step_to_users_table.php`
**Tests:** `tests/Feature/TwoFactorAndMagicLinkReplayTest.php` (7 tests — same TOTP code can't authenticate twice, a later step still works, challenge expiry, mid-challenge suspension is blocked, a recovery code is consumed exactly once, a magic-link token is consumed exactly once, an expired magic link is rejected).

**Not independently tested:** true concurrent-worker races (two simultaneous requests hitting the same code/token at the exact same instant) — the fixes use the same `DB::transaction()` + `lockForUpdate()` pattern already proven correct elsewhere in this codebase (GL-4's period-closure work, manual-journal approval), and the UPDATE-as-check-and-set pattern is a standard, provably-atomic technique independent of timing, but a real multi-worker MySQL race test (as the original report itself calls out as needed release-gate evidence) was not constructed here.

### UX-01 — Favicon unreachable through the file allowlist

**Confirmed, trivially.** Favicons upload to `favicons/` (`SettingsController::update()`); `FileServeController::PUBLIC_PREFIXES` listed `logos/` and `stamps/` but not `favicons/` — even the owning company's own logged-in users got a 404 on their own favicon.

**Fix:** added `'favicons/'` to the allowlist, matching the existing public treatment of logos/stamps.

**Files:** `app/Http/Controllers/FileServeController.php`
**Tests:** covered by `TenantlessAccountCannotReachCompanyDataTest::test_an_unauthenticated_guest_can_still_reach_a_public_favicon`.

## Batch B — Payment gateway integrity

| ID | Severity | Finding | Status |
|---|---|---|---|
| PAY-01 | High | Payment status and business settlement are not atomic or durably idempotent | **Fixed** |
| PAY-02 | High | HyperPay status response is not bound to the expected checkout | **Fixed** |
| PAY-03 | High | Tap webhook HMAC uses the wrong signed message | **Hardened — unverifiable from this environment** |
| PAY-04 | High | Gateway settlement never checks the paid amount and currency | **Fixed** |
| SEC-05 | High | Webhook SSRF defense is checked only when saving the URL | **Hardened — partial** |

### PAY-01 — Non-atomic, non-idempotent settlement

**Confirmed.** `PaymentWebhookController::handle()` saved `$transaction->status = 'paid'` and called `PaymentSettlementService::settle()` as two separate, unlocked writes. A crash between them left `isPaid()` permanently true with settlement never applied — and a retry then skipped settlement entirely, since the "unpaid" branch never re-entered. Two concurrent deliveries could also both read `isPaid() === false` before either write landed, each calling `settleInvoice()`, which unconditionally created a new `InvoicePayment` with no idempotency check of any kind.

**Fix:** the status-save and `settle()` call now run inside one `DB::transaction()` with `PaymentTransaction::lockForUpdate()`. A failure mid-way rolls back the status save too, so a retry correctly redoes the whole sequence; the lock serializes concurrent deliveries so a second one sees the already-paid status and does nothing. Added a belt-and-suspenders idempotency anchor independent of the lock: a new `invoice_payments.payment_transaction_id` column (mirroring `payments.payment_transaction_id`, added earlier for the same reason on the subscription side) lets `settleInvoice()` refuse to create a second `InvoicePayment` for a transaction it's already settled, even if ever reached twice through some other path.

**Files:** `app/Http/Controllers/PaymentWebhookController.php`, `app/Services/Payments/PaymentSettlementService.php`, `app/Models/InvoicePayment.php`
**Migrations:** `2026_10_25_030000_add_payment_transaction_id_to_invoice_payments_table.php`
**Tests:** `tests/Feature/PaymentWebhookIntegrityTest.php` — a payment settles the invoice exactly once; a duplicate webhook delivery never double-settles; calling `settle()` directly twice never creates a second `InvoicePayment`.

### PAY-02 — HyperPay status not bound to the expected checkout

**Confirmed.** `resourcePath` and `reference` both come from the unverified GET redirect. Within one merchant account, a genuinely-paid checkout's real `resourcePath` could be submitted paired with a *different*, attacker-chosen `reference` — the old code fetched (and trusted) the real successful status for the first checkout, then credited it to whatever `PaymentTransaction` the chosen reference resolved to.

**Fix:** after the authoritative server-to-server status fetch, the response's own `merchantTransactionId` (set only by HyperPay, inside a response only reachable via our own access token) is cross-checked against `$transaction->reference` — the exact value *we* sent as `merchantTransactionId` when creating that specific checkout. A mismatch is rejected outright, regardless of how successful the underlying charge was.

**Files:** `app/Services/Payments/Drivers/HyperPayDriver.php`, `app/Services/Payments/PaymentGatewayDriver.php` (interface now passes `$transaction` to `verifyWebhook()`)
**Tests:** a resourcePath belonging to a different transaction's reference is rejected and settles nothing; a resourcePath matching its own transaction's reference is accepted.

### PAY-03 — Tap webhook HMAC canonicalization

**Confirmed a real discrepancy exists, could not confirm which side is correct.** This file's own code history already shows a previous switch between two candidate schemes (raw-JSON-body HMAC vs. a concatenated-field string), and public sources disagree on which Tap actually documents. Outbound access to `developers.tap.company` is blocked from this sandboxed environment (confirmed: direct `curl` returns `connect_rejected` from the egress proxy), so Tap's own documentation could not be read to settle this directly, and `WebSearch` turned up conflicting secondary sources.

**Fix, deliberately conservative given the uncertainty:** rather than guess again and risk silently rejecting every real payment notification a second time, the signature check now accepts *either* canonicalization — the existing raw-JSON-body HMAC, or a concatenated-field string (charge id, amount, currency, gateway reference, payment reference, status, creation time) built from the best candidate found. This does not weaken security (both still require the real secret key) and maximizes the chance a legitimate webhook succeeds under whichever scheme Tap actually uses.

**This must be confirmed against a real Tap sandbox callback before production reliance** — exactly the acceptance check the original audit itself specifies, and exactly what this response cannot execute. Tracked as a genuinely open item, not closed by this hardening.

**Files:** `app/Services/Payments/Drivers/TapDriver.php`

### PAY-04 — Gateway settlement never verified the paid amount/currency

**Confirmed.** No driver's result was ever cross-checked against what the transaction actually expected. Moyasar was the most severe instance of this: it trusted the shared `secret_token` match alone and never independently retrieved the payment at all — meaning the webhook *body itself* (amount, status, everything) was fully attacker-controlled once the static secret was known. The same unredacted secret was also being persisted verbatim into every logged webhook payload.

**Fix, two parts:**
1. **Generic amount/currency check**: `PaymentGatewayDriver::verifyWebhook()` now returns `amount`/`currency` (from the authoritative source, not blindly the request body) for every driver; the controller rejects a `'paid'` result whose amount/currency don't match the `PaymentTransaction` it's about to settle (0.01 tolerance on amount, case-insensitive currency match). A driver that can't determine one is treated as matching, rather than blocking settlement entirely.
2. **Moyasar specifically**: the webhook body is now used only as a lookup key (its `id`) to drive a real, secret-key-authenticated `GET` to Moyasar's own invoice endpoint — settlement is based entirely on *that* authoritative response, never on anything from the unauthenticated webhook body. The shared `secret_token` is now stripped (at whatever nesting depth) from every stored webhook payload, in both the driver's own `raw` result and the controller's `PaymentGatewayWebhookEvent.payload` audit trail.

**Files:** `app/Services/Payments/PaymentGatewayDriver.php`, `app/Services/Payments/Drivers/{Tap,PayTabs,HyperPay,Moyasar}Driver.php`, `app/Http/Controllers/PaymentWebhookController.php`
**Tests:** a correctly-signed Tap webhook claiming the wrong amount is rejected and never settles; Moyasar settlement reflects the fetched invoice's status, not the body's (body says `'initiated'`, fetched invoice says `'paid'` → settles as paid); the shared secret never appears in a stored, processed webhook event's payload.

### SEC-05 — Webhook SSRF defense checked only at save time

**Confirmed.** `PublicHttpUrl` ran once, at validation time; `SendWebhookDelivery::handle()` called `Http::post($webhook->url)` directly with no re-check — a DNS record a URL's host resolves to is not fixed for the lifetime of that webhook.

**Fix, partial, scope deliberately bounded:** `PublicHttpUrl`'s IP-range check is now also run immediately before every actual delivery attempt (shrinks the DNS-rebinding window from "the webhook's entire lifetime" to "between this check and the connection a few milliseconds later" — not eliminated, since full protection needs the resolved IP pinned for the connection itself, e.g. via `CURLOPT_RESOLVE`, which this does not attempt). Automatic redirect-following is disabled (`allow_redirects => false`), closing the separate "a validated host 302s to an internal target" gap entirely.

**Not done:** true DNS pinning (connect to the exact IP validated, while still presenting the correct Host/SNI) and per-redirect-hop validation, both named in the original recommendation — a meaningfully larger change (low-level cURL option wiring through Laravel's HTTP client) not attempted in this pass. An egress-level network policy blocking internal/metadata ranges at the infrastructure layer (also named in the recommendation) is outside what application code can provide at all.

**Files:** `app/Rules/PublicHttpUrl.php` (exposed reusable `isSafe()`), `app/Jobs/SendWebhookDelivery.php`
**Tests:** a delivery to a URL that now resolves to a private address is blocked before any HTTP call is attempted (asserted via `Http::assertNothingSent()`); a delivery to a genuinely public address still sends normally.

## Batches C–H

Not started. See the task list for scope; this file will be extended per batch as each lands.
