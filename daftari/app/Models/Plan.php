<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name', 'name_ar', 'slug', 'price_monthly', 'price_yearly',
        'price_monthly_original', 'price_yearly_original',
        'max_users', 'max_invoices_per_month', 'max_customers', 'max_suppliers',
        'max_invoice_templates', 'max_warehouses', 'max_bank_accounts', 'max_branches',
        'has_recurring_invoices', 'has_quotations', 'has_stamps', 'has_financial_statements',
        'has_vat_return_report', 'has_cost_centers', 'has_purchase_orders', 'has_debit_notes',
        'has_roles_permissions',
        'features', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_active' => 'boolean',
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
            'price_monthly_original' => 'decimal:2',
            'price_yearly_original' => 'decimal:2',
            'has_recurring_invoices' => 'boolean',
            'has_quotations' => 'boolean',
            'has_stamps' => 'boolean',
            'has_financial_statements' => 'boolean',
            'has_vat_return_report' => 'boolean',
            'has_cost_centers' => 'boolean',
            'has_purchase_orders' => 'boolean',
            'has_debit_notes' => 'boolean',
            'has_roles_permissions' => 'boolean',
        ];
    }

    /**
     * Maps a feature key (as used by Company::hasFeature()) to this plan's
     * boolean column. Kept as a single lookup table so the pricing page's
     * comparison table and the actual gating logic can never drift apart.
     */
    public const FEATURE_KEYS = [
        'recurring_invoices' => 'has_recurring_invoices',
        'quotations' => 'has_quotations',
        'stamps' => 'has_stamps',
        'financial_statements' => 'has_financial_statements',
        'vat_return_report' => 'has_vat_return_report',
        'cost_centers' => 'has_cost_centers',
        'purchase_orders' => 'has_purchase_orders',
        'debit_notes' => 'has_debit_notes',
        'roles_permissions' => 'has_roles_permissions',
    ];

    public function hasFeature(string $key): bool
    {
        $column = self::FEATURE_KEYS[$key] ?? null;

        return $column ? (bool) $this->{$column} : false;
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function priceFor(string $cycle): float
    {
        return (float) ($cycle === 'yearly' ? $this->price_yearly : $this->price_monthly);
    }
}
