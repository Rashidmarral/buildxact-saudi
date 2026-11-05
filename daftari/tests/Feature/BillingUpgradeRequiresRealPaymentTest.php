<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Coupon;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Security audit finding CRIT-02: BillingController::upgrade() validated
 * 'provider' as a loose nullable string with no check that it named an
 * actually-enabled PaymentGateway. Whenever it didn't resolve to one
 * (omitted, or any garbage string), the request fell through to a branch
 * that created an 'active' Subscription and a 'paid' Payment with no real
 * payment gateway involved — a free upgrade to any plan, repeatable every
 * renewal period.
 */
class BillingUpgradeRequiresRealPaymentTest extends TestCase
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

    public function test_omitting_the_provider_no_longer_grants_a_free_active_subscription(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $plan = $this->makePlan();

        $response = $this->actingAs($owner)->post(route('app.billing.upgrade'), [
            'plan_id' => $plan->id, 'billing_cycle' => 'monthly',
        ]);

        $response->assertSessionHasErrors('provider');
        $this->assertSame(0, Subscription::where('company_id', $company->id)->count());
    }

    public function test_a_made_up_provider_name_is_rejected_by_validation(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $plan = $this->makePlan();

        $response = $this->actingAs($owner)->post(route('app.billing.upgrade'), [
            'plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'provider' => 'totally-not-a-real-gateway',
        ]);

        $response->assertSessionHasErrors('provider');
        $this->assertSame(0, Subscription::where('company_id', $company->id)->count());
    }

    public function test_a_disabled_gateways_provider_name_is_rejected(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $plan = $this->makePlan();
        PaymentGateway::create(['company_id' => null, 'provider' => 'tap', 'mode' => 'test', 'is_enabled' => false]);

        $response = $this->actingAs($owner)->post(route('app.billing.upgrade'), [
            'plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'provider' => 'tap',
        ]);

        $response->assertSessionHasErrors('provider');
        $this->assertSame(0, Subscription::where('company_id', $company->id)->count());
    }

    public function test_an_enabled_bank_transfer_provider_still_works_as_a_legitimate_offline_checkout(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $plan = $this->makePlan();
        PaymentGateway::create(['company_id' => null, 'provider' => PaymentGateway::BANK_TRANSFER, 'mode' => 'test', 'is_enabled' => true]);

        $response = $this->actingAs($owner)->post(route('app.billing.upgrade'), [
            'plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'provider' => PaymentGateway::BANK_TRANSFER,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $subscription = Subscription::where('company_id', $company->id)->first();
        $this->assertNotNull($subscription);
        $this->assertSame('pending', $subscription->status, 'Bank transfer must stay pending until an admin confirms it, never auto-activate.');
    }

    public function test_a_coupon_that_discounts_a_paid_plan_to_zero_can_still_check_out_without_a_gateway(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $plan = $this->makePlan(['price_monthly' => 100]);
        $coupon = Coupon::create([
            'code' => 'FREE100', 'discount_type' => 'percentage', 'percentage' => 100,
            'is_active' => true, 'max_uses' => null, 'max_uses_per_company' => null,
        ]);

        $response = $this->actingAs($owner)->post(route('app.billing.upgrade'), [
            'plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'coupon_code' => 'FREE100',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $subscription = Subscription::where('company_id', $company->id)->first();
        $this->assertNotNull($subscription, 'A coupon that discounts the price to $0 is a legitimate free checkout and must still work.');
        $this->assertSame('active', $subscription->status);
    }

    public function test_a_genuinely_free_zero_price_plan_can_still_be_activated_without_a_gateway(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $plan = $this->makePlan(['price_monthly' => 0, 'price_yearly' => 0]);

        $response = $this->actingAs($owner)->post(route('app.billing.upgrade'), [
            'plan_id' => $plan->id, 'billing_cycle' => 'monthly',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $subscription = Subscription::where('company_id', $company->id)->first();
        $this->assertNotNull($subscription, 'A genuinely $0 plan must still be selectable without a payment gateway.');
        $this->assertSame('active', $subscription->status);
    }
}
