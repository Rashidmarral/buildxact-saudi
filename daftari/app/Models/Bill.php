<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Bill extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'supplier_id', 'branch_id', 'warehouse_id', 'purchase_order_id', 'created_by', 'bill_number',
        'supplier_reference', 'status', 'bill_date', 'due_date', 'subtotal',
        'discount_total', 'vat_total', 'total', 'amount_paid', 'currency', 'exchange_rate', 'notes',
        'stock_received', 'wht_rate_id', 'wht_amount', 'wht_withheld',
    ];

    protected function casts(): array
    {
        return [
            'bill_date' => 'date',
            'due_date' => 'date',
            'stock_received' => 'boolean',
            'wht_amount' => 'decimal:2',
            'wht_withheld' => 'boolean',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function whtRate(): BelongsTo
    {
        return $this->belongsTo(WhtRate::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillItem::class);
    }

    public function billPayments(): HasMany
    {
        return $this->hasMany(BillPayment::class);
    }

    public function customsDeclarations(): BelongsToMany
    {
        return $this->belongsToMany(CustomsDeclaration::class, 'customs_declaration_bill');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function purchaseReturns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class);
    }

    public function returnedTotal(): float
    {
        return (float) $this->purchaseReturns()->where('status', 'issued')->sum('total');
    }

    public function remainingReturnableTotal(): float
    {
        return round((float) $this->total - $this->returnedTotal(), 2);
    }

    public function recalculateTotals(): void
    {
        $items = $this->items;

        $this->subtotal = $items->sum(fn ($item) => $item->quantity * $item->unit_price);
        $this->vat_total = $items->sum('vat_amount');
        $this->total = $this->subtotal - $this->discount_total + $this->vat_total;

        $this->save();
    }

    public function balanceDue(): float
    {
        return round((float) $this->total - (float) $this->amount_paid, 2);
    }

    public function isFullyPaid(): bool
    {
        return $this->balanceDue() <= 0;
    }
}
