<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ZatcaInvoiceLog;
use App\Services\Zatca\ZatcaSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ZATCA compliance audit against the official spec PDF (BR-KSA-81): a
 * standard (B2B) tax invoice's buyer must carry the Other Buyer ID
 * (national ID/Iqama/passport/GCC ID) whenever it has no VAT registration
 * number. ZatcaXmlGenerator::party() only emits a PartyIdentification
 * element when one of those is actually set on the client, so nothing
 * previously stopped a standard invoice for an unidentified buyer from
 * reaching ZATCA with neither — caught here, same pattern as the VAT
 * exemption reason guard.
 */
class ZatcaBuyerIdentificationGuardTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        return Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_number' => '300012345600003']);
    }

    private function makeInvoice(Company $company, Client $client, string $type = 'standard'): Invoice
    {
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-'.uniqid(),
            'type' => $type, 'status' => 'sent', 'issue_date' => now()->toDateString(), 'currency' => 'SAR',
        ]);

        $item = new InvoiceItem(['invoice_id' => $invoice->id, 'description' => 'Work', 'quantity' => 1, 'unit_price' => 500, 'vat_rate' => 15, 'sort_order' => 0]);
        $item->recalculate();
        $item->save();

        $invoice->refresh();
        $invoice->recalculateTotals();
        $invoice->load('items.taxRate', 'company', 'client');

        return $invoice;
    }

    public function test_a_standard_invoice_for_a_client_with_neither_vat_number_nor_other_id_is_rejected(): void
    {
        $company = $this->makeCompany();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Unidentified Buyer']);
        $invoice = $this->makeInvoice($company, $client);

        $log = app(ZatcaSyncService::class)->submit($invoice);

        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('no other buyer ID', $log->error_message);
        $this->assertFalse($log->exists, 'The rejection must not persist a ZatcaInvoiceLog row.');
    }

    public function test_a_standard_invoice_for_a_client_with_a_vat_number_passes_the_guard(): void
    {
        $company = $this->makeCompany();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Registered Buyer', 'vat_number' => '300098765400003']);
        $invoice = $this->makeInvoice($company, $client);

        $log = app(ZatcaSyncService::class)->submit($invoice);

        // Not onboarded with ZATCA in this test, so it still fails — but
        // not for a missing buyer-ID reason.
        $this->assertSame('failed', $log->status);
        $this->assertStringNotContainsString('buyer ID', $log->error_message);
    }

    public function test_a_standard_invoice_for_a_client_with_an_other_id_but_no_vat_number_passes_the_guard(): void
    {
        $company = $this->makeCompany();
        $client = Client::create([
            'company_id' => $company->id, 'name' => 'National ID Buyer',
            'additional_id_type' => 'national_id', 'additional_id_number' => '1012345678',
        ]);
        $invoice = $this->makeInvoice($company, $client);

        $log = app(ZatcaSyncService::class)->submit($invoice);

        $this->assertSame('failed', $log->status);
        $this->assertStringNotContainsString('buyer ID', $log->error_message);
    }

    public function test_a_simplified_b2c_invoice_for_an_unidentified_buyer_is_not_blocked_by_this_guard(): void
    {
        $company = $this->makeCompany();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Walk-in Customer']);
        $invoice = $this->makeInvoice($company, $client, type: 'simplified');

        $log = app(ZatcaSyncService::class)->submit($invoice);

        $this->assertSame('failed', $log->status);
        $this->assertStringNotContainsString('buyer ID', $log->error_message);
    }

    public function test_a_credit_note_against_a_standard_invoice_for_an_unidentified_buyer_is_rejected(): void
    {
        $company = $this->makeCompany();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Unidentified Buyer']);
        $invoice = $this->makeInvoice($company, $client);
        // The credit note's guard is what's under test — its own buyer
        // identification, independent of the original invoice — so the
        // original invoice just needs to be ZATCA-locked, which
        // rejectUnsubmittableCreditNote() requires before it even reaches
        // this guard (see ZatcaCorrectionDocumentGuardsTest).
        ZatcaInvoiceLog::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id,
            'environment' => 'production', 'invoice_type' => 'b2b', 'direction' => 'clearance',
            'status' => 'cleared', 'request_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'xml_payload' => '<Invoice></Invoice>', 'submitted_at' => now(), 'cleared_at' => now(),
        ]);
        $creditNote = CreditNote::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id, 'client_id' => $client->id,
            'credit_note_number' => 'CN-'.uniqid(), 'issue_date' => now()->toDateString(), 'status' => 'issued',
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115, 'currency' => 'SAR',
        ]);
        CreditNoteItem::create(['credit_note_id' => $creditNote->id, 'description' => 'Refund', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'line_total' => 100]);

        $log = app(ZatcaSyncService::class)->submitCreditNote($creditNote);

        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('no other buyer ID', $log->error_message);
    }
}
