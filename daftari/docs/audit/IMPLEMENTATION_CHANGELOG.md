# Implementation Changelog — Phase 2 (Remediation)

Everything below is additive or newly-guarded behavior; nothing changes existing output for a company that hasn't opted into a new threshold, and no historical invoice/accounting data was touched, renamed, or deleted.

## ZATCA-05 — Double-submission guard

**Problem:** `ZatcaController::syncInvoice()` (and the equivalent single-document paths for Prepayment Invoices, Credit Notes, and Debit Notes) never checked whether a document was already cleared/reported before calling `submit()`. A double-click or a retry after a slow response could mint a fresh UUID/hash, advance the ICV, and fire a second live clearance/report call for the same document.

**Fix:** `ZatcaSyncService::rejectUnsubmittable()`/`rejectUnsubmittablePrepayment()`/`rejectUnsubmittableCreditNote()`/`rejectUnsubmittableDebitNote()` now each check `isZatcaLocked()`/`isZatcaSynced()` first and return a clear `failed` log (no XML built, no ICV/PIH touched) instead of proceeding. This is the same choke-point pattern the codebase already used for the cancelled-invoice and never-cleared-correction-document bugs — not a new mechanism.

**Files changed:** `app/Services/Zatca/ZatcaSyncService.php`

**Tests added:** `tests/Feature/ZatcaDoubleSubmissionGuardTest.php` (4 tests — invoice, prepayment invoice, credit note, debit note, each proving a second submission is rejected without a second HTTP call).

## GL-6 — Opt-in approval threshold for manual journal entries

**Problem:** Manual journal entries — a direct, unaudited GL posting with no source document behind it — had no maker-checker control at all, unlike Expenses/POs/Invoices/Quotations, which already support a configurable approval threshold.

**Fix:** Mirrors the existing threshold pattern exactly.
- New `Company::manual_journal_approval_threshold` (nullable decimal). `null` (the default) preserves today's exact instant-post behavior for every company that hasn't opted in.
- New `Company::manualJournalRequiresApproval(float $total): bool` helper, identical in shape to `expenseRequiresApproval()`/`poRequiresApproval()`.
- New `PendingManualJournalEntry` staging model/table: since a manual journal entry has no pre-existing persistent record separate from the act of posting itself (`LedgerPostingService::postManual()` creates the real `JournalEntry` + lines atomically, with its own balance check), a threshold-gated entry is staged here — with the same balance validation applied up front — instead of being written into `JournalEntry` in some unposted state (which would have required retrofitting every GL report query with a status filter).
- `ManualJournalEntryController::store()`: computes the entry's total before deciding; if at/above the threshold, stages it (`pending_approval`, nothing touches the ledger) and audit-logs `journal_entry.create_pending`; otherwise behaves exactly as before.
- New `approvePending()`/`rejectPending()`/`pendingIndex()` actions and routes (`journals/pending`, `.../approve`, `.../reject`). Approval is the *only* moment `postManual()` is ever called for a staged entry — rejection never touches the ledger.
- New "Pending approvals" page listing staged entries with their full line breakdown, plus an approve/reject action each.

**Files changed:** `app/Models/Company.php`, `app/Http/Controllers/User/ManualJournalEntryController.php`, `routes/web.php`, `resources/views/user/journals/index.blade.php`
**Files added:** `app/Models/PendingManualJournalEntry.php`, `resources/views/user/journals/pending-index.blade.php`
**Migrations added:** `2026_10_23_010000_add_manual_journal_approval_threshold_to_companies_table.php`, `2026_10_23_020000_create_pending_manual_journal_entries_table.php`

**Tests added:** `tests/Feature/ManualJournalApprovalThresholdTest.php` (7 tests — null-threshold no-op, staging above threshold, instant-post below threshold, approve-then-posts, reject-never-posts, unbalanced-entry-still-rejected-before-staging, cross-company isolation).

**Regression check:** `tests/Feature/ManualJournalEntryTest.php` (the pre-existing suite) re-run and passes unchanged — the new pre-validation produces the identical error-bag key (`lines`) the old `postManual()`-thrown exception did.

## SEC-08 — Tax-rate change audit logging

**Problem (as verified, not as originally suspected):** `TaxRateController` had zero `AuditLog::record()` calls — tax-rate creation, updates (including rate/type changes), deletion, and VAT recovery/apportionment settings changes left no trail at all.

**Note on scope:** the parent finding originally suspected three other gaps (manual journal entry creation, user role changes, ZATCA credential/settings changes). Direct verification before implementing found: manual journal creation was *already* logged; ZATCA credential/settings changes were *already* logged extensively (six distinct actions); and "user role changes" isn't a logging gap at all — the feature to change an existing member's role doesn't exist in `TeamController`. Only the tax-rate gap was real, and only it was fixed. See `SECURITY_AND_ACCESS_AUDIT.md` for the full verification table.

**Fix:** `TaxRateController::store()`/`update()`/`updateRecovery()`/`destroy()` now each call `AuditLog::record()`, with before/after values captured on `update()` and `updateRecovery()`.

**Files changed:** `app/Http/Controllers/User/TaxRateController.php`

**Tests added:** `tests/Feature/TaxRateAuditLogTest.php` (4 tests — create, update with before/after rate values, delete, VAT-recovery-settings update).

## Documentation added

`docs/audit/INITIAL_AUDIT_REPORT.md`, `ACCOUNTING_CONTROL_MATRIX.md`, `ZATCA_COMPLIANCE_MATRIX.md`, `VAT_RECONCILIATION_GUIDE.md`, `CASH_AND_BANK_CONTROLS.md`, `INVOICE_NUMBERING_POLICY.md`, `SECURITY_AND_ACCESS_AUDIT.md`, this file, and `TEST_RESULTS.md`/`PRODUCTION_READINESS_REPORT.md` (written after the full regression run — see those files for the actual pass/fail counts).

## Explicitly not done this session (see parent report's Open Questions / deferred items)

Year-based invoice numbering reset, employee-advance tracking, negative-balance flagging, approval thresholds on Payment/Receipt Vouchers and Bills, a formal multi-period accounting-period entity, a general opening-balance mechanism, file-access-control redesign, a systematic Policy-layer rollout, inventory costing, and anything requiring the live production database or ZATCA merchant portal. None of these were silently skipped — each is named with its severity and reasoning in the parent report.
