<?php

namespace App\Services\Reports;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankStatementLine;
use App\Models\BankTransfer;
use App\Models\Bill;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\CostCenterLink;
use App\Models\Expense;
use App\Models\FixedAsset;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\PaymentMode;
use App\Models\PaymentVoucher;
use App\Models\PosPayment;
use App\Models\ReceiptVoucher;
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
     * "add more reports... Account Statement" with filters by
     * "location, transaction type and user" — a statement of one OR
     * MORE ledger accounts at once (not the single account
     * JournalController::ledger() offers), each with its own opening
     * balance, filtered postings and running balance, narrowable by
     * source type (invoice/bill/manual/...) and the user who posted
     * it. No location filter: a journal line carries no branch/location
     * column of its own in this ledger (only specific source documents
     * like Invoice/Bill do), so a line-level location filter would
     * silently drop every manual/payment-voucher/expense posting —
     * worse than not offering it.
     *
     * When $sourceType or $userId narrows the lines shown, the opening
     * balance stays the account's real, unfiltered balance as of the
     * day before $from (so it still means something), but the running/
     * closing balance in this view is then a running total of only the
     * filtered lines — it will not equal the account's true closing
     * balance unless every excluded line happens to be outside the
     * filter too. That's the deliberate trade-off of an
     * audit/investigation view ("every posting of this type/by this
     * user"), not a bug.
     *
     * @return Collection<int, array{account: Account, openingBalance: float, lines: Collection, closingBalance: float, periodDebit: float, periodCredit: float}>
     */
    public function accountStatementMulti(Company $company, Collection $accountIds, Carbon $from, Carbon $to, ?string $sourceType, ?int $userId): Collection
    {
        return Account::where('company_id', $company->id)
            ->whereIn('id', $accountIds)
            ->orderBy('code')
            ->get()
            ->map(function (Account $account) use ($from, $to, $sourceType, $userId) {
                $openingDebit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->whereDate('entry_date', '<', $from->toDateString()))->sum('debit');
                $openingCredit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->whereDate('entry_date', '<', $from->toDateString()))->sum('credit');
                $openingBalance = $account->normal_balance === 'debit' ? $openingDebit - $openingCredit : $openingCredit - $openingDebit;

                $lines = $account->journalEntryLines()
                    ->with('journalEntry.creator')
                    ->whereHas('journalEntry', function ($q) use ($from, $to, $sourceType, $userId) {
                        $q->whereBetween('entry_date', [$from, $to])
                            ->when($sourceType, fn ($q2) => $q2->where('source_type', $sourceType))
                            ->when($userId, fn ($q2) => $q2->where('created_by', $userId));
                    })
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

    /**
     * A single account's balance as of a date, reused by balanceSheet(),
     * netIncomeToDate(), and statementOfChangesInEquity() so the
     * whereDate()-safe comparison (see FixedAssetSchedule's note: a
     * lone `<=` against this mixed date/datetime column is unreliable
     * when an entry's own time isn't midnight) lives in exactly one
     * place.
     */
    private function accountBalanceAsOf(Account $account, Carbon $asOf): float
    {
        $debit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->whereDate('entry_date', '<=', $asOf->toDateString()))->sum('debit');
        $credit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->whereDate('entry_date', '<=', $asOf->toDateString()))->sum('credit');

        return $account->normal_balance === 'debit' ? $debit - $credit : $credit - $debit;
    }

    /**
     * Retained earnings as of a date: without formal period-close
     * entries, net income to date (revenue minus expenses, since
     * inception) is the equity the books imply but never explicitly
     * post — without it, Assets would never actually equal Liabilities
     * + Equity on the Balance Sheet, or the Statement of Changes in
     * Equity's own opening balance would be short by exactly this.
     */
    private function netIncomeToDate(Company $company, Carbon $asOf): float
    {
        return (float) Account::where('company_id', $company->id)
            ->whereIn('type', ['revenue', 'expense'])
            ->get()
            ->sum(fn (Account $account) => $account->type === 'revenue'
                ? $this->accountBalanceAsOf($account, $asOf)
                : -$this->accountBalanceAsOf($account, $asOf));
    }

    public function balanceSheet(Company $company, Carbon $asOf): array
    {
        $balances = Account::where('company_id', $company->id)
            ->where('is_active', true)
            ->whereIn('type', ['asset', 'liability', 'equity'])
            ->orderBy('code')
            ->get()
            ->map(fn (Account $account) => ['account' => $account, 'balance' => $this->accountBalanceAsOf($account, $asOf)])
            ->filter(fn ($row) => abs($row['balance']) > 0.005)
            ->groupBy(fn ($row) => $row['account']->type);

        $netIncomeToDate = $this->netIncomeToDate($company, $asOf);

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

    /**
     * The fourth standard financial statement a full year-end audit
     * expects alongside the Balance Sheet, Income Statement and Cash
     * Flow — "add more detailed reports... keep in mind final year
     * Audit". Per real equity account (Owner's Capital, Drawings, …):
     * opening balance (as of the day before $from) + this period's own
     * movement (contributions/drawings actually posted to it) =
     * closing balance. Net income for the period is added as its own
     * line, same as balanceSheet()'s synthesized "Current period
     * earnings" row — this ledger never auto-posts profit into a real
     * equity account (see balanceSheet()'s own note on why), so without
     * it the statement's closing total would silently be short.
     *
     * @return array{rows: Collection, openingRetainedEarnings: float, netIncomeForPeriod: float, totalOpening: float, totalMovement: float, totalClosing: float}
     */
    public function statementOfChangesInEquity(Company $company, Carbon $from, Carbon $to): array
    {
        $openingAsOf = $from->copy()->subDay();

        $rows = Account::where('company_id', $company->id)
            ->where('is_active', true)
            ->where('type', 'equity')
            ->orderBy('code')
            ->get()
            ->map(function (Account $account) use ($openingAsOf, $to) {
                $opening = $this->accountBalanceAsOf($account, $openingAsOf);
                $closing = $this->accountBalanceAsOf($account, $to);

                return ['account' => $account, 'opening' => $opening, 'movement' => round($closing - $opening, 2), 'closing' => $closing];
            })
            ->filter(fn ($row) => abs($row['opening']) > 0.005 || abs($row['movement']) > 0.005 || abs($row['closing']) > 0.005)
            ->values();

        $openingRetainedEarnings = $this->netIncomeToDate($company, $openingAsOf);
        $netIncomeForPeriod = $this->incomeStatement($company, $from, $to)['netProfit'];

        $totalOpening = round((float) $rows->sum('opening') + $openingRetainedEarnings, 2);
        $totalMovement = round((float) $rows->sum('movement'), 2);
        $totalClosing = round($totalOpening + $totalMovement + $netIncomeForPeriod, 2);

        return [
            'rows' => $rows,
            'openingRetainedEarnings' => $openingRetainedEarnings,
            'netIncomeForPeriod' => $netIncomeForPeriod,
            'totalOpening' => $totalOpening,
            'totalMovement' => $totalMovement,
            'totalClosing' => $totalClosing,
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
     * "add more reports... Profit & Loss by month" — one column per
     * calendar month of the period, to spot trends and seasonality the
     * single-total Income Statement can't show. Pulls each account's
     * lines once (not once per month) and buckets them in PHP, so this
     * stays two queries per account type regardless of how many months
     * the period spans.
     *
     * @return array{months: Collection<int, Carbon>, revenueLines: Collection, expenseLines: Collection, monthlyIncome: Collection, monthlyExpenses: Collection, monthlyNetProfit: Collection, totalIncome: float, totalExpenses: float, netProfit: float}
     */
    public function incomeStatementByMonth(Company $company, Carbon $from, Carbon $to): array
    {
        $months = collect();
        for ($cursor = $from->copy()->startOfMonth(); $cursor->lte($to); $cursor->addMonth()) {
            $months->push($cursor->copy());
        }

        $revenueLines = $this->monthlyAmountsByType($company, 'revenue', $from, $to, $months);
        $expenseLines = $this->monthlyAmountsByType($company, 'expense', $from, $to, $months);

        $monthlyIncome = $months->mapWithKeys(fn (Carbon $m) => [$m->format('Y-m') => (float) $revenueLines->sum(fn ($r) => $r['monthly'][$m->format('Y-m')])]);
        $monthlyExpenses = $months->mapWithKeys(fn (Carbon $m) => [$m->format('Y-m') => (float) $expenseLines->sum(fn ($r) => $r['monthly'][$m->format('Y-m')])]);
        $monthlyNetProfit = $months->mapWithKeys(fn (Carbon $m) => [$m->format('Y-m') => $monthlyIncome[$m->format('Y-m')] - $monthlyExpenses[$m->format('Y-m')]]);

        return [
            'months' => $months,
            'revenueLines' => $revenueLines,
            'expenseLines' => $expenseLines,
            'monthlyIncome' => $monthlyIncome,
            'monthlyExpenses' => $monthlyExpenses,
            'monthlyNetProfit' => $monthlyNetProfit,
            'totalIncome' => (float) $monthlyIncome->sum(),
            'totalExpenses' => (float) $monthlyExpenses->sum(),
            'netProfit' => (float) $monthlyNetProfit->sum(),
        ];
    }

    /**
     * @return Collection<int, array{account: Account, monthly: Collection<string, float>, total: float}>
     */
    private function monthlyAmountsByType(Company $company, string $type, Carbon $from, Carbon $to, Collection $months): Collection
    {
        $accounts = Account::where('company_id', $company->id)->where('is_active', true)->where('type', $type)->orderBy('code')->get();

        $lines = JournalEntryLine::whereIn('account_id', $accounts->pluck('id'))
            ->whereHas('journalEntry', fn ($q) => $q->whereBetween('entry_date', [$from, $to]))
            ->with('journalEntry:id,entry_date')
            ->get(['id', 'account_id', 'journal_entry_id', 'debit', 'credit']);

        return $accounts->map(function (Account $account) use ($lines, $months) {
            $accountLines = $lines->where('account_id', $account->id);
            $monthly = $months->mapWithKeys(function (Carbon $month) use ($accountLines, $account) {
                $monthLines = $accountLines->filter(fn ($l) => $l->journalEntry->entry_date->isSameMonth($month) && $l->journalEntry->entry_date->isSameYear($month));
                $amount = $account->normal_balance === 'credit'
                    ? (float) $monthLines->sum('credit') - (float) $monthLines->sum('debit')
                    : (float) $monthLines->sum('debit') - (float) $monthLines->sum('credit');

                return [$month->format('Y-m') => $amount];
            });

            return ['account' => $account, 'monthly' => $monthly, 'total' => (float) $monthly->sum()];
        })->filter(fn ($row) => abs($row['total']) > 0.005)->values();
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

    /**
     * "add more reports... bank reconciliation" — one row per bank
     * statement reconciled within the period, across every bank/cash
     * account, so an owner or auditor can see at a glance which
     * accounts are actually reconciled (and whether the book balance
     * their own ledger shows as of that statement date agrees with
     * what the bank said) without opening each account's reconciliation
     * workflow one at a time.
     *
     * @return Collection<int, array{reconciliation: BankReconciliation, bankAccount: \App\Models\BankAccount, statementBalance: float, bookBalance: float, difference: float, outstandingCount: int}>
     */
    public function bankReconciliationSummary(Company $company, Carbon $from, Carbon $to): Collection
    {
        return BankReconciliation::where('company_id', $company->id)
            ->whereBetween('statement_date', [$from, $to])
            ->with('bankAccount.account', 'lines')
            ->orderBy('statement_date')
            ->get()
            ->map(function (BankReconciliation $reconciliation) {
                $bankAccount = $reconciliation->bankAccount;
                $glAccount = $bankAccount?->account;
                $statementBalance = (float) $reconciliation->statement_ending_balance;
                $bookBalance = $glAccount ? $this->accountBalanceAsOf($glAccount, $reconciliation->statement_date) : 0.0;

                return [
                    'reconciliation' => $reconciliation,
                    'bankAccount' => $bankAccount,
                    'statementBalance' => $statementBalance,
                    'bookBalance' => $bookBalance,
                    'difference' => round($statementBalance - $bookBalance, 2),
                    'outstandingCount' => $reconciliation->lines->whereNull('matched_type')->count(),
                ];
            });
    }

    /**
     * "add more reports... uncleared bank items" — deposits not yet on
     * the bank statement and payments/cheques not yet presented, as at
     * a date: every issued receipt/payment voucher, bank transfer leg
     * and directly-paid expense through this account that isn't matched
     * to a statement line in any reconciliation yet. Mirrors
     * BankReconciliationController::unmatchedBookTransactions() — kept
     * as a separate read here (not shared) so this standalone report
     * has no dependency on the reconciliation workflow's own controller,
     * same as every other report in this service.
     *
     * @return Collection<int, array{type: string, id: int, date: Carbon, amount: float, label: string}>
     */
    public function unclearedBankItems(BankAccount $bankAccount, Carbon $asOf): Collection
    {
        $accountId = $bankAccount->id;
        $alreadyMatched = fn (string $type) => BankStatementLine::where('matched_type', $type)->pluck('matched_id')->all();

        $receipts = ReceiptVoucher::where('bank_account_id', $accountId)
            ->where('status', 'issued')
            ->whereDate('date', '<=', $asOf->toDateString())
            ->whereNotIn('id', $alreadyMatched('receipt_voucher'))
            ->get()
            ->map(fn ($v) => ['type' => 'receipt_voucher', 'id' => $v->id, 'date' => $v->date, 'amount' => (float) $v->amount, 'label' => __('Receipt :number — :party', ['number' => $v->voucher_number, 'party' => $v->payer_name])]);

        $payments = PaymentVoucher::where('bank_account_id', $accountId)
            ->where('status', 'issued')
            ->whereDate('date', '<=', $asOf->toDateString())
            ->whereNotIn('id', $alreadyMatched('payment_voucher'))
            ->get()
            ->map(fn ($v) => ['type' => 'payment_voucher', 'id' => $v->id, 'date' => $v->date, 'amount' => -(float) $v->amount, 'label' => __('Payment :number — :party', ['number' => $v->voucher_number, 'party' => $v->payee_name])]);

        $transfersOut = BankTransfer::where('from_bank_account_id', $accountId)
            ->where('status', 'issued')
            ->whereDate('date', '<=', $asOf->toDateString())
            ->whereNotIn('id', $alreadyMatched('bank_transfer'))
            ->get()
            ->map(fn ($t) => ['type' => 'bank_transfer', 'id' => $t->id, 'date' => $t->date, 'amount' => -(float) $t->amount, 'label' => __('Transfer to :account', ['account' => $t->toAccount->name])]);

        $transfersIn = BankTransfer::where('to_bank_account_id', $accountId)
            ->where('status', 'issued')
            ->whereDate('date', '<=', $asOf->toDateString())
            ->whereNotIn('id', $alreadyMatched('bank_transfer'))
            ->get()
            ->map(fn ($t) => ['type' => 'bank_transfer', 'id' => $t->id, 'date' => $t->date, 'amount' => (float) $t->amount, 'label' => __('Transfer from :account', ['account' => $t->fromAccount->name])]);

        $expenses = Expense::where('bank_account_id', $accountId)
            ->where('status', 'approved')
            ->whereDate('expense_date', '<=', $asOf->toDateString())
            ->whereNotIn('id', $alreadyMatched('expense'))
            ->get()
            ->map(fn (Expense $e) => ['type' => 'expense', 'id' => $e->id, 'date' => $e->expense_date, 'amount' => -(float) ($e->gross_amount ?? ($e->amount + $e->vat_amount)), 'label' => __('Expense — :vendor', ['vendor' => $e->vendor_name ?: $e->description])]);

        return $receipts->concat($payments)->concat($transfersOut)->concat($transfersIn)->concat($expenses)->sortBy('date')->values();
    }

    /**
     * "add more reports... journal export (all postings)" — every
     * posting line in the period in one flat list for auditors/external
     * accountants: date, reference, source, account code, debit,
     * credit, cost centre, contact, location and user. Contact/location
     * are only ever as good as the source document carries them — this
     * ledger has no company-wide "location"/"contact" column on
     * JournalEntry itself, so they're a best-effort lookup through the
     * invoice/bill that posted the entry (the two source types with a
     * branch and a party) and come back blank for every other source
     * type (manual, payment_voucher, expense, ...) rather than guessed.
     *
     * @return Collection<int, array{date: Carbon, reference: string, source: ?string, code: string, account: string, description: ?string, debit: float, credit: float, costCentre: ?string, contact: ?string, location: ?string, user: ?string}>
     */
    public function journalExport(Company $company, Carbon $from, Carbon $to): Collection
    {
        $lines = JournalEntryLine::whereHas('journalEntry', fn ($q) => $q->where('company_id', $company->id)->whereBetween('entry_date', [$from, $to]))
            ->with(['account', 'costCenter', 'journalEntry.creator'])
            ->get();

        $invoiceIds = $lines->filter(fn (JournalEntryLine $l) => $l->journalEntry->source_type === 'invoice')->pluck('journalEntry.source_id')->unique();
        $billIds = $lines->filter(fn (JournalEntryLine $l) => $l->journalEntry->source_type === 'bill')->pluck('journalEntry.source_id')->unique();

        $invoices = Invoice::whereIn('id', $invoiceIds)->with('client', 'branch')->get()->keyBy('id');
        $bills = Bill::whereIn('id', $billIds)->with('supplier', 'branch')->get()->keyBy('id');

        return $lines->map(function (JournalEntryLine $line) use ($invoices, $bills) {
            $entry = $line->journalEntry;
            $contact = null;
            $location = null;

            if ($entry->source_type === 'invoice' && $invoice = $invoices->get($entry->source_id)) {
                $contact = $invoice->client?->name;
                $location = $invoice->branch?->name;
            } elseif ($entry->source_type === 'bill' && $bill = $bills->get($entry->source_id)) {
                $contact = $bill->supplier?->name;
                $location = $bill->branch?->name;
            }

            return [
                'date' => $entry->entry_date,
                'reference' => $entry->entry_number,
                'source' => $entry->source_type,
                'code' => $line->account->code,
                'account' => $line->account->name,
                'description' => $entry->description,
                'debit' => (float) $line->debit,
                'credit' => (float) $line->credit,
                'costCentre' => $line->costCenter?->label(),
                'contact' => $contact,
                'location' => $location,
                'user' => $entry->creator?->name,
            ];
        })->sortBy('date')->values();
    }

    /**
     * "add more reports... Profit & Loss by cost centre" — income and
     * expenses split by cost centre, with lines that carry no cost
     * centre shown separately as "Unassigned", matching Hamly's own
     * report. A line counts toward a cost centre two ways: tagged
     * directly on the journal line itself (journal_entry_lines.
     * cost_center_id — the fully general mechanism, for whenever a
     * posting path sets it), or for a revenue line posted from an
     * invoice, through that invoice's own CostCenterLink allocation
     * (split by its allocation_percent when more than one cost centre
     * is linked to the same invoice). Every other line — most expenses
     * today, since nothing yet threads a cost centre onto a bill or
     * expense posting — lands in Unassigned; that's an honest reflection
     * of how little of the ledger is actually cost-centre-tagged yet,
     * not a bug in this report.
     *
     * @return array{rows: Collection<int, array{costCentre: ?CostCenter, income: float, expenses: float, net: float}>, totalIncome: float, totalExpenses: float}
     */
    public function profitLossByCostCentre(Company $company, Carbon $from, Carbon $to): array
    {
        $costCenters = CostCenter::where('company_id', $company->id)->where('is_active', true)->orderBy('code')->get();

        $lines = JournalEntryLine::whereHas('account', fn ($q) => $q->where('company_id', $company->id)->whereIn('type', ['revenue', 'expense']))
            ->whereHas('journalEntry', fn ($q) => $q->whereBetween('entry_date', [$from, $to]))
            ->with('account', 'journalEntry')
            ->get();

        $invoiceLinks = CostCenterLink::where('company_id', $company->id)->where('entity_type', 'invoice')->get()->groupBy('entity_id');

        $totals = $costCenters->mapWithKeys(fn (CostCenter $cc) => [$cc->id => ['income' => 0.0, 'expenses' => 0.0]])->all();
        $unassigned = ['income' => 0.0, 'expenses' => 0.0];

        foreach ($lines as $line) {
            $account = $line->account;
            $signed = $account->normal_balance === 'credit' ? ((float) $line->credit - (float) $line->debit) : ((float) $line->debit - (float) $line->credit);
            $bucket = $account->type === 'revenue' ? 'income' : 'expenses';

            if ($line->cost_center_id && isset($totals[$line->cost_center_id])) {
                $totals[$line->cost_center_id][$bucket] += $signed;

                continue;
            }

            if ($account->type === 'revenue' && $line->journalEntry->source_type === 'invoice' && $invoiceLinks->has($line->journalEntry->source_id)) {
                foreach ($invoiceLinks[$line->journalEntry->source_id] as $link) {
                    if (isset($totals[$link->cost_center_id])) {
                        $totals[$link->cost_center_id][$bucket] += $signed * ((float) $link->allocation_percent / 100);
                    }
                }

                continue;
            }

            $unassigned[$bucket] += $signed;
        }

        $rows = $costCenters->map(fn (CostCenter $cc) => [
            'costCentre' => $cc,
            'income' => round($totals[$cc->id]['income'], 2),
            'expenses' => round($totals[$cc->id]['expenses'], 2),
            'net' => round($totals[$cc->id]['income'] - $totals[$cc->id]['expenses'], 2),
        ])->filter(fn (array $r) => abs($r['income']) > 0.005 || abs($r['expenses']) > 0.005)->values();

        $rows->push([
            'costCentre' => null,
            'income' => round($unassigned['income'], 2),
            'expenses' => round($unassigned['expenses'], 2),
            'net' => round($unassigned['income'] - $unassigned['expenses'], 2),
        ]);

        return [
            'rows' => $rows,
            'totalIncome' => round((float) $rows->sum('income'), 2),
            'totalExpenses' => round((float) $rows->sum('expenses'), 2),
        ];
    }

    /**
     * "add more reports... Profit & Loss by location" — income and
     * expenses split by business location (branch), with lines that
     * carry no location shown separately as "Unassigned". Journal
     * entries themselves carry no branch column, so a line's location
     * is derived through its source document: Invoice and Bill both
     * have branch_id, looked up in two batched queries to avoid N+1.
     * Every other source type (manual, payment_voucher, receipt_voucher,
     * expense, etc.) has no location to derive and lands in Unassigned.
     *
     * @return array{rows: Collection<int, array{branch: ?Branch, income: float, expenses: float, net: float}>, totalIncome: float, totalExpenses: float}
     */
    public function profitLossByLocation(Company $company, Carbon $from, Carbon $to): array
    {
        $branches = Branch::where('company_id', $company->id)->orderBy('code')->get();

        $lines = JournalEntryLine::whereHas('account', fn ($q) => $q->where('company_id', $company->id)->whereIn('type', ['revenue', 'expense']))
            ->whereHas('journalEntry', fn ($q) => $q->whereBetween('entry_date', [$from, $to]))
            ->with('account', 'journalEntry')
            ->get();

        $invoiceIds = $lines->filter(fn (JournalEntryLine $l) => $l->journalEntry->source_type === 'invoice')->pluck('journalEntry.source_id')->unique();
        $billIds = $lines->filter(fn (JournalEntryLine $l) => $l->journalEntry->source_type === 'bill')->pluck('journalEntry.source_id')->unique();

        $invoices = Invoice::whereIn('id', $invoiceIds)->get()->keyBy('id');
        $bills = Bill::whereIn('id', $billIds)->get()->keyBy('id');

        $totals = $branches->mapWithKeys(fn (Branch $b) => [$b->id => ['income' => 0.0, 'expenses' => 0.0]])->all();
        $unassigned = ['income' => 0.0, 'expenses' => 0.0];

        foreach ($lines as $line) {
            $account = $line->account;
            $entry = $line->journalEntry;
            $signed = $account->normal_balance === 'credit' ? ((float) $line->credit - (float) $line->debit) : ((float) $line->debit - (float) $line->credit);
            $bucket = $account->type === 'revenue' ? 'income' : 'expenses';

            $branchId = null;
            if ($entry->source_type === 'invoice' && $invoice = $invoices->get($entry->source_id)) {
                $branchId = $invoice->branch_id;
            } elseif ($entry->source_type === 'bill' && $bill = $bills->get($entry->source_id)) {
                $branchId = $bill->branch_id;
            }

            if ($branchId && isset($totals[$branchId])) {
                $totals[$branchId][$bucket] += $signed;

                continue;
            }

            $unassigned[$bucket] += $signed;
        }

        $rows = $branches->map(fn (Branch $b) => [
            'branch' => $b,
            'income' => round($totals[$b->id]['income'], 2),
            'expenses' => round($totals[$b->id]['expenses'], 2),
            'net' => round($totals[$b->id]['income'] - $totals[$b->id]['expenses'], 2),
        ])->filter(fn (array $r) => abs($r['income']) > 0.005 || abs($r['expenses']) > 0.005)->values();

        $rows->push([
            'branch' => null,
            'income' => round($unassigned['income'], 2),
            'expenses' => round($unassigned['expenses'], 2),
            'net' => round($unassigned['income'] - $unassigned['expenses'], 2),
        ]);

        return [
            'rows' => $rows,
            'totalIncome' => round((float) $rows->sum('income'), 2),
            'totalExpenses' => round((float) $rows->sum('expenses'), 2),
        ];
    }

    /**
     * "add more... Payment Mode Statement" — every POS/restaurant/
     * repair-shop sale payment in a period for one or all payment modes,
     * running total included, same idea as the Bank/Cash Account
     * Statement but for the tender dimension rather than the GL account
     * one — a voided sale's payments are excluded since its journal
     * entry was already reversed. $modeId narrows to a single mode;
     * null returns every active mode's payments together, sorted by date.
     *
     * @return Collection<int, array{date: \Illuminate\Support\Carbon, saleNumber: string, mode: string, amount: float, reference: ?string, runningTotal: float}>
     */
    public function paymentModeStatement(Company $company, Carbon $from, Carbon $to, ?int $modeId = null): Collection
    {
        $modes = PaymentMode::where('company_id', $company->id)
            ->when($modeId, fn ($q) => $q->where('id', $modeId))
            ->get()
            ->keyBy('code');

        $payments = PosPayment::where('company_id', $company->id)
            ->whereIn('method', $modes->keys())
            ->whereHas('sale', fn ($q) => $q->where('status', '!=', 'void')->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]))
            ->with('sale')
            ->get()
            ->sortBy(fn (PosPayment $p) => $p->sale->created_at)
            ->values();

        $running = 0.0;

        return $payments->map(function (PosPayment $payment) use ($modes, &$running) {
            $running += (float) $payment->amount;

            return [
                'date' => $payment->sale->created_at,
                'saleId' => $payment->sale->id,
                'saleNumber' => $payment->sale->sale_number,
                'mode' => $modes->get($payment->method)?->name ?? $payment->method,
                'amount' => round((float) $payment->amount, 2),
                'reference' => $payment->reference,
                'runningTotal' => round($running, 2),
            ];
        });
    }

    /**
     * "add more... Payment Mode Balances" — one row per active payment
     * mode: how much it collected in the period (summed straight off
     * PosPayment, matching paymentModeStatement()'s own filtering) next
     * to its linked GL account's actual closing balance as of the
     * period end — ties day-to-day POS takings back to the ledger, the
     * way a cash/bank reconciliation does for a bank account.
     *
     * @return Collection<int, array{mode: PaymentMode, periodTotal: float, accountBalance: ?float}>
     */
    public function paymentModeBalances(Company $company, Carbon $from, Carbon $to): Collection
    {
        $modes = PaymentMode::where('company_id', $company->id)->where('is_active', true)->with('linkedAccount')->orderBy('sort_order')->orderBy('name')->get();

        $totals = PosPayment::where('company_id', $company->id)
            ->whereHas('sale', fn ($q) => $q->where('status', '!=', 'void')->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]))
            ->selectRaw('method, sum(amount) as total')
            ->groupBy('method')
            ->pluck('total', 'method');

        return $modes->map(fn (PaymentMode $mode) => [
            'mode' => $mode,
            'periodTotal' => round((float) ($totals->get($mode->code) ?? 0), 2),
            'accountBalance' => $mode->linkedAccount ? round($this->accountBalanceAsOf($mode->linkedAccount, $to), 2) : null,
        ]);
    }
}
