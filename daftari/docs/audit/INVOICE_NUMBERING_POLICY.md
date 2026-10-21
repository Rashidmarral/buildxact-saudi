# Invoice Numbering Policy

Companion to `INITIAL_AUDIT_REPORT.md` §2. Answers, directly and with evidence, the question that prompted this document: can `INV-00001` through `INV-00004` safely coexist with a new `INV-2026-00005`-style format?

## How numbering actually works today

`Company::nextSequenceNumber()` (`app/Models/Company.php:315-324`) allocates every document number — invoice, quotation, credit note, debit note, prepayment invoice — inside a `DB::transaction()` with `lockForUpdate()`, serializing concurrent requests on the counter row. `InvoiceController::store()` calls it inside its own enclosing transaction, so the lock covers the entire invoice-creation operation, not just the counter bump. This is race-safe under concurrent requests — verified directly, not assumed.

The format itself (`Company::nextInvoiceNumber()`, `Company.php:327-332`) is:

```
{invoice_prefix}-{5-digit zero-padded lifetime counter}
```

A single, ever-incrementing integer. **There is no year component and no reset-per-year logic anywhere in the codebase.**

## Can you switch to `INV-2026-00005`?

**Yes, safely, with zero code changes, if you mean "continue numbering from where you are, under a new prefix."**

Changing `Company::invoice_prefix` from `INV` to `INV-2026` (or however you want the year embedded) is safe because:

1. **Existing invoices are untouched.** `INV-00001` through `INV-00004` keep their exact historical `invoice_number` — nothing renames them. Issued-invoice immutability is enforced at the controller level (see §2 of the parent report) independently of any prefix change.
2. **No uniqueness conflict.** The DB-level unique constraint is on `(company_id, invoice_number)` as a full string — `INV-2026-00005` and `INV-00005` (if it existed) are different strings and would never collide.
3. **No ZATCA impact either way.** ZATCA's actual document identity for e-invoicing purposes is the UUID plus the ICV/PIH hash chain (`cbc:UUID`, `icvReference()` in `ZatcaXmlGenerator`), not the human-readable invoice number. ZATCA does not mandate any particular visible-number format or require it to reset annually.

**But if you mean "00005 should mean the 5th invoice issued in 2026" — i.e. the counter resets each fiscal year — that logic does not exist today.** A prefix-only change would produce `INV-2026-00005`, then `INV-2026-00006`, `00007`... continuing the same lifetime count under the new prefix forever, not a fresh count starting at `00001` each January.

## Open decision (not implemented — needs your confirmation)

Building real year-scoped numbering (reset the counter on fiscal-year rollover, keyed per company and, if relevant, per branch) is a legitimate, moderate-sized feature — but it's a policy decision with real consequences (e.g., what happens to a sequence that spans a year boundary mid-transaction, whether branches share or have independent sequences) that the business should decide explicitly before it's built silently. This was deliberately **not implemented** in this engagement pending that decision — see the parent report's Open Questions section.

## What's already solid (verified, no action needed)

- Real DB-level unique constraints on every document-number column, not application-logic-only.
- Issued invoices cannot be silently edited or deleted — enforced at the controller action itself, independently re-checked in `update()` in case it's reached directly, and proven by `InvoiceEditLockTest`.
- Credit/debit notes cannot be issued against an invoice ZATCA has never actually cleared or reported — their `BillingReference` only ever points to a real, resolvable document (`ZatcaCorrectionDocumentGuardsTest`).
