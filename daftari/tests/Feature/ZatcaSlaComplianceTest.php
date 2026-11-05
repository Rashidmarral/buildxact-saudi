<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\ZatcaInvoiceLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Gap found during a ZATCA compliance audit: a simplified (B2C) tax
 * invoice must be REPORTED to ZATCA within 24 hours of issuance (unlike
 * a standard B2B invoice, which clears before it ever reaches the
 * buyer) — nothing in the codebase enforced or even tracked this.
 * ZatcaSyncInvoices just ran whichever cadence a company picked
 * (including "weekly", which can sit on an invoice for up to 7 days)
 * with no notion of an invoice becoming overdue.
 *
 * Two changes close this: (1) ZatcaController::updateSettings() now
 * rejects saving "weekly" sync frequency while Simplified (B2C) sync is
 * enabled, since that combination can never reliably meet the 24-hour
 * deadline; (2) DashboardController::zatcaSlaWarning() surfaces any
 * simplified invoice that's been sitting unsynced for 18+ hours (and
 * flags it as breached once it passes 24), the same way vatDueNotice()
 * already surfaces an overdue VAT filing — a frequency-agnostic safety
 * net that also catches a stalled "daily"/"manual" company, not just
 * the weekly case the validation rule blocks outright.
 */
class ZatcaSlaComplianceTest extends TestCase
{
    use RefreshDatabase;

