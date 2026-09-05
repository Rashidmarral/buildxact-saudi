<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id', 'item_id', 'unit_id', 'description', 'quantity', 'unit_price',
        'vat_rate', 'vat_amount', 'line_total', 'sort_order',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function billItems(): HasMany
    {
        return $this->hasMany(BillItem::class);
    }

    /**
     * How much of this line has already been billed — void bills don't
     * count, since a voided bill never actually received/committed to
     * anything against the order.
     */
    public function billedQuantity(): float
    {
        return (float) $this->billItems()
            ->whereHas('bill', fn ($q) => $q->where('status', '!=', 'void'))
            ->sum('quantity');
    }

    public function remainingQuantity(): float
    {
        return max(0, round((float) $this->quantity - $this->billedQuantity(), 2));
    }

    public function recalculate(): void
    {
        $lineSubtotal = $this->quantity * $this->unit_price;
        $this->vat_amount = round($lineSubtotal * ($this->vat_rate / 100), 2);
        $this->line_total = round($lineSubtotal + $this->vat_amount, 2);
    }
}
