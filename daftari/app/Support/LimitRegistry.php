<?php

namespace App\Support;

use App\Models\ApiUsageCounter;
use App\Models\Company;

/**
 * The catalog of usage limits a Super Admin can configure per plan
 * (Module 07) and override per company (UsageLimitService). 'column'
 * names the Plan column holding the default cap; 'usage' computes how
 * much of it a company has used right now. Six of these nine
 * (users/branches/customers/suppliers/warehouses/invoices) reuse the
 * exact counting logic Company::hasReachedPlanLimit() already had before
 * this module — see UsageLimitService's docblock for why that method now
 * delegates here instead of duplicating it.
 */
class LimitRegistry
{
    /**
     * @return array<string, array{label: string, column: string, unit: string, usage: \Closure(Company): int}>
     */
    public static function catalog(): array
    {
        return [
            'users' => [
                'label' => __('Users'), 'column' => 'max_users', 'unit' => 'count',
                'usage' => fn (Company $company) => $company->users()->count(),
            ],
            'branches' => [
                'label' => __('Branches'), 'column' => 'max_branches', 'unit' => 'count',
                'usage' => fn (Company $company) => $company->branches()->count(),
            ],
            'customers' => [
                'label' => __('Customers'), 'column' => 'max_customers', 'unit' => 'count',
                'usage' => fn (Company $company) => $company->clients()->count(),
            ],
            'suppliers' => [
                'label' => __('Suppliers'), 'column' => 'max_suppliers', 'unit' => 'count',
                'usage' => fn (Company $company) => $company->suppliers()->count(),
            ],
            'products' => [
                'label' => __('Products'), 'column' => 'max_items', 'unit' => 'count',
                'usage' => fn (Company $company) => $company->items()->count(),
            ],
            'invoices' => [
                'label' => __('Invoices per month'), 'column' => 'max_invoices_per_month', 'unit' => 'count',
                'usage' => function (Company $company) {
                    $subscription = $company->activeSubscription();
                    $periodStart = $subscription?->current_period_start ?? $company->created_at;

                    return $company->invoices()->where('created_at', '>=', $periodStart)->count();
                },
            ],
            'storage' => [
                'label' => __('Storage'), 'column' => 'max_storage_mb', 'unit' => 'mb',
                'usage' => fn (Company $company) => (int) round($company->storageUsedBytes() / 1048576),
            ],
            'api_calls' => [
                'label' => __('API calls'), 'column' => 'max_api_calls_per_month', 'unit' => 'count',
                'usage' => fn (Company $company) => ApiUsageCounter::currentPeriodCount($company->id),
            ],
            'warehouses' => [
                'label' => __('Warehouses'), 'column' => 'max_warehouses', 'unit' => 'count',
                'usage' => fn (Company $company) => $company->warehouses()->count(),
            ],
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::catalog());
    }

    public static function isValid(string $key): bool
    {
        return array_key_exists($key, self::catalog());
    }
}
