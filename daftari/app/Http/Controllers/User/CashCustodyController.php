<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\BankTransfer;
use App\Models\Expense;
use App\Services\Features\FeatureAccessService;
use App\Services\MpdfRenderer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;

/**
 * "how can i track payment for him" — a company that hands cash floats to
 * site staff (an engineer who spends on-site costs, someone who runs daily
 * local errands) already has everything needed: flag their float as its
 * own BankAccount (type cash or bank) with is_personal + personal_owner_name
 * set (BankAccount::$fillable), and its own running-balance statement
 * (Project Cash Flow > that account) already shows the advance in, every
 * spend out, and what's still unaccounted with them. This page just
 * collects every such account into one glance instead of opening each
 * custodian's statement one at a time.
 *
 * "please suggest me ... how can i get custody account reports" —
 * report()/pdf() add a cross-custodian summary (advanced/spent/returned/
 * balance per person, total out) on top of that glance, independent of
 * the separately-gated Project Cash Flow module so it's useful even
 * without it. days_idle flags a custodian who's been sitting on an
 * unspent float for a while, so an advance doesn't just get forgotten.
 */
class CashCustodyController extends Controller
{
    private const IDLE_ALERT_DAYS = 30;

    public function index(FeatureAccessService $featureAccess)
    {
        $accounts = BankAccount::where('is_personal', true)
            ->orderBy('personal_owner_name')
            ->orderBy('name')
            ->get();

        $otherAccounts = BankAccount::where('is_personal', false)->where('is_active', true)->orderBy('name')->get();

        return view('user.cash-custody.index', [
            'accounts' => $accounts,
            'otherAccounts' => $otherAccounts,
            'totalOutstanding' => $accounts->sum(fn (BankAccount $account) => $account->currentBalance()),
            'idleDays' => $accounts->mapWithKeys(fn (BankAccount $a) => [$a->id => $this->idleDays($a)]),
            'idleAlertThreshold' => self::IDLE_ALERT_DAYS,
            // The per-account running-balance statement lives in the
            // separately-gated Project Cash Flow module — link to it only
            // when this company actually has access, rather than send a
            // company without that module into a 403.
            'hasProjectCashFlow' => $featureAccess->enabled(Auth::user()->company, 'project_cash_flow'),
        ]);
    }

    public function report()
    {
        $accounts = BankAccount::where('is_personal', true)
            ->orderBy('personal_owner_name')
            ->orderBy('name')
            ->get();

        $rows = $accounts->map(fn (BankAccount $a) => $this->summarizeCustodian($a));

        return view('user.cash-custody.report', [
            'rows' => $rows,
            'totals' => [
                'advanced' => $rows->sum('advanced'),
                'spent' => $rows->sum('spent'),
                'returned' => $rows->sum('returned'),
                'balance' => $rows->sum('balance'),
            ],
        ]);
    }

    public function pdf(MpdfRenderer $renderer)
    {
        $accounts = BankAccount::where('is_personal', true)
            ->orderBy('personal_owner_name')
            ->orderBy('name')
            ->get();

        $rows = $accounts->map(fn (BankAccount $a) => $this->summarizeCustodian($a));

        $pdf = $renderer->render('user.cash-custody.pdf', [
            'company' => Auth::user()->company,
            'rows' => $rows,
            'totals' => [
                'advanced' => $rows->sum('advanced'),
                'spent' => $rows->sum('spent'),
                'returned' => $rows->sum('returned'),
                'balance' => $rows->sum('balance'),
            ],
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="custody-report.pdf"',
        ]);
    }

    /**
     * All-time advanced/spent/returned/balance for one custodian.
     * "Advanced" is cash moved in from a non-custody account (the
     * company handing them a float); "returned" is the reverse — cash
     * moved back out to a non-custody account (unused float handed
     * back). A transfer between two custody accounts is rare enough
     * (and ambiguous as "advance" vs "return") that it's left out of
     * both buckets here — currentBalance() still reflects it correctly,
     * only this breakdown doesn't categorize it.
     */
    private function summarizeCustodian(BankAccount $account): array
    {
        $advanced = (float) BankTransfer::where('to_bank_account_id', $account->id)
            ->whereHas('fromAccount', fn ($q) => $q->where('is_personal', false))
            ->sum('amount');

        $returned = (float) BankTransfer::where('from_bank_account_id', $account->id)
            ->whereHas('toAccount', fn ($q) => $q->where('is_personal', false))
            ->sum('amount');

        $spent = (float) Expense::where('bank_account_id', $account->id)->sum('amount');
        $spentByCategory = Expense::where('bank_account_id', $account->id)
            ->with('category')
            ->get()
            ->groupBy(fn (Expense $e) => $e->category?->name ?? __('Uncategorized'))
            ->map(fn ($group, $category) => ['category' => $category, 'total' => (float) $group->sum('amount')])
            ->sortByDesc('total')
            ->values();

        return [
            'account' => $account,
            'advanced' => $advanced,
            'spent' => $spent,
            'returned' => $returned,
            'balance' => $account->currentBalance(),
            'spent_by_category' => $spentByCategory,
            'idle_days' => $this->idleDays($account),
        ];
    }

    /**
     * Days since the last expense or transfer touched this account, or
     * null if it has never had any activity at all (nothing to flag —
     * a brand new, still-empty custody account isn't "idle").
     */
    private function idleDays(BankAccount $account): ?int
    {
        $lastExpense = Expense::where('bank_account_id', $account->id)->max('expense_date');
        $lastTransfer = BankTransfer::where('from_bank_account_id', $account->id)
            ->orWhere('to_bank_account_id', $account->id)
            ->max('date');

        $last = collect([$lastExpense, $lastTransfer])->filter()->map(fn ($d) => Carbon::parse($d))->sort()->last();

        return $last ? $last->diffInDays(now()) : null;
    }
}
