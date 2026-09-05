<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Services\ZatcaQrGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DebitNote extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'invoice_id', 'client_id', 'branch_id', 'created_by',
        'debit_note_number', 'issue_date', 'reason', 'status',
        'subtotal', 'vat_total', 'total', 'currency', 'qr_code',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
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
        return $this->hasMany(DebitNoteItem::class);
    }

    public function zatcaDebitNoteLogs(): HasMany
    {
        return $this->hasMany(ZatcaDebitNoteLog::class);
    }

    public function isZatcaSynced(): bool
    {
        return $this->zatcaDebitNoteLogs()->whereIn('status', ['cleared', 'reported'])->exists();
    }

    public function recalculateTotals(): void
    {
        $items = $this->items;

        $this->subtotal = $items->sum(fn ($item) => $item->quantity * $item->unit_price);
        $this->vat_total = $items->sum('vat_amount');
        $this->total = $this->subtotal + $this->vat_total;

        $this->qr_code = $this->company->isZatcaQrEnabled()
            ? ZatcaQrGenerator::generate(
                $this->company->name,
                (string) $this->company->vat_number,
                $this->issue_date ?? now(),
                (float) $this->total,
                (float) $this->vat_total
            )
            : null;

        $this->save();
    }
}
