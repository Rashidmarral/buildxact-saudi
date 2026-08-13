<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Services\ZatcaQrGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'client_id', 'branch_id', 'salesperson_id', 'created_by', 'invoice_number', 'type',
        'status', 'issue_date', 'due_date', 'subtotal', 'discount_total',
        'vat_total', 'total', 'amount_paid', 'currency', 'notes', 'qr_code',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(Salesperson::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function invoicePayments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function recalculateTotals(): void
    {
        $items = $this->items;

        $this->subtotal = $items->sum(fn ($item) => $item->quantity * $item->unit_price);
        $this->vat_total = $items->sum('vat_amount');
        $this->total = $this->subtotal - $this->discount_total + $this->vat_total;
        $this->amount_paid = $this->invoicePayments()->sum('amount');

        $this->qr_code = ZatcaQrGenerator::generate(
            $this->company->name,
            (string) $this->company->vat_number,
            $this->issue_date ?? now(),
            (float) $this->total,
            (float) $this->vat_total
        );

        $this->save();
    }

    public function balanceDue(): float
    {
        return round((float) $this->total - (float) $this->amount_paid, 2);
    }

    public function isFullyPaid(): bool
    {
        return $this->balanceDue() <= 0.0;
    }
}
