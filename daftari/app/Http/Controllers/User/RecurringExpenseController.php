<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Project;
use App\Models\RecurringExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class RecurringExpenseController extends Controller
{
    public function index()
    {
        $recurringExpenses = RecurringExpense::with('category')
            ->orderByDesc('id')
            ->paginate(20);

        return view('user.recurring-expenses.index', compact('recurringExpenses'));
    }

    public function create()
    {
        return view('user.recurring-expenses.form', [
            'recurringExpense' => new RecurringExpense(['start_date' => now()->toDateString(), 'tax_category' => 'standard_15']),
            'categories' => ExpenseCategory::orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
            'bankAccounts' => BankAccount::where('is_active', true)->orderBy('name')->get(),
            'glAccounts' => Account::where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = Auth::id();
        $data['next_run_date'] = $data['start_date'];

        $recurringExpense = RecurringExpense::create($data);

        AuditLog::record('recurring_expense.create', $recurringExpense, __('Created recurring expense ":title"', ['title' => $recurringExpense->title]));

        return redirect()->route('app.recurring-expenses.index')->with('status', __('Recurring expense created.'));
    }

    public function edit(RecurringExpense $recurringExpense)
    {
        return view('user.recurring-expenses.form', [
            'recurringExpense' => $recurringExpense,
            'categories' => ExpenseCategory::orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
            'bankAccounts' => BankAccount::where('is_active', true)->orderBy('name')->get(),
            'glAccounts' => Account::where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function update(Request $request, RecurringExpense $recurringExpense)
    {
        $data = $this->validated($request);
        unset($data['start_date']);

        $recurringExpense->update($data);

        AuditLog::record('recurring_expense.update', $recurringExpense, __('Updated recurring expense ":title"', ['title' => $recurringExpense->title]));

        return redirect()->route('app.recurring-expenses.index')->with('status', __('Recurring expense updated.'));
    }

    public function pause(RecurringExpense $recurringExpense)
    {
        $recurringExpense->update(['status' => 'paused']);

        AuditLog::record('recurring_expense.pause', $recurringExpense, __('Paused recurring expense ":title"', ['title' => $recurringExpense->title]));

        return back()->with('status', __('Recurring expense paused.'));
    }

    public function resume(RecurringExpense $recurringExpense)
    {
        $recurringExpense->update([
            'status' => 'active',
            'next_run_date' => $recurringExpense->next_run_date->isPast() ? now()->toDateString() : $recurringExpense->next_run_date,
        ]);

        AuditLog::record('recurring_expense.resume', $recurringExpense, __('Resumed recurring expense ":title"', ['title' => $recurringExpense->title]));

        return back()->with('status', __('Recurring expense resumed.'));
    }

    public function destroy(RecurringExpense $recurringExpense)
    {
        $title = $recurringExpense->title;
        $recurringExpense->delete();

        AuditLog::record('recurring_expense.delete', null, __('Deleted recurring expense ":title"', ['title' => $title]));

        return redirect()->route('app.recurring-expenses.index')->with('status', __('Recurring expense deleted.'));
    }

    private function validated(Request $request): array
    {
        $companyId = Auth::user()->company_id;

        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'expense_category_id' => ['nullable', Rule::exists('expense_categories', 'id')->where('company_id', $companyId)],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where('company_id', $companyId)],
            'bank_account_id' => ['nullable', Rule::exists('bank_accounts', 'id')->where('company_id', $companyId)],
            'account_id' => ['nullable', Rule::exists('accounts', 'id')->where('company_id', $companyId)],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'gross_amount' => ['required', 'numeric', 'min:0.01'],
            'tax_category' => ['required', Rule::in(Expense::TAX_CATEGORIES)],
            'reference' => ['nullable', 'string', 'max:255'],
            'frequency' => ['required', Rule::in(RecurringExpense::FREQUENCIES)],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
