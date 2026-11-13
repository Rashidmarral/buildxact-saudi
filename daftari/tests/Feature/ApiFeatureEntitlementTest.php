<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyOverride;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Audit finding API-01: ApiTokenController::store() only ever checked the
 * company's 'api' plan entitlement once, when a token was first issued —
 * nothing re-checked it on each actual API request, so an already-issued
 * token kept working against every v1 endpoint after the company
 * downgraded to a plan without API access, or had an admin override
 * revoked.
 */
class ApiFeatureEntitlementTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        return Company::create(['name' => 'Acme Trading', 'slug' => 'acme-'.uniqid(), 'status' => 'active']);
    }

    private function subscribe(Company $company, array $planOverrides = []): void
    {
        $plan = Plan::create(array_merge([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true,
        ], $planOverrides));

        Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);
    }

    public function test_an_api_token_is_rejected_once_the_companys_plan_no_longer_includes_api_access(): void
    {
        $company = $this->makeCompany();
        $this->subscribe($company, ['has_api' => false]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        Sanctum::actingAs($owner, ['read']);

        $response = $this->getJson(route('api.v1.me'));

        $response->assertStatus(403);
    }

    public function test_an_api_token_still_works_while_the_plan_includes_api_access(): void
    {
        $company = $this->makeCompany();
        $this->subscribe($company, ['has_api' => true]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        Sanctum::actingAs($owner, ['read']);

        $response = $this->getJson(route('api.v1.me'));

        $response->assertOk();
    }

    public function test_a_company_with_no_active_subscription_is_unrestricted_matching_the_existing_feature_access_default(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        Sanctum::actingAs($owner, ['read']);

        $response = $this->getJson(route('api.v1.me'));

        $response->assertOk();
    }

    public function test_an_admin_override_re_enabling_api_access_overrides_a_plan_that_lacks_it(): void
    {
        $company = $this->makeCompany();
        $this->subscribe($company, ['has_api' => false]);
        CompanyOverride::create(['company_id' => $company->id, 'type' => 'feature', 'key' => 'api', 'value' => '1']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        Sanctum::actingAs($owner, ['read']);

        $response = $this->getJson(route('api.v1.me'));

        $response->assertOk();
    }

    public function test_an_admin_override_revoking_api_access_overrides_a_plan_that_includes_it(): void
    {
        $company = $this->makeCompany();
        $this->subscribe($company, ['has_api' => true]);
        CompanyOverride::create(['company_id' => $company->id, 'type' => 'feature', 'key' => 'api', 'value' => '0']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        Sanctum::actingAs($owner, ['read']);

        $response = $this->getJson(route('api.v1.me'));

        $response->assertStatus(403);
    }
}
