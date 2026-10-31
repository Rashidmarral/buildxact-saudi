<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Limits\UsageLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Security audit finding CRIT-03: Company::hasFeature()/hasReachedPlanLimit()
 * and UsageLimitService::limit() treated "no active (non-terminal)
 * subscription" as unlimited access — so a company whose subscription
 * lapsed to a terminal state (cancelled/expired) ended up with *more*
 * access than one still being actively chased for payment (past_due/
 * grace_period/suspended, which DOES have its plan's real limits applied).
 *
 * Fixed by distinguishing "never had a subscription at all" (a data/test
 * setup gap, left unblocked exactly as before — real signups always get a
 * subscription atomically, so this case doesn't occur in production) from
 * "had one and it lapsed to a terminal state" (now correctly blocked).
 */
class ExpiredSubscriptionLosesAccessTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(array $overrides = []): Company
    {
        return Company::create(array_merge([
            'name' => 'Acme Trading', 'slug' => 'acme-'.uniqid(), 'status' => 'active',
        ], $overrides));
    }

    private function makePlan(array $overrides = []): Plan
    {
        return Plan::create(array_merge([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000,
            'is_active' => true, 'max_customers' => 10, 'has_zatca_phase2' => true,
        ], $overrides));
    }

    private function attachSubscription(Company $company, Plan $plan, string $status): Subscription
    {
        return Subscription::create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => $status,
            'billing_cycle' => 'monthly',
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->subDay(),
        ]);
    }

    public function test_a_company_that_never_had_a_subscription_is_unaffected_unlimited_legacy_behavior(): void
    {
        $company = $this->makeCompany();

        $this->assertFalse($company->hasReachedPlanLimit('customers'));
        $this->assertTrue($company->hasFeature('zatca_phase2'));
        $this->assertNull(app(UsageLimitService::class)->limit($company, 'customers'));
    }

    public function test_a_company_with_an_active_subscription_is_governed_by_its_plans_real_limits(): void
    {
        $company = $this->makeCompany();
        $plan = $this->makePlan(['max_customers' => 2]);
        $this->attachSubscription($company, $plan, 'active');

        $this->assertSame(2, app(UsageLimitService::class)->limit($company, 'customers'));
        $this->assertTrue($company->hasFeature('zatca_phase2'));
    }

    public function test_a_cancelled_subscription_loses_every_feature_and_every_limited_action(): void
    {
        $company = $this->makeCompany();
        $plan = $this->makePlan();
        $this->attachSubscription($company, $plan, 'cancelled');

        $this->assertFalse($company->hasFeature('zatca_phase2'), 'A cancelled subscription must not keep every feature unlocked.');
        $this->assertTrue($company->hasReachedPlanLimit('customers'), 'A cancelled subscription must not grant unlimited record creation.');
        $this->assertSame(0, app(UsageLimitService::class)->limit($company, 'customers'));
        $this->assertSame(0, app(UsageLimitService::class)->limit($company, 'invoices'));
    }

    public function test_an_expired_subscription_loses_every_feature_and_every_limited_action(): void
    {
        $company = $this->makeCompany();
        $plan = $this->makePlan();
        $this->attachSubscription($company, $plan, 'expired');

        $this->assertFalse($company->hasFeature('zatca_phase2'));
        $this->assertTrue($company->hasReachedPlanLimit('customers'));
        $this->assertSame(0, app(UsageLimitService::class)->limit($company, 'customers'));
    }

    public function test_a_company_mid_dunning_past_due_still_uses_its_real_plan_limits_not_zero(): void
    {
        $company = $this->makeCompany();
        $plan = $this->makePlan(['max_customers' => 5]);
        $this->attachSubscription($company, $plan, 'past_due');

        // NON_TERMINAL_STATUSES already includes past_due/grace_period/
        // suspended — those were never affected by this bug, and must
        // keep seeing their plan's real limits, not the new "terminal ->
        // zero" behavior this fix adds.
        $this->assertSame(5, app(UsageLimitService::class)->limit($company, 'customers'));
        $this->assertTrue($company->hasFeature('zatca_phase2'));
    }
}
