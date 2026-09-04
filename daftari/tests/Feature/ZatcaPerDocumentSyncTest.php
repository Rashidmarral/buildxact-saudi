<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\ZatcaInvoiceLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Feature request: the "Sync now" button synced every pending document at
 * once with no way to choose which one goes first, and the ZATCA dashboard
 * had no way to download the signed XML for a cleared/reported document
 * (that download route already existed per-document — see
 * InvoiceController::downloadXml() — it just wasn't linked from here).
 *
 * Adds per-document sync actions (ZatcaController::syncInvoice/
 * syncCreditNote/syncDebitNote) alongside the existing bulk sync, a
 * "Pending sync" list on the dashboard with one Sync button per document,
 * and "Download XML" links on the cleared/reported rows of each sync log
 * table.
 */
class ZatcaPerDocumentSyncTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Same throwaway self-signed certificate shape used in DemoModeTest —
     * needed so submit()'s certificate parsing succeeds and execution
     * reaches the actual HTTP call, which Http::fake() intercepts.
     */
    private const TEST_CSID = 'MIIDBzCCAe+gAwIBAgIUBSx0rLzK3YZPX+xqWHd5Snjpe5AwDQYJKoZIhvcNAQELBQAwEzERMA8GA1UEAwwIdGVzdC1lZ3MwHhcNMjYwODMwMjAzODAyWhcNMzYwODI3MjAzODAyWjATMREwDwYDVQQDDAh0ZXN0LWVnczCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBALq2jcY9XpSEhbetKvAAcMAP7Hjp5uJk7eo8luKn5Rgl9QqM/Bwgjuz6xKKASmn6QSaZOk44wdafGJvi/5MQ9fDVO1bCEUWDFVbMDblBxEjBe3N9FsQ33u4x1uZAUndQMaBukxH3+XxW7bGGCfkYJwJaDbSA6HAPF8kOzFNVjQKmyf3vOHa3uajxwMG4XKXqifFFhmn4jgCIhD5Nd6tLvY0dLMjD+MG7EVLPJCf0BGIMbyRJR6KWbz+lcCrO8hAC2UPX9jObTcz/kQQSDXWS8XnKjxyCr+BTVWZNYfIGOz3Y8YMM36IHBsHG/mIT3GXX6KKK4T9MmoGXcV87pV0dQusCAwEAAaNTMFEwHQYDVR0OBBYEFMVsaLNnewpMwUC33hHUv1RhoyBPMB8GA1UdIwQYMBaAFMVsaLNnewpMwUC33hHUv1RhoyBPMA8GA1UdEwEB/wQFMAMBAf8wDQYJKoZIhvcNAQELBQADggEBAKJPnEQOZlEPhevrJxthiJ2qZnemUKvvrdCJ1e5TqWG3+H2q+35dKjPE3QbPCnJtuw9iL54nkby8DiGrHRowJ5BcoxJbFernKLljBxCxRHOAp7M//nDXrYfWwrdDUqd4GE/T0buNrrCLSLEWdMxS1vEh4j/CV8h9wh9EVS7jgo99487iY/PxolzU5+Wjb+bxsgkrySpKhZt3En4A0jq3+3bP5bdFkj1fhmoAYzcIzUZj9ldcwrqesHGkF+SpGxRh6fll6pHZUnP+zqJH3Jqy1Ccer+M/MVmuqKQtwnMSb4yfRn8u9ffmzAAsBXnCSaceZYaYRPh/dubVhFrie20ODXY=';

    private function makeOnboardedOwner(): array
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(),
            'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true,
            'has_zatca_phase2' => true,
        ]);

        $company = Company::create([
            'name' => 'Sync Buttons Co.', 'slug' => 'sync-buttons-'.uniqid(),
            'zatca_integration_mode' => Company::ZATCA_MODE_PHASE2,
            'zatca_onboarding_status' => 'onboarded',
            'zatca_production_csid' => self::TEST_CSID,
            'zatca_production_secret' => 'secret-value',
        ]);

        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    private function makeInvoice(Company $company, string $type = 'standard'): Invoice
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client']);

        return Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-'.uniqid(),
            'type' => $type, 'status' => 'sent', 'issue_date' => now()->toDateString(), 'currency' => $company->currency,
            'subtotal' => 100, 'discount_total' => 0, 'vat_total' => 15, 'total' => 115,
        ]);
    }

    public function test_syncing_a_single_pending_invoice_marks_it_cleared_and_flashes_success(): void
    {
        Http::fake(['*' => Http::response(['clearedInvoice' => 'stamp'], 200)]);
        [$company, $owner] = $this->makeOnboardedOwner();
        $invoice = $this->makeInvoice($company);

        $response = $this->actingAs($owner)->post(route('app.zatca.sync.invoice', $invoice));

        $response->assertRedirect();
        $response->assertSessionHas('status');
        $this->assertSame('cleared', ZatcaInvoiceLog::where('invoice_id', $invoice->id)->latest('id')->first()->status);
    }

    public function test_syncing_a_single_invoice_zatca_rejects_flashes_an_error_and_leaves_it_pending(): void
    {
        Http::fake(['*' => Http::response(['status' => 'rejected'], 400)]);
        [$company, $owner] = $this->makeOnboardedOwner();
        $invoice = $this->makeInvoice($company);

        $response = $this->actingAs($owner)->post(route('app.zatca.sync.invoice', $invoice));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame('failed', ZatcaInvoiceLog::where('invoice_id', $invoice->id)->latest('id')->first()->status);
    }

    public function test_a_company_cannot_sync_another_companys_invoice(): void
    {
        Http::fake();
        [, $owner] = $this->makeOnboardedOwner();
        [$otherCompany] = $this->makeOnboardedOwner();
        $otherInvoice = $this->makeInvoice($otherCompany);

        $response = $this->actingAs($owner)->post(route('app.zatca.sync.invoice', $otherInvoice));

        $response->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_syncing_a_single_credit_note_and_debit_note_reaches_zatca(): void
    {
        Http::fake(['*' => Http::response(['clearedInvoice' => 'stamp'], 200)]);
        [$company, $owner] = $this->makeOnboardedOwner();
        $invoice = $this->makeInvoice($company);
        $client = Client::find($invoice->client_id);

        $creditNote = CreditNote::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id, 'client_id' => $client->id,
            'credit_note_number' => 'CN-1', 'issue_date' => now(), 'status' => 'issued', 'currency' => $company->currency,
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);
        $debitNote = DebitNote::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id, 'client_id' => $client->id,
            'debit_note_number' => 'DN-1', 'issue_date' => now(), 'status' => 'issued', 'currency' => $company->currency,
            'subtotal' => 50, 'vat_total' => 7.5, 'total' => 57.5,
        ]);

        $creditResponse = $this->actingAs($owner)->post(route('app.zatca.sync.credit-note', $creditNote));
        $debitResponse = $this->actingAs($owner)->post(route('app.zatca.sync.debit-note', $debitNote));

        $creditResponse->assertSessionHas('status');
        $debitResponse->assertSessionHas('status');
        Http::assertSentCount(2);
    }

    public function test_the_dashboard_lists_pending_documents_with_an_individual_sync_button_each(): void
    {
        [$company, $owner] = $this->makeOnboardedOwner();
        $invoice = $this->makeInvoice($company);

        $response = $this->actingAs($owner)->get(route('app.zatca.dashboard'));

        $response->assertOk();
        $response->assertSee($invoice->invoice_number);
        $response->assertSee(route('app.zatca.sync.invoice', $invoice), false);
    }

    public function test_the_dashboard_offers_an_xml_download_link_for_a_cleared_invoice(): void
    {
        [$company, $owner] = $this->makeOnboardedOwner();
        $invoice = $this->makeInvoice($company);
        ZatcaInvoiceLog::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id,
            'environment' => 'production', 'invoice_type' => 'b2b', 'direction' => 'clearance',
            'status' => 'cleared', 'request_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'xml_payload' => '<Invoice></Invoice>', 'submitted_at' => now(), 'cleared_at' => now(),
        ]);

        $response = $this->actingAs($owner)->get(route('app.zatca.dashboard'));

        $response->assertOk();
        $response->assertSee(route('app.invoices.xml', $invoice), false);
    }

    /**
     * Regression: the invoice show page mixes an inline @php(...) directive
     * with a later @php ... @endphp block in the same view — Blade's
     * storePhpBlocks() extracts raw PHP blocks via a single non-greedy
     * regex across the WHOLE file, so an inline @php(...) with no
     * @endphp of its own gets merged with the NEXT @endphp anywhere later
     * in the file, silently swallowing everything (including @if/@elseif/
     * @endif) in between into one broken raw block. Blade::compileString()
     * doesn't catch this — the resulting PHP is only invalid once actually
     * parsed — so this hits the real route to catch it. Only a full HTTP
     * GET (not compileString(), which was checked and passed during
     * development) reproduces the ParseError reported live.
     */
    public function test_the_invoice_show_page_renders_with_a_sync_with_zatca_button_for_an_eligible_invoice(): void
    {
        [$company, $owner] = $this->makeOnboardedOwner();
        $invoice = $this->makeInvoice($company);

        $response = $this->actingAs($owner)->get(route('app.invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee(__('Sync with ZATCA'));
        $response->assertSee(route('app.zatca.sync.invoice', $invoice), false);
    }

    /**
     * The document header always said "Tax Invoice" regardless of whether
     * the invoice was standard (B2B) or simplified (B2C) — ZATCA's own
     * terminology distinguishes the two on the printed/downloaded
     * document itself.
     */
    public function test_the_invoice_document_header_reflects_standard_vs_simplified_type(): void
    {
        [$company, $owner] = $this->makeOnboardedOwner();
        $standardInvoice = $this->makeInvoice($company, 'standard');
        $simplifiedInvoice = $this->makeInvoice($company, 'simplified');

        $standardResponse = $this->actingAs($owner)->get(route('app.invoices.show', $standardInvoice));
        $simplifiedResponse = $this->actingAs($owner)->get(route('app.invoices.show', $simplifiedInvoice));

        $standardResponse->assertOk();
        $standardResponse->assertSee(__('Standard tax invoice'));
        $standardResponse->assertDontSee(__('Simplified tax invoice'));

        $simplifiedResponse->assertOk();
        $simplifiedResponse->assertSee(__('Simplified tax invoice'));
        $simplifiedResponse->assertDontSee(__('Standard tax invoice'));
    }
}
