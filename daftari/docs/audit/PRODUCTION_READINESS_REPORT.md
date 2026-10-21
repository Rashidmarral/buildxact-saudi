# Production Readiness Report

Final summary for this engagement, structured per the ten questions asked at the outset. Nothing here claims regulatory certification — code review alone cannot provide that, and this report says so explicitly wherever it applies.

**Revision note:** this report originally covered only the first implementation batch (ZATCA-05, GL-6, SEC-08). The user then asked to "fix all" remaining open findings; this revision covers the complete engagement across both batches, and corrects one finding (SEC-03) that was itself found to be inaccurate on re-verification while implementing it. See `IMPLEMENTATION_CHANGELOG.md` for the batch-by-batch diff-level record.

## 1. What was inspected

Direct source inspection (controllers, models, services, migrations, existing tests) across five areas, each with file:line evidence for every claim: General Ledger posting integrity and decimal precision; invoice/document numbering, concurrency safety, and issued-document immutability; cash and bank accounting controls; tenant isolation, authorization, mass assignment, and audit-log coverage; and the real current state of the ZATCA Phase 2 e-invoicing stack. See `INITIAL_AUDIT_REPORT.md` for the full findings register and `ACCOUNTING_CONTROL_MATRIX.md`/`ZATCA_COMPLIANCE_MATRIX.md` for the control-by-control detail.

## 2. What defects were found

Full list in `INITIAL_AUDIT_REPORT.md`; the material ones, with final status:

- **High:** Manual journal entries had no approval gate (GL-6, **fixed**). Payment/Receipt Vouchers and Bills had no approval gate either (CASH-06, **fixed**). No employee-advance tracking existed (CASH-02, **fixed**).
- **Medium-High:** No guard against resubmitting an already-cleared document to ZATCA (ZATCA-05, **fixed**).
- **Medium:** No negative-balance detection (CASH-03, **fixed**); no formal accounting-period entity, just a single rolling lock date (GL-4, **fixed** — closure history added, the lock-date mechanism itself intentionally left alone); no general opening-balance mechanism (GL-7, **fixed for GL accounts**, customer/supplier balances deliberately deferred — see §7); tax-rate changes had zero audit trail (SEC-08, **fixed**); invoice numbering has no year-reset logic (NUM-05, a policy decision — **you chose "reset each fiscal year"; built**).
- **Low/Informational:** float arithmetic in GL posting instead of BCMath (GL-2, **evaluated, not rewritten** — see §7); single-layer tenant isolation with no Policy layer (SEC-01, **evaluated, not rewritten** — see §7); file access control (SEC-03, **this finding was itself wrong** — the access check already existed pre-session; the real gap, a missing download audit trail, is **fixed**).
- **Strengths confirmed, not defects:** double-entry enforcement, duplicate-posting prevention, GL-derived financial statements with a self-balancing check, race-safe number allocation, DB-level uniqueness, issued-document immutability, real bank reconciliation, a ZATCA onboarding/crypto stack with genuine historical evidence of real-world debugging against actual ZATCA rejections.

## 3. What was implemented

**Batch 1** (three fixes):

1. **ZATCA-05** — resubmitting an already-cleared/reported invoice, prepayment invoice, credit note, or debit note is now rejected before any XML is built or any ICV/PIH chain value advances.
2. **GL-6** — an opt-in, nullable `manual_journal_approval_threshold` on Company; entries at/above it are staged in a new `PendingManualJournalEntry` table and only reach the real ledger once explicitly approved. Default (`null`) leaves every company's current behavior completely unchanged.
3. **SEC-08** — tax-rate create/update/delete and VAT recovery-settings changes are now audit-logged, with before/after values on updates.

Two suspected SEC-08 gaps (manual journal logging, ZATCA settings logging) were verified to already exist and were *not* re-implemented; a third ("user role changes") was found to not be a logging gap at all, since the underlying feature doesn't exist.

**Batch 2** ("fix all" — everything else that was safely buildable):

