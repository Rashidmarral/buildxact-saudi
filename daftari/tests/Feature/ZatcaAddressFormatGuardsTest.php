<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\Zatca\ZatcaSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ZATCA compliance audit against the official spec PDF:
 *
 * - BR-KSA-37/66: the seller's own building number/postal code, once set
 *   at all, must be exactly 4/5 digits.
 * - BR-KSA-63/67: once a buyer's XML carries a country code of "SA" —
 *   which ZatcaXmlGenerator::party() only emits alongside a PostalAddress,
 *   itself only built once ANY one of street/building number/district/
 *   city/postal code is on file — every one of those five fields becomes
 *   mandatory, and the postal code must be 5 digits.
 *
 * Both are format/completeness rules on data that was already optional,
 * not new blanket mandatory fields: a company or client with none of
 * these fields on file at all is unaffected either way, matching the
 * spec's own conditional wording and avoiding retroactively blocking
 * sync for every existing document with an incomplete profile.
 */
class ZatcaAddressFormatGuardsTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(array $overrides = []): Company
    {
        return Company::create(array_merge([
            'name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active',
            'currency' => 'SAR', 'vat_number' => '300012345600003',
        ], $overrides));
    }

    private function makeInvoice(Company $company, Client $client): Invoice
    {
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-'.uniqid(),
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(), 'currency' => 'SAR',
        ]);

        $item = new InvoiceItem(['invoice_id' => $invoice->id, 'description' => 'Work', 'quantity' => 1, 'unit_price' => 500, 'vat_rate' => 15, 'sort_order' => 0]);
        $item->recalculate();
        $item->save();

        $invoice->refresh();
        $invoice->recalculateTotals();
        $invoice->load('items.taxRate', 'company', 'client');

        return $invoice;
    }

    private function identifiedClient(Company $company, array $overrides = []): Client
    {
        return Client::create(array_merge([
            'company_id' => $company->id, 'name' => 'Registered Buyer', 'vat_number' => '300098765400003',
        ], $overrides));
    }

    public function test_a_company_with_no_building_number_or_postal_code_on_file_is_unaffected(): void
    {
        $company = $this->makeCompany();
        $invoice = $this->makeInvoice($company, $this->identifiedClient($company));

        $log = app(ZatcaSyncService::class)->submit($invoice);

        $this->assertSame('failed', $log->status);
        $this->assertStringNotContainsString('building number', $log->error_message);
        $this->assertStringNotContainsString('postal code', $log->error_message);
    }

    public function test_a_company_building_number_that_is_not_4_digits_blocks_sync(): void
    {
        $company = $this->makeCompany(['building_number' => '123']);
        $invoice = $this->makeInvoice($company, $this->identifiedClient($company));

        $log = app(ZatcaSyncService::class)->submit($invoice);

        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('building number must be exactly 4 digits', $log->error_message);
        $this->assertFalse($log->exists, 'The rejection must not persist a ZatcaInvoiceLog row.');
    }

    public function test_a_company_postal_code_that_is_not_5_digits_blocks_sync(): void
    {
        $company = $this->makeCompany(['building_number' => '1234', 'postal_code' => '123']);
        $invoice = $this->makeInvoice($company, $this->identifiedClient($company));

        $log = app(ZatcaSyncService::class)->submit($invoice);

        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('postal code must be exactly 5 digits', $log->error_message);
    }

    public function test_a_correctly_formatted_company_address_passes_the_guard(): void
    {
        $company = $this->makeCompany(['building_number' => '1234', 'postal_code' => '12345']);
        $invoice = $this->makeInvoice($company, $this->identifiedClient($company));

        $log = app(ZatcaSyncService::class)->submit($invoice);

        // Not onboarded with ZATCA in this test, so it still fails — but
        // not for a seller address format reason.
        $this->assertSame('failed', $log->status);
        $this->assertStringNotContainsString('building number', $log->error_message);
        $this->assertStringNotContainsString('postal code', $log->error_message);
    }

    public function test_a_client_with_no_address_fields_at_all_is_unaffected(): void
    {
        $company = $this->makeCompany();
        $client = $this->identifiedClient($company);
        $invoice = $this->makeInvoice($company, $client);

        $log = app(ZatcaSyncService::class)->submit($invoice);

        $this->assertSame('failed', $log->status);
        $this->assertStringNotContainsString('partial address', $log->error_message);
        $this->assertStringNotContainsString('postal code', $log->error_message);
    }

    public function test_a_client_with_only_a_city_set_is_rejected_for_a_partial_address(): void
    {
        $company = $this->makeCompany();
        $client = $this->identifiedClient($company, ['city' => 'Riyadh']);
        $invoice = $this->makeInvoice($company, $client);

        $log = app(ZatcaSyncService::class)->submit($invoice);

        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('partial address', $log->error_message);
        $this->assertStringContainsString('street', $log->error_message);
        $this->assertStringContainsString('postal code', $log->error_message);
    }

    public function test_a_client_with_a_complete_address_but_a_malformed_postal_code_is_rejected(): void
    {
        $company = $this->makeCompany();
        $client = $this->identifiedClient($company, [
            'street_name' => 'King Fahd Rd', 'building_number' => '7712', 'district' => 'Al Olaya',
            'city' => 'Riyadh', 'postal_code' => '123',
        ]);
        $invoice = $this->makeInvoice($company, $client);

        $log = app(ZatcaSyncService::class)->submit($invoice);

        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('not exactly 5 digits', $log->error_message);
    }

    public function test_a_client_with_a_complete_and_correctly_formatted_address_passes_the_guard(): void
    {
        $company = $this->makeCompany();
        $client = $this->identifiedClient($company, [
            'street_name' => 'King Fahd Rd', 'building_number' => '7712', 'district' => 'Al Olaya',
            'city' => 'Riyadh', 'postal_code' => '12345',
        ]);
        $invoice = $this->makeInvoice($company, $client);

        $log = app(ZatcaSyncService::class)->submit($invoice);

        $this->assertSame('failed', $log->status);
        $this->assertStringNotContainsString('partial address', $log->error_message);
        $this->assertStringNotContainsString('5 digits', $log->error_message);
    }
}
