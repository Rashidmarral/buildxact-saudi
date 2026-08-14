<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function index()
    {
        $expenses = Expense::with('category')->orderByDesc('expense_date')->paginate(20);
        $categories = ExpenseCategory::orderBy('name')->get();

        return view('user.expenses.index', compact('expenses', 'categories'));
    }

    public function create()
    {
        $categories = ExpenseCategory::orderBy('name')->get();

        return view('user.expenses.form', ['expense' => new Expense, 'categories' => $categories]);
    }

    public function store(Request $request, LedgerPostingService $ledger)
    {
        $data = $this->validated($request);
        $data['created_by'] = Auth::id();
        $expense = Expense::create($data);
        $ledger->postExpense($expense);

        return redirect()->route('app.expenses.index')->with('status', __('Expense recorded.'));
    }

    public function edit(Expense $expense)
    {
        $categories = ExpenseCategory::orderBy('name')->get();

        return view('user.expenses.form', compact('expense', 'categories'));
    }

    public function update(Request $request, Expense $expense, LedgerPostingService $ledger)
    {
        $data = $this->validated($request);
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

    private function validated(Request $request): array
    {
        return $request->validate([
            'expense_category_id' => ['nullable', Rule::exists('expense_categories', 'id')->where('company_id', Auth::user()->company_id)],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'min:0'],
            'vat_amount' => ['nullable', 'numeric', 'min:0'],
            'expense_date' => ['required', 'date'],
        ]);
    }
}
