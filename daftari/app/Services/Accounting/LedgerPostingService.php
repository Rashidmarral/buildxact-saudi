<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankTransfer;
use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\Company;
use App\Models\CustomsDeclaration;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\JournalEntry;
use App\Models\PaymentVoucher;
use App\Models\ReceiptVoucher;
use App\Models\StockAdjustment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The double-entry posting engine. Every real business event that moves
 * money or inventory goes through post() with a balanced set of lines —
 * nothing here is decorative bookkeeping, unbalanced entries are rejected.
 */
class LedgerPostingService
{
    private const TOLERANCE = 0.01;

    /**
     * @param  array<int, array{account_id:int, debit?:float, credit?:float, memo?:string}>  $lines
     */
    public function post(Company $company, string $sourceType, int $sourceId, string $description, \DateTimeInterface $date, array $lines): ?JournalEntry
    {
        $lines = array_values(array_filter($lines, fn ($l) => (float) ($l['debit'] ?? 0) > 0 || (float) ($l['credit'] ?? 0) > 0));

        if (empty($lines)) {
            return null;
        }

        if ($this->alreadyPosted($company, $sourceType, $sourceId)) {
            return null;
        }

        $totalDebit = round(array_sum(array_map(fn ($l) => (float) ($l['debit'] ?? 0), $lines)), 2);
        $totalCredit = round(array_sum(array_map(fn ($l) => (float) ($l['credit'] ?? 0), $lines)), 2);

        if (abs($totalDebit - $totalCredit) > self::TOLERANCE) {
            throw new InvalidArgumentException("Unbalanced journal entry for {$sourceType}#{$sourceId}: debit {$totalDebit} != credit {$totalCredit}");
        }

        return DB::transaction(function () use ($company, $sourceType, $sourceId, $description, $date, $lines) {
            $entry = JournalEntry::create([
                'company_id' => $company->id,
                'entry_number' => $company->nextJournalNumber(),
                'entry_date' => $date,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'description' => $description,
                'created_by' => Auth::id(),
            ]);

            foreach ($lines as $line) {
                $entry->lines()->create([
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'memo' => $line['memo'] ?? null,
                ]);
            }

            return $entry;
        });
    }

    /**
     * Posts a mirrored reversing entry for a void/cancel action. Safe to
     * call even if nothing was posted originally (returns null).
     */
    public function reverse(Company $company, string $sourceType, int $sourceId, string $description): ?JournalEntry
    {
        $original = JournalEntry::where('company_id', $company->id)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->with('lines')
            ->first();

        if (! $original || $this->alreadyPosted($company, $sourceType.'_reversal', $sourceId)) {
            return null;
        }

        $lines = $original->lines->map(fn ($line) => [
            'account_id' => $line->account_id,
            'debit' => $line->credit,
            'credit' => $line->debit,
            'memo' => $line->memo,
        ])->all();

        return $this->post($company, $sourceType.'_reversal', $sourceId, $description, now(), $lines);
    }

    /**
     * Removes the journal entry (and lines) for a source outright — used
     * when a record is edited in place and needs its posting rebuilt from
     * scratch, as opposed to reverse() which preserves both entries as an
     * audit trail for a genuine cancellation/void.
     */
    public function deletePosting(Company $company, string $sourceType, int $sourceId): void
    {
        JournalEntry::where('company_id', $company->id)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->get()
            ->each(function (JournalEntry $entry) {
                $entry->lines()->delete();
                $entry->delete();
            });
    }

    private function alreadyPosted(Company $company, string $sourceType, int $sourceId): bool
    {
        return JournalEntry::where('company_id', $company->id)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->exists();
    }

    private function account(Company $company, string $key): ?Account
    {
        return AccountMapping::resolve($company->id, $key);
    }

    private function bankOrCashAccount(Company $company, string $bankAccountType): ?Account
    {
        return $this->account($company, $bankAccountType === 'cash' ? 'DEFAULT_CASH' : 'DEFAULT_BANK');
    }

    // ---- Sales cycle ----------------------------------------------------

    public function postInvoiceIssued(Invoice $invoice): ?JournalEntry
    {
        $company = $invoice->company;
        $ar = $this->account($company, 'ACCOUNTS_RECEIVABLE');
        $revenue = $this->account($company, 'DEFAULT_SALES_REVENUE');
        $vatOutput = $this->account($company, 'VAT_OUTPUT');
        $discounts = $this->account($company, 'DEFAULT_SALES_DISCOUNTS');

        if (! $ar || ! $revenue || ! $vatOutput) {
            return null;
        }

        $lines = [
            ['account_id' => $ar->id, 'debit' => $invoice->total, 'memo' => $invoice->invoice_number],
            ['account_id' => $revenue->id, 'credit' => $invoice->subtotal],
            ['account_id' => $vatOutput->id, 'credit' => $invoice->vat_total],
        ];

        if ($invoice->discount_total > 0 && $discounts) {
            $lines[] = ['account_id' => $discounts->id, 'debit' => $invoice->discount_total];
        }

        return $this->post($company, 'invoice', $invoice->id, __('Invoice :number issued', ['number' => $invoice->invoice_number]), $invoice->issue_date, $lines);
    }

