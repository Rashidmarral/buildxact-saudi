<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id', 'item_id', 'unit_id', 'description', 'quantity', 'unit_price',
        'vat_rate', 'tax_rate_id', 'vat_amount', 'line_total', 'sort_order',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Which managed TaxRate (Settings → Tax rates) this line's vat_rate
     * came from, if any — lets downstream code (e.g. ZATCA XML generation)
     * tell zero-rated and exempt apart, since both charge 0% but are
     * different legal categories. Null on older rows created before tax
     * rate management existed; every calculation still works off vat_rate
     * alone regardless.
     */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function recalculate(): void
    {
        $lineSubtotal = $this->quantity * $this->unit_price;
        $this->vat_amount = round($lineSubtotal * ($this->vat_rate / 100), 2);
        $this->line_total = round($lineSubtotal + $this->vat_amount, 2);
    }
}
