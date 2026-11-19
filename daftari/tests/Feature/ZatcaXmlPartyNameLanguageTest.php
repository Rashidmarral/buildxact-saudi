<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\Zatca\ZatcaXmlGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "why the only English name coming in xml for buyer and seller arabic
 * name is not coming" — confirmed: every cac:PartyLegalEntity/
 * cbc:RegistrationName in the generated XML always used $company->name /
 * $client->name, never name_ar. UBL allows exactly one registered name
 * per party (no separate English/Arabic slots), so this submits the
 * Arabic name — the one registered on the Commercial Registration, which
 * is what ZATCA's own backend is most likely to cross-check against —
 * when one is on file, falling back to English only when it isn't.
 */
class ZatcaXmlPartyNameLanguageTest extends TestCase
{
    use RefreshDatabase;

    private function makeInvoice(Company $company, Client $client): Invoice
    {
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'XML-NAME-'.uniqid(),
            'type' => 'standard', 'issue_date' => now()->toDateString(), 'currency' => 'SAR',
        ]);
        $line = new InvoiceItem(['invoice_id' => $invoice->id, 'description' => 'Line 1', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 15, 'sort_order' => 0]);
        $line->recalculate();
        $line->save();
        $invoice->refresh();
        $invoice->recalculateTotals();
        $invoice->load('items.taxRate', 'company', 'client');

        return $invoice;
    }

    public function test_the_arabic_seller_and_buyer_names_are_used_when_on_file(): void
    {
        $company = Company::create(['name' => 'Al-Zubaidi Est.', 'name_ar' => 'مؤسسة الزبيدي', 'slug' => 'zubaidi-'.uniqid(), 'vat_number' => '300012345600003']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Al Jazira Capital', 'name_ar' => 'الجزيرة كابيتال', 'vat_number' => '300059765400003']);
        $invoice = $this->makeInvoice($company, $client);

        $xml = (new ZatcaXmlGenerator())->generate($invoice, '0100000', null, 'test-uuid-name-1', 1);

        $this->assertStringContainsString('<cbc:RegistrationName>مؤسسة الزبيدي</cbc:RegistrationName>', $xml);
        $this->assertStringContainsString('<cbc:RegistrationName>الجزيرة كابيتال</cbc:RegistrationName>', $xml);
        $this->assertStringNotContainsString('Al-Zubaidi Est.', $xml);
        $this->assertStringNotContainsString('Al Jazira Capital', $xml);
    }

    public function test_english_name_is_used_as_a_fallback_when_no_arabic_name_is_on_file(): void
    {
        $company = Company::create(['name' => 'No Arabic Co.', 'slug' => 'no-arabic-'.uniqid(), 'vat_number' => '300012345600003']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'English Only Client', 'vat_number' => '300059765400003']);
        $invoice = $this->makeInvoice($company, $client);

        $xml = (new ZatcaXmlGenerator())->generate($invoice, '0100000', null, 'test-uuid-name-2', 1);

        $this->assertStringContainsString('<cbc:RegistrationName>No Arabic Co.</cbc:RegistrationName>', $xml);
        $this->assertStringContainsString('<cbc:RegistrationName>English Only Client</cbc:RegistrationName>', $xml);
    }
}
