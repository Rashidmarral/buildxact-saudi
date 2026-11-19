<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentVoucherLine extends Model
{
    protected $fillable = ['payment_voucher_id', 'account_id', 'amount', 'description'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function paymentVoucher(): BelongsTo
    {
        return $this->belongsTo(PaymentVoucher::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
