<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name', 'name_ar', 'description', 'slug', 'currency', 'trial_days',
        'price_monthly', 'price_yearly',
        'price_monthly_original', 'price_yearly_original',
        'max_users', 'max_invoices_per_month', 'max_customers', 'max_suppliers',
        'max_invoice_templates', 'max_warehouses', 'max_bank_accounts', 'max_branches', 'max_storage_mb',
        'max_items', 'max_api_calls_per_month',
        'has_recurring_invoices', 'has_quotations', 'has_stamps', 'has_financial_statements',
        'has_vat_return_report', 'has_cost_centers', 'has_purchase_orders', 'has_debit_notes',
        'has_roles_permissions', 'has_zatca_phase2', 'has_api', 'has_whatsapp',
        'features', 'is_active', 'is_public', 'is_featured', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'is_featured' => 'boolean',
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
            'has_zatca_phase2' => 'boolean',
            'has_api' => 'boolean',
            'has_whatsapp' => 'boolean',
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
        'zatca_phase2' => 'has_zatca_phase2',
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

    /**
     * This plan's trial length, falling back to the platform-wide
     * trial_days Setting (and its config default) when the plan doesn't
     * override it — same fallback chain AuthController::register() used
     * before per-plan trial periods existed, so a plan left blank behaves
     * exactly as every plan did previously.
     */
    public function trialDays(): int
    {
        return $this->trial_days ?? (int) Setting::get('trial_days', config('daftari.trial_days'));
    }
}
