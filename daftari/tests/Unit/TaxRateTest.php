<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\TaxRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxRateTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        return Company::create(['name' => 'Test Co', 'slug' => 'test-co-'.uniqid()]);
    }

    public function test_seed_defaults_creates_standard_zero_rated_and_exempt(): void
    {
        $company = $this->makeCompany();

        TaxRate::seedDefaults($company->id);

        $rates = TaxRate::where('company_id', $company->id)->get()->keyBy('type');

        $this->assertCount(3, $rates);
        $this->assertSame(15.0, (float) $rates[TaxRate::TYPE_STANDARD]->rate);
        $this->assertSame(0.0, (float) $rates[TaxRate::TYPE_ZERO_RATED]->rate);
        $this->assertSame(0.0, (float) $rates[TaxRate::TYPE_EXEMPT]->rate);
        $this->assertTrue((bool) $rates[TaxRate::TYPE_STANDARD]->is_default);
        $this->assertFalse((bool) $rates[TaxRate::TYPE_ZERO_RATED]->is_default);
        $this->assertFalse((bool) $rates[TaxRate::TYPE_EXEMPT]->is_default);
    }

    public function test_seed_defaults_is_idempotent(): void
    {
        $company = $this->makeCompany();

        TaxRate::seedDefaults($company->id);
        TaxRate::seedDefaults($company->id);

        $this->assertSame(3, TaxRate::where('company_id', $company->id)->count());
    }

    public function test_default_rate_returns_the_standard_fifteen_percent(): void
    {
        $company = $this->makeCompany();
        TaxRate::seedDefaults($company->id);

        $this->assertSame(15.0, TaxRate::defaultRate($company->id));
    }

    public function test_default_rate_is_zero_when_company_has_no_tax_rates_at_all(): void
    {
        // No hardcoded 15 fallback anywhere in the app — a company with an
        // empty tax rate list (shouldn't normally happen, but must degrade
        // safely) gets 0, not a silently invented rate.
        $company = $this->makeCompany();

        $this->assertSame(0.0, TaxRate::defaultRate($company->id));
    }

    public function test_make_default_unsets_the_previous_default(): void
    {
        $company = $this->makeCompany();
        TaxRate::seedDefaults($company->id);

        $zeroRated = TaxRate::where('company_id', $company->id)->where('type', TaxRate::TYPE_ZERO_RATED)->first();
        $zeroRated->makeDefault();

        $standard = TaxRate::where('company_id', $company->id)->where('type', TaxRate::TYPE_STANDARD)->first();

        $this->assertTrue($zeroRated->fresh()->is_default);
        $this->assertFalse($standard->fresh()->is_default);
        $this->assertSame(0.0, TaxRate::defaultRate($company->id));
    }

    public function test_effective_as_of_excludes_a_rate_not_yet_in_force(): void
    {
        $company = $this->makeCompany();
        TaxRate::create([
            'company_id' => $company->id,
            'name' => 'Future VAT 20%',
            'rate' => 20,
            'type' => TaxRate::TYPE_STANDARD,
            'is_active' => true,
            'effective_date' => now()->addYear()->toDateString(),
        ]);

        $visible = TaxRate::where('company_id', $company->id)->effectiveAsOf()->pluck('name');

        $this->assertFalse($visible->contains('Future VAT 20%'));
    }

    public function test_tax_rates_are_scoped_per_company(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        TaxRate::seedDefaults($companyA->id);
        TaxRate::seedDefaults($companyB->id);

        $this->assertSame(3, TaxRate::where('company_id', $companyA->id)->count());
        $this->assertSame(3, TaxRate::where('company_id', $companyB->id)->count());
        $this->assertSame(6, TaxRate::count());
    }
}
