<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\BankTransfer;
use App\Models\Expense;
use App\Models\Income;
use App\Models\InvoicePayment;
use App\Models\PaymentVoucher;
use App\Models\ReceiptVoucher;
use Illuminate\Support\Collection;

/**
 * The shared running-balance cash ledger behind both the Project Cash
 * Flow module (ProjectCashFlowController) and the ungated per-account
 * Bank Account Report (BankAccountReportController) — extracted out of
 * ProjectCashFlowController, which originally had the only copy, so the
 * two features read the exact same cash-movement logic (Receipt/Payment
 * Vouchers, directly-paid Expenses, bank-tagged InvoicePayments and
 * Income, and BankTransfers) instead of risking two copies drifting
 * apart. Logic and comments carried over unchanged from that original
 * buildLedger()/summarize() pair.
 */
class BankAccountLedgerService
{
    /**
     * Merges receipts/payments/transfers (plus directly-paid expenses,
     * invoice payments, and other income) into one chronological list
     * with an explicit in/out split, then walks it accumulating a
     * running balance.
     *
     * For a bank-account context, a transfer's direction is derived from
     * which side of from/to matches the account (mirrors
     * BankAccount::currentBalance()'s own transfer handling). For a
     * project-only context (no bank account given), a transfer tagged to
     * the project is always an outflow — see Project::cashTransferredOut().
     */
    public function build(?int $projectId, ?int $bankAccountId, ?string $from = null, ?string $to = null): array
    {
        $receipts = ReceiptVoucher::with('bankAccount', 'project')
            ->where('status', 'issued')
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->when($bankAccountId, fn ($q) => $q->where('bank_account_id', $bankAccountId))
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
            ->get()
            ->map(fn (ReceiptVoucher $v) => [
                'date' => $v->date,
                'created_at' => $v->created_at,
                'id' => 'receipt-'.$v->id,
                'type' => 'receipt',
                'number' => $v->voucher_number,
                'party' => $v->payer_name,
                'account' => $v->bankAccount?->name,
                'in_amount' => (float) $v->amount,
                'out_amount' => 0.0,
                'affects_balance' => true,
                'url' => route('app.receipt-vouchers.show', $v),
            ]);

        $payments = PaymentVoucher::with('bankAccount', 'project')
            ->where('status', 'issued')
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->when($bankAccountId, fn ($q) => $q->where('bank_account_id', $bankAccountId))
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
            ->get()
            ->map(fn (PaymentVoucher $v) => [
                'date' => $v->date,
                'created_at' => $v->created_at,
                'id' => 'payment-'.$v->id,
                'type' => 'payment',
                'number' => $v->voucher_number,
                'party' => $v->payee_name,
                'account' => $v->bankAccount?->name,
                'in_amount' => 0.0,
                'out_amount' => (float) $v->amount,
                'affects_balance' => true,
                'url' => route('app.payment-vouchers.show', $v),
            ]);

        // Expenses paid directly out of an account (no Payment Voucher
        // involved at all — see BankAccount::currentBalance()) are just as
        // real a cash-out as a Payment Voucher. An unpaid Expense
        // (bank_account_id null) hasn't touched any account yet, and only
        // 'approved' ones have actually posted.
        $expenses = Expense::with('bankAccount', 'project', 'category')
            ->whereNotNull('bank_account_id')
            ->where('status', 'approved')
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->when($bankAccountId, fn ($q) => $q->where('bank_account_id', $bankAccountId))
            ->when($from, fn ($q) => $q->whereDate('expense_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('expense_date', '<=', $to))
            ->get()
            ->map(fn (Expense $e) => [
                'date' => $e->expense_date,
                'created_at' => $e->created_at,
                'id' => 'expense-'.$e->id,
                'type' => 'expense',
                'number' => $e->reference,
                'party' => $e->vendor_name ?: ($e->description ?: __('Expense')),
                'account' => $e->bankAccount?->name,
                'category' => $e->category?->name,
                'in_amount' => 0.0,
                'out_amount' => (float) $e->gross_amount,
                'affects_balance' => true,
                'url' => route('app.expenses.edit', $e),
            ]);

        // A client's invoice payment is just as real a cash-in as a
        // Receipt Voucher — only payments with a bank_account_id set
        // (InvoiceController::storePayment()) can appear here at all.
        $invoicePayments = InvoicePayment::whereNotNull('bank_account_id')
            ->with('bankAccount', 'invoice.client', 'invoice.project')
            ->whereHas('invoice', fn ($q) => $q->when($projectId, fn ($q2) => $q2->where('project_id', $projectId)))
            ->when($bankAccountId, fn ($q) => $q->where('bank_account_id', $bankAccountId))
            ->when($from, fn ($q) => $q->whereDate('paid_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('paid_at', '<=', $to))
            ->get()
            ->map(fn (InvoicePayment $p) => [
                'date' => $p->paid_at,
                'created_at' => $p->created_at,
                'id' => 'invoice_payment-'.$p->id,
                'type' => 'invoice_payment',
                'number' => $p->invoice->invoice_number,
                'party' => $p->invoice->client?->name,
                'account' => $p->bankAccount?->name,
                'in_amount' => (float) $p->amount,
                'out_amount' => 0.0,
                'affects_balance' => true,
                'url' => route('app.invoices.show', $p->invoice),
            ]);

        // General income not tied to an invoice — uses gross_amount, same
        // reasoning as $expenses above: the account actually received the
        // full amount including VAT collected.
        $incomes = Income::with('bankAccount', 'project')
            ->whereNotNull('bank_account_id')
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->when($bankAccountId, fn ($q) => $q->where('bank_account_id', $bankAccountId))
            ->when($from, fn ($q) => $q->whereDate('income_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('income_date', '<=', $to))
            ->get()
            ->map(fn (Income $i) => [
                'date' => $i->income_date,
                'created_at' => $i->created_at,
                'id' => 'income-'.$i->id,
                'type' => 'income',
                'number' => $i->reference,
                'party' => $i->payer_name ?: ($i->description ?: __('Income')),
                'account' => $i->bankAccount?->name,
                'in_amount' => (float) $i->gross_amount,
                'out_amount' => 0.0,
                'affects_balance' => true,
                'url' => route('app.incomes.edit', $i),
            ]);

        $transfers = BankTransfer::with('fromAccount', 'toAccount', 'project')
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->when($bankAccountId, fn ($q) => $q->where(fn ($q2) => $q2->where('from_bank_account_id', $bankAccountId)->orWhere('to_bank_account_id', $bankAccountId)))
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
            ->get()
            ->map(function (BankTransfer $t) use ($bankAccountId) {
                $isIncoming = $bankAccountId && (int) $t->to_bank_account_id === $bankAccountId;

                return [
                    'date' => $t->date,
                    'created_at' => $t->created_at,
                    'id' => 'transfer-'.$t->id,
                    // BankTransfer::kind() distinguishes a withdrawal
                    // (bank→cash) and a deposit (cash→bank) from a plain
                    // transfer — this only changes how the row reads on
                    // the statement, so a withdrawal is instantly
                    // recognizable from a plain account-to-account move.
                    'type' => $t->kind(),
                    'number' => null,
                    'party' => __(':from → :to', ['from' => $t->fromAccount->name, 'to' => $t->toAccount->name]),
                    'account' => $bankAccountId ? ($isIncoming ? $t->toAccount->name : $t->fromAccount->name) : null,
                    'in_amount' => $isIncoming ? (float) $t->amount : 0.0,
                    'out_amount' => $isIncoming ? 0.0 : (float) $t->amount,
                    // A transfer genuinely moves money into/out of the one
                    // account being viewed, so it must affect that
                    // account's own running balance. But viewed project-
                    // wide (no single account picked), it's just money
                    // relocating between two of the company's own
                    // accounts — not yet spent — so it must NOT also
                    // subtract from the project's total, or a withdrawal
                    // followed by an Expense/Payment Voucher paid out of
                    // the destination account would count as spent twice.
                    'affects_balance' => $bankAccountId !== null,
                    'url' => route('app.bank-transfers.show', $t),
                ];
            });

        // Sorting by date alone isn't enough: several rows sharing the
        // same date (very common — a same-day withdrawal followed by a
        // cash payment out of it) would otherwise fall back to this
        // collection's concatenation order (receipts, then payments, then
        // transfers) rather than the order they actually happened in,
        // which could show a payment before the withdrawal that funded
        // it — a nonsensical dip into negative balance on the statement.
        // created_at is a wall-clock tiebreaker across all record types,
        // but the created_at/updated_at columns only store whole-second
        // precision, so two rows saved within the same second still tie
        // there. A last tiebreaker settles that: an incoming amount sorts
        // before an outgoing one at the same instant, since money can't
        // fund a payment before it arrives.
        $rows = $receipts->concat($payments)->concat($expenses)->concat($invoicePayments)->concat($incomes)->concat($transfers)
            ->sortBy(fn (array $row) => $row['date']->format('Y-m-d').'-'.$row['created_at']->format('Y-m-d H:i:s').'-'.($row['in_amount'] > 0 ? '0' : '1'))
            ->values();

        $openingBalance = $bankAccountId ? (float) BankAccount::find($bankAccountId)?->opening_balance : 0.0;
        $balance = $openingBalance;

        $rows = $rows->map(function (array $row) use (&$balance) {
            // A transfer viewed project-wide (see the 'transfers' map
            // above) carries affects_balance=false: it's real money moving
            // between two of the company's own accounts, not spending, and
            // whatever is later paid out of the destination account is
            // already counted in its own row — letting this row also
            // subtract would count that same money as spent twice.
            if ($row['affects_balance'] ?? true) {
                $balance += $row['in_amount'] - $row['out_amount'];
            }
            $row['balance_after'] = $balance;

            return $row;
        });

        return [
            'rows' => $rows,
            'opening_balance' => $openingBalance,
            'closing_balance' => $balance,
        ];
    }

    /**
     * "i am unable to calculate the expenses" — summary tiles derived
     * from the same filtered $rows the table itself shows, rather than
     * all-time totals that ignore whatever account/date filter narrowed
     * the ledger.
     */
    public function summarize(Collection $rows): array
    {
        $received = (float) $rows->whereIn('type', ['receipt', 'invoice_payment', 'income'])->sum('in_amount');
        $paidVouchers = (float) $rows->where('type', 'payment')->sum('out_amount');
        $expensesTotal = (float) $rows->where('type', 'expense')->sum('out_amount');
        $transferred = (float) $rows->whereIn('type', ['withdrawal', 'deposit', 'transfer'])->sum('out_amount');

        $byType = $rows->groupBy('type')->map(fn ($group, $type) => [
            'type' => $type,
            'count' => $group->count(),
            'in' => (float) $group->sum('in_amount'),
            'out' => (float) $group->sum('out_amount'),
        ])->values();

        $expenseByCategory = $rows->where('type', 'expense')
            ->groupBy(fn (array $row) => $row['category'] ?: __('Uncategorized'))
            ->map(fn ($group, $category) => [
                'category' => $category,
                'count' => $group->count(),
                'total' => (float) $group->sum('out_amount'),
            ])
            ->sortByDesc('total')
            ->values();

        return [
            'received' => $received,
            'paid' => $paidVouchers + $expensesTotal,
            'paid_vouchers' => $paidVouchers,
            'expenses' => $expensesTotal,
            'transferred' => $transferred,
            'net' => $received - ($paidVouchers + $expensesTotal),
            'by_type' => $byType,
            'expense_by_category' => $expenseByCategory,
        ];
    }
}
