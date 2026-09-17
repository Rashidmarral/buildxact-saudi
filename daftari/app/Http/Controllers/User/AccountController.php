<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountMapping;
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
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('accounts')->where('company_id', $companyId)],
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(self::TYPES)],
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
            'normal_balance' => ['required', 'in:debit,credit'],
        ]);

        if ($account->is_system) {
            // System accounts keep their code/type/normal balance stable —
            // mappings and the posting engine rely on these being fixed —
            // only the display name is editable.
            $account->update(['name' => $data['name'], 'name_ar' => $data['name_ar']]);

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
