<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceTemplate;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Distribution Classic" — a user shared a real wholesale/trading
 * company's tax invoice (dashed-border info grid, item-code column, a
 * three-box totals grid ending in a running customer balance, bilingual
 * amount-in-words bar, IBAN strip, Received-By/Issued-By signature row)
 * and asked for it built "100% same design". Covers both rendering paths
 * this app maintains in parallel: the browser-rendered show page
 * (documents.print.body, Tailwind) and the downloaded PDF
 * (documents.print.pdf, mPDF table markup) — same preset, same layout
 * key, two independent template files that must each compile and carry
 * the document's real numbers.
 */
class DistributionClassicInvoiceTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        return Company::create(['name' => 'Al Rahwah Est.', 'slug' => 'rahwah-'.uniqid(), 'vat_number' => '300124664400003', 'cr_number' => '4030068065']);
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeInvoice(Company $company): Invoice
    {
        $client = Client::create([
            'company_id' => $company->id, 'name' => 'Al Jazira Capital', 'name_ar' => 'الجزيرة كابيتال',
            'vat_number' => '300059765400003', 'phone' => '0555000111',
        ]);
        $bankAccount = BankAccount::create([
            'company_id' => $company->id, 'name' => 'Main', 'bank_name' => 'Saudi National Bank',
            'account_number' => '1327070300010', 'iban' => 'SA3010000001327070300010',
        ]);
        $company->update(['default_bank_account_id' => $bankAccount->id]);
        $item = Item::create(['company_id' => $company->id, 'name' => 'White mattress', 'sku' => '102288', 'unit_price' => 450]);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => '78744',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(),
            'currency' => $company->currency, 'subtotal' => 450, 'vat_total' => 67.5, 'total' => 517.5,
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'item_id' => $item->id, 'description' => 'White mattress',
            'quantity' => 1, 'unit_price' => 450, 'vat_rate' => 15, 'vat_amount' => 67.5, 'line_total' => 517.5,
        ]);

        return $invoice;
    }

    public function test_activating_the_preset_from_the_gallery_applies_it_everywhere(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->post(route('app.invoice-templates.gallery.activate', 'distribution_classic'));

        $response->assertRedirect();
        $this->assertSame('distribution_classic', $company->invoiceTemplates()->where('document_type', 'all')->where('is_default', true)->value('layout'));
    }

    public function test_the_gallery_preview_renders_with_sample_data(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->get(route('app.invoice-templates.gallery.preview', 'distribution_classic'));

        $response->assertOk();
        $response->assertSee('INV-0001');
    }

    public function test_the_invoice_show_page_renders_every_real_field_through_this_layout(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Distribution Classic', 'document_type' => 'all',
            'layout' => 'distribution_classic', 'is_default' => true,
        ]);
        $invoice = $this->makeInvoice($company);

        $response = $this->actingAs($owner)->get(route('app.invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee('78744');
        $response->assertSee('Al Jazira Capital');
        $response->assertSee('102288');
        $response->assertSee('517.50');
        $response->assertSee('SA3010000001327070300010');
    }

    public function test_the_pdf_download_renders_through_the_mpdf_counterpart(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Distribution Classic', 'document_type' => 'all',
            'layout' => 'distribution_classic', 'is_default' => true,
        ]);
        $invoice = $this->makeInvoice($company);

        $response = $this->actingAs($owner)->get(route('app.invoices.pdf', $invoice));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_the_layout_still_renders_cleanly_with_no_client_vat_or_bank_account_on_file(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Distribution Classic', 'document_type' => 'all',
            'layout' => 'distribution_classic', 'is_default' => true,
        ]);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Bare Client']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-BARE-1',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(),
            'currency' => $company->currency, 'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'description' => 'Unlinked line', 'quantity' => 1,
            'unit_price' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'line_total' => 115,
        ]);

        $showResponse = $this->actingAs($owner)->get(route('app.invoices.show', $invoice));
        $pdfResponse = $this->actingAs($owner)->get(route('app.invoices.pdf', $invoice));

        $showResponse->assertOk();
        $pdfResponse->assertOk();
    }
}
