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
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * COGS was defined as a semantic account key (COGS_DEFAULT, code 5000) and
 * shown as a line on the Income Statement, but nothing ever posted to it —
 * Gross Profit always equalled Net Sales for every company selling tracked
 * inventory, and purchased inventory was expensed in full on the bill
 * rather than capitalized, leaving Inventory Asset disconnected from real
 * purchase/sale activity. Fixed by:
 *
 * - postBillPosted(): a bill line for a track_inventory item capitalizes
 *   to INVENTORY_ASSET instead of DEFAULT_OPERATING_EXPENSES.
 * - postInvoiceIssued(): once stock is deducted for a sale, it also
 *   relieves INVENTORY_ASSET and debits COGS_DEFAULT, using each item's
 *   standard cost (purchase_price) — the same figure the existing
 *   Inventory Valuation report already uses.
 */
class InventoryCostingTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Costing Co.', 'slug' => 'costing-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    public function test_a_bill_line_for_a_tracked_inventory_item_capitalizes_to_inventory_asset(): void
    {
        $company = $this->makeCompany();
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Supplier']);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Widget', 'track_inventory' => true, 'purchase_price' => 40]);

        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-1',
            'status' => 'draft', 'bill_date' => now()->toDateString(),
            'subtotal' => 400, 'discount_total' => 0, 'vat_total' => 60, 'total' => 460,
        ]);
        BillItem::create(['bill_id' => $bill->id, 'item_id' => $item->id, 'description' => 'Widget', 'quantity' => 10, 'unit_price' => 40, 'vat_rate' => 15, 'vat_amount' => 60, 'line_total' => 460]);

        $entry = app(LedgerPostingService::class)->postBillPosted($bill);

        $inventoryAsset = Account::where('company_id', $company->id)->where('code', '1400')->first();
        $expenseAccount = Account::where('company_id', $company->id)->where('code', '5100')->first();

        $this->assertSame(400.0, (float) $entry->lines()->where('account_id', $inventoryAsset->id)->sum('debit'));
        $this->assertSame(0.0, (float) $entry->lines()->where('account_id', $expenseAccount->id)->sum('debit'));
    }

    public function test_a_bill_mixing_tracked_and_non_tracked_lines_splits_and_prorates_the_discount(): void
    {
        $company = $this->makeCompany();
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Supplier']);
        $trackedItem = Item::create(['company_id' => $company->id, 'name' => 'Widget', 'track_inventory' => true, 'purchase_price' => 40]);
        $serviceItem = Item::create(['company_id' => $company->id, 'name' => 'Consulting', 'track_inventory' => false]);

        // 300 tracked (75% of the 400 line subtotal) + 100 untracked, with
        // a 40 header discount that should split 30/10 by that same share.
        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-2',
            'status' => 'draft', 'bill_date' => now()->toDateString(),
            'subtotal' => 400, 'discount_total' => 40, 'vat_total' => 54, 'total' => 414,
        ]);
        BillItem::create(['bill_id' => $bill->id, 'item_id' => $trackedItem->id, 'description' => 'Widget', 'quantity' => 1, 'unit_price' => 300, 'vat_rate' => 15, 'vat_amount' => 45, 'line_total' => 345]);
        BillItem::create(['bill_id' => $bill->id, 'item_id' => $serviceItem->id, 'description' => 'Consulting', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'line_total' => 115]);

        $entry = app(LedgerPostingService::class)->postBillPosted($bill);

        $inventoryAsset = Account::where('company_id', $company->id)->where('code', '1400')->first();
        $expenseAccount = Account::where('company_id', $company->id)->where('code', '5100')->first();

        // net = 400 - 40 = 360; tracked share = 300/400 = 0.75 -> 270 capitalized, 90 expensed.
        $this->assertSame(270.0, (float) $entry->lines()->where('account_id', $inventoryAsset->id)->sum('debit'));
        $this->assertSame(90.0, (float) $entry->lines()->where('account_id', $expenseAccount->id)->sum('debit'));
    }

    public function test_a_bill_with_only_non_tracked_items_still_expenses_in_full(): void
    {
        $company = $this->makeCompany();
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Supplier']);
        $serviceItem = Item::create(['company_id' => $company->id, 'name' => 'Consulting', 'track_inventory' => false]);

        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-3',
            'status' => 'draft', 'bill_date' => now()->toDateString(),
            'subtotal' => 100, 'discount_total' => 0, 'vat_total' => 15, 'total' => 115,
        ]);
        BillItem::create(['bill_id' => $bill->id, 'item_id' => $serviceItem->id, 'description' => 'Consulting', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'line_total' => 115]);

        $entry = app(LedgerPostingService::class)->postBillPosted($bill);

        $expenseAccount = Account::where('company_id', $company->id)->where('code', '5100')->first();
        $this->assertSame(100.0, (float) $entry->lines()->where('account_id', $expenseAccount->id)->sum('debit'));
    }

    public function test_selling_a_tracked_item_from_a_warehouse_posts_cogs_at_the_items_standard_cost(): void
    {
        $company = $this->makeCompany();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client']);
        $warehouse = Warehouse::create(['company_id' => $company->id, 'name' => 'Main']);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Widget', 'track_inventory' => true, 'purchase_price' => 40, 'unit_price' => 100]);
        ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => 50]);

        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-1',
            'issue_date' => now(), 'due_date' => now()->addDays(30), 'status' => 'sent', 'currency' => 'SAR',
            'warehouse_id' => $warehouse->id, 'stock_deducted' => true,
            'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150,
        ]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'item_id' => $item->id, 'description' => 'Widget', 'quantity' => 10, 'unit_price' => 100, 'vat_rate' => 15, 'vat_amount' => 150, 'line_total' => 1150]);

        $entry = app(LedgerPostingService::class)->postInvoiceIssued($invoice);

        $cogs = Account::where('company_id', $company->id)->where('code', '5000')->first();
        $inventoryAsset = Account::where('company_id', $company->id)->where('code', '1400')->first();

        // 10 units * purchase_price 40 = 400 COGS, relieving Inventory Asset by the same.
        $this->assertSame(400.0, (float) $entry->lines()->where('account_id', $cogs->id)->sum('debit'));
        $this->assertSame(400.0, (float) $entry->lines()->where('account_id', $inventoryAsset->id)->sum('credit'));
    }

    public function test_cancelling_the_invoice_reverses_the_cogs_entry_too(): void
    {
        $company = $this->makeCompany();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client']);
        $warehouse = Warehouse::create(['company_id' => $company->id, 'name' => 'Main']);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Widget', 'track_inventory' => true, 'purchase_price' => 40, 'unit_price' => 100]);
        ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => 50]);

        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-2',
            'issue_date' => now(), 'due_date' => now()->addDays(30), 'status' => 'sent', 'currency' => 'SAR',
            'warehouse_id' => $warehouse->id, 'stock_deducted' => true,
            'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150,
        ]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'item_id' => $item->id, 'description' => 'Widget', 'quantity' => 10, 'unit_price' => 100, 'vat_rate' => 15, 'vat_amount' => 150, 'line_total' => 1150]);

        $ledger = app(LedgerPostingService::class);
        $ledger->postInvoiceIssued($invoice);
        $reversal = $ledger->reverse($company, 'invoice', $invoice->id, 'Invoice cancelled');

        $cogs = Account::where('company_id', $company->id)->where('code', '5000')->first();
        $inventoryAsset = Account::where('company_id', $company->id)->where('code', '1400')->first();

        $this->assertSame(400.0, (float) $reversal->lines()->where('account_id', $cogs->id)->sum('credit'));
        $this->assertSame(400.0, (float) $reversal->lines()->where('account_id', $inventoryAsset->id)->sum('debit'));
        $this->assertSame(2, JournalEntry::where('company_id', $company->id)->count());
    }

    public function test_income_statement_now_shows_a_real_gross_profit_instead_of_always_equaling_net_sales(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client']);
        $warehouse = Warehouse::create(['company_id' => $company->id, 'name' => 'Main']);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Widget', 'track_inventory' => true, 'purchase_price' => 40, 'unit_price' => 100]);
        ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => 50]);

        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-3',
            'issue_date' => now(), 'due_date' => now()->addDays(30), 'status' => 'sent', 'currency' => 'SAR',
            'warehouse_id' => $warehouse->id, 'stock_deducted' => true,
            'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150,
        ]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'item_id' => $item->id, 'description' => 'Widget', 'quantity' => 10, 'unit_price' => 100, 'vat_rate' => 15, 'vat_amount' => 150, 'line_total' => 1150]);
        app(LedgerPostingService::class)->postInvoiceIssued($invoice);

        $response = $this->actingAs($owner)->get(route('app.reports.income-statement'));

        $response->assertOk();
        $response->assertViewHas('grossProfit', 600.0);
        $response->assertViewHas('netSales', 1000.0);
    }
}
