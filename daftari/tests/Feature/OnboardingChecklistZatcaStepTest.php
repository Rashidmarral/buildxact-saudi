<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding LOW-32: the dashboard onboarding checklist (logo, VAT
 * number, first client/item/invoice/expense) never mentioned ZATCA
 * e-invoicing at all, even though it's central to this product — a
 * company could tick off the whole checklist and still be issuing
 * invoices with no CSID, no clearance, no compliance whatsoever. The
 * step is shown only when the company's plan actually includes ZATCA
 * Phase 2 (Company::hasFeature('zatca_phase2')) so it never sits
 * permanently unchecked for a plan that doesn't carry the feature.
 */
class OnboardingChecklistZatcaStepTest extends TestCase
{
    use RefreshDatabase;

    private function makePlan(bool $hasZatca): Plan
    {
        return Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(),
            'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true,
            'has_zatca_phase2' => $hasZatca,
        ]);
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_the_zatca_step_appears_when_the_plan_includes_zatca_phase2(): void
    {
        $company = Company::create(['name' => 'Compliant Co.', 'slug' => 'compliant-'.uniqid()]);
        $plan = $this->makePlan(true);
        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth()]);
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->get(route('app.dashboard'));

        $response->assertOk();
        $response->assertSee(__('Complete ZATCA e-invoicing setup'));
    }

    public function test_the_zatca_step_is_hidden_when_the_plan_does_not_include_zatca_phase2(): void
    {
        $company = Company::create(['name' => 'Basic Co.', 'slug' => 'basic-'.uniqid()]);
        $plan = $this->makePlan(false);
        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth()]);
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->get(route('app.dashboard'));

        $response->assertOk();
        $response->assertDontSee(__('Complete ZATCA e-invoicing setup'));
    }

    public function test_the_zatca_step_shows_as_incomplete_until_the_company_is_actually_onboarded(): void
    {
        $company = Company::create(['name' => 'Compliant Co.', 'slug' => 'compliant-'.uniqid(), 'zatca_onboarding_status' => 'csr_generated']);
        $plan = $this->makePlan(true);
        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth()]);
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->get(route('app.dashboard'));

        $response->assertOk();
        $this->assertFalse($company->fresh()->isZatcaOnboarded());
        $response->assertSee(route('app.zatca.dashboard'), false);
    }
}
