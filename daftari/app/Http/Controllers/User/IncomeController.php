<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ResolvesPerPage;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\Project;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * A general-purpose way to record money received that isn't tied to an
 * invoice — mirrors ExpenseController deliberately (see Income's
 * docblock), minus an approval workflow: unlike a purchase, there's no
 * one money could be routed to for sign-off before it's "real", so every
 * Income posts to the ledger immediately, the same way a Receipt Voucher
 * already does.
 */
class IncomeController extends Controller
{
    use ResolvesPerPage;

    public function index(Request $request)
    {
        $incomes = Income::with('category', 'bankAccount', 'account', 'project')->orderByDesc('income_date')->paginate($this->resolvePerPage($request))->withQueryString();
        $categories = IncomeCategory::orderBy('name')->get();

        return view('user.incomes.index', compact('incomes', 'categories'));
    }

    public function create(Request $request)
    {
        return view('user.incomes.form', [
            'income' => new Income(['project_id' => $request->integer('project_id') ?: null]),
            'categories' => IncomeCategory::orderBy('name')->get(),
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
        $data['status'] = 'received';

        $income = DB::transaction(function () use ($data, $ledger) {
            $income = Income::create($data);
            $ledger->postIncome($income);

            return $income;
        });

        AuditLog::record('income.create', $income, __('Recorded income of :amount :currency', [
            'amount' => number_format($income->gross_amount, 2), 'currency' => $income->company->currency,
        ]));

        return redirect()->route('app.incomes.index')->with('status', __('Income recorded.'));
    }

    public function edit(Income $income)
    {
        return view('user.incomes.form', [
            'income' => $income,
            'categories' => IncomeCategory::orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
            'bankAccounts' => BankAccount::where('is_active', true)->orderBy('name')->get(),
            'glAccounts' => Account::where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function update(Request $request, Income $income, LedgerPostingService $ledger)
    {
        $data = $this->validated($request);
        $data = $this->withComputedAmounts($data);
        $old = ['gross_amount' => $income->gross_amount, 'tax_category' => $income->tax_category, 'income_date' => $income->income_date?->toDateString()];

        DB::transaction(function () use ($income, $data, $ledger) {
            $income->update($data);
            // The ledger keys a posting by (source_type, source_id), so an
            // in-place correction has to clear the old entry before the
            // corrected one can be posted under the same key — mirrors
            // ExpenseController::update()'s own reasoning.
            $ledger->deletePosting($income->company, 'income', $income->id);
            $ledger->postIncome($income);
        });

        AuditLog::record(
            'income.update',
            $income,
            __('Updated income of :amount :currency (ledger entry reversed and reposted)', [
                'amount' => number_format($income->gross_amount, 2), 'currency' => $income->company->currency,
            ]),
            old: $old,
            new: ['gross_amount' => $income->gross_amount, 'tax_category' => $income->tax_category, 'income_date' => $income->income_date?->toDateString()],
        );

        return redirect()->route('app.incomes.index')->with('status', __('Income updated.'));
    }

    public function destroy(Income $income, LedgerPostingService $ledger)
    {
        $amount = $income->gross_amount;
        $currency = $income->company->currency;

        DB::transaction(function () use ($income, $ledger) {
            $ledger->reverse($income->company, 'income', $income->id, __('Income deleted'));
            $income->delete();
        });

        AuditLog::record('income.delete', null, __('Deleted income #:id of :amount :currency (ledger entry reversed)', [
            'id' => $income->id, 'amount' => number_format($amount, 2), 'currency' => $currency,
        ]));

        return redirect()->route('app.incomes.index')->with('status', __('Income deleted.'));
    }

    private function withComputedAmounts(array $data): array
    {
        $rate = Income::taxRateFor($data['tax_category']);
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
            'income_category_id' => ['nullable', Rule::exists('income_categories', 'id')->where('company_id', $companyId)],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where('company_id', $companyId)],
            'bank_account_id' => ['nullable', Rule::exists('bank_accounts', 'id')->where('company_id', $companyId)],
            'account_id' => ['nullable', Rule::exists('accounts', 'id')->where('company_id', $companyId)->where('is_active', true)],
            'payer_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'gross_amount' => ['required', 'numeric', 'min:0.01'],
            'tax_category' => ['required', Rule::in(Income::TAX_CATEGORIES)],
            'reference' => ['nullable', 'string', 'max:255'],
            'income_date' => ['required', 'date'],
        ]);
    }
}
