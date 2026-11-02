<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Item;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "barcode/SKU settings (prefix, auto-generate, length)" — a Hamly
 * settings gap. Manual SKU/barcode entry keeps working unconditionally;
 * these settings only change what happens when the SKU field is left
 * blank on a new item (ItemController::generateSku()) and how many
 * digits the "Generate" barcode button hands out
 * (ItemController::generateBarcode()).
 */
class BarcodeSkuSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $plan = Plan::create([
            'name' => 'Test Plan', 'slug' => 'test-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true,
        ]);
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Unit::seedDefaults($company->id);
        Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['company_id' => $company->id, 'role' => 'owner', 'status' => 'active']);
    }

    public function test_a_company_defaults_to_manual_sku_entry_and_a_12_digit_barcode(): void
    {
        $company = $this->makeCompany()->fresh();

        $this->assertFalse($company->sku_auto_generate);
        $this->assertSame(12, $company->barcode_length);
    }

    public function test_an_owner_can_save_barcode_and_sku_settings(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->post(route('app.settings.barcode-sku.update'), [
            'sku_prefix' => 'ITM-',
            'sku_auto_generate' => '1',
            'barcode_length' => '8',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $company->refresh();
        $this->assertSame('ITM-', $company->sku_prefix);
        $this->assertTrue($company->sku_auto_generate);
        $this->assertSame(8, $company->barcode_length);
    }

    public function test_an_out_of_range_barcode_length_is_rejected(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->post(route('app.settings.barcode-sku.update'), [
            'barcode_length' => '30',
        ]);

        $response->assertSessionHasErrors('barcode_length');
    }

    public function test_leaving_sku_blank_on_a_new_item_keeps_it_blank_when_auto_generate_is_off(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->post(route('app.items.store'), [
            'name' => 'Rebar 12mm', 'item_type' => 'physical', 'unit_price' => 20, 'vat_rate' => 15,
        ])->assertSessionDoesntHaveErrors();

        $this->assertNull(Item::where('name', 'Rebar 12mm')->first()->sku);
    }

    public function test_leaving_sku_blank_auto_generates_one_with_the_configured_prefix(): void
    {
        $company = $this->makeCompany();
        $company->update(['sku_auto_generate' => true, 'sku_prefix' => 'ITM-']);
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->post(route('app.items.store'), [
            'name' => 'Rebar 12mm', 'item_type' => 'physical', 'unit_price' => 20, 'vat_rate' => 15,
        ])->assertSessionDoesntHaveErrors();

        $item = Item::where('name', 'Rebar 12mm')->first();
        $this->assertSame('ITM-00001', $item->sku);
    }

    public function test_auto_generated_skus_increment_and_never_collide(): void
    {
        $company = $this->makeCompany();
        $company->update(['sku_auto_generate' => true, 'sku_prefix' => 'ITM-']);
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->post(route('app.items.store'), ['name' => 'A', 'item_type' => 'physical', 'unit_price' => 1, 'vat_rate' => 15]);
        $this->actingAs($owner)->post(route('app.items.store'), ['name' => 'B', 'item_type' => 'physical', 'unit_price' => 1, 'vat_rate' => 15]);

        $skus = Item::orderBy('id')->pluck('sku')->all();
        $this->assertSame(['ITM-00001', 'ITM-00002'], $skus);
    }

    public function test_an_explicitly_typed_sku_is_never_overridden_by_auto_generation(): void
    {
        $company = $this->makeCompany();
        $company->update(['sku_auto_generate' => true, 'sku_prefix' => 'ITM-']);
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->post(route('app.items.store'), [
            'name' => 'Rebar 12mm', 'item_type' => 'physical', 'unit_price' => 20, 'vat_rate' => 15, 'sku' => 'MY-OWN-SKU',
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame('MY-OWN-SKU', Item::where('name', 'Rebar 12mm')->first()->sku);
    }

    public function test_the_generate_barcode_button_respects_the_configured_length(): void
    {
        $company = $this->makeCompany();
        $company->update(['barcode_length' => 8]);
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->get(route('app.items.generate-barcode'));

        $response->assertOk();
        $this->assertSame(8, strlen($response->json('barcode')));
    }

    public function test_the_generate_barcode_button_still_defaults_to_12_digits(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->get(route('app.items.generate-barcode'));

        $response->assertOk();
        $this->assertSame(12, strlen($response->json('barcode')));
    }
}
