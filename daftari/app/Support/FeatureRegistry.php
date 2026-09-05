<?php

namespace App\Support;

/**
 * The catalog of module-level features a Super Admin can see and control
 * per plan (Module 07). Deliberately separate from Plan::FEATURE_KEYS /
 * Company::hasFeature(), which is the pre-existing, unrelated system for
 * fine-grained CAPABILITY flags within a module (recurring invoices,
 * quotations, stamps, VAT return report, cost centers, purchase orders,
 * debit notes, roles & permissions) — that system is untouched by this
 * one and keeps working exactly as it did before.
 *
 * Every entry here has a 'type':
 *   - 'core': a foundational module every company always has. Not a real
 *     plan toggle (gating Sales or Accounting off per plan would break
 *     the product for that tier) — listed for a complete, honest catalog
 *     and so FeatureAccessService::enabled() answers uniformly for any
 *     key a developer might ask about.
 *   - 'gated': a real, currently-enforced plan toggle. 'column' names the
 *     Plan boolean column it reads (nullable — 'multi_branch' is instead
 *     computed from the max_branches limit, see FeatureAccessService).
 *   - 'planned': listed because the request named it, but no such module
 *     exists in the app yet (Payroll, POS) — always returns false. Only
 *     register a 'planned' entry for something genuinely reserved for a
 *     realistic future module; don't invent modules that will never
 *     exist just to fill out a list.
 */
class FeatureRegistry
{
    public static function catalog(): array
    {
        return [
            'accounting' => ['label' => __('Accounting'), 'type' => 'core'],
            'sales' => ['label' => __('Sales'), 'type' => 'core'],
            'purchases' => ['label' => __('Purchases'), 'type' => 'core'],
            'inventory' => ['label' => __('Inventory'), 'type' => 'core'],
            'expenses' => ['label' => __('Expenses'), 'type' => 'core'],
            'banking' => ['label' => __('Banking'), 'type' => 'core'],
            'reports' => ['label' => __('Reports'), 'type' => 'core'],
            'zatca' => ['label' => __('ZATCA'), 'type' => 'gated', 'column' => 'has_zatca_phase2'],
            'multi_branch' => ['label' => __('Multi-branch'), 'type' => 'gated', 'column' => null],
            'api' => ['label' => __('API'), 'type' => 'gated', 'column' => 'has_api'],
            'advanced_reports' => ['label' => __('Advanced reports'), 'type' => 'gated', 'column' => 'has_financial_statements'],
            'whatsapp' => ['label' => __('WhatsApp'), 'type' => 'gated', 'column' => 'has_whatsapp'],
            'payroll' => ['label' => __('Payroll'), 'type' => 'planned'],
            'pos' => ['label' => __('POS'), 'type' => 'planned'],
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

    public static function gatedKeys(): array
    {
        return array_keys(array_filter(self::catalog(), fn ($entry) => $entry['type'] === 'gated'));
    }
}
