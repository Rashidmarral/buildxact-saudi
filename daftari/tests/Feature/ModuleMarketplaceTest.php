<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyOverride;
use App\Models\ModuleRequest;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Features\FeatureAccessService;
use App\Support\ModulePricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Follow-up to the Restaurant module: paid modules (Payroll, POS,
 * Restaurant Management) must not be visible in the sidebar — or usable
 * — until a company has actually installed them. Adds a company-facing
 * "Modules" marketplace (price + Request to install) and an admin
 * review queue, so getting a module isn't "silently in your plan" but a
 * deliberate purchase-then-install step, matching how Payroll/POS/
 * Restaurant are already gated per company via FeatureAccessService.
 */
class ModuleMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(array $planOverrides = []): Company
    {
        $plan = Plan::create(array_merge([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true,
        ], $planOverrides));

        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active']);

        Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function withConfirmedPassword(User $user)
    {
        return $this->actingAs($user)->withSession(['auth.password_confirmed_at' => now()->timestamp]);
    }

    // ------------------------------------------------------------------
    // Sidebar visibility
    // ------------------------------------------------------------------

    public function test_the_sidebar_hides_uninstalled_module_links(): void
    {
        $company = $this->makeCompany(['has_pos' => false, 'has_payroll' => false, 'has_restaurant' => false]);
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->get(route('app.dashboard'));

        $response->assertOk();
        $response->assertDontSee(route('app.pos.terminal'), false);
        $response->assertDontSee(route('app.payroll.index'), false);
        $response->assertDontSee(route('app.restaurant.orders.index'), false);
    }

    public function test_the_sidebar_shows_a_module_link_once_the_plan_includes_it(): void
    {
        $company = $this->makeCompany(['has_pos' => true]);
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->get(route('app.dashboard'));

        $response->assertOk();
        $response->assertSee(route('app.pos.terminal'), false);
    }

    public function test_the_sidebar_shows_a_module_link_once_a_company_override_installs_it(): void
    {
        $company = $this->makeCompany(['has_restaurant' => false]);
        $owner = $this->makeOwner($company);
        CompanyOverride::create(['company_id' => $company->id, 'type' => 'feature', 'key' => 'restaurant', 'value' => '1']);

        $response = $this->actingAs($owner)->get(route('app.dashboard'));

        $response->assertOk();
        $response->assertSee(route('app.restaurant.orders.index'), false);
    }

    // ------------------------------------------------------------------
    // Company-facing marketplace
    // ------------------------------------------------------------------

    public function test_the_marketplace_shows_price_and_available_status_for_an_uninstalled_module(): void
    {
        ModulePricing::set('pos', 149);
        $company = $this->makeCompany(['has_pos' => false]);
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->get(route('app.modules.index'));

        $response->assertOk();
        $response->assertSee('149.00');
        $response->assertSee(__('Request to install'));
    }

    public function test_the_marketplace_shows_an_installed_module_with_no_request_button(): void
    {
        $company = $this->makeCompany(['has_payroll' => true]);
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->get(route('app.modules.index'));

        $response->assertOk();
        $response->assertSee(__('Installed'));
    }

    public function test_an_owner_can_request_to_install_a_module(): void
    {
        $company = $this->makeCompany(['has_restaurant' => false]);
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->post(route('app.modules.request', 'restaurant'), [
            'note' => 'We opened a new branch that is a restaurant.',
        ]);

        $response->assertRedirect();
        $moduleRequest = ModuleRequest::first();
        $this->assertSame('restaurant', $moduleRequest->module_key);
        $this->assertSame('requested', $moduleRequest->status);
        $this->assertSame($owner->id, $moduleRequest->requested_by);

        $marketplace = $this->actingAs($owner)->get(route('app.modules.index'));
        $marketplace->assertSee(__('Pending review'));
    }

    public function test_a_module_already_enabled_cannot_be_requested_again(): void
    {
        $company = $this->makeCompany(['has_pos' => true]);
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->post(route('app.modules.request', 'pos'));

        $response->assertSessionHasErrors('module');
        $this->assertSame(0, ModuleRequest::count());
    }

    public function test_a_second_pending_request_for_the_same_module_is_blocked(): void
    {
        $company = $this->makeCompany(['has_pos' => false]);
        $owner = $this->makeOwner($company);
        $this->actingAs($owner)->post(route('app.modules.request', 'pos'));

        $response = $this->actingAs($owner)->post(route('app.modules.request', 'pos'));

        $response->assertSessionHasErrors('module');
        $this->assertSame(1, ModuleRequest::count());
    }

    public function test_requesting_an_unknown_module_key_404s(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->post(route('app.modules.request', 'not-a-real-module'))->assertNotFound();
    }

    public function test_a_non_owner_cannot_reach_the_modules_marketplace(): void
    {
        $company = $this->makeCompany();
        $accountant = User::factory()->create(['role' => 'accountant', 'company_id' => $company->id, 'status' => 'active']);

        $this->actingAs($accountant)->get(route('app.modules.index'))->assertForbidden();
    }

    // ------------------------------------------------------------------
    // Admin review queue
    // ------------------------------------------------------------------

    public function test_a_super_admin_can_approve_a_request_which_installs_the_module(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'company_id' => null]);
        $company = $this->makeCompany(['has_restaurant' => false]);
        $owner = $this->makeOwner($company);
        $this->actingAs($owner)->post(route('app.modules.request', 'restaurant'));
        $moduleRequest = ModuleRequest::first();

        $this->assertFalse(app(FeatureAccessService::class)->enabled($company, 'restaurant'));

        $response = $this->withConfirmedPassword($admin)->post(route('admin.module-requests.approve', $moduleRequest));

        $response->assertRedirect();
        $this->assertSame('approved', $moduleRequest->fresh()->status);
        $this->assertSame($admin->id, $moduleRequest->fresh()->reviewed_by);
        $this->assertTrue(app(FeatureAccessService::class)->enabled($company->fresh(), 'restaurant'));

        $dashboard = $this->actingAs($owner)->get(route('app.dashboard'));
        $dashboard->assertSee(route('app.restaurant.orders.index'), false);
    }

    public function test_a_super_admin_can_reject_a_request_with_a_note(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'company_id' => null]);
        $company = $this->makeCompany(['has_pos' => false]);
        $owner = $this->makeOwner($company);
        $this->actingAs($owner)->post(route('app.modules.request', 'pos'));
        $moduleRequest = ModuleRequest::first();

        $response = $this->withConfirmedPassword($admin)->post(route('admin.module-requests.reject', $moduleRequest), [
            'admin_note' => 'Please contact sales to arrange payment first.',
        ]);

        $response->assertRedirect();
        $moduleRequest->refresh();
        $this->assertSame('rejected', $moduleRequest->status);
        $this->assertSame('Please contact sales to arrange payment first.', $moduleRequest->admin_note);
        $this->assertFalse(app(FeatureAccessService::class)->enabled($company->fresh(), 'pos'));

        $marketplace = $this->actingAs($owner)->get(route('app.modules.index'));
        $marketplace->assertSee(__('Not approved'));
        $marketplace->assertSee('Please contact sales to arrange payment first.');
        $marketplace->assertSee(__('Request again'));
    }

    public function test_a_request_cannot_be_reviewed_twice(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'company_id' => null]);
        $company = $this->makeCompany(['has_pos' => false]);
        $owner = $this->makeOwner($company);
        $this->actingAs($owner)->post(route('app.modules.request', 'pos'));
        $moduleRequest = ModuleRequest::first();
        $this->withConfirmedPassword($admin)->post(route('admin.module-requests.approve', $moduleRequest));

        $response = $this->withConfirmedPassword($admin)->post(route('admin.module-requests.reject', $moduleRequest));

        $response->assertSessionHasErrors('module_request');
        $this->assertSame('approved', $moduleRequest->fresh()->status);
    }

    public function test_the_admin_queue_lists_requests_across_companies_and_filters_by_status(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'company_id' => null]);
        $companyA = $this->makeCompany(['has_pos' => false]);
        $companyB = $this->makeCompany(['has_payroll' => false]);
        $ownerA = $this->makeOwner($companyA);
        $ownerB = $this->makeOwner($companyB);
        $this->actingAs($ownerA)->post(route('app.modules.request', 'pos'));
        $this->actingAs($ownerB)->post(route('app.modules.request', 'payroll'));

        $response = $this->withConfirmedPassword($admin)->get(route('admin.module-requests.index'));

        $response->assertOk();
        $response->assertSee($companyA->name);
        $response->assertSee($companyB->name);
    }

    public function test_a_non_super_admin_without_company_permission_cannot_reach_the_admin_queue(): void
    {
        $company = $this->makeCompany();
        $staff = User::factory()->create(['role' => 'admin_staff', 'company_id' => null, 'status' => 'active']);

        $this->actingAs($staff)->get(route('admin.module-requests.index'))->assertForbidden();
    }

    // ------------------------------------------------------------------
    // Admin-set pricing (the other half of the marketplace)
    // ------------------------------------------------------------------

    public function test_a_super_admin_can_set_a_modules_monthly_price_from_platform_settings(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'company_id' => null]);

        $response = $this->actingAs($admin)->post(route('admin.settings.features.update'), [
            'features' => ['payroll', 'pos', 'restaurant'],
            'prices' => ['restaurant' => '199.99'],
        ]);

        $response->assertRedirect();
        $this->assertSame(199.99, ModulePricing::get('restaurant'));
        $this->assertNull(ModulePricing::get('pos'));

        $company = $this->makeCompany(['has_restaurant' => false]);
        $owner = $this->makeOwner($company);
        $this->actingAs($owner)->get(route('app.modules.index'))->assertSee('199.99');
    }

    /**
     * UX audit finding: the Modules marketplace nav link and page stayed in
     * English when the app was switched to Arabic, because lang/ar.json had
     * no entries for its strings — pins the fix.
     */
    public function test_the_modules_page_renders_in_arabic(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $this->actingAs($owner)->get(route('locale.switch', 'ar'));

        $this->actingAs($owner)->get(route('app.modules.index'))
            ->assertOk()->assertSee(__('Modules'))->assertSee(__('Request to install'));
    }
}
