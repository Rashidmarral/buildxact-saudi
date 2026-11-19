<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding MEDIUM-19: hitting a plan limit (hasReachedPlanLimit()
 * across the various user controllers) or a plan feature gate
 * (EnsurePlanFeature) flashed a 'plan_limit'/'feature' error, but the
 * layout rendered it as plain text inside the generic error list — no
 * actual link to upgrade. layouts/app.blade.php now gives those two error
 * keys their own banner with a direct "Upgrade plan" link into the
 * Billing page's plans tab.
 */
class PlanLimitUpgradeCtaTest extends TestCase
{
    use RefreshDatabase;

    private function makePlan(array $overrides = []): Plan
    {
        return Plan::create(array_merge([
            'name' => 'Starter', 'slug' => 'starter-'.uniqid(), 'price_monthly' => 50, 'price_yearly' => 500, 'is_active' => true,
        ], $overrides));
    }

    private function makeCompanyOnPlan(array $planOverrides = []): Company
    {
        $company = Company::create(['name' => 'Limited Co.', 'slug' => 'limited-'.uniqid(), 'status' => 'active']);
        $plan = $this->makePlan($planOverrides);

        Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);

        return $company;
    }

    public function test_hitting_a_plan_limit_shows_an_upgrade_cta_linking_to_the_plans_tab(): void
    {
        $company = $this->makeCompanyOnPlan(['max_customers' => 0]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $response = $this->actingAs($owner)->from(route('app.clients.create'))->post(route('app.clients.store'), [
            'name' => 'A New Client',
        ]);

        $response->assertRedirect(route('app.clients.create'));

        $follow = $this->get(route('app.clients.create'));
        $follow->assertSee(__('Upgrade plan'));
        $follow->assertSee(route('app.billing.index', ['tab' => 'plans']), false);
    }

    public function test_a_disabled_plan_feature_shows_the_same_upgrade_cta(): void
    {
        $company = $this->makeCompanyOnPlan(['has_purchase_orders' => false]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $response = $this->actingAs($owner)->get(route('app.purchase-orders.index'));

        $response->assertRedirect(route('app.dashboard'));

        $follow = $this->get(route('app.dashboard'));
        $follow->assertSee(__('Upgrade plan'));
        $follow->assertSee(route('app.billing.index', ['tab' => 'plans']), false);
    }

    public function test_an_ordinary_validation_error_does_not_trigger_the_upgrade_banner(): void
    {
        $company = $this->makeCompanyOnPlan(['max_customers' => null]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $response = $this->actingAs($owner)->from(route('app.clients.create'))->post(route('app.clients.store'), []);

        $response->assertRedirect(route('app.clients.create'));

        $follow = $this->get(route('app.clients.create'));
        $follow->assertDontSee(__('Upgrade plan'));
    }
}
