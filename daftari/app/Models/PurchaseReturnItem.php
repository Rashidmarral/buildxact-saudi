<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReturnItem extends Model
{
    protected $fillable = [
        'purchase_return_id', 'bill_item_id', 'description', 'quantity',
        'unit_price', 'vat_rate', 'vat_amount', 'line_total',
    ];

    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    public function billItem(): BelongsTo
    {
        return $this->belongsTo(BillItem::class);
    }

    public function recalculate(): void
    {
        $lineSubtotal = $this->quantity * $this->unit_price;
        $this->vat_amount = round($lineSubtotal * ($this->vat_rate / 100), 2);
        $this->line_total = round($lineSubtotal + $this->vat_amount, 2);
    }
}
