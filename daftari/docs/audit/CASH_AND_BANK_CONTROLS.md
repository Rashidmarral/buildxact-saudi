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
2. **No negative-balance detection.** Nothing checks `BankAccount::currentBalance()` before or after a Payment Voucher posts, and the UI renders a negative balance with no distinct styling. The honest fix here is **visibility** (flag it prominently, e.g. in an exception dashboard — see the Accounting Control Matrix's deferred items), not a hard block that could wrongly reject a legitimate transaction your actual bank allows (an overdraft facility, a same-day incoming transfer not yet recorded, etc.). Not implemented this session.
3. **No approval gate on Payment/Receipt Vouchers or Bills.** Expenses and Purchase Orders already support a configurable approval threshold (`Company::expense_approval_threshold`/`po_approval_threshold`) — Vouchers and Bills have zero equivalent, meaning a supplier payment or a bill of any size can be recorded by anyone with voucher access, with no sign-off. This is the same class of control this session *did* add for Manual Journal Entries (see the Accounting Control Matrix, AC-GL-06) — extending the identical opt-in threshold pattern to Vouchers/Bills is the clear next phase, not done here because it touches three separate controllers and the business should confirm the threshold amounts first.

## What to tell your accountant / internal auditor

- Treat CashTracker as informal and exclude it entirely from any reconciliation against the GL or financial statements.
- Until the approval-threshold extension above is built, any material-sized Payment Voucher, Receipt Voucher, or Bill should be reviewed manually (e.g. via a periodic export/report) rather than assumed to have gone through sign-off.
- Employee cash advances currently require a separate manual tracking mechanism outside the software (a spreadsheet, or careful memo discipline on "manual" vouchers) until the dedicated feature above is built.
