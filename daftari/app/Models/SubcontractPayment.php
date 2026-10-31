<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubcontractPayment extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'company_id', 'subcontract_id', 'milestone_id', 'payment_voucher_id',
        'amount', 'gross_amount', 'paid_at', 'method', 'reference', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'gross_amount' => 'decimal:2',
            'paid_at' => 'date',
        ];
    }

    /**
     * The real cash figure for this payment ("i have paid the same
     * amount with tax but i am paying my supplier this amount without
     * tax") — gross_amount when a VAT difference was recorded, amount
     * otherwise (the common case: no difference to track).
     */
    public function displayGross(): float
    {
        return (float) ($this->gross_amount ?? $this->amount);
    }

    public function subcontract(): BelongsTo
    {
        return $this->belongsTo(Subcontract::class);
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(SubcontractMilestone::class, 'milestone_id');
    }

    public function paymentVoucher(): BelongsTo
    {
        return $this->belongsTo(PaymentVoucher::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
