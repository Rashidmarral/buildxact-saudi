# Daftari — Initial Accounting & Compliance Audit Report

**Date:** 2026-10-21
**Scope:** Accounting/GL integrity, invoice numbering & document immutability, cash/bank controls, security/tenant isolation/audit logging, ZATCA Phase 2 compliance.
**Method:** Direct source inspection (controllers, models, services, migrations, existing tests) with file:line evidence for every finding below. No finding in this report is inferred from naming conventions or assumed from a menu/feature existing — each was read and verified, or explicitly marked as unverifiable from code alone.
**Environment inspected:** Laravel ^12.0, PHP ^8.2, this sandbox runs on sqlite for its own test suite (the real production system runs MySQL — see the migration index-length bug fixed earlier this session, which sqlite never would have caught).

This report does not assert regulatory certification. Sections marked **[NEEDS LIVE ZATCA VERIFICATION]** or **[NEEDS SPECIALIST REVIEW]** require evidence this sandbox cannot produce (the real production database, ZATCA merchant portal, or a licensed Saudi accountant/auditor's judgment).

---

## 1. General Ledger / Posting Integrity

| ID | Severity | Finding | Evidence |
|---|---|---|---|
| GL-1 | — (strength) | Double-entry is enforced before any write: `post()`/`postManual()` throw `InvalidArgumentException` if debit/credit totals differ by more than 0.01, *before* the DB transaction opens. | `app/Services/Accounting/LedgerPostingService.php:44-65,102-119` |
| GL-2 | Medium | Posting arithmetic uses native PHP floats (`(float)` casts), not BCMath/a Decimal type. Storage is correctly `decimal(14,2)` and every value is rounded before compare/store with a 0.01 tolerance, so real-world risk is low — but it is not textbook-safe arithmetic. | `LedgerPostingService.php:60-61,254-259`; migration `0001_01_23_000000_create_journal_entries_table.php:33-34` |
| GL-3 | — (strength) | Duplicate posting is actively prevented: `post()` checks `alreadyPosted()` (keyed on `company_id`+`source_type`+`source_id`) before writing anything; a retried job or double-submit is a safe no-op. | `LedgerPostingService.php:52-54,191-197` |
| GL-4 | Medium | Period lock exists but is minimal: one rolling `Company::accounting_lock_date`, not a formal period/fiscal-year entity with named approver or reopen trail. It is real and enforced (`PeriodLockedException`), and changes to it are audit-logged. | `LedgerPostingService.php:56-58,110-112`; `Company.php:47,151`; `ApprovalSettingsController.php:86-102` |
| GL-5 | — (confirmed correct) | Credit/debit notes do not reverse the original invoice's journal entry — each posts its own fresh, correctly-signed entry. This is the accounting-correct pattern, not a defect. | `LedgerPostingService.php:310-333,339-361` |
| **GL-6** | **High** | **Manual journal entries have no maker-checker approval at all.** `ManualJournalEntryController::store()` validates and posts immediately via `postManual()` — no draft/pending state, no second-person sign-off, gated only by a flat `permission:accounting`. This is the same class of gap as Invoice-only approval chains found in an earlier session audit, but worse: this is a *direct* GL posting, the most sensitive operation in the system. | `app/Http/Controllers/User/ManualJournalEntryController.php:26-56`; `routes/web.php` (`permission:accounting` group) |
| GL-7 | Medium | No general opening-balance mechanism for GL accounts, customers, or suppliers. `opening_balance` exists only on `BankAccount`. A mid-year onboarding company has no safeguarded way to enter opening balances. | `app/Models/BankAccount.php`; absence confirmed elsewhere |
| GL-8 | — (strength) | Trial Balance, Balance Sheet, and Income Statement are genuinely derived live from posted `JournalEntryLine` rows, not separately maintained totals. Balance Sheet exposes a real `'balanced'` self-check (Assets = Liabilities + Equity). | `app/Services/Reports/FinancialReportService.php:23-132` |

**Remediated this session:** GL-6 (see §7 below — opt-in approval threshold added, mirroring the existing Expense/PO pattern).

---

## 2. Invoice Numbering & Document Immutability

| ID | Severity | Finding | Evidence |
|---|---|---|---|
| NUM-01 | — (strength) | Number allocation is race-safe: `Company::nextSequenceNumber()` wraps the read+increment in `DB::transaction()` + `lockForUpdate()`, held for the full enclosing invoice-creation transaction. Every document type's `next*Number()` routes through this. | `Company.php:315-466`; `InvoiceController.php:188-202` |
| NUM-02 | — (strength) | Real DB-level unique constraints on `(company_id, invoice_number)` and equivalents for quotations/credit notes/debit notes/prepayment invoices — not application-logic-only. | respective `create_*_table` migrations |
| NUM-03 | — (strength) | Issued-invoice immutability is enforced at the controller action itself (`edit`/`update`/`destroy`/`cancel` all check `isZatcaLocked()` then `status !== 'draft'`), re-checked independently inside `update()` in case it's reached directly. Drafts can be deleted; issued invoices cannot. | `InvoiceController.php:437-535,~690` |
| NUM-04 | — (strength) | Credit/debit notes cannot be issued against an invoice ZATCA has never actually cleared/reported — `BillingReference` only ever points to a real cleared document. | `tests/Feature/ZatcaCorrectionDocumentGuardsTest.php` |
| **NUM-05** | **Medium (policy decision, not a code defect)** | `nextInvoiceNumber()` is `{prefix}-{5-digit lifetime counter}` with **no year component and no reset logic**. Switching `invoice_prefix` to `"INV-2026"` is safe — it will not rename INV-00001..4, will not violate the unique constraint, and has **no ZATCA impact** (ZATCA's real document identity is the UUID + ICV/PIH chain, not the human-readable number). But if the intent is "00005 = 5th invoice of 2026," that reset logic does not exist today — the counter would continue its lifetime count under the new prefix. **This needs your decision before I build it** — see open question in §8. | `Company.php:327-332` |

---

## 3. Cash & Bank Controls

| ID | Severity | Finding | Evidence |
|---|---|---|---|
| CASH-01 | Low (by design) | `CashTracker` is explicitly documented as a scratch ledger, intentionally **not** posted to the real GL and with no audit trail on its own deletions. Real cash/bank only flows through PaymentVoucher/ReceiptVoucher/Expense/BankAccount. Risk is user confusion, not a hidden bug. | `app/Models/CashTracker.php:9-16`; `CashTrackerController.php:84,93` |
| **CASH-02** | **High** | **No employee-advance concept exists.** `party_type` on Payment/Receipt Vouchers is `manual`\|`customer`\|`supplier` only — no `employee`, no link to the `Employee` model, no settlement/outstanding-advance tracking. An employee cash advance can only be recorded as free-text. **Large build — deferred, flagged for next phase.** | `PaymentVoucherController.php:454`; `ReceiptVoucherController.php:405` |
| **CASH-03** | **High** | **Nothing detects or flags a negative bank/cash balance.** `PaymentVoucherController::store()` posts to the ledger with no check against `BankAccount::currentBalance()` before or after; the UI renders the balance with no distinct styling if negative. **Deferred to the exception-dashboard phase** (§8) rather than a point fix, since the honest fix is visibility, not a hard block that could wrongly reject a legitimate transaction. | `PaymentVoucherController.php:164-226`; `BankAccount.php:81-92`; `resources/views/user/bank-accounts/index.blade.php:26` |
| CASH-04 | — (strength) | Bank reconciliation is a real, implemented feature (matched/unmatched tracking), not just a balance field. | `app/Models/BankReconciliation.php:43-51`, `BankStatementLine` |
| CASH-05 | — (strength) | Attachments on vouchers are real, correctly-scoped `morphMany` relations. | `PaymentVoucher.php:67-70`, `ReceiptVoucher.php:62-65` |
| **CASH-06** | **High** | **Payment/Receipt Vouchers and Bills have zero approval gate of any kind** — no threshold check at all (confirmed: zero matches for "threshold"/"approval" in either controller). Expenses and Purchase Orders *do* have a real threshold-based approval chain (`Company::expense_approval_threshold`/`po_approval_threshold`) — this corrects an earlier session's audit which assumed Expenses had none; they do. The gap is specifically direct cash movement and Bills. **Large build — same class as GL-6 but broader; deferred, flagged for next phase.** | `PaymentVoucherController.php`; `ReceiptVoucherController.php`; `BillController.php`; `Company.php:47-48,519`; `ExpenseController.php:85,213,247` |
| CASH-07 | — (strength) | Real vouchers (Payment/Receipt) ARE correctly posted to the GL via `LedgerPostingService`. Only the intentionally-informal `CashTracker` bypasses it. | `PaymentVoucherController.php:221,294,389` |

---

## 4. Security, Tenant Isolation & Audit Logging

| ID | Severity | Finding | Evidence |
|---|---|---|---|
| SEC-01 | Informational | Tenant isolation is single-layer: a global Eloquent scope (`BelongsToCompany`) auto-filters by `Auth::user()->company_id`, active only when `Auth::check()`. **No `app/Policies/` directory exists at all** — no defense-in-depth beyond the scope. ~100 `withoutGlobalScopes()` call sites were spot-checked; every one found was a legitimate cross-company platform-admin path, an unauthenticated job/command (where the scope is already inert), or a public share-link controller. **No proven active leak was found**, but the architecture has a single point of failure rather than two independent checks. | `app/Models/Concerns/BelongsToCompany.php:12-17` |
| SEC-02 | — (strength) | Public invoice/quotation share links and attachment storage rely on unguessable, high-entropy random tokens compared via `hash_equals` — an acceptable pattern. | `PublicInvoiceController`/`PublicQuotationController` |
| SEC-03 | Low/Medium | Files on the `public` disk (receipts, attachments — potentially containing bank statements, contracts, personal data) have no authorization check once the URL is known — security rests entirely on filename entropy, with no expiry/revocation/access trail. | storage disk usage across attachment controllers |
| SEC-04 | — (strength, already remediated pre-session) | Invoice deletion is well-guarded: ZATCA-locked invoices can never be deleted; only drafts can be; draft deletion is logged. | `InvoiceController.php:540` |
| SEC-05 | — (strength) | Sensitive fields (`status`, `approved_by`, `approved_at`) are never mass-assigned from raw request input despite being `$fillable` — approval actions set them explicitly in dedicated methods. | `ExpenseController.php:235,263` |
| SEC-06 | — (strength) | API tokens are actually ability-checked (`abilities:read`/`abilities:write` middleware), not just issued and ignored. | `routes/api.php:19,68` |
| SEC-07 | Confirmed exists | 2FA is real, with failed/successful attempts logged. Whether it's enforceable per-role vs. always-optional was not verified in this pass. | `TwoFactorChallengeController` |
| **SEC-08** | **Medium-High** | **Audit log coverage has real gaps.** `AuditLog::record()` covers auth, invoice create/delete, bill create/void, expense approve/reject, impersonation, and more — but a scan found **no logging** for manual journal entry creation, tax-rate configuration changes, user role changes, or ZATCA credential/settings changes. (API token creation/revocation and a full enumeration were not completed within the inspection budget — treat as unverified, not confirmed-absent, beyond what's fixed below.) | grep across `app/` for `AuditLog::record(` |

**Remediated this session:** SEC-08's four confirmed gaps (manual journal entries, tax-rate changes, user role changes, ZATCA credential/settings changes) — see §7.

---

## 5. ZATCA Phase 2 Compliance

| ID | Severity | Finding | Tag |
|---|---|---|---|
| ZATCA-01 | — (strength) | Onboarding is a real 3-step flow against ZATCA's actual published endpoints (compliance CSR+OTP → compliance/invoices validation → production/csids), not invented. | [CODE-VERIFIED] |
| ZATCA-02 | Medium | Environment separation (dev/simulation/production) is real and company-configured; demo companies are hard-blocked. Nothing in code prevents a production-onboarded company from routing a *test* invoice to production — that's an operational control, not a code one. | [CODE-VERIFIED] / [NEEDS SPECIALIST REVIEW] |
| ZATCA-03 | — (strength) | ZATCA private keys and CSID secrets use Laravel's `encrypted` cast — no plaintext storage found. | [CODE-VERIFIED] |
| ZATCA-04 | — (strength) | Invoice hash chain (PIH) and ICV counter only advance on confirmed ZATCA success, inside a per-company row lock — a failed attempt cannot corrupt the chain for the next real submission. | [CODE-VERIFIED] |
| **ZATCA-05** | **Medium-High** | **No idempotency guard against resubmitting an already-cleared/reported invoice.** `ZatcaController::syncInvoice()` never checks `isZatcaLocked()` before calling `submit()`. A double-click or retry-after-slow-response mints a new UUID/hash, advances the ICV, and fires a second live clearance/report call for the same invoice. | [CODE-VERIFIED — real gap] |
| ZATCA-06 | — (strength, stronger than typical) | Cryptographic signing (XAdES, QR stamp) has **real debugging history against actual ZATCA compliance-check rejections** — not just internally self-consistent code. One fix was verified byte-for-byte against a genuine ZATCA-accepted invoice's own declared digest. | [CODE-VERIFIED] |
| **ZATCA-07** | **Unverifiable from code** | No evidence in this repository of a completed **production** CSID issuance or a real invoice actually cleared in production — all HTTP-calling tests use hand-built fakes, not captured real responses. This can only be confirmed by inspecting the live production `companies`/`zatca_invoice_logs` tables and the ZATCA merchant portal directly. | **[NEEDS LIVE ZATCA VERIFICATION]** |

**Remediated this session:** ZATCA-05 — see §7.

---

## 6. What This Report Does Not Cover

Per the engagement scope, the following were explicitly deferred to later phases (tracked in the project task list) and are **not** claimed as audited here: Quotation/CreditNote/DebitNote edit-lock enforcement in equal depth to Invoice; full enumeration of every `AuditLog::record()` call site; Policy-equivalent authorization on every controller individually; VAT line-level classification correctness (zero-rated/exempt/reverse-charge) in depth; fixed-asset depreciation correctness; inventory costing (already flagged as missing entirely in an earlier session audit); and anything requiring the live production database or ZATCA merchant portal.

---

## 7. Fixes Implemented This Session (Phase 2)

See `docs/audit/IMPLEMENTATION_CHANGELOG.md` for the full diff-level record. Summary:

1. **ZATCA-05** — `ZatcaController::syncInvoice()`/bulk sync now reject an already-`isZatcaLocked()` invoice before calling `ZatcaSyncService::submit()`, with a clear flash message instead of a silent second live submission.
2. **GL-6** — Added `Company::manual_journal_approval_threshold` (nullable, opt-in — `null` preserves today's exact behavior for every company that hasn't configured it). When set, a manual journal entry whose total meets or exceeds it is created as `pending_approval` instead of posted immediately, and requires an explicit approve action (mirrors the existing `Expense`/`PurchaseOrder` threshold pattern exactly).
3. **SEC-08** — Added the four confirmed-missing `AuditLog::record()` calls: manual journal entry creation, tax-rate changes, user role changes, ZATCA credential/settings changes.

## 8. Open Questions Requiring Your Decision (Not Implemented — Policy, Not Code)

- **NUM-05**: Do you want `INV-2026-00005` to mean "continuing the lifetime counter under a new prefix" (safe today, zero build) or "the counter resets each fiscal year" (a real, separate feature)? I have not built year-reset logic without this decision.
- **CASH-06 / GL-6 scope**: the maker-checker threshold I added for manual journals is opt-in and defaults to off. Do you want me to extend the same pattern to Payment/Receipt Vouchers and Bills (CASH-06) as the next phase, and if so, what threshold?
- **CASH-02**: employee cash advances with real settlement tracking is a genuine gap but a multi-day build (new relation, settlement workflow, reporting). Confirm you want this before I scope it.
