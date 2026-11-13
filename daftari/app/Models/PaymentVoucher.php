<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentVoucher extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'company_id', 'bank_account_id', 'project_id', 'party_type', 'client_id', 'supplier_id', 'employee_id', 'counter_account_id',
        'expense_id', 'bill_id', 'bill_payment_id', 'created_by', 'voucher_number', 'date', 'payee_name',
        'party_name_ar', 'party_vat_number', 'party_phone', 'party_email', 'party_address',
        'amount', 'wht_amount', 'method', 'reference', 'notes', 'notes_ar', 'status',
        'approved_by', 'approved_at', 'rejection_reason',
        'cheque_number', 'cheque_date', 'cheque_cleared_at',
    ];

    protected function casts(): array
    {
        return ['date' => 'date', 'wht_amount' => 'decimal:2', 'approved_at' => 'datetime', 'cheque_date' => 'date', 'cheque_cleared_at' => 'date'];
    }

    public function isUnclearedCheque(): bool
    {
        return $this->method === 'cheque' && $this->cheque_cleared_at === null;
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
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

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Set when this voucher has been linked into a Subcontract's payment
     * ledger (see SubcontractController::linkVoucher()) — lets the
     * voucher form/show warn before a linked voucher's amount is
     * changed out from under the subcontract's totals. A hasMany, not
     * hasOne: one real payment can waterfall across several milestones,
     * which SubcontractController::allocateAcrossMilestones() splits
     * into several subcontract_payments rows all pointing at this same
     * voucher.
     */
    public function subcontractPayments(): HasMany
    {
        return $this->hasMany(SubcontractPayment::class);
    }

    /**
     * Extra entries riding along in the same bank transaction as this
     * voucher's main payment — e.g. the bank's own service charge and the
     * VAT on it, deducted in the same transfer that paid the supplier.
     * Never touches `amount`/`counter_account_id` (still "paid to the
     * main counterparty" — what bill settlement, subcontract linkage and
     * WHT all key off) or anything else about the voucher's identity.
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PaymentVoucherLine::class);
    }

    public function extraLinesTotal(): float
    {
        return round((float) $this->lines->sum('amount'), 2);
    }

    /**
     * What actually left the bank account in this one transaction — the
     * main payment plus every extra line. This is the figure that must
     * match the real bank statement line, used anywhere that's comparing
     * against or summing real cash movement (BankAccount::currentBalance(),
     * bank reconciliation matching) — never for anything keyed to the
     * main counterparty specifically (bill settlement, subcontract
     * payments, the voucher list's "total paid to this supplier"), which
     * stay on `amount` alone.
     */
    public function totalAmount(): float
    {
        return round((float) $this->amount + $this->extraLinesTotal(), 2);
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