    public function postInvoicePayment(InvoicePayment $payment): ?JournalEntry
    {
        $invoice = $payment->invoice;
        $company = $invoice->company;
        $ar = $this->account($company, 'ACCOUNTS_RECEIVABLE');
        $cashOrBank = $this->account($company, $payment->method === 'cash' ? 'DEFAULT_CASH' : 'DEFAULT_BANK');

        if (! $ar || ! $cashOrBank) {
            return null;
        }

        return $this->post($company, 'invoice_payment', $payment->id, __('Payment received for :number', ['number' => $invoice->invoice_number]), $payment->paid_at, [
            ['account_id' => $cashOrBank->id, 'debit' => $payment->amount],
            ['account_id' => $ar->id, 'credit' => $payment->amount],
        ]);
    }

    public function postReceiptVoucher(ReceiptVoucher $voucher): ?JournalEntry
    {
        $company = $voucher->company;
        $bankAccount = $voucher->bankAccount;
        $cashOrBank = $bankAccount ? $this->bankOrCashAccount($company, $bankAccount->type) : null;

        if (! $cashOrBank) {
            return null;
        }

        // An explicit counter account on the voucher always wins (e.g. the
        // user knows this receipt should be credited to a specific account).
        // Otherwise: linked to an invoice settles Accounts Receivable;
        // anything else is a general receipt booked to Other Income.
        $counterpart = $voucher->counterAccount
            ?? ($voucher->invoice_id
                ? $this->account($company, 'ACCOUNTS_RECEIVABLE')
                : $this->account($company, 'OTHER_INCOME_DEFAULT'));

        if (! $counterpart) {
            return null;
        }

        return $this->post($company, 'receipt_voucher', $voucher->id, __('Receipt voucher :number', ['number' => $voucher->voucher_number]), $voucher->date, [
            ['account_id' => $cashOrBank->id, 'debit' => $voucher->amount],
            ['account_id' => $counterpart->id, 'credit' => $voucher->amount],
        ]);
    }

    // ---- Purchase cycle ---------------------------------------------------

    public function postBillPosted(Bill $bill): ?JournalEntry
    {
        $company = $bill->company;
        $ap = $this->account($company, 'ACCOUNTS_PAYABLE');
        $expenseAccount = $this->account($company, 'DEFAULT_OPERATING_EXPENSES');
        $vatInput = $this->account($company, 'VAT_INPUT');

        if (! $ap || ! $expenseAccount || ! $vatInput) {
            return null;
        }

        $net = $bill->subtotal - $bill->discount_total;

        return $this->post($company, 'bill', $bill->id, __('Bill :number posted', ['number' => $bill->bill_number]), $bill->bill_date, [
            ['account_id' => $expenseAccount->id, 'debit' => $net],
            ['account_id' => $vatInput->id, 'debit' => $bill->vat_total],
            ['account_id' => $ap->id, 'credit' => $bill->total],
        ]);
    }

    public function postBillPayment(BillPayment $payment): ?JournalEntry
    {
        $bill = $payment->bill;
        $company = $bill->company;
        $ap = $this->account($company, 'ACCOUNTS_PAYABLE');
        $cashOrBank = $this->account($company, $payment->method === 'cash' ? 'DEFAULT_CASH' : 'DEFAULT_BANK');

        if (! $ap || ! $cashOrBank) {
            return null;
        }

        return $this->post($company, 'bill_payment', $payment->id, __('Payment made for :number', ['number' => $bill->bill_number]), $payment->paid_at, [
            ['account_id' => $ap->id, 'debit' => $payment->amount],
            ['account_id' => $cashOrBank->id, 'credit' => $payment->amount],
        ]);
    }

    public function postPaymentVoucher(PaymentVoucher $voucher): ?JournalEntry
    {
        $company = $voucher->company;
        $bankAccount = $voucher->bankAccount;
        $cashOrBank = $bankAccount ? $this->bankOrCashAccount($company, $bankAccount->type) : null;

        if (! $cashOrBank) {
            return null;
        }

        // An explicit counter account on the voucher always wins. Otherwise:
        // linked to a bill or expense settles Accounts Payable (the bill or
        // the expense already posted its own accrual); anything else is a
        // direct, unaccrued payment booked straight to operating expenses.
        $counterpart = $voucher->counterAccount
            ?? (($voucher->bill_id || $voucher->expense_id)
                ? $this->account($company, 'ACCOUNTS_PAYABLE')
                : $this->account($company, 'DEFAULT_OPERATING_EXPENSES'));

        if (! $counterpart) {
            return null;
        }

        return $this->post($company, 'payment_voucher', $voucher->id, __('Payment voucher :number', ['number' => $voucher->voucher_number]), $voucher->date, [
            ['account_id' => $counterpart->id, 'debit' => $voucher->amount],
            ['account_id' => $cashOrBank->id, 'credit' => $voucher->amount],
        ]);
    }

