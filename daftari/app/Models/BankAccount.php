<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'name', 'bank_name', 'account_holder_name', 'account_number', 'iban',
        'bank_phone', 'bank_address', 'type', 'opening_balance', 'opening_balance_date',
        'opening_balance_reference', 'currency', 'is_active', 'is_personal', 'personal_owner_name',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_personal' => 'boolean',
            'opening_balance_date' => 'date',
        ];
    }

    public function receiptVouchers(): HasMany
    {
        return $this->hasMany(ReceiptVoucher::class);
    }

    public function paymentVouchers(): HasMany
    {
        return $this->hasMany(PaymentVoucher::class);
    }

    /**
     * Expenses paid directly out of this account (its "Financial account"
     * chosen as something other than "Unpaid (record as payable)") — a
     * second, voucher-free way real cash leaves an account. Only ones with
     * status 'approved' have actually been posted to the ledger (see
     * ExpenseController::store()/approve()); an expense left unpaid never
     * touches an account until it's later settled by a Payment Voucher.
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(BankReconciliation::class);
    }

    /**
     * Invoice payments recorded against this account (see
     * InvoiceController::storePayment()) — Sales' own money-in record,
     * alongside Receipt Vouchers. A payment recorded before this field
     * existed has no bank_account_id and so was never tied to a specific
     * account, so it can't appear here either.
     */
    public function invoicePayments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function currentBalance(): float
    {
        $received = $this->receiptVouchers()->where('status', 'issued')->sum('amount')
            + $this->invoicePayments()->sum('amount');
        $paid = $this->paymentVouchers()->where('status', 'issued')->sum('amount');
        $expensesPaid = $this->expenses()->where('status', 'approved')->sum('gross_amount');
        $transfersIn = BankTransfer::where('to_bank_account_id', $this->id)->sum('amount');
        $transfersOut = BankTransfer::where('from_bank_account_id', $this->id)->sum('amount');

        return (float) $this->opening_balance + $received - $paid - $expensesPaid + $transfersIn - $transfersOut;
    }
}
