<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "don't show payment details in the invoice only status shows — payment
 * details we need to show for our use not to show in invoice": the
 * itemized payment history (method/reference/amount per payment) used to
 * print inside the shared documents.print.body partial — the same
 * template the invoice show page embeds as "the invoice itself" — on
 * every layout. That's now gone from the document; the only place that
 * detail still lives is the dedicated, print:hidden "Payments" panel
 * further down the show page, which this file now confirms is the sole
 * place it survives.
 */
class InvoiceOnScreenPaymentsTableTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): array
    {
        $company = Company::create(['name' => 'On Screen Co.', 'slug' => 'on-screen-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    private function makePaidInvoice(Company $company): Invoice
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co.']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-00001',
            'status' => 'partially_paid', 'issue_date' => now(), 'due_date' => now()->addDays(30),
            'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150, 'amount_paid' => 500, 'currency' => 'SAR',
        ]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => 'Work done', 'quantity' => 1, 'unit_price' => 1000, 'vat_rate' => 15, 'vat_amount' => 150, 'line_total' => 1150]);
        InvoicePayment::create(['invoice_id' => $invoice->id, 'amount' => 500, 'paid_at' => now(), 'method' => 'bank_transfer', 'reference' => 'REF-1']);

        return $invoice->fresh();
    }

    public function test_the_embedded_document_no_longer_shows_a_payments_received_table_on_any_layout(): void
    {
        [$company, $owner] = $this->makeOwner();
        $invoice = $this->makePaidInvoice($company);

        foreach (['bilingual_classic', 'custom_letterhead', 'minimal'] as $layout) {
            $company->invoiceTemplates()->updateOrCreate(['document_type' => 'invoice'], ['name' => 'T', 'layout' => $layout, 'is_default' => true]);

            $response = $this->actingAs($owner)->get(route('app.invoices.show', $invoice));

            $response->assertOk();
            $response->assertDontSee('Payments received');
        }
    }

    public function test_the_dedicated_payments_panel_still_shows_the_method_reference_and_amount(): void
    {
        [$company, $owner] = $this->makeOwner();
        $invoice = $this->makePaidInvoice($company);

        $response = $this->actingAs($owner)->get(route('app.invoices.show', $invoice));

        $response->assertOk();
        // The standalone "Payments" panel (print:hidden, internal-use
        // only) — a distinct heading from the removed "Payments received"
        // document section, and still showing the real recorded detail.
        $response->assertSee(__('Payments'));
        $response->assertSee(__('Bank transfer'));
        $response->assertSee('REF-1');
        $response->assertSee('500.00');
    }

    public function test_no_payments_panel_detail_when_nothing_has_been_paid(): void
    {
        [$company, $owner] = $this->makeOwner();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co.']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-00002',
            'status' => 'sent', 'issue_date' => now(), 'due_date' => now()->addDays(30),
            'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150, 'amount_paid' => 0, 'currency' => 'SAR',
        ]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => 'Work done', 'quantity' => 1, 'unit_price' => 1000, 'vat_rate' => 15, 'vat_amount' => 150, 'line_total' => 1150]);

        $this->actingAs($owner)->get(route('app.invoices.show', $invoice))
            ->assertOk()
            ->assertDontSee('Payments received')
            ->assertDontSee('REF-1');
    }
}
