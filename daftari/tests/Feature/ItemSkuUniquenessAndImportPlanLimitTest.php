<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Item;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Security audit findings M-19 (item SKU had no uniqueness constraint at
 * all, not even per company) and M-05 (Item CSV import completely
 * bypassed the max_items plan limit, unlike Client/Supplier import).
 */
class ItemSkuUniquenessAndImportPlanLimitTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyWithPlan(?int $maxItems): Company
    {
        $plan = Plan::create([
            'name' => 'Test Plan', 'slug' => 'test-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000,
            'is_active' => true, 'max_items' => $maxItems,
        ]);

        $company = Company::create(['name' => 'Acme', 'slug' => 'acme-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
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

    public function test_two_items_in_the_same_company_cannot_share_a_sku(): void
    {
        $company = $this->makeCompanyWithPlan(null);
        $owner = $this->makeOwner($company);
        Item::create(['company_id' => $company->id, 'name' => 'Cement', 'sku' => 'CEM-050', 'unit_price' => 10, 'vat_rate' => 15, 'item_type' => 'physical']);

        $response = $this->actingAs($owner)->post(route('app.items.store'), [
            'name' => 'Cement Duplicate', 'sku' => 'CEM-050', 'unit_price' => 12, 'vat_rate' => 15, 'item_type' => 'physical',
        ]);

        $response->assertSessionHasErrors('sku');
        $this->assertSame(1, Item::where('company_id', $company->id)->where('sku', 'CEM-050')->count());
    }

    public function test_two_items_in_different_companies_can_share_the_same_sku(): void
    {
        $companyA = $this->makeCompanyWithPlan(null);
        $ownerA = $this->makeOwner($companyA);
        $companyB = $this->makeCompanyWithPlan(null);
        Item::create(['company_id' => $companyB->id, 'name' => 'Cement', 'sku' => 'CEM-050', 'unit_price' => 10, 'vat_rate' => 15, 'item_type' => 'physical']);

        $response = $this->actingAs($ownerA)->post(route('app.items.store'), [
            'name' => 'Cement', 'sku' => 'CEM-050', 'unit_price' => 10, 'vat_rate' => 15, 'item_type' => 'physical',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('items', ['company_id' => $companyA->id, 'sku' => 'CEM-050']);
    }

    public function test_multiple_items_can_still_have_no_sku_at_all(): void
    {
        $company = $this->makeCompanyWithPlan(null);
        $owner = $this->makeOwner($company);
        Item::create(['company_id' => $company->id, 'name' => 'Service A', 'unit_price' => 10, 'vat_rate' => 15, 'item_type' => 'service']);

        $response = $this->actingAs($owner)->post(route('app.items.store'), [
            'name' => 'Service B', 'unit_price' => 10, 'vat_rate' => 15, 'item_type' => 'service',
        ]);

        $response->assertSessionDoesntHaveErrors();
    }

    public function test_updating_an_items_own_sku_to_itself_is_not_rejected_as_a_duplicate(): void
    {
        $company = $this->makeCompanyWithPlan(null);
        $owner = $this->makeOwner($company);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Cement', 'sku' => 'CEM-050', 'unit_price' => 10, 'vat_rate' => 15, 'item_type' => 'physical']);

        $response = $this->actingAs($owner)->put(route('app.items.update', $item), [
            'name' => 'Cement Renamed', 'sku' => 'CEM-050', 'unit_price' => 10, 'vat_rate' => 15, 'item_type' => 'physical',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('items', ['id' => $item->id, 'name' => 'Cement Renamed', 'sku' => 'CEM-050']);
    }

    public function test_csv_import_stops_once_the_plan_item_limit_is_reached(): void
    {
        $company = $this->makeCompanyWithPlan(2);
        $owner = $this->makeOwner($company);
        Item::create(['company_id' => $company->id, 'name' => 'Existing', 'unit_price' => 10, 'vat_rate' => 15, 'item_type' => 'physical']);

        $csv = "name,item_type,sku,barcode,category,unit_price,purchase_price,vat_rate\n"
            .'"Item A",physical,,,,10,5,15'."\n"
            .'"Item B",physical,,,,10,5,15'."\n";
        $file = UploadedFile::fake()->createWithContent('items.csv', $csv);

        $response = $this->actingAs($owner)->post(route('app.items.import.store'), ['file' => $file]);

        $response->assertRedirect();
        $this->assertSame(2, Item::where('company_id', $company->id)->count());
    }

    public function test_csv_import_with_no_plan_limit_imports_every_row(): void
    {
        $company = $this->makeCompanyWithPlan(null);
        $owner = $this->makeOwner($company);

        $csv = "name,item_type,sku,barcode,category,unit_price,purchase_price,vat_rate\n"
            .'"Item A",physical,,,,10,5,15'."\n"
            .'"Item B",physical,,,,10,5,15'."\n";
        $file = UploadedFile::fake()->createWithContent('items.csv', $csv);

        $this->actingAs($owner)->post(route('app.items.import.store'), ['file' => $file]);

        $this->assertSame(2, Item::where('company_id', $company->id)->count());
    }

    public function test_csv_import_rejects_a_row_whose_sku_already_exists(): void
    {
        $company = $this->makeCompanyWithPlan(null);
        $owner = $this->makeOwner($company);
        Item::create(['company_id' => $company->id, 'name' => 'Existing', 'sku' => 'DUP-1', 'unit_price' => 10, 'vat_rate' => 15, 'item_type' => 'physical']);

        $csv = "name,item_type,sku,barcode,category,unit_price,purchase_price,vat_rate\n"
            .'"Item A",physical,DUP-1,,,10,5,15'."\n";
        $file = UploadedFile::fake()->createWithContent('items.csv', $csv);

        $this->actingAs($owner)->post(route('app.items.import.store'), ['file' => $file]);

        $this->assertSame(1, Item::where('company_id', $company->id)->where('sku', 'DUP-1')->count());
    }
}
