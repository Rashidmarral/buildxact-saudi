<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\ZatcaCreditNoteLog;
use App\Models\ZatcaDebitNoteLog;
use App\Models\ZatcaInvoiceLog;
use App\Services\Zatca\ZatcaSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Deep ZATCA correction-document audit findings (#6/#9 in the review):
 *
 * 1. A credit/debit note's cac:BillingReference points back to the
 *    original invoice's own cbc:ID — and, where available, the UUID
 *    ZATCA assigned it at clearance/reporting time (see
 *    ZatcaXmlGenerator::generateForCreditNote()/generateForDebitNote()).
 *    Nothing stopped a correction document from being submitted for an
 *    invoice ZATCA has never actually cleared/reported — still Phase 1
 *    only, not yet synced, excluded by a B2B/B2C toggle, or simply never
 *    attempted. The resulting document's BillingReference would resolve
 *    to nothing on ZATCA's side.
 *
 * 2. The single-document "Sync now" button and admin retry had no
 *    currency check of their own (pendingInvoices()/pendingCreditNotes()/
 *    pendingDebitNotes() already exclude foreign-currency documents from
 *    bulk/scheduled sync, since ZatcaXmlGenerator always renders amounts
 *    in the company's base currency) — same class of gap as the
 *    cancelled-invoice and voided-credit/debit-note bugs fixed earlier.
 */
class ZatcaCorrectionDocumentGuardsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Same throwaway self-signed certificate used in
     * ZatcaPerDocumentSyncTest — needed so submit()'s certificate parsing
     * succeeds and execution reaches the actual HTTP call, which
     * Http::fake() intercepts.
     */
    private const TEST_CSID = 'MIIDBzCCAe+gAwIBAgIUBSx0rLzK3YZPX+xqWHd5Snjpe5AwDQYJKoZIhvcNAQELBQAwEzERMA8GA1UEAwwIdGVzdC1lZ3MwHhcNMjYwODMwMjAzODAyWhcNMzYwODI3MjAzODAyWjATMREwDwYDVQQDDAh0ZXN0LWVnczCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBALq2jcY9XpSEhbetKvAAcMAP7Hjp5uJk7eo8luKn5Rgl9QqM/Bwgjuz6xKKASmn6QSaZOk44wdafGJvi/5MQ9fDVO1bCEUWDFVbMDblBxEjBe3N9FsQ33u4x1uZAUndQMaBukxH3+XxW7bGGCfkYJwJaDbSA6HAPF8kOzFNVjQKmyf3vOHa3uajxwMG4XKXqifFFhmn4jgCIhD5Nd6tLvY0dLMjD+MG7EVLPJCf0BGIMbyRJR6KWbz+lcCrO8hAC2UPX9jObTcz/kQQSDXWS8XnKjxyCr+BTVWZNYfIGOz3Y8YMM36IHBsHG/mIT3GXX6KKK4T9MmoGXcV87pV0dQusCAwEAAaNTMFEwHQYDVR0OBBYEFMVsaLNnewpMwUC33hHUv1RhoyBPMB8GA1UdIwQYMBaAFMVsaLNnewpMwUC33hHUv1RhoyBPMA8GA1UdEwEB/wQFMAMBAf8wDQYJKoZIhvcNAQELBQADggEBAKJPnEQOZlEPhevrJxthiJ2qZnemUKvvrdCJ1e5TqWG3+H2q+35dKjPE3QbPCnJtuw9iL54nkby8DiGrHRowJ5BcoxJbFernKLljBxCxRHOAp7M//nDXrYfWwrdDUqd4GE/T0buNrrCLSLEWdMxS1vEh4j/CV8h9wh9EVS7jgo99487iY/PxolzU5+Wjb+bxsgkrySpKhZt3En4A0jq3+3bP5bdFkj1fhmoAYzcIzUZj9ldcwrqesHGkF+SpGxRh6fll6pHZUnP+zqJH3Jqy1Ccer+M/MVmuqKQtwnMSb4yfRn8u9ffmzAAsBXnCSaceZYaYRPh/dubVhFrie20ODXY=';

    private function makeOnboardedCompany(string $currency = 'SAR'): Company
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(),
            'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true,
            'has_zatca_phase2' => true,
        ]);

        $company = Company::create([
            'name' => 'Correction Guard Co.', 'slug' => 'correction-guard-'.uniqid(),
            'currency' => $currency,
            'zatca_integration_mode' => Company::ZATCA_MODE_PHASE2,
            'zatca_onboarding_status' => 'onboarded',
            'zatca_production_csid' => self::TEST_CSID, 'zatca_production_secret' => 'secret',
        ]);

        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth()]);

        return $company;
    }

    private function makeInvoice(Company $company, array $overrides = []): Invoice
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client']);

        return Invoice::create(array_merge([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-'.uniqid(),
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(), 'currency' => $company->currency,
            'subtotal' => 100, 'discount_total' => 0, 'vat_total' => 15, 'total' => 115,
        ], $overrides));
    }

    public function test_a_credit_note_against_a_never_cleared_invoice_is_rejected_before_any_xml_or_log_row_is_created(): void
    {
        Http::fake();
        $company = $this->makeOnboardedCompany();
        $invoice = $this->makeInvoice($company);

        $creditNote = CreditNote::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id, 'client_id' => $invoice->client_id,
            'credit_note_number' => 'CN-1', 'issue_date' => now(), 'status' => 'issued', 'currency' => $company->currency,
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);

        $log = app(ZatcaSyncService::class)->submitCreditNote($creditNote);

        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('never cleared or reported', $log->error_message);
        $this->assertFalse($log->exists, 'The rejection must not persist a ZatcaCreditNoteLog row.');
        $this->assertSame(0, ZatcaCreditNoteLog::where('credit_note_id', $creditNote->id)->count());
        Http::assertNothingSent();
    }

    public function test_a_debit_note_against_a_never_cleared_invoice_is_rejected_the_same_way(): void
    {
        Http::fake();
        $company = $this->makeOnboardedCompany();
        $invoice = $this->makeInvoice($company);

        $debitNote = DebitNote::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id, 'client_id' => $invoice->client_id,
            'debit_note_number' => 'DN-1', 'issue_date' => now(), 'status' => 'issued', 'currency' => $company->currency,
            'subtotal' => 50, 'vat_total' => 7.5, 'total' => 57.5,
        ]);

        $log = app(ZatcaSyncService::class)->submitDebitNote($debitNote);

        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('never cleared or reported', $log->error_message);
        $this->assertSame(0, ZatcaDebitNoteLog::where('debit_note_id', $debitNote->id)->count());
        Http::assertNothingSent();
    }

    public function test_a_credit_note_is_accepted_once_the_original_invoice_has_been_cleared(): void
    {
        Http::fake(['*' => Http::response(['clearedInvoice' => 'stamp'], 200)]);
        $company = $this->makeOnboardedCompany();
        $invoice = $this->makeInvoice($company);
        $sync = app(ZatcaSyncService::class);

        $invoiceLog = $sync->submit($invoice);
        $this->assertSame('cleared', $invoiceLog->status);

        $creditNote = CreditNote::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id, 'client_id' => $invoice->client_id,
            'credit_note_number' => 'CN-1', 'issue_date' => now(), 'status' => 'issued', 'currency' => $company->currency,
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);

        $log = $sync->submitCreditNote($creditNote);

        $this->assertSame('cleared', $log->status);
    }

    public function test_a_foreign_currency_invoice_is_rejected_by_the_single_sync_choke_point(): void
    {
        Http::fake();
        $company = $this->makeOnboardedCompany('SAR');
        $invoice = $this->makeInvoice($company, ['currency' => 'USD']);

        $log = app(ZatcaSyncService::class)->submit($invoice);

        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('base currency', $log->error_message);
        $this->assertFalse($log->exists);
        $this->assertSame(0, ZatcaInvoiceLog::where('invoice_id', $invoice->id)->count());
        Http::assertNothingSent();
    }

    public function test_a_foreign_currency_credit_note_is_rejected_even_once_cleared_is_impossible_to_reach(): void
    {
        Http::fake();
        $company = $this->makeOnboardedCompany('SAR');
        $invoice = $this->makeInvoice($company);

        $creditNote = CreditNote::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id, 'client_id' => $invoice->client_id,
            'credit_note_number' => 'CN-1', 'issue_date' => now(), 'status' => 'issued', 'currency' => 'USD',
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);

        $log = app(ZatcaSyncService::class)->submitCreditNote($creditNote);

        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('base currency', $log->error_message);
        Http::assertNothingSent();
    }
}