4. **NUM-05** — `Company::document_numbering_resets_yearly` (opt-in, off by default): when on, each document type's counter resets to `00001` at the start of every fiscal year instead of running as one lifetime count.
5. **CASH-06** — the same opt-in threshold/approval pattern as GL-6, added to Payment Vouchers, Receipt Vouchers, and Bills independently (`payment_voucher_approval_threshold`, `receipt_voucher_approval_threshold`, `bill_approval_threshold`).
6. **CASH-03** — Bank Accounts and Account Statements pages now flag a negative balance distinctly; no transaction is blocked.
7. **GL-7** (GL accounts only) — a one-time "Opening Balances" screen posts a single balanced (or equity-plugged) journal entry; guarded so it can only run once per company.
8. **GL-4** — a new `period_closures` table records who closed/reopened the accounting period, when, and why (reopen only) — layered on top of the existing `accounting_lock_date`/`PeriodLockedException` mechanism, which was not rewritten.
9. **CASH-02** — `employee` added as a fourth party type on both vouchers; `Employee::totalAdvanced()/totalSettled()/outstandingAdvanceTotal()`; a new Reports → Employee Advances page.
10. **SEC-03** — found the finding itself was wrong (the tenant-isolation check already existed, fixed pre-session under CRIT-01); added the one genuinely missing piece, an `AuditLog` entry on every attachment download.

**Evaluated, deliberately not rewritten** (see §7 for why): SEC-01 (Policy-layer authorization), GL-2 (BCMath arithmetic).

## 4. Files changed

Batch 1: `app/Models/Company.php`, `app/Http/Controllers/User/ManualJournalEntryController.php`, `app/Http/Controllers/User/TaxRateController.php`, `app/Services/Zatca/ZatcaSyncService.php`, `routes/web.php`, `resources/views/user/journals/index.blade.php`; new `app/Models/PendingManualJournalEntry.php`, `resources/views/user/journals/pending-index.blade.php`.

Batch 2: `app/Models/Company.php` (further additions), `app/Models/Employee.php`, `app/Models/PaymentVoucher.php`, `app/Models/ReceiptVoucher.php`, `app/Models/PeriodClosure.php` (new), `app/Http/Controllers/User/PaymentVoucherController.php`, `ReceiptVoucherController.php`, `BillController.php`, `ApprovalSettingsController.php`, `OpeningBalanceController.php` (new), `ReportController.php`, `app/Http/Controllers/FileServeController.php`; views for approvals settings, bills/vouchers show pages, bank-accounts/account-reports index, opening-balances/create, reports/employee-advances (new); `routes/web.php`. Full detail in `IMPLEMENTATION_CHANGELOG.md`.

## 5. Migrations added

Batch 1: `2026_10_23_010000_add_manual_journal_approval_threshold_to_companies_table.php`, `2026_10_23_020000_create_pending_manual_journal_entries_table.php`.

Batch 2: `2026_10_24_010000_add_yearly_document_numbering_reset_to_companies_table.php`, `2026_10_24_020000_add_approval_workflow_to_vouchers_and_bills.php`, `2026_10_24_030000_create_period_closures_table.php`, `2026_10_24_040000_add_employee_id_to_vouchers_table.php`.

All are additive (new nullable column or new table) — no existing column is altered or dropped, and no existing row is touched. **Not yet applied to your production database** — run `php artisan migrate` on your real system to apply them, same as every other migration from this engagement.

## 6. Tests run and actual results

Full detail in `TEST_RESULTS.md`. Headline: **full suite — 1815 passed, 2 failed, 6023 assertions.** The 2 failures (`DashboardVatDueBannerTest`, `SettingsSecurityAuditTest`) are pre-existing and unrelated — they have failed identically in every full-suite run across this entire engagement, before any of this session's changes. New tests across both batches, all passing, no existing test altered to force a pass: `YearlyDocumentNumberingTest`, `VoucherAndBillApprovalThresholdTest`, `NegativeBalanceFlagTest`, `OpeningBalanceTest`, `EmployeeAdvanceTrackingTest`, `AttachmentDownloadAuditTest`, plus rewritten/extended coverage in `AccountingPeriodLockTest`.

## 7. What remains incomplete

Named explicitly, not silently dropped:

