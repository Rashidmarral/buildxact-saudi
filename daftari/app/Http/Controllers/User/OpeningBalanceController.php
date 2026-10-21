<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\JournalEntry;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

/**
 * Audit finding GL-7: there was no traceable, safeguarded way to enter
 * opening balances for a company onboarding mid-year — only
 * BankAccount::opening_balance existed, for bank/cash accounts alone.
 * This covers General Ledger accounts; see docs/audit/INITIAL_AUDIT_REPORT.md
 * for why customer/supplier opening balances are deliberately NOT part of
 * this (a GL-only credit to Accounts Receivable/Payable would desync the
 * control account from the sum of individual customer/supplier balances,
 * which this system derives from real Invoice/Bill rows, not a ledger
 * balance field — fixing that properly needs its own, separate design).
 *
 * One entry per company, ever — a correction afterward goes through a
 * normal manual journal entry, same as fixing any other posted mistake.
 * Any imbalance between what the user enters is plugged into Owner's
 * Equity (code 3000, present on every company via Account::seedSystemAccounts())
 * rather than requiring the user to work out the double-entry themselves,
 * which is the entire reason this exists instead of just sending them to
 * the Manual Journal Entry form.
 */
class OpeningBalanceController extends Controller
{
    private const SOURCE_TYPE = 'opening_balance';

    private const SOURCE_ID = 1;

    public function create()
    {
        $company = Auth::user()->company;
        $existing = $this->existingEntry($company->id);

        return view('user.opening-balances.create', [
            'accounts' => Account::where('is_active', true)->orderBy('code')->get(),
            'existing' => $existing,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = Auth::user()->company;
        $companyId = $company->id;

        if ($this->existingEntry($companyId)) {
            return back()->withErrors(['lines' => __('Opening balances have already been recorded for this company. To correct them, post a manual journal entry instead.')]);
        }

        $data = $request->validate([
            'as_of_date' => ['required', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.account_id' => ['required', \Illuminate\Validation\Rule::exists('accounts', 'id')->where('company_id', $companyId)->where('is_active', true)],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $lines = collect($data['lines'])
            ->filter(fn (array $line) => (float) ($line['debit'] ?? 0) > 0 || (float) ($line['credit'] ?? 0) > 0)
            ->map(fn (array $line) => ['account_id' => (int) $line['account_id'], 'debit' => (float) ($line['debit'] ?? 0), 'credit' => (float) ($line['credit'] ?? 0)])
            ->values();

        if ($lines->isEmpty()) {
            return back()->withInput()->withErrors(['lines' => __('Enter at least one account balance.')]);
        }

        $totalDebit = round($lines->sum('debit'), 2);
        $totalCredit = round($lines->sum('credit'), 2);
        $difference = round($totalDebit - $totalCredit, 2);

        if (abs($difference) > 0.01) {
            $equity = Account::where('company_id', $companyId)->where('code', '3000')->first();

            if (! $equity) {
                return back()->withInput()->withErrors(['lines' => __('No Owner\'s Equity account (3000) was found to plug the difference into — contact support.')]);
            }

            // The user enters raw balances without necessarily knowing
            // the double-entry theory — whichever side is short, the
            // difference is plugged into equity so the entry always
            // balances, exactly what a real opening-balance migration
            // does in practice.
            $lines->push($difference > 0
                ? ['account_id' => $equity->id, 'debit' => 0, 'credit' => $difference]
                : ['account_id' => $equity->id, 'debit' => abs($difference), 'credit' => 0]);
        }

        try {
            $entry = DB::transaction(fn () => app(LedgerPostingService::class)->post(
                $company,
                self::SOURCE_TYPE,
                self::SOURCE_ID,
                __('Opening balances as of :date', ['date' => $data['as_of_date']]),
                \Illuminate\Support\Carbon::parse($data['as_of_date']),
                $lines->map(fn ($l) => $l + ['memo' => __('Opening balance')])->all()
            ));
        } catch (\InvalidArgumentException|\App\Exceptions\PeriodLockedException $e) {
            return back()->withInput()->withErrors(['lines' => $e->getMessage()]);
        }

        if (! $entry) {
            return back()->withErrors(['lines' => __('Opening balances have already been recorded for this company.')]);
        }

        AuditLog::record('opening_balance.create', $entry, __('Recorded opening balances as of :date', ['date' => $data['as_of_date']]));

        return redirect()->route('app.journals.show', $entry)->with('status', __('Opening balances recorded.'));
    }

    private function existingEntry(int $companyId): ?JournalEntry
    {
        return JournalEntry::where('company_id', $companyId)
            ->where('source_type', self::SOURCE_TYPE)
            ->where('source_id', self::SOURCE_ID)
            ->first();
    }
}
