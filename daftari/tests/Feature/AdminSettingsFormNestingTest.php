<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug report: on Platform Settings → Platform Identity, the "Sync now"
 * button (shown next to a ZATCA-onboarded billing company with pending
 * documents) sat in its own <form> nested inside the tab's outer identity
 * <form>. A browser refuses to open a second <form> while one is already
 * in scope — the nested form's own </form> closed the OUTER form early
 * instead, stranding "Save Identity settings" (and the invoice-number-
 * prefix field before it) outside of any form. Same root cause and same
 * fix shape as the item-edit page's Save button (see
 * ItemFormPageRendersTest) — the "Sync now" form was pulled out to be a
 * detached <form id="..."> referenced via the button's form="" attribute.
 */
class AdminSettingsFormNestingTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_CSID = 'MIIDBzCCAe+gAwIBAgIUBSx0rLzK3YZPX+xqWHd5Snjpe5AwDQYJKoZIhvcNAQELBQAwEzERMA8GA1UEAwwIdGVzdC1lZ3MwHhcNMjYwODMwMjAzODAyWhcNMzYwODI3MjAzODAyWjATMREwDwYDVQQDDAh0ZXN0LWVnczCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBALq2jcY9XpSEhbetKvAAcMAP7Hjp5uJk7eo8luKn5Rgl9QqM/Bwgjuz6xKKASmn6QSaZOk44wdafGJvi/5MQ9fDVO1bCEUWDFVbMDblBxEjBe3N9FsQ33u4x1uZAUndQMaBukxH3+XxW7bGGCfkYJwJaDbSA6HAPF8kOzFNVjQKmyf3vOHa3uajxwMG4XKXqifFFhmn4jgCIhD5Nd6tLvY0dLMjD+MG7EVLPJCf0BGIMbyRJR6KWbz+lcCrO8hAC2UPX9jObTcz/kQQSDXWS8XnKjxyCr+BTVWZNYfIGOz3Y8YMM36IHBsHG/mIT3GXX6KKK4T9MmoGXcV87pV0dQusCAwEAAaNTMFEwHQYDVR0OBBYEFMVsaLNnewpMwUC33hHUv1RhoyBPMB8GA1UdIwQYMBaAFMVsaLNnewpMwUC33hHUv1RhoyBPMA8GA1UdEwEB/wQFMAMBAf8wDQYJKoZIhvcNAQELBQADggEBAKJPnEQOZlEPhevrJxthiJ2qZnemUKvvrdCJ1e5TqWG3+H2q+35dKjPE3QbPCnJtuw9iL54nkby8DiGrHRowJ5BcoxJbFernKLljBxCxRHOAp7M//nDXrYfWwrdDUqd4GE/T0buNrrCLSLEWdMxS1vEh4j/CV8h9wh9EVS7jgo99487iY/PxolzU5+Wjb+bxsgkrySpKhZt3En4A0jq3+3bP5bdFkj1fhmoAYzcIzUZj9ldcwrqesHGkF+SpGxRh6fll6pHZUnP+zqJH3Jqy1Ccer+M/MVmuqKQtwnMSb4yfRn8u9ffmzAAsBXnCSaceZYaYRPh/dubVhFrie20ODXY=';

    /**
     * Builds a company that is fully ZATCA Phase 2 onboarded, with one
     * pending (unsynced) invoice, and points the platform's billing
     * company at it — the exact combination that makes the "Sync now"
     * button render on the identity tab.
     */
    private function makeBillingCompanyWithPendingSync(): Company
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(),
            'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true,
            'has_zatca_phase2' => true,
        ]);

        $company = Company::create([
            'name' => 'Billing Co.', 'slug' => 'billing-'.uniqid(),
            'zatca_integration_mode' => Company::ZATCA_MODE_PHASE2,
            'zatca_onboarding_status' => 'onboarded',
            'zatca_production_csid' => self::TEST_CSID,
            'zatca_production_secret' => 'secret-value',
        ]);

        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth()]);

        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client']);
        Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-'.uniqid(),
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(), 'currency' => $company->currency,
            'subtotal' => 100, 'discount_total' => 0, 'vat_total' => 15, 'total' => 115,
        ]);

        Setting::set('platform_billing_company_id', (string) $company->id);

        return $company;
    }

    private function assertNoNestedForms(string $html): void
    {
        preg_match_all('/<\/?form\b[^>]*>/i', $html, $matches);
        $this->assertNotEmpty($matches[0], 'Expected to find at least one <form> tag on the page.');

        $depth = 0;
        foreach ($matches[0] as $tag) {
            if (str_starts_with($tag, '</')) {
                $depth--;
            } else {
                $depth++;
                $this->assertLessThanOrEqual(1, $depth, "A <form> tag opened while another was still open: {$tag}");
            }
        }

        $this->assertSame(0, $depth, 'A <form> tag was left unclosed.');
    }

    public function test_the_identity_tab_never_nests_the_sync_now_form_inside_the_identity_form(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'company_id' => null]);
        $this->makeBillingCompanyWithPendingSync();

        $response = $this->actingAs($admin)->get(route('admin.settings.edit'));

        $response->assertOk();
        $response->assertSee(__('Sync now'));
        $this->assertNoNestedForms($response->getContent());
    }

    public function test_the_sync_now_button_submits_the_sync_form_not_the_identity_form(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'company_id' => null]);
        $company = $this->makeBillingCompanyWithPendingSync();

        $response = $this->actingAs($admin)->get(route('admin.settings.edit'));
        $html = $response->getContent();

        $syncFormId = "sync-billing-company-{$company->id}";
        $this->assertStringContainsString('id="'.$syncFormId.'"', $html);
        $this->assertStringContainsString('form="'.$syncFormId.'"', $html);

        preg_match('/<form id="'.preg_quote($syncFormId, '/').'"[^>]*action="([^"]+)"/', $html, $m);
        $this->assertNotEmpty($m, 'Could not find the detached sync form.');
        $this->assertSame(route('admin.zatca.companies.sync', $company), html_entity_decode($m[1]));
    }
}
