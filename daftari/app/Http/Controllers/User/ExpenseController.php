<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Project;
use App\Models\User;
use App\Notifications\GenericNotification;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        $company = Auth::user()->company;
        $requiresApproval = $company->expenseRequiresApproval((float) $data['gross_amount']);
        $data['status'] = $requiresApproval ? 'pending_approval' : 'approved';

        $expense = DB::transaction(function () use ($data, $requiresApproval, $ledger) {
            $expense = Expense::create($data);

            if (! $requiresApproval) {
                $ledger->postExpense($expense);
            }

            return $expense;
        });

        if ($requiresApproval) {
            $this->notifyApprovers($expense);

            return redirect()->route('app.expenses.index')->with('status', __('Expense submitted for approval.'));
        }

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

        $wasPosted = $expense->status === 'approved';
        $old = ['gross_amount' => $expense->gross_amount, 'tax_category' => $expense->tax_category, 'expense_date' => $expense->expense_date?->toDateString()];

        DB::transaction(function () use ($expense, $data, $ledger, $wasPosted) {
            $expense->update($data);

            // Still awaiting approval — nothing was ever posted, so there's
            // nothing to repost either; the approve action does that once.
            // The ledger keys a posting by (source_type, source_id), so an
            // in-place correction has to clear the old entry before the
            // corrected one can be posted under the same key — unlike a
            // void/cancel, there is no way to keep the original entry
            // alongside a reversal here. AuditLog is the durable record of
            // what changed.
            if ($wasPosted) {
                $ledger->deletePosting($expense->company, 'expense', $expense->id);
                $ledger->postExpense($expense);
            }
        });

        AuditLog::record(
            'expense.update',
            $expense,
            __('Updated expense of :amount :currency:reposted', [
                'amount' => number_format($expense->gross_amount, 2),
                'currency' => $expense->company->currency,
                'reposted' => $wasPosted ? __(' (ledger entry reversed and reposted)') : '',
            ]),
            old: $old,
            new: ['gross_amount' => $expense->gross_amount, 'tax_category' => $expense->tax_category, 'expense_date' => $expense->expense_date?->toDateString()],
        );

        return redirect()->route('app.expenses.index')->with('status', __('Expense updated.'));
    }

    public function destroy(Expense $expense, LedgerPostingService $ledger)
    {
        $wasPosted = $expense->status === 'approved';
        $amount = $expense->gross_amount;
        $currency = $expense->company->currency;

        DB::transaction(function () use ($expense, $ledger, $wasPosted) {
            if ($wasPosted) {
                $ledger->reverse($expense->company, 'expense', $expense->id, __('Expense deleted'));
            }

            $expense->delete();
        });

        AuditLog::record(
            'expense.delete',
            null,
            __('Deleted expense #:id of :amount :currency:reversed', [
                'id' => $expense->id,
                'amount' => number_format($amount, 2),
                'currency' => $currency,
                'reversed' => $wasPosted ? __(' (ledger entry reversed)') : '',
            ]),
        );

        return redirect()->route('app.expenses.index')->with('status', __('Expense deleted.'));
    }

    public function approve(Expense $expense, LedgerPostingService $ledger)
    {
        abort_unless(Auth::user()->hasPermission('approvals'), 403);
        abort_unless($expense->status === 'pending_approval', 404);

        DB::transaction(function () use ($expense, $ledger) {
            $expense->update(['status' => 'approved', 'approved_by' => Auth::id(), 'approved_at' => now()]);
            $ledger->postExpense($expense);
        });

        AuditLog::record('expense.approve', $expense, __('Approved expense of :amount :currency', ['amount' => number_format($expense->gross_amount, 2), 'currency' => $expense->company->currency]));

        return back()->with('status', __('Expense approved and posted.'));
    }

    public function reject(Request $request, Expense $expense)
    {
        abort_unless(Auth::user()->hasPermission('approvals'), 403);
        abort_unless($expense->status === 'pending_approval', 404);

        $data = $request->validate(['rejection_reason' => ['nullable', 'string', 'max:1000']]);

        $expense->update(['status' => 'rejected', 'approved_by' => Auth::id(), 'approved_at' => now(), 'rejection_reason' => $data['rejection_reason'] ?? null]);

        AuditLog::record('expense.reject', $expense, __('Rejected expense of :amount :currency', ['amount' => number_format($expense->gross_amount, 2), 'currency' => $expense->company->currency]));

        if ($expense->creator) {
            $expense->creator->notify(new GenericNotification(
                title: __('Expense rejected'),
                body: __(':amount :currency was rejected.', ['amount' => number_format($expense->gross_amount, 2), 'currency' => $expense->company->currency]),
                url: route('app.expenses.index'),
                icon: 'purchases',
            ));
        }

        return back()->with('status', __('Expense rejected.'));
    }

    private function notifyApprovers(Expense $expense): void
    {
        User::where('company_id', $expense->company_id)
            ->get()
            ->filter(fn (User $user) => $user->hasPermission('approvals'))
            ->each(fn (User $user) => $user->notify(new GenericNotification(
                title: __('Expense awaiting approval'),
                body: __(':amount :currency from :vendor', ['amount' => number_format($expense->gross_amount, 2), 'currency' => $expense->company->currency, 'vendor' => $expense->vendor_name ?: __('Unknown vendor')]),
                url: route('app.expenses.index'),
                icon: 'purchases',
            )));
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
