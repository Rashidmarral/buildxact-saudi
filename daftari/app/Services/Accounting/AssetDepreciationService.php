<?php

namespace App\Services\Accounting;

use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\FixedAsset;
use Carbon\Carbon;

/**
 * Posts one month of straight-line depreciation for every active,
 * not-yet-fully-depreciated asset in a company. Safe to run more than
 * once for the same month — see the sourceId packing note below for how
 * that idempotency is achieved without a dedicated tracking table.
 */
class AssetDepreciationService
{
    public function __construct(private LedgerPostingService $ledger) {}

    public function postForCompany(Company $company, ?Carbon $period = null): int
    {
        $period ??= now();

        $depreciationAccount = AccountMapping::resolve($company->id, 'DEPRECIATION_EXPENSE_DEFAULT');
        $accumulatedAccount = AccountMapping::resolve($company->id, 'ACCUMULATED_DEPRECIATION_DEFAULT');

        if (! $depreciationAccount || ! $accumulatedAccount) {
            return 0;
        }

        $assets = FixedAsset::where('company_id', $company->id)->where('status', 'active')->get();
        $posted = 0;

        foreach ($assets as $asset) {
            if ($asset->isFullyDepreciated()) {
                continue;
            }

            $amount = min($asset->monthlyDepreciation(), $asset->remainingDepreciable());

            if ($amount <= 0) {
                continue;
            }

            $entry = $this->ledger->post(
                $company,
                'fixed_asset_depreciation',
                $this->sourceIdFor($asset, $period),
                __('Depreciation — :name (:code), :period', [
                    'name' => $asset->name,
                    'code' => $asset->asset_code,
                    'period' => $period->format('Y-m'),
                ]),
                $period->copy()->endOfMonth(),
                [
                    ['account_id' => $depreciationAccount->id, 'debit' => $amount],
                    ['account_id' => $accumulatedAccount->id, 'credit' => $amount],
                ]
            );

            if ($entry) {
                $asset->increment('accumulated_depreciation', $amount);
                $posted++;
            }
        }

        return $posted;
    }

    /**
     * LedgerPostingService's duplicate-posting guard keys on
     * (source_type, source_id) alone — there's no separate "period" column
     * to key on. Packing asset_id and year/month into one bigint gives
     * each (asset, month) pair its own unique, deterministic id under the
     * existing "fixed_asset_depreciation" source_type, so re-running this
     * for a month that's already posted is naturally a no-op instead of
     * needing a new tracking table.
     */
    private function sourceIdFor(FixedAsset $asset, Carbon $period): int
    {
        return $asset->id * 1_000_000 + ($period->year * 100 + $period->month);
    }
}
