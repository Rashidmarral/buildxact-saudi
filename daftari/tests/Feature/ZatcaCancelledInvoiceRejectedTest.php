<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\ZatcaInvoiceLog;
use App\Services\Zatca\ZatcaSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Compliance review finding: cancelling an invoice and syncing it
 * individually (the one-off "Sync now"/admin "Retry" button, which has no
 * status check of its own) reached ZatcaXmlGenerator::generate(), which
 * emitted InvoiceTypeCode 381 (credit note) for a cancelled invoice with
 * no cac:BillingReference back to the original — not a valid way to void
 * a document under ZATCA's rules, which requires a real credit note.
 * ZatcaSyncService::submit() now refuses a draft or cancelled invoice
 * before building any XML or touching the ICV/PIH chain, and the
 * generator itself always emits 388 regardless, as defense in depth.
 */
class ZatcaCancelledInvoiceRejectedTest extends TestCase
{
    use RefreshDatabase;

    private function makeInvoice(string $status): Invoice
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'vat_number' => '300012345600003']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client']);

        return Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-'.uniqid(),
            'type' => 'standard', 'status' => $status, 'issue_date' => now()->toDateString(),
            'currency' => 'SAR', 'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);
    }

    public function test_a_cancelled_invoice_is_rejected_before_any_xml_or_log_row_is_created(): void
    {
        $invoice = $this->makeInvoice('cancelled');

        $log = app(ZatcaSyncService::class)->submit($invoice);

        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('cancelled', $log->error_message);
        $this->assertFalse($log->exists, 'The rejection must not persist a ZatcaInvoiceLog row.');
        $this->assertSame(0, ZatcaInvoiceLog::where('invoice_id', $invoice->id)->count());
        $this->assertNull($invoice->company->fresh()->zatca_last_invoice_hash);
    }

    public function test_a_draft_invoice_is_rejected_the_same_way(): void
    {
        $invoice = $this->makeInvoice('draft');

        $log = app(ZatcaSyncService::class)->submit($invoice);

        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('draft', $log->error_message);
        $this->assertSame(0, ZatcaInvoiceLog::where('invoice_id', $invoice->id)->count());
    }
}
