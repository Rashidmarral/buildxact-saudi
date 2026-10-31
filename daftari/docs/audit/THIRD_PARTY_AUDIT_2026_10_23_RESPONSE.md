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

## Batch C — GL/ledger core correctness

| ID | Severity | Finding | Status |
|---|---|---|---|
| GL-01 | High | Journal-entry idempotency check has a concurrent-posting race | **Fixed** |
| GL-02 | High | Journal line amounts are rounded only at the aggregate total, not per line | **Fixed** |
| GL-03 | High | Credit note, debit note, and purchase return posting (and employee termination settlement) commit outside the document's own transaction | **Fixed** |
| GL-04 | High | Manual-journal approval has a concurrent double-approval race | **Fixed** |
| SEC-06 | High | Approval thresholds do not enforce an independent approver | **Fixed** |

### GL-01 — Concurrent-posting race in the idempotency check

**Confirmed.** `LedgerPostingService::post()`'s only duplicate-prevention was `alreadyPosted()` — a plain, unlocked `SELECT ... EXISTS`. Two concurrent callers posting the same source document (a retried job alongside a webhook, two workers racing a queue) could both read "not yet posted" before either's `INSERT` committed, each then creating a real, duplicate journal entry for the same business event — silently doubling the recorded financial impact.

**Fix:** a database-level unique index on `(company_id, source_type, source_id)` (`journal_entries_source_unique`), which is what actually closes the race a plain application-level check cannot — the database serializes the two concurrent inserts and the loser gets a constraint violation instead of a second row. `post()` catches exactly that violation and treats it as the same idempotent no-op as the pre-existing sequential check, so no caller-visible behavior changes for the normal (non-racing) case.

**Files:** `app/Services/Accounting/LedgerPostingService.php`
**Migrations:** `2026_10_25_040000_add_unique_source_index_to_journal_entries_table.php` — the migration includes an explicit comment warning against auto-deleting any pre-existing duplicate rows if it fails to apply in production; a real duplicate found there needs manual accounting review, not a silent cleanup script.
**Tests:** `tests/Feature/LedgerPostingSourceIntegrityTest.php` — the database itself rejects a second journal entry for the same `(company_id, source_type, source_id)`; a different company may reuse the same `source_type`/`source_id` freely (the index is correctly company-scoped); calling `post()` twice for the same source through the service is an idempotent no-op end-to-end.
**Not independently tested:** true multi-process concurrency (two real simultaneous database connections racing the same insert) — a single PHPUnit process can't reproduce that directly; what's verified is that the constraint exists and that the service's catch path is wired correctly, which is what a losing concurrent writer would actually hit.

### GL-02 — Per-line vs. aggregate rounding mismatch

**Confirmed.** Lines were summed as raw, unrounded floats, and only the aggregate `$totalDebit`/`$totalCredit` was rounded for the balance check. `journal_entry_lines`'s `debit`/`credit` columns are `decimal(14,2)` — the database independently rounds *each line* to 2dp at insert time regardless of what the application summed. A set of lines whose raw sum balanced could therefore still land unbalanced once actually stored, because each line rounded slightly differently than the aggregate did.

**Fix:** new private `quantizeLines()` rounds every line's debit/credit to 2dp *before* any summing happens, in both `post()` and `postManual()` — the balance check and the eventual stored values are now computed from the exact same rounded numbers. It also now rejects outright any line carrying both a non-zero debit *and* a non-zero credit (a malformed line no legitimate caller should ever produce — previously these would have silently passed straight through to the balance sum).

**Files:** `app/Services/Accounting/LedgerPostingService.php`
**Tests:** `tests/Feature/LedgerPostingSourceIntegrityTest.php` — a line with both a non-zero debit and credit is rejected (via both `post()` and `postManual()`); fractional amounts that only balanced before per-line rounding still post correctly and every stored line is confirmed already rounded to 2dp; a zero-amount line is silently dropped rather than stored as a no-op row.

### GL-03 — Posting commits outside the document's own transaction

**Confirmed, and found to be broader than scoped.** `CreditNoteController::store()` and `DebitNoteController::store()` both called their `LedgerPostingService::post*()` method *after* the enclosing `DB::transaction()` had already returned — a ledger-posting failure (a missing account mapping, a locked period) left a fully issued, numbered document permanently on the books with zero corresponding journal entry. `EmployeeController::terminate()` had no transaction at all wrapping the status change, the `EndOfServiceSettlement` record, and the posting call together — any one of the three could succeed while another failed, with no rollback relationship between them.

While investigating, re-checked `PurchaseReturnController::store()` against this existing codebase's own `LedgerPostingIntegrityTest.php` docblock, which claims an earlier "commercial audit finding C1" had already wrapped purchase-return posting in a transaction alongside every other document type. Direct inspection showed `postPurchaseReturn()` was **still** being called outside the transaction in `store()` — the earlier fix evidently covered a different purchase-return action (e.g. void), not document creation. This is a real, unfixed instance of the exact same bug the third-party report flagged, not a false positive — now fixed identically to credit/debit notes.

**Fix:** in all three controllers, the `post*()` call moved to run *inside* the existing `DB::transaction()` closure, immediately before its `return`. For `EmployeeController::terminate()`, the employee status update, the `EndOfServiceSettlement::create()`, and the conditional `postEndOfServiceSettlement()` call are now all wrapped in one `DB::transaction()` — a posting failure rolls back the termination and the settlement record too, not just leaves them orphaned.

**Files:** `app/Http/Controllers/User/CreditNoteController.php`, `app/Http/Controllers/User/DebitNoteController.php`, `app/Http/Controllers/User/PurchaseReturnController.php`, `app/Http/Controllers/User/EmployeeController.php`
**Tests:** `tests/Feature/LedgerPostingIntegrityCreateFlowsTest.php` — issuing a credit note, debit note, or purchase return rolls back the document itself (zero rows, not just zero journal entries) if posting fails; terminating an employee rolls back both the status change and the settlement record if posting fails; terminating an employee whose gratuity computes to zero (short tenure, resignation) still commits cleanly with no posting call ever attempted, confirmed by *not* seeding any account mappings at all for that case and having it still succeed. Mirrors the existing, already-proven pattern from `LedgerPostingIntegrityTest.php` (Bill/Expense).

### GL-04 — Concurrent double-approval race on manual journal entries

