<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\WhtRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The 2026_09_17_020000 migration is the one-time catch-up for the two
 * real companies (and any other company in the same state) that
 * RealCompanySeeder left with zero TaxRate/WhtRate rows before that
 * seeder was fixed to call seedDefaults() for all three sets.
 */
class BackfillTaxAndWhtRateDefaultsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_migration_seeds_defaults_for_a_company_missing_them_and_leaves_one_with_its_own_rates_alone(): void
    {
        $missingDefaults = Company::create(['name' => 'No Rates Co.', 'slug' => 'no-rates-'.uniqid()]);

        $hasOwnRates = Company::create(['name' => 'Has Own Rates Co.', 'slug' => 'has-rates-'.uniqid()]);
        TaxRate::create(['company_id' => $hasOwnRates->id, 'name' => 'Custom 6%', 'rate' => 6, 'type' => TaxRate::TYPE_STANDARD, 'is_default' => true, 'is_active' => true]);

        $migration = require database_path('migrations/2026_09_17_020000_backfill_tax_and_wht_rate_defaults_for_companies_missing_them.php');
        $migration->up();

        $this->assertGreaterThan(0, TaxRate::where('company_id', $missingDefaults->id)->count());
        $this->assertGreaterThan(0, WhtRate::where('company_id', $missingDefaults->id)->count());
        $this->assertGreaterThan(0, Unit::where('company_id', $missingDefaults->id)->count());
        $this->assertSame(15.0, TaxRate::defaultRate($missingDefaults->id));

        // A company that already set up its own rates is left exactly as is.
        $this->assertSame(1, TaxRate::where('company_id', $hasOwnRates->id)->count());
        $this->assertSame(6.0, TaxRate::defaultRate($hasOwnRates->id));
    }
}
