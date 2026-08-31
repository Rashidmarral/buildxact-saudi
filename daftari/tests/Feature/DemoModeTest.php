<?php

namespace Tests\Feature;

use App\Console\Commands\InstallDemoData;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\User;
use App\Models\ZatcaInvoiceLog;
use App\Services\Zatca\ZatcaSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Module 23 (Demo Mode & Demo Data): the is_demo company flag and its five
 * guarantees — destructive actions, real payment processing, and real
 * ZATCA submissions are all refused for a demo company, its data can never
 * be deleted from the company panel, and the "DEMO MODE" banner shows only
 * for a demo company — plus the demo:install / demo:reset commands, which
 * must never touch a second, non-demo company's data.
 */
class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A throwaway self-signed certificate (base64 DER, matching the shape
     * of a real ZATCA-issued binarySecurityToken) so submit()'s certificate
     * parsing succeeds and execution reaches the demo guard below it,
     * instead of failing earlier on an unparseable placeholder string. No
     * corresponding private key is set, so buildSignedPayload() naturally
     * produces an unsigned payload — fine, since nothing here is ever
     * actually sent to ZATCA.
     */
    private const TEST_CSID = 'MIIDBzCCAe+gAwIBAgIUBSx0rLzK3YZPX+xqWHd5Snjpe5AwDQYJKoZIhvcNAQELBQAwEzERMA8GA1UEAwwIdGVzdC1lZ3MwHhcNMjYwODMwMjAzODAyWhcNMzYwODI3MjAzODAyWjATMREwDwYDVQQDDAh0ZXN0LWVnczCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBALq2jcY9XpSEhbetKvAAcMAP7Hjp5uJk7eo8luKn5Rgl9QqM/Bwgjuz6xKKASmn6QSaZOk44wdafGJvi/5MQ9fDVO1bCEUWDFVbMDblBxEjBe3N9FsQ33u4x1uZAUndQMaBukxH3+XxW7bGGCfkYJwJaDbSA6HAPF8kOzFNVjQKmyf3vOHa3uajxwMG4XKXqifFFhmn4jgCIhD5Nd6tLvY0dLMjD+MG7EVLPJCf0BGIMbyRJR6KWbz+lcCrO8hAC2UPX9jObTcz/kQQSDXWS8XnKjxyCr+BTVWZNYfIGOz3Y8YMM36IHBsHG/mIT3GXX6KKK4T9MmoGXcV87pV0dQusCAwEAAaNTMFEwHQYDVR0OBBYEFMVsaLNnewpMwUC33hHUv1RhoyBPMB8GA1UdIwQYMBaAFMVsaLNnewpMwUC33hHUv1RhoyBPMA8GA1UdEwEB/wQFMAMBAf8wDQYJKoZIhvcNAQELBQADggEBAKJPnEQOZlEPhevrJxthiJ2qZnemUKvvrdCJ1e5TqWG3+H2q+35dKjPE3QbPCnJtuw9iL54nkby8DiGrHRowJ5BcoxJbFernKLljBxCxRHOAp7M//nDXrYfWwrdDUqd4GE/T0buNrrCLSLEWdMxS1vEh4j/CV8h9wh9EVS7jgo99487iY/PxolzU5+Wjb+bxsgkrySpKhZt3En4A0jq3+3bP5bdFkj1fhmoAYzcIzUZj9ldcwrqesHGkF+SpGxRh6fll6pHZUnP+zqJH3Jqy1Ccer+M/MVmuqKQtwnMSb4yfRn8u9ffmzAAsBXnCSaceZYaYRPh/dubVhFrie20ODXY=';

    private function makeCompany(array $overrides = []): Company
    {
        return Company::create(array_merge([
            'name' => 'Acme Trading', 'slug' => 'acme-'.uniqid(), 'status' => 'active',
        ], $overrides));
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create([
            'role' => 'owner', 'company_id' => $company->id, 'status' => 'active',
        ]);
    }

    private function makePlan(array $overrides = []): Plan
    {
        return Plan::create(array_merge([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true,
        ], $overrides));
    }

    // ---------------------------------------------------------------
    // 1. Destructive actions / deleting demo data
    // ---------------------------------------------------------------

    public function test_a_delete_request_is_blocked_for_a_demo_company(): void
    {
        $company = $this->makeCompany(['is_demo' => true]);
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Demo Client']);

        $response = $this->actingAs($owner)->delete(route('app.clients.destroy', $client));

        $response->assertSessionHasErrors('demo');
        $this->assertDatabaseHas('clients', ['id' => $client->id]);
    }

    public function test_a_delete_request_still_works_for_a_regular_company(): void
    {
        $company = $this->makeCompany(['is_demo' => false]);
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Real Client']);

        $response = $this->actingAs($owner)->delete(route('app.clients.destroy', $client));

        $response->assertSessionDoesntHaveErrors('demo');
        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
    }

    /**
     * Commercial audit finding: void/cancel/revoke actions are routed as
     * POST, not DELETE, so PreventDemoDestruction's original DELETE-only
     * check never caught them even though they're just as destructive
     * (they reverse a posted journal entry and permanently change a
     * document's status). Covered here via Invoice::cancel(); the fix
     * matches by route-name suffix, so it applies identically to every
     * other *.void/*.cancel/*.revoke action across the app.
     */
    public function test_a_void_style_action_is_blocked_for_a_demo_company(): void
    {
        $company = $this->makeCompany(['is_demo' => true]);
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Demo Client']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id,
            'invoice_number' => 'INV-'.uniqid(), 'type' => 'standard', 'status' => 'sent',
            'issue_date' => now()->toDateString(), 'currency' => 'SAR',
        ]);

        $response = $this->actingAs($owner)->post(route('app.invoices.cancel', $invoice));

        $response->assertSessionHasErrors('demo');
        $invoice->refresh();
        $this->assertSame('sent', $invoice->status);
    }

    public function test_a_void_style_action_still_works_for_a_regular_company(): void
    {
        $company = $this->makeCompany(['is_demo' => false]);
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Real Client']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id,
            'invoice_number' => 'INV-'.uniqid(), 'type' => 'standard', 'status' => 'sent',
            'issue_date' => now()->toDateString(), 'currency' => 'SAR',
        ]);

        $response = $this->actingAs($owner)->post(route('app.invoices.cancel', $invoice));

        $response->assertSessionDoesntHaveErrors('demo');
        $invoice->refresh();
        $this->assertSame('cancelled', $invoice->status);
    }

    // ---------------------------------------------------------------
    // 2. Real payment processing
    // ---------------------------------------------------------------

    public function test_upgrading_via_a_real_online_gateway_is_blocked_for_a_demo_company(): void
    {
        Http::fake();

        $company = $this->makeCompany(['is_demo' => true]);
        $owner = $this->makeOwner($company);
        $plan = $this->makePlan();
        PaymentGateway::create(['company_id' => null, 'provider' => 'moyasar', 'mode' => 'test', 'is_enabled' => true, 'credentials' => ['secret_key' => 'sk']]);

        $response = $this->actingAs($owner)->post(route('app.billing.upgrade'), [
            'plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'provider' => 'moyasar',
        ]);

        $response->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_upgrading_via_a_real_online_gateway_still_works_for_a_regular_company(): void
    {
        Http::fake(['api.moyasar.com/*' => Http::response(['id' => 'inv_123', 'url' => 'https://api.moyasar.com/pay/inv_123'], 200)]);

        $company = $this->makeCompany(['is_demo' => false]);
        $owner = $this->makeOwner($company);
        $plan = $this->makePlan();
        PaymentGateway::create(['company_id' => null, 'provider' => 'moyasar', 'mode' => 'test', 'is_enabled' => true, 'credentials' => ['secret_key' => 'sk']]);

        $response = $this->actingAs($owner)->post(route('app.billing.upgrade'), [
            'plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'provider' => 'moyasar',
        ]);

        $response->assertRedirect('https://api.moyasar.com/pay/inv_123');
    }

    public function test_bank_transfer_upgrade_still_works_for_a_demo_company(): void
    {
        $company = $this->makeCompany(['is_demo' => true]);
        $owner = $this->makeOwner($company);
        $plan = $this->makePlan();
        PaymentGateway::create(['company_id' => null, 'provider' => PaymentGateway::BANK_TRANSFER, 'mode' => 'test', 'is_enabled' => true]);

        $response = $this->actingAs($owner)->post(route('app.billing.upgrade'), [
            'plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'provider' => PaymentGateway::BANK_TRANSFER,
        ]);

        $response->assertRedirect(route('app.billing.bank-transfer', Payment::where('company_id', $company->id)->firstOrFail()->id));
    }

    // ---------------------------------------------------------------
    // 3. Real ZATCA submissions
    // ---------------------------------------------------------------

    private function makeOnboardedCompany(bool $isDemo): Company
    {
        return $this->makeCompany([
            'is_demo' => $isDemo,
            'zatca_onboarding_status' => 'onboarded',
            'zatca_production_csid' => self::TEST_CSID,
            'zatca_production_secret' => 'secret-value',
            'zatca_integration_mode' => 'phase2',
        ]);
    }

    private function makeInvoice(Company $company): Invoice
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client']);

        return Invoice::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'invoice_number' => 'INV-'.uniqid(),
            'type' => 'standard',
            'issue_date' => now()->toDateString(),
            'currency' => $company->currency,
            'subtotal' => 100,
            'discount_total' => 0,
            'vat_total' => 15,
            'total' => 115,
        ]);
    }

    public function test_zatca_submission_is_short_circuited_for_a_demo_company_without_any_real_http_call(): void
    {
        Http::fake();

        $company = $this->makeOnboardedCompany(true);
        $invoice = $this->makeInvoice($company);

        $log = app(ZatcaSyncService::class)->submit($invoice);

        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('Demo mode', $log->error_message);
        Http::assertNothingSent();
    }

    public function test_zatca_submission_is_attempted_normally_for_a_regular_onboarded_company(): void
    {
        Http::fake(['*' => Http::response(['status' => 'rejected'], 400)]);

        $company = $this->makeOnboardedCompany(false);
        $invoice = $this->makeInvoice($company);

        $log = app(ZatcaSyncService::class)->submit($invoice);

        Http::assertSentCount(1);
        $this->assertNotSame(0, ZatcaInvoiceLog::where('invoice_id', $invoice->id)->count());
        $this->assertStringNotContainsString('Demo mode', (string) $log->error_message);
    }

    // ---------------------------------------------------------------
    // 4. Demo Mode banner
    // ---------------------------------------------------------------

    public function test_the_demo_mode_banner_is_shown_for_a_demo_company(): void
    {
        $company = $this->makeCompany(['is_demo' => true]);
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->get('/app');

        $response->assertOk()->assertSee('DEMO MODE');
    }

    public function test_the_demo_mode_banner_is_not_shown_for_a_regular_company(): void
    {
        $company = $this->makeCompany(['is_demo' => false]);
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->get('/app');

        $response->assertOk()->assertDontSee('DEMO MODE');
    }

    // ---------------------------------------------------------------
    // 5. demo:install / demo:reset commands
    // ---------------------------------------------------------------

    public function test_demo_install_creates_a_demo_company_with_its_team(): void
    {
        Artisan::call(InstallDemoData::class);

        $company = Company::where('slug', 'al-rashid-trading')->firstOrFail();
        $this->assertTrue($company->isDemo());
        $this->assertDatabaseHas('users', ['email' => 'owner@daftari.local', 'company_id' => $company->id]);
        $this->assertDatabaseHas('users', ['email' => 'accountant@daftari.local', 'company_id' => $company->id]);
        $this->assertDatabaseHas('users', ['email' => 'sales@daftari.local', 'company_id' => $company->id]);
    }

    public function test_demo_reset_removes_the_demo_company_and_its_users_without_touching_a_real_company(): void
    {
        Artisan::call(InstallDemoData::class);
        $demoCompany = Company::where('slug', 'al-rashid-trading')->firstOrFail();

        $realCompany = $this->makeCompany(['is_demo' => false]);
        $realOwner = $this->makeOwner($realCompany);
        $realClient = Client::create(['company_id' => $realCompany->id, 'name' => 'Real Customer']);

        Artisan::call('demo:reset', ['--force' => true]);

        $this->assertDatabaseMissing('companies', ['id' => $demoCompany->id]);
        $this->assertDatabaseMissing('users', ['email' => 'owner@daftari.local']);
        $this->assertDatabaseMissing('users', ['email' => 'accountant@daftari.local']);
        $this->assertDatabaseMissing('users', ['email' => 'sales@daftari.local']);

        $this->assertDatabaseHas('companies', ['id' => $realCompany->id]);
        $this->assertDatabaseHas('users', ['id' => $realOwner->id]);
        $this->assertDatabaseHas('clients', ['id' => $realClient->id]);
    }

    public function test_demo_reset_reports_nothing_to_do_when_no_demo_company_exists(): void
    {
        $exitCode = Artisan::call('demo:reset', ['--force' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('nothing to reset', Artisan::output());
    }
}
