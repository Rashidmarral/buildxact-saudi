<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Registration was reworked into a 3-step wizard (account -> profile ->
 * business) with new fields (first/last name split into one User.name,
 * job_title, organization_size, industry) and the plan selector dropped
 * in favor of auto-assigning the platform's default/first active plan.
 * These tests exercise the real POST /register the wizard submits.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function makeActivePlan(): Plan
    {
        return Plan::create([
            'name' => 'Starter', 'slug' => 'starter-'.uniqid(),
            'price_monthly' => 99, 'price_yearly' => 990, 'is_active' => true, 'sort_order' => 1,
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Ahmed',
            'last_name' => 'Al-Saud',
            'email' => 'ahmed@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '0512345678',
            'job_title' => 'owner_ceo',
            'company_name' => 'Al-Nour Trading Est.',
            'organization_size' => '11-50',
            'industry' => 'retail_trading',
            'vat_number' => '300012345600003',
            'primary_customer_type' => 'b2b',
        ], $overrides);
    }

    public function test_registration_creates_company_and_owner_with_all_new_fields(): void
    {
        $this->makeActivePlan();

        $response = $this->post(route('register'), $this->validPayload());

        $response->assertRedirect(route('app.dashboard'));

        $user = User::where('email', 'ahmed@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('Ahmed Al-Saud', $user->name);
        $this->assertSame('owner_ceo', $user->job_title);
        $this->assertSame('owner', $user->role);
        $this->assertSame('0512345678', $user->phone);

        $company = $user->company;
        $this->assertSame('Al-Nour Trading Est.', $company->name);
        $this->assertSame('11-50', $company->organization_size);
        $this->assertSame('retail_trading', $company->industry);
        $this->assertSame('300012345600003', $company->vat_number);
        $this->assertSame('b2b', $company->primary_customer_type);

        $this->assertNotNull($company->activeSubscription());
        $this->assertSame('trialing', $company->activeSubscription()->status);
    }

    public function test_registration_requires_matching_business_classification_fields(): void
    {
        $this->makeActivePlan();

        $response = $this->post(route('register'), $this->validPayload(['organization_size' => 'not-a-real-size']));

        $response->assertSessionHasErrors('organization_size');
        $this->assertDatabaseMissing('companies', ['name' => 'Al-Nour Trading Est.']);
    }

    public function test_registration_allows_blank_vat_number_and_job_title(): void
    {
        $this->makeActivePlan();

        $response = $this->post(route('register'), $this->validPayload(['vat_number' => '', 'job_title' => '']));

        $response->assertRedirect(route('app.dashboard'));
        $company = Company::where('name', 'Al-Nour Trading Est.')->first();
        $this->assertNull($company->vat_number);
    }

    public function test_registration_falls_back_to_first_active_plan_when_no_default_configured(): void
    {
        $plan = $this->makeActivePlan();

        $this->post(route('register'), $this->validPayload());

        $company = Company::where('name', 'Al-Nour Trading Est.')->first();
        $this->assertSame($plan->id, $company->activeSubscription()->plan_id);
    }
}
