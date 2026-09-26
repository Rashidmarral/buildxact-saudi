<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Budget;
use App\Models\BudgetLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BudgetController extends Controller
{
    public function index()
    {
        return view('user.budgets.index', [
            'budgets' => Budget::withCount('lines')->orderByDesc('fiscal_year')->paginate(20),
        ]);
    }

    public function create()
    {
        return view('user.budgets.form', [
            'budget' => new Budget(['fiscal_year' => (int) now()->format('Y')]),
            'accounts' => $this->budgetableAccounts(),
            'lines' => collect(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $budget = DB::transaction(function () use ($data) {
            $budget = Budget::create([
                'created_by' => Auth::id(),
                'name' => $data['name'],
                'fiscal_year' => $data['fiscal_year'],
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncLines($budget, $data['lines'] ?? []);

            return $budget;
        });

        AuditLog::record('budget.create', $budget, __('Created budget ":name" for :year', ['name' => $budget->name, 'year' => $budget->fiscal_year]));

        return redirect()->route('app.budgets.show', $budget)->with('status', __('Budget created.'));
    }

    public function show(Budget $budget)
    {
        $fromDate = "{$budget->fiscal_year}-01-01";
        $toDate = "{$budget->fiscal_year}-12-31";

        $rows = $budget->lines()->with('account')->get()->map(function (BudgetLine $line) use ($fromDate, $toDate) {
            $account = $line->account;
            $debit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->whereBetween('entry_date', [$fromDate, $toDate]))->sum('debit');
            $credit = (float) $account->journalEntryLines()->whereHas('journalEntry', fn ($q) => $q->whereBetween('entry_date', [$fromDate, $toDate]))->sum('credit');
            $actual = $account->normal_balance === 'credit' ? $credit - $debit : $debit - $credit;

            $variance = $actual - (float) $line->amount;

            return [
                'account' => $account,
                'budgeted' => (float) $line->amount,
                'actual' => $actual,
                'variance' => $variance,
                // For revenue, coming in over budget is good news; for
                // expenses it's the opposite — so the color the report
                // shows a variance in depends on which side of the P&L
                // the account sits on, not just its sign.
                'unfavorable' => $account->type === 'revenue' ? $variance < 0 : $variance > 0,
            ];
        })->sortBy(fn ($row) => $row['account']->code)->values();

        return view('user.budgets.show', [
            'budget' => $budget,
            'rows' => $rows,
            'totalBudgeted' => $rows->sum('budgeted'),
            'totalActual' => $rows->sum('actual'),
        ]);
    }

    public function edit(Budget $budget)
    {
        return view('user.budgets.form', [
            'budget' => $budget,
            'accounts' => $this->budgetableAccounts(),
            'lines' => $budget->lines()->pluck('amount', 'account_id'),
        ]);
    }

    public function update(Request $request, Budget $budget)
    {
        $data = $this->validated($request, $budget);

        DB::transaction(function () use ($budget, $data) {
            $budget->update([
                'name' => $data['name'],
                'fiscal_year' => $data['fiscal_year'],
                'notes' => $data['notes'] ?? null,
            ]);

            $budget->lines()->delete();
            $this->syncLines($budget, $data['lines'] ?? []);
        });

        AuditLog::record('budget.update', $budget, __('Updated budget ":name"', ['name' => $budget->name]));

        return redirect()->route('app.budgets.show', $budget)->with('status', __('Budget updated.'));
    }

    public function activate(Budget $budget)
    {
        $budget->update(['status' => 'active']);

        AuditLog::record('budget.activate', $budget, __('Activated budget ":name"', ['name' => $budget->name]));

        return back()->with('status', __('Budget activated.'));
    }

    public function destroy(Budget $budget)
    {
        $name = $budget->name;
        $budget->delete();

        AuditLog::record('budget.delete', null, __('Deleted budget ":name"', ['name' => $name]));

        return redirect()->route('app.budgets.index')->with('status', __('Budget deleted.'));
    }

    private function budgetableAccounts()
    {
        return Account::where('company_id', Auth::user()->company_id)
            ->where('is_active', true)
            ->whereIn('type', ['revenue', 'expense'])
            ->orderBy('code')
            ->get();
    }

    private function syncLines(Budget $budget, array $lines): void
    {
        foreach ($lines as $accountId => $amount) {
            if ((float) $amount <= 0) {
                continue;
            }

            BudgetLine::create([
                'budget_id' => $budget->id,
                'account_id' => $accountId,
                'amount' => $amount,
            ]);
        }
    }

    private function validated(Request $request, ?Budget $budget = null): array
    {
        $companyId = Auth::user()->company_id;

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'fiscal_year' => [
                'required', 'integer', 'min:2000', 'max:2100',
                Rule::unique('budgets', 'fiscal_year')->where('company_id', $companyId)->ignore($budget?->id),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['nullable', 'array'],
            'lines.*' => ['nullable', 'numeric', 'min:0'],
        ]);
    }
}
