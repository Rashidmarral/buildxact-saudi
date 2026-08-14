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

    public function deactivate(Account $account): RedirectResponse
    {
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
            'account_id' => ['required', Rule::exists('accounts', 'id')->where('company_id', Auth::user()->company_id)],
        ]);

        Auth::user()->company->accountMappings()->updateOrCreate(
            ['key' => $data['key']],
            ['account_id' => $data['account_id']]
        );

        return back()->with('status', __('Mapping saved.'));
    }
}
