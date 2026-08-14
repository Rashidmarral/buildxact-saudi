<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentVoucher extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'bank_account_id', 'party_type', 'client_id', 'supplier_id', 'counter_account_id',
        'expense_id', 'bill_id', 'bill_payment_id', 'created_by', 'voucher_number', 'date', 'payee_name',
        'party_name_ar', 'party_vat_number', 'party_phone', 'party_email', 'party_address',
        'amount', 'method', 'reference', 'notes', 'status',
    ];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function counterAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'counter_account_id');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function billPayment(): BelongsTo
    {
        return $this->belongsTo(BillPayment::class);
    }
}
