<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the user's real company (Dynamic Core Contracting Company) so it
 * survives a migrate:fresh — this is genuine business data (CR/VAT/address
 * confirmed against the company's actual registration documents, bank
 * details from a real issued quote), not demo data, so it's seeded
 * unconditionally rather than gated behind DemoSeeder's local-only check.
 * Safe to re-run: every write is an updateOrCreate/firstOrCreate keyed on
 * a natural unique field, so it never duplicates the company on reseed.
 */
class RealCompanySeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::updateOrCreate(
            ['vat_number' => '314526094900003'],
            [
                'name' => 'Dynamic Core Contracting Company',
                'name_ar' => 'شركة دايناميك كور كونتراكتينج',
                'slug' => 'dynamic-core-contracting',
                'cr_number' => '7053180563',
                'address' => 'Building 3779, Al Umrah 2 St, Al Umrah Dist., Makkah 24414',
                'city' => 'Makkah',
                'building_number' => '3779',
                'street_name' => 'Al Umrah 2 St',
                'district' => 'Al Umrah',
                'postal_code' => '24414',
                'additional_number' => '8683',
                'phone' => '0568582270',
                'email' => 'info@dynamic.com.sa',
                'currency' => 'SAR',
                'locale' => 'en',
                'status' => 'active',
                'trial_ends_at' => now()->addYear(),
            ]
        );

        Role::seedSystemRoles($company->id);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        User::firstOrCreate(
            ['email' => 'owner@dynamiccorecontracting.sa'],
            [
                'company_id' => $company->id,
                'name' => 'Dynamic Core Owner',
                'password' => Hash::make('DynamicCore@2026'),
                'role' => 'owner',
                'status' => 'active',
            ]
        );

        if (! $company->activeSubscription()) {
            $plan = Plan::where('slug', 'enterprise')->first();

            if ($plan) {
                Subscription::create([
                    'company_id' => $company->id,
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'billing_cycle' => 'yearly',
                    'current_period_start' => now(),
                    'current_period_end' => now()->addYear(),
                ]);
            }
        }

        $bankAccount = BankAccount::updateOrCreate(
            ['company_id' => $company->id, 'iban' => 'SA7210000002600000918103'],
            [
                'name' => 'SNB — Operating Account',
                'bank_name' => 'Saudi National Bank (SNB)',
                'account_holder_name' => 'Dynamic Core Contracting Company',
                'account_number' => '02600000918103',
                'type' => 'bank',
                'currency' => 'SAR',
                'is_active' => true,
            ]
        );

        if (! $company->default_bank_account_id) {
            $company->update(['default_bank_account_id' => $bankAccount->id]);
        }
    }
}
