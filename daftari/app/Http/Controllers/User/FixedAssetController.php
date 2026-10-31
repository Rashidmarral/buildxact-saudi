<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ResolvesPerPage;
use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\FixedAsset;
use App\Models\JournalEntry;
use App\Services\Accounting\AssetDepreciationService;
use App\Services\Accounting\FixedAssetLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class FixedAssetController extends Controller
{
    use ResolvesPerPage;

    public function index(Request $request)
    {
        $assets = FixedAsset::orderBy('status')->orderByDesc('acquisition_date')->paginate($this->resolvePerPage($request))->withQueryString();

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

    public function store(Request $request, FixedAssetLifecycleService $lifecycle)
    {
        $data = $this->validated($request);
        $company = Auth::user()->company;

        $data['company_id'] = $company->id;
        $data['created_by'] = Auth::id();

        $asset = $lifecycle->acquire($company, $data);

        AuditLog::record('fixed_asset.create', $asset, __('Registered fixed asset :code', ['code' => $asset->asset_code]));

        return redirect()->route('app.fixed-assets.show', $asset)->with('status', __('Fixed asset registered.'));
    }

    public function edit(FixedAsset $fixedAsset)
    {
        return view('user.fixed-assets.form', [
            'asset' => $fixedAsset,
            'glAccounts' => Account::where('is_active', true)->orderBy('code')->get(),
            'bankAccounts' => BankAccount::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, FixedAsset $fixedAsset, FixedAssetLifecycleService $lifecycle)
    {
        $data = $this->validated($request);

        $error = $lifecycle->amend($fixedAsset, $data);

        if ($error) {
            return back()->withErrors(['asset' => $error]);
        }

        AuditLog::record('fixed_asset.update', $fixedAsset, __('Updated fixed asset :code', ['code' => $fixedAsset->asset_code]));

        return redirect()->route('app.fixed-assets.show', $fixedAsset)->with('status', __('Fixed asset updated.'));
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

    public function dispose(Request $request, FixedAsset $fixedAsset, FixedAssetLifecycleService $lifecycle)
    {
        abort_unless($fixedAsset->status === 'active', 404);

        $data = $request->validate([
            'disposed_at' => ['required', 'date'],
            'disposal_proceeds' => ['nullable', 'numeric', 'min:0'],
        ]);

        $error = $lifecycle->dispose($fixedAsset, (float) ($data['disposal_proceeds'] ?? 0), new \DateTime($data['disposed_at']));

        if ($error) {
            return back()->withErrors(['disposal' => $error]);
        }

        AuditLog::record('fixed_asset.dispose', $fixedAsset, __('Disposed fixed asset :code', ['code' => $fixedAsset->asset_code]));

        return redirect()->route('app.fixed-assets.show', $fixedAsset)->with('status', __('Asset marked as disposed.'));
    }

    private function validated(Request $request): array
    {
        $companyId = Auth::user()->company_id;

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'account_id' => ['nullable', Rule::exists('accounts', 'id')->where('company_id', $companyId)->where('is_active', true)],
            'bank_account_id' => ['nullable', Rule::exists('bank_accounts', 'id')->where('company_id', $companyId)],
            'acquisition_date' => ['required', 'date'],
            'acquisition_cost' => ['required', 'numeric', 'min:0.01'],
            'salvage_value' => ['nullable', 'numeric', 'min:0'],
            'useful_life_years' => ['required', 'integer', 'min:1', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