- **Customer/supplier opening balances** (part of GL-7) — deliberately not built; their aging/balance is derived from real Invoice/Bill rows, not a ledger field, so a GL-only credit to the AR/AP control account would desync it. Needs a dedicated mechanism and your confirmation on how it should appear on a customer's own statement — see `INITIAL_AUDIT_REPORT.md` §8.
- **SEC-01 — Policy-layer authorization rollout.** Evaluated, not attempted: tenant isolation today is a single global Eloquent scope (`BelongsToCompany`), with 163 controllers and ~42 `withoutGlobalScopes()` call sites in the app. No proven cross-tenant leak was found in this engagement's spot-checks. A proper Policy layer is the right long-term investment, but doing it as a rushed pass across most of the controller layer — without dedicating real review time to each one — would risk introducing authorization bugs more serious than the single-layer status quo it replaces. Recommended as its own dedicated phase, not folded into this one.
- **GL-2 — BCMath migration for GL posting arithmetic.** Evaluated, not attempted: `LedgerPostingService.php` alone has 41 native-float call sites, and the pattern repeats across the reporting services. Every comparison already rounds to 2dp with a 0.01 tolerance before storing into a `decimal(14,2)` column, which is the main practical safeguard against float drift — real-world risk is genuinely low today. Swapping the arithmetic layer under the entire posting engine without a dedicated regression pass against real multi-currency/high-volume data is a correctness-risk trade that isn't proportionate to that low measured risk. Recommended as its own dedicated phase if you want it done.
- **Attachment download links don't expire or get revoked** (the one real residual piece of SEC-03) — the access-control check is solid; a link that was once valid stays valid for as long as the attachment exists. Would need signed/expiring URLs, a larger change across ~13 attachment-serving views.
- Everything out of this engagement's original scope entirely: inventory costing, manufacturing/BOM, time tracking (flagged in an earlier session audit, not addressed here).

## 8. Is the system ready for internal accounting use?

**Yes, with narrower caveats than before this engagement.** The core double-entry engine is genuinely sound — balanced-entry enforcement, duplicate-posting prevention, GL-derived financial statements with a self-check, race-safe document numbering, and immutable issued invoices are all verified, not assumed. Material cash movements (Vouchers, Bills, manual journals) now have an opt-in approval gate available, mirroring the one Expenses/POs already had — you still need to actually configure a threshold for it to take effect, since every new control defaults to off. The real remaining caveats: (a) CashTracker must not be treated as part of the real books; (b) a Saudi accountant should review the VAT apportionment settings and the GL-vs-VAT-Report reconciliation gap noted in `VAT_RECONCILIATION_GUIDE.md` before relying on the Tax Report as filing-ready without adjustment; (c) customer/supplier opening balances for a mid-year onboarding still need a manual workaround until that policy question is resolved (§7).

## 9. Is the system ready for official ZATCA production invoicing, based on verified evidence?

**This cannot be answered from code alone, and this report does not claim it can.** What's verified: the onboarding flow matches ZATCA's real published endpoints, credentials are encrypted at rest, the hash chain is correctly serialized and only advances on confirmed success, and the cryptographic signing has real historical evidence of being debugged against actual ZATCA compliance-check rejections — stronger evidence than most codebases of this kind. What's **not verifiable from this repository**: whether this specific company has completed production CSID issuance, or whether any real invoice has ever actually been cleared or reported in ZATCA's production environment. All HTTP-calling tests use hand-built fakes, not captured real responses. **This is the single most important open item in this entire report** — it requires checking your live `companies` and `zatca_invoice_logs` tables and your ZATCA merchant portal directly, which no amount of code review can substitute for.

## 10. What must a Saudi accountant, licensed auditor, or ZATCA specialist verify before relying on this system

- Whether your company has actually completed ZATCA Phase 2 production onboarding and cleared a real invoice in production (item 9 above).
- Whether your VAT apportionment settings (if you make exempt supplies), tax-rate classifications (zero-rated/exempt/not-subject reason codes), and the GL-vs-VAT-Report reconciliation gap in `VAT_RECONCILIATION_GUIDE.md` are correct for your specific filing circumstances.
- Whether the year-reset invoice numbering you chose (`INVOICE_NUMBERING_POLICY.md`) matches your actual regulatory/internal requirement — this is now built and tested, but the choice itself was yours, not a code-derived fact.
- Whether you actually want to turn on the new Voucher/Bill/manual-journal approval thresholds and employee-advance workflow in production (they exist and are tested, but ship off by default) — and at what amount.
- A proper penetration test / security review of the single-layer tenant-isolation architecture, ideally by someone independent of this engagement — this report's own spot-checks found no active leak, which is not the same as an independent review finding none.
- Current ZATCA certificate expiry status, and your process discipline around never routing a test invoice to production or vice versa (code allows both; only your own process prevents it).
