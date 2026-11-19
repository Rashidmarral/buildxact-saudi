<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\Invoice;
use App\Models\ZatcaCreditNoteLog;
use App\Models\ZatcaDebitNoteLog;
use App\Services\Zatca\ZatcaSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Go-live compliance review finding, parallel to the cancelled-invoice gap
 * fixed earlier: pendingCreditNotes()/pendingDebitNotes() already exclude
 * status='void' from bulk/scheduled sync, but the single-document "Sync
 * now" buttons (ZatcaController::syncCreditNote/syncDebitNote) and the
 * admin retry action have no status check of their own — a voided credit
 * or debit note could still be manually submitted to ZATCA, generating a
 * real government record for a document the business has marked invalid.
 * submitCreditNote()/submitDebitNote() now refuse a void document before
 * building any XML or touching the ICV/PIH chain, the same single choke
 * point every caller goes through.
 */
class ZatcaVoidedCreditDebitNoteRejectedTest extends TestCase
{
    use RefreshDatabase;

    private function makeInvoice(): Invoice
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'vat_number' => '300012345600003']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client']);

        return Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-'.uniqid(),
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(),
            'currency' => 'SAR', 'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);
    }

    public function test_a_voided_credit_note_is_rejected_before_any_xml_or_log_row_is_created(): void
    {
        $invoice = $this->makeInvoice();
        $creditNote = CreditNote::create([
            'company_id' => $invoice->company_id, 'invoice_id' => $invoice->id, 'client_id' => $invoice->client_id,
            'credit_note_number' => 'CN-'.uniqid(), 'issue_date' => now(), 'status' => 'void', 'currency' => 'SAR',
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);

        $log = app(ZatcaSyncService::class)->submitCreditNote($creditNote);

        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('voided', $log->error_message);
        $this->assertFalse($log->exists, 'The rejection must not persist a ZatcaCreditNoteLog row.');
        $this->assertSame(0, ZatcaCreditNoteLog::where('credit_note_id', $creditNote->id)->count());
    }

    public function test_a_voided_debit_note_is_rejected_the_same_way(): void
    {
        $invoice = $this->makeInvoice();
        $debitNote = DebitNote::create([
            'company_id' => $invoice->company_id, 'invoice_id' => $invoice->id, 'client_id' => $invoice->client_id,
            'debit_note_number' => 'DN-'.uniqid(), 'issue_date' => now(), 'status' => 'void', 'currency' => 'SAR',
            'subtotal' => 50, 'vat_total' => 7.5, 'total' => 57.5,
        ]);

        $log = app(ZatcaSyncService::class)->submitDebitNote($debitNote);

        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('voided', $log->error_message);
        $this->assertSame(0, ZatcaDebitNoteLog::where('debit_note_id', $debitNote->id)->count());
    }
}
