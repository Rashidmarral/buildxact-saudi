<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\PrepaymentInvoice;
use App\Models\Subscription;
use App\Models\ZatcaCreditNoteLog;
use App\Models\ZatcaDebitNoteLog;
use App\Models\ZatcaInvoiceLog;
use App\Services\Zatca\ZatcaSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Audit finding ZATCA-05: pendingInvoices()/pendingPrepaymentInvoices()/
 * pendingCreditNotes()/pendingDebitNotes() already exclude any document
 * with a cleared/reported log from bulk/scheduled sync, but the
 * single-document "Sync now" button and admin "Retry" action had no guard
 * of their own against resubmitting an already-cleared document — a
 * double-click or a retry after a slow response would mint a fresh
 * UUID/hash, advance the ICV, and fire a second live clearance/report
 * call for the same document. Same reject() choke point already used for
 * the cancelled-invoice and never-cleared-correction-document bugs.
 */
class ZatcaDoubleSubmissionGuardTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_CSID = 'MIIDBzCCAe+gAwIBAgIUBSx0rLzK3YZPX+xqWHd5Snjpe5AwDQYJKoZIhvcNAQELBQAwEzERMA8GA1UEAwwIdGVzdC1lZ3MwHhcNMjYwODMwMjAzODAyWhcNMzYwODI3MjAzODAyWjATMREwDwYDVQQDDAh0ZXN0LWVnczCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBALq2jcY9XpSEhbetKvAAcMAP7Hjp5uJk7eo8luKn5Rgl9QqM/Bwgjuz6xKKASmn6QSaZOk44wdafGJvi/5MQ9fDVO1bCEUWDFVbMDblBxEjBe3N9FsQ33u4x1uZAUndQMaBukxH3+XxW7bGGCfkYJwJaDbSA6HAPF8kOzFNVjQKmyf3vOHa3uajxwMG4XKXqifFFhmn4jgCIhD5Nd6tLvY0dLMjD+MG7EVLPJCf0BGIMbyRJR6KWbz+lcCrO8hAC2UPX9jObTcz/kQQSDXWS8XnKjxyCr+BTVWZNYfIGOz3Y8YMM36IHBsHG/mIT3GXX6KKK4T9MmoGXcV87pV0dQusCAwEAAaNTMFEwHQYDVR0OBBYEFMVsaLNnewpMwUC33hHUv1RhoyBPMB8GA1UdIwQYMBaAFMVsaLNnewpMwUC33hHUv1RhoyBPMA8GA1UdEwEB/wQFMAMBAf8wDQYJKoZIhvcNAQELBQADggEBAKJPnEQOZlEPhevrJxthiJ2qZnemUKvvrdCJ1e5TqWG3+H2q+35dKjPE3QbPCnJtuw9iL54nkby8DiGrHRowJ5BcoxJbFernKLljBxCxRHOAp7M//nDXrYfWwrdDUqd4GE/T0buNrrCLSLEWdMxS1vEh4j/CV8h9wh9EVS7jgo99487iY/PxolzU5+Wjb+bxsgkrySpKhZt3En4A0jq3+3bP5bdFkj1fhmoAYzcIzUZj9ldcwrqesHGkF+SpGxRh6fll6pHZUnP+zqJH3Jqy1Ccer+M/MVmuqKQtwnMSb4yfRn8u9ffmzAAsBXnCSaceZYaYRPh/dubVhFrie20ODXY=';

    private function makeOnboardedCompany(): Company
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(),
            'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true,
            'has_zatca_phase2' => true,
        ]);

        $company = Company::create([
            'name' => 'Double Submission Guard Co.', 'slug' => 'dsg-'.uniqid(),
            'currency' => 'SAR',
            'zatca_integration_mode' => Company::ZATCA_MODE_PHASE2,
            'zatca_onboarding_status' => 'onboarded',
            'zatca_production_csid' => self::TEST_CSID, 'zatca_production_secret' => 'secret',
        ]);

        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth()]);

        return $company;
    }

    public function test_an_already_cleared_invoice_is_rejected_on_resubmission_without_a_second_http_call(): void
    {
        Http::fake(['*' => Http::response(['clearedInvoice' => 'stamp'], 200)]);
        $company = $this->makeOnboardedCompany();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client', 'vat_number' => '300012345600003']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-1',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(), 'currency' => 'SAR',
            'subtotal' => 100, 'discount_total' => 0, 'vat_total' => 15, 'total' => 115,
        ]);
        $sync = app(ZatcaSyncService::class);

        $firstLog = $sync->submit($invoice);
        $this->assertSame('cleared', $firstLog->status);
        $this->assertSame(1, Http::recorded()->count());

        $secondLog = $sync->submit($invoice->fresh());

        $this->assertSame('failed', $secondLog->status);
        $this->assertStringContainsString('already been cleared or reported', $secondLog->error_message);
        $this->assertFalse($secondLog->exists, 'The rejection must not persist a second ZatcaInvoiceLog row.');
        $this->assertSame(1, ZatcaInvoiceLog::where('invoice_id', $invoice->id)->count());
        $this->assertSame(1, Http::recorded()->count(), 'Resubmitting a cleared invoice must not fire a second live ZATCA call.');
    }

    public function test_an_already_cleared_prepayment_invoice_is_rejected_on_resubmission(): void
    {
        Http::fake(['*' => Http::response(['clearedInvoice' => 'stamp'], 200)]);
        $company = $this->makeOnboardedCompany();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client', 'vat_number' => '300012345600003']);
        $prepayment = PrepaymentInvoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'prepayment_number' => 'PRE-1',
            'type' => 'standard', 'status' => 'issued', 'issue_date' => now()->toDateString(),
            'amount' => 1000, 'vat_rate' => 15, 'vat_amount' => 150, 'total' => 1150, 'currency' => 'SAR',
        ]);
        $sync = app(ZatcaSyncService::class);

        $firstLog = $sync->submitPrepaymentInvoice($prepayment);
        $this->assertSame('cleared', $firstLog->status);

        $secondLog = $sync->submitPrepaymentInvoice($prepayment->fresh());

        $this->assertSame('failed', $secondLog->status);
        $this->assertStringContainsString('already been cleared or reported', $secondLog->error_message);
        $this->assertSame(1, ZatcaInvoiceLog::where('prepayment_invoice_id', $prepayment->id)->count());
        $this->assertSame(1, Http::recorded()->count());
    }

    public function test_an_already_cleared_credit_note_is_rejected_on_resubmission(): void
    {
        Http::fake(['*' => Http::response(['clearedInvoice' => 'stamp'], 200)]);
        $company = $this->makeOnboardedCompany();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client', 'vat_number' => '300012345600003']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-1',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(), 'currency' => 'SAR',
            'subtotal' => 100, 'discount_total' => 0, 'vat_total' => 15, 'total' => 115,
        ]);
        $sync = app(ZatcaSyncService::class);
        $sync->submit($invoice);

        $creditNote = CreditNote::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id, 'client_id' => $invoice->client_id,
            'credit_note_number' => 'CN-1', 'issue_date' => now(), 'status' => 'issued', 'currency' => 'SAR',
            'subtotal' => 100, 'discount_total' => 0, 'vat_total' => 15, 'total' => 115,
        ]);

        $firstLog = $sync->submitCreditNote($creditNote);
        $this->assertSame('cleared', $firstLog->status);

        $secondLog = $sync->submitCreditNote($creditNote->fresh());

        $this->assertSame('failed', $secondLog->status);
        $this->assertStringContainsString('already been cleared or reported', $secondLog->error_message);
        $this->assertSame(1, ZatcaCreditNoteLog::where('credit_note_id', $creditNote->id)->count());
    }

    public function test_an_already_cleared_debit_note_is_rejected_on_resubmission(): void
    {
        Http::fake(['*' => Http::response(['clearedInvoice' => 'stamp'], 200)]);
        $company = $this->makeOnboardedCompany();
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client', 'vat_number' => '300012345600003']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-1',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(), 'currency' => 'SAR',
            'subtotal' => 100, 'discount_total' => 0, 'vat_total' => 15, 'total' => 115,
        ]);
        $sync = app(ZatcaSyncService::class);
        $sync->submit($invoice);

        $debitNote = DebitNote::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id, 'client_id' => $invoice->client_id,
            'debit_note_number' => 'DN-1', 'issue_date' => now(), 'status' => 'issued', 'currency' => 'SAR',
            'subtotal' => 50, 'vat_total' => 7.5, 'total' => 57.5,
        ]);

        $firstLog = $sync->submitDebitNote($debitNote);
        $this->assertSame('cleared', $firstLog->status);

        $secondLog = $sync->submitDebitNote($debitNote->fresh());

        $this->assertSame('failed', $secondLog->status);
        $this->assertStringContainsString('already been cleared or reported', $secondLog->error_message);
        $this->assertSame(1, ZatcaDebitNoteLog::where('debit_note_id', $debitNote->id)->count());
    }
}
