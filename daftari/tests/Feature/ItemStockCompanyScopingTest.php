<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Security audit finding M-06: item_stocks had no company_id of its own —
 * safe today only because every caller pre-scopes item_id/warehouse_id
 * through already company-scoped lookups first. This adds the column as
 * defense-in-depth against a future direct/unscoped query.
 */
class ItemStockCompanyScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_id_is_derived_from_the_item_even_with_no_authenticated_user(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        $warehouse = Warehouse::create(['company_id' => $company->id, 'name' => 'Main']);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Widget', 'unit_price' => 10, 'vat_rate' => 15, 'item_type' => 'physical']);

        // No actingAs() here — exactly the "seeded before authenticating"
        // pattern several existing tests already use.
        $stock = ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => 50]);

        $this->assertSame($company->id, $stock->company_id);
    }

    public function test_a_company_cannot_see_another_companys_item_stock_row(): void
    {
        $companyA = Company::create(['name' => 'A', 'slug' => 'a-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        $warehouseA = Warehouse::create(['company_id' => $companyA->id, 'name' => 'Main A']);
        $itemA = Item::create(['company_id' => $companyA->id, 'name' => 'Widget A', 'unit_price' => 10, 'vat_rate' => 15, 'item_type' => 'physical']);
        ItemStock::create(['item_id' => $itemA->id, 'warehouse_id' => $warehouseA->id, 'quantity' => 50]);

        $companyB = Company::create(['name' => 'B', 'slug' => 'b-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        $owner = \App\Models\User::factory()->create(['company_id' => $companyB->id, 'role' => 'owner', 'status' => 'active']);

        $this->actingAs($owner);
        $this->assertSame(0, ItemStock::count());
        $this->assertSame(1, ItemStock::withoutGlobalScopes()->count());
    }
}
