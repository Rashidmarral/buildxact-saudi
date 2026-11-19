<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Item;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Bug report: after fixing the disabled-unit-picker bug for one company
 * (ItemController::validated() + a one-off backfill migration for
 * whatever items already existed), the exact same symptom reappeared for
 * a different company (Zubaidi) — because that fix only covered the item
 * form and items that existed at the time. ZubaidiProductsSeeder writes
 * ~380 real catalog rows directly via Item::updateOrCreate() and
 * DemoSeeder writes its six starter items via Item::create(), both
 * bypassing ItemController entirely, so both left every row with
 * base_unit_id null — permanently disabling the unit picker on every
 * invoice/quotation/bill/PO line for those items, in any company that
 * catalog belongs to.
 *
 * Item::booted() now guarantees base_unit_id on every create/update
 * instead of leaving it to whichever controller happens to be writing —
 * these tests hit the model directly, the way a seeder does, rather than
 * going through the item form (already covered by UnitDefaultsTest).
 */
class ItemAlwaysGetsABaseUnitTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyWithUnits(): Company
    {
        $company = Company::create(['name' => 'Guard Test Co.', 'slug' => 'guard-'.uniqid()]);
        Unit::seedDefaults($company->id);

        return $company;
    }

    public function test_a_plain_eloquent_create_with_no_base_unit_id_still_gets_one(): void
    {
        $company = $this->makeCompanyWithUnits();

        $item = Item::create([
            'company_id' => $company->id, 'name' => 'Directly Created Item',
            'item_type' => 'service', 'unit_price' => 100, 'vat_rate' => 15,
        ]);

        $pce = Unit::where('company_id', $company->id)->where('code', 'PCE')->first();

        $this->assertNotNull($item->base_unit_id);
        $this->assertSame($pce->id, $item->base_unit_id);
    }

    /**
     * Reproduces ZubaidiProductsSeeder's exact call shape: raw
     * Item::updateOrCreate() with a legacy unit/unit_code pair but no
     * base_unit_id, keyed on (company_id, sku) — both the first insert and
     * a second run that updates the same row must end up with a real unit.
     */
    public function test_update_or_create_like_a_catalog_seeder_still_gets_a_base_unit_on_both_insert_and_update(): void
    {
        $company = $this->makeCompanyWithUnits();

        Item::updateOrCreate(
            ['company_id' => $company->id, 'sku' => 'CAT-1'],
            ['name' => 'Catalog Row', 'unit' => 'Day', 'unit_code' => 'DAY', 'unit_price' => 50, 'item_type' => 'service', 'track_inventory' => false]
        );

        $item = Item::where('company_id', $company->id)->where('sku', 'CAT-1')->first();
        $day = Unit::where('company_id', $company->id)->where('code', 'DAY')->first();

        $this->assertNotNull($item->base_unit_id);
        $this->assertSame($day->id, $item->base_unit_id, 'should match the DAY unit by unit_code, not just fall back to PCE');

        // Re-running the seeder (idempotent update path) must not lose it.
        Item::updateOrCreate(
            ['company_id' => $company->id, 'sku' => 'CAT-1'],
            ['name' => 'Catalog Row', 'unit' => 'Day', 'unit_code' => 'DAY', 'unit_price' => 55, 'item_type' => 'service', 'track_inventory' => false]
        );

        $item->refresh();
        $this->assertSame($day->id, $item->base_unit_id);
        $this->assertSame(55.0, (float) $item->unit_price);
    }

    public function test_an_explicit_base_unit_id_is_never_overridden(): void
    {
        $company = $this->makeCompanyWithUnits();
        $kg = Unit::where('company_id', $company->id)->where('code', 'KGM')->first();

        $item = Item::create([
            'company_id' => $company->id, 'name' => 'Explicit Unit Item',
            'item_type' => 'physical', 'unit_price' => 10, 'vat_rate' => 15,
            'base_unit_id' => $kg->id,
        ]);

        $this->assertSame($kg->id, $item->base_unit_id);
    }

    /**
     * A company created before Units existed (or whose items are seeded
     * directly without the company ever visiting the app first) can have
     * zero Unit rows at all — the guard must seed the default set rather
     * than silently leaving base_unit_id null because there was nothing
     * to point it at.
     */
    public function test_a_company_with_zero_units_gets_the_default_set_seeded_on_first_item_save(): void
    {
        $company = Company::create(['name' => 'No Units Yet Co.', 'slug' => 'no-units-'.uniqid()]);
        $this->assertSame(0, Unit::where('company_id', $company->id)->count());

        $item = Item::create([
            'company_id' => $company->id, 'name' => 'First Item Ever',
            'item_type' => 'service', 'unit_price' => 20, 'vat_rate' => 15,
        ]);

        $this->assertGreaterThan(0, Unit::where('company_id', $company->id)->count());
        $this->assertNotNull($item->base_unit_id);
    }

    /**
     * An item already sitting in the database with base_unit_id null
     * (legacy data from before this fix) self-heals the moment anything
     * updates it again, even if the update itself has nothing to do with
     * units.
     */
    public function test_updating_a_legacy_item_that_still_has_no_base_unit_heals_it(): void
    {
        $company = $this->makeCompanyWithUnits();
        $item = Item::create([
            'company_id' => $company->id, 'name' => 'Will Be Corrupted', 'item_type' => 'service', 'unit_price' => 30, 'vat_rate' => 15,
        ]);
        // Simulate genuinely legacy/corrupt data bypassing the model entirely.
        DB::table('items')->where('id', $item->id)->update(['base_unit_id' => null]);
        $item->refresh();
        $this->assertNull($item->base_unit_id);

        $item->update(['unit_price' => 35]);

        $item->refresh();
        $this->assertNotNull($item->base_unit_id);
    }

    public function test_resolve_default_base_unit_id_returns_null_without_a_company(): void
    {
        $this->assertNull(Item::resolveDefaultBaseUnitId(null, 'EA'));
    }
}
