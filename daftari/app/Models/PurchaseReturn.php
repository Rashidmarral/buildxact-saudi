<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseReturn extends Model
{
    use BelongsToCompany;

    public const REASON_TYPES = ['returned_goods', 'pricing_error', 'cancelled_order', 'other'];

    protected $fillable = [
        'company_id', 'bill_id', 'supplier_id', 'branch_id', 'created_by',
        'return_number', 'issue_date', 'reason', 'reason_type', 'status',
        'subtotal', 'vat_total', 'total', 'currency', 'exchange_rate',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }

    public function reasonTypeLabel(): ?string
    {
        return match ($this->reason_type) {
            'returned_goods' => __('Returned goods'),
            'pricing_error' => __('Pricing error'),
            'cancelled_order' => __('Order cancelled'),
            'other' => __('Other'),
            default => null,
        };
    }

    public function recalculateTotals(): void
    {
        $items = $this->items;

        $this->subtotal = $items->sum(fn ($item) => $item->quantity * $item->unit_price);
        $this->vat_total = $items->sum('vat_amount');
        $this->total = $this->subtotal + $this->vat_total;

        $this->save();
    }
}
