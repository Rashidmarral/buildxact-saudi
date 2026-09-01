<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankTransfer;
use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\CustomsDeclaration;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\JournalEntry;
use App\Models\PaymentVoucher;
use App\Models\PurchaseReturn;
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

    /**
     * A missing *required* account mapping used to make a post*() method
     * quietly return null before ever calling post() — the caller (e.g.
     * InvoiceController::doSend()) never checks that return value, and the
     * status flip + posting call sit in the same DB::transaction(), so
     * nothing rolled back: the invoice was marked sent, stock deducted,
     * with zero journal entry ever created. Throwing here instead lets
     * post()'s own transaction (and the caller's, since this runs inside
     * it) actually roll back, matching how an unbalanced entry is already
     * handled. Optional/conditional lines (e.g. VAT_INPUT on an expense
     * with no VAT) are intentionally not required here — omitting those
     * safely trips post()'s own balance check instead.
     */
    private function requireAccounts(array $accounts, string $context): void
    {
        $missing = array_keys(array_filter($accounts, fn ($account) => $account === null));

        if ($missing) {
            throw new \RuntimeException(
                "Cannot post {$context}: missing required account mapping(s) [".implode(', ', $missing).']. '
                .'Configure them in Settings > Semantic Account Mappings.'
            );
        }
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
        $arRetention = $this->account($company, 'AR_RETENTION');
        $revenue = $this->account($company, 'DEFAULT_SALES_REVENUE');
        $vatOutput = $this->account($company, 'VAT_OUTPUT');
        $discounts = $this->account($company, 'DEFAULT_SALES_DISCOUNTS');

        $this->requireAccounts(['ACCOUNTS_RECEIVABLE' => $ar, 'DEFAULT_SALES_REVENUE' => $revenue, 'VAT_OUTPUT' => $vatOutput], 'invoice');

        // The Chart of Accounts is always kept in the company's base
        // currency, so a foreign-currency invoice is converted through its
        // exchange_rate before it ever reaches a journal line — every
        // amount below this point is a base-currency amount. total is
        // re-derived from the converted components (not rounded
        // independently from invoice->total) so the entry balances exactly
        // even after each component's own rounding.
        $rate = (float) $invoice->exchange_rate ?: 1.0;
        $subtotal = round((float) $invoice->subtotal * $rate, 2);
        $vatTotal = round((float) $invoice->vat_total * $rate, 2);
        $discountTotal = round((float) $invoice->discount_total * $rate, 2);
        $retentionAmountRaw = round((float) $invoice->retention_amount * $rate, 2);
        $total = round($subtotal - $discountTotal + $vatTotal, 2);

        // A retained portion of the receivable isn't collectible until the
        // retention is released later, so it's tracked in its own AR account
        // rather than blended into ordinary receivables.
        $retentionAmount = min($retentionAmountRaw, $total);
        $regularAr = round($total - $retentionAmount, 2);

        $lines = [];

        if ($regularAr > 0) {
            $lines[] = ['account_id' => $ar->id, 'debit' => $regularAr, 'memo' => $invoice->invoice_number];
        }

        if ($retentionAmount > 0) {
            $lines[] = ['account_id' => ($arRetention ?: $ar)->id, 'debit' => $retentionAmount, 'memo' => __('Retention held on :number', ['number' => $invoice->invoice_number])];
        }

        $lines[] = ['account_id' => $revenue->id, 'credit' => $subtotal];
        $lines[] = ['account_id' => $vatOutput->id, 'credit' => $vatTotal];

        if ($discountTotal > 0 && $discounts) {
            $lines[] = ['account_id' => $discounts->id, 'debit' => $discountTotal];
        }

        // Only relieve inventory when stock was actually deducted for this
        // invoice (InvoiceController::doSend() sets stock_deducted only
        // when a warehouse was selected and tracked-inventory lines exist)
        // — otherwise there is no physical stock movement to match a COGS
        // entry to. Cost is each item's standard cost (purchase_price),
        // the same figure the Inventory Valuation report already uses, not
        // whatever this particular invoice's line price was.
        if ($invoice->stock_deducted) {
            $invoice->loadMissing('items.item');
            $cogsAmount = round((float) $invoice->items
                ->filter(fn ($line) => $line->item?->track_inventory)
                ->sum(fn ($line) => $line->item->baseQuantityFor((float) $line->quantity, $line->unit_id) * (float) ($line->item->purchase_price ?? 0)), 2);

            if ($cogsAmount > 0) {
                $cogs = $this->account($company, 'COGS_DEFAULT');
                $inventoryAsset = $this->account($company, 'INVENTORY_ASSET');
                $this->requireAccounts(['COGS_DEFAULT' => $cogs, 'INVENTORY_ASSET' => $inventoryAsset], 'invoice (COGS)');

                $lines[] = ['account_id' => $cogs->id, 'debit' => $cogsAmount, 'memo' => __('Cost of goods sold for :number', ['number' => $invoice->invoice_number])];
                $lines[] = ['account_id' => $inventoryAsset->id, 'credit' => $cogsAmount];
            }
        }

        return $this->post($company, 'invoice', $invoice->id, __('Invoice :number issued', ['number' => $invoice->invoice_number]), $invoice->issue_date, $lines);
    }

    public function postCreditNote(CreditNote $creditNote): ?JournalEntry
    {
        $company = $creditNote->company;
        $ar = $this->account($company, 'ACCOUNTS_RECEIVABLE');
        $revenue = $this->account($company, 'DEFAULT_SALES_REVENUE');
        $vatOutput = $this->account($company, 'VAT_OUTPUT');

        $this->requireAccounts(['ACCOUNTS_RECEIVABLE' => $ar, 'DEFAULT_SALES_REVENUE' => $revenue, 'VAT_OUTPUT' => $vatOutput], 'credit note');

        $lines = [
            ['account_id' => $revenue->id, 'debit' => $creditNote->subtotal, 'memo' => $creditNote->credit_note_number],
            ['account_id' => $vatOutput->id, 'debit' => $creditNote->vat_total],
            ['account_id' => $ar->id, 'credit' => $creditNote->total],
        ];

        return $this->post($company, 'credit_note', $creditNote->id, __('Credit note :number', ['number' => $creditNote->credit_note_number]), $creditNote->issue_date, $lines);
    }

    /**
     * Commercial audit finding: FX_GAINS/FX_LOSSES were mappable accounts
     * with no code ever posting to them — every payment was converted at
     * the invoice's own booked exchange_rate, so a real rate movement
     * between issue and settlement was silently absorbed rather than
     * recognized. Accounts Receivable is relieved at the rate it was
     * booked at (what the invoice's own total represents in base
     * currency); cash/bank records what was actually received at the
     * payment's own rate. The difference is the realized gain or loss.
     * When the payment carries no rate of its own (same-currency
     * companies, the overwhelming majority here, or a payment recorded
     * before this field existed) it defaults to the invoice's rate and
     * the difference is exactly zero — no behavior change for those.
     */
    public function postInvoicePayment(InvoicePayment $payment): ?JournalEntry
    {
        $invoice = $payment->invoice;
        $company = $invoice->company;
        $ar = $this->account($company, 'ACCOUNTS_RECEIVABLE');
        $cashOrBank = $this->account($company, $payment->method === 'cash' ? 'DEFAULT_CASH' : 'DEFAULT_BANK');

        $this->requireAccounts(['ACCOUNTS_RECEIVABLE' => $ar, 'DEFAULT_CASH/DEFAULT_BANK' => $cashOrBank], 'invoice payment');

        $bookedRate = (float) $invoice->exchange_rate ?: 1.0;
        $paymentRate = (float) ($payment->exchange_rate ?: $bookedRate);

        $arRelief = round((float) $payment->amount * $bookedRate, 2);
        $cashReceived = round((float) $payment->amount * $paymentRate, 2);

        $lines = [
            ['account_id' => $cashOrBank->id, 'debit' => $cashReceived],
            ['account_id' => $ar->id, 'credit' => $arRelief],
        ];

        $this->appendFxDifference($company, $lines, $cashReceived - $arRelief, 'invoice payment');

        return $this->post($company, 'invoice_payment', $payment->id, __('Payment received for :number', ['number' => $invoice->invoice_number]), $payment->paid_at, $lines);
    }

    public function postReceiptVoucher(ReceiptVoucher $voucher): ?JournalEntry
    {
        $company = $voucher->company;
        $bankAccount = $voucher->bankAccount;
        $cashOrBank = $bankAccount ? $this->bankOrCashAccount($company, $bankAccount->type) : null;

        // An explicit counter account on the voucher always wins (e.g. the
        // user knows this receipt should be credited to a specific account).
        // Otherwise: linked to an invoice settles Accounts Receivable;
        // anything else is a general receipt booked to Other Income.
        $counterpart = $voucher->counterAccount
            ?? ($voucher->invoice_id
                ? $this->account($company, 'ACCOUNTS_RECEIVABLE')
                : $this->account($company, 'OTHER_INCOME_DEFAULT'));

        $this->requireAccounts(['DEFAULT_CASH/DEFAULT_BANK' => $cashOrBank, 'counterpart' => $counterpart], 'receipt voucher');

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

        $this->requireAccounts(['ACCOUNTS_PAYABLE' => $ap, 'DEFAULT_OPERATING_EXPENSES' => $expenseAccount, 'VAT_INPUT' => $vatInput], 'bill');

        // Converted to the company's base currency the same way as
        // postInvoiceIssued() — see the note there on why total is
        // re-derived from the converted components rather than converted
        // independently (keeps the entry balanced after rounding).
        $rate = (float) $bill->exchange_rate ?: 1.0;
        $subtotal = round((float) $bill->subtotal * $rate, 2);
        $discountTotal = round((float) $bill->discount_total * $rate, 2);
        $vatTotal = round((float) $bill->vat_total * $rate, 2);
        $net = round($subtotal - $discountTotal, 2);
        $total = round($net + $vatTotal, 2);

        // A line for a tracked-inventory item is a purchase of stock, not
        // an expense — it belongs on the balance sheet as Inventory Asset
        // until the item is sold (see postInvoiceIssued(), which relieves
        // it to COGS at that point). The line-level split is done as a
        // share of pre-discount, pre-VAT amounts so a header-level
        // discount is prorated across both buckets rather than landing
        // entirely on one.
        $bill->loadMissing('items.item');
        $trackedLineSubtotal = (float) $bill->items
            ->filter(fn ($line) => $line->item?->track_inventory)
            ->sum(fn ($line) => (float) $line->quantity * (float) $line->unit_price);
        $lineSubtotal = (float) $bill->subtotal;
        $trackedShare = $lineSubtotal > 0.005 ? min($trackedLineSubtotal / $lineSubtotal, 1.0) : 0.0;

        $capitalized = round($net * $trackedShare, 2);
        $expensed = round($net - $capitalized, 2);

        $lines = [];

        if ($capitalized > 0) {
            $inventoryAsset = $this->account($company, 'INVENTORY_ASSET');
            $this->requireAccounts(['INVENTORY_ASSET' => $inventoryAsset], 'bill (inventory capitalization)');
            $lines[] = ['account_id' => $inventoryAsset->id, 'debit' => $capitalized];
        }

        if ($expensed > 0) {
            $lines[] = ['account_id' => $expenseAccount->id, 'debit' => $expensed];
        }

        $lines[] = ['account_id' => $vatInput->id, 'debit' => $vatTotal];
        $lines[] = ['account_id' => $ap->id, 'credit' => $total];

        return $this->post($company, 'bill', $bill->id, __('Bill :number posted', ['number' => $bill->bill_number]), $bill->bill_date, $lines);
    }

    /**
     * Mirrors postInvoicePayment()'s FX treatment for the purchase side:
     * Accounts Payable is relieved at the bill's own booked rate, cash/
     * bank records what was actually paid at the payment's own rate, and
     * the difference posts as a realized gain or loss.
     */
    public function postBillPayment(BillPayment $payment): ?JournalEntry
    {
        $bill = $payment->bill;
        $company = $bill->company;
        $ap = $this->account($company, 'ACCOUNTS_PAYABLE');
        $cashOrBank = $this->account($company, $payment->method === 'cash' ? 'DEFAULT_CASH' : 'DEFAULT_BANK');

        $this->requireAccounts(['ACCOUNTS_PAYABLE' => $ap, 'DEFAULT_CASH/DEFAULT_BANK' => $cashOrBank], 'bill payment');

        $bookedRate = (float) $bill->exchange_rate ?: 1.0;
        $paymentRate = (float) ($payment->exchange_rate ?: $bookedRate);

        $apRelief = round((float) $payment->amount * $bookedRate, 2);
        $cashPaid = round((float) $payment->amount * $paymentRate, 2);

        $lines = [
            ['account_id' => $ap->id, 'debit' => $apRelief],
            ['account_id' => $cashOrBank->id, 'credit' => $cashPaid],
        ];

        $this->appendFxDifference($company, $lines, $apRelief - $cashPaid, 'bill payment');

        return $this->post($company, 'bill_payment', $payment->id, __('Payment made for :number', ['number' => $bill->bill_number]), $payment->paid_at, $lines);
    }

    /**
     * Appends an FX_GAINS credit or FX_LOSSES debit line for the given
     * base-currency difference — positive means a gain (more value than
     * expected), negative a loss. A zero difference (same currency, or no
     * payment-specific rate given) appends nothing, and post() already
     * drops zero-amount lines regardless.
     */
    private function appendFxDifference(Company $company, array &$lines, float $difference, string $context): void
    {
        $difference = round($difference, 2);

        if (abs($difference) <= self::TOLERANCE) {
            return;
        }

        $key = $difference > 0 ? 'FX_GAINS' : 'FX_LOSSES';
        $account = $this->account($company, $key);
        $this->requireAccounts([$key => $account], $context.' (FX)');

        $lines[] = $difference > 0
            ? ['account_id' => $account->id, 'credit' => abs($difference)]
            : ['account_id' => $account->id, 'debit' => abs($difference)];
    }

    /**
     * Mirrors postCreditNote() for the purchase side: reduces what we owe
     * the supplier and reverses the previously recorded expense + input
     * VAT. Purely internal bookkeeping — not a ZATCA-regulated document,
     * since ZATCA only regulates what a business issues as the seller.
     */
    public function postPurchaseReturn(PurchaseReturn $purchaseReturn): ?JournalEntry
    {
        $company = $purchaseReturn->company;
        $ap = $this->account($company, 'ACCOUNTS_PAYABLE');
        $expenseAccount = $this->account($company, 'DEFAULT_OPERATING_EXPENSES');
        $vatInput = $this->account($company, 'VAT_INPUT');

        $this->requireAccounts(['ACCOUNTS_PAYABLE' => $ap, 'DEFAULT_OPERATING_EXPENSES' => $expenseAccount, 'VAT_INPUT' => $vatInput], 'purchase return');

        $lines = [
            ['account_id' => $ap->id, 'debit' => $purchaseReturn->total, 'memo' => $purchaseReturn->return_number],
            ['account_id' => $expenseAccount->id, 'credit' => $purchaseReturn->subtotal],
            ['account_id' => $vatInput->id, 'credit' => $purchaseReturn->vat_total],
        ];

        return $this->post($company, 'purchase_return', $purchaseReturn->id, __('Purchase return :number', ['number' => $purchaseReturn->return_number]), $purchaseReturn->issue_date, $lines);
    }

    public function postPaymentVoucher(PaymentVoucher $voucher): ?JournalEntry
    {
        $company = $voucher->company;
        $bankAccount = $voucher->bankAccount;
        $cashOrBank = $bankAccount ? $this->bankOrCashAccount($company, $bankAccount->type) : null;

        // An explicit counter account on the voucher always wins. Otherwise:
        // linked to a bill or expense settles Accounts Payable (the bill or
        // the expense already posted its own accrual); anything else is a
        // direct, unaccrued payment booked straight to operating expenses.
        $counterpart = $voucher->counterAccount
            ?? (($voucher->bill_id || $voucher->expense_id)
                ? $this->account($company, 'ACCOUNTS_PAYABLE')
                : $this->account($company, 'DEFAULT_OPERATING_EXPENSES'));

        $this->requireAccounts(['DEFAULT_CASH/DEFAULT_BANK' => $cashOrBank, 'counterpart' => $counterpart], 'payment voucher');

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

        // Paid immediately from a financial account when one is chosen;
        // otherwise recorded as an accrued payable, to be settled later by
        // a Payment Voucher referencing this expense.
        $bankAccount = $expense->bankAccount;
        $counterpart = $bankAccount
            ? $this->bankOrCashAccount($company, $bankAccount->type)
            : $this->account($company, 'ACCOUNTS_PAYABLE');

        $this->requireAccounts(['expense account' => $expenseAccount, 'counterpart' => $counterpart], 'expense');

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

        $this->requireAccounts(['CUSTOMS_PAYABLE' => $customsPayable], 'customs declaration');

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

        $this->requireAccounts(['INVENTORY_ASSET' => $inventory, 'INVENTORY_ADJUSTMENT' => $adjustmentAccount], 'stock adjustment');

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

        $this->requireAccounts(['from account' => $fromAccount, 'to account' => $toAccount], 'bank transfer');

        return $this->post($company, 'bank_transfer', $transfer->id, __('Transfer :from -> :to', ['from' => $from->name, 'to' => $to->name]), $transfer->date, [
            ['account_id' => $toAccount->id, 'debit' => $transfer->amount],
            ['account_id' => $fromAccount->id, 'credit' => $transfer->amount],
        ]);
    }
}
