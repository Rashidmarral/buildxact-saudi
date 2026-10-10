<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding MEDIUM-21: a stock transfer used to move quantity from the
 * source warehouse straight into the destination warehouse's stock in one
 * atomic step — real transfers between physically distant warehouses take
 * time in between, during which the stock is neither sellable from the
 * source (it's already left) nor from the destination (it hasn't arrived).
 * A transfer now starts 'in_transit' (deducted from source only) and only
 * lands in the destination warehouse once receive() confirms it arrived.
 */
class StockTransferInTransitTest extends TestCase
{
    use RefreshDatabase;

    private function makeSetup(float $stock = 100): array
    {
        $company = Company::create(['name' => 'Transfer Co.', 'slug' => 'transfer-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $warehouseA = Warehouse::create(['company_id' => $company->id, 'name' => 'Warehouse A', 'is_default' => true]);
        $warehouseB = Warehouse::create(['company_id' => $company->id, 'name' => 'Warehouse B']);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Widget', 'type' => 'product', 'track_inventory' => true, 'sale_price' => 10]);
        ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $warehouseA->id, 'quantity' => $stock]);

        return [$owner, $item, $warehouseA, $warehouseB];
    }

    public function test_creating_a_transfer_deducts_the_source_immediately_but_does_not_yet_credit_the_destination(): void
    {
        [$owner, $item, $warehouseA, $warehouseB] = $this->makeSetup(100);

        $response = $this->actingAs($owner)->post(route('app.stock-transfers.store'), [
            'item_id' => $item->id, 'from_warehouse_id' => $warehouseA->id, 'to_warehouse_id' => $warehouseB->id, 'quantity' => 30,
        ]);
        $response->assertRedirect(route('app.stock-transfers.index'));

        $transfer = StockTransfer::first();
        $this->assertEquals('in_transit', $transfer->status);
        $this->assertNull($transfer->received_at);

        $this->assertEquals(70, ItemStock::where('item_id', $item->id)->where('warehouse_id', $warehouseA->id)->value('quantity'));
        $this->assertNull(ItemStock::where('item_id', $item->id)->where('warehouse_id', $warehouseB->id)->value('quantity'));
    }

    public function test_receiving_a_transfer_credits_the_destination_and_marks_it_completed(): void
    {
        [$owner, $item, $warehouseA, $warehouseB] = $this->makeSetup(100);

        $this->actingAs($owner)->post(route('app.stock-transfers.store'), [
            'item_id' => $item->id, 'from_warehouse_id' => $warehouseA->id, 'to_warehouse_id' => $warehouseB->id, 'quantity' => 30,
        ]);
        $transfer = StockTransfer::first();

        $response = $this->post(route('app.stock-transfers.receive', $transfer));
        $response->assertRedirect();

        $transfer->refresh();
        $this->assertEquals('completed', $transfer->status);
        $this->assertNotNull($transfer->received_at);
        $this->assertEquals($owner->id, $transfer->received_by);

        $this->assertEquals(70, ItemStock::where('item_id', $item->id)->where('warehouse_id', $warehouseA->id)->value('quantity'));
        $this->assertEquals(30, ItemStock::where('item_id', $item->id)->where('warehouse_id', $warehouseB->id)->value('quantity'));
    }

    public function test_reversing_an_in_transit_transfer_only_restores_the_source_and_never_touches_the_destination(): void
    {
        [$owner, $item, $warehouseA, $warehouseB] = $this->makeSetup(100);

        $this->actingAs($owner)->post(route('app.stock-transfers.store'), [
            'item_id' => $item->id, 'from_warehouse_id' => $warehouseA->id, 'to_warehouse_id' => $warehouseB->id, 'quantity' => 30,
        ]);
        $transfer = StockTransfer::first();

        $this->post(route('app.stock-transfers.reverse', $transfer));

        $transfer->refresh();
        $this->assertEquals('reversed', $transfer->status);
        $this->assertEquals(100, ItemStock::where('item_id', $item->id)->where('warehouse_id', $warehouseA->id)->value('quantity'));
        $this->assertNull(ItemStock::where('item_id', $item->id)->where('warehouse_id', $warehouseB->id)->value('quantity'));
    }

    public function test_reversing_a_completed_transfer_undoes_both_legs(): void
    {
        [$owner, $item, $warehouseA, $warehouseB] = $this->makeSetup(100);

        $this->actingAs($owner)->post(route('app.stock-transfers.store'), [
            'item_id' => $item->id, 'from_warehouse_id' => $warehouseA->id, 'to_warehouse_id' => $warehouseB->id, 'quantity' => 30,
        ]);
        $transfer = StockTransfer::first();
        $this->post(route('app.stock-transfers.receive', $transfer));

        $this->post(route('app.stock-transfers.reverse', $transfer));

        $transfer->refresh();
        $this->assertEquals('reversed', $transfer->status);
        $this->assertEquals(100, ItemStock::where('item_id', $item->id)->where('warehouse_id', $warehouseA->id)->value('quantity'));
        $this->assertEquals(0, ItemStock::where('item_id', $item->id)->where('warehouse_id', $warehouseB->id)->value('quantity'));
    }

    public function test_a_reversed_or_completed_transfer_cannot_be_received_again(): void
    {
        [$owner, $item, $warehouseA, $warehouseB] = $this->makeSetup(100);

        $this->actingAs($owner)->post(route('app.stock-transfers.store'), [
            'item_id' => $item->id, 'from_warehouse_id' => $warehouseA->id, 'to_warehouse_id' => $warehouseB->id, 'quantity' => 30,
        ]);
        $transfer = StockTransfer::first();
        $this->post(route('app.stock-transfers.receive', $transfer));

        $this->post(route('app.stock-transfers.receive', $transfer));

        $this->assertEquals(30, ItemStock::where('item_id', $item->id)->where('warehouse_id', $warehouseB->id)->value('quantity'));
    }
}
