<?php

namespace App\Services\Reports;

use App\Models\Account;
use App\Models\Company;
use App\Models\FixedAsset;
use App\Models\JournalEntry;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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

    /**
     * The full, multi-account General Ledger — the single most-requested
     * document in any year-end audit PBC list (every account, its opening
     * balance, every line posted to it in the period, and its closing
     * balance), as distinct from JournalController::ledger() which only
     * ever shows one account the user picks from a dropdown with no
     * export. An account with no opening balance and no activity in the
     * period is left out entirely — same convention as trialBalance().
     *
     * @return Collection<int, array{account: Account, openingBalance: float, lines: Collection, closingBalance: float, periodDebit: float, periodCredit: float}>
     */
    public function generalLedger(Company $company, Carbon $from, Carbon $to): Collection
    {
        return Account::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(function (Account $account) use ($from, $to) {
                // whereDate(), not a raw Carbon comparison — see
                // fixedAssetSchedule()'s note: a lone `<`/`<=` against
                // this mixed date/datetime column is unreliable for an
                // entry whose own time isn't midnight (e.g. a month-end
                // depreciation run or a same-day reversal).
                $openingDebit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->whereDate('entry_date', '<', $from->toDateString()))->sum('debit');
                $openingCredit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->whereDate('entry_date', '<', $from->toDateString()))->sum('credit');
                $openingBalance = $account->normal_balance === 'debit' ? $openingDebit - $openingCredit : $openingCredit - $openingDebit;

                $lines = $account->journalEntryLines()
                    ->with('journalEntry')
                    ->whereHas('journalEntry', fn ($q) => $q->whereBetween('entry_date', [$from, $to]))
                    ->get()
                    ->sortBy(fn ($l) => $l->journalEntry->entry_date.'-'.$l->id)
                    ->values();

                $running = $openingBalance;
                $lines = $lines->map(function ($line) use (&$running, $account) {
                    $running += $account->normal_balance === 'debit' ? ($line->debit - $line->credit) : ($line->credit - $line->debit);
                    $line->running_balance = $running;

                    return $line;
                });

                return [
                    'account' => $account,
                    'openingBalance' => $openingBalance,
                    'lines' => $lines,
                    'closingBalance' => $running,
                    'periodDebit' => (float) $lines->sum('debit'),
                    'periodCredit' => (float) $lines->sum('credit'),
                ];
            })
            ->filter(fn ($row) => $row['lines']->isNotEmpty() || abs($row['openingBalance']) > 0.005 || abs($row['closingBalance']) > 0.005)
            ->values();
    }

    /**
     * The #2 most-requested document on a year-end audit PBC list after
     * the General Ledger: a fixed asset roll-forward — opening net book
     * value + additions − disposals − depreciation for the period =
     * closing net book value, tied to the GL rather than guessed from
     * the FixedAsset row's own running accumulated_depreciation (which
     * is a to-date total, not period-scoped). Depreciation posted for an
     * asset is found via FixedAssetDepreciationService's own
     * (source_type='fixed_asset_depreciation', source_id packing) —
     * each such entry's total debit is that run's depreciation amount,
     * account-agnostic so this doesn't care which account mapping a
     * company configured.
     *
     * An asset not yet acquired by $to, or already disposed before
     * $from, has nothing to say about this period and is left out.
     *
     * @return Collection<int, array{asset: FixedAsset, openingNbv: float, additions: float, depreciation: float, disposals: float, closingNbv: float}>
     */
    public function fixedAssetSchedule(Company $company, Carbon $from, Carbon $to): Collection
    {
        $depreciationThrough = function (FixedAsset $asset, Carbon $asOf) {
            // whereDate() wraps the column in SQL date(...) so a
            // depreciation entry's end-of-month timestamp (23:59:59, see
            // AssetDepreciationService) compares correctly against a
            // plain cutoff date — a raw `where('entry_date', '<=', ...)`
            // comparison is unreliable for a mixed date/datetime column.
            return (float) JournalEntry::where('company_id', $asset->company_id)
                ->where('source_type', 'fixed_asset_depreciation')
                ->whereBetween('source_id', [$asset->id * 1_000_000, $asset->id * 1_000_000 + 999_999])
                ->whereDate('entry_date', '<=', $asOf->toDateString())
                ->with('lines')
                ->get()
                ->sum(fn (JournalEntry $je) => $je->totalDebit());
        };

        return FixedAsset::where('company_id', $company->id)
            ->where('acquisition_date', '<=', $to)
            ->where(fn ($q) => $q->whereNull('disposed_at')->orWhere('disposed_at', '>=', $from))
            ->orderBy('acquisition_date')
            ->get()
            ->map(function (FixedAsset $asset) use ($from, $to, $depreciationThrough) {
                $existedBeforePeriod = $asset->acquisition_date->lt($from);
                $openingNbv = $existedBeforePeriod
                    ? round((float) $asset->acquisition_cost - $depreciationThrough($asset, $from->copy()->subDay()), 2)
                    : 0.0;
                $additions = $existedBeforePeriod ? 0.0 : round((float) $asset->acquisition_cost, 2);
                $depreciation = round($depreciationThrough($asset, $to) - $depreciationThrough($asset, $from->copy()->subDay()), 2);

                $disposedThisPeriod = $asset->disposed_at && $asset->disposed_at->between($from, $to);
                $nbvBeforeDisposal = round($openingNbv + $additions - $depreciation, 2);
                $disposals = $disposedThisPeriod ? $nbvBeforeDisposal : 0.0;

                return [
                    'asset' => $asset,
                    'openingNbv' => $openingNbv,
                    'additions' => $additions,
                    'depreciation' => $depreciation,
                    'disposals' => $disposals,
                    'closingNbv' => round($nbvBeforeDisposal - $disposals, 2),
                ];
            })
            ->values();
    }

    /**
     * "add more detailed reports... compare" — merges a report's current-
     * period line rows with the same shape computed for a comparison
     * period (prior period / prior year), matching how Zoho/QuickBooks/
     * Xero show both side by side rather than making you run the report
     * twice. Keys on the account id where there is one, or on the
     * pseudo-row's own 'key'/'label' (balanceSheet()'s synthesized
     * "Current period earnings" row has no account). An account that
     * only has activity in one of the two periods still gets a row,
     * with the other side at 0 — never silently dropped.
     *
     * @param  Collection<int, array<string, mixed>>  $current
     * @param  Collection<int, array<string, mixed>>  $prior
     * @return Collection<int, array<string, mixed>>
     */
    public function mergeComparison(Collection $current, Collection $prior, string $valueKey): Collection
    {
        $keyFor = fn ($row) => $row['account']?->id ?? 'pseudo:'.($row['key'] ?? $row['label'] ?? '');
        $merged = [];

        foreach ($current as $row) {
            $merged[$keyFor($row)] = $row + ['priorAmount' => 0.0];
        }

        foreach ($prior as $row) {
            $key = $keyFor($row);
            if (isset($merged[$key])) {
                $merged[$key]['priorAmount'] = (float) $row[$valueKey];
            } else {
                $merged[$key] = [...$row, $valueKey => 0.0, 'priorAmount' => (float) $row[$valueKey]];
            }
        }

        return collect($merged)
            ->sortBy(fn ($row) => $row['account']?->code ?? $row['label'] ?? '')
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
                // whereDate(), not a raw Carbon comparison — see
                // FixedAssetSchedule's note: a lone `<=` against this
                // mixed date/datetime column is unreliable when an
                // entry's own time isn't midnight.
                $debit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->whereDate('entry_date', '<=', $asOf->toDateString()))->sum('debit');
                $credit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->whereDate('entry_date', '<=', $asOf->toDateString()))->sum('credit');
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
                $debit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->whereDate('entry_date', '<=', $asOf->toDateString()))->sum('debit');
                $credit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->whereDate('entry_date', '<=', $asOf->toDateString()))->sum('credit');

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
            'priorPeriodAdjustments' => $this->priorPeriodAdjustments($company, $from, $to),
        ];
    }

    /**
     * "how this showing profit this month... these vouchers assigned to
     * project manager... this payment will not be considered as used
     * expenses where goes" — the real cause: voiding a Bill/Invoice/
     * Payment Voucher/Receipt Voucher posts a reversing entry dated the
     * moment it's voided (see LedgerPostingService::reverse()), not
     * back-dated to the original document's own date — correct practice
     * (a closed period's figures are never silently rewritten), but it
     * means a prior month's expense/revenue can flip in THIS month's
     * Income Statement with nothing else around it to explain the swing,
     * exactly the "0 paid / 0 expenses / 0 purchases but SAR X profit"
     * pattern reported. This finds every such reversal landing in
     * [$from, $to] whose own original entry was dated outside it, so the
     * report can show it as a labelled adjustment instead of a mystery.
     *
     * Each row's 'amount' is only the reversal's net effect on THIS
     * statement's own revenue/expense accounts (a reversal's other leg —
     * Accounts Payable, Bank, etc. — is a balance-sheet matter and
     * already excluded), signed so a positive amount means the reversal
     * INCREASED this period's reported net profit (undoing a prior
     * period's expense) and a negative amount means it DECREASED it
     * (undoing a prior period's revenue) — this is a breakdown of what
     * the totals above already include, not an addition to them.
     *
     * @return Collection<int, array{entry: JournalEntry, original: JournalEntry, label: string, originalDate: Carbon, amount: float}>
     */
    public function priorPeriodAdjustments(Company $company, Carbon $from, Carbon $to): Collection
    {
        return JournalEntry::where('company_id', $company->id)
            ->whereBetween('entry_date', [$from, $to])
            ->where('source_type', 'like', '%_reversal')
            ->with('lines.account')
            ->get()
            ->map(function (JournalEntry $entry) use ($company) {
                $originalType = Str::beforeLast($entry->source_type, '_reversal');
                $original = JournalEntry::where('company_id', $company->id)
                    ->where('source_type', $originalType)
                    ->where('source_id', $entry->source_id)
                    ->first();

                return ['entry' => $entry, 'original' => $original];
            })
            ->filter(fn ($row) => $row['original'] && ! $row['original']->entry_date->between($from, $to))
            ->map(function ($row) {
                $pnlDelta = $row['entry']->lines
                    ->filter(fn ($line) => in_array($line->account->type, ['revenue', 'expense'], true))
                    ->sum(function ($line) {
                        $netCredit = $line->account->normal_balance === 'credit' ? $line->credit - $line->debit : $line->debit - $line->credit;

                        return $line->account->type === 'revenue' ? $netCredit : -$netCredit;
                    });

                return [
                    'entry' => $row['entry'],
                    'original' => $row['original'],
                    'label' => $row['entry']->description,
                    'originalDate' => $row['original']->entry_date,
                    'amount' => (float) $pnlDelta,
                ];
            })
            ->values();
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
            // whereDate(), not a raw Carbon comparison — see
            // fixedAssetSchedule()'s note on why a lone `<=` against this
            // mixed date/datetime column is unreliable.
            $debit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->whereDate('entry_date', '<=', $asOf->toDateString()))->sum('debit');
            $credit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->whereDate('entry_date', '<=', $asOf->toDateString()))->sum('credit');

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
