<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BillPayment extends Model
{
    protected $fillable = ['bill_id', 'amount', 'exchange_rate', 'paid_at', 'method', 'reference'];

    protected function casts(): array
    {
        return ['paid_at' => 'date'];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    /**
     * The Payment Voucher this payment was settled through, when it was
     * recorded that way — mirrors InvoicePayment::receiptVoucher().
     */
    public function paymentVoucher(): HasOne
    {
        return $this->hasOne(PaymentVoucher::class, 'bill_payment_id');
    }
}
