<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding LOW-25: Item::reorder_point already existed and
 * inventory:check-low-stock already notified users when a tracked item
 * dipped to or below it, but the notification just linked to the Stock
 * report — nothing turned "this needs reordering" into an actual
 * purchase order. The Reorder Suggestions page lists every item+
 * warehouse pair at or below its reorder point with a suggested
 * quantity, and can turn a selected subset straight into a draft PO.
 */
class ReorderSuggestionTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $company = Company::create(['name' => 'Restock Co.', 'slug' => 'restock-'.uniqid()]);

        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_an_item_at_or_below_its_reorder_point_appears_with_a_suggested_quantity(): void
    {
        $owner = $this->makeOwner();
        $warehouse = Warehouse::create(['company_id' => $owner->company_id, 'name' => 'Main', 'is_default' => true]);
        $item = Item::create(['company_id' => $owner->company_id, 'name' => 'Low Widget', 'item_type' => 'product', 'track_inventory' => true, 'reorder_point' => 20, 'unit_price' => 50, 'purchase_price' => 30]);
        ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => 5]);

        // An item well above its reorder point should never appear.
        $fineItem = Item::create(['company_id' => $owner->company_id, 'name' => 'Fine Widget', 'item_type' => 'product', 'track_inventory' => true, 'reorder_point' => 10, 'unit_price' => 50]);
        ItemStock::create(['item_id' => $fineItem->id, 'warehouse_id' => $warehouse->id, 'quantity' => 100]);

        $response = $this->actingAs($owner)->get(route('app.reorder-suggestions.index'));
        $response->assertOk();
        $response->assertSee('Low Widget');
        $response->assertDontSee('Fine Widget');
        // 20 (reorder point) - 5 (on hand) = 15 suggested.
        $response->assertSee('value="15"', false);
    }

    public function test_creating_a_purchase_order_from_selected_suggestions(): void
    {
        $owner = $this->makeOwner();
        $warehouse = Warehouse::create(['company_id' => $owner->company_id, 'name' => 'Main', 'is_default' => true]);
        $supplier = Supplier::create(['company_id' => $owner->company_id, 'name' => 'Restock Supplier']);
        $item = Item::create(['company_id' => $owner->company_id, 'name' => 'Low Widget', 'item_type' => 'product', 'track_inventory' => true, 'reorder_point' => 20, 'unit_price' => 50, 'purchase_price' => 30]);
        ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => 5]);

        $response = $this->actingAs($owner)->post(route('app.reorder-suggestions.store'), [
            'supplier_id' => $supplier->id,
            'items' => [
                ['item_id' => $item->id, 'quantity' => 15, 'unit_price' => 30],
            ],
        ]);

        $order = PurchaseOrder::first();
        $response->assertRedirect(route('app.purchase-orders.show', $order));

        $this->assertNotNull($order);
        $this->assertEquals($supplier->id, $order->supplier_id);
        $this->assertEquals('draft', $order->status);
        $this->assertEquals(1, $order->items()->count());
        $this->assertEquals(15, (float) $order->items->first()->quantity);
        $this->assertEquals(450, (float) $order->total);
    }

    public function test_an_item_that_has_been_replenished_no_longer_appears(): void
    {
        $owner = $this->makeOwner();
        $warehouse = Warehouse::create(['company_id' => $owner->company_id, 'name' => 'Main', 'is_default' => true]);
        $item = Item::create(['company_id' => $owner->company_id, 'name' => 'Replenished Widget', 'item_type' => 'product', 'track_inventory' => true, 'reorder_point' => 20, 'unit_price' => 50]);
        ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => 25]);

        $response = $this->actingAs($owner)->get(route('app.reorder-suggestions.index'));
        $response->assertOk();
        $response->assertSee(__('Nothing needs reordering right now.'));
    }
}
