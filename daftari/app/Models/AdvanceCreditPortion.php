<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdvanceCreditPortion extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'advance_credit_id', 'description', 'amount', 'vat_rate', 'vat_amount', 'total', 'sort_order',
    ];

    public function advanceCredit(): BelongsTo
    {
        return $this->belongsTo(AdvanceCredit::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(AdvanceCreditApplication::class);
    }

    public function appliedTotal(): float
    {
        return (float) $this->applications()->sum('total');
    }

    public function remainingTotal(): float
    {
        return round((float) $this->total - $this->appliedTotal(), 2);
    }

    /** Same share of the remaining total as amount/vat_amount are of the portion's own total — keeps a partial application's own VAT split proportional to the portion's original rate. */
    public function remainingAmount(): float
    {
        if ((float) $this->total <= 0) {
            return 0.0;
        }

        return round($this->remainingTotal() * ((float) $this->amount / (float) $this->total), 2);
    }

    public function remainingVatAmount(): float
    {
        return round($this->remainingTotal() - $this->remainingAmount(), 2);
    }
}
