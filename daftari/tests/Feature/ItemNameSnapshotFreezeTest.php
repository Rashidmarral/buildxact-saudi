<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Bill;
use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\Quotation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug report: editing an Item's Arabic name/description after an
 * invoice/quotation/bill/PO/credit-note/debit-note was already issued
 * retroactively changed how that historical document displayed, because
 * the print templates read $line->item?->name_ar / ->description live off
 * the current Item record instead of a frozen snapshot. Fixed by adding
 * name_ar/item_description columns to each line-item table, populated at
 * line-creation time, and switching the templates to read those instead.
 * This locks in that every document type snapshots correctly and stays
 * frozen after the source Item changes.
 */
class ItemNameSnapshotFreezeTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Snapshot Co.', 'slug' => 'snapshot-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_invoice_line_freezes_item_arabic_name_and_description_at_creation(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client']);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Steel Beam', 'name_ar' => 'عارضة فولاذية', 'description' => 'Grade A', 'unit_price' => 100]);

        $this->actingAs($owner)->post(route('app.invoices.store'), [
            'client_id' => $client->id,
            'type' => 'standard',
            'issue_date' => now()->toDateString(),
            'items' => [
                ['item_id' => $item->id, 'description' => 'Steel Beam', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 15],
            ],
        ])->assertSessionDoesntHaveErrors();

        $invoice = Invoice::latest('id')->first();
        $line = $invoice->items()->first();

        $this->assertSame('عارضة فولاذية', $line->name_ar);
        $this->assertSame('Grade A', $line->item_description);

        // Product master data changes after the invoice was issued...
        $item->update(['name_ar' => 'اسم جديد', 'description' => 'Grade B (renamed)']);

        // ...but the already-issued invoice's line must not follow it.
        $line->refresh();
        $this->assertSame('عارضة فولاذية', $line->name_ar);
        $this->assertSame('Grade A', $line->item_description);

        $response = $this->get(route('app.invoices.show', $invoice));
        $response->assertOk();
        $response->assertSee('عارضة فولاذية');
        $response->assertDontSee('اسم جديد');
    }

    public function test_quotation_line_freezes_item_snapshot_and_carries_it_through_conversion_to_invoice(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client']);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Cement Bag', 'name_ar' => 'كيس أسمنت', 'unit_price' => 20]);

        $this->actingAs($owner)->post(route('app.quotations.store'), [
            'client_id' => $client->id,
            'type' => 'quotation',
            'issue_date' => now()->toDateString(),
            'items' => [
                ['item_id' => $item->id, 'description' => 'Cement Bag', 'quantity' => 1, 'unit_price' => 20, 'vat_rate' => 15],
            ],
        ])->assertSessionDoesntHaveErrors();

        $quotation = Quotation::latest('id')->first();
        $this->assertSame('كيس أسمنت', $quotation->items()->first()->name_ar);

        $item->update(['name_ar' => 'اسم متغير']);

        $this->actingAs($owner)->post(route('app.quotations.convert', $quotation))->assertRedirect();

        $quotation->refresh();
        $convertedInvoice = Invoice::find($quotation->converted_invoice_id);

        // Converted at a point after the rename — the invoice line still
        // carries the quotation line's original frozen snapshot, not the
        // Item's current (renamed) value.
        $this->assertSame('كيس أسمنت', $convertedInvoice->items()->first()->name_ar);
    }

    public function test_bill_line_freezes_item_snapshot(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Test Supplier']);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Rebar', 'name_ar' => 'حديد تسليح', 'unit_price' => 50]);

        $this->actingAs($owner)->post(route('app.bills.store'), [
            'supplier_id' => $supplier->id,
            'bill_date' => now()->toDateString(),
            'items' => [
                ['item_id' => $item->id, 'description' => 'Rebar', 'quantity' => 1, 'unit_price' => 50, 'vat_rate' => 15],
            ],
        ])->assertSessionDoesntHaveErrors();

        $bill = Bill::latest('id')->first();
        $line = $bill->items()->first();
        $this->assertSame('حديد تسليح', $line->name_ar);

        $item->update(['name_ar' => 'تغيير']);
        $line->refresh();
        $this->assertSame('حديد تسليح', $line->name_ar);
    }

    public function test_purchase_order_line_freezes_item_snapshot_and_carries_it_through_conversion_to_bill(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Test Supplier']);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Pipe', 'name_ar' => 'أنبوب', 'unit_price' => 30]);

        $this->actingAs($owner)->post(route('app.purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'post_immediately' => '1',
            'items' => [
                ['item_id' => $item->id, 'description' => 'Pipe', 'quantity' => 1, 'unit_price' => 30, 'vat_rate' => 15],
            ],
        ])->assertSessionDoesntHaveErrors();

        $order = PurchaseOrder::latest('id')->first();
        $poItem = $order->items()->first();
        $this->assertSame('أنبوب', $poItem->name_ar);

        $item->update(['name_ar' => 'تغيير']);

        $this->actingAs($owner)->post(route('app.purchase-orders.bill-store', $order), [
            'bill_date' => now()->toDateString(),
            'items' => [
                ['purchase_order_item_id' => $poItem->id, 'description' => 'Pipe', 'quantity' => 1, 'unit_price' => 30, 'vat_rate' => 15],
            ],
        ])->assertSessionDoesntHaveErrors();

        $bill = Bill::latest('id')->first();
        $this->assertSame('أنبوب', $bill->items()->first()->name_ar);
    }

    public function test_credit_note_line_carries_the_source_invoice_lines_frozen_snapshot(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client']);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Paint Can', 'name_ar' => 'علبة دهان', 'unit_price' => 40]);

        $this->actingAs($owner)->post(route('app.invoices.store'), [
            'client_id' => $client->id,
            'type' => 'standard',
            'issue_date' => now()->toDateString(),
            'send_immediately' => '1',
            'items' => [
                ['item_id' => $item->id, 'description' => 'Paint Can', 'quantity' => 1, 'unit_price' => 40, 'vat_rate' => 15],
            ],
        ])->assertSessionDoesntHaveErrors();

        $invoice = Invoice::latest('id')->first();
        $invoiceItem = $invoice->items()->first();

        $item->update(['name_ar' => 'تغيير']);

        $this->actingAs($owner)->post(route('app.credit-notes.store'), [
            'invoice_id' => $invoice->id,
            'issue_date' => now()->toDateString(),
            'items' => [
                ['invoice_item_id' => $invoiceItem->id, 'description' => 'Paint Can', 'quantity' => 1, 'unit_price' => 40, 'vat_rate' => 15],
            ],
        ])->assertSessionDoesntHaveErrors();

        $creditNote = CreditNote::latest('id')->first();
        $this->assertSame('علبة دهان', $creditNote->items()->first()->name_ar);
    }

    public function test_purchase_return_line_carries_the_source_bill_lines_frozen_snapshot(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Test Supplier']);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Wire Spool', 'name_ar' => 'بكرة سلك', 'unit_price' => 60]);

        $this->actingAs($owner)->post(route('app.bills.store'), [
            'supplier_id' => $supplier->id,
            'bill_date' => now()->toDateString(),
            'post_immediately' => '1',
            'items' => [
                ['item_id' => $item->id, 'description' => 'Wire Spool', 'quantity' => 1, 'unit_price' => 60, 'vat_rate' => 15],
            ],
        ])->assertSessionDoesntHaveErrors();

        $bill = Bill::latest('id')->first();
        $billItem = $bill->items()->first();

        $item->update(['name_ar' => 'تغيير']);

        $this->actingAs($owner)->post(route('app.purchase-returns.store'), [
            'bill_id' => $bill->id,
            'issue_date' => now()->toDateString(),
            'items' => [
                ['bill_item_id' => $billItem->id, 'description' => 'Wire Spool', 'quantity' => 1, 'unit_price' => 60, 'vat_rate' => 15],
            ],
        ])->assertSessionDoesntHaveErrors();

        $purchaseReturn = PurchaseReturn::latest('id')->first();
        $this->assertSame('بكرة سلك', $purchaseReturn->items()->first()->name_ar);
    }
}
