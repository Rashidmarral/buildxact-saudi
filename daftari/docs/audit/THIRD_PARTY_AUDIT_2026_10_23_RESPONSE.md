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

## Batches E–H

Not started. See the task list for scope; this file will be extended per batch as each lands.
