# Cash & Bank Controls

Companion to `INITIAL_AUDIT_REPORT.md` §3. Describes what actually exists today, grounded in code, and what a company relying on this system for cash control should know.

## What actually moves cash in the real books

Only **PaymentVoucher**, **ReceiptVoucher**, **Expense**, and **BankAccount** transactions are posted to the General Ledger via `LedgerPostingService`. **`CashTracker`** (a feature added earlier in this engagement) is explicitly, intentionally **not** part of the real books — its own model doc comment states it "has no relation to Account, JournalEntry, or BankAccount; nothing here is posted or summed into the real accounting records," and it is "meant to be a freely correctable scratch ledger, not a second set of books." Its deletions carry no audit trail. **If your team is using CashTracker as "the" cash book, stop — it is not reconcilable against your financial statements and was never meant to be.**

## What's solid

- **Bank reconciliation is real**, not a placeholder: `BankReconciliation`/`BankStatementLine` give genuine matched/unmatched tracking, driven by `BankReconciliationController`.
- **Vouchers are correctly GL-linked**: every Payment/Receipt Voucher store/update/void injects `LedgerPostingService` and posts a real journal entry.
- **Attachments work**: both voucher types have real, correctly-scoped `morphMany` attachment relations — a receipt image or supporting document can be attached and retrieved.

## Real gaps (none fixed this session — see Open Questions in the parent report)

1. **No employee-advance tracking.** `party_type` on both vouchers is `manual`\|`customer`\|`supplier` — there is no `employee` option, no link to the `Employee` model, and no settlement/outstanding-advance workflow. An employee cash advance today can only be recorded as a free-text "manual" payee, with nothing stopping it from being forgotten, and nothing reporting "which employees currently owe the company money." **This is a real, High-severity gap** — but a multi-day build (new relation, settlement UI, aging report), not something to bolt on inside this engagement without your explicit scoping confirmation.
2. **Negative-balance detection — fixed.** `BankAccount::currentBalance() < 0` is now visibly flagged (red balance + a "Negative balance" badge) on the Cash & Banks index, the account-statements list, and the Cash Custody dashboard — wherever a balance was already being shown. Deliberately **visibility only, not a hard block**: `currentBalance()` itself is untouched and a Payment Voucher can still push it negative, since a real bank may allow it (overdraft, a same-day incoming transfer not yet recorded) and a hard block risks wrongly rejecting a legitimate transaction.
3. **Approval gate on Payment/Receipt Vouchers and Bills — fixed.** `Company::payment_voucher_approval_threshold`/`receipt_voucher_approval_threshold`/`bill_approval_threshold` (configured in Settings → Approvals, null by default — unchanged behavior until an owner opts in) mirror the existing Expense/PO/Manual-Journal pattern exactly. At or above the threshold: a Payment/Receipt Voucher is created as `pending_approval` with **no** bill/invoice payment recorded and **no** GL posting until explicitly approved; a Bill can still be saved as a draft, but posting it (immediately at creation or via the standalone Post button) routes it to `pending_approval` instead, with no GL entry and no stock receipt until approved. Rejecting either returns it to a dead-end (`rejected`) or back to `draft` (Bill) with no side effects ever having happened — there's nothing to undo. See `tests/Feature/VoucherAndBillApprovalThresholdTest.php`.

## What to tell your accountant / internal auditor

- Treat CashTracker as informal and exclude it entirely from any reconciliation against the GL or financial statements.
- Until the approval-threshold extension above is built, any material-sized Payment Voucher, Receipt Voucher, or Bill should be reviewed manually (e.g. via a periodic export/report) rather than assumed to have gone through sign-off.
- Employee cash advances currently require a separate manual tracking mechanism outside the software (a spreadsheet, or careful memo discipline on "manual" vouchers) until the dedicated feature above is built.
