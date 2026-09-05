<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Quotation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding MEDIUM-15: no list page could act on more than one record
 * at a time — every bulk action here was a genuine, from-scratch gap.
 * Covers the five highest-value modules (Invoices, Bills, Quotations,
 * Clients, Items): exporting exactly the checked rows, the guarded bulk
 * destructive action for each, and that a malicious id list can never
 * reach another company's records (BelongsToCompany's global scope
 * applies to whereIn() the same as any other query).
 */
class BulkActionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(?string $name = null): User
    {
        $company = Company::create(['name' => $name ?? 'Bulk Co.', 'slug' => 'bulk-'.uniqid()]);

        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_invoice_bulk_export_returns_only_the_selected_rows(): void
    {
        $owner = $this->makeOwner();
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Client A']);
        $keep = Invoice::create(['company_id' => $owner->company_id, 'client_id' => $client->id, 'invoice_number' => 'INV-1', 'type' => 'standard', 'issue_date' => now(), 'status' => 'draft', 'currency' => 'SAR', 'subtotal' => 100, 'vat_total' => 15, 'total' => 115]);
        $excluded = Invoice::create(['company_id' => $owner->company_id, 'client_id' => $client->id, 'invoice_number' => 'INV-2', 'type' => 'standard', 'issue_date' => now(), 'status' => 'draft', 'currency' => 'SAR', 'subtotal' => 200, 'vat_total' => 30, 'total' => 230]);

        $response = $this->actingAs($owner)->post(route('app.invoices.bulk-export'), ['ids' => [$keep->id]]);

        $response->assertOk();
        $this->assertStringContainsString('INV-1', $response->streamedContent());
        $this->assertStringNotContainsString('INV-2', $response->streamedContent());
    }

    public function test_invoice_bulk_destroy_deletes_drafts_and_skips_non_drafts(): void
    {
        $owner = $this->makeOwner();
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Client A']);
        $draft = Invoice::create(['company_id' => $owner->company_id, 'client_id' => $client->id, 'invoice_number' => 'INV-1', 'type' => 'standard', 'issue_date' => now(), 'status' => 'draft', 'currency' => 'SAR', 'subtotal' => 100, 'vat_total' => 15, 'total' => 115]);
        $sent = Invoice::create(['company_id' => $owner->company_id, 'client_id' => $client->id, 'invoice_number' => 'INV-2', 'type' => 'standard', 'issue_date' => now(), 'due_date' => now()->addDays(30), 'status' => 'sent', 'currency' => 'SAR', 'subtotal' => 200, 'vat_total' => 30, 'total' => 230]);

        $response = $this->actingAs($owner)->post(route('app.invoices.bulk-destroy'), ['ids' => [$draft->id, $sent->id]]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseMissing('invoices', ['id' => $draft->id]);
        $this->assertDatabaseHas('invoices', ['id' => $sent->id]);
    }

    public function test_invoice_bulk_destroy_never_touches_another_companys_invoice(): void
    {
        $owner = $this->makeOwner();
        $otherOwner = $this->makeOwner('Other Co.');
        $otherClient = Client::create(['company_id' => $otherOwner->company_id, 'name' => 'Other Client']);
        $otherInvoice = Invoice::create(['company_id' => $otherOwner->company_id, 'client_id' => $otherClient->id, 'invoice_number' => 'INV-OTHER', 'type' => 'standard', 'issue_date' => now(), 'status' => 'draft', 'currency' => 'SAR', 'subtotal' => 100, 'vat_total' => 15, 'total' => 115]);

        $this->actingAs($owner)->post(route('app.invoices.bulk-destroy'), ['ids' => [$otherInvoice->id]]);

        $this->assertDatabaseHas('invoices', ['id' => $otherInvoice->id]);
    }

    public function test_bill_bulk_void_voids_posted_bills_and_skips_draft_bills(): void
    {
        $owner = $this->makeOwner();
        $supplier = Supplier::create(['company_id' => $owner->company_id, 'name' => 'Supplier A']);
        $draft = Bill::create(['company_id' => $owner->company_id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-1', 'status' => 'draft', 'bill_date' => now()->toDateString(), 'subtotal' => 100, 'vat_total' => 15, 'total' => 115]);
        $posted = Bill::create(['company_id' => $owner->company_id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-2', 'status' => 'posted', 'bill_date' => now()->toDateString(), 'subtotal' => 100, 'vat_total' => 15, 'total' => 115]);

        $response = $this->actingAs($owner)->post(route('app.bills.bulk-void'), ['ids' => [$draft->id, $posted->id]]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame('draft', $draft->fresh()->status);
        $this->assertSame('void', $posted->fresh()->status);
    }

    public function test_quotation_bulk_destroy_skips_converted_quotations(): void
    {
        $owner = $this->makeOwner();
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Client A']);
        $issued = Quotation::create(['company_id' => $owner->company_id, 'client_id' => $client->id, 'quotation_number' => 'QTN-1', 'type' => 'quotation', 'status' => 'issued', 'issue_date' => now(), 'currency' => 'SAR', 'subtotal' => 100, 'vat_total' => 15, 'total' => 115]);
        $converted = Quotation::create(['company_id' => $owner->company_id, 'client_id' => $client->id, 'quotation_number' => 'QTN-2', 'type' => 'quotation', 'status' => 'converted', 'issue_date' => now(), 'currency' => 'SAR', 'subtotal' => 100, 'vat_total' => 15, 'total' => 115]);

        $response = $this->actingAs($owner)->post(route('app.quotations.bulk-destroy'), ['ids' => [$issued->id, $converted->id]]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseMissing('quotations', ['id' => $issued->id]);
        $this->assertDatabaseHas('quotations', ['id' => $converted->id]);
    }

    public function test_client_bulk_destroy_skips_clients_with_invoices(): void
    {
        $owner = $this->makeOwner();
        $withInvoice = Client::create(['company_id' => $owner->company_id, 'name' => 'Has Invoice']);
        Invoice::create(['company_id' => $owner->company_id, 'client_id' => $withInvoice->id, 'invoice_number' => 'INV-1', 'type' => 'standard', 'issue_date' => now(), 'status' => 'draft', 'currency' => 'SAR', 'subtotal' => 100, 'vat_total' => 15, 'total' => 115]);
        $clean = Client::create(['company_id' => $owner->company_id, 'name' => 'No History']);

        $response = $this->actingAs($owner)->post(route('app.clients.bulk-destroy'), ['ids' => [$withInvoice->id, $clean->id]]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('clients', ['id' => $withInvoice->id]);
        $this->assertDatabaseMissing('clients', ['id' => $clean->id]);
    }

    public function test_item_bulk_destroy_skips_items_used_on_an_invoice(): void
    {
        $owner = $this->makeOwner();
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Client A']);
        $usedItem = Item::create(['company_id' => $owner->company_id, 'name' => 'Widget', 'unit_price' => 10, 'vat_rate' => 15]);
        $invoice = Invoice::create(['company_id' => $owner->company_id, 'client_id' => $client->id, 'invoice_number' => 'INV-1', 'type' => 'standard', 'issue_date' => now(), 'status' => 'draft', 'currency' => 'SAR', 'subtotal' => 100, 'vat_total' => 15, 'total' => 115]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'item_id' => $usedItem->id, 'description' => 'Widget', 'quantity' => 1, 'unit_price' => 10, 'vat_rate' => 15, 'vat_amount' => 1.5, 'line_total' => 11.5]);
        $unusedItem = Item::create(['company_id' => $owner->company_id, 'name' => 'Gadget', 'unit_price' => 20, 'vat_rate' => 15]);

        $response = $this->actingAs($owner)->post(route('app.items.bulk-destroy'), ['ids' => [$usedItem->id, $unusedItem->id]]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('items', ['id' => $usedItem->id]);
        $this->assertDatabaseMissing('items', ['id' => $unusedItem->id]);
    }

    public function test_bulk_destroy_requires_at_least_one_id(): void
    {
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->post(route('app.invoices.bulk-destroy'), ['ids' => []]);

        $response->assertSessionHasErrors('ids');
    }
}
