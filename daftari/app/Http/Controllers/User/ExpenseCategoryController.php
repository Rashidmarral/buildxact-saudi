<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ExportsCsv;
use App\Http\Controllers\User\Concerns\ResolvesPerPage;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ExpenseCategoryController extends Controller
{
    use ExportsCsv, ResolvesPerPage;

    public function index(Request $request)
    {
        $query = ExpenseCategory::with('parent')->orderBy('name');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('name_ar', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->query('export') === 'csv') {
            return $this->csvResponse(
                'expense-categories.csv',
                [__('Category name'), __('Category code'), __('Group'), __('Direct cost'), __('VAT eligible'), __('Active')],
                $query->get()->map(fn (ExpenseCategory $category) => [
                    $category->name,
                    $category->code,
                    $category->parent?->name,
                    $category->is_direct_cost ? __('Yes') : __('No'),
                    $category->vat_eligible ? __('Yes') : __('No'),
                    $category->is_active ? __('Yes') : __('No'),
                ])
            );
        }

        $categories = $query->paginate($this->resolvePerPage($request))->withQueryString();

        return view('user.expense-categories.index', compact('categories'));
    }

    public function create()
    {
        return view('user.expense-categories.form', [
            'category' => new ExpenseCategory,
            'parentOptions' => $this->parentOptions(),
            'accountOptions' => $this->accountOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $category = ExpenseCategory::create($data);

        AuditLog::record('expense_category.create', $category, __('Created expense category :name', ['name' => $category->name]));

        return redirect()->route('app.expense-categories.index')->with('status', __('Category added.'));
    }

    public function edit(ExpenseCategory $expenseCategory)
    {
        return view('user.expense-categories.form', [
            'category' => $expenseCategory,
            'parentOptions' => $this->parentOptions($expenseCategory),
            'accountOptions' => $this->accountOptions(),
        ]);
    }

    public function update(Request $request, ExpenseCategory $expenseCategory)
    {
        $data = $this->validated($request, $expenseCategory);

        $expenseCategory->update($data);

        AuditLog::record('expense_category.update', $expenseCategory, __('Updated expense category :name', ['name' => $expenseCategory->name]));

        return redirect()->route('app.expense-categories.index')->with('status', __('Category updated.'));
    }

    public function destroy(ExpenseCategory $expenseCategory)
    {
        if ($expenseCategory->expenses()->exists()) {
            return back()->withErrors(['expense_category' => __('This category has expenses recorded against it and cannot be deleted. Deactivate it instead.')]);
        }

        if ($expenseCategory->children()->exists()) {
            return back()->withErrors(['expense_category' => __('This category has other categories grouped under it and cannot be deleted.')]);
        }

        AuditLog::record('expense_category.delete', $expenseCategory, __('Deleted expense category :name', ['name' => $expenseCategory->name]));

        $expenseCategory->delete();

        return redirect()->route('app.expense-categories.index')->with('status', __('Category deleted.'));
    }

    private function validated(Request $request, ?ExpenseCategory $category = null): array
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('expense_categories', 'code')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($category?->id),
            ],
            'parent_category_id' => [
                'nullable',
                Rule::exists('expense_categories', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
                $category?->exists ? Rule::notIn([$category->id]) : 'nullable',
            ],
            'linked_account_id' => [
                'nullable',
                Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
        ]);

        $data['is_direct_cost'] = $request->boolean('is_direct_cost');
        $data['requires_project'] = $request->boolean('requires_project');
        $data['vat_eligible'] = $request->boolean('vat_eligible', true);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }

    private function parentOptions(?ExpenseCategory $excluding = null)
    {
        return ExpenseCategory::where('company_id', Auth::user()->company_id)
            ->when($excluding?->exists, fn ($q) => $q->where('id', '!=', $excluding->id))
            ->orderBy('name')
            ->get();
    }

    private function accountOptions()
    {
        return Account::where('company_id', Auth::user()->company_id)
            ->where('type', 'expense')
            ->orderBy('code')
            ->get();
    }
}
