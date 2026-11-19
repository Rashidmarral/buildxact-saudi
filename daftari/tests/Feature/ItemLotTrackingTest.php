<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Item;
use App\Models\ItemLot;
use App\Models\ItemStock;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding LOW-23: items only ever had a single, item-level
 * expiry_date and no way to tell one physical batch or serial-numbered
 * unit apart from another — every unit of an item in a warehouse was
 * fungible. Item::tracking_type ('lot' or 'serial') now opts an item in;
 * receiving/consuming an ItemLot keeps ItemStock in sync rather than the
 * lot table becoming a second, disconnected stock figure.
 */
class ItemLotTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function makeSetup(string $trackingType = 'lot'): array
    {
        $company = Company::create(['name' => 'Lot Co.', 'slug' => 'lot-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $warehouse = Warehouse::create(['company_id' => $company->id, 'name' => 'Main', 'is_default' => true]);
        $item = Item::create([
            'company_id' => $company->id, 'name' => 'Vaccine Vial', 'item_type' => 'product',
            'track_inventory' => true, 'tracking_type' => $trackingType, 'unit_price' => 20,
        ]);

        return [$owner, $item, $warehouse];
    }

    public function test_receiving_a_lot_creates_the_record_and_increments_item_stock(): void
    {
        [$owner, $item, $warehouse] = $this->makeSetup('lot');

        $response = $this->actingAs($owner)->post(route('app.item-lots.store'), [
            'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'lot_number' => 'LOT-100',
            'quantity' => 50, 'expiry_date' => now()->addMonths(6)->toDateString(),
        ]);
        $response->assertRedirect();

        $lot = ItemLot::first();
        $this->assertEquals('LOT-100', $lot->lot_number);
        $this->assertEquals(50, $lot->quantity);
        $this->assertEquals($owner->id, $lot->received_by);
        $this->assertEquals(50, ItemStock::where('item_id', $item->id)->where('warehouse_id', $warehouse->id)->value('quantity'));
    }

    public function test_a_serial_tracked_item_requires_a_serial_number_and_locks_quantity_to_one(): void
    {
        [$owner, $item, $warehouse] = $this->makeSetup('serial');

        $missingSerial = $this->actingAs($owner)->post(route('app.item-lots.store'), [
            'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'lot_number' => 'SN-BATCH', 'quantity' => 1,
        ]);
        $missingSerial->assertSessionHasErrors('serial_number');

        $wrongQuantity = $this->actingAs($owner)->post(route('app.item-lots.store'), [
            'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'lot_number' => 'SN-BATCH',
            'serial_number' => 'SN-001', 'quantity' => 5,
        ]);
        $wrongQuantity->assertSessionHasErrors('quantity');

        $ok = $this->actingAs($owner)->post(route('app.item-lots.store'), [
            'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'lot_number' => 'SN-BATCH',
            'serial_number' => 'SN-001', 'quantity' => 1,
        ]);
        $ok->assertRedirect();
        $this->assertEquals(1, ItemLot::count());
        $this->assertEquals('SN-001', ItemLot::first()->serial_number);
    }

    public function test_receiving_a_lot_for_an_untracked_item_is_rejected(): void
    {
        $company = Company::create(['name' => 'Plain Co.', 'slug' => 'plain-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $warehouse = Warehouse::create(['company_id' => $company->id, 'name' => 'Main', 'is_default' => true]);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Plain Widget', 'item_type' => 'product', 'track_inventory' => true, 'tracking_type' => 'none', 'unit_price' => 10]);

        $response = $this->actingAs($owner)->post(route('app.item-lots.store'), [
            'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'lot_number' => 'LOT-1', 'quantity' => 5,
        ]);

        $response->assertSessionHasErrors('item_id');
        $this->assertEquals(0, ItemLot::count());
    }

    public function test_consuming_a_lot_decrements_both_the_lot_and_item_stock(): void
    {
        [$owner, $item, $warehouse] = $this->makeSetup('lot');

        $this->actingAs($owner)->post(route('app.item-lots.store'), [
            'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'lot_number' => 'LOT-100', 'quantity' => 50,
        ]);
        $lot = ItemLot::first();

        $response = $this->post(route('app.item-lots.consume', $lot), ['quantity' => 20]);
        $response->assertRedirect();

        $lot->refresh();
        $this->assertEquals(30, $lot->quantity);
        $this->assertEquals(30, ItemStock::where('item_id', $item->id)->where('warehouse_id', $warehouse->id)->value('quantity'));
    }

    public function test_consuming_more_than_the_lot_holds_is_rejected(): void
    {
        [$owner, $item, $warehouse] = $this->makeSetup('lot');

        $this->actingAs($owner)->post(route('app.item-lots.store'), [
            'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'lot_number' => 'LOT-100', 'quantity' => 10,
        ]);
        $lot = ItemLot::first();

        $response = $this->post(route('app.item-lots.consume', $lot), ['quantity' => 15]);
        $response->assertSessionHasErrors('quantity');

        $this->assertEquals(10, $lot->fresh()->quantity);
    }

    public function test_expiring_soon_and_expired_are_flagged_correctly(): void
    {
        [, $item, $warehouse] = $this->makeSetup('lot');

        $expired = ItemLot::create(['company_id' => $item->company_id, 'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'lot_number' => 'OLD', 'quantity' => 5, 'expiry_date' => now()->subDay()]);
        $soon = ItemLot::create(['company_id' => $item->company_id, 'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'lot_number' => 'SOON', 'quantity' => 5, 'expiry_date' => now()->addDays(10)]);
        $fine = ItemLot::create(['company_id' => $item->company_id, 'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'lot_number' => 'FINE', 'quantity' => 5, 'expiry_date' => now()->addYear()]);

        $this->assertTrue($expired->isExpired());
        $this->assertFalse($expired->isExpiringSoon());

        $this->assertFalse($soon->isExpired());
        $this->assertTrue($soon->isExpiringSoon());

        $this->assertFalse($fine->isExpired());
        $this->assertFalse($fine->isExpiringSoon());
    }
}