    private const PROD_CSID = 'PROD-CSID-PLACEHOLDER-BASE64-CERTIFICATE-BYTES';

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeOnboardedOwner(array $companyOverrides = []): array
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(),
            'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true,
            'has_zatca_phase2' => true,
        ]);

        $company = Company::create(array_merge([
            'name' => 'Zatca SLA Co.', 'slug' => 'zatca-sla-'.uniqid(),
            'vat_number' => '399999999900003', 'cr_number' => 'CRN999999',
            'street_name' => 'Test Street', 'city' => 'Riyadh', 'currency' => 'SAR',
            'zatca_integration_mode' => Company::ZATCA_MODE_PHASE2,
            'zatca_environment' => 'production',
            'zatca_sync_b2b' => true,
            'zatca_sync_b2c' => true,
            'zatca_onboarding_status' => 'onboarded',
            'zatca_production_csid' => self::PROD_CSID,
            'zatca_linked_at' => now(),
        ], $companyOverrides));

        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    private function makeUnsyncedSimplifiedInvoice(Company $company, Carbon $createdAt): Invoice
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Walk-in Customer']);

        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id,
            'invoice_number' => 'INV-SLA-'.uniqid(), 'type' => 'simplified', 'status' => 'sent',
            'issue_date' => $createdAt->toDateString(), 'due_date' => $createdAt->copy()->addDays(30),
            'currency' => $company->currency, 'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);
        $invoice->forceFill(['created_at' => $createdAt])->save();

        return $invoice;
    }

    // ------------------------------------------------------------------
    // Settings validation
    // ------------------------------------------------------------------

    public function test_weekly_sync_frequency_is_rejected_while_simplified_b2c_sync_is_enabled(): void
    {
        [$company, $owner] = $this->makeOnboardedOwner();

        $response = $this->actingAs($owner)->put(route('app.zatca.settings.update'), [
            'zatca_environment' => 'production',
            'zatca_sync_frequency' => 'weekly',
            'zatca_sync_b2b' => '1',
            'zatca_sync_b2c' => '1',
        ]);

        $response->assertSessionHasErrors('zatca_sync_frequency');
        $company->refresh();
        $this->assertNotSame('weekly', $company->zatca_sync_frequency);
    }

    public function test_weekly_sync_frequency_is_allowed_once_simplified_b2c_sync_is_disabled(): void
    {
        [$company, $owner] = $this->makeOnboardedOwner();

        $response = $this->actingAs($owner)->put(route('app.zatca.settings.update'), [
            'zatca_environment' => 'production',
            'zatca_sync_frequency' => 'weekly',
            'zatca_sync_b2b' => '1',
            'zatca_sync_b2c' => '0',
        ]);

        $response->assertSessionDoesntHaveErrors('zatca_sync_frequency');
        $company->refresh();
        $this->assertSame('weekly', $company->zatca_sync_frequency);
    }

    public function test_daily_sync_frequency_is_still_allowed_with_simplified_b2c_sync_enabled(): void
    {
        [$company, $owner] = $this->makeOnboardedOwner();

        $response = $this->actingAs($owner)->put(route('app.zatca.settings.update'), [
            'zatca_environment' => 'production',
            'zatca_sync_frequency' => 'daily',
            'zatca_sync_b2b' => '1',
            'zatca_sync_b2c' => '1',
        ]);

        $response->assertSessionDoesntHaveErrors('zatca_sync_frequency');
        $company->refresh();
        $this->assertSame('daily', $company->zatca_sync_frequency);
    }

    // ------------------------------------------------------------------
    // Dashboard warning banner
    // ------------------------------------------------------------------

    public function test_the_banner_does_not_show_when_nothing_is_overdue(): void
    {
        [$company, $owner] = $this->makeOnboardedOwner();
        $this->makeUnsyncedSimplifiedInvoice($company, now()->subHours(2));

        $response = $this->actingAs($owner)->get(route('app.dashboard'));

        $response->assertOk();
        $response->assertDontSee('24-hour', false);
    }

    public function test_the_banner_shows_a_warning_once_an_invoice_passes_18_hours_unsynced(): void
    {
        [$company, $owner] = $this->makeOnboardedOwner();
        $this->makeUnsyncedSimplifiedInvoice($company, now()->subHours(20));

        $response = $this->actingAs($owner)->get(route('app.dashboard'));

        $response->assertOk();
        $response->assertSee('approaching', false);
        $response->assertDontSee('missed', false);
    }

    public function test_the_banner_shows_breached_once_an_invoice_passes_24_hours_unsynced(): void
    {
        [$company, $owner] = $this->makeOnboardedOwner();
        $this->makeUnsyncedSimplifiedInvoice($company, now()->subHours(30));

        $response = $this->actingAs($owner)->get(route('app.dashboard'));

        $response->assertOk();
        $response->assertSee('missed', false);
    }

    public function test_the_banner_does_not_show_once_the_invoice_is_actually_cleared(): void
    {
        [$company, $owner] = $this->makeOnboardedOwner();
        $invoice = $this->makeUnsyncedSimplifiedInvoice($company, now()->subHours(30));
        ZatcaInvoiceLog::create([
            'company_id' => $company->id, 'invoice_id' => $invoice->id, 'environment' => 'production',
            'invoice_type' => 'simplified', 'direction' => 'reporting', 'status' => 'reported', 'submitted_at' => now(),
            'request_uuid' => (string) \Illuminate\Support\Str::uuid(),
        ]);

        $response = $this->actingAs($owner)->get(route('app.dashboard'));

        $response->assertOk();
        $response->assertDontSee('24-hour', false);
    }

    public function test_the_banner_does_not_show_when_simplified_b2c_sync_is_disabled(): void
    {
        [$company, $owner] = $this->makeOnboardedOwner(['zatca_sync_b2c' => false]);
        $this->makeUnsyncedSimplifiedInvoice($company, now()->subHours(30));

        $response = $this->actingAs($owner)->get(route('app.dashboard'));

        $response->assertOk();
        $response->assertDontSee('24-hour', false);
    }

    public function test_the_banner_does_not_show_for_a_company_not_actually_zatca_onboarded(): void
    {
        [$company, $owner] = $this->makeOnboardedOwner(['zatca_onboarding_status' => 'not_started', 'zatca_production_csid' => null]);
        $this->makeUnsyncedSimplifiedInvoice($company, now()->subHours(30));

        $response = $this->actingAs($owner)->get(route('app.dashboard'));

        $response->assertOk();
        $response->assertDontSee('24-hour', false);
    }
}
