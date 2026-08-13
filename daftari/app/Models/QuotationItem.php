<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    protected $fillable = [
        'quotation_id', 'item_id', 'description', 'quantity', 'unit_price',
        'vat_rate', 'vat_amount', 'line_total', 'sort_order',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function recalculate(): void
    {
        $lineSubtotal = $this->quantity * $this->unit_price;
        $this->vat_amount = round($lineSubtotal * ($this->vat_rate / 100), 2);
        $this->line_total = round($lineSubtotal + $this->vat_amount, 2);
    }
}
