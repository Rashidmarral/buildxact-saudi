<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'name_ar' => 'أساسي',
                'slug' => 'starter',
                'price_monthly' => 49,
                'price_yearly' => 490,
                'max_users' => 2,
                'max_invoices_per_month' => 30,
                'features' => ['VAT-compliant invoicing', 'Expense tracking', 'Email support'],
                'sort_order' => 1,
            ],
            [
                'name' => 'Professional',
                'name_ar' => 'احترافي',
                'slug' => 'professional',
                'price_monthly' => 129,
                'price_yearly' => 1290,
                'max_users' => 10,
                'max_invoices_per_month' => null,
                'features' => ['Unlimited invoices', 'VAT report exports', 'Team roles', 'Priority support'],
                'sort_order' => 2,
            ],
            [
                'name' => 'Enterprise',
                'name_ar' => 'مؤسسي',
                'slug' => 'enterprise',
                'price_monthly' => 299,
                'price_yearly' => 2990,
                'max_users' => null,
                'max_invoices_per_month' => null,
                'features' => ['Unlimited everything', 'Dedicated account manager', 'Custom onboarding'],
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
