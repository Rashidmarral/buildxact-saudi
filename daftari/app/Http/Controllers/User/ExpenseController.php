<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Project;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function index()
    {
        $expenses = Expense::with('category', 'bankAccount', 'account')->orderByDesc('expense_date')->paginate(20);
        $categories = ExpenseCategory::orderBy('name')->get();

        return view('user.expenses.index', compact('expenses', 'categories'));
    }

    public function create()
    {
        return view('user.expenses.form', [
            'expense' => new Expense,
            'categories' => ExpenseCategory::orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
            'bankAccounts' => BankAccount::where('is_active', true)->orderBy('name')->get(),
            'glAccounts' => Account::where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request, LedgerPostingService $ledger)
    {
        $data = $this->validated($request);
        $data = $this->withComputedAmounts($data);
        $data['created_by'] = Auth::id();
        $expense = Expense::create($data);
        $ledger->postExpense($expense);

        return redirect()->route('app.expenses.index')->with('status', __('Expense recorded.'));
    }

    public function edit(Expense $expense)
    {
        return view('user.expenses.form', [
            'expense' => $expense,
            'categories' => ExpenseCategory::orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
            'bankAccounts' => BankAccount::where('is_active', true)->orderBy('name')->get(),
            'glAccounts' => Account::where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function update(Request $request, Expense $expense, LedgerPostingService $ledger)
    {
        $data = $this->validated($request);
        $data = $this->withComputedAmounts($data);
        $expense->update($data);
        $ledger->deletePosting($expense->company, 'expense', $expense->id);
        $ledger->postExpense($expense);

        return redirect()->route('app.expenses.index')->with('status', __('Expense updated.'));
    }

    public function destroy(Expense $expense, LedgerPostingService $ledger)
    {
        $ledger->reverse($expense->company, 'expense', $expense->id, __('Expense deleted'));
        $expense->delete();

        return redirect()->route('app.expenses.index')->with('status', __('Expense deleted.'));
    }

    private function withComputedAmounts(array $data): array
    {
        $rate = Expense::taxRateFor($data['tax_category']);
        $gross = (float) $data['gross_amount'];
        $vat = round($gross * $rate / (100 + $rate), 2);

        $data['vat_amount'] = $vat;
        $data['amount'] = round($gross - $vat, 2);

        return $data;
    }

    private function validated(Request $request): array
    {
        $companyId = Auth::user()->company_id;

        return $request->validate([
            'expense_category_id' => ['nullable', Rule::exists('expense_categories', 'id')->where('company_id', $companyId)],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where('company_id', $companyId)],
            'bank_account_id' => ['nullable', Rule::exists('bank_accounts', 'id')->where('company_id', $companyId)],
            'account_id' => ['nullable', Rule::exists('accounts', 'id')->where('company_id', $companyId)],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'gross_amount' => ['required', 'numeric', 'min:0.01'],
            'tax_category' => ['required', Rule::in(Expense::TAX_CATEGORIES)],
            'reference' => ['nullable', 'string', 'max:255'],
            'expense_date' => ['required', 'date'],
        ]);
    }
}
