<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\WhtRate;
use Database\Seeders\RealCompanySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Found while investigating the Zubaidi disabled-unit-picker report:
 * RealCompanySeeder (which seeds both real companies — Dynamic Core and
 * Zubaidi Maintenance — bypassing the normal signup flow entirely) only
 * called AccountMapping::seedDefaults(), never TaxRate::seedDefaults() or
 * WhtRate::seedDefaults(). A company with zero TaxRate rows isn't just a
 * missing dropdown option: TaxRate::defaultRate() documents that it falls
 * back to a bare 0.0, never a hardcoded 15 — so every new invoice/
 * quotation/bill line for that company silently pre-filled at 0% VAT.
 */
class RealCompanySeederDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_real_companies_get_the_same_defaults_a_normal_signup_would(): void
    {
        (new RealCompanySeeder)->run();

        $dynamicCore = Company::where('vat_number', '314526094900003')->firstOrFail();
        $zubaidi = Company::where('vat_number', '310464560600003')->firstOrFail();

        foreach ([$dynamicCore, $zubaidi] as $company) {
            $this->assertGreaterThan(0, TaxRate::where('company_id', $company->id)->count(), "{$company->name} has no tax rates");
            $this->assertGreaterThan(0, WhtRate::where('company_id', $company->id)->count(), "{$company->name} has no WHT rates");
            $this->assertGreaterThan(0, Unit::where('company_id', $company->id)->count(), "{$company->name} has no units");
            $this->assertSame(15.0, TaxRate::defaultRate($company->id), "{$company->name}'s default VAT rate should be the standard 15%, not the no-tax-rates-at-all fallback of 0%");
        }
    }

    public function test_rerunning_the_seeder_does_not_duplicate_the_defaults(): void
    {
        (new RealCompanySeeder)->run();
        (new RealCompanySeeder)->run();

        $dynamicCore = Company::where('vat_number', '314526094900003')->firstOrFail();

        $this->assertSame(3, TaxRate::where('company_id', $dynamicCore->id)->count());
        $this->assertSame(8, WhtRate::where('company_id', $dynamicCore->id)->count());
    }
}
