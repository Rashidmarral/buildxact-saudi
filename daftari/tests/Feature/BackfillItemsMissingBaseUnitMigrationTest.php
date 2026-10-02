<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Item;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The 2026_09_17_010000 migration is the one-time catch-up for Item rows
 * that predate Item::booted()'s base-unit guard — real production data
 * like ZubaidiProductsSeeder's ~380 rows and any DemoSeeder company. This
 * test simulates that pre-fix state directly (a raw DB insert, bypassing
 * the model guard entirely, the same way those rows actually got into the
 * database) across two different companies, then runs the migration's own
 * up() and asserts every row across every company gets backfilled in one
 * pass — not one company at a time.
 */
class BackfillItemsMissingBaseUnitMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_migration_backfills_items_missing_a_base_unit_across_every_company_at_once(): void
    {
        $companyWithUnits = Company::create(['name' => 'Has Units Co.', 'slug' => 'has-units-'.uniqid()]);
        Unit::seedDefaults($companyWithUnits->id);
        $kg = Unit::where('company_id', $companyWithUnits->id)->where('code', 'KGM')->first();

        $companyWithoutUnits = Company::create(['name' => 'No Units Co.', 'slug' => 'no-units-'.uniqid()]);
        $this->assertSame(0, Unit::where('company_id', $companyWithoutUnits->id)->count());

        // Raw inserts — bypasses Item::booted()'s guard entirely, exactly
        // how ZubaidiProductsSeeder's Item::updateOrCreate() calls looked
        // before that guard existed (base_unit_id simply never set).
        $legacyId = DB::table('items')->insertGetId([
            'company_id' => $companyWithUnits->id, 'name' => 'Legacy Kilogram Item',
            'item_type' => 'physical', 'unit' => 'Kilogram', 'unit_code' => 'KGM',
            'unit_price' => 10, 'vat_rate' => 15, 'base_unit_id' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $noUnitsCompanyItemId = DB::table('items')->insertGetId([
            'company_id' => $companyWithoutUnits->id, 'name' => 'Item For A Unitless Company',
            'item_type' => 'service', 'unit_price' => 20, 'vat_rate' => 15, 'base_unit_id' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_09_17_010000_backfill_items_still_missing_a_base_unit.php');
        $migration->up();

        $legacyItem = Item::find($legacyId);
        $noUnitsItem = Item::find($noUnitsCompanyItemId);

        $this->assertSame($kg->id, $legacyItem->base_unit_id, 'should match by legacy unit_code, not just fall back to PCE');

        $this->assertNotNull($noUnitsItem->base_unit_id);
        $pceForUnitlessCompany = Unit::where('company_id', $companyWithoutUnits->id)->where('code', 'PCE')->first();
        $this->assertNotNull($pceForUnitlessCompany, 'the migration should seed default units for a company that had none');
        $this->assertSame($pceForUnitlessCompany->id, $noUnitsItem->base_unit_id);
    }

    public function test_the_migration_does_not_touch_items_that_already_have_a_base_unit(): void
    {
        $company = Company::create(['name' => 'Already Fine Co.', 'slug' => 'already-fine-'.uniqid()]);
        Unit::seedDefaults($company->id);
        $litre = Unit::where('company_id', $company->id)->where('code', 'LTR')->first();

        $item = Item::create([
            'company_id' => $company->id, 'name' => 'Already Has A Unit',
            'item_type' => 'physical', 'unit_price' => 5, 'vat_rate' => 15, 'base_unit_id' => $litre->id,
        ]);

        $migration = require database_path('migrations/2026_09_17_010000_backfill_items_still_missing_a_base_unit.php');
        $migration->up();

        $this->assertSame($litre->id, $item->refresh()->base_unit_id);
    }
}
