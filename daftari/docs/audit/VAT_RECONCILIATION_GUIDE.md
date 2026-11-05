# VAT Reconciliation Guide

Companion to `INITIAL_AUDIT_REPORT.md`. This is a usage/reconciliation guide for Daftari's own VAT Report (Reports → Tax Report), grounded in what the report actually computes today — not a restatement of ZATCA's own filing rules, which a licensed Saudi accountant should confirm against your specific registration and filing circumstances.

## What the Output Tax figure actually is

`ReportController::vat()` computes Output Tax for a period as:

```
Output Tax = Σ(invoice.vat_total for invoices issued in the period, excluding draft/cancelled)
            + Σ(prepayment_invoice.vat_amount for Prepayment Invoices issued in the period)
            − Σ(vat_amount already recognized via AdvanceCreditApplication, for invoices in the period)
            − Σ(vat_amount already recognized via PrepaymentInvoiceApplication/the simple FK, for invoices in the period)
```

This was a real fix made earlier in this engagement (see `TaxReportTest.php`): without it, an invoice's own `vat_total` never shrinks when an advance is credited against it, so naively summing every invoice's `vat_total` would report the same VAT twice — once when the original advance was issued, again when the final invoice settling it was issued.

The report page itself shows this breakdown transparently (gross → adjustments → net) whenever either mechanism is in play — it does not hide the adjustment inside a single opaque number.

## Reconciling the report to your own records

1. **Sales register vs. GL.** `outputTaxFromInvoices` (the gross line in the breakdown) should equal the sum of `vat_total` across every non-draft/non-cancelled invoice in the period — cross-check against the Sales tab's own transaction table, which lists every row that sum is built from.
2. **GL VAT Output account vs. the report.** `LedgerPostingService::postInvoiceIssued()` posts the invoice's full (unreduced) `vat_total` to the VAT Output account at issuance — the same figure the report's gross line uses. The report's *net* figure (after advance/prepayment adjustment) will differ from the GL's running VAT Output balance by exactly the advance-credited amount, for as long as that advance remains unresolved in the GL. **This is a known, documented limitation**: the GL posting itself is not adjusted when an Advance Credit or Prepayment Invoice application is recorded — only the Report's own output-tax figure is. If you reconcile the VAT Output GL account directly against a VAT return, you must apply the same advance-credit/prepayment adjustment manually, or treat the Report's net figure (not the raw GL balance) as the return-ready number. **This is a gap worth a dedicated follow-up phase** — ideally the GL posting itself should be adjusted at the same point the Report is, so the two never diverge. Not fixed in this session; flagged here so it isn't mistaken for already solved.
3. **Input Tax vs. Purchases/Expenses.** `inputTaxPurchases` + `expenseTax` sum Bill.vat_total and Expense.vat_amount respectively, for non-draft/non-void/non-rejected rows in the period — cross-check against the Purchases/Expenses tabs' own transaction tables the same way.
4. **Apportionment, if enabled.** If `Company::vat_makes_exempt_supplies` is on, `VatApportionmentService` reduces recoverable input VAT by the exempt-sales ratio (or a manually-set `vat_recovery_percentage`). The report shows `taxableOutputSales`/`exemptOutputSales`/`recoveryPercentage` explicitly — reconcile these against your own exempt-supply classification before relying on the apportioned figure.
5. **Net Tax Position.** `outputTax − netRecoverableInputTax`. This is Daftari's own computed position for your own records — the report's own footer explicitly says "verify figures against your ZATCA VAT return before filing," and this guide does not change that advice.

## What this report is not

- It is not a VAT Return *filing* mechanism — there is a separate `VatReturnPeriodController` for recording filed-period positions, which this report does not auto-populate.
- It does not know about anything ZATCA itself has independently assessed, corrected, or disputed — it is built entirely from Daftari's own transaction records.
- A Saudi accountant or licensed auditor should review the apportionment settings, the exempt/zero-rated/not-subject classifications on your tax rates, and the GL-vs-Report divergence noted in point 2 above before this report is used as the sole basis for a filed return.
