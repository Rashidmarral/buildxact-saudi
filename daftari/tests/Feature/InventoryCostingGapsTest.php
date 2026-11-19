<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\JournalEntry;
use App\Models\PosRegister;
use App\Models\PosSale;
use App\Models\PosShift;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\LedgerPostingService;
use App\Services\Pos\PosSaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit findings INV-01 through INV-04 (inventory costing correctness).
 */
class InventoryCostingGapsTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Costing Gaps Co.', 'slug' => 'costing-gaps-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    // --- INV-01: stock adjustments must value at cost, not selling price ---

    public function test_a_stock_increase_is_valued_at_cost_not_the_selling_price(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $warehouse = Warehouse::create(['company_id' => $company->id, 'name' => 'Main']);
        // Sells for 100, costs 40 — a wide margin that would make the old
        // selling-price valuation obviously wrong if it were still used.
        $item = Item::create(['company_id' => $company->id, 'name' => 'Widget', 'track_inventory' => true, 'unit_price' => 100, 'purchase_price' => 40]);

        $this->actingAs($owner)->post(route('app.stock-adjustments.store'), [
            'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'type' => 'increase', 'quantity' => 10,
        ])->assertRedirect();

        $inventoryAsset = Account::where('company_id', $company->id)->where('code', '1400')->first();
        $entry = JournalEntry::where('company_id', $company->id)->where('source_type', 'stock_adjustment')->first();

        $this->assertNotNull($entry);
        // 10 units * purchase_price 40 = 400, not 10 * unit_price 100 = 1000.
        $this->assertSame(400.0, (float) $entry->lines()->where('account_id', $inventoryAsset->id)->sum('debit'));
    }

    // --- INV-02: POS sales must post COGS for tracked-inventory lines ---

    public function test_a_pos_sale_of_a_tracked_item_posts_cogs_and_relieves_inventory(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $warehouse = Warehouse::create(['company_id' => $company->id, 'name' => 'Main']);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Widget', 'track_inventory' => true, 'unit_price' => 100, 'purchase_price' => 40, 'vat_rate' => 0]);
        ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => 50]);
        $register = PosRegister::create(['company_id' => $company->id, 'name' => 'Till 1', 'warehouse_id' => $warehouse->id, 'is_active' => true]);
        $shift = PosShift::create(['company_id' => $company->id, 'register_id' => $register->id, 'opened_by' => $owner->id, 'opening_cash' => 0, 'opened_at' => now(), 'status' => 'open']);

        $this->actingAs($owner);
        $sale = app(PosSaleService::class)->checkout(
            $shift,
            [['item_id' => $item->id, 'quantity' => 5, 'unit_price' => 100]],
            [['method' => 'cash', 'amount' => 500]],
            null,
            app(LedgerPostingService::class)
        );

        $cogs = Account::where('company_id', $company->id)->where('code', '5000')->first();
        $inventoryAsset = Account::where('company_id', $company->id)->where('code', '1400')->first();
        $entry = JournalEntry::where('company_id', $company->id)->where('source_type', 'pos_sale')->where('source_id', $sale->id)->first();

        $this->assertNotNull($entry);
        // 5 units * purchase_price 40 = 200.
        $this->assertSame(200.0, (float) $entry->lines()->where('account_id', $cogs->id)->sum('debit'));
        $this->assertSame(200.0, (float) $entry->lines()->where('account_id', $inventoryAsset->id)->sum('credit'));
        $this->assertEquals(45, (float) ItemStock::where('item_id', $item->id)->where('warehouse_id', $warehouse->id)->value('quantity'));
    }

    public function test_a_pos_sale_with_no_register_warehouse_posts_no_cogs(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Widget', 'track_inventory' => true, 'unit_price' => 100, 'purchase_price' => 40, 'vat_rate' => 0]);
        $register = PosRegister::create(['company_id' => $company->id, 'name' => 'Till 1', 'warehouse_id' => null, 'is_active' => true]);
        $shift = PosShift::create(['company_id' => $company->id, 'register_id' => $register->id, 'opened_by' => $owner->id, 'opening_cash' => 0, 'opened_at' => now(), 'status' => 'open']);

        $this->actingAs($owner);
        $sale = app(PosSaleService::class)->checkout(
            $shift,
            [['item_id' => $item->id, 'quantity' => 5, 'unit_price' => 100]],
            [['method' => 'cash', 'amount' => 500]],
            null,
            app(LedgerPostingService::class)
        );

        $cogs = Account::where('company_id', $company->id)->where('code', '5000')->first();
        $entry = JournalEntry::where('company_id', $company->id)->where('source_type', 'pos_sale')->where('source_id', $sale->id)->first();

        $this->assertNotNull($entry);
        $this->assertSame(0.0, (float) $entry->lines()->where('account_id', $cogs->id)->sum('debit'));
    }

    // --- INV-03: purchase returns must reverse the stock the original bill received ---

    public function test_issuing_a_purchase_return_reverses_the_stock_the_bill_received(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $warehouse = Warehouse::create(['company_id' => $company->id, 'name' => 'Main']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Supplier']);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Widget', 'track_inventory' => true, 'purchase_price' => 40]);

        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-1',
            'status' => 'posted', 'warehouse_id' => $warehouse->id, 'stock_received' => true,
            'bill_date' => now()->toDateString(), 'currency' => $company->currency,
            'subtotal' => 1000, 'vat_total' => 0, 'total' => 1000,
        ]);
        $billItem = BillItem::create(['bill_id' => $bill->id, 'item_id' => $item->id, 'description' => 'Widget', 'quantity' => 10, 'unit_price' => 100, 'vat_rate' => 0, 'vat_amount' => 0, 'line_total' => 1000]);
        ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => 10]);

        $this->actingAs($owner)->post(route('app.purchase-returns.store'), [
            'bill_id' => $bill->id, 'issue_date' => now()->toDateString(),
            'items' => [['bill_item_id' => $billItem->id, 'description' => 'Widget', 'quantity' => 4, 'unit_price' => 100, 'vat_rate' => 0]],
        ])->assertRedirect();

        $this->assertEquals(6, (float) ItemStock::where('item_id', $item->id)->where('warehouse_id', $warehouse->id)->value('quantity'));

        $purchaseReturn = \App\Models\PurchaseReturn::first();
        $this->actingAs($owner)->post(route('app.purchase-returns.void', $purchaseReturn))->assertRedirect();

        $this->assertEquals(10, (float) ItemStock::where('item_id', $item->id)->where('warehouse_id', $warehouse->id)->value('quantity'), 'Voiding the return must give the stock back.');
    }

    public function test_a_purchase_return_against_a_bill_that_never_received_stock_touches_nothing(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Supplier']);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Widget', 'track_inventory' => true, 'purchase_price' => 40]);

        // No warehouse_id, so the bill never received stock.
        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-1',
            'status' => 'posted', 'bill_date' => now()->toDateString(), 'currency' => $company->currency,
            'subtotal' => 1000, 'vat_total' => 0, 'total' => 1000,
        ]);
        $billItem = BillItem::create(['bill_id' => $bill->id, 'item_id' => $item->id, 'description' => 'Widget', 'quantity' => 10, 'unit_price' => 100, 'vat_rate' => 0, 'vat_amount' => 0, 'line_total' => 1000]);

        $this->actingAs($owner)->post(route('app.purchase-returns.store'), [
            'bill_id' => $bill->id, 'issue_date' => now()->toDateString(),
            'items' => [['bill_item_id' => $billItem->id, 'description' => 'Widget', 'quantity' => 4, 'unit_price' => 100, 'vat_rate' => 0]],
        ])->assertRedirect();

        $this->assertSame(0, ItemStock::count());
    }

    // --- INV-04: profitability report must use the cost at the time of sale ---

    public function test_the_profitability_report_uses_the_cost_frozen_at_sale_time_not_todays_cost(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client']);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Widget', 'track_inventory' => true, 'unit_price' => 100, 'purchase_price' => 40]);

        $this->actingAs($owner)->post(route('app.invoices.store'), [
            'client_id' => $client->id, 'type' => 'standard', 'issue_date' => now()->toDateString(),
            'items' => [['item_id' => $item->id, 'description' => 'Widget', 'quantity' => 10, 'unit_price' => 100, 'vat_rate' => 0]],
        ]);
        $invoice = Invoice::first();
        $line = $invoice->items()->first();

        $this->assertEqualsWithDelta(40.0, (float) $line->unit_cost, 0.0001, 'The line must freeze the cost at creation time.');

        // store() leaves a new invoice as a draft, which the profitability
        // report (like every other revenue report) excludes — send it so
        // it's a real, counted sale.
        $this->actingAs($owner)->post(route('app.invoices.send', $invoice));

        // The item's cost rises after the sale — the report for that past
        // sale must still show the margin as it actually was, not today's.
        $item->update(['purchase_price' => 90]);

        $response = $this->actingAs($owner)->get(route('app.inventory.profitability', [
            'period' => 'custom', 'from' => now()->subDay()->toDateString(), 'to' => now()->addDay()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertViewHas('totals', function ($totals) {
            // 10 * 40 = 400 cost, 1000 revenue, 600 margin — using the
            // frozen cost, not 10 * 90 = 900 (which would give only 100).
            return abs($totals['cost'] - 400.0) < 0.01 && abs($totals['margin'] - 600.0) < 0.01;
        });
    }

    public function test_a_legacy_line_with_no_frozen_cost_falls_back_to_the_items_current_price(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client']);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Widget', 'track_inventory' => true, 'unit_price' => 100, 'purchase_price' => 40]);

        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-2',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(),
            'currency' => $company->currency, 'subtotal' => 1000, 'vat_total' => 0, 'total' => 1000,
        ]);
        // Created directly (bypassing syncItems()), the way a pre-migration
        // row would exist with no unit_cost ever recorded.
        InvoiceItem::create(['invoice_id' => $invoice->id, 'item_id' => $item->id, 'description' => 'Widget', 'quantity' => 10, 'unit_price' => 100, 'vat_rate' => 0, 'vat_amount' => 0, 'line_total' => 1000]);

        $response = $this->actingAs($owner)->get(route('app.inventory.profitability', [
            'period' => 'custom', 'from' => now()->subDay()->toDateString(), 'to' => now()->addDay()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertViewHas('totals', fn ($totals) => abs($totals['cost'] - 400.0) < 0.01);
    }
}