    public function postExpense(Expense $expense): ?JournalEntry
    {
        $company = $expense->company;
        $expenseAccount = $expense->account ?? $this->account($company, 'DEFAULT_OPERATING_EXPENSES');
        $vatInput = $this->account($company, 'VAT_INPUT');

        if (! $expenseAccount) {
            return null;
        }

        // Paid immediately from a financial account when one is chosen;
        // otherwise recorded as an accrued payable, to be settled later by
        // a Payment Voucher referencing this expense.
        $bankAccount = $expense->bankAccount;
        $counterpart = $bankAccount
            ? $this->bankOrCashAccount($company, $bankAccount->type)
            : $this->account($company, 'ACCOUNTS_PAYABLE');

        if (! $counterpart) {
            return null;
        }

        $lines = [
            ['account_id' => $expenseAccount->id, 'debit' => $expense->amount, 'memo' => $expense->vendor_name],
            ['account_id' => $counterpart->id, 'credit' => $expense->amount + $expense->vat_amount],
        ];

        if ($expense->vat_amount > 0 && $vatInput) {
            $lines[] = ['account_id' => $vatInput->id, 'debit' => $expense->vat_amount];
        }

        return $this->post($company, 'expense', $expense->id, __('Expense: :description', ['description' => $expense->description]), $expense->expense_date, $lines);
    }

    public function postCustomsDeclaration(CustomsDeclaration $declaration): ?JournalEntry
    {
        $company = $declaration->company;
        $vatInputImports = $this->account($company, 'VAT_INPUT_IMPORTS');
        $customsPayable = $this->account($company, 'CUSTOMS_PAYABLE');
        $dutyExpense = $this->account($company, 'DEFAULT_OPERATING_EXPENSES');

        if (! $customsPayable) {
            return null;
        }

        $lines = [];

        if ($declaration->vat_amount > 0 && $vatInputImports) {
            $lines[] = ['account_id' => $vatInputImports->id, 'debit' => $declaration->vat_amount, 'memo' => __('Import VAT')];
        }

        if ($declaration->customs_duty > 0 && $dutyExpense) {
            $lines[] = ['account_id' => $dutyExpense->id, 'debit' => $declaration->customs_duty, 'memo' => __('Customs duty')];
        }

        $lines[] = ['account_id' => $customsPayable->id, 'credit' => (float) $declaration->vat_amount + (float) $declaration->customs_duty];

        return $this->post(
            $company,
            'customs_declaration',
            $declaration->id,
            __('Customs declaration :number', ['number' => $declaration->declaration_number ?: '#'.$declaration->id]),
            $declaration->declaration_date,
            $lines
        );
    }

    // ---- Inventory ----------------------------------------------------

    public function postStockAdjustment(StockAdjustment $adjustment): ?JournalEntry
    {
        $company = $adjustment->company;
        $inventory = $this->account($company, 'INVENTORY_ASSET');
        $adjustmentAccount = $this->account($company, 'INVENTORY_ADJUSTMENT');

        if (! $inventory || ! $adjustmentAccount) {
            return null;
        }

        $value = round((float) $adjustment->quantity * (float) ($adjustment->item->unit_price ?? 0), 2);

        if ($value <= 0) {
            return null;
        }

        $lines = $adjustment->type === 'increase'
            ? [
                ['account_id' => $inventory->id, 'debit' => $value],
                ['account_id' => $adjustmentAccount->id, 'credit' => $value],
            ]
            : [
                ['account_id' => $adjustmentAccount->id, 'debit' => $value],
                ['account_id' => $inventory->id, 'credit' => $value],
            ];

        return $this->post($company, 'stock_adjustment', $adjustment->id, __('Stock adjustment: :item', ['item' => $adjustment->item->name]), $adjustment->date, $lines);
    }

    // ---- Cash & banks ----------------------------------------------------

    public function postBankTransfer(BankTransfer $transfer): ?JournalEntry
    {
        $company = $transfer->company;
        $from = $transfer->fromAccount;
        $to = $transfer->toAccount;

        if (! $from || ! $to || $from->type === $to->type) {
            // Same-type transfers (bank-to-bank or cash-to-cash) share one
            // mapped GL account each — there's no distinguishable pair of
            // accounts to post between, so no journal entry is recorded.
            return null;
        }

        $fromAccount = $this->bankOrCashAccount($company, $from->type);
        $toAccount = $this->bankOrCashAccount($company, $to->type);

        if (! $fromAccount || ! $toAccount) {
            return null;
        }

        return $this->post($company, 'bank_transfer', $transfer->id, __('Transfer :from -> :to', ['from' => $from->name, 'to' => $to->name]), $transfer->date, [
            ['account_id' => $toAccount->id, 'debit' => $transfer->amount],
            ['account_id' => $fromAccount->id, 'credit' => $transfer->amount],
        ]);
    }
}
