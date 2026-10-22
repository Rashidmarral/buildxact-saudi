<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "if some companies order receive spare parts order and they have
 * deliver it to client and companies can issue delivery note for the
 * customers as well" — proof of delivery, standalone or generated from
 * an invoice. The invoice path is the one with real business logic to
 * verify: a delivery note can never ship more of a line than the invoice
 * billed, and a company can split one invoice's shipment across more
 * than one partial delivery note without ever over-shipping it.
 */
class DeliveryNoteTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        return Company::create(['name' => 'Spare Parts Co.', 'slug' => 'spare-'.uniqid()]);
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeInvoiceWithLine(Company $company, float $quantity = 10): array
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client A']);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Brake Pad', 'sku' => 'BP-100', 'unit_price' => 50]);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-1001',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(),
            'currency' => $company->currency, 'subtotal' => $quantity * 50, 'vat_total' => 0, 'total' => $quantity * 50,
        ]);
        $invoiceItem = InvoiceItem::create([
            'invoice_id' => $invoice->id, 'item_id' => $item->id, 'description' => 'Brake Pad',
            'quantity' => $quantity, 'unit_price' => 50, 'vat_rate' => 0, 'vat_amount' => 0, 'line_total' => $quantity * 50,
        ]);

        return [$invoice, $invoiceItem];
    }

    public function test_a_standalone_delivery_note_can_be_created_with_manual_lines(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Walk-in Client']);

        $response = $this->actingAs($owner)->post(route('app.delivery-notes.store'), [
            'client_id' => $client->id,
            'issue_date' => now()->toDateString(),
            'items' => [
                ['description' => 'Oil filter', 'quantity' => 3],
            ],
        ]);

        $note = DeliveryNote::first();
        $response->assertRedirect(route('app.delivery-notes.show', $note));
        $this->assertSame('issued', $note->status);
        $this->assertNull($note->invoice_id);
        $this->assertCount(1, $note->items);
        $this->assertSame('Oil filter', $note->items->first()->description);
    }

    public function test_a_delivery_note_can_be_generated_from_an_invoice_with_the_full_quantity(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        [$invoice, $invoiceItem] = $this->makeInvoiceWithLine($company, 10);

        $response = $this->actingAs($owner)->post(route('app.delivery-notes.store'), [
            'invoice_id' => $invoice->id,
            'issue_date' => now()->toDateString(),
            'items' => [
                ['invoice_item_id' => $invoiceItem->id, 'quantity' => 10],
            ],
        ]);

        $note = DeliveryNote::first();
        $response->assertRedirect(route('app.delivery-notes.show', $note));
        $this->assertSame($invoice->id, $note->invoice_id);
        $this->assertSame($invoice->client_id, $note->client_id);
        $this->assertSame(0.0, $invoiceItem->fresh()->remainingQuantity());
    }

    public function test_a_shipment_can_be_split_across_two_partial_delivery_notes(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        [$invoice, $invoiceItem] = $this->makeInvoiceWithLine($company, 10);

        $this->actingAs($owner)->post(route('app.delivery-notes.store'), [
            'invoice_id' => $invoice->id, 'issue_date' => now()->toDateString(),
            'items' => [['invoice_item_id' => $invoiceItem->id, 'quantity' => 6]],
        ]);

        $this->assertSame(4.0, $invoiceItem->fresh()->remainingQuantity());

        $this->actingAs($owner)->post(route('app.delivery-notes.store'), [
            'invoice_id' => $invoice->id, 'issue_date' => now()->toDateString(),
            'items' => [['invoice_item_id' => $invoiceItem->id, 'quantity' => 4]],
        ])->assertRedirect();

        $this->assertSame(0.0, $invoiceItem->fresh()->remainingQuantity());
        $this->assertSame(2, DeliveryNote::count());
    }

    public function test_a_delivery_note_cannot_ship_more_than_the_invoice_line_billed(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        [$invoice, $invoiceItem] = $this->makeInvoiceWithLine($company, 10);

        $response = $this->actingAs($owner)->post(route('app.delivery-notes.store'), [
            'invoice_id' => $invoice->id, 'issue_date' => now()->toDateString(),
            'items' => [['invoice_item_id' => $invoiceItem->id, 'quantity' => 15]],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertSame(0, DeliveryNote::count());
    }

    public function test_cancelling_a_delivery_note_frees_up_its_quantity_for_a_new_one(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        [$invoice, $invoiceItem] = $this->makeInvoiceWithLine($company, 10);

        $this->actingAs($owner)->post(route('app.delivery-notes.store'), [
            'invoice_id' => $invoice->id, 'issue_date' => now()->toDateString(),
            'items' => [['invoice_item_id' => $invoiceItem->id, 'quantity' => 10]],
        ]);
        $note = DeliveryNote::first();
        $this->assertSame(0.0, $invoiceItem->fresh()->remainingQuantity());

        $this->actingAs($owner)->post(route('app.delivery-notes.cancel', $note))->assertRedirect();

        $this->assertSame('cancelled', $note->fresh()->status);
        $this->assertSame(10.0, $invoiceItem->fresh()->remainingQuantity());
    }

    public function test_an_already_cancelled_delivery_note_cannot_be_cancelled_again(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client A']);
        $this->actingAs($owner)->post(route('app.delivery-notes.store'), [
            'client_id' => $client->id, 'issue_date' => now()->toDateString(),
            'items' => [['description' => 'Spare part', 'quantity' => 1]],
        ]);
        $note = DeliveryNote::first();
        $this->actingAs($owner)->post(route('app.delivery-notes.cancel', $note));

        $response = $this->actingAs($owner)->post(route('app.delivery-notes.cancel', $note->fresh()));

        $response->assertSessionHasErrors('delivery_note');
    }

    public function test_the_pdf_downloads_for_a_standalone_delivery_note(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client A']);
        $this->actingAs($owner)->post(route('app.delivery-notes.store'), [
            'client_id' => $client->id, 'issue_date' => now()->toDateString(),
            'items' => [['description' => 'Spare part', 'quantity' => 2]],
        ]);
        $note = DeliveryNote::first();

        $response = $this->actingAs($owner)->get(route('app.delivery-notes.pdf', $note));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_the_eligible_invoices_search_excludes_fully_delivered_invoices(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        [$invoice, $invoiceItem] = $this->makeInvoiceWithLine($company, 5);

        $this->actingAs($owner)->post(route('app.delivery-notes.store'), [
            'invoice_id' => $invoice->id, 'issue_date' => now()->toDateString(),
            'items' => [['invoice_item_id' => $invoiceItem->id, 'quantity' => 5]],
        ]);

        $response = $this->actingAs($owner)->getJson(route('app.delivery-notes.eligible-invoices'));

        $response->assertOk();
        $this->assertEmpty($response->json());
    }

    public function test_a_company_cannot_generate_a_delivery_note_from_another_companys_invoice(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        $ownerA = $this->makeOwner($companyA);
        [$invoiceB, $invoiceItemB] = $this->makeInvoiceWithLine($companyB, 5);

        $response = $this->actingAs($ownerA)->post(route('app.delivery-notes.store'), [
            'invoice_id' => $invoiceB->id, 'issue_date' => now()->toDateString(),
            'items' => [['invoice_item_id' => $invoiceItemB->id, 'quantity' => 1]],
        ]);

        $response->assertSessionHasErrors('invoice_id');
        $this->assertSame(0, DeliveryNote::withoutGlobalScopes()->count());
    }

    public function test_a_company_cannot_view_another_companys_delivery_note(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        $ownerA = $this->makeOwner($companyA);
        $clientB = Client::create(['company_id' => $companyB->id, 'name' => 'Client B']);
        $this->actingAs($this->makeOwner($companyB))->post(route('app.delivery-notes.store'), [
            'client_id' => $clientB->id, 'issue_date' => now()->toDateString(),
            'items' => [['description' => 'Part', 'quantity' => 1]],
        ]);
        $noteB = DeliveryNote::withoutGlobalScopes()->where('company_id', $companyB->id)->first();

        $this->actingAs($ownerA)->get(route('app.delivery-notes.show', $noteB))->assertNotFound();
    }

    public function test_the_create_form_renders_with_an_invoice_preselected(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        [$invoice] = $this->makeInvoiceWithLine($company, 10);

        $response = $this->actingAs($owner)->get(route('app.delivery-notes.create', ['invoice_id' => $invoice->id]));

        $response->assertOk();
        $response->assertSee('Brake Pad');
    }

    public function test_the_show_page_renders_for_a_standalone_delivery_note(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client A']);
        $this->actingAs($owner)->post(route('app.delivery-notes.store'), [
            'client_id' => $client->id, 'issue_date' => now()->toDateString(),
            'items' => [['description' => 'Spare part', 'quantity' => 2]],
        ]);
        $note = DeliveryNote::first();

        $response = $this->actingAs($owner)->get(route('app.delivery-notes.show', $note));

        $response->assertOk();
        $response->assertSee($note->delivery_note_number);
        $response->assertSee('Spare part');
    }

    public function test_the_show_page_renders_for_a_delivery_note_generated_from_an_invoice(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        [$invoice, $invoiceItem] = $this->makeInvoiceWithLine($company, 5);
        $this->actingAs($owner)->post(route('app.delivery-notes.store'), [
            'invoice_id' => $invoice->id, 'issue_date' => now()->toDateString(),
            'items' => [['invoice_item_id' => $invoiceItem->id, 'quantity' => 5]],
        ]);
        $note = DeliveryNote::first();

        $response = $this->actingAs($owner)->get(route('app.delivery-notes.show', $note));

        $response->assertOk();
        $response->assertSee('Brake Pad');
        $response->assertSee($invoice->invoice_number);
    }

    public function test_the_index_page_renders(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client A']);
        $this->actingAs($owner)->post(route('app.delivery-notes.store'), [
            'client_id' => $client->id, 'issue_date' => now()->toDateString(),
            'items' => [['description' => 'Spare part', 'quantity' => 2]],
        ]);

        $response = $this->actingAs($owner)->get(route('app.delivery-notes.index'));

        $response->assertOk();
        $response->assertSee(DeliveryNote::first()->delivery_note_number);
    }

    public function test_the_select_invoice_page_renders(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->get(route('app.delivery-notes.select-invoice'));

        $response->assertOk();
    }

    public function test_the_standalone_create_form_renders(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->get(route('app.delivery-notes.create'));

        $response->assertOk();
    }
}
