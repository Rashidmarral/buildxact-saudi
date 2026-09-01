<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Item;
use App\Models\Plan;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A brand-new company has zero units until Unit::seedDefaults() runs
 * (wired into signup — AuthController::register()), and the unit picker
 * on invoice/quotation/bill/purchase-order forms only enables once the
 * selected item has at least one unit — so with no units at all it stays
 * permanently disabled no matter what the user does.
 */
class UnitDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_defaults_creates_a_standard_unit_set_with_zatca_codes(): void
    {
        $company = Company::create(['name' => 'Unit Test Co.', 'slug' => 'unit-test-'.uniqid()]);

        Unit::seedDefaults($company->id);

        $this->assertTrue(Unit::where('company_id', $company->id)->where('code', 'PCE')->exists());
        $this->assertTrue(Unit::where('company_id', $company->id)->where('code', 'KGM')->exists());
        $this->assertGreaterThanOrEqual(10, Unit::where('company_id', $company->id)->count());
    }

    public function test_seed_defaults_is_idempotent_and_does_not_duplicate_on_a_second_call(): void
    {
        $company = Company::create(['name' => 'Unit Test Co.', 'slug' => 'unit-test-'.uniqid()]);

        Unit::seedDefaults($company->id);
        $countAfterFirst = Unit::where('company_id', $company->id)->count();

        Unit::seedDefaults($company->id);
        $countAfterSecond = Unit::where('company_id', $company->id)->count();

        $this->assertSame($countAfterFirst, $countAfterSecond);
    }

    public function test_a_new_signup_gets_default_units_so_the_unit_picker_is_never_permanently_disabled(): void
    {
        Plan::create([
            'name' => 'Starter', 'slug' => 'starter-'.uniqid(),
            'price_monthly' => 99, 'price_yearly' => 990, 'is_active' => true, 'sort_order' => 1,
        ]);

        $this->post(route('register'), [
            'first_name' => 'Test', 'last_name' => 'Owner',
            'email' => 'unit-signup-'.uniqid().'@example.test',
            'phone' => '0512345678',
            'job_title' => 'owner_ceo',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'company_name' => 'Signup Unit Co.',
            'organization_size' => '1-10', 'industry' => 'retail_trading',
            'primary_customer_type' => 'b2c',
        ]);

        $company = Company::where('name', 'Signup Unit Co.')->first();

        $this->assertNotNull($company);
        $this->assertGreaterThan(0, Unit::where('company_id', $company->id)->count());
    }

    /**
     * The item form's base-unit dropdown ships with a first option reading
     * "None (defaults to Piece / PCE)" — that promise only holds if
     * something actually assigns Piece when the field is left blank.
     * Before this fix, leaving it on "None" (the default state) silently
     * saved base_unit_id as null, so the item's unit picker on invoice/
     * quotation/bill/PO forms stayed permanently disabled even though the
     * company had units configured.
     */
    public function test_creating_an_item_without_picking_a_base_unit_defaults_to_the_companys_piece_unit(): void
    {
        $company = Company::create(['name' => 'Item Default Co.', 'slug' => 'item-default-'.uniqid()]);
        Unit::seedDefaults($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $response = $this->actingAs($owner)->post(route('app.items.store'), [
            'name' => 'No Unit Chosen Item',
            'item_type' => 'physical',
            'unit_price' => 50,
            'vat_rate' => 15,
            // base_unit_id intentionally omitted, matching the form's "None" default
        ]);

        $response->assertRedirect(route('app.items.index'));

        $item = Item::where('company_id', $company->id)->where('name', 'No Unit Chosen Item')->first();
        $pce = Unit::where('company_id', $company->id)->where('code', 'PCE')->first();

        $this->assertNotNull($item);
        $this->assertNotNull($item->base_unit_id);
        $this->assertSame($pce->id, $item->base_unit_id);
    }

    public function test_items_without_a_base_unit_can_be_backfilled_from_a_legacy_unit_code(): void
    {
        $company = Company::create(['name' => 'Backfill Co.', 'slug' => 'backfill-'.uniqid()]);
        Unit::seedDefaults($company->id);

        $item = Item::create([
            'company_id' => $company->id, 'name' => 'Legacy Item',
            'unit_code' => 'KGM', 'base_unit_id' => null,
        ]);

        $matched = Unit::where('company_id', $company->id)
            ->whereRaw('UPPER(code) = ?', [strtoupper($item->unit_code)])
            ->first();

        $this->assertNotNull($matched);
        $this->assertSame('KGM', $matched->code);

        $item->update(['base_unit_id' => $matched->id]);
        $item->refresh();

        $this->assertSame($matched->id, $item->base_unit_id);
        $this->assertNotNull($item->baseUnit);
    }
}
