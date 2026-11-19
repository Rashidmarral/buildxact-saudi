<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceTemplate;
use App\Models\User;
use App\Models\ZatcaInvoiceLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two findings from a user-reported PDF: (1) the Seller/Customer address
 * boxes on "Retail Classic" (and "Sage Classic", "Distribution Classic")
 * printed the party's English name only, even with a name_ar on file and
 * the template in bilingual mode — $primary() alone only ever picks ONE
 * language, and these single-line label/value rows had nowhere to put
 * $secondary()'s usual sub-line fallback. (2) once an invoice actually
 * clears ZATCA, a "ZATCA Cleared"/"ZATCA Reported" text pill appeared
 * right next to the QR code on every printed layout — removed per
 * request, so the QR prints alone.
 */
class BilingualPartyNameAndZatcaBadgeTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        return Company::create(['name' => 'Bilingual Co.', 'slug' => 'bilingual-'.uniqid(), 'name_ar' => 'شركة ثنائية اللغة']);
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeInvoice(Company $company, string $layout): Invoice
    {
        InvoiceTemplate::create([
            'company_id' => $company->id, 'name' => 'Test', 'document_type' => 'all',
            'layout' => $layout, 'is_default' => true, 'language_mode' => 'bilingual',
        ]);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Al Jazira Capital', 'name_ar' => 'الجزيرة كابيتال']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-BIL-1',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(),
            'currency' => $company->currency, 'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'description' => 'Line', 'quantity' => 1,
            'unit_price' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'line_total' => 115,
        ]);

        return $invoice;
    }

    public function test_retail_classic_shows_the_customers_arabic_name_alongside_english(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $invoice = $this->makeInvoice($company, 'retail_classic');

        $showResponse = $this->actingAs($owner)->get(route('app.invoices.show', $invoice));
        $pdfResponse = $this->actingAs($owner)->get(route('app.invoices.pdf', $invoice));

        $showResponse->assertOk()->assertSee('Al Jazira Capital / الجزيرة كابيتال');
        $pdfResponse->assertOk();
    }

    public function test_sage_classic_shows_the_customers_arabic_name_alongside_english(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $invoice = $this->makeInvoice($company, 'sage_classic');

        $response = $this->actingAs($owner)->get(route('app.invoices.show', $invoice));

        $response->assertOk()->assertSee('Al Jazira Capital / الجزيرة كابيتال');
    }

    public function test_distribution_classic_shows_the_customers_arabic_name_alongside_english(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $invoice = $this->makeInvoice($company, 'distribution_classic');

        $response = $this->actingAs($owner)->get(route('app.invoices.show', $invoice));

        $response->assertOk()->assertSee('Al Jazira Capital / الجزيرة كابيتال');
    }

    public function test_no_zatca_cleared_badge_renders_next_to_the_qr_code_on_any_layout(): void
    {
        foreach (['minimal', 'bordered', 'boxed', 'retail_classic', 'sage_classic', 'distribution_classic'] as $layout) {
            $company = $this->makeCompany();
            $owner = $this->makeOwner($company);
            $invoice = $this->makeInvoice($company, $layout);
            $invoice->update(['qr_code' => base64_encode('fake-qr-payload')]);
            ZatcaInvoiceLog::create([
                'company_id' => $company->id, 'invoice_id' => $invoice->id,
                'environment' => 'simulation', 'invoice_type' => 'b2b', 'direction' => 'clearance',
                'status' => 'cleared', 'request_uuid' => (string) \Illuminate\Support\Str::uuid(),
            ]);

            $showResponse = $this->actingAs($owner)->get(route('app.invoices.show', $invoice));
            $pdfResponse = $this->actingAs($owner)->get(route('app.invoices.pdf', $invoice));

            $showResponse->assertOk()->assertDontSee('ZATCA Cleared')->assertDontSee(__('ZATCA Cleared'));
            $pdfResponse->assertOk();
        }
    }
}
