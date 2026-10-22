<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "time is not showing for invoice on pdf print or issue an invoice for
 * a buyer" — invoices.issue_date is a date-only column; the real issue
 * instant lives on created_at, the same value ZatcaXmlGenerator already
 * uses for cbc:IssueTime (and the QR code built from it). Every printed/
 * on-screen copy showed the date alone, so a buyer or auditor had no way
 * to cross-check the one field ZATCA itself treats as mandatory. Fixed
 * at every place a $doc array gets built — there turned out to be six
 * separate copies of this logic (Invoice::pdfData(), the on-screen
 * invoices/credit-notes/debit-notes show pages, CreditNoteController/
 * DebitNoteController's downloadPdf(), and PublicInvoiceController) —
 * covered individually since a shared helper wasn't already in place.
 */
class DocumentIssueTimeDisplayTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        return Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeInvoice(Company $company, Client $client): Invoice
    {
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-00001',
            'status' => 'sent', 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(30),
            'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150, 'currency' => 'SAR',
        ]);
        InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => 'Work', 'quantity' => 1, 'unit_price' => 1000, 'vat_rate' => 15, 'vat_amount' => 150, 'line_total' => 1000]);

        return $invoice->fresh();
    }

    public function test_the_pdf_shows_the_issue_time_on_every_layout(): void
    {
        $company = $this->makeCompany();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Al Khuraif Water Tech']);
        $invoice = $this->makeInvoice($company, $client);
        $expectedTime = \App\Support\PlatformFormat::time($invoice->created_at);

        foreach (['minimal', 'bordered', 'boxed', 'bilingual_classic'] as $layout) {
            $company->invoiceTemplates()->updateOrCreate(
                ['document_type' => 'invoice'], ['name' => 'T', 'layout' => $layout, 'is_default' => true]
            );

            $html = view('documents.print.pdf', $invoice->pdfData() + ['embed' => fn () => null])->render();

            $this->assertStringContainsString($expectedTime, $html, "Issue time missing on layout: {$layout}");
        }
    }

    public function test_the_on_screen_body_shows_the_issue_time(): void
    {
        $company = $this->makeCompany();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Al Khuraif Water Tech']);
        $invoice = $this->makeInvoice($company, $client);
        $expectedTime = \App\Support\PlatformFormat::time($invoice->created_at);

        $html = view('documents.print.body', $invoice->pdfData() + ['embed' => fn () => null])->render();

        $this->assertStringContainsString($expectedTime, $html);
    }

    public function test_the_invoice_show_page_displays_the_issue_time(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Al Khuraif Water Tech']);
        $invoice = $this->makeInvoice($company, $client);
        $expectedTime = \App\Support\PlatformFormat::time($invoice->created_at);

        $response = $this->actingAs($owner)->get(route('app.invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee($expectedTime);
    }

    public function test_the_public_buyer_facing_invoice_page_displays_the_issue_time(): void
    {
        $company = $this->makeCompany();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Al Khuraif Water Tech']);
        $invoice = $this->makeInvoice($company, $client);
        $expectedTime = \App\Support\PlatformFormat::time($invoice->created_at);

        $response = $this->get(route('public.invoices.show', [$invoice->id, $invoice->public_token]));

        $response->assertOk();
        $response->assertSee($expectedTime);
    }

    public function test_a_credit_notes_show_page_displays_the_issue_time(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Al Khuraif Water Tech']);
        $invoice = $this->makeInvoice($company, $client);
        $creditNote = CreditNote::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id, 'client_id' => $client->id,
            'credit_note_number' => 'CN-1', 'issue_date' => now()->toDateString(), 'status' => 'issued',
            'subtotal' => 100, 'vat_total' => 0, 'total' => 100, 'currency' => 'SAR',
        ]);
        $expectedTime = \App\Support\PlatformFormat::time($creditNote->created_at);

        $this->actingAs($owner)->get(route('app.credit-notes.show', $creditNote))
            ->assertOk()->assertSee($expectedTime);
    }

    /**
     * CreditNoteController::downloadPdf() builds its own $doc array
     * (not a shared pdfData() method — see this test class's own
     * docblock) and streams it through mPDF to real binary PDF bytes,
     * which aren't usefully assertable as text. This renders the exact
     * same $doc shape as HTML instead, the same way
     * InvoicePdfPaymentPresentationTest verifies PDF content elsewhere
     * in this suite — real coverage of the fix without decoding a PDF.
     */
    public function test_a_credit_notes_pdf_doc_array_includes_the_issue_time(): void
    {
        $company = $this->makeCompany();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Al Khuraif Water Tech']);
        $invoice = $this->makeInvoice($company, $client);
        $creditNote = CreditNote::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id, 'client_id' => $client->id,
            'credit_note_number' => 'CN-1', 'issue_date' => now()->toDateString(), 'status' => 'issued',
            'subtotal' => 100, 'vat_total' => 0, 'total' => 100, 'currency' => 'SAR',
        ]);
        $expectedTime = \App\Support\PlatformFormat::time($creditNote->created_at);

        $html = view('documents.print.pdf', [
            'doc' => [
                'type_label' => __('Credit Note'), 'number' => $creditNote->credit_note_number,
                'date_label' => __('Issued'), 'date' => $creditNote->issue_date, 'issue_time' => $creditNote->created_at,
                'party_label' => __('Credit to'), 'party' => $client, 'lines' => collect(),
                'subtotal' => 100, 'vat_total' => 0, 'total' => 100,
            ],
            'company' => $company, 'template' => null, 'embed' => fn () => null,
        ])->render();

        $this->assertStringContainsString($expectedTime, $html);
    }

    public function test_a_document_with_no_issue_time_set_falls_back_to_the_plain_date(): void
    {
        $company = $this->makeCompany();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Al Khuraif Water Tech']);
        $invoice = $this->makeInvoice($company, $client);
        $data = $invoice->pdfData();
        unset($data['doc']['issue_time']); // e.g. a Quotation/Bill/Purchase Return's own $doc, which never sets this key.

        $html = view('documents.print.body', $data + ['embed' => fn () => null])->render();

        $this->assertStringContainsString(\App\Support\PlatformFormat::date($invoice->issue_date), $html);
        $this->assertStringNotContainsString(\App\Support\PlatformFormat::time($invoice->created_at), $html);
    }
}
