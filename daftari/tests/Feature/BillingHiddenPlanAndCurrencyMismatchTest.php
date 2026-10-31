<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding BILL-01: BillingController::upgrade() only ever checked
 * that 'plan_id' resolved to *some* row in `plans` — the pricing page
 * itself (index()) only ever lists plans that are both is_active and
 * is_public, but a direct POST with a retired or internal-only plan's id
 * succeeded anyway. Separately, a Plan's price is denominated in its own
 * `currency` column, but every Payment/Subscription row created afterward
 * labels the charged amount with the COMPANY's currency instead, with no
 * conversion — a plan priced in a different currency than the company
 * bills in would silently mislabel what was actually charged.
 */
class BillingHiddenPlanAndCurrencyMismatchTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(array $overrides = []): Company
    {
        return Company::create(array_merge([
            'name' => 'Acme Trading', 'slug' => 'acme-'.uniqid(), 'status' => 'active',
        ], $overrides));
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makePlan(array $overrides = []): Plan
    {
        return Plan::create(array_merge([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 0, 'price_yearly' => 0, 'is_active' => true,
        ], $overrides));
    }

    public function test_a_retired_inactive_plan_cannot_be_upgraded_to_directly(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $plan = $this->makePlan(['is_active' => false]);

        $response = $this->actingAs($owner)->post(route('app.billing.upgrade'), [
            'plan_id' => $plan->id, 'billing_cycle' => 'monthly',
        ]);

        $response->assertSessionHasErrors('plan_id');
        $this->assertSame(0, Subscription::where('company_id', $company->id)->count());
    }

    public function test_an_internal_only_non_public_plan_cannot_be_self_upgraded_to(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $plan = $this->makePlan(['is_public' => false]);

        $response = $this->actingAs($owner)->post(route('app.billing.upgrade'), [
            'plan_id' => $plan->id, 'billing_cycle' => 'monthly',
        ]);

        $response->assertSessionHasErrors('plan_id');
        $this->assertSame(0, Subscription::where('company_id', $company->id)->count());
    }

    public function test_a_plan_priced_in_a_different_currency_than_the_company_bills_in_is_rejected(): void
    {
        $company = $this->makeCompany(['currency' => 'SAR']);
        $owner = $this->makeOwner($company);
        $plan = $this->makePlan(['currency' => 'USD']);

        $response = $this->actingAs($owner)->post(route('app.billing.upgrade'), [
            'plan_id' => $plan->id, 'billing_cycle' => 'monthly',
        ]);

        $response->assertSessionHasErrors('plan_id');
        $this->assertSame(0, Subscription::where('company_id', $company->id)->count());
    }

    public function test_a_plan_with_no_currency_set_is_not_blocked_by_the_currency_check(): void
    {
        $company = $this->makeCompany(['currency' => 'SAR']);
        $owner = $this->makeOwner($company);
        $plan = $this->makePlan(); // no currency set — legacy/unscoped plan

        $response = $this->actingAs($owner)->post(route('app.billing.upgrade'), [
            'plan_id' => $plan->id, 'billing_cycle' => 'monthly',
        ]);

        $response->assertSessionDoesntHaveErrors('plan_id');
    }

    public function test_a_plan_whose_currency_matches_the_company_checks_out_normally(): void
    {
        $company = $this->makeCompany(['currency' => 'SAR']);
        $owner = $this->makeOwner($company);
        $plan = $this->makePlan(['currency' => 'SAR']);

        $response = $this->actingAs($owner)->post(route('app.billing.upgrade'), [
            'plan_id' => $plan->id, 'billing_cycle' => 'monthly',
        ]);

        $response->assertSessionDoesntHaveErrors('plan_id');
        $this->assertSame(1, Subscription::where('company_id', $company->id)->count());
    }
}
