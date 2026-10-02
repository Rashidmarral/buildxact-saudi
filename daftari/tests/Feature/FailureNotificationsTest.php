<?php

namespace Tests\Feature;

use App\Jobs\SendWebhookDelivery;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Webhook;
use App\Notifications\GenericNotification;
use App\Services\Zatca\ZatcaSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Audit finding LOW-30: a failed webhook delivery or a rejected ZATCA
 * submission left nothing but a row in a log table nobody was looking
 * at — no in-app notification ever told anyone. ZatcaSyncService now
 * notifies every user with the "zatca" permission when a real submission
 * attempt is rejected or throws, and SendWebhookDelivery::failed() (which
 * Laravel calls once all retries are exhausted) notifies every user with
 * the "settings" permission.
 */
class FailureNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_CSID = 'MIIDBzCCAe+gAwIBAgIUBSx0rLzK3YZPX+xqWHd5Snjpe5AwDQYJKoZIhvcNAQELBQAwEzERMA8GA1UEAwwIdGVzdC1lZ3MwHhcNMjYwODMwMjAzODAyWhcNMzYwODI3MjAzODAyWjATMREwDwYDVQQDDAh0ZXN0LWVnczCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBALq2jcY9XpSEhbetKvAAcMAP7Hjp5uJk7eo8luKn5Rgl9QqM/Bwgjuz6xKKASmn6QSaZOk44wdafGJvi/5MQ9fDVO1bCEUWDFVbMDblBxEjBe3N9FsQ33u4x1uZAUndQMaBukxH3+XxW7bGGCfkYJwJaDbSA6HAPF8kOzFNVjQKmyf3vOHa3uajxwMG4XKXqifFFhmn4jgCIhD5Nd6tLvY0dLMjD+MG7EVLPJCf0BGIMbyRJR6KWbz+lcCrO8hAC2UPX9jObTcz/kQQSDXWS8XnKjxyCr+BTVWZNYfIGOz3Y8YMM36IHBsHG/mIT3GXX6KKK4T9MmoGXcV87pV0dQusCAwEAAaNTMFEwHQYDVR0OBBYEFMVsaLNnewpMwUC33hHUv1RhoyBPMB8GA1UdIwQYMBaAFMVsaLNnewpMwUC33hHUv1RhoyBPMA8GA1UdEwEB/wQFMAMBAf8wDQYJKoZIhvcNAQELBQADggEBAKJPnEQOZlEPhevrJxthiJ2qZnemUKvvrdCJ1e5TqWG3+H2q+35dKjPE3QbPCnJtuw9iL54nkby8DiGrHRowJ5BcoxJbFernKLljBxCxRHOAp7M//nDXrYfWwrdDUqd4GE/T0buNrrCLSLEWdMxS1vEh4j/CV8h9wh9EVS7jgo99487iY/PxolzU5+Wjb+bxsgkrySpKhZt3En4A0jq3+3bP5bdFkj1fhmoAYzcIzUZj9ldcwrqesHGkF+SpGxRh6fll6pHZUnP+zqJH3Jqy1Ccer+M/MVmuqKQtwnMSb4yfRn8u9ffmzAAsBXnCSaceZYaYRPh/dubVhFrie20ODXY=';

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_a_rejected_zatca_invoice_submission_notifies_users_with_zatca_permission(): void
    {
        Notification::fake();
        Http::fake(['*' => Http::response(['status' => 'rejected'], 400)]);

        $company = Company::create([
            'name' => 'Sync Co.', 'slug' => 'sync-'.uniqid(),
            'zatca_onboarding_status' => 'onboarded',
            'zatca_production_csid' => self::TEST_CSID,
            'zatca_production_secret' => 'secret-value',
            'zatca_integration_mode' => 'phase2',
        ]);
        $owner = $this->makeOwner($company);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client']);
        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-'.uniqid(),
            'type' => 'standard', 'issue_date' => now()->toDateString(), 'currency' => $company->currency,
            'subtotal' => 100, 'discount_total' => 0, 'vat_total' => 15, 'total' => 115,
        ]);

        $log = app(ZatcaSyncService::class)->submit($invoice);

        $this->assertSame('failed', $log->status);
        Notification::assertSentTo($owner, GenericNotification::class, function (GenericNotification $notification) use ($invoice) {
            return str_contains($notification->body, $invoice->invoice_number);
        });
    }

    public function test_a_webhook_that_exhausts_all_retries_notifies_users_with_settings_permission(): void
    {
        Notification::fake();

        $company = Company::create(['name' => 'Hook Co.', 'slug' => 'hook-'.uniqid()]);
        $owner = $this->makeOwner($company);
        $webhook = Webhook::create([
            'company_id' => $company->id, 'url' => 'https://example.com/hook',
            'events' => ['invoice.sent'], 'is_active' => true,
        ]);

        $job = new SendWebhookDelivery($webhook->id, 'invoice.sent', ['id' => 1]);
        $job->failed(new \Exception('Connection timed out'));

        Notification::assertSentTo($owner, GenericNotification::class, function (GenericNotification $notification) use ($webhook) {
            return str_contains($notification->body, $webhook->url);
        });
    }

    public function test_a_webhook_failure_notification_is_not_sent_to_a_user_without_settings_permission(): void
    {
        Notification::fake();

        $company = Company::create(['name' => 'Hook Co.', 'slug' => 'hook-'.uniqid()]);
        $staff = User::factory()->create(['role' => 'member', 'company_id' => $company->id, 'status' => 'active']);
        $webhook = Webhook::create([
            'company_id' => $company->id, 'url' => 'https://example.com/hook',
            'events' => ['invoice.sent'], 'is_active' => true,
        ]);

        $job = new SendWebhookDelivery($webhook->id, 'invoice.sent', ['id' => 1]);
        $job->failed(new \Exception('Connection timed out'));

        Notification::assertNotSentTo($staff, GenericNotification::class);
    }
}
