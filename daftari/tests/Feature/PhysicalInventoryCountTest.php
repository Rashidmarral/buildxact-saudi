<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding LOW-27: reconciling a physical stock count against the
 * system meant opening the single-item Inventory Adjustment modal once per
 * item. The Physical Count page lists every tracked item in a warehouse
 * with its system quantity, lets the user key in what was actually
 * counted, and on submit records one StockAdjustment per item whose count
 * differs from the system — reusing the same posting path as the
 * single-item flow.
 */
class PhysicalInventoryCountTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Count Co.', 'slug' => 'count-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_the_index_page_shows_every_tracked_item_with_its_system_quantity(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $warehouse = Warehouse::create(['company_id' => $company->id, 'name' => 'Main']);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Widget', 'track_inventory' => true, 'unit_price' => 10]);
        ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => 42]);

        $untracked = Item::create(['company_id' => $company->id, 'name' => 'Consulting', 'track_inventory' => false]);

        $response = $this->actingAs($owner)->get(route('app.physical-counts.index', ['warehouse_id' => $warehouse->id]));
        $response->assertOk();
        $response->assertSee('Widget');
        $response->assertSee('value="42"', false);
        $response->assertDontSee('Consulting');
    }

    public function test_submitting_a_count_adjusts_only_items_whose_count_differs(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $warehouse = Warehouse::create(['company_id' => $company->id, 'name' => 'Main']);

        $short = Item::create(['company_id' => $company->id, 'name' => 'Short Item', 'track_inventory' => true, 'unit_price' => 10]);
        ItemStock::create(['item_id' => $short->id, 'warehouse_id' => $warehouse->id, 'quantity' => 50]);

        $over = Item::create(['company_id' => $company->id, 'name' => 'Over Item', 'track_inventory' => true, 'unit_price' => 5]);
        ItemStock::create(['item_id' => $over->id, 'warehouse_id' => $warehouse->id, 'quantity' => 10]);

        $exact = Item::create(['company_id' => $company->id, 'name' => 'Exact Item', 'track_inventory' => true, 'unit_price' => 8]);
        ItemStock::create(['item_id' => $exact->id, 'warehouse_id' => $warehouse->id, 'quantity' => 30]);

        $response = $this->actingAs($owner)->post(route('app.physical-counts.store'), [
            'warehouse_id' => $warehouse->id,
            'items' => [
                ['item_id' => $short->id, 'counted_quantity' => 45],
                ['item_id' => $over->id, 'counted_quantity' => 15],
                ['item_id' => $exact->id, 'counted_quantity' => 30],
            ],
        ]);

        $response->assertRedirect(route('app.physical-counts.index', ['warehouse_id' => $warehouse->id]));

        $this->assertEquals(45, (float) ItemStock::where('item_id', $short->id)->value('quantity'));
        $this->assertEquals(15, (float) ItemStock::where('item_id', $over->id)->value('quantity'));
        $this->assertEquals(30, (float) ItemStock::where('item_id', $exact->id)->value('quantity'));

        $this->assertEquals(2, StockAdjustment::count());
        $this->assertEquals('decrease', StockAdjustment::where('item_id', $short->id)->first()->type);
        $this->assertEquals(5, (float) StockAdjustment::where('item_id', $short->id)->first()->quantity);
        $this->assertEquals('increase', StockAdjustment::where('item_id', $over->id)->first()->type);
        $this->assertEquals(5, (float) StockAdjustment::where('item_id', $over->id)->first()->quantity);
        $this->assertNull(StockAdjustment::where('item_id', $exact->id)->first());
    }
}
