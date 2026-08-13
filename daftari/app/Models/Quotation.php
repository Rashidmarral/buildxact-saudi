<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'client_id', 'created_by', 'converted_invoice_id', 'quotation_number',
        'type', 'status', 'issue_date', 'expiry_date', 'subtotal', 'discount_total',
        'vat_total', 'total', 'currency', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'expiry_date' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function convertedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'converted_invoice_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function recalculateTotals(): void
    {
        $items = $this->items;

        $this->subtotal = $items->sum(fn ($item) => $item->quantity * $item->unit_price);
        $this->vat_total = $items->sum('vat_amount');
        $this->total = $this->subtotal - $this->discount_total + $this->vat_total;

        $this->save();
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast() && ! in_array($this->status, ['converted', 'accepted']);
    }
}
