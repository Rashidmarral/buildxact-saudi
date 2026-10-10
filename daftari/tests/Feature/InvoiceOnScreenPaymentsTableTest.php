<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\InvoiceTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The mPDF download already shows a "Payments received" table (method +
 * amount per payment) on 3 of 4 layouts — but the on-screen/browser-print
 * view (documents.print.body, a separate Tailwind template from the mPDF
 * one) had no equivalent at all, which is what "no info of payment mode
 * cash/bank account on invoice" was actually pointing at once a fresh PDF
 * confirmed the download path itself was already fine.
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

    public function test_the_on_screen_invoice_shows_the_payments_received_table_on_bilingual_classic(): void
    {
        [$company, $owner] = $this->makeOwner();
        $company->invoiceTemplates()->updateOrCreate(['document_type' => 'invoice'], ['name' => 'Bilingual', 'layout' => 'bilingual_classic', 'is_default' => true]);
        $invoice = $this->makePaidInvoice($company);

        $response = $this->actingAs($owner)->get(route('app.invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee('Payments received');
        $response->assertSee(__('Bank transfer'));
        $response->assertSee('500.00');
    }

    public function test_the_on_screen_invoice_shows_the_payments_received_table_on_custom_letterhead(): void
    {
        [$company, $owner] = $this->makeOwner();
        $company->invoiceTemplates()->updateOrCreate(['document_type' => 'invoice'], ['name' => 'Letterhead', 'layout' => 'custom_letterhead', 'is_default' => true]);
        $invoice = $this->makePaidInvoice($company);

        $this->actingAs($owner)->get(route('app.invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Payments received')
            ->assertSee('500.00');
    }

    public function test_the_on_screen_invoice_shows_the_payments_received_table_on_minimal(): void
    {
        [$company, $owner] = $this->makeOwner();
        $company->invoiceTemplates()->updateOrCreate(['document_type' => 'invoice'], ['name' => 'Minimal', 'layout' => 'minimal', 'is_default' => true]);
        $invoice = $this->makePaidInvoice($company);

        $this->actingAs($owner)->get(route('app.invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Payments received')
            ->assertSee('500.00');
    }

    public function test_no_payments_table_when_nothing_has_been_paid(): void
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
            ->assertDontSee('Payments received');
    }
}
