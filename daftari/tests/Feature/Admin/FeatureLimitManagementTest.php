<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\CompanyOverride;
use App\Models\Item;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Features\FeatureAccessService;
use App\Services\Limits\UsageLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module 07 (Feature Flags & Plan Limits): FeatureAccessService and
 * UsageLimitService's precedence rules (override beats plan), the new
 * Products limit gate wired into ItemController, the EnsureWithinApiLimit
 * middleware, the admin override set/clear endpoints + audit logging, and
 * that Company::hasReachedPlanLimit() still behaves identically for its
 * pre-existing callers now that six of its keys delegate internally.
 */
class FeatureLimitManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'company_id' => null]);
    }

    private function withConfirmedPassword(User $user)
    {
        return $this->actingAs($user)->withSession(['auth.password_confirmed_at' => now()->timestamp]);
    }

    private function makePlan(array $overrides = []): Plan
    {
        return Plan::create(array_merge([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true,
        ], $overrides));
    }

    private function makeCompany(array $overrides = []): Company
    {
        return Company::create(array_merge([
            'name' => 'Acme Trading', 'slug' => 'acme-'.uniqid(), 'status' => 'active',
        ], $overrides));
    }

    private function subscribe(Company $company, Plan $plan): Subscription
    {
        return Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);
    }

    // ---------------------------------------------------------------
    // FeatureAccessService
    // ---------------------------------------------------------------

    public function test_core_features_are_always_enabled(): void
    {
        $company = $this->makeCompany();
        $this->subscribe($company, $this->makePlan(['has_api' => false]));

        $this->assertTrue(app(FeatureAccessService::class)->enabled($company, 'accounting'));
        $this->assertTrue(app(FeatureAccessService::class)->enabled($company, 'sales'));
    }

    public function test_planned_features_are_always_disabled(): void
    {
        $company = $this->makeCompany();
        $this->subscribe($company, $this->makePlan());

        $this->assertFalse(app(FeatureAccessService::class)->enabled($company, 'payroll'));
        $this->assertFalse(app(FeatureAccessService::class)->enabled($company, 'pos'));
    }

    public function test_gated_feature_reads_plan_column(): void
    {
        $company = $this->makeCompany();
        $this->subscribe($company, $this->makePlan(['has_api' => false, 'has_whatsapp' => true]));

        $access = app(FeatureAccessService::class);
        $this->assertFalse($access->enabled($company, 'api'));
        $this->assertTrue($access->enabled($company, 'whatsapp'));
    }

    public function test_multi_branch_feature_is_derived_from_branch_limit(): void
    {
        $company = $this->makeCompany();
        $this->subscribe($company, $this->makePlan(['max_branches' => 1]));
        $this->assertFalse(app(FeatureAccessService::class)->enabled($company, 'multi_branch'));

        $companyB = $this->makeCompany();
        $this->subscribe($companyB, $this->makePlan(['max_branches' => 5]));
        $this->assertTrue(app(FeatureAccessService::class)->enabled($companyB, 'multi_branch'));
    }

    public function test_feature_override_takes_precedence_over_plan(): void
    {
        $company = $this->makeCompany();
        $this->subscribe($company, $this->makePlan(['has_api' => false]));

        CompanyOverride::create(['company_id' => $company->id, 'type' => 'feature', 'key' => 'api', 'value' => '1', 'is_unlimited' => false]);

        $this->assertTrue(app(FeatureAccessService::class)->enabled($company, 'api'));
    }

    // ---------------------------------------------------------------
    // UsageLimitService
    // ---------------------------------------------------------------

    public function test_limit_is_null_when_plan_column_is_null(): void
    {
        $company = $this->makeCompany();
        $this->subscribe($company, $this->makePlan(['max_users' => null]));

        $service = app(UsageLimitService::class);
        $this->assertNull($service->limit($company, 'users'));
        $this->assertFalse($service->reached($company, 'users'));
    }

    public function test_reached_is_true_once_usage_meets_the_limit(): void
    {
        $company = $this->makeCompany();
        $this->subscribe($company, $this->makePlan(['max_users' => 1]));

        // The owner created below counts toward "users" usage.
        User::factory()->create(['company_id' => $company->id, 'role' => 'owner']);

        $this->assertTrue(app(UsageLimitService::class)->reached($company, 'users'));
    }

    public function test_limit_override_replaces_plan_value(): void
    {
        $company = $this->makeCompany();
        $this->subscribe($company, $this->makePlan(['max_users' => 1]));
        User::factory()->create(['company_id' => $company->id, 'role' => 'owner']);

        CompanyOverride::create(['company_id' => $company->id, 'type' => 'limit', 'key' => 'users', 'value' => '10', 'is_unlimited' => false]);

        $service = app(UsageLimitService::class);
        $this->assertSame(10, $service->limit($company, 'users'));
        $this->assertFalse($service->reached($company, 'users'));
    }

    public function test_unlimited_override_removes_the_cap(): void
    {
        $company = $this->makeCompany();
        $this->subscribe($company, $this->makePlan(['max_users' => 1]));
        User::factory()->create(['company_id' => $company->id, 'role' => 'owner']);

        CompanyOverride::create(['company_id' => $company->id, 'type' => 'limit', 'key' => 'users', 'value' => null, 'is_unlimited' => true]);

        $service = app(UsageLimitService::class);
        $this->assertNull($service->limit($company, 'users'));
        $this->assertFalse($service->reached($company, 'users'));
    }

    // ---------------------------------------------------------------
    // Products limit wired into ItemController
    // ---------------------------------------------------------------

    public function test_item_creation_is_blocked_once_the_products_limit_is_reached(): void
    {
        $company = $this->makeCompany();
        $this->subscribe($company, $this->makePlan(['max_items' => 1]));
        $owner = User::factory()->create(['company_id' => $company->id, 'role' => 'owner']);

        Item::create(['company_id' => $company->id, 'name' => 'Existing item', 'item_type' => 'physical', 'unit_price' => 10, 'vat_rate' => 15]);

        $response = $this->actingAs($owner)->post(route('app.items.store'), [
            'name' => 'Second item', 'item_type' => 'physical', 'unit_price' => 20, 'vat_rate' => 15,
        ]);

        $response->assertSessionHasErrors('plan_limit');
        $this->assertSame(1, Item::where('company_id', $company->id)->count());
    }

    public function test_item_creation_succeeds_under_an_overridden_limit(): void
    {
        $company = $this->makeCompany();
        $this->subscribe($company, $this->makePlan(['max_items' => 1]));
        $owner = User::factory()->create(['company_id' => $company->id, 'role' => 'owner']);

        Item::create(['company_id' => $company->id, 'name' => 'Existing item', 'item_type' => 'physical', 'unit_price' => 10, 'vat_rate' => 15]);
        CompanyOverride::create(['company_id' => $company->id, 'type' => 'limit', 'key' => 'products', 'is_unlimited' => true]);

        $response = $this->actingAs($owner)->post(route('app.items.store'), [
            'name' => 'Second item', 'item_type' => 'physical', 'unit_price' => 20, 'vat_rate' => 15,
        ]);

        $response->assertSessionDoesntHaveErrors('plan_limit');
        $this->assertSame(2, Item::where('company_id', $company->id)->count());
    }

    // ---------------------------------------------------------------
    // EnsureWithinApiLimit middleware
    // ---------------------------------------------------------------

    public function test_api_request_under_the_limit_is_allowed_and_counted(): void
    {
        $company = $this->makeCompany();
        $this->subscribe($company, $this->makePlan(['max_api_calls_per_month' => 5]));
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'owner']);
        $token = $user->createToken('test', ['read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/me');

        $response->assertOk();
        $this->assertSame(1, \App\Models\ApiUsageCounter::currentPeriodCount($company->id));
    }

    public function test_api_request_over_the_limit_is_blocked_with_429(): void
    {
        $company = $this->makeCompany();
        $this->subscribe($company, $this->makePlan(['max_api_calls_per_month' => 1]));
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'owner']);
        $token = $user->createToken('test', ['read'])->plainTextToken;

        \App\Models\ApiUsageCounter::recordCall($company->id);

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/me');

        $response->assertStatus(429);
    }

    // ---------------------------------------------------------------
    // Admin override set/clear + audit logging
    // ---------------------------------------------------------------

    public function test_admin_can_set_and_clear_a_limit_override(): void
    {
        $admin = $this->makeAdmin();
        $company = $this->makeCompany();
        $this->subscribe($company, $this->makePlan(['max_users' => 3]));

        $this->withConfirmedPassword($admin)->post(route('admin.companies.overrides.set', $company), [
            'type' => 'limit', 'key' => 'users', 'value' => '25', 'reason' => 'Support exception',
        ])->assertRedirect();

        $override = CompanyOverride::where('company_id', $company->id)->where('type', 'limit')->where('key', 'users')->firstOrFail();
        $this->assertSame('25', $override->value);
        $this->assertDatabaseHas('audit_logs', ['action' => 'company.override.set', 'company_id' => $company->id]);

        $this->withConfirmedPassword($admin)->delete(route('admin.companies.overrides.clear', [$company, $override]))
            ->assertRedirect();

        $this->assertDatabaseMissing('company_overrides', ['id' => $override->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'company.override.clear', 'company_id' => $company->id]);
    }

    public function test_admin_override_requires_a_registered_key(): void
    {
        $admin = $this->makeAdmin();
        $company = $this->makeCompany();

        $this->withConfirmedPassword($admin)->post(route('admin.companies.overrides.set', $company), [
            'type' => 'limit', 'key' => 'not_a_real_limit', 'value' => '5',
        ])->assertStatus(422);
    }

    // ---------------------------------------------------------------
    // Regression: Company::hasReachedPlanLimit() unchanged for existing callers
    // ---------------------------------------------------------------

    public function test_has_reached_plan_limit_still_works_for_delegated_and_inline_keys(): void
    {
        $company = $this->makeCompany();
        $this->subscribe($company, $this->makePlan(['max_branches' => 0, 'max_bank_accounts' => 0]));

        // 'branches' is one of the six keys now delegated to UsageLimitService.
        $this->assertTrue($company->hasReachedPlanLimit('branches'));
        // 'bank_accounts' keeps its original inline logic untouched.
        $this->assertTrue($company->hasReachedPlanLimit('bank_accounts'));
    }

    public function test_has_reached_plan_limit_is_never_blocked_without_a_subscription(): void
    {
        $company = $this->makeCompany();

        $this->assertFalse($company->hasReachedPlanLimit('users'));
        $this->assertFalse($company->hasReachedPlanLimit('bank_accounts'));
    }
}
