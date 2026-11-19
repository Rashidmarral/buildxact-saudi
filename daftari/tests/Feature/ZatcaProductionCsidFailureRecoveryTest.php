<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug report: after completing simulation onboarding, switching to the
 * production environment, and running through CSR generation, the OTP
 * exchange, and compliance checks (which is what actually registers the
 * EGS unit with ZATCA's Fatoora portal), a rejected "issue production
 * CSID" call left the onboarding wizard looking like nothing had ever
 * been done — asking the user to start over from CSR generation.
 *
 * Root cause: ZatcaController::issueProductionCsid() sets
 * zatca_onboarding_status to 'failed' when ZATCA rejects the exchange.
 * The dashboard computed which wizard step to show by array_search()-ing
 * that status string against a fixed list of the *successful* step
 * labels ('not_started', 'csr_generated', 'compliance_pending',
 * 'compliance_verified', 'onboarded') — 'failed' isn't one of them, so
 * array_search() returned false and the step index silently collapsed to
 * 0, hiding the already-issued CSR and compliance CSID behind the
 * "Generate CSR" form again. Regenerating a CSR at that point would
 * orphan the device ZATCA's portal already has registered under the old
 * one, forcing a genuinely fresh onboarding cycle for no reason.
 *
 * Fixed by deriving the step index from which credentials actually
 * exist rather than the raw status string, mirroring the pattern
 * Company::isZatcaOnboarded() already uses for the production-CSID step
 * itself.
 */
class ZatcaProductionCsidFailureRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function makeOnboardedButFailedOwner(): User
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(),
            'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true,
            'has_zatca_phase2' => true,
        ]);

        $company = Company::create([
            'name' => 'Failed Prod CSID Co.', 'slug' => 'failed-csid-'.uniqid(),
            'vat_number' => '399999999900003', 'cr_number' => 'CRN999999',
            'street_name' => 'Test Street', 'city' => 'Riyadh',
            'zatca_integration_mode' => Company::ZATCA_MODE_PHASE2,
            'zatca_environment' => 'production',
            'zatca_sync_b2b' => true,
            'zatca_sync_b2c' => true,
            'zatca_csr' => 'fake-csr-already-registered-with-zatca',
            'zatca_private_key' => 'fake-private-key',
            'zatca_onboarding_status' => 'failed',
            'zatca_compliance_request_id' => 'req-123',
            'zatca_compliance_csid' => 'fake-compliance-csid',
            'zatca_compliance_secret' => 'fake-compliance-secret',
            'zatca_production_csid' => null,
        ]);

        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth()]);

        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_a_rejected_production_csid_request_does_not_hide_the_already_issued_csr_and_compliance_csid(): void
    {
        $owner = $this->makeOnboardedButFailedOwner();

        $response = $this->actingAs($owner)->get(route('app.zatca.dashboard'));

        $response->assertOk();
        // Steps 1-3 must still show their already-issued values/"Done"
        // badges, not the CSR-generation form for a step that already
        // succeeded.
        $response->assertSee('fake-csr-already-registered-with-zatca');
        $response->assertDontSee(__('Save & Generate CSR'));
        $response->assertDontSeeText('name="otp"', false);
        $response->assertDontSee(route('app.zatca.compliance-check'), false);
        // Step 4 stays reachable so the user can just retry it.
        $response->assertSee(route('app.zatca.production-csid'), false);
        $response->assertSee(__('The last attempt was rejected by ZATCA. Your CSR and compliance CSID from steps 1–3 are still valid and were not lost — just retry this step below. If it keeps failing, check the error message from your last attempt above and the readiness checklist.'));
    }
}
