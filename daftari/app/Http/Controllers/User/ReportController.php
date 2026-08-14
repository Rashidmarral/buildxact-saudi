<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ResolvesReportPeriod;
use App\Models\Account;
use App\Models\Client;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Item;
use App\Models\Salesperson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    use ResolvesReportPeriod;

    public function vat(Request $request)
    {
        $month = $request->integer('month') ?: now()->month;
        $year = $request->integer('year') ?: now()->year;

        $invoices = Invoice::whereMonth('issue_date', $month)
            ->whereYear('issue_date', $year)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->get();

        $expenses = Expense::whereMonth('expense_date', $month)
            ->whereYear('expense_date', $year)
            ->get();

        $outputVat = $invoices->sum('vat_total');
        $inputVat = $expenses->sum('vat_amount');

        return view('user.reports.vat', [
            'company' => Auth::user()->company,
            'month' => $month,
            'year' => $year,
            'salesTotal' => $invoices->sum('subtotal'),
            'outputVat' => $outputVat,
            'purchasesTotal' => $expenses->sum('amount'),
            'inputVat' => $inputVat,
            'netVatDue' => $outputVat - $inputVat,
            'invoiceCount' => $invoices->count(),
            'expenseCount' => $expenses->count(),
        ]);
    }

    public function trialBalance(Request $request)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);

        $rows = Account::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(function (Account $account) use ($period) {
                $debit = (float) $account->journalEntryLines()
                    ->whereHas('journalEntry', fn ($q) => $q->whereBetween('entry_date', [$period['from'], $period['to']]))
                    ->sum('debit');
                $credit = (float) $account->journalEntryLines()
                    ->whereHas('journalEntry', fn ($q) => $q->whereBetween('entry_date', [$period['from'], $period['to']]))
                    ->sum('credit');

                return [
                    'account' => $account,
                    'debit' => $debit,
                    'credit' => $credit,
                ];
            })
            ->filter(fn ($row) => $row['debit'] > 0 || $row['credit'] > 0)
            ->values();

        return view('user.reports.trial-balance', [
            'company' => $company,
            'period' => $period,
            'rows' => $rows,
            'totalDebit' => $rows->sum('debit'),
            'totalCredit' => $rows->sum('credit'),
        ]);
    }

    public function balanceSheet(Request $request)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);

        $balances = Account::where('company_id', $company->id)
            ->where('is_active', true)
            ->whereIn('type', ['asset', 'liability', 'equity'])
            ->orderBy('code')
            ->get()
            ->map(function (Account $account) use ($period) {
                $debit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->where('entry_date', '<=', $period['to']))->sum('debit');
                $credit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->where('entry_date', '<=', $period['to']))->sum('credit');
                $balance = $account->normal_balance === 'debit' ? $debit - $credit : $credit - $debit;

                return ['account' => $account, 'balance' => $balance];
            })
            ->filter(fn ($row) => abs($row['balance']) > 0.005)
            ->groupBy(fn ($row) => $row['account']->type);

        // Retained earnings: without formal period-close entries, net income
        // to date (revenue minus expenses, since inception) is the equity
        // the books imply but never explicitly post — without it, Assets
        // would never actually equal Liabilities + Equity.
        $netIncomeToDate = Account::where('company_id', $company->id)
            ->whereIn('type', ['revenue', 'expense'])
            ->get()
            ->sum(function (Account $account) use ($period) {
                $debit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->where('entry_date', '<=', $period['to']))->sum('debit');
                $credit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->where('entry_date', '<=', $period['to']))->sum('credit');

                return $account->type === 'revenue' ? $credit - $debit : -($debit - $credit);
            });

        $equity = $balances->get('equity', collect());
        if (abs($netIncomeToDate) > 0.005) {
            $equity = $equity->push(['account' => null, 'label' => __('Retained earnings (current period)'), 'balance' => $netIncomeToDate]);
        }

        $totalAssets = $balances->get('asset', collect())->sum('balance');
        $totalLiabilities = $balances->get('liability', collect())->sum('balance');
        $totalEquity = $equity->sum('balance');

        return view('user.reports.balance-sheet', [
            'company' => $company,
            'period' => $period,
            'assets' => $balances->get('asset', collect()),
            'liabilities' => $balances->get('liability', collect()),
            'equity' => $equity,
            'totalAssets' => $totalAssets,
            'totalLiabilities' => $totalLiabilities,
            'totalEquity' => $totalEquity,
        ]);
    }

    public function incomeStatement(Request $request)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);

        $lines = function (string $type) use ($company, $period) {
            return Account::where('company_id', $company->id)
                ->where('is_active', true)
                ->where('type', $type)
                ->orderBy('code')
                ->get()
                ->map(function (Account $account) use ($period) {
                    $debit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->whereBetween('entry_date', [$period['from'], $period['to']]))->sum('debit');
                    $credit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->whereBetween('entry_date', [$period['from'], $period['to']]))->sum('credit');
                    $amount = $account->normal_balance === 'credit' ? $credit - $debit : $debit - $credit;

                    return ['account' => $account, 'amount' => $amount];
                })
                ->filter(fn ($row) => abs($row['amount']) > 0.005);
        };

        $revenueLines = $lines('revenue');
        $expenseLines = $lines('expense');

        $netSales = (float) $revenueLines->sum('amount');
        $cogsRow = $expenseLines->first(fn ($r) => $r['account']->code === '5000');
        $cogs = (float) ($cogsRow['amount'] ?? 0);
        $operatingExpenses = (float) $expenseLines->reject(fn ($r) => $r['account']->code === '5000')->sum('amount');
        $grossProfit = $netSales - $cogs;
        $operatingProfit = $grossProfit - $operatingExpenses;
        $netProfit = $operatingProfit;

        return view('user.reports.income-statement', [
            'company' => $company,
            'period' => $period,
            'revenueLines' => $revenueLines,
            'expenseLines' => $expenseLines,
            'netSales' => $netSales,
            'grossProfit' => $grossProfit,
            'operatingProfit' => $operatingProfit,
            'netProfit' => $netProfit,
        ]);
    }

    public function sales(Request $request)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);

        $items = collect();
        Invoice::with('items', 'client', 'salesperson')
            ->whereBetween('issue_date', [$period['from'], $period['to']])
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->query('client_id')))
            ->when($request->filled('salesperson_id'), fn ($q) => $q->where('salesperson_id', $request->query('salesperson_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('search'), fn ($q) => $q->where('invoice_number', 'like', '%'.$request->query('search').'%'))
            ->orderByDesc('issue_date')
            ->get()
            ->each(function (Invoice $invoice) use (&$items, $request) {
                foreach ($invoice->items as $line) {
                    if ($request->filled('item_id') && $line->item_id != $request->query('item_id')) {
                        continue;
                    }
                    if ($request->filled('min_amount') && $line->line_total < (float) $request->query('min_amount')) {
                        continue;
                    }
                    if ($request->filled('max_amount') && $line->line_total > (float) $request->query('max_amount')) {
                        continue;
                    }
                    $items->push((object) [
                        'invoice' => $invoice,
                        'line' => $line,
                    ]);
                }
            });

        $sort = $request->query('sort', 'date');
        $direction = $request->query('direction', 'desc');
        $items = $items->sortBy(fn ($row) => match ($sort) {
            'amount' => $row->line->line_total,
            'customer' => $row->invoice->client->name ?? '',
            default => $row->invoice->issue_date,
        }, SORT_REGULAR, $direction === 'desc')->values();

        return view('user.reports.sales', [
            'company' => $company,
            'period' => $period,
            'items' => $items,
            'clients' => Client::orderBy('name')->get(),
            'salespersons' => Salesperson::orderBy('name')->get(),
            'products' => Item::orderBy('name')->get(),
            'invoiceCount' => $items->pluck('invoice.id')->unique()->count(),
            'lineCount' => $items->count(),
            'discountTotal' => $items->pluck('invoice')->unique('id')->sum('discount_total'),
            'taxableSales' => $items->sum(fn ($r) => $r->line->quantity * $r->line->unit_price),
            'taxTotal' => $items->sum('line.vat_amount'),
            'totalSales' => $items->sum('line.line_total'),
        ]);
    }

    public function expenses(Request $request)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);

        $expenses = Expense::with('category')
            ->whereBetween('expense_date', [$period['from'], $period['to']])
            ->when($request->filled('category_id'), fn ($q) => $q->where('expense_category_id', $request->query('category_id')))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q2) => $q2
                ->where('vendor_name', 'like', '%'.$request->query('search').'%')
                ->orWhere('description', 'like', '%'.$request->query('search').'%')))
            ->orderByDesc('expense_date')
            ->get();

        return view('user.reports.expenses', [
            'company' => $company,
            'period' => $period,
            'expenses' => $expenses,
            'categories' => ExpenseCategory::orderBy('name')->get(),
            'total' => $expenses->sum('amount'),
            'vatTotal' => $expenses->sum('vat_amount'),
        ]);
    }

    public function cashFlow(Request $request)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);

        $cashAndBankCodes = ['1000', '1100'];

        $rows = Account::where('company_id', $company->id)
            ->whereIn('code', $cashAndBankCodes)
            ->get()
            ->map(function (Account $account) use ($period) {
                $inflow = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->whereBetween('entry_date', [$period['from'], $period['to']]))->sum('debit');
                $outflow = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->whereBetween('entry_date', [$period['from'], $period['to']]))->sum('credit');
                $openingDebit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->where('entry_date', '<', $period['from']))->sum('debit');
                $openingCredit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->where('entry_date', '<', $period['from']))->sum('credit');

                return [
                    'account' => $account,
                    'opening' => $openingDebit - $openingCredit,
                    'inflow' => $inflow,
                    'outflow' => $outflow,
                    'closing' => ($openingDebit - $openingCredit) + $inflow - $outflow,
                ];
            });

        return view('user.reports.cash-flow', [
            'company' => $company,
            'period' => $period,
            'rows' => $rows,
            'totalInflow' => $rows->sum('inflow'),
            'totalOutflow' => $rows->sum('outflow'),
            'netChange' => $rows->sum('inflow') - $rows->sum('outflow'),
            'openingTotal' => $rows->sum('opening'),
            'closingTotal' => $rows->sum('closing'),
        ]);
    }

    public function accountStatement(Request $request)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);

        $client = null;
        $lines = collect();
        $openingBalance = 0.0;

        if ($request->filled('client_id')) {
            $client = Client::find($request->query('client_id'));
        }

        if ($client) {
            $invoices = $client->invoices()->whereNotIn('status', ['draft'])->get();
            $payments = InvoicePayment::whereIn('invoice_id', $invoices->pluck('id'))->get();

            foreach ($invoices as $invoice) {
                if ($invoice->issue_date->lt($period['from'])) {
                    $openingBalance += $invoice->total;
                } elseif ($invoice->issue_date->lte($period['to'])) {
                    $lines->push((object) ['date' => $invoice->issue_date, 'description' => __('Invoice :number', ['number' => $invoice->invoice_number]), 'debit' => $invoice->total, 'credit' => 0]);
                }
            }
            foreach ($payments as $payment) {
                if ($payment->paid_at->lt($period['from'])) {
                    $openingBalance -= $payment->amount;
                } elseif ($payment->paid_at->lte($period['to'])) {
                    $lines->push((object) ['date' => $payment->paid_at, 'description' => __('Payment received'), 'debit' => 0, 'credit' => $payment->amount]);
                }
            }

            $lines = $lines->sortBy('date')->values();
            $running = $openingBalance;
            $lines = $lines->map(function ($line) use (&$running) {
                $running += $line->debit - $line->credit;
                $line->balance = $running;

                return $line;
            });
        }

        return view('user.reports.account-statement', [
            'company' => $company,
            'period' => $period,
            'clients' => Client::orderBy('name')->get(),
            'client' => $client,
            'lines' => $lines,
            'openingBalance' => $openingBalance,
        ]);
    }
}
