<?php

namespace App\Models\Concerns;

/**
 * Shared by every document that carries a header-level discount (Invoice,
 * Quotation, Bill, PurchaseOrder, RecurringInvoice): discount_type/
 * discount_value are the raw user input (a flat amount, or a percentage of
 * the subtotal); discount_total is the derived absolute amount everything
 * downstream (ZATCA XML, ledger posting, totals, reports, PDFs) already
 * expects and must stay in that shape — never trusted as raw client input,
 * always recomputed here from type+value+subtotal.
 */
trait ComputesDiscount
{
    protected function computeDiscountTotal(float $subtotal): float
    {
        if ($this->discount_type === 'percentage') {
            $percent = max(0.0, min(100.0, (float) $this->discount_value));

            return round($subtotal * $percent / 100, 2);
        }

        // A fixed amount is free-typed, so nothing upstream stops an
        // operator from entering more than the document is actually worth
        // (e.g. a fat-fingered extra digit) — clamped to the subtotal so a
        // discount can zero a document out but never push its total
        // negative through the ZATCA XML, PDF, and ledger posting that all
        // trust this value downstream.
        $flat = max(0.0, (float) $this->discount_value);

        return round(min($flat, $subtotal), 2);
    }

    /**
     * QA audit finding: every recalculateTotals() using this trait summed
     * each line's own vat_amount — computed from that line's full,
     * pre-discount price — straight into vat_total, so a header-level
     * discount reduced the document's total but left VAT exactly as if
     * no discount had ever been applied (SAR 100 less a SAR 10 discount
     * at 15% kept charging VAT 15 instead of the correct VAT 13.50 on the
     * SAR 90 actually-taxable amount).
     *
     * Reducing vat_total only at the header level (rather than rewriting
     * each line's own persisted vat_amount) is the correct ZATCA UBL
     * treatment for a document-level discount in the first place — it's
     * reported as its own cac:AllowanceCharge with its own tax category,
     * not by retroactively changing each line's LineExtensionAmount or
     * TaxTotal. $vatBeforeDiscount/$subtotal is exactly the document's
     * blended VAT rate (correct even with mixed-rate lines, since
     * $vatBeforeDiscount = Σ(line_subtotal × its own rate)), so scaling it
     * by the post-discount share of the subtotal allocates the discount's
     * tax impact across every tax category in exactly the same proportion
     * it was taxed in — the standard "discount allocated proportionally
     * across tax categories" treatment.
     */
    protected function discountedVatTotal(float $subtotal, float $vatBeforeDiscount, float $discountTotal): float
    {
        if ($subtotal <= 0.0) {
            return 0.0;
        }

        return round($vatBeforeDiscount * ($subtotal - $discountTotal) / $subtotal, 2);
    }
}
