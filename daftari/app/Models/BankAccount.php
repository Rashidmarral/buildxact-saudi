<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'name', 'bank_name', 'account_holder_name', 'account_number', 'iban',
        'bank_phone', 'bank_address', 'type', 'bank_account_type_id', 'opening_balance', 'opening_balance_date',
        'opening_balance_reference', 'currency', 'is_active', 'is_personal', 'personal_owner_name',
        // Not set from form input (see BankAccountController::validated()) —
        // only ever written by glAccount() itself, below.
        'account_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_personal' => 'boolean',
            'opening_balance_date' => 'date',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function bankAccountType(): BelongsTo
    {
        return $this->belongsTo(BankAccountType::class);
    }

    /**
     * What the UI actually shows for this account's type — the company's
     * own custom label when one was chosen, otherwise the plain built-in
     * "Bank"/"Cash". Never affects accounting treatment: that's always
     * driven by the `type` column itself (see glAccount()), which a custom
     * type only ever mirrors, never overrides.
     */
    public function displayTypeLabel(): string
    {
        return $this->bankAccountType?->name ?? ($this->type === 'cash' ? __('Cash') : __('Bank'));
    }

    /**
     * Each real bank/cash account gets its own dedicated ledger line
     * (created on first use, idempotent) instead of every account of the
     * same type sharing one generic "Bank"/"Cash" account — see
     * LedgerPostingService::bankOrCashAccount(). Code is allocated from a
     * reserved sub-range under the matching system parent (1001–1099
     * under Cash 1000, 1101–1149 under Bank 1100 — see
     * Account::nextAvailableCode()).
     */
    public function glAccount(): Account
    {
        if ($this->account_id) {
            return $this->account;
        }

        $isCash = $this->type === 'cash';
        $code = Account::nextAvailableCode($this->company_id, $isCash ? 1001 : 1101, $isCash ? 1099 : 1149);

        if (! $code) {
            throw new \RuntimeException("No free ledger code left for new {$this->type} accounts — contact support.");
        }

        $account = Account::create([
            'company_id' => $this->company_id,
            'code' => $code,
            'name' => $this->name,
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_system' => false,
        ]);

        $this->update(['account_id' => $account->id]);
        $this->setRelation('account', $account);

        return $account;
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

    /**
     * Income recorded directly against this account (see
     * IncomeController) — a voucher-free way real cash arrives, the
     * revenue-side mirror of expenses() above. Uses gross_amount, same
     * reasoning as expenses(): the account actually received the full
     * amount including VAT collected, even though that VAT is a liability
     * (not yet remitted) rather than the company's own money.
     */
    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    public function currentBalance(): float
    {
        $received = $this->receiptVouchers()->where('status', 'issued')->sum('amount')
            + $this->invoicePayments()->sum('amount')
            + $this->incomes()->sum('gross_amount');
        $issuedPaymentVoucherIds = $this->paymentVouchers()->where('status', 'issued')->pluck('id');
        // Extra lines (bank charges, VAT on them, ...) are real cash that
        // left this account alongside the main payment — see
        // PaymentVoucher::totalAmount() — so they count here too.
        $paid = (float) $this->paymentVouchers()->where('status', 'issued')->sum('amount')
            + (float) PaymentVoucherLine::whereIn('payment_voucher_id', $issuedPaymentVoucherIds)->sum('amount');
        $expensesPaid = $this->expenses()->where('status', 'approved')->sum('gross_amount');
        $transfersIn = BankTransfer::where('to_bank_account_id', $this->id)->where('status', 'issued')->sum('amount');
        $transfersOut = BankTransfer::where('from_bank_account_id', $this->id)->where('status', 'issued')->sum('amount');

        return (float) $this->opening_balance + $received - $paid - $expensesPaid + $transfersIn - $transfersOut;
    }
}
