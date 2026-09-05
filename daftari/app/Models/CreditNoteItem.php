<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNoteItem extends Model
{
    protected $fillable = [
        'credit_note_id', 'invoice_item_id', 'unit_id', 'description', 'quantity',
        'unit_price', 'vat_rate', 'tax_rate_id', 'vat_amount', 'line_total',
    ];

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    /** @see InvoiceItem::taxRate() */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
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
