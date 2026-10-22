<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "can we make our credit note something like this" — the same
 * treatment just given to debit notes: a structured "Reason type"
 * dropdown (Returned goods / Pricing error / Discount given / Order
 * cancelled / Other) alongside the free-text reason, and an "Original
 * invoice" summary on the create form. Unlike debit notes, the existing
 * credit note form already pre-fills every original invoice line
 * directly into the one editable table (adjust quantity down, or clear
 * a line, to credit back only part of what was billed) — that design
 * stays; what it was missing was a way to add a line NOT tied to any
 * original invoice line (e.g. a goodwill credit), which the backend
 * already supported (invoice_item_id was always nullable) but the form
 * gave no way to reach.
 */
class CreditNoteReasonTypeAndFreeformLineTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyWithInvoice(): array
    {
        $company = Company::create(['name' => 'Credit Note Co.', 'slug' => 'credit-note-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $client = Client::create(['company_id' => $company->id, 'name' => 'Big Client LLC']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-1',
            'type' => 'standard', 'issue_date' => now(), 'due_date' => now()->addDays(30),
            'status' => 'sent', 'currency' => 'SAR', 'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150,
        ]);
        $invoice->items()->create([
            'description' => 'Consulting hours', 'quantity' => 2, 'unit_price' => 500,
            'vat_rate' => 15, 'vat_amount' => 150, 'line_total' => 1150,
        ]);

        return [$company, $owner, $client, $invoice];
    }

    public function test_a_reason_type_can_be_saved_alongside_the_free_text_reason(): void
    {
        [, $owner, , $invoice] = $this->makeCompanyWithInvoice();
        $invoiceItem = $invoice->items->first();

        $this->actingAs($owner)->post(route('app.credit-notes.store'), [
            'invoice_id' => $invoice->id,
            'issue_date' => now()->toDateString(),
            'reason' => 'Client returned one unit',
            'reason_type' => 'returned_goods',
            'items' => [
                ['invoice_item_id' => $invoiceItem->id, 'description' => 'Consulting hours', 'quantity' => 1, 'unit_price' => 500, 'vat_rate' => 15],
            ],
        ])->assertSessionDoesntHaveErrors();

        $creditNote = CreditNote::latest('id')->first();
        $this->assertSame('returned_goods', $creditNote->reason_type);
        $this->assertSame('Returned goods', $creditNote->reasonTypeLabel());
    }

    public function test_an_invalid_reason_type_is_rejected(): void
    {
        [, $owner, , $invoice] = $this->makeCompanyWithInvoice();
        $invoiceItem = $invoice->items->first();

        $response = $this->actingAs($owner)->post(route('app.credit-notes.store'), [
            'invoice_id' => $invoice->id,
            'issue_date' => now()->toDateString(),
            'reason_type' => 'not-a-real-type',
            'items' => [
                ['invoice_item_id' => $invoiceItem->id, 'description' => 'Consulting hours', 'quantity' => 1, 'unit_price' => 500, 'vat_rate' => 15],
            ],
        ]);

        $response->assertSessionHasErrors('reason_type');
        $this->assertSame(0, CreditNote::count());
    }

    public function test_reason_type_is_optional(): void
    {
        [, $owner, , $invoice] = $this->makeCompanyWithInvoice();
        $invoiceItem = $invoice->items->first();

        $this->actingAs($owner)->post(route('app.credit-notes.store'), [
            'invoice_id' => $invoice->id,
            'issue_date' => now()->toDateString(),
            'items' => [
                ['invoice_item_id' => $invoiceItem->id, 'description' => 'Consulting hours', 'quantity' => 1, 'unit_price' => 500, 'vat_rate' => 15],
            ],
        ])->assertSessionDoesntHaveErrors();

        $creditNote = CreditNote::latest('id')->first();
        $this->assertNull($creditNote->reason_type);
        $this->assertNull($creditNote->reasonTypeLabel());
    }

    public function test_a_freeform_line_not_tied_to_any_invoice_line_can_be_credited(): void
    {
        [, $owner, , $invoice] = $this->makeCompanyWithInvoice();

        $response = $this->actingAs($owner)->post(route('app.credit-notes.store'), [
            'invoice_id' => $invoice->id,
            'issue_date' => now()->toDateString(),
            'items' => [
                ['description' => 'Goodwill credit', 'quantity' => 1, 'unit_price' => 50, 'vat_rate' => 15],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $creditNote = CreditNote::latest('id')->first();
        $this->assertCount(1, $creditNote->items);
        $this->assertNull($creditNote->items->first()->invoice_item_id);
        $this->assertSame('Goodwill credit', $creditNote->items->first()->description);
    }

    public function test_the_create_form_shows_the_original_invoice_summary(): void
    {
        [, $owner, , $invoice] = $this->makeCompanyWithInvoice();

        $response = $this->actingAs($owner)->get(route('app.credit-notes.create', ['invoice_id' => $invoice->id]));

        $response->assertOk();
        $response->assertSee($invoice->invoice_number);
        $response->assertSee('Consulting hours');
        $response->assertSee(__('Reason type'));
        $response->assertSee(__('+ Add credit line'));
    }

    public function test_the_show_page_renders_the_reason_type_label_alongside_the_reason(): void
    {
        [$company, $owner, $client, $invoice] = $this->makeCompanyWithInvoice();
        $creditNote = CreditNote::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id, 'client_id' => $client->id,
            'credit_note_number' => 'CN-1', 'issue_date' => now(), 'status' => 'issued', 'currency' => 'SAR',
            'reason' => 'One unit came back damaged', 'reason_type' => 'returned_goods',
            'subtotal' => 500, 'vat_total' => 75, 'total' => 575,
        ]);
        $creditNote->items()->create([
            'description' => 'Consulting hours', 'quantity' => 1, 'unit_price' => 500,
            'vat_rate' => 15, 'vat_amount' => 75, 'line_total' => 575,
        ]);

        $response = $this->actingAs($owner)->get(route('app.credit-notes.show', $creditNote));

        $response->assertOk();
        $response->assertSee('Returned goods');
        $response->assertSee('One unit came back damaged');
    }
}
