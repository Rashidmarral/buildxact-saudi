<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PurchaseOrder extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'supplier_id', 'branch_id', 'created_by', 'converted_bill_id',
        'po_number', 'status', 'order_date', 'expected_date', 'subtotal',
        'discount_total', 'vat_total', 'total', 'notes',
        'approved_by', 'approved_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function convertedBill(): BelongsTo
    {
        return $this->belongsTo(Bill::class, 'converted_bill_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * Every bill raised against this order — a PO can be billed across
     * several deliveries, not just the single converted_bill_id the
     * one-shot conversion used to leave behind.
     */
    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function isFullyBilled(): bool
    {
        return $this->items->every(fn (PurchaseOrderItem $item) => $item->remainingQuantity() <= 0.01);
    }

    public function hasAnyBilledQuantity(): bool
    {
        return $this->items->contains(fn (PurchaseOrderItem $item) => $item->billedQuantity() > 0.01);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function recalculateTotals(): void
    {
        $items = $this->items;

        $this->subtotal = $items->sum(fn ($item) => $item->quantity * $item->unit_price);
        $this->vat_total = $items->sum('vat_amount');
        $this->total = $this->subtotal - $this->discount_total + $this->vat_total;

        $this->save();
    }
}
