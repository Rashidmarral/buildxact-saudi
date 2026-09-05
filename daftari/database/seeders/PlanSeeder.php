<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Matched against each plan's *existing* slug (its identity before this
     * seeder introduced the Invoicing/Essential/Business naming), so
     * re-running this seeder on an install that already has subscriptions
     * renames and re-configures the same plan rows in place instead of
     * creating new ones and orphaning existing subscriptions.
     */
    public function run(): void
    {
        $plans = [
            'starter' => [
                'name' => 'Invoicing',
                'name_ar' => 'الفوترة',
                'slug' => 'invoicing',
                'price_monthly' => 49,
                'price_monthly_original' => 59,
                'price_yearly' => 490,
                'price_yearly_original' => 590,
                'max_users' => 1,
                'max_invoices_per_month' => 30,
                'max_customers' => 50,
                'max_suppliers' => 10,
                'max_invoice_templates' => 1,
                'max_warehouses' => 1,
                'max_bank_accounts' => 1,
                'max_branches' => 1,
                'max_items' => 100,
                'max_api_calls_per_month' => null,
                'has_recurring_invoices' => false,
                'has_quotations' => false,
                'has_stamps' => false,
                'has_financial_statements' => false,
                'has_vat_return_report' => false,
                'has_cost_centers' => false,
                'has_purchase_orders' => false,
                'has_debit_notes' => false,
                'has_roles_permissions' => false,
                'has_zatca_phase2' => false,
                'has_api' => false,
                'has_whatsapp' => false,
                'features' => [
                    'VAT-compliant invoicing with ZATCA QR codes',
                    'Up to 30 invoices / month',
                    '1 user',
                    'Email support',
                ],
                'sort_order' => 1,
            ],
            'professional' => [
                'name' => 'Essential',
                'name_ar' => 'الأساسية',
                'slug' => 'essential',
                'price_monthly' => 59,
                'price_monthly_original' => 89,
                'price_yearly' => 590,
                'price_yearly_original' => 890,
                'max_users' => 5,
                'max_invoices_per_month' => null,
                'max_customers' => 200,
                'max_suppliers' => 100,
                'max_invoice_templates' => 5,
                'max_warehouses' => 3,
                'max_bank_accounts' => 3,
                'max_branches' => 3,
                'max_items' => 2000,
                'max_api_calls_per_month' => 10000,
                'has_recurring_invoices' => true,
                'has_quotations' => true,
                'has_stamps' => true,
                'has_financial_statements' => true,
                'has_vat_return_report' => true,
                'has_cost_centers' => false,
                'has_purchase_orders' => true,
                'has_debit_notes' => true,
                'has_roles_permissions' => false,
                'has_zatca_phase2' => true,
                'has_api' => true,
                'has_whatsapp' => true,
                'features' => [
                    'Everything in Invoicing',
                    'Unlimited invoices',
                    'ZATCA Phase 2 real-time integration',
                    'Recurring invoices & quotations',
                    'Purchase orders & debit notes',
                    'Financial statements & VAT return report',
                    'Company stamp on documents',
                    'Up to 5 users',
                    'Priority support',
                ],
                'sort_order' => 2,
            ],
            'enterprise' => [
                'name' => 'Business',
                'name_ar' => 'الأعمال',
                'slug' => 'business',
                'price_monthly' => 109,
                'price_monthly_original' => 169,
                'price_yearly' => 1090,
                'price_yearly_original' => 1690,
                'max_users' => null,
                'max_invoices_per_month' => null,
                'max_customers' => null,
                'max_suppliers' => null,
                'max_invoice_templates' => null,
                'max_warehouses' => null,
                'max_bank_accounts' => null,
                'max_branches' => null,
                'max_items' => null,
                'max_api_calls_per_month' => null,
                'has_recurring_invoices' => true,
                'has_quotations' => true,
                'has_stamps' => true,
                'has_financial_statements' => true,
                'has_vat_return_report' => true,
                'has_cost_centers' => true,
                'has_purchase_orders' => true,
                'has_debit_notes' => true,
                'has_roles_permissions' => true,
                'has_zatca_phase2' => true,
                'has_api' => true,
                'has_whatsapp' => true,
                'features' => [
                    'Everything in Essential',
                    'Unlimited users, branches & warehouses',
                    'Cost centers',
                    'Custom roles & permissions',
                    'Dedicated account manager',
                ],
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $currentSlug => $plan) {
            $existing = Plan::where('slug', $currentSlug)->first()
                ?? Plan::where('slug', $plan['slug'])->first();

            if ($existing) {
                $existing->update($plan);
            } else {
                Plan::create($plan);
            }
        }
    }
}
