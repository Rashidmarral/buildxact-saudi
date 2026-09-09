<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\User;
use App\Models\ZatcaCreditNoteLog;
use App\Models\ZatcaInvoiceLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Module 15 (ZATCA Super Admin Management): the platform-wide dashboard
 * KPIs, the company ZATCA status table, the integration-logs listing across
 * both zatca_invoice_logs/zatca_credit_note_logs, the retry guard (never
 * resubmits an already cleared/reported document), the connectivity-only
 * test-connection action, the admin-triggered onboarding reset, the
 * pending-sync bulk action (the admin-side "Sync now"), and the security
 * guarantees: no private key/secret ever rendered or written to the audit
 * log, every mutating action audit-logged, permission gating.
 */
class ZatcaManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Same throwaway self-signed certificate shape used in
     * ZatcaPerDocumentSyncTest — needed so submit()'s certificate parsing
     * succeeds and execution reaches the real HTTP call, which Http::fake()
     * intercepts.
     */
    private const TEST_CSID = 'MIIDBzCCAe+gAwIBAgIUBSx0rLzK3YZPX+xqWHd5Snjpe5AwDQYJKoZIhvcNAQELBQAwEzERMA8GA1UEAwwIdGVzdC1lZ3MwHhcNMjYwODMwMjAzODAyWhcNMzYwODI3MjAzODAyWjATMREwDwYDVQQDDAh0ZXN0LWVnczCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBALq2jcY9XpSEhbetKvAAcMAP7Hjp5uJk7eo8luKn5Rgl9QqM/Bwgjuz6xKKASmn6QSaZOk44wdafGJvi/5MQ9fDVO1bCEUWDFVbMDblBxEjBe3N9FsQ33u4x1uZAUndQMaBukxH3+XxW7bGGCfkYJwJaDbSA6HAPF8kOzFNVjQKmyf3vOHa3uajxwMG4XKXqifFFhmn4jgCIhD5Nd6tLvY0dLMjD+MG7EVLPJCf0BGIMbyRJR6KWbz+lcCrO8hAC2UPX9jObTcz/kQQSDXWS8XnKjxyCr+BTVWZNYfIGOz3Y8YMM36IHBsHG/mIT3GXX6KKK4T9MmoGXcV87pV0dQusCAwEAAaNTMFEwHQYDVR0OBBYEFMVsaLNnewpMwUC33hHUv1RhoyBPMB8GA1UdIwQYMBaAFMVsaLNnewpMwUC33hHUv1RhoyBPMA8GA1UdEwEB/wQFMAMBAf8wDQYJKoZIhvcNAQELBQADggEBAKJPnEQOZlEPhevrJxthiJ2qZnemUKvvrdCJ1e5TqWG3+H2q+35dKjPE3QbPCnJtuw9iL54nkby8DiGrHRowJ5BcoxJbFernKLljBxCxRHOAp7M//nDXrYfWwrdDUqd4GE/T0buNrrCLSLEWdMxS1vEh4j/CV8h9wh9EVS7jgo99487iY/PxolzU5+Wjb+bxsgkrySpKhZt3En4A0jq3+3bP5bdFkj1fhmoAYzcIzUZj9ldcwrqesHGkF+SpGxRh6fll6pHZUnP+zqJH3Jqy1Ccer+M/MVmuqKQtwnMSb4yfRn8u9ffmzAAsBXnCSaceZYaYRPh/dubVhFrie20ODXY=';

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'company_id' => null]);
    }

    private function withConfirmedPassword(User $user)
    {
        return $this->actingAs($user)->withSession(['auth.password_confirmed_at' => now()->timestamp]);
    }

    private function makeCompany(array $overrides = []): Company
    {
        return Company::create(array_merge([
            'name' => 'Acme Trading', 'slug' => 'acme-'.uniqid(), 'status' => 'active',
            'vat_number' => '300012345600003',
        ], $overrides));
    }

    private function makeInvoice(Company $company, array $overrides = []): Invoice
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client']);

        return Invoice::create(array_merge([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'invoice_number' => 'INV-'.uniqid(),
            'type' => 'standard',
            'issue_date' => now()->toDateString(),
            'currency' => 'SAR',
        ], $overrides));
    }

    private function makeInvoiceLog(Company $company, Invoice $invoice, array $overrides = []): ZatcaInvoiceLog
    {
        return ZatcaInvoiceLog::create(array_merge([
            'company_id' => $company->id,
            'invoice_id' => $invoice->id,
            'environment' => 'developer',
            'invoice_type' => 'b2b',
            'direction' => 'clearance',
            'status' => 'failed',
            'request_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'error_message' => 'HTTP 400: {"message":"validation error"}',
            'submitted_at' => now(),
        ], $overrides));
    }

    /**
     * A company like the operator's own "Dynamic Core Contracting Company"
     * — already ZATCA Phase 2 onboarded for its real business, no
     * subscription of its own (Company::hasFeature() treats an absent
     * subscription as full access), zatca_sync_frequency left at its
     * 'manual' default so nothing auto-syncs.
     */
    private function makeOnboardedCompany(array $overrides = []): Company
    {
        return $this->makeCompany(array_merge([
            'zatca_integration_mode' => Company::ZATCA_MODE_PHASE2,
            'zatca_onboarding_status' => 'onboarded',
            'zatca_production_csid' => self::TEST_CSID,
            'zatca_production_secret' => 'secret-value',
        ], $overrides));
    }

    private function makePendingInvoice(Company $company, string $type = 'standard'): Invoice
    {
        return $this->makeInvoice($company, [
            'type' => $type,
            'status' => 'sent',
            'subtotal' => 100,
            'vat_total' => 15,
            'total' => 115,
        ]);
    }

    // ---------------------------------------------------------------
    // Manual "Sync now" for pending documents (admin-side)
    // ---------------------------------------------------------------

    public function test_admin_can_sync_all_pending_invoices_for_a_company_at_once(): void
    {
        Http::fake(['*' => Http::response(['clearedInvoice' => 'stamp'], 200)]);
        $admin = $this->makeAdmin();
        $company = $this->makeOnboardedCompany();
        $invoiceA = $this->makePendingInvoice($company);
        $invoiceB = $this->makePendingInvoice($company);

        $response = $this->withConfirmedPassword($admin)
            ->post(route('admin.zatca.companies.sync', $company));

        $response->assertRedirect();
        $response->assertSessionHas('status');
        $this->assertSame('cleared', ZatcaInvoiceLog::where('invoice_id', $invoiceA->id)->latest('id')->first()->status);
        $this->assertSame('cleared', ZatcaInvoiceLog::where('invoice_id', $invoiceB->id)->latest('id')->first()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'zatca.sync_pending', 'company_id' => $company->id]);
    }

    public function test_sync_pending_requires_the_company_to_be_onboarded_first(): void
    {
        $admin = $this->makeAdmin();
        $company = $this->makeCompany(); // never onboarded
        $this->makePendingInvoice($company);

        $response = $this->withConfirmedPassword($admin)
            ->post(route('admin.zatca.companies.sync', $company));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(0, ZatcaInvoiceLog::where('company_id', $company->id)->count());
    }

    public function test_sync_pending_flashes_a_status_message_when_there_is_nothing_to_sync(): void
    {
        $admin = $this->makeAdmin();
        $company = $this->makeOnboardedCompany();

        $response = $this->withConfirmedPassword($admin)
            ->post(route('admin.zatca.companies.sync', $company));

        $response->assertRedirect();
        $response->assertSessionHas('status');
        $this->assertSame(0, ZatcaInvoiceLog::where('company_id', $company->id)->count());
    }

    public function test_index_page_shows_the_pending_count_and_a_sync_now_button(): void
    {
        $admin = $this->makeAdmin();
        $company = $this->makeOnboardedCompany(['name' => 'Pending Sync Co']);
        $this->makePendingInvoice($company);
        $this->makePendingInvoice($company);

        $response = $this->actingAs($admin)->get(route('admin.zatca.index'));

        $response->assertOk()
            ->assertSee('Pending Sync Co')
            ->assertSee(__('Sync now'))
            ->assertSee(route('admin.zatca.companies.sync', $company), false);
    }

    public function test_index_page_shows_zero_pending_with_no_sync_button_when_nothing_is_pending(): void
    {
        $admin = $this->makeAdmin();
        $this->makeOnboardedCompany(['name' => 'Fully Synced Co']);

        $response = $this->actingAs($admin)->get(route('admin.zatca.index'));

        $response->assertOk()->assertSee('Fully Synced Co')->assertDontSee(__('Sync now'));
    }

    // ---------------------------------------------------------------
    // Dashboard KPIs
    // ---------------------------------------------------------------

    public function test_dashboard_kpis_count_companies_and_submissions_correctly(): void
    {
        $admin = $this->makeAdmin();

        $this->makeCompany(['zatca_onboarding_status' => 'onboarded', 'zatca_production_csid' => 'csid-value']);
        $this->makeCompany(['zatca_onboarding_status' => 'compliance_pending']);
        $this->makeCompany(['zatca_onboarding_status' => 'failed']);
        $notConnected = $this->makeCompany(['zatca_onboarding_status' => 'not_started']);

        $invoice = $this->makeInvoice($notConnected);
        $this->makeInvoiceLog($notConnected, $invoice, ['status' => 'cleared', 'error_message' => null]);

        $invoice2 = $this->makeInvoice($notConnected);
        $this->makeInvoiceLog($notConnected, $invoice2, ['status' => 'failed', 'error_message' => 'HTTP 400: rejected']);

        $invoice3 = $this->makeInvoice($notConnected);
        $this->makeInvoiceLog($notConnected, $invoice3, ['status' => 'failed', 'error_message' => 'Connection timed out']);

        $response = $this->actingAs($admin)->get(route('admin.zatca.index'));

        $response->assertOk();
        $response->assertViewHas('stats', function ($stats) {
            return $stats['connected'] === 1
                && $stats['pending_onboarding'] === 1
                && $stats['failed_connections'] === 1
                && $stats['total_submitted'] === 3
                && $stats['accepted'] === 1
                && $stats['rejected'] === 1
                && $stats['failed_submissions'] === 1;
        });
    }

    public function test_company_status_table_shows_computed_integration_status(): void
    {
        $admin = $this->makeAdmin();
        $connected = $this->makeCompany(['name' => 'Connected Co', 'zatca_onboarding_status' => 'onboarded', 'zatca_production_csid' => 'csid']);
        $failed = $this->makeCompany(['name' => 'Failed Co', 'zatca_onboarding_status' => 'failed']);

        $response = $this->actingAs($admin)->get(route('admin.zatca.index'));

        $response->assertOk()
            ->assertSee('Connected Co')
            ->assertSee('Failed Co');
    }

    public function test_company_status_filter_by_status(): void
    {
        $admin = $this->makeAdmin();
        $this->makeCompany(['name' => 'Onboarded Co', 'zatca_onboarding_status' => 'onboarded', 'zatca_production_csid' => 'csid']);
        $this->makeCompany(['name' => 'Never Started Co', 'zatca_onboarding_status' => 'not_started']);

        $response = $this->actingAs($admin)->get(route('admin.zatca.index', ['status' => 'connected']));

        $response->assertOk()->assertSee('Onboarded Co')->assertDontSee('Never Started Co');
    }

    // ---------------------------------------------------------------
    // Integration logs listing + filtering
    // ---------------------------------------------------------------

    public function test_logs_page_lists_both_invoice_and_credit_note_submissions(): void
    {
        $admin = $this->makeAdmin();
        $company = $this->makeCompany();
        $invoice = $this->makeInvoice($company);
        $this->makeInvoiceLog($company, $invoice, ['status' => 'cleared', 'error_message' => null]);

        $client = Client::create(['company_id' => $company->id, 'name' => 'Client']);
        $creditNote = CreditNote::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id, 'client_id' => $client->id,
            'credit_note_number' => 'CN-'.uniqid(), 'issue_date' => now()->toDateString(), 'status' => 'issued', 'currency' => 'SAR',
        ]);
        ZatcaCreditNoteLog::create([
            'company_id' => $company->id, 'credit_note_id' => $creditNote->id, 'environment' => 'developer',
            'invoice_type' => 'b2b', 'direction' => 'clearance', 'status' => 'failed',
            'request_uuid' => (string) \Illuminate\Support\Str::uuid(), 'error_message' => 'HTTP 400: bad',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.zatca.logs'));

        $response->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertSee($creditNote->credit_note_number);
    }

    public function test_logs_page_filters_by_company(): void
    {
        $admin = $this->makeAdmin();
        $companyA = $this->makeCompany(['name' => 'Findable Co']);
        $companyB = $this->makeCompany(['name' => 'Other Co']);

        $invoiceA = $this->makeInvoice($companyA);
        $this->makeInvoiceLog($companyA, $invoiceA, ['error_message' => 'HTTP 400: A-error']);
        $invoiceB = $this->makeInvoice($companyB);
        $this->makeInvoiceLog($companyB, $invoiceB, ['error_message' => 'HTTP 400: B-error']);

        $response = $this->actingAs($admin)->get(route('admin.zatca.logs', ['company_id' => $companyA->id]));

        $response->assertOk()
            ->assertSee($invoiceA->invoice_number)
            ->assertDontSee($invoiceB->invoice_number);
    }

    // ---------------------------------------------------------------
    // Log detail: XML / response, never a private key
    // ---------------------------------------------------------------

    public function test_log_detail_shows_xml_and_response_but_never_a_private_key(): void
    {
        $admin = $this->makeAdmin();
        $company = $this->makeCompany([
            'zatca_private_key' => 'TOP-SECRET-PRIVATE-KEY-VALUE',
            'zatca_production_secret' => 'TOP-SECRET-PRODUCTION-SECRET',
        ]);
        $invoice = $this->makeInvoice($company);
        $log = $this->makeInvoiceLog($company, $invoice, [
            'xml_payload' => '<Invoice>signed-xml-content</Invoice>',
            'response_payload' => '{"status":"rejected"}',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.zatca.logs.show', ['type' => 'invoice', 'log' => $log->id]));

        $response->assertOk()
            ->assertSee('signed-xml-content', false)
            ->assertSee('rejected', false)
            ->assertDontSee('TOP-SECRET-PRIVATE-KEY-VALUE')
            ->assertDontSee('TOP-SECRET-PRODUCTION-SECRET');
    }

    public function test_download_xml_returns_the_raw_signed_payload(): void
    {
        $admin = $this->makeAdmin();
        $company = $this->makeCompany();
        $invoice = $this->makeInvoice($company);
        $log = $this->makeInvoiceLog($company, $invoice, ['xml_payload' => '<Invoice>xml-body</Invoice>']);

        $response = $this->actingAs($admin)->get(route('admin.zatca.logs.xml', ['type' => 'invoice', 'log' => $log->id]));

        $response->assertOk();
        $this->assertStringContainsString('xml-body', $response->getContent());
    }

    // ---------------------------------------------------------------
    // Retry: never resubmits an already accepted document
    // ---------------------------------------------------------------

    public function test_retry_is_blocked_once_the_invoice_is_already_cleared(): void
    {
        $admin = $this->makeAdmin();
        $company = $this->makeCompany();
        $invoice = $this->makeInvoice($company);
        $log = $this->makeInvoiceLog($company, $invoice, ['status' => 'cleared', 'error_message' => null]);

        $response = $this->withConfirmedPassword($admin)
            ->post(route('admin.zatca.logs.retry', ['type' => 'invoice', 'log' => $log->id]));

        $response->assertSessionHasErrors('zatca');
        $this->assertSame(1, ZatcaInvoiceLog::where('invoice_id', $invoice->id)->count());
    }

    public function test_retry_creates_a_new_log_row_without_touching_the_failed_one(): void
    {
        $admin = $this->makeAdmin();
        $company = $this->makeCompany(); // not onboarded — submit() will fail gracefully with a new log row
        $invoice = $this->makeInvoice($company);
        $log = $this->makeInvoiceLog($company, $invoice, ['status' => 'failed', 'error_message' => 'Connection timed out']);

        $this->withConfirmedPassword($admin)
            ->post(route('admin.zatca.logs.retry', ['type' => 'invoice', 'log' => $log->id]));

        $this->assertSame(2, ZatcaInvoiceLog::where('invoice_id', $invoice->id)->count());
        $log->refresh();
        $this->assertSame('Connection timed out', $log->error_message); // original untouched
        $this->assertDatabaseHas('audit_logs', ['action' => 'zatca.retry_submission', 'company_id' => $company->id]);
    }

    // ---------------------------------------------------------------
    // Test connection: network-level only, audit logged
    // ---------------------------------------------------------------

    public function test_connection_reports_reachable_when_the_gateway_responds(): void
    {
        Http::fake(['gw-fatoora.zatca.gov.sa/*' => Http::response('', 404)]);

        $admin = $this->makeAdmin();
        $company = $this->makeCompany();

        $response = $this->withConfirmedPassword($admin)
            ->post(route('admin.zatca.companies.test-connection', $company));

        $response->assertRedirect();
        $response->assertSessionHas('status');
        $this->assertDatabaseHas('audit_logs', ['action' => 'zatca.test_connection', 'company_id' => $company->id]);
    }

    public function test_connection_reports_unreachable_on_a_connection_failure(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Could not resolve host.');
        });

        $admin = $this->makeAdmin();
        $company = $this->makeCompany();

        $response = $this->withConfirmedPassword($admin)
            ->post(route('admin.zatca.companies.test-connection', $company));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    // ---------------------------------------------------------------
    // Re-onboarding reset: clears credentials, audits presence flags only
    // ---------------------------------------------------------------

    public function test_reset_onboarding_clears_credentials_and_audits_without_leaking_secrets(): void
    {
        $admin = $this->makeAdmin();
        $company = $this->makeCompany([
            'zatca_onboarding_status' => 'onboarded',
            'zatca_csr' => 'csr-value',
            'zatca_private_key' => 'SECRET-PRIVATE-KEY',
            'zatca_production_csid' => 'csid-value',
            'zatca_production_secret' => 'SECRET-PRODUCTION-SECRET',
        ]);

        $response = $this->withConfirmedPassword($admin)
            ->post(route('admin.zatca.companies.reset-onboarding', $company));

        $response->assertRedirect();
        $company->refresh();
        $this->assertSame('not_started', $company->zatca_onboarding_status);
        $this->assertNull($company->zatca_private_key);
        $this->assertNull($company->zatca_production_csid);

        $log = AuditLog::where('action', 'zatca.reset_onboarding')->where('company_id', $company->id)->firstOrFail();
        $payload = json_encode([$log->old_value, $log->new_value]);
        $this->assertStringNotContainsString('SECRET-PRIVATE-KEY', $payload);
        $this->assertStringNotContainsString('SECRET-PRODUCTION-SECRET', $payload);
    }

    // ---------------------------------------------------------------
    // Permission gating
    // ---------------------------------------------------------------

    public function test_admin_staff_without_zatca_permission_is_forbidden(): void
    {
        $staff = User::factory()->create(['role' => 'admin_staff', 'company_id' => null]);

        $response = $this->actingAs($staff)->get(route('admin.zatca.index'));

        $response->assertForbidden();
    }
}
