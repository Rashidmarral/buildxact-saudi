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

## Batches B–H

Not started. See the task list for scope; this file will be extended per batch as each lands.
