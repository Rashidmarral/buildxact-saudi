<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyOverride;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Features\FeatureAccessService;
use App\Support\PlatformFeatureToggle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Requested go-live feature: a Super Admin needs a platform-wide master
 * switch on top of the existing per-plan/per-company gating (Module 07)
 * — e.g. to soft-launch Payroll/POS to nobody yet while finishing QA, or
 * to roll a module back everywhere during an incident, regardless of
 * which plan any given company is on.
 */
class PlatformFeatureToggleTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'company_id' => null]);
    }

    private function makeCompanyOnPlan(array $planOverrides = []): Company
    {
        $plan = Plan::create(array_merge([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true,
        ], $planOverrides));

        $company = Company::create(['name' => 'Acme', 'slug' => 'acme-'.uniqid(), 'status' => 'active']);

        Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);

        return $company;
    }

    public function test_a_gated_feature_defaults_to_platform_enabled_when_no_toggle_has_been_set(): void
    {
        $company = $this->makeCompanyOnPlan(['has_payroll' => true]);

        $this->assertTrue(app(FeatureAccessService::class)->enabled($company, 'payroll'));
    }

    public function test_switching_a_feature_off_platform_wide_blocks_every_company_even_with_the_plan_enabled(): void
    {
        $company = $this->makeCompanyOnPlan(['has_payroll' => true]);
        PlatformFeatureToggle::setEnabled('payroll', false);

        $this->assertFalse(app(FeatureAccessService::class)->enabled($company, 'payroll'));
    }

    public function test_the_platform_wide_switch_vetoes_even_an_explicit_company_override(): void
    {
        $company = $this->makeCompanyOnPlan(['has_payroll' => false]);
        CompanyOverride::create(['company_id' => $company->id, 'type' => 'feature', 'key' => 'payroll', 'value' => '1']);
        PlatformFeatureToggle::setEnabled('payroll', false);

        $this->assertFalse(app(FeatureAccessService::class)->enabled($company, 'payroll'));
    }

    public function test_turning_the_platform_switch_back_on_restores_the_plans_own_answer(): void
    {
        $company = $this->makeCompanyOnPlan(['has_payroll' => false]);
        PlatformFeatureToggle::setEnabled('payroll', false);
        PlatformFeatureToggle::setEnabled('payroll', true);

        $this->assertFalse(app(FeatureAccessService::class)->enabled($company, 'payroll'));
    }

    public function test_a_core_feature_is_unaffected_by_the_platform_toggle_mechanism(): void
    {
        $company = $this->makeCompanyOnPlan();

        $this->assertTrue(app(FeatureAccessService::class)->enabled($company, 'accounting'));
    }

    public function test_a_super_admin_can_save_platform_feature_toggles(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post(route('admin.settings.features.update'), [
            'features' => ['payroll'],
        ]);

        $response->assertRedirect();
        $this->assertTrue(PlatformFeatureToggle::isEnabled('payroll'));
        $this->assertFalse(PlatformFeatureToggle::isEnabled('pos'));
        $this->assertFalse(PlatformFeatureToggle::isEnabled('whatsapp'));
    }

    public function test_submitting_no_checked_features_disables_every_gated_feature(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('admin.settings.features.update'), []);

        $this->assertFalse(PlatformFeatureToggle::isEnabled('payroll'));
        $this->assertFalse(PlatformFeatureToggle::isEnabled('zatca'));
    }

    public function test_a_non_super_admin_cannot_reach_the_feature_toggle_page(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme-'.uniqid(), 'status' => 'active']);
        $user = User::factory()->create(['role' => 'owner', 'company_id' => $company->id]);

        $response = $this->actingAs($user)->post(route('admin.settings.features.update'), ['features' => ['payroll']]);

        $response->assertForbidden();
        $this->assertTrue(PlatformFeatureToggle::isEnabled('payroll'));
    }
}
