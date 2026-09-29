<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\FixedAsset;
use Illuminate\Support\Facades\DB;

/**
 * The acquire/dispose GL-posting logic behind a Fixed Asset's lifecycle —
 * extracted out of FixedAssetController so a second caller (the Machinery
 * & Equipment module: buying and selling machinery) can post the exact
 * same acquisition/disposal accounting instead of re-deriving it. A
 * machine's purchase and sale are, financially, nothing but a Fixed Asset
 * acquisition and disposal.
 */
class FixedAssetLifecycleService
{
    public function __construct(private LedgerPostingService $ledger) {}

    /**
     * Creates the FixedAsset row and posts its acquisition (debit Fixed
     * Assets, credit the paying bank/cash account or Accounts Payable when
     * unpaid) — identical to FixedAssetController::store()'s prior inline
     * logic. Silently skips posting (asset still created) when a required
     * account mapping is missing, matching the pre-existing behavior this
     * was extracted from.
     */
    public function acquire(Company $company, array $data): FixedAsset
    {
        $data['account_id'] = $data['account_id'] ?? AccountMapping::resolve($company->id, 'FIXED_ASSETS_DEFAULT')?->id;

        return DB::transaction(function () use ($data, $company) {
            $asset = FixedAsset::create($data);

            $fixedAssetsAccount = Account::find($asset->account_id) ?? AccountMapping::resolve($company->id, 'FIXED_ASSETS_DEFAULT');
            $creditAccount = $asset->bank_account_id
                ? AccountMapping::resolve($company->id, $asset->bankAccount->type === 'cash' ? 'DEFAULT_CASH' : 'DEFAULT_BANK')
                : AccountMapping::resolve($company->id, 'ACCOUNTS_PAYABLE');

            if ($fixedAssetsAccount && $creditAccount) {
                $this->ledger->post(
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

            return $asset;
        });
    }

    /**
     * Posts disposal (reverse cost + accumulated depreciation, book any
     * cash proceeds, plug the gain/loss), then marks the asset disposed —
     * identical to FixedAssetController::dispose()'s prior inline logic.
     * Returns an error message (and posts/updates nothing) when the
     * required Fixed Assets account mapping is missing, so the caller can
     * flash a clear error instead of the transaction silently doing the
     * wrong thing (audit finding MEDIUM-3 — see FixedAssetDisposalTest).
     */
    public function dispose(FixedAsset $fixedAsset, float $proceeds, \DateTimeInterface $disposedAt): ?string
    {
        $company = $fixedAsset->company;
        $fixedAssetsAccount = $fixedAsset->account ?? AccountMapping::resolve($company->id, 'FIXED_ASSETS_DEFAULT');
        $accumulatedAccount = AccountMapping::resolve($company->id, 'ACCUMULATED_DEPRECIATION_DEFAULT');
        $bankAccount = $fixedAsset->bankAccount
            ? AccountMapping::resolve($company->id, $fixedAsset->bankAccount->type === 'cash' ? 'DEFAULT_CASH' : 'DEFAULT_BANK')
            : AccountMapping::resolve($company->id, 'DEFAULT_BANK');

        if (! $fixedAssetsAccount) {
            return __('Cannot dispose this asset: no Fixed Assets account mapping is configured. Set one in Settings > Semantic Account Mappings, or link an account directly on the asset.');
        }

        $netBookValue = $fixedAsset->netBookValue();
        $gainLoss = round($proceeds - $netBookValue, 2);

        $lines = [
            ['account_id' => $fixedAssetsAccount->id, 'credit' => (float) $fixedAsset->acquisition_cost],
        ];
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

        DB::transaction(function () use ($fixedAsset, $company, $disposedAt, $lines, $proceeds) {
            $this->ledger->post(
                $company,
                'fixed_asset_disposal',
                $fixedAsset->id,
                __('Disposed :name (:code)', ['name' => $fixedAsset->name, 'code' => $fixedAsset->asset_code]),
                $disposedAt,
                $lines
            );

            $fixedAsset->update([
                'status' => 'disposed',
                'disposed_at' => $disposedAt,
                'disposal_proceeds' => $proceeds,
            ]);
        });

        return null;
    }
}
