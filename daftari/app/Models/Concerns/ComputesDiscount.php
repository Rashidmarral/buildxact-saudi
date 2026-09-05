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

        return round((float) $this->discount_value, 2);
    }
}
