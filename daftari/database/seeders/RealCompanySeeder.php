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

        $this->seedZubaidiMaintenance();
    }

    /**
     * Second real company: an Abdulmajeed Al-Zubaidi sole-proprietorship
     * establishment (مؤسسة) in Makkah. CR/VAT/National Address confirmed
     * against the company's actual government-issued documents (CR
     * certificate, VAT registration, National Address proof, SNB IBAN
     * letter) — same treatment as Dynamic Core above: real data, seeded
     * unconditionally, idempotent on re-run.
     */
    private function seedZubaidiMaintenance(): void
    {
        $company = Company::updateOrCreate(
            ['vat_number' => '310464560600003'],
            [
                'name' => 'Abdulmajeed Abdullah Al-Zubaidi Est. for Maintenance',
                'name_ar' => 'مؤسسة عبدالمجيد عبدالله بن عبيد الزبيدي للصيانة',
                'slug' => 'zubaidi-maintenance',
                'cr_number' => '4031233603',
                'address' => 'Building 8562, Muhammad Lutfi Jameah St, Al Buhayrat Dist., Makkah 24211',
                'city' => 'Makkah',
                'building_number' => '8562',
                'street_name' => 'Muhammad Lutfi Jameah',
                'district' => 'Al Buhayrat',
                'postal_code' => '24211',
                'additional_number' => '4565',
                'currency' => 'SAR',
                'locale' => 'ar',
                'status' => 'active',
                'trial_ends_at' => now()->addYear(),
            ]
        );

        Role::seedSystemRoles($company->id);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        User::firstOrCreate(
            ['email' => 'owner@zubaidimaintenance.sa'],
            [
                'company_id' => $company->id,
                'name' => 'Abdulmajeed Al-Zubaidi',
                'password' => Hash::make('ZubaidiEst@2026'),
                'role' => 'owner',
                'status' => 'active',
            ]
        );

        $existingSubscription = $company->activeSubscription();
        $enterprisePlan = Plan::where('slug', 'enterprise')->first();

        if ($enterprisePlan && (! $existingSubscription || $existingSubscription->plan_id !== $enterprisePlan->id)) {
            if ($existingSubscription) {
                $existingSubscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            }

            Subscription::create([
                'company_id' => $company->id,
                'plan_id' => $enterprisePlan->id,
                'status' => 'active',
                'billing_cycle' => 'yearly',
                'current_period_start' => now(),
                'current_period_end' => now()->addYear(),
            ]);
        }

        $bankAccount = BankAccount::updateOrCreate(
            ['company_id' => $company->id, 'iban' => 'SA2610000001400005458106'],
            [
                'name' => 'SNB — Operating Account',
                'bank_name' => 'Saudi National Bank (SNB)',
                'account_holder_name' => 'مؤسسة عبدالمجيد عبدالله بن عبيد الزبيدي للصيانة',
                'account_number' => '01400005458106',
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
