<?php

namespace App\Services\Reports;

use App\Models\Account;
use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * The GL-derived computations behind the Trial Balance, Balance Sheet and
 * Income Statement report pages (User\ReportController), extracted so the
 * public API (Api\V1\ReportApiController) can return the exact same
 * figures as JSON instead of re-deriving them from the ledger a second
 * time in a way that could quietly drift from the web report.
 */
class FinancialReportService
{
    /**
     * @return Collection<int, array{account: Account, debit: float, credit: float}>
     */
    public function trialBalance(Company $company, Carbon $from, Carbon $to): Collection
    {
        return Account::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(function (Account $account) use ($from, $to) {
                $debit = (float) $account->journalEntryLines()
                    ->whereHas('journalEntry', fn ($q) => $q->whereBetween('entry_date', [$from, $to]))
                    ->sum('debit');
                $credit = (float) $account->journalEntryLines()
                    ->whereHas('journalEntry', fn ($q) => $q->whereBetween('entry_date', [$from, $to]))
                    ->sum('credit');

                return ['account' => $account, 'debit' => $debit, 'credit' => $credit];
            })
            ->filter(fn ($row) => $row['debit'] > 0 || $row['credit'] > 0)
            ->values();
    }

    public function balanceSheet(Company $company, Carbon $asOf): array
    {
        $balances = Account::where('company_id', $company->id)
            ->where('is_active', true)
            ->whereIn('type', ['asset', 'liability', 'equity'])
            ->orderBy('code')
            ->get()
            ->map(function (Account $account) use ($asOf) {
                $debit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->where('entry_date', '<=', $asOf))->sum('debit');
                $credit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->where('entry_date', '<=', $asOf))->sum('credit');
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
            ->sum(function (Account $account) use ($asOf) {
                $debit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->where('entry_date', '<=', $asOf))->sum('debit');
                $credit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->where('entry_date', '<=', $asOf))->sum('credit');

                return $account->type === 'revenue' ? $credit - $debit : -($debit - $credit);
            });

        $equity = $balances->get('equity', collect());
        if (abs($netIncomeToDate) > 0.005) {
            $equity = $equity->push(['account' => null, 'key' => 'CURRENT_EARNINGS', 'label' => __('Current period earnings'), 'balance' => $netIncomeToDate]);
        }

        $assets = $balances->get('asset', collect());
        $liabilities = $balances->get('liability', collect());
        $totalAssets = $assets->sum('balance');
        $totalLiabilities = $liabilities->sum('balance');
        $totalEquity = $equity->sum('balance');

        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'totalAssets' => $totalAssets,
            'totalLiabilities' => $totalLiabilities,
            'totalEquity' => $totalEquity,
            'balanced' => abs($totalAssets - ($totalLiabilities + $totalEquity)) < 0.01,
        ];
    }

    public function incomeStatement(Company $company, Carbon $from, Carbon $to): array
    {
        $lines = function (string $type) use ($company, $from, $to) {
            return Account::where('company_id', $company->id)
                ->where('is_active', true)
                ->where('type', $type)
                ->orderBy('code')
                ->get()
                ->map(function (Account $account) use ($from, $to) {
                    $debit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->whereBetween('entry_date', [$from, $to]))->sum('debit');
                    $credit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->whereBetween('entry_date', [$from, $to]))->sum('credit');
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

        return [
            'revenueLines' => $revenueLines,
            'expenseLines' => $expenseLines,
            'netSales' => $netSales,
            'grossProfit' => $grossProfit,
            'operatingProfit' => $operatingProfit,
            'netProfit' => $operatingProfit,
        ];
    }
}