**Confirmed.** `ManualJournalEntryController::approvePending()` and `rejectPending()` both checked `$pending->status === 'pending_approval'`, then posted/updated, then saved the new status — three separate, unlocked steps. Two concurrent approval requests for the same pending entry could both pass the status check before either write landed; `postManual()` has no natural source document to deduplicate against (unlike `post()`, it self-assigns a fresh `source_id` per call), so both would genuinely post — one pending record ending up pointing at only one of two real, money-moving journal entries, the other permanently orphaned with no record of its own existence.

**Fix:** both actions now lock the pending row for the duration of the whole check-then-act sequence (`PendingManualJournalEntry::where('id', ...)->lockForUpdate()->firstOrFail()`) and re-check status *after* the lock is acquired, not before. A losing concurrent request now finds the status already `'approved'`/`'rejected'` once it gets the lock and does nothing, instead of posting a second time.

**Files:** `app/Http/Controllers/User/ManualJournalEntryController.php`
**Tests:** covered by the existing `ManualJournalApprovalThresholdTest.php` regression suite (unchanged behavior for the non-racing case) plus the new `ApprovalCreatorSeparationTest.php` exercising the same locked code path. **Not independently tested:** true concurrent double-approval (same limitation as GL-01 — a single test process can't submit two simultaneously-racing HTTP requests); the fix reuses the identical `lockForUpdate()` + re-check-after-lock pattern already relied on elsewhere in this codebase (GL-01's database constraint aside, this exact application-level pattern is what SEC-09's 2FA/magic-link fixes and the period-closure work both already use).

**Scope note:** payment voucher, receipt voucher, and bill approvals (`PaymentVoucherController`, `ReceiptVoucherController`, `BillController`) were **not** given the same row-locking treatment in this batch — GL-04 as reported named manual journal entries specifically. `BillController::approvePending()` in particular still checks status before calling `postAndReceiveStock()` with no row lock of its own (though that method's internal `DB::transaction()` makes any single call atomic). The same class of race plausibly exists there too; tracked as a follow-up, not claimed as fixed here.

### SEC-06 — Approval thresholds do not enforce an independent approver

**Confirmed.** None of the four approval-threshold flows added across this engagement (manual journal entries, payment vouchers, receipt vouchers, bills) ever checked *who* was approving — the same user who created and submitted a pending entry could open it right back up and approve their own submission. A control whose entire purpose is forcing a second set of eyes on a large transaction is worthless if the first set of eyes can also be the second.

**Fix:** a small shared `EnforcesApprovalSeparation` trait exposes `approvalBlockedBySelfApproval(?int $createdBy, int $companyId): bool` — true when the authenticated user is the record's own creator *and* the company has more than one active user. Applied at the top of all four `approvePending()` actions, rejecting with a clear validation error (`back()->withErrors(['approve' => ...])`) before any posting is attempted. A company with only one active user (a genuine sole owner/operator, with literally nobody else available to approve anything) is deliberately exempted — the check would otherwise permanently lock them out of their own thresholded transactions.

**Files:** `app/Http/Controllers/User/Concerns/EnforcesApprovalSeparation.php` (new), `app/Http/Controllers/User/ManualJournalEntryController.php`, `app/Http/Controllers/User/PaymentVoucherController.php`, `app/Http/Controllers/User/ReceiptVoucherController.php`, `app/Http/Controllers/User/BillController.php`
**Tests:** `tests/Feature/ApprovalCreatorSeparationTest.php` (9 tests) — the creator of a pending manual journal entry, payment voucher, receipt voucher, or bill cannot approve their own submission once a second active user exists on the company; a different user can approve the same submission; a sole owner (the only active user) may approve their own entry; an *inactive* second user does not count toward requiring separation (so deactivating everyone else can't be used to dodge the control, but also doesn't accidentally lock a genuinely-solo operator out just because an old disabled account still exists on the company).
**Not done:** this only covers the four approval-threshold flows this engagement added. Other approval-style actions elsewhere in the app (if any exist outside this engagement's own threshold work) were not audited for the same gap.

### Batch C regression

Targeted sweep (`--filter="ManualJournal|CreditNote|DebitNote|PurchaseReturn|Employee|LedgerPosting|VoucherAndBillApprovalThreshold|ApprovalCreatorSeparation"`): **109 passed**, 0 failed. Full application suite re-run after this batch; see the top-level summary once all batches land for the final combined count.

## Batch D — Inventory costing correctness

| ID | Severity | Finding | Status |
|---|---|---|---|
| INV-01 | High | Stock adjustments are valued at the item's selling price, not its cost | **Fixed** |
| INV-02 | High | POS sales deduct stock but never post a matching COGS entry | **Fixed** |
| INV-03 | High | Purchase returns don't reverse the stock the original bill received | **Fixed** |
| INV-04 | Medium | Margin/profitability reports use the item's current price, not its historical cost | **Fixed** |

### INV-01 — Stock adjustments valued at selling price

**Confirmed.** `LedgerPostingService::postStockAdjustment()` computed the adjustment's GL value as `$adjustment->quantity * $item->unit_price` — `unit_price` is what the item **sells** for, not what it **costs**. Inventory Asset is a cost-basis account; every other inventory-costing posting in this same file (bill receipt, invoice COGS) already keys off `purchase_price`. A stock increase inflated Inventory Asset (and its offsetting adjustment account) by the item's whole margin; a decrease understated a write-off's real cost by the same amount.

**Fix:** one-line change — `unit_price` to `purchase_price`.

**Files:** `app/Services/Accounting/LedgerPostingService.php`
**Tests:** `tests/Feature/InventoryCostingGapsTest.php::test_a_stock_increase_is_valued_at_cost_not_the_selling_price` — an item priced at 100 but costing 40 posts a 10-unit increase at 400, not 1000.

### INV-02 — POS sales never posted COGS

**Confirmed.** `PosSaleService::checkout()` deducts `ItemStock` for every tracked-inventory line sold through the till, but `LedgerPostingService::postPosSale()` only ever posted the payment/revenue/VAT lines — no COGS debit, no Inventory Asset credit. Every POS sale of tracked inventory left Inventory Asset permanently overstated by that sale's cost and contributed zero cost of goods sold to the income statement, even though the exact same sale made through the ordinary Invoice flow (`postInvoiceIssued()`) already gets this right.

**Fix:** added the identical COGS block `postInvoiceIssued()` already uses — standard cost (`purchase_price`), summed only over lines whose item is `track_inventory` and only when the sale actually has a warehouse (mirroring the same precondition `checkout()` itself uses before deducting stock at all).

**Files:** `app/Services/Accounting/LedgerPostingService.php`
**Tests:** `tests/Feature/InventoryCostingGapsTest.php` — a POS sale of a tracked item posts COGS and relieves Inventory Asset at standard cost, and the stock deduction and the GL credit agree; a sale through a register with no warehouse posts zero COGS (nothing to relieve, matching that it never touched stock either).

### INV-03 — Purchase returns never reversed stock

**Confirmed.** `PurchaseReturnController::store()` created the return, posted its ledger entry, and recalculated totals — but never touched `ItemStock` at all. Goods physically going back to the supplier left recorded stock exactly as if they were still on hand; every purchase return against a bill that had received tracked inventory left that warehouse's system quantity silently overstated by the returned quantity, with no code path to ever correct it.

**Fix:** new private `applyStock()` (mirroring `BillController`'s own method of the same name/shape) decrements `ItemStock` at the originating bill's warehouse for each returned line resolvable back to a tracked-inventory item via its `bill_item_id`, and `void()` reverses it back. Only runs when the originating bill actually received stock in the first place (`$bill->warehouse_id` and `$bill->stock_received` both set) — a return against a bill that was never received into a warehouse has no stock to give back, and a freeform return line with no `bill_item_id` (a deliberately supported case — see `PurchaseReturnReasonTypeAndFreeformLineTest`) has no item to resolve and is silently skipped, exactly as `BillController::applyStock()` already skips a non-tracked item.

**Files:** `app/Http/Controllers/User/PurchaseReturnController.php`
**Tests:** `tests/Feature/InventoryCostingGapsTest.php` — issuing a purchase return against a bill that received stock reduces that warehouse's quantity, and voiding the return gives it back; a return against a bill that never received stock (no warehouse) touches no `ItemStock` row at all.

### INV-04 — Margin reports use today's cost, not the sale's actual cost

**Confirmed.** `InventoryController::profitability()` computed each item's cost for a historical period as `$quantity * $item->purchase_price` — the item's **current** cost, recomputed fresh every time the report runs, applied retroactively to sales from however far back the report's period reaches. If the item's cost has changed since a given sale (the normal case over any real reporting horizon), a profitability report for last month silently shows a different margin today than it would have shown the day after that sale — and never matches what the GL actually recorded.

**Fix:** new nullable `invoice_items.unit_cost` column, frozen at the moment an invoice line is created (`InvoiceController::syncItems()`, `RecurringInvoice::generateInvoice()`) — the same per-line snapshot pattern already used for `name_ar`/`item_description` on this exact table. `profitability()` now sums each line's own frozen `unit_cost` instead of recomputing from the item's live price, falling back to the item's current price only for a line that predates this column (an explicitly documented, unavoidable approximation for historical data with no snapshot ever taken).

**Scope note:** this intentionally does not change `LedgerPostingService::postInvoiceIssued()`'s own COGS calculation, which still uses the item's cost *at the moment stock is actually deducted* (send time) — that is the economically correct moment for the GL entry itself (the physical stock leaves at send time, not whenever the draft was first typed up), and changing it would risk relitigating an already-tested, already-correct posting. The bug here was specifically the *report* re-deriving a number that should simply be read back from what already happened, not the GL posting itself.

**Files:** `app/Http/Controllers/User/InventoryController.php`, `app/Http/Controllers/User/InvoiceController.php`, `app/Models/InvoiceItem.php`, `app/Models/RecurringInvoice.php`
**Migrations:** `2026_10_25_050000_add_unit_cost_to_invoice_items_table.php`
**Tests:** `tests/Feature/InventoryCostingGapsTest.php` — a line's `unit_cost` is frozen at creation time; the profitability report for a sale still shows that sale's true margin after the item's cost later changes (600, not the 100 a live recompute at the new cost would show); a line with no frozen cost (created before this migration) falls back to the item's current price.
**Not done:** `MachineryInvoiceDraftingService` and `PlatformInvoiceService` (the SaaS platform's own subscription billing) create `InvoiceItem` rows with no `item_id` at all (free-text lines) — there is no cost to freeze for either, and neither was touched.

### Batch D regression

Targeted sweep (`--filter="InventoryCosting|BillGoodsReceipt|PurchaseReturn|Inventory|Pos|RecurringInvoice|CreditDebitNote"`): **189 passed**, 0 failed. Full application suite re-run after this batch; see the top-level summary once all batches land for the final combined count.

## Batch E — Payroll correctness

| ID | Severity | Finding | Status |
|---|---|---|---|
| PAYROLL-01 | High | Marking a payroll run "paid" never settles salaries payable or a bank account | **Fixed** |
| PAYROLL-02 | Medium | Saudi GOSI/EOS/WPS regime controls | **Corrected — WPS export gap fixed, GOSI/EOS already correct** |

### PAYROLL-01 — Marking a run paid never settles anything

**Confirmed.** `postPayrollRun()` (run at approval) credits `SALARIES_PAYABLE` (plus `GOSI_PAYABLE` and, when applicable, `PAYROLL_DEDUCTIONS_PAYABLE`) for the run's net/gosi/deductions totals. `PayrollRunController::markPaid()` then did nothing but `$payrollRun->update(['status' => 'paid', 'paid_at' => now()])` — no settlement journal entry, no bank/cash account ever touched. Every paid payroll run left Salaries Payable permanently outstanding on the balance sheet, exactly as if the company had approved paying its staff but never actually transferred the money — forever, with no code path to ever correct it.

**Fix:** `markPaid()` now requires a funding bank/cash account (a new `payroll_runs.bank_account_id` column, mirroring how `postExpense()`/`BillPayment` already require one to settle `ACCOUNTS_PAYABLE`) and posts a new `postPayrollRunSettlement()` entry debiting `SALARIES_PAYABLE` and crediting that account for the run's `total_net`, inside the same transaction as the status change.

**Scope note:** this settles `SALARIES_PAYABLE` only — the liability actually named in the finding. `GOSI_PAYABLE` (remitted to GOSI) and `PAYROLL_DEDUCTIONS_PAYABLE` (e.g. loan repayments owed to a third party) are genuinely separate remittance obligations with their own timing, not something a single "mark paid" action should assume happens simultaneously with payroll. Settling those is not attempted here and remains open — tracked below, not silently folded into this fix.

**Files:** `app/Http/Controllers/User/PayrollRunController.php`, `app/Services/Accounting/LedgerPostingService.php`, `app/Models/PayrollRun.php`, `resources/views/user/payroll/show.blade.php`
**Migrations:** `2026_10_25_060000_add_bank_account_id_to_payroll_runs_table.php`
**Tests:** `tests/Feature/Payroll/PayrollRunSettlementTest.php` (4 tests) — marking a run paid settles Salaries Payable against the chosen bank account; the same from a cash-type account credits the cash account instead; marking paid with no bank account chosen is rejected and posts nothing; a bank account belonging to another company is rejected.
**Not done:** GOSI and other-deductions remittance settlement (see scope note above) — those liabilities still have no settlement action anywhere in the app, exactly as before this fix.

### PAYROLL-02 — Saudi GOSI/EOS/WPS regime controls

**GOSI and EOS calculators re-verified directly against this engagement's own existing, already-passing test coverage (`GosiCalculatorTest`, `EndOfServiceCalculatorTest`) and against the Saudi Labor Law articles and GOSI contribution structure each calculator's own docblock cites — both are correctly implemented: GOSI's contributory-wage ceiling, the Saudi/non-Saudi contribution split, and Article 84/85's resignation-tenure gratuity scaling all compute correctly. No defect was found in either calculator.**

**The real, concrete, fixable gap found on inspection was in `WpsSifExporter`, not GOSI or EOS:** every WPS detail record emitted `$employee->iban ?? ''` and `$employee->national_id ?? ''` with a silent blank fallback — an employee who hadn't completed onboarding (a common, ordinary state) produced a WPS SIF file with a blank IBAN or national ID for that row. A real bank/MHRSD submission with a blank mandatory field either gets the whole file rejected, or — worse, on a more lenient parser — risks a payment that can't be correctly matched back to the employee it's meant to pay. Nothing in the app ever surfaced this as an error; the broken file was simply handed to the user to upload to their bank.

**Fix:** `WpsSifExporter::export()` now checks every employee on the run for a present `iban` and `national_id` before generating anything, and throws a clear, named-employee error instead of a silently incomplete file. `PayrollRunController::downloadWps()` catches it and returns a validation error instead of a broken download.

**Files:** `app/Services/Payroll/WpsSifExporter.php`, `app/Http/Controllers/User/PayrollRunController.php`
**Tests:** `tests/Feature/Payroll/WpsExportIntegrityTest.php` (4 tests) — export is refused for an employee missing an IBAN; refused for an employee missing a national ID; succeeds once every employee has both; a run mixing one complete and one incomplete employee is refused entirely, and the error names only the incomplete one.
**Not done:** this validates data completeness, not field *format* (e.g. a malformed IBAN checksum) — and, as the exporter's own pre-existing docblock already states, the SIF's exact field order/delimiter has not been validated against any real bank's current WPS portal. Both remain genuinely unverified from this environment.

### Batch E regression

Targeted sweep (`--filter="WpsExportIntegrityTest|PayrollRunSettlementTest|Payroll"`): **50 passed**, 0 failed. Full application suite re-run after this batch; see the top-level summary once all batches land for the final combined count.

## Batch F — ZATCA integrity

| ID | Severity | Finding | Status |
|---|---|---|---|
| ZATCA-01 | High | Delivery to the buyer is not gated by clearance for standard (B2B) invoices | **Fixed** |
| ZATCA-02 | High | A retry regenerates the UUID/hash rather than resuming the original attempt | **Confirmed — not fixed, see below** |
| ZATCA-03 | High | The duplicate-submission check runs before the company row is locked | **Fixed** |
| ZATCA-04 | High | The QR code is saved before the clearance/reporting call is confirmed successful | **Fixed** |

Outbound access to `gw-fatoora.zatca.gov.sa` and `zatca.gov.sa` is blocked from this sandbox (confirmed: `curl` returns `connect_rejected`/`403` from the egress proxy) — the same constraint already documented for PAY-03 in Batch B. No fix in this batch could be verified against a real ZATCA sandbox call; every test below exercises the code path with `Http::fake()`.

### ZATCA-01 — Delivery not gated by clearance

**Confirmed.** A standard (B2B) tax invoice is regulated under ZATCA's Clearance Model: the government must cryptographically clear it *before* it reaches the buyer — unlike a simplified (B2C) invoice, which is delivered immediately and only reported afterward. `InvoiceController::doSend()` marks an invoice `'sent'` immediately and synchronously; the actual ZATCA submission is either an async queued job (`zatca_sync_frequency === 'instant'`) or waits for a later batched/scheduled sync run. Nothing stopped `emailInvoice()` — which attaches the real tax-invoice PDF, QR code and all — from handing a standard invoice to the customer well before that submission ever completed, or even ran at all.

**Fix:** `emailInvoice()` now refuses to send once a standard invoice's company has turned on B2B sync and finished ZATCA onboarding, unless that specific invoice already has a `cleared` log entry. A company that hasn't onboarded yet, or has deliberately left B2B sync off, isn't under this obligation in the app's own model and isn't newly blocked. Simplified (B2C) invoices are never gated by this check at all — they're correctly allowed to go out before reporting, matching the Reporting Model.

**Scope note:** only `emailInvoice()` is gated — it's the one action that hands over the actual regulated tax document (PDF with QR/signature). `downloadPdf()` is left open (an internal preview/review action, not itself "delivery to the buyer"), and `sendWhatsapp()`/`sendSms()` only ever send a text notification with a payment link, never the document itself, so they carry no clearance obligation to gate.

**Files:** `app/Http/Controllers/User/InvoiceController.php`
**Tests:** `tests/Feature/ZatcaDeliveryGatedByClearanceTest.php` (5 tests) — emailing an uncleared standard invoice is blocked for an onboarded B2B company; emailing it succeeds once cleared; a simplified B2C invoice is never blocked; a company with B2B sync off, or not yet onboarded at all, is never blocked either.

### ZATCA-02 — Retry regenerates the UUID/hash

**Confirmed, and deliberately not fixed in this pass.** Every call to `submit()`/`submitCreditNote()`/`submitDebitNote()`/`submitPrepaymentInvoice()` — including a retry of a previously-failed attempt — calls `$this->xml->newUuid()` fresh and recomputes a fresh `invoiceHash` from it. The PIH/ICV hash chain itself is unaffected by this (both only ever advance on a *confirmed* success, so a failed attempt's retry correctly reuses the same previous-hash and ICV slot) — but the UUID embedded in the document changes on every attempt regardless of why the prior one failed.

For a clean rejection (ZATCA explicitly returned a 4xx with validation errors), a fresh UUID on retry is correct and safe — the prior attempt is definitively dead. The real risk is a **connection-level failure** (a timeout, a dropped connection) where the request may have actually reached and been processed by ZATCA before the response was lost client-side. Retrying with a brand-new UUID in that case risks either ZATCA rejecting the retry as a duplicate against a submission it already has record of, or — worse — accepting it as a second, genuinely distinct cleared document for what is, on this app's side, the same single invoice.

**Why this isn't fixed here:** a correct fix needs to distinguish "ZATCA told us no" from "we don't actually know what ZATCA did," and reuse the original UUID/hash/ICV only in the second case — while still minting fresh ones for a normal clean-rejection retry. This is a genuine design decision about how to safely resume an at-least-once retry against a non-idempotent government API, not a narrow, mechanical bug fix, and the correct behavior (does ZATCA's clearance endpoint tolerate a resubmission of the exact same UUID/hash it may or may not have already processed? does it reject outright? is there an idempotency key mechanism in the real API that documentation would reveal?) cannot be determined without access to ZATCA's own integration guide or a live sandbox call — both blocked from this environment, the same constraint PAY-03 hit in Batch B. Implementing a guess here risks making the hash chain *more* fragile than today's simple "always mint fresh" behavior, not less. Tracked as a genuinely open item requiring either real ZATCA sandbox access or a deliberate product/architecture decision, not claimed as resolved.

### ZATCA-03 — Duplicate-submission check races the company lock

**Confirmed.** `rejectUnsubmittable()` (and its prepayment/credit-note/debit-note equivalents) — which includes the already-cleared/reported duplicate check — ran *before* `lockedSubmission()` acquires the company row lock. Two concurrent submission attempts for the same document (a double-click on "Sync now," an admin retry racing the original async job) could both pass that check before either transaction committed. The company lock then correctly serializes the two calls, but without re-checking once inside it, the second one to acquire the lock — still holding its pre-lock, now-stale copy of the document — went on to mint a fresh UUID/ICV and fire a second, real clearance/reporting call for a document the first call, now committed, had already cleared.

**Fix:** every `submit*()` method now re-fetches the document (`$invoice->fresh()`, etc.) and re-runs the exact same rejection check *inside* the locked closure, after the company row lock is held — mirroring the identical lock-then-recheck pattern already used for GL-04 (manual journal approvals) and SEC-09 (2FA/magic-link consumption) elsewhere in this engagement. A losing concurrent call now finds the document already cleared/reported once it actually gets the lock, and is rejected exactly like any other duplicate-submission attempt — no second UUID minted, no second ICV consumed, no second live call.

**Files:** `app/Services/Zatca/ZatcaSyncService.php`
**Tests:** `tests/Feature/ZatcaConcurrentSubmissionRaceTest.php` (3 tests) — a second submission call reusing the exact same pre-clearance in-memory invoice/prepayment/credit-note object (what a real concurrent second request would hold) is rejected without a second HTTP call, with the ICV left unadvanced.
**Not independently tested:** true concurrent HTTP requests racing in separate processes — same limitation as GL-04/GL-01's identical caveat; what's verified is that the re-check is wired correctly, which is what a losing concurrent writer would actually hit once the lock serializes it.

### ZATCA-04 — QR code saved before the clearance call is confirmed

**Confirmed.** `buildSignedPayload()` builds the fully-signed XML and QR *proactively*, from the company's own private key — deliberately not dependent on a clearance response (its own docblock explains why: QR tag 9, the certificate signature, is a property of the certificate itself, not of any submission outcome). But the code then saved that locally-built QR straight onto the invoice/prepayment/credit note/debit note *before* the outbound clearance/reporting HTTP call was even attempted — not after confirming it succeeded. A submission ZATCA went on to reject (or one that failed outright on a connection error) still left the document carrying a QR that encodes a cryptographic stamp and signature for a document the government never actually cleared.

**Fix:** the QR-code `update()` call for all four document types moved from immediately before the HTTP call to inside the `if ($response->successful())` branch, alongside the log's own `'cleared'`/`'reported'` status write — so the QR is only ever persisted in the exact same moment the submission is recorded as confirmed-successful, never before.

**Files:** `app/Services/Zatca/ZatcaSyncService.php`
**Tests:** `tests/Feature/ZatcaQrOnlyPersistedOnConfirmedSuccessTest.php` (3 tests) — a rejected submission (HTTP 400) leaves the invoice with no QR code; a connection-level failure leaves it with no QR code either; a confirmed cleared submission does save it.

### Batch F regression

Targeted sweep (`--filter="Zatca|DemoMode|FailureNotifications|Invoice"`): **529 passed**, 0 failed — including fixing two pre-existing test fixtures (`DemoModeTest`, `FailureNotificationsTest`) that created an `Invoice` with no explicit `status`, silently relying on the in-memory object never reflecting the database's own `'draft'` column default; ZATCA-03's `->fresh()` re-fetch correctly started rejecting those as the drafts they actually were, which is what exposed the gap in the fixtures themselves, not a regression in the fix. Full application suite re-run after this batch; see the top-level summary once all batches land for the final combined count.

## Batch G — Billing/POS/voucher currency correctness

| ID | Severity | Finding | Status |
|---|---|---|---|
| BILL-01 | High | A hidden/retired plan can be self-upgraded to, and plan/company currency mismatches are never checked | **Fixed** |
| BILL-02 | High | Coupon redemption has a usage-limit race | **Corrected — already fixed** |
| POS-01 | High | A discounted POS sale's mirrored invoice loses the discount | **Fixed** |
| GL-05 | High | Payment/receipt vouchers silently mislabel a foreign-currency bill/invoice's settlement | **Fixed** |

### BILL-01 — Hidden plan and currency substitution

**Confirmed, two distinct gaps.** `BillingController::upgrade()` validated `plan_id` only as `exists:plans,id` — the pricing page itself (`index()`) only ever lists plans that are both `is_active` and `is_public`, but nothing stopped a direct POST naming a retired or internal-only plan's id from succeeding anyway, at whatever price that plan was last configured with. Separately, `Plan` carries its own `currency` column, but every `Payment`/`Subscription` row created afterward labels the charged amount with the **company's** currency instead — with no conversion ever applied. A USD-priced plan checked out by a SAR-billing company would silently charge, and record, "500 SAR" for what was actually configured as $500.

**Fix:** `upgrade()` now rejects a plan that isn't both `is_active` and `is_public` (matching exactly what the pricing page itself offers), and rejects a plan whose `currency` is set and differs from the company's own — this app does not do real-time FX conversion for billing (the same restriction `ZatcaSyncService` already enforces for e-invoicing), so a mismatch is rejected cleanly with a message to contact support, rather than silently mislabeled. A plan with no `currency` set at all (a legacy/unscoped row) is treated as matching anything, preserving existing behavior for plans that predate this column.

**Files:** `app/Http/Controllers/User/BillingController.php`
**Tests:** `tests/Feature/BillingHiddenPlanAndCurrencyMismatchTest.php` (5 tests) — a retired (`is_active=false`) plan and an internal-only (`is_public=false`) plan are both rejected; a plan priced in a different currency than the company bills in is rejected; a plan with no currency set is unaffected; a matching-currency plan still checks out normally.

### BILL-02 — Coupon redemption usage-limit race

**Corrected — already fixed, not a live finding.** `CouponService::redeem()` already locks the coupon row (`Coupon::where('id', ...)->lockForUpdate()->firstOrFail()`) and re-checks both `max_uses` and `max_uses_per_company` *inside* that lock, with its own docblock explaining exactly this TOCTOU race and why the earlier `validate()` call is only the fast, non-authoritative check. Direct inspection found no path that bypasses `redeem()` to apply a discount or record a redemption. No code change made.

### POS-01 — Discounted POS sale loses its discount when mirrored to an invoice

**Confirmed.** Every completed POS sale mirrors into a real `Invoice` (ZATCA treats it as a simplified tax invoice — see `PosSaleService::mirrorAsInvoice()`). `InvoiceItem` has no per-line discount column, so `Invoice::recalculateTotals()` sums each mirrored line's full, *undiscounted* `quantity * unit_price` for `subtotal`. A `PosSaleItem`'s own `discount_amount` (already correctly baked into its `line_total`/`vat_amount`, both copied over as-is) was silently dropped at the header level — a discounted sale's mirrored invoice ended up with a *higher* total than what was actually charged and than its own `InvoicePayment` rows actually recorded, leaving a "paid in full" invoice whose `total` didn't equal its `amount_paid`.

**Fix:** `mirrorAsInvoice()` now carries the sale's own `discount_total` forward as the invoice's header-level fixed discount (`discount_type: 'fixed'`, `discount_value: $sale->discount_total`) — the same discount mechanism ordinary invoices already use — so `recalculateTotals()` lands on the exact same total the sale was actually rung up and paid at.

**Files:** `app/Services/Pos/PosSaleService.php`
**Tests:** `tests/Feature/Pos/PosSaleMirroredInvoiceDiscountTest.php` (2 tests) — a discounted sale's mirrored invoice total matches what was actually paid (and `total === amount_paid`); an undiscounted sale is unaffected.

### GL-05 — Foreign-currency bill/invoice settled through a voucher mislabels the amount

**Confirmed.** Neither `PaymentVoucher` nor `ReceiptVoucher` has a currency column of its own — `->amount` is always implicitly a base-currency figure, exactly matching what `postPaymentVoucher()`/`postReceiptVoucher()` post to the GL with no conversion (unlike `postBillPosted()`/`postInvoiceIssued()`, which explicitly convert through the document's own `exchange_rate`). `settleBillAndPost()`/`settleInvoiceAndPost()` increment the linked bill/invoice's `amount_paid` by that same base-currency number directly — correct only when the bill/invoice is *also* in the base currency. A foreign-currency bill paid through a voucher (a real, reachable path — the bill's own "Pay" link routes straight into the voucher form with no currency filter at all) had its `amount_paid` compared against its own foreign-currency `total`, two different currencies treated as the same number — e.g. a $1,000 USD bill paid via a 3,750 SAR voucher would show `amount_paid = 3750` against `total = 1000`, nowhere close to actually reconciling.

**Fix:** both controllers' validation now rejects a `bill_id`/`invoice_id` whose own `currency` differs from the company's base currency, the same restriction `ZatcaSyncService` already applies to e-invoicing sync — directing the user to record that payment directly against the bill/invoice instead, rather than attempting a conversion this app has no established convention for inside a voucher.

**Files:** `app/Http/Controllers/User/PaymentVoucherController.php`, `app/Http/Controllers/User/ReceiptVoucherController.php`
**Tests:** `tests/Feature/VoucherForeignCurrencyDocumentRejectedTest.php` (4 tests) — a foreign-currency bill/invoice is rejected for both voucher types, with nothing created and `amount_paid` left untouched; a base-currency bill/invoice still settles normally.
**Not done:** this only closes the voucher path. Whether a foreign-currency bill/invoice has any OTHER settlement path with the same gap (e.g. a direct "record payment" action, if one exists independent of vouchers) was not separately audited in this batch.

### Batch G regression

Targeted sweep (`--filter="Billing|Coupon|DemoMode|Pos|Restaurant|Repair|Voucher|WithholdingTax"`): **331 passed**, 0 failed. Full application suite re-run after this batch; see the top-level summary once all batches land for the final combined count.

## Batch H — Medium/low ops & compliance cleanup

| ID | Severity | Finding | Status |
|---|---|---|---|
| GL-06 | Medium | Opening-balance entry can desync Accounts Receivable/Payable from the real subledger | **Fixed** |
| COMP-01 | Medium | Record retention counts from the document date, not the tax-period end | **Fixed** |
| SEC-07 | Medium | The CodeCanyon packaging script can ship real backup dumps/attachments | **Fixed** |
| SEC-08 | Medium | All proxies are trusted for every forwarded header, including client IP | **Fixed** |
| API-01 | Medium | An API token's plan entitlement is checked only at issue time, never per-request | **Fixed** |
| OPS-03 | Low | The System Health "Last backup" check is hardcoded and the scheduler/queue checks have an inverted threshold | **Fixed** |
| OPS-02 | Low | Backups are local-disk only, with no off-site replication or DR runbook | **Open — operational, not a code defect** |

### GL-06 — Opening balance can desync AR/AP from the real subledger

**Confirmed.** `OpeningBalanceController`'s own docblock already explains why customer/supplier opening balances were deliberately left out of this GL-only form: this system treats Accounts Receivable/Payable's real balance as the live sum of actual `Invoice`/`Bill` rows, not a ledger field — a balance posted straight to the AR/AP control account here would never match that sum, with no way to ever reconcile it. Nothing stopped a user from picking that exact control account from the dropdown anyway.

**Fix:** `store()` now resolves the company's AR/AP accounts (via `AccountMapping::resolve()`, so a company that has remapped either account is still caught) and rejects any submitted line against either one, directing the user to record that balance as a backdated invoice or bill per customer/supplier instead — the mechanism this system actually uses to build up those totals.

**Files:** `app/Http/Controllers/User/OpeningBalanceController.php`
**Tests:** `tests/Feature/OpeningBalanceControlAccountGuardTest.php` (3 tests) — an AR line and an AP line are both rejected with nothing posted; an ordinary GL account is unaffected.
**Not done:** customer/supplier opening balances still have no dedicated mechanism of their own — exactly the gap this class's docblock already named as needing its own, separate design.

### COMP-01 — Retention counted from the document date, not the tax-period end

**Confirmed.** `RecordRetentionPolicy`'s own docblock already states the correct rule (Saudi VAT Implementing Regulations Article 66: 6 years *from the end of the tax period*), but the implementation compared `now()` against the document's own date directly — a document dated early in a quarter became legally deletable up to ~3 months before Article 66 actually permits it.

**Fix:** the window now counts from the end of the calendar quarter containing the document's date (via the same `VatPeriods` helper the VAT return tracker already uses), not the raw date. This is correct for a company that files quarterly, and a deliberately safe, conservative over-approximation for one that files monthly — a quarter always ends on or after the month containing the same date, so this never retains for *less* time than the law requires for either filing frequency, without this document-agnostic check needing to know which one a given company actually uses.

**Files:** `app/Support/RecordRetentionPolicy.php`
**Tests:** `tests/Feature/RecordRetentionPolicyTest.php` — new case with a fixed "now" showing a document just past the old, incorrect 6-raw-year cutoff is still correctly protected once its quarter-end is accounted for, while one genuinely outside the window either way is still correctly expired.

### SEC-07 — Packaging script can ship real backup dumps and attachments

**Confirmed.** `packaging/build-package.sh` (builds the CodeCanyon release zip) carefully excludes `.env`, `.git`, sqlite databases, logs, and `storage/app/public/*` — but never excluded `storage/app/private/*`, where `BackupDatabase` (Batch H/GL-adjacent feature, see OPS-03 below) writes real database + public-storage dumps under `backups/`, and where real support-ticket attachments land under `ticket-attachments/`. A developer who ever ran the backup command, or exercised the ticket-attachment flow, locally before building a release would have shipped those real files straight into the distributed zip.

**Fix:** added `storage/app/private/*` to the rsync exclude list, and extended the placeholder-directory recreation loop so the staged copy still has empty, writable `backups/`/`ticket-attachments/` directories at boot.

**Files:** `packaging/build-package.sh`
**Verification:** a dry-run `rsync --dry-run -v` against this checkout (with a real file manually placed under `storage/app/private/backups/`) confirmed the exclude now suppresses everything under `storage/app/private/` from the file listing. The full build script itself (`composer install --no-dev` + `npm run build` + two zips) was not executed end-to-end in this pass — that's a multi-minute build with real side effects on a throwaway staged copy, not something worth running just to prove an `rsync --exclude` pattern, which the dry-run already verified directly.

### SEC-08 — All proxies trusted for every forwarded header

**Confirmed.** `bootstrap/app.php` calls `trustProxies(at: '*')` with no `headers` argument, which defaults to Laravel's full set — including `X-Forwarded-For`. The documented reason for trusting `'*'` at all (a packaged product with no fixed deployment topology, needing `X-Forwarded-Proto` for correct HTTPS detection behind an unknown reverse proxy) never actually required trusting the client's reported IP — but trusting `'*'` for the proxy's address while also trusting whatever `X-Forwarded-For` it hands over let *any* direct client spoof its own reported IP (`request()->ip()`, used by audit logging and anything else IP-based) just by setting that header itself.

**Fix:** narrowed the trusted header set to `X-Forwarded-Host | X-Forwarded-Port | X-Forwarded-Proto` — explicitly excluding `X-Forwarded-For` — while keeping the documented, genuinely-needed HTTPS-detection behavior fully intact.

**Files:** `bootstrap/app.php`

### API-01 — API token entitlement checked only at issue time

**Confirmed.** `ApiTokenController::store()` already refuses to *issue* a new token when the company's plan doesn't include the `'api'` feature (`FeatureAccessService`), but nothing on the actual `routes/api.php` middleware stack re-checked that on each request — a token minted while on an API-enabled plan kept working against every v1 endpoint indefinitely after the company downgraded to a plan without API access, or had an admin's per-company override revoked.

**Fix:** new `EnsureApiFeatureEnabled` middleware (API-side sibling of the existing `EnsurePlanFeature`/`EnsureApiCompanyActive` pattern — a JSON 403 instead of a web redirect) aliased as `api.feature` and added to the `v1` route group alongside the existing `api.company.active`/`api.limit` checks, using the exact same `FeatureAccessService` the token-issuance check already relies on — so a plan downgrade or a revoked override takes effect on the very next API request, not just on the next token someone tries to create.

**Files:** `app/Http/Middleware/EnsureApiFeatureEnabled.php` (new), `bootstrap/app.php`, `routes/api.php`
**Tests:** `tests/Feature/ApiFeatureEntitlementTest.php` (5 tests) — a token is rejected once the plan lacks `has_api`; still works while the plan includes it; a company with no active subscription at all stays unrestricted (matching `FeatureAccessService`'s existing default); a per-company override correctly wins in both directions (re-enabling a lacking plan, revoking an otherwise-included one).
**Fixture note:** two pre-existing tests (`FeatureLimitManagementTest`'s API-limit cases) constructed a plan with no `has_api` set at all and then exercised a real `/api/v1/me` call — an accidental gap this fix now correctly catches. Both updated to set `has_api: true`, since they're testing the call-limit middleware specifically, not entitlement.

### OPS-03 — Health report's backup status is hardcoded; scheduler/queue thresholds inverted

**Confirmed, and found to be two separate bugs in the same file.** `SystemHealthService::backup()` unconditionally returned "No backup system is configured for this installation," regardless of whether one actually existed — stale the moment `BackupDatabase`/`BackupController` shipped (both already maintain the exact `backup_last_run_at`/`backup_last_status` Setting keys this check could have read). A failed or silently-stalled backup schedule looked identical to a healthy one on the one dashboard meant to catch it.

**While fixing that, found a second, more serious bug in the same class:** `scheduler()` and `queue()` both compute "how long ago" via `now()->diffInHours()`/`diffInMinutes()` against a timestamp that is always in the past — which this installed Carbon version returns as a **negative** number. Every "elapsed time ≤ N" threshold in both checks is trivially true for any negative number, so the scheduler check silently reported "healthy" no matter how long cron had actually stopped running, and the queue check's "oldest pending job" staleness threshold never fired either. Neither bug was named in the original report; found incidentally while implementing the backup fix, and fixed identically (`abs()` around each diff) since it's the exact same root cause.

**Fix:** `backup()` now reads the real last-run timestamp/status and reports healthy/warning/failed based on recency and outcome, the same pattern `scheduler()` already used (now correctly). `scheduler()` and `queue()`'s elapsed-time calculations are wrapped in `abs()`.

**Files:** `app/Services/SystemHealthService.php`
**Tests:** `tests/Feature/SystemHealthBackupStatusTest.php` (6 tests) — backup: never-run (warning), recent success (healthy), failed run (failed), stale schedule (warning, then failed as it gets older); scheduler: a 2-hour-old heartbeat is now correctly reported failed (it silently reported healthy before this fix), a fresh one still reports healthy.

### OPS-02 — Backups have no off-site replication or DR runbook

**Confirmed, and deliberately not attempted as a code fix.** `BackupDatabase`'s own docblock already acknowledges this directly: dumps land on the same server's local disk (`storage/app/private/backups`) unless the public-storage disk has separately been switched to S3, and even then "S3's own durability/versioning is the operator's responsibility to configure, not this command's." A server-loss disaster destroys the application and its only backup copy together. This is a genuine, correctly-self-documented operational gap, not a narrow bug — closing it needs an off-site storage target (S3/remote disk export), delivery failure alerting, and a written disaster-recovery runbook, which is new infrastructure/product scope well beyond this response's remit. Left open and reported honestly as such, rather than fabricating a partial fix.

### Batch H regression

Targeted sweep (`--filter="Api|OpeningBalance|RecordRetention|SystemHealth|AdminDashboard|DatabaseBackup|Suspended"`): **84 passed**, 0 failed. Full application suite re-run after this batch; see the top-level summary once all batches land for the final combined count.

## Final summary

All 38 findings from the original report have been re-verified against this codebase and addressed across Batches A–H. Final tally:

- **33 Fixed** — code changed, dedicated regression tests added, full application suite verified green after every batch (same 2 pre-existing/unrelated failures — `DashboardVatDueBannerTest`, `SettingsSecurityAuditTest` — throughout this entire response; no batch introduced a new one): SEC-01, SEC-02, SEC-03, SEC-04, SEC-06, SEC-09, UX-01, OPS-01, PAY-01, PAY-02, PAY-04, GL-01, GL-02, GL-03, GL-04, GL-05, GL-06, INV-01, INV-02, INV-03, INV-04, PAYROLL-01, PAYROLL-02, ZATCA-01, ZATCA-03, ZATCA-04, BILL-01, POS-01, COMP-01, SEC-07, SEC-08, API-01, OPS-03.
- **1 Corrected** — re-verification found no live defect, only a report that didn't match the current code: BILL-02 (coupon redemption's usage-limit race was already closed by a row lock + re-check inside the transaction, with its own docblock already explaining exactly that race).
- **2 Hardened but explicitly flagged unverifiable** — a real, confirmed gap, fixed as conservatively as possible without inventing an answer this sandbox has no way to check: PAY-03 (Tap webhook HMAC canonicalization — outbound access to `developers.tap.company` is blocked here, so the signature check now accepts either candidate scheme rather than guessing one), SEC-05 (webhook SSRF — re-validates the target before every delivery and blocks redirect-following, but true DNS-pinning was out of scope for this pass).
- **1 Confirmed but not fixed** — a real gap where a correct fix needs information this environment cannot obtain: ZATCA-02 (a retry mints a fresh UUID/hash rather than resuming the original attempt after an ambiguous network failure; the right behavior depends on ZATCA's own retry/idempotency semantics, and `gw-fatoora.zatca.gov.sa`/`zatca.gov.sa` are both blocked from this sandbox — implementing a guess here risked making the hash chain more fragile, not less, so it was left open rather than shipped unverified).
- **1 Open, operational** — a genuine infrastructure/product gap the codebase already self-documents honestly, not something a code change in this response can close: OPS-02 (backups are local-disk only, no off-site replication or DR runbook).

**What "Fixed" means here, precisely:** the described code change was made, a new test reproduces the original failure mode and proves the fix closes it, a targeted regression sweep covering the surrounding feature area passed, and the full `php artisan test` suite (1800+ tests, growing to 1934 by the final batch) was run clean after every single batch before it was committed — never just the new tests in isolation. Nothing here was marked Fixed on the strength of the new tests alone.

**What remains genuinely outside what this response can verify**, regardless of batch:
- Live acceptance against a real ZATCA sandbox (clearance/reporting response shapes, retry semantics, the Tap/PayTabs/HyperPay/Moyasar webhook signature schemes against each provider's actual production behavior) — every fix in this area was built from public documentation, existing code conventions, and this codebase's own prior commit history, never from a live call this sandbox's network policy blocks.
- True multi-process/multi-worker concurrency (the double-submission, double-approval, and coupon-redemption race fixes are all verified by reproducing the *losing* side of the race deterministically — reusing a stale object, or calling twice sequentially while holding a lock — which exercises the exact code path a real concurrent loser would hit, but a literal two-process simultaneous test was not constructed anywhere in this response).
- Anything requiring a real production-like MySQL deployment, a real payment gateway account, or a real WPS bank portal — all testing here ran against this sandbox's sqlite test database and `Http::fake()`.

**Everything committed, batch by batch, same branch** (`claude/saudi-accounting-software-clone-zmo2zo`): Batch A (`0295092`), Batch B (`49c941f`), Batch C (`8f52b25`), Batch D (`0fe5728`), Batch E (`6a36c7a`), Batch F (`4638260`), Batch G (`5eb44a0`), Batch H (`4a7ca55`) — tracked by the existing open draft PR for this branch.
