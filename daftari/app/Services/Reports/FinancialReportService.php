<?php

namespace App\Services\Reports;

use App\Models\Account;
use App\Models\Company;
use App\Models\FixedAsset;
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

    /**
     * A real classified Statement of Cash Flows, indirect method — not the
     * cash/bank ledger roll-forward this used to be (that told you WHAT
     * moved through the cash accounts, not WHY, and couldn't answer "is
     * this company burning cash on operations or just spent it on
     * equipment"). Three sections, standard shape:
     *
     *  - Operating: net income, plus non-cash add-backs (depreciation),
     *    plus the swing in working-capital accounts (a receivable going up
     *    ties up cash even though it's booked as revenue; a payable going
     *    up delays a cash outflow that's already booked as an expense).
     *  - Investing: real fixed-asset acquisitions/disposals, read directly
     *    from the FixedAsset register (acquisition_cost / disposal_proceeds)
     *    rather than inferred from the Fixed Assets account's GL delta —
     *    the register already knows exactly what was bought or sold and
     *    for how much, so there's no need to guess from a debit/credit mix
     *    that also contains depreciation's own postings.
     *  - Financing: the net change in equity-type accounts. Net income is
     *    never auto-posted into equity in this ledger (see
     *    balanceSheet()'s own note on why) — so any equity movement here
     *    is a real capital transaction (an owner contribution or a
     *    drawing), not double-counted profit.
     *
     * 'reconciled' cross-checks the three sections' net change against the
     * cash & bank accounts' actual balance change over the period, the
     * same spirit as balanceSheet()'s own 'balanced' flag — informational,
     * not a hard requirement, since this reads acquisitions/disposals as
     * fully cash-funded and can't see a transaction a company financed on
     * credit without a dedicated loan account to track it against.
     */
    public function cashFlow(Company $company, Carbon $from, Carbon $to): array
    {
        $cashAccountCodes = ['1000', '1100'];
        $fixedAssetCodes = ['1500', '1550'];

        $balanceAt = function (Account $account, Carbon $asOf) {
            $debit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->where('entry_date', '<=', $asOf))->sum('debit');
            $credit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->where('entry_date', '<=', $asOf))->sum('credit');

            return $account->normal_balance === 'debit' ? $debit - $credit : $credit - $debit;
        };

        $income = $this->incomeStatement($company, $from, $to);
        $netIncome = (float) $income['netProfit'];

        $depreciation = (float) Account::where('company_id', $company->id)
            ->where('code', '5150')
            ->get()
            ->sum(fn (Account $a) => (float) $a->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->whereBetween('entry_date', [$from, $to]))->sum('debit')
                - (float) $a->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->whereBetween('entry_date', [$from, $to]))->sum('credit'));

        $workingCapitalLines = Account::where('company_id', $company->id)
            ->where('is_active', true)
            ->whereIn('type', ['asset', 'liability'])
            ->whereNotIn('code', [...$cashAccountCodes, ...$fixedAssetCodes])
            ->orderBy('code')
            ->get()
            ->map(function (Account $account) use ($balanceAt, $from, $to) {
                $change = $balanceAt($account, $to) - $balanceAt($account, $from->copy()->subDay());

                // A receivable/inventory increase ties up cash (outflow); a
                // payable/accrual increase delays a cash outflow (inflow).
                $cashEffect = $account->type === 'asset' ? -$change : $change;

                return ['account' => $account, 'change' => $change, 'cashEffect' => $cashEffect];
            })
            ->filter(fn ($row) => abs($row['cashEffect']) > 0.005)
            ->values();

        $workingCapitalTotal = (float) $workingCapitalLines->sum('cashEffect');
        $operatingTotal = $netIncome + $depreciation + $workingCapitalTotal;

        $acquisitions = (float) FixedAsset::where('company_id', $company->id)->whereBetween('acquisition_date', [$from, $to])->sum('acquisition_cost');
        $disposalProceeds = (float) FixedAsset::where('company_id', $company->id)->whereBetween('disposed_at', [$from, $to])->sum('disposal_proceeds');
        $investingTotal = $disposalProceeds - $acquisitions;

        $equityLines = Account::where('company_id', $company->id)
            ->where('is_active', true)
            ->where('type', 'equity')
            ->orderBy('code')
            ->get()
            ->map(fn (Account $account) => ['account' => $account, 'change' => $balanceAt($account, $to) - $balanceAt($account, $from->copy()->subDay())])
            ->filter(fn ($row) => abs($row['change']) > 0.005)
            ->values();

        $financingTotal = (float) $equityLines->sum('change');
        $netChange = $operatingTotal + $investingTotal + $financingTotal;

        $cashAccounts = Account::where('company_id', $company->id)->whereIn('code', $cashAccountCodes)->get();
        $openingCash = (float) $cashAccounts->sum(fn (Account $a) => $balanceAt($a, $from->copy()->subDay()));
        $actualClosingCash = (float) $cashAccounts->sum(fn (Account $a) => $balanceAt($a, $to));

        return [
            'netIncome' => $netIncome,
            'depreciation' => $depreciation,
            'workingCapitalLines' => $workingCapitalLines,
            'workingCapitalTotal' => $workingCapitalTotal,
            'operatingTotal' => $operatingTotal,
            'acquisitions' => $acquisitions,
            'disposalProceeds' => $disposalProceeds,
            'investingTotal' => $investingTotal,
            'equityLines' => $equityLines,
            'financingTotal' => $financingTotal,
            'netChange' => $netChange,
            'openingCash' => $openingCash,
            'closingCash' => $openingCash + $netChange,
            'actualClosingCash' => $actualClosingCash,
            'reconciled' => abs(($openingCash + $netChange) - $actualClosingCash) < 0.01,
        ];
    }
}
