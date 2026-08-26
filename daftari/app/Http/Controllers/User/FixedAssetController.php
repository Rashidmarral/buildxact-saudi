<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\FixedAsset;
use App\Models\JournalEntry;
use App\Services\Accounting\AssetDepreciationService;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class FixedAssetController extends Controller
{
    public function index()
    {
        $assets = FixedAsset::orderBy('status')->orderByDesc('acquisition_date')->paginate(20);

        return view('user.fixed-assets.index', compact('assets'));
    }

    public function create()
    {
        $company = Auth::user()->company;

        // Guarantees the three depreciation-related system accounts (and
        // their mappings) exist even for a company created before this
        // feature shipped — new companies already get them for free via
        // Account::seedSystemAccounts() at signup.
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return view('user.fixed-assets.form', [
            'asset' => new FixedAsset(['acquisition_date' => now()->toDateString(), 'useful_life_years' => 5]),
            'glAccounts' => Account::where('is_active', true)->orderBy('code')->get(),
            'bankAccounts' => BankAccount::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, LedgerPostingService $ledger)
    {
        $data = $this->validated($request);
        $company = Auth::user()->company;

        $data['company_id'] = $company->id;
        $data['created_by'] = Auth::id();
        $data['account_id'] = $data['account_id'] ?? AccountMapping::resolve($company->id, 'FIXED_ASSETS_DEFAULT')?->id;

        $asset = FixedAsset::create($data);

        $fixedAssetsAccount = Account::find($asset->account_id) ?? AccountMapping::resolve($company->id, 'FIXED_ASSETS_DEFAULT');
        $creditAccount = $asset->bank_account_id
            ? AccountMapping::resolve($company->id, $asset->bankAccount->type === 'cash' ? 'DEFAULT_CASH' : 'DEFAULT_BANK')
            : AccountMapping::resolve($company->id, 'ACCOUNTS_PAYABLE');

        if ($fixedAssetsAccount && $creditAccount) {
            $ledger->post(
                $company,
                'fixed_asset',
                $asset->id,
                __('Acquired :name (:code)', ['name' => $asset->name, 'code' => $asset->asset_code]),
                $asset->acquisition_date,
                [
                    ['account_id' => $fixedAssetsAccount->id, 'debit' => (float) $asset->acquisition_cost],
                    ['account_id' => $creditAccount->id, 'credit' => (float) $asset->acquisition_cost],
                ]
            );
        }

        AuditLog::record('fixed_asset.create', $asset, __('Registered fixed asset :code', ['code' => $asset->asset_code]));

        return redirect()->route('app.fixed-assets.show', $asset)->with('status', __('Fixed asset registered.'));
    }

    public function show(FixedAsset $fixedAsset)
    {
        $depreciationEntries = JournalEntry::where('company_id', $fixedAsset->company_id)
            ->where('source_type', 'fixed_asset_depreciation')
            ->whereBetween('source_id', [$fixedAsset->id * 1_000_000, ($fixedAsset->id + 1) * 1_000_000 - 1])
            ->orderByDesc('entry_date')
            ->get();

        return view('user.fixed-assets.show', ['asset' => $fixedAsset, 'depreciationEntries' => $depreciationEntries]);
    }

    public function runDepreciation(AssetDepreciationService $service)
    {
        $count = $service->postForCompany(Auth::user()->company);

        return back()->with('status', $count > 0
            ? __(':count depreciation entries posted.', ['count' => $count])
            : __('No depreciation was due — every active asset is already up to date for this month.'));
    }

    public function dispose(Request $request, FixedAsset $fixedAsset, LedgerPostingService $ledger)
    {
        abort_unless($fixedAsset->status === 'active', 404);

        $data = $request->validate([
            'disposed_at' => ['required', 'date'],
            'disposal_proceeds' => ['nullable', 'numeric', 'min:0'],
        ]);

        $proceeds = (float) ($data['disposal_proceeds'] ?? 0);
        $netBookValue = $fixedAsset->netBookValue();
        $gainLoss = round($proceeds - $netBookValue, 2);

        $company = $fixedAsset->company;
        $fixedAssetsAccount = $fixedAsset->account ?? AccountMapping::resolve($company->id, 'FIXED_ASSETS_DEFAULT');
        $accumulatedAccount = AccountMapping::resolve($company->id, 'ACCUMULATED_DEPRECIATION_DEFAULT');
        $bankAccount = $fixedAsset->bankAccount
            ? AccountMapping::resolve($company->id, $fixedAsset->bankAccount->type === 'cash' ? 'DEFAULT_CASH' : 'DEFAULT_BANK')
            : AccountMapping::resolve($company->id, 'DEFAULT_BANK');

        $lines = [];
        if ($fixedAssetsAccount) {
            $lines[] = ['account_id' => $fixedAssetsAccount->id, 'credit' => (float) $fixedAsset->acquisition_cost];
        }
        if ($accumulatedAccount && (float) $fixedAsset->accumulated_depreciation > 0) {
            $lines[] = ['account_id' => $accumulatedAccount->id, 'debit' => (float) $fixedAsset->accumulated_depreciation];
        }
        if ($proceeds > 0 && $bankAccount) {
            $lines[] = ['account_id' => $bankAccount->id, 'debit' => $proceeds];
        }
        if (abs($gainLoss) > 0.005) {
            $plugAccount = $gainLoss > 0
                ? AccountMapping::resolve($company->id, 'OTHER_INCOME_DEFAULT')
                : AccountMapping::resolve($company->id, 'DEFAULT_OPERATING_EXPENSES');

            if ($plugAccount) {
                $lines[] = $gainLoss > 0
                    ? ['account_id' => $plugAccount->id, 'credit' => $gainLoss]
                    : ['account_id' => $plugAccount->id, 'debit' => abs($gainLoss)];
            }
        }

        $ledger->post(
            $company,
            'fixed_asset_disposal',
            $fixedAsset->id,
            __('Disposed :name (:code)', ['name' => $fixedAsset->name, 'code' => $fixedAsset->asset_code]),
            new \DateTime($data['disposed_at']),
            $lines
        );

        $fixedAsset->update([
            'status' => 'disposed',
            'disposed_at' => $data['disposed_at'],
            'disposal_proceeds' => $proceeds,
        ]);

        AuditLog::record('fixed_asset.dispose', $fixedAsset, __('Disposed fixed asset :code', ['code' => $fixedAsset->asset_code]));

        return redirect()->route('app.fixed-assets.show', $fixedAsset)->with('status', __('Asset marked as disposed.'));
    }

    private function validated(Request $request): array
    {
        $companyId = Auth::user()->company_id;

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'account_id' => ['nullable', Rule::exists('accounts', 'id')->where('company_id', $companyId)],
            'bank_account_id' => ['nullable', Rule::exists('bank_accounts', 'id')->where('company_id', $companyId)],
            'acquisition_date' => ['required', 'date'],
            'acquisition_cost' => ['required', 'numeric', 'min:0.01'],
            'salvage_value' => ['nullable', 'numeric', 'min:0'],
            'useful_life_years' => ['required', 'integer', 'min:1', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
