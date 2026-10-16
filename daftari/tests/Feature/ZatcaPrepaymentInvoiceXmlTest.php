<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PrepaymentInvoice;
use App\Models\TaxRate;
use App\Services\Zatca\ZatcaXmlGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ZATCA compliance gap found during an audit: a prepayment/advance
 * collected before the main supply must be issued as its own formal
 * "prepayment tax invoice" (InvoiceTypeCode 386) — VAT is due on the
 * advance the moment it's received. Previously Daftari had no way to
 * issue one at all (self-documented at the top of
 * ZatcaXmlGenerator::generate(), in the old PrepaidAmount comment).
 *
 * These tests cover the two new pieces: generateForPrepayment() itself
 * (the 386 document), and generate()'s $appliedPrepayment handling (the
 * later, final invoice that settles it — PrepaidAmount/PayableAmount
 * and the BR-KSA-73/80 document reference back to the 386 invoice).
 */
class ZatcaPrepaymentInvoiceXmlTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Test Trading Co.', 'slug' => 'test-trading-'.uniqid(),
            'vat_number' => '300012345600003', 'cr_number' => 'CRN12345',
        ]);
        TaxRate::seedDefaults($this->company->id);
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Advance Client LLC', 'vat_number' => '399999999900003']);
    }

    private function makePrepayment(float $amount = 10000): PrepaymentInvoice
    {
        $standardRate = TaxRate::where('company_id', $this->company->id)->where('type', TaxRate::TYPE_STANDARD)->first();

        return PrepaymentInvoice::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'tax_rate_id' => $standardRate->id, 'prepayment_number' => 'PPI-00001', 'type' => 'standard',
            'status' => 'issued', 'issue_date' => now()->toDateString(), 'description' => 'Advance against Contract 2026-014',
            'amount' => $amount, 'vat_rate' => 15, 'vat_amount' => $amount * 0.15, 'total' => $amount * 1.15, 'currency' => 'SAR',
        ]);
    }

    public function test_the_prepayment_xml_carries_type_code_386_and_the_advance_amount(): void
    {
        $prepayment = $this->makePrepayment(10000)->fresh();
        $prepayment->load('client', 'company', 'taxRate');

        $xml = app(ZatcaXmlGenerator::class)->generateForPrepayment($prepayment, '0100000', null, (string) \Illuminate\Support\Str::uuid());

        $this->assertStringContainsString('<cbc:InvoiceTypeCode name="0100000">386</cbc:InvoiceTypeCode>', $xml);
        $this->assertStringContainsString('<cbc:ID>PPI-00001</cbc:ID>', $xml);
        $this->assertStringContainsString('<cbc:LineExtensionAmount currencyID="SAR">10000.00</cbc:LineExtensionAmount>', $xml);
        $this->assertStringContainsString('<cbc:TaxInclusiveAmount currencyID="SAR">11500.00</cbc:TaxInclusiveAmount>', $xml);
        $this->assertStringContainsString('<cbc:PayableAmount currencyID="SAR">11500.00</cbc:PayableAmount>', $xml);
        $this->assertStringContainsString('Advance against Contract 2026-014', $xml);
        // No BillingReference — a prepayment invoice is issued standalone,
        // it doesn't correct another document the way a credit/debit note does.
        $this->assertStringNotContainsString('cac:BillingReference', $xml);
    }

    public function test_a_final_invoice_that_applies_a_prepayment_reports_the_reduced_payable_amount(): void
    {
        $prepayment = $this->makePrepayment(10000)->fresh();
        $prepayment->load('client', 'company', 'taxRate');

        $invoice = Invoice::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'applies_prepayment_invoice_id' => $prepayment->id,
            'invoice_number' => 'INV-00001', 'type' => 'standard', 'status' => 'sent',
            'issue_date' => now()->toDateString(), 'currency' => 'SAR',
        ]);
        $line = new InvoiceItem([
            'invoice_id' => $invoice->id, 'description' => 'Full contract value', 'quantity' => 1,
            'unit_price' => 50000, 'vat_rate' => 15, 'sort_order' => 0,
        ]);
        $line->recalculate();
        $line->save();
        $invoice->refresh();
        $invoice->recalculateTotals();
        $invoice->load('items.taxRate', 'company', 'client', 'appliesPrepaymentInvoice');

        $xml = app(ZatcaXmlGenerator::class)->generate($invoice, '0100000', null, (string) \Illuminate\Support\Str::uuid(), 1, $invoice->appliesPrepaymentInvoice);

        // Invoice total is 57500 (50000 + 15% VAT); the prepayment already
        // settled 11500 of that, so only 46000 is still payable.
        $this->assertStringContainsString('<cbc:TaxInclusiveAmount currencyID="SAR">57500.00</cbc:TaxInclusiveAmount>', $xml);
        $this->assertStringContainsString('<cbc:PrepaidAmount currencyID="SAR">11500.00</cbc:PrepaidAmount>', $xml);
        $this->assertStringContainsString('<cbc:PayableAmount currencyID="SAR">46000.00</cbc:PayableAmount>', $xml);
        $this->assertStringContainsString('<cbc:ID>PPI-00001</cbc:ID>', $xml);
        $this->assertStringContainsString('<cbc:DocumentTypeCode>386</cbc:DocumentTypeCode>', $xml);
    }

    public function test_a_final_invoice_with_no_applied_prepayment_is_unaffected(): void
    {
        $invoice = Invoice::create([
            'company_id' => $this->company->id, 'client_id' => $this->client->id,
            'invoice_number' => 'INV-00002', 'type' => 'standard', 'status' => 'sent',
            'issue_date' => now()->toDateString(), 'currency' => 'SAR',
        ]);
        $line = new InvoiceItem([
            'invoice_id' => $invoice->id, 'description' => 'Full contract value', 'quantity' => 1,
            'unit_price' => 50000, 'vat_rate' => 15, 'sort_order' => 0,
        ]);
        $line->recalculate();
        $line->save();
        $invoice->refresh();
        $invoice->recalculateTotals();
        $invoice->load('items.taxRate', 'company', 'client');

        $xml = app(ZatcaXmlGenerator::class)->generate($invoice, '0100000', null, (string) \Illuminate\Support\Str::uuid());

        $this->assertStringContainsString('<cbc:PrepaidAmount currencyID="SAR">0.00</cbc:PrepaidAmount>', $xml);
        $this->assertStringContainsString('<cbc:PayableAmount currencyID="SAR">57500.00</cbc:PayableAmount>', $xml);
        $this->assertStringNotContainsString('<cbc:DocumentTypeCode>386</cbc:DocumentTypeCode>', $xml);
    }
}
