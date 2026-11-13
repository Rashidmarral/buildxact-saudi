<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\AccountMappingBranchOverride;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    private const TYPES = ['asset', 'liability', 'equity', 'revenue', 'expense'];

    public function index(Request $request)
    {
        $company = Auth::user()->company;
        $view = $request->query('view', 'list') === 'tree' ? 'tree' : 'list';

        $accounts = $company->accounts()
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q2) => $q2
                ->where('name', 'like', '%'.$request->query('search').'%')
                ->orWhere('name_ar', 'like', '%'.$request->query('search').'%')
                ->orWhere('code', 'like', '%'.$request->query('search').'%')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
            ->when(! $request->boolean('show_inactive'), fn ($q) => $q->where('is_active', true))
            ->orderBy('code')
            ->get();

        $mappings = $company->accountMappings()->with('account')->get()->keyBy('key');
        $activeAccounts = $company->accounts()->where('is_active', true)->orderBy('code')->get();

        return view('user.accounts.index', [
            'accounts' => $accounts,
            'types' => self::TYPES,
            'mappings' => $mappings,
            'mappingCatalog' => AccountMapping::catalog(),
            'activeAccounts' => $activeAccounts,
            'branches' => $company->branches()->orderBy('name')->get(),
            'view' => $view,
            'allAccounts' => $company->accounts()->orderBy('code')->get(),
            // Lets a custom account be filed under an existing category
            // (so it shows up correctly on Trial Balance/Balance Sheet
            // instead of always landing in the generic "Other :type"
            // catch-all) without forcing a fixed, closed list — a
            // company's own chart can need a category Daftari never
            // seeded.
            'existingCategories' => $company->accounts()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
        ]);
    }

    /**
     * One branch's Sales/Purchases account overrides (see
     * AccountMapping::branchOverridableKeys()) — any key left unset here
     * falls back to the company-wide default shown on the main Accounts
     * page, exactly like Hamly's own per-location mapping screen.
     */
    public function branchMappings(Branch $branch)
    {
        $company = Auth::user()->company;
        abort_unless($branch->company_id === $company->id, 404);

        $overrides = $branch->accountMappingOverrides()->with('account')->get()->keyBy('key');
        $activeAccounts = $company->accounts()->where('is_active', true)->orderBy('code')->get();
        $catalog = AccountMapping::catalog();
        $overridableKeys = collect(AccountMapping::branchOverridableKeys())->mapWithKeys(fn ($key) => [$key => $catalog[$key]]);

        return view('user.accounts.branch-mappings', [
            'branch' => $branch,
            'overrides' => $overrides,
            'overridableKeys' => $overridableKeys,
            'activeAccounts' => $activeAccounts,
            'companyMappings' => $company->accountMappings()->with('account')->get()->keyBy('key'),
        ]);
    }

    public function updateBranchMapping(Request $request, Branch $branch): RedirectResponse
    {
        $company = Auth::user()->company;
        abort_unless($branch->company_id === $company->id, 404);

        $data = $request->validate([
            'key' => ['required', Rule::in(AccountMapping::branchOverridableKeys())],
            // Empty clears the override — "use the company default" is
            // itself a valid, common choice, not an error.
            'account_id' => ['nullable', Rule::exists('accounts', 'id')->where('company_id', $company->id)->where('is_active', true)],
        ]);

        if (empty($data['account_id'])) {
            $branch->accountMappingOverrides()->where('key', $data['key'])->delete();

            return back()->with('status', __('Now using the company default for this key.'));
        }

        $allowedType = AccountMapping::catalog()[$data['key']]['allowed_type'] ?? null;
        $account = Account::where('company_id', $company->id)->find($data['account_id']);

        if ($allowedType && $account && $account->type !== $allowedType) {
            return back()->withErrors(['account_id' => __('This role requires a(n) :type account — the selected account is a(n) :actual account.', ['type' => $allowedType, 'actual' => $account->type])]);
        }

        AccountMappingBranchOverride::updateOrCreate(
            ['company_id' => $company->id, 'branch_id' => $branch->id, 'key' => $data['key']],
            ['account_id' => $data['account_id']]
        );

        return back()->with('status', __('Branch override saved.'));
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('accounts')->where('company_id', $companyId)],
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(self::TYPES)],
            'category' => ['nullable', 'string', 'max:255'],
            'parent_account_id' => ['nullable', Rule::exists('accounts', 'id')->where('company_id', $companyId)],
            'normal_balance' => ['required', 'in:debit,credit'],
        ]);

        Auth::user()->company->accounts()->create($data);

        return back()->with('status', __('Account added.'));
    }

    public function update(Request $request, Account $account): RedirectResponse
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('accounts')->where('company_id', $companyId)->ignore($account->id)],
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(self::TYPES)],
            'category' => ['nullable', 'string', 'max:255'],
            'parent_account_id' => [
                'nullable',
                Rule::exists('accounts', 'id')->where('company_id', $companyId),
                Rule::notIn([$account->id]),
            ],
            'normal_balance' => ['required', 'in:debit,credit'],
        ]);

        if ($account->is_system) {
            // System accounts keep their code/type/normal balance stable —
            // mappings and the posting engine rely on these being fixed —
            // only the display name and parent (for tree-view grouping)
            // are editable.
            $account->update(['name' => $data['name'], 'name_ar' => $data['name_ar'], 'parent_account_id' => $data['parent_account_id']]);

            return back()->with('status', __('Account updated.'));
        }

        $account->update($data);

        return back()->with('status', __('Account updated.'));
    }

    /**
     * Commercial audit finding A1: this used to unconditionally flip
     * is_active, contradicting the page's own copy ("System accounts and
     * required mappings cannot be deactivated once used") — nothing
     * actually enforced that. Deactivating a mapped or in-use account
     * doesn't stop new postings (AccountMapping::resolve() doesn't check
     * is_active), it just makes the account vanish from every report that
     * filters on is_active (Trial Balance, Balance Sheet, Income
     * Statement, Zakat) while the posting keeps accumulating there —
     * silently unbalancing what those reports show. Mirrors destroy()'s
     * existing guards.
     */
    public function deactivate(Account $account): RedirectResponse
    {
        if ($account->is_system) {
            return back()->withErrors(['account' => __('System accounts cannot be deactivated.')]);
        }

        if ($account->journalEntryLines()->exists()) {
            return back()->withErrors(['account' => __('This account has journal entries posted to it and cannot be deactivated.')]);
        }

        if (Auth::user()->company->accountMappings()->where('account_id', $account->id)->exists()) {
            return back()->withErrors(['account' => __('This account is used in an account mapping. Repoint the mapping before deactivating it.')]);
        }

        $account->update(['is_active' => false]);

        return back()->with('status', __('Account deactivated.'));
    }

    public function activate(Account $account): RedirectResponse
    {
        $account->update(['is_active' => true]);

        return back()->with('status', __('Account activated.'));
    }

    public function destroy(Account $account): RedirectResponse
    {
        if ($account->is_system) {
            return back()->withErrors(['account' => __('System accounts cannot be deleted — deactivate it instead.')]);
        }

        if ($account->journalEntryLines()->exists()) {
            return back()->withErrors(['account' => __('This account has journal entries posted to it and cannot be deleted.')]);
        }

        if (Auth::user()->company->accountMappings()->where('account_id', $account->id)->exists()) {
            return back()->withErrors(['account' => __('This account is used in an account mapping. Repoint the mapping before deleting it.')]);
        }

        $account->delete();

        return back()->with('status', __('Account deleted.'));
    }

    public function updateMapping(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', Rule::in(array_keys(AccountMapping::catalog()))],
            // is_active is enforced here, not just by the <select> only
            // listing $activeAccounts in the view — a crafted POST could
            // otherwise point a mapping at an inactive account.
            'account_id' => ['required', Rule::exists('accounts', 'id')->where('company_id', Auth::user()->company_id)->where('is_active', true)],
        ]);

        // Commercial audit finding A4: nothing previously stopped mapping
        // a semantic role to an account of the wrong type — e.g. VAT
        // Output (which the posting engine always credits as a liability)
        // pointed at an expense account — silently corrupting Trial
        // Balance, Balance Sheet, and every report built on account type.
        $allowedType = AccountMapping::catalog()[$data['key']]['allowed_type'] ?? null;
        $account = Account::where('company_id', Auth::user()->company_id)->find($data['account_id']);

        if ($allowedType && $account && $account->type !== $allowedType) {
            return back()->withErrors(['account_id' => __('This role requires a(n) :type account — the selected account is a(n) :actual account.', ['type' => $allowedType, 'actual' => $account->type])]);
        }

        Auth::user()->company->accountMappings()->updateOrCreate(
            ['key' => $data['key']],
            ['account_id' => $data['account_id']]
        );

        return back()->with('status', __('Mapping saved.'));
    }
}
