<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Company;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding: posting a bill capitalized cost to the ledger (this
 * session's earlier COGS fix) but never touched physical stock — the only
 * way ItemStock.quantity ever increased was a disconnected manual Stock
 * Adjustment. Bills now optionally name a receiving warehouse; posting
 * increments stock for tracked-inventory lines, voiding reverses it.
 */
class BillGoodsReceiptTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $company = Company::create(['name' => 'Receiving Co.', 'slug' => 'receiving-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_posting_a_bill_with_a_warehouse_receives_stock_for_tracked_items(): void
    {
        $owner = $this->makeOwner();
        $supplier = Supplier::create(['company_id' => $owner->company_id, 'name' => 'Supplier']);
        $warehouse = Warehouse::create(['company_id' => $owner->company_id, 'name' => 'Main']);
        $item = Item::create(['company_id' => $owner->company_id, 'name' => 'Widget', 'track_inventory' => true, 'purchase_price' => 10]);

        $bill = Bill::create([
            'company_id' => $owner->company_id, 'supplier_id' => $supplier->id, 'warehouse_id' => $warehouse->id,
            'bill_number' => 'BILL-1', 'status' => 'draft', 'bill_date' => now()->toDateString(),
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);
        BillItem::create(['bill_id' => $bill->id, 'item_id' => $item->id, 'description' => 'Widget', 'quantity' => 10, 'unit_price' => 10, 'vat_rate' => 15, 'vat_amount' => 15, 'line_total' => 115]);

        $response = $this->actingAs($owner)->post(route('app.bills.post', $bill));

        $response->assertSessionDoesntHaveErrors();
        $stock = ItemStock::where('item_id', $item->id)->where('warehouse_id', $warehouse->id)->first();
        $this->assertNotNull($stock);
        $this->assertSame(10.0, (float) $stock->quantity);
        $this->assertTrue($bill->fresh()->stock_received);
    }

    public function test_posting_a_bill_with_no_warehouse_never_touches_stock(): void
    {
        $owner = $this->makeOwner();
        $supplier = Supplier::create(['company_id' => $owner->company_id, 'name' => 'Supplier']);
        $item = Item::create(['company_id' => $owner->company_id, 'name' => 'Widget', 'track_inventory' => true, 'purchase_price' => 10]);

        $bill = Bill::create([
            'company_id' => $owner->company_id, 'supplier_id' => $supplier->id,
            'bill_number' => 'BILL-2', 'status' => 'draft', 'bill_date' => now()->toDateString(),
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);
        BillItem::create(['bill_id' => $bill->id, 'item_id' => $item->id, 'description' => 'Widget', 'quantity' => 10, 'unit_price' => 10, 'vat_rate' => 15, 'vat_amount' => 15, 'line_total' => 115]);

        $this->actingAs($owner)->post(route('app.bills.post', $bill));

        $this->assertSame(0, ItemStock::where('item_id', $item->id)->count());
        $this->assertFalse($bill->fresh()->stock_received);
    }

    public function test_voiding_a_received_bill_reverses_the_stock(): void
    {
        $owner = $this->makeOwner();
        $supplier = Supplier::create(['company_id' => $owner->company_id, 'name' => 'Supplier']);
        $warehouse = Warehouse::create(['company_id' => $owner->company_id, 'name' => 'Main']);
        $item = Item::create(['company_id' => $owner->company_id, 'name' => 'Widget', 'track_inventory' => true, 'purchase_price' => 10]);

        $bill = Bill::create([
            'company_id' => $owner->company_id, 'supplier_id' => $supplier->id, 'warehouse_id' => $warehouse->id,
            'bill_number' => 'BILL-3', 'status' => 'draft', 'bill_date' => now()->toDateString(),
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);
        BillItem::create(['bill_id' => $bill->id, 'item_id' => $item->id, 'description' => 'Widget', 'quantity' => 10, 'unit_price' => 10, 'vat_rate' => 15, 'vat_amount' => 15, 'line_total' => 115]);

        $this->actingAs($owner)->post(route('app.bills.post', $bill));
        $this->actingAs($owner)->post(route('app.bills.void', $bill));

        $stock = ItemStock::where('item_id', $item->id)->where('warehouse_id', $warehouse->id)->first();
        $this->assertSame(0.0, (float) $stock->quantity);
        $this->assertFalse($bill->fresh()->stock_received);
        $this->assertSame('void', $bill->fresh()->status);
    }
}
