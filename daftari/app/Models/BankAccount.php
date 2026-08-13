<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'name', 'bank_name', 'account_number', 'iban',
        'type', 'opening_balance', 'currency', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function receiptVouchers(): HasMany
    {
        return $this->hasMany(ReceiptVoucher::class);
    }

    public function paymentVouchers(): HasMany
    {
        return $this->hasMany(PaymentVoucher::class);
    }

    public function currentBalance(): float
    {
        $received = $this->receiptVouchers()->where('status', 'issued')->sum('amount');
        $paid = $this->paymentVouchers()->where('status', 'issued')->sum('amount');
        $transfersIn = BankTransfer::where('to_bank_account_id', $this->id)->sum('amount');
        $transfersOut = BankTransfer::where('from_bank_account_id', $this->id)->sum('amount');

        return (float) $this->opening_balance + $received - $paid + $transfersIn - $transfersOut;
    }
}
