<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\TaxRate;
use App\Services\Zatca\ZatcaXmlGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Confirms the invoice XML carries every field a real ZATCA-cleared
 * invoice (downloaded from a working Ultimate POS ZATCA installation's
 * synced records) carries, which our generator previously either omitted
 * entirely (line-level discount AllowanceCharge, PrepaidAmount, buyer
 * Additional ID) or only emitted conditionally (document-level
 * AllowanceCharge, skipped whenever discount_total was 0).
 */
class ZatcaXmlGeneratorFieldsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Test Trading Co.', 'slug' => 'test-trading-'.uniqid(), 'vat_number' => '300012345600003']);
        TaxRate::seedDefaults($this->company->id);
    }

    private function makeInvoiceWithLine(Client $client, float $discountTotal = 0): Invoice
    {
        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'invoice_number' => 'TEST-'.uniqid(),
            'type' => 'standard',
            'issue_date' => now()->toDateString(),
            'currency' => 'SAR',
            'discount_total' => $discountTotal,
        ]);

        $line = new InvoiceItem([
            'invoice_id' => $invoice->id,
            'description' => 'Line 1',
            'quantity' => 1,
            'unit_price' => 100,
            'vat_rate' => 15,
            'sort_order' => 0,
        ]);
        $line->recalculate();
        $line->save();

        $invoice->refresh();
        $invoice->recalculateTotals();
        $invoice->load('items.taxRate', 'company', 'client');

        return $invoice;
    }

    public function test_document_level_allowance_charge_is_always_present_even_with_no_discount(): void
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'No Discount Client']);
        $invoice = $this->makeInvoiceWithLine($client, 0);

        $xml = (new ZatcaXmlGenerator())->generate($invoice, '0100000', null, 'test-uuid-1', 1);

        $this->assertStringContainsString('<cac:AllowanceCharge><cbc:ID>1</cbc:ID><cbc:ChargeIndicator>false</cbc:ChargeIndicator><cbc:AllowanceChargeReason>discount</cbc:AllowanceChargeReason><cbc:Amount currencyID="SAR">0.00</cbc:Amount>', $xml);
        $this->assertStringContainsString('schemeAgencyID="6"', $xml);
        $this->assertStringContainsString('schemeID="UN/ECE 5305"', $xml);
    }

    public function test_each_invoice_line_carries_a_price_level_allowance_charge(): void
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Line Discount Client']);
        $invoice = $this->makeInvoiceWithLine($client, 0);

        $xml = (new ZatcaXmlGenerator())->generate($invoice, '0100000', null, 'test-uuid-2', 1);

        preg_match('/<cac:Price>.*?<\/cac:Price>/s', $xml, $matches);
        $this->assertNotEmpty($matches);
        $this->assertStringContainsString('<cac:AllowanceCharge><cbc:ChargeIndicator>false</cbc:ChargeIndicator><cbc:AllowanceChargeReason>Applied Discount</cbc:AllowanceChargeReason><cbc:Amount currencyID="SAR">0.00</cbc:Amount></cac:AllowanceCharge>', $matches[0]);
    }

    public function test_legal_monetary_total_carries_prepaid_amount(): void
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Prepaid Amount Client']);
        $invoice = $this->makeInvoiceWithLine($client, 0);

        $xml = (new ZatcaXmlGenerator())->generate($invoice, '0100000', null, 'test-uuid-3', 1);

        preg_match('/<cac:LegalMonetaryTotal>.*?<\/cac:LegalMonetaryTotal>/s', $xml, $matches);
        $this->assertNotEmpty($matches);
        $this->assertStringContainsString('<cbc:PrepaidAmount currencyID="SAR">0.00</cbc:PrepaidAmount>', $matches[0]);
    }

    /**
     * The bug this closes: a client with no VAT number (a common case for
     * simplified/B2C buyers, or a standard buyer identified by National
     * ID/Iqama/Passport/GCC ID instead) previously produced an
     * AccountingCustomerParty with neither cac:PartyIdentification nor
     * cac:PartyTaxScheme at all — Client::additional_id_type/number were
     * captured on the client form but never reached the XML.
     */
    public function test_buyer_without_vat_number_carries_additional_id_as_party_identification(): void
    {
        $client = Client::create([
            'company_id' => $this->company->id,
            'name' => 'No VAT Client',
            'is_vat_registered' => false,
            'additional_id_type' => 'national_id',
            'additional_id_number' => '1012345678',
        ]);
        $invoice = $this->makeInvoiceWithLine($client, 0);

        $xml = (new ZatcaXmlGenerator())->generate($invoice, '0200000', null, 'test-uuid-4', 1);

        preg_match('/<cac:AccountingCustomerParty>.*?<\/cac:AccountingCustomerParty>/s', $xml, $matches);
        $this->assertNotEmpty($matches);
        $this->assertStringContainsString('<cac:PartyIdentification><cbc:ID schemeID="NAT">1012345678</cbc:ID></cac:PartyIdentification>', $matches[0]);
    }
}
