<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Coupons\CouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module 06 (Coupons & Promotions): CouponService's validation rules and
 * discount computation, the checkout integration (minimal, additive —
 * absent coupon_code, billing behaves exactly as before this module), and
 * the admin CRUD + stats page.
 */
class CouponManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'company_id' => null]);
    }

    private function makePlan(array $overrides = []): Plan
    {
        return Plan::create(array_merge([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true, 'is_public' => true,
        ], $overrides));
    }

    private function makeCompany(array $overrides = []): Company
    {
        return Company::create(array_merge([
            'name' => 'Acme Trading', 'slug' => 'acme-'.uniqid(), 'status' => 'active', 'currency' => 'SAR',
        ], $overrides));
    }

    private function makeCoupon(array $overrides = []): Coupon
    {
        return Coupon::create(array_merge([
            'code' => 'SAVE20', 'discount_type' => 'percentage', 'percentage' => 20,
            'currency' => 'SAR', 'is_active' => true,
        ], $overrides));
    }

    private function withConfirmedPassword($user)
    {
        return $this->actingAs($user)->withSession(['auth.password_confirmed_at' => now()->timestamp]);
    }

    // ---------------------------------------------------------------
    // CouponService::validate()
    // ---------------------------------------------------------------

    public function test_validate_rejects_an_expired_coupon(): void
    {
        $coupon = $this->makeCoupon(['expires_at' => now()->subDay()]);
        $company = $this->makeCompany();
        $plan = $this->makePlan();

        $result = app(CouponService::class)->validate($coupon, $company, $plan, 100);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('expired', $result['errors'][0]);
    }

    public function test_validate_rejects_an_inactive_coupon(): void
    {
        $coupon = $this->makeCoupon(['is_active' => false]);
        $company = $this->makeCompany();
        $plan = $this->makePlan();

        $result = app(CouponService::class)->validate($coupon, $company, $plan, 100);

        $this->assertFalse($result['valid']);
    }

    public function test_validate_rejects_a_coupon_that_has_not_started_yet(): void
    {
        $coupon = $this->makeCoupon(['starts_at' => now()->addDay()]);
        $company = $this->makeCompany();
        $plan = $this->makePlan();

        $result = app(CouponService::class)->validate($coupon, $company, $plan, 100);

        $this->assertFalse($result['valid']);
    }

    public function test_validate_rejects_once_maximum_usage_is_reached(): void
    {
        $coupon = $this->makeCoupon(['max_uses' => 1]);
        $company = $this->makeCompany();
        $plan = $this->makePlan();
        CouponRedemption::create(['coupon_id' => $coupon->id, 'company_id' => $company->id, 'original_amount' => 100, 'discount_amount' => 20, 'final_amount' => 80, 'redeemed_at' => now()]);

        $result = app(CouponService::class)->validate($coupon, $company, $plan, 100);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('maximum number of uses', $result['errors'][0]);
    }

    public function test_validate_rejects_a_plan_not_in_the_coupons_applicable_plans(): void
    {
        $planA = $this->makePlan(['name' => 'Plan A']);
        $planB = $this->makePlan(['name' => 'Plan B']);
        $coupon = $this->makeCoupon();
        $coupon->plans()->sync([$planA->id]);
        $company = $this->makeCompany();

        $result = app(CouponService::class)->validate($coupon, $company, $planB, 100);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('does not apply to the selected plan', $result['errors'][0]);
    }

    public function test_validate_accepts_a_plan_that_is_in_the_coupons_applicable_plans(): void
    {
        $planA = $this->makePlan(['name' => 'Plan A']);
        $coupon = $this->makeCoupon();
        $coupon->plans()->sync([$planA->id]);
        $company = $this->makeCompany();

        $result = app(CouponService::class)->validate($coupon, $company, $planA, 100);

        $this->assertTrue($result['valid']);
    }

    public function test_validate_rejects_an_amount_below_the_minimum_subscription_amount(): void
    {
        $coupon = $this->makeCoupon(['min_subscription_amount' => 200]);
        $company = $this->makeCompany();
        $plan = $this->makePlan();

        $result = app(CouponService::class)->validate($coupon, $company, $plan, 100);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('minimum subscription amount', $result['errors'][0]);
    }

    public function test_validate_rejects_a_returning_customer_on_a_new_customers_only_coupon(): void
    {
        $coupon = $this->makeCoupon(['new_customers_only' => true]);
        $company = $this->makeCompany();
        $plan = $this->makePlan();
        Payment::withoutGlobalScopes()->create(['company_id' => $company->id, 'amount' => 50, 'currency' => 'SAR', 'status' => 'paid', 'method' => 'manual', 'paid_at' => now()]);

        $result = app(CouponService::class)->validate($coupon, $company, $plan, 100);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('new customers', $result['errors'][0]);
    }

    public function test_validate_rejects_first_payment_coupon_for_a_company_that_already_paid(): void
    {
        $coupon = $this->makeCoupon(['discount_type' => 'first_payment', 'percentage' => 50]);
        $company = $this->makeCompany();
        $plan = $this->makePlan();
        Payment::withoutGlobalScopes()->create(['company_id' => $company->id, 'amount' => 50, 'currency' => 'SAR', 'status' => 'paid', 'method' => 'manual', 'paid_at' => now()]);

        $result = app(CouponService::class)->validate($coupon, $company, $plan, 100);

        $this->assertFalse($result['valid']);
    }

    public function test_validate_rejects_limited_duration_coupon_once_its_cycle_count_is_exhausted(): void
    {
        $coupon = $this->makeCoupon(['discount_type' => 'limited_duration', 'percentage' => 10, 'duration_in_cycles' => 2]);
        $company = $this->makeCompany();
        $plan = $this->makePlan();
        CouponRedemption::create(['coupon_id' => $coupon->id, 'company_id' => $company->id, 'original_amount' => 100, 'discount_amount' => 10, 'final_amount' => 90, 'redeemed_at' => now()]);
        CouponRedemption::create(['coupon_id' => $coupon->id, 'company_id' => $company->id, 'original_amount' => 100, 'discount_amount' => 10, 'final_amount' => 90, 'redeemed_at' => now()]);

        $result = app(CouponService::class)->validate($coupon, $company, $plan, 100);

        $this->assertFalse($result['valid']);
    }

    public function test_validate_rejects_once_per_company_max_uses_is_reached(): void
    {
        $coupon = $this->makeCoupon(['max_uses_per_company' => 1]);
        $company = $this->makeCompany();
        $otherCompany = $this->makeCompany();
        $plan = $this->makePlan();
        CouponRedemption::create(['coupon_id' => $coupon->id, 'company_id' => $company->id, 'original_amount' => 100, 'discount_amount' => 20, 'final_amount' => 80, 'redeemed_at' => now()]);

        $this->assertFalse(app(CouponService::class)->validate($coupon, $company, $plan, 100)['valid']);
        // A different company hasn't used it yet — still valid for them.
        $this->assertTrue(app(CouponService::class)->validate($coupon, $otherCompany, $plan, 100)['valid']);
    }

    // ---------------------------------------------------------------
    // CouponService::calculateDiscount() — never a negative total
    // ---------------------------------------------------------------

    public function test_calculate_discount_percentage(): void
    {
        $coupon = $this->makeCoupon(['discount_type' => 'percentage', 'percentage' => 25]);

        $this->assertSame(25.0, app(CouponService::class)->calculateDiscount($coupon, 100));
    }

    public function test_calculate_discount_fixed_amount(): void
    {
        $coupon = $this->makeCoupon(['discount_type' => 'fixed', 'percentage' => null, 'fixed_amount' => 30]);

        $this->assertSame(30.0, app(CouponService::class)->calculateDiscount($coupon, 100));
    }

    public function test_calculate_discount_never_produces_a_negative_subscription_total(): void
    {
        $coupon = $this->makeCoupon(['discount_type' => 'fixed', 'percentage' => null, 'fixed_amount' => 500]);

        $discount = app(CouponService::class)->calculateDiscount($coupon, 100);

        $this->assertSame(100.0, $discount);
        [$final] = app(CouponService::class)->priceWithCoupon($coupon, $this->makeCompany(), $this->makePlan(), 100);
        $this->assertSame(0.0, $final);
    }

    // ---------------------------------------------------------------
    // Checkout integration (minimal — no coupon_code changes nothing)
    // ---------------------------------------------------------------

    public function test_checkout_without_a_coupon_code_behaves_exactly_as_before(): void
    {
        $plan = $this->makePlan(['price_monthly' => 100]);
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id, 'role' => 'owner']);

        $this->actingAs($owner)->post(route('app.billing.upgrade'), ['plan_id' => $plan->id, 'billing_cycle' => 'monthly'])
            ->assertRedirect(route('app.billing.index'));

        $this->assertDatabaseHas('payments', ['company_id' => $company->id, 'amount' => 100, 'status' => 'paid']);
        $this->assertSame(0, CouponRedemption::count());
    }

    public function test_checkout_with_a_valid_coupon_applies_the_discount_and_records_a_redemption(): void
    {
        $plan = $this->makePlan(['price_monthly' => 100]);
        $coupon = $this->makeCoupon(['discount_type' => 'percentage', 'percentage' => 20]);
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id, 'role' => 'owner']);

        $this->actingAs($owner)->post(route('app.billing.upgrade'), ['plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'coupon_code' => 'save20'])
            ->assertRedirect(route('app.billing.index'));

        $this->assertDatabaseHas('payments', ['company_id' => $company->id, 'amount' => 80, 'status' => 'paid']);
        $redemption = CouponRedemption::first();
        $this->assertNotNull($redemption);
        $this->assertEquals(100, $redemption->original_amount);
        $this->assertEquals(20, $redemption->discount_amount);
        $this->assertEquals(80, $redemption->final_amount);

        $log = AuditLog::where('action', 'coupon.redeem')->first();
        $this->assertNotNull($log);
        $this->assertSame($company->id, $log->company_id);
    }

    public function test_checkout_rejects_an_invalid_coupon_code_before_creating_any_records(): void
    {
        $plan = $this->makePlan(['price_monthly' => 100]);
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id, 'role' => 'owner']);

        $response = $this->actingAs($owner)->post(route('app.billing.upgrade'), ['plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'coupon_code' => 'DOESNOTEXIST']);

        $response->assertSessionHasErrors('coupon_code');
        $this->assertDatabaseMissing('payments', ['company_id' => $company->id]);
        $this->assertNull(Subscription::where('company_id', $company->id)->first());
    }

    public function test_checkout_rejects_an_expired_coupon(): void
    {
        $plan = $this->makePlan(['price_monthly' => 100]);
        $this->makeCoupon(['code' => 'OLD10', 'expires_at' => now()->subDay()]);
        $company = $this->makeCompany();
        $owner = User::factory()->create(['company_id' => $company->id, 'role' => 'owner']);

        $response = $this->actingAs($owner)->post(route('app.billing.upgrade'), ['plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'coupon_code' => 'OLD10']);

        $response->assertSessionHasErrors('coupon_code');
    }

    public function test_checkout_enforces_max_uses_across_repeated_redemptions(): void
    {
        $plan = $this->makePlan(['price_monthly' => 100]);
        $this->makeCoupon(['code' => 'ONECODE', 'max_uses' => 1]);

        $companyA = $this->makeCompany();
        $ownerA = User::factory()->create(['company_id' => $companyA->id, 'role' => 'owner']);
        $this->actingAs($ownerA)->post(route('app.billing.upgrade'), ['plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'coupon_code' => 'ONECODE'])
            ->assertRedirect();

        $companyB = $this->makeCompany();
        $ownerB = User::factory()->create(['company_id' => $companyB->id, 'role' => 'owner']);
        $response = $this->actingAs($ownerB)->post(route('app.billing.upgrade'), ['plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'coupon_code' => 'ONECODE']);

        $response->assertSessionHasErrors('coupon_code');
        $this->assertSame(1, CouponRedemption::count());
    }

    // ---------------------------------------------------------------
    // Admin CRUD
    // ---------------------------------------------------------------

    public function test_admin_can_create_a_coupon_with_applicable_plans(): void
    {
        $plan = $this->makePlan();

        $response = $this->withConfirmedPassword($this->makeAdmin())->post(route('admin.coupons.store'), [
            'code' => 'newcode', 'discount_type' => 'percentage', 'percentage' => 15, 'currency' => 'SAR',
            'plan_ids' => [$plan->id],
        ]);

        $response->assertRedirect(route('admin.coupons.index'));
        $coupon = Coupon::where('code', 'NEWCODE')->first();
        $this->assertNotNull($coupon);
        $this->assertTrue($coupon->plans->contains($plan->id));

        $log = AuditLog::where('action', 'coupon.create')->first();
        $this->assertNotNull($log);
    }

    public function test_admin_cannot_create_a_coupon_with_both_percentage_and_fixed_amount(): void
    {
        $response = $this->withConfirmedPassword($this->makeAdmin())->post(route('admin.coupons.store'), [
            'code' => 'BADCODE', 'discount_type' => 'percentage', 'percentage' => 15, 'fixed_amount' => 10, 'currency' => 'SAR',
        ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseMissing('coupons', ['code' => 'BADCODE']);
    }

    public function test_admin_cannot_delete_a_coupon_that_has_already_been_redeemed(): void
    {
        $coupon = $this->makeCoupon();
        $company = $this->makeCompany();
        CouponRedemption::create(['coupon_id' => $coupon->id, 'company_id' => $company->id, 'original_amount' => 100, 'discount_amount' => 20, 'final_amount' => 80, 'redeemed_at' => now()]);

        $response = $this->withConfirmedPassword($this->makeAdmin())->delete(route('admin.coupons.destroy', $coupon));

        $response->assertRedirect();
        $this->assertDatabaseHas('coupons', ['id' => $coupon->id]);
    }

    public function test_admin_show_page_reports_usage_stats(): void
    {
        $coupon = $this->makeCoupon(['max_uses' => 5]);
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        CouponRedemption::create(['coupon_id' => $coupon->id, 'company_id' => $companyA->id, 'original_amount' => 100, 'discount_amount' => 20, 'final_amount' => 80, 'redeemed_at' => now()]);
        CouponRedemption::create(['coupon_id' => $coupon->id, 'company_id' => $companyB->id, 'original_amount' => 200, 'discount_amount' => 40, 'final_amount' => 160, 'redeemed_at' => now()]);

        $response = $this->actingAs($this->makeAdmin())->get(route('admin.coupons.show', $coupon));

        $response->assertOk()
            ->assertSee(__('Total uses'))
            ->assertSee(__('Remaining uses'))
            ->assertSee(__('Revenue discounted'))
            ->assertSee(__('Companies using coupon'));

        $response->assertViewHas('stats', function ($stats) {
            return $stats['total_uses'] === 2
                && $stats['remaining_uses'] === 3
                && (float) $stats['revenue_discounted'] === 60.0
                && $stats['companies_using'] === 2;
        });
    }
}
