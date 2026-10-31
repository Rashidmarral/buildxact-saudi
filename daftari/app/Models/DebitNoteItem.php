<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DebitNoteItem extends Model
{
    protected $fillable = [
        'debit_note_id', 'unit_id', 'tax_rate_id', 'description', 'quantity',
        'unit_price', 'vat_rate', 'vat_amount', 'line_total',
    ];

    public function debitNote(): BelongsTo
    {
        return $this->belongsTo(DebitNote::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @see InvoiceItem::taxRate() */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function recalculate(): void
    {
        $lineSubtotal = $this->quantity * $this->unit_price;
        $this->vat_amount = round($lineSubtotal * ($this->vat_rate / 100), 2);
        $this->line_total = round($lineSubtotal + $this->vat_amount, 2);
    }
}
