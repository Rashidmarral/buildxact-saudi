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
        'amount', 'wht_amount', 'method', 'reference', 'notes', 'status',
    ];

    protected function casts(): array
    {
        return ['date' => 'date', 'wht_amount' => 'decimal:2'];
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

    /**
     * What the "For" line on the printed voucher falls back to when no
     * one typed a description — the linked bill's number and item
     * descriptions (what was actually purchased), or the expense's own
     * description, so the voucher never prints blank just because nobody
     * filled in the optional notes field.
     */
    public function defaultPurpose(): ?string
    {
        if ($this->bill) {
            $items = $this->bill->items->pluck('description')->filter()->implode(', ');

            return __('Bill :number', ['number' => $this->bill->bill_number]).($items ? ' — '.$items : '');
        }

        if ($this->expense) {
            return $this->expense->description ?: $this->expense->vendor_name;
        }

        return null;
    }
}
