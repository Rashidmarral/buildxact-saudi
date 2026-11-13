<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardSettlement extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'payment_mode_id', 'bank_account_id', 'settlement_date',
        'gross_amount', 'fee_amount', 'fee_vat_amount', 'net_amount',
        'reference', 'notes', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'settlement_date' => 'date',
            'gross_amount' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'fee_vat_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
        ];
    }

    public function paymentMode(): BelongsTo
    {
        return $this->belongsTo(PaymentMode::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
