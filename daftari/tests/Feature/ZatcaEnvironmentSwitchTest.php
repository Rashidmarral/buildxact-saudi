<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\ZatcaEnvironmentCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug report: a company that finished onboarding in Simulation, then
 * separately finished onboarding in Production, found that switching
 * back to Simulation asked them to redo CSR generation, the OTP
 * exchange, and compliance checks all over again — as if Simulation had
 * never been completed. Root cause: every zatca_* onboarding field lived
 * directly on the companies row, so ZatcaController::updateSettings()
 * had nowhere to keep an environment's progress when switching away from
 * it — it just overwrote those columns with whichever environment was
 * entered next (or blanked them entirely, since a plain environment
 * change was already treated as a "capability changed, reset
 * everything" case).
 *
 * Fixed by Company::switchZatcaEnvironment(): switching environments now
 * stashes the current onboarding columns into a per-(company,
 * environment) zatca_environment_credentials row before loading the
 * target environment's own saved row (or a blank slate, the first time
 * it's visited) back onto those columns. Changing the B2B/B2C sync
 * settings still wipes every environment's saved progress, since that
 * changes the capability every environment's CSR needs to declare.
 */
class ZatcaEnvironmentSwitchTest extends TestCase
{
    use RefreshDatabase;

    private const SIM_CSID = 'MIIDBzCCAe+gAwIBAgIUBSx0rLzK3YZPX+xqWHd5Snjpe5AwDQYJKoZIhvcNAQELBQAwEzERMA8GA1UEAwwIdGVzdC1lZ3MwHhcNMjYwODMwMjAzODAyWhcNMzYwODI3MjAzODAyWjATMREwDwYDVQQDDAh0ZXN0LWVnczCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBALq2jcY9XpSEhbetKvAAcMAP7Hjp5uJk7eo8luKn5Rgl9QqM/Bwgjuz6xKKASmn6QSaZOk44wdafGJvi/5MQ9fDVO1bCEUWDFVbMDblBxEjBe3N9FsQ33u4x1uZAUndQMaBukxH3+XxW7bGGCfkYJwJaDbSA6HAPF8kOzFNVjQKmyf3vOHa3uajxwMG4XKXqifFFhmn4jgCIhD5Nd6tLvY0dLMjD+MG7EVLPJCf0BGIMbyRJR6KWbz+lcCrO8hAC2UPX9jObTcz/kQQSDXWS8XnKjxyCr+BTVWZNYfIGOz3Y8YMM36IHBsHG/mIT3GXX6KKK4T9MmoGXcV87pV0dQusCAwEAAaNTMFEwHQYDVR0OBBYEFMVsaLNnewpMwUC33hHUv1RhoyBPMB8GA1UdIwQYMBaAFMVsaLNnewpMwUC33hHUv1RhoyBPMA8GA1UdEwEB/wQFMAMBAf8wDQYJKoZIhvcNAQELBQADggEBAKJPnEQOZlEPhevrJxthiJ2qZnemUKvvrdCJ1e5TqWG3+H2q+35dKjPE3QbPCnJtuw9iL54nkby8DiGrHRowJ5BcoxJbFernKLljBxCxRHOAp7M//nDXrYfWwrdDUqd4GE/T0buNrrCLSLEWdMxS1vEh4j/CV8h9wh9EVS7jgo99487iY/PxolzU5+Wjb+bxsgkrySpKhZt3En4A0jq3+3bP5bdFkj1fhmoAYzcIzUZj9ldcwrqesHGkF+SpGxRh6fll6pHZUnP+zqJH3Jqy1Ccer+M/MVmuqKQtwnMSb4yfRn8u9ffmzAAsBXnCSaceZYaYRPh/dubVhFrie20ODXY=';

    private const PROD_CSID = 'PROD-CSID-PLACEHOLDER-BASE64-CERTIFICATE-BYTES-DIFFER-FROM-SIM';

    private function makeOnboardedOwner(): array
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(),
            'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true,
            'has_zatca_phase2' => true,
        ]);

        $company = Company::create([
            'name' => 'Env Switch Co.', 'slug' => 'env-switch-'.uniqid(),
            'vat_number' => '399999999900003', 'cr_number' => 'CRN999999',
            'street_name' => 'Test Street', 'city' => 'Riyadh',
            'zatca_integration_mode' => Company::ZATCA_MODE_PHASE2,
            'zatca_environment' => 'simulation',
            'zatca_sync_b2b' => true,
            'zatca_sync_b2c' => true,
            'zatca_onboarding_status' => 'onboarded',
            'zatca_csr' => 'SIMULATION-CSR-CONTENT',
            'zatca_private_key' => 'sim-private-key',
            'zatca_compliance_request_id' => 'sim-compliance-req',
            'zatca_compliance_csid' => self::SIM_CSID,
            'zatca_compliance_secret' => 'sim-compliance-secret',
            'zatca_production_request_id' => 'sim-production-req',
            'zatca_production_csid' => self::SIM_CSID,
            'zatca_production_secret' => 'sim-production-secret',
            'zatca_linked_at' => now(),
        ]);

        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    public function test_switching_environments_saves_the_environment_being_left_instead_of_wiping_it(): void
    {
        [$company, $owner] = $this->makeOnboardedOwner();

        $response = $this->actingAs($owner)->put(route('app.zatca.settings.update'), [
            'zatca_environment' => 'production',
            'zatca_sync_frequency' => 'manual',
            'zatca_sync_b2b' => '1',
            'zatca_sync_b2c' => '1',
        ]);

        $response->assertSessionHas('status');
        $company->refresh();

        // The active columns now reflect Production, which has never been
        // onboarded yet — a blank slate, not "reset".
        $this->assertSame('production', $company->zatca_environment);
        $this->assertSame('not_started', $company->zatca_onboarding_status);
        $this->assertNull($company->zatca_csr);
        $this->assertNull($company->zatca_production_csid);

        // Simulation's completed onboarding was saved, not discarded.
        $simSlot = ZatcaEnvironmentCredential::where('company_id', $company->id)->where('environment', 'simulation')->first();
        $this->assertNotNull($simSlot);
        $this->assertSame('onboarded', $simSlot->onboarding_status);
        $this->assertSame('SIMULATION-CSR-CONTENT', $simSlot->csr);
        $this->assertSame(self::SIM_CSID, $simSlot->production_csid);
        $this->assertSame('sim-production-secret', $simSlot->production_secret);
    }

    public function test_switching_back_to_a_previously_completed_environment_restores_its_progress(): void
    {
        [$company, $owner] = $this->makeOnboardedOwner();

        // Leave Simulation (saved) and enter Production.
        $this->actingAs($owner)->put(route('app.zatca.settings.update'), [
            'zatca_environment' => 'production', 'zatca_sync_frequency' => 'manual',
            'zatca_sync_b2b' => '1', 'zatca_sync_b2c' => '1',
        ]);

        // Complete Production onboarding too (as the real wizard would,
        // one step at a time — set directly here for a focused test).
        $company->refresh();
        $company->update([
            'zatca_onboarding_status' => 'onboarded',
            'zatca_csr' => 'PRODUCTION-CSR-CONTENT',
            'zatca_private_key' => 'prod-private-key',
            'zatca_production_csid' => self::PROD_CSID,
            'zatca_production_secret' => 'prod-production-secret',
            'zatca_linked_at' => now(),
        ]);

        // Reload $owner so Auth::user()->company inside the next request
        // re-queries the row instead of returning the "company" relation
        // this same in-memory $owner instance already cached from the
        // first ->put() call above (an artifact of reusing one object
        // across simulated requests in-process, not something a real,
        // separate HTTP request would ever hit).
        $owner->unsetRelation('company');

        // Now switch back to Simulation — this is the user's exact
        // complaint: "why would previous mode data be reset".
        $response = $this->actingAs($owner)->put(route('app.zatca.settings.update'), [
            'zatca_environment' => 'simulation', 'zatca_sync_frequency' => 'manual',
            'zatca_sync_b2b' => '1', 'zatca_sync_b2c' => '1',
        ]);

        $response->assertSessionHas('status');
        $company->refresh();

        $this->assertSame('simulation', $company->zatca_environment);
        $this->assertSame('onboarded', $company->zatca_onboarding_status);
        $this->assertSame('SIMULATION-CSR-CONTENT', $company->zatca_csr);
        $this->assertSame(self::SIM_CSID, $company->zatca_production_csid);
        $this->assertSame('sim-production-secret', $company->zatca_production_secret);

        // Production's own progress is now the one safely tucked away.
        $prodSlot = ZatcaEnvironmentCredential::where('company_id', $company->id)->where('environment', 'production')->first();
        $this->assertNotNull($prodSlot);
        $this->assertSame('onboarded', $prodSlot->onboarding_status);
        $this->assertSame(self::PROD_CSID, $prodSlot->production_csid);

        // And the dashboard itself shows Simulation's wizard as complete,
        // not the CSR-generation form again.
        $dashboard = $this->actingAs($owner)->get(route('app.zatca.dashboard'));
        $dashboard->assertOk();
        $dashboard->assertSee('SIMULATION-CSR-CONTENT');
        $dashboard->assertDontSee(__('Save & Generate CSR'));
    }

    public function test_changing_b2c_sync_wipes_every_environments_saved_progress(): void
    {
        [$company, $owner] = $this->makeOnboardedOwner();

        // Save Simulation's progress into a slot by switching away, then
        // back — now there's a saved slot to prove gets wiped.
        $this->actingAs($owner)->put(route('app.zatca.settings.update'), [
            'zatca_environment' => 'production', 'zatca_sync_frequency' => 'manual',
            'zatca_sync_b2b' => '1', 'zatca_sync_b2c' => '1',
        ]);
        $this->assertDatabaseCount('zatca_environment_credentials', 1);

        $response = $this->actingAs($owner)->put(route('app.zatca.settings.update'), [
            'zatca_environment' => 'production', 'zatca_sync_frequency' => 'manual',
            'zatca_sync_b2b' => '1', 'zatca_sync_b2c' => '0',
        ]);

        $response->assertSessionHas('status');
        $company->refresh();

        $this->assertSame('not_started', $company->zatca_onboarding_status);
        $this->assertNull($company->zatca_csr);
        $this->assertFalse((bool) $company->zatca_sync_b2c);
        $this->assertDatabaseCount('zatca_environment_credentials', 0);
    }
}
