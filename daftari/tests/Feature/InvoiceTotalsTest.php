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
 * End-to-end invoice totals: real Company/Client/Invoice/InvoiceItem rows,
 * mixing a standard-rated, a zero-rated, and an exempt line on the same
 * invoice — the exact three cases Module 09 asks for, run through
 * Invoice::recalculateTotals() (unchanged by this module) and, for the
 * zero/exempt distinction specifically, ZatcaXmlGenerator's tax category
 * output.
 */
class InvoiceTotalsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Test Trading Co.', 'slug' => 'test-trading-'.uniqid(), 'vat_number' => '300012345600003']);
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);
        TaxRate::seedDefaults($this->company->id);
    }

    private function addLine(Invoice $invoice, float $qty, float $price, float $vatRate, ?string $taxType = null, int $sort = 0): InvoiceItem
    {
        $taxRateId = $taxType
            ? TaxRate::where('company_id', $this->company->id)->where('type', $taxType)->first()->id
            : null;

        $line = new InvoiceItem([
            'invoice_id' => $invoice->id,
            'description' => 'Line '.$sort,
            'quantity' => $qty,
            'unit_price' => $price,
            'vat_rate' => $vatRate,
            'tax_rate_id' => $taxRateId,
            'sort_order' => $sort,
        ]);
        $line->recalculate();
        $line->save();

        return $line;
    }

    private function makeInvoice(): Invoice
    {
        return Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'invoice_number' => 'TEST-'.uniqid(),
            'type' => 'standard',
            'issue_date' => now()->toDateString(),
            'currency' => 'SAR',
        ]);
    }

    public function test_invoice_totals_sum_correctly_across_standard_zero_and_exempt_lines(): void
    {
        $invoice = $this->makeInvoice();

        // Standard 15%: 200 net, 30 VAT, 230 total
        $this->addLine($invoice, 2, 100, 15, TaxRate::TYPE_STANDARD, 0);
        // Zero-rated: 50 net, 0 VAT
        $this->addLine($invoice, 1, 50, 0, TaxRate::TYPE_ZERO_RATED, 1);
        // Exempt: 30 net, 0 VAT
        $this->addLine($invoice, 1, 30, 0, TaxRate::TYPE_EXEMPT, 2);

        $invoice->refresh();
        $invoice->recalculateTotals();

        $this->assertSame(280.0, (float) $invoice->subtotal); // 200 + 50 + 30
        $this->assertSame(30.0, (float) $invoice->vat_total); // only the standard line
        $this->assertSame(310.0, (float) $invoice->total); // 280 + 30
    }

    public function test_invoice_total_deducts_a_document_level_discount(): void
    {
        $invoice = $this->makeInvoice();
        $this->addLine($invoice, 1, 100, 15, TaxRate::TYPE_STANDARD);

        $invoice->refresh();
        $invoice->discount_total = 10;
        $invoice->recalculateTotals();

        // subtotal 100, vat 15, minus 10 discount = 105
        $this->assertSame(100.0, (float) $invoice->subtotal);
        $this->assertSame(15.0, (float) $invoice->vat_total);
        $this->assertSame(105.0, (float) $invoice->total);
    }

    public function test_balance_due_reflects_partial_payment(): void
    {
        $invoice = $this->makeInvoice();
        $this->addLine($invoice, 1, 100, 15, TaxRate::TYPE_STANDARD);
        $invoice->refresh();
        $invoice->recalculateTotals();

        $invoice->invoicePayments()->create([
            'amount' => 50,
            'paid_at' => now()->toDateString(),
            'method' => 'bank_transfer',
        ]);
        $invoice->recalculateTotals();

        $this->assertSame(65.0, $invoice->balanceDue()); // 115 total - 50 paid
    }

    public function test_zatca_xml_distinguishes_zero_rated_from_exempt_lines(): void
    {
        $invoice = $this->makeInvoice();
        $this->addLine($invoice, 1, 100, 15, TaxRate::TYPE_STANDARD, 0);
        $this->addLine($invoice, 1, 50, 0, TaxRate::TYPE_ZERO_RATED, 1);
        $this->addLine($invoice, 1, 30, 0, TaxRate::TYPE_EXEMPT, 2);

        $invoice->refresh();
        $invoice->recalculateTotals();
        $invoice->load('items.taxRate', 'company', 'client');

        $xml = (new ZatcaXmlGenerator())->generate($invoice, '0100000', null, 'test-uuid', 1);

        preg_match_all('/<cbc:ID>(S|Z|E)<\/cbc:ID>/', $xml, $matches);

        // The 3 per-line ClassifiedTaxCategory codes must be S, Z, E in
        // that order — not S, Z, Z (which is what conflating exempt into
        // "any 0% line is zero-rated" would have produced).
        $this->assertSame(['S', 'Z', 'E'], array_slice($matches[1], 0, 3));
    }

    public function test_zatca_xml_falls_back_correctly_for_lines_with_no_linked_tax_rate(): void
    {
        // Pre-tax-rate-management data: tax_rate_id is null, only vat_rate
        // is set — must still classify sanely (old ">0 ⇒ S" heuristic).
        $invoice = $this->makeInvoice();
        $this->addLine($invoice, 1, 100, 15, null, 0);
        $this->addLine($invoice, 1, 50, 0, null, 1);

        $invoice->refresh();
        $invoice->recalculateTotals();
        $invoice->load('items.taxRate', 'company', 'client');

        $xml = (new ZatcaXmlGenerator())->generate($invoice, '0100000', null, 'test-uuid-2', 1);
        preg_match_all('/<cbc:ID>(S|Z|E)<\/cbc:ID>/', $xml, $matches);

        $this->assertSame(['S', 'Z'], array_slice($matches[1], 0, 2));
    }
}
