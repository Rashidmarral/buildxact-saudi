<?php

namespace Tests\Feature\Admin;

use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module 03 (Advanced Company / Tenant Management): the companies index's
 * filters/search, the detail page's new tabs, and the four new Super Admin
 * actions (extend trial, cancel/resume subscription, reset settings) —
 * plus the audit-logging guarantees ("never bypass audit logging",
 * old/new/ip/user-agent capture) and tenant isolation of the admin-context
 * Role/Branch/Attachment queries the detail page added.
 */
class CompanyManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'company_id' => null]);
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

    private function withConfirmedPassword($user)
    {
        return $this->actingAs($user)->withSession(['auth.password_confirmed_at' => now()->timestamp]);
    }

    // ---------------------------------------------------------------
    // Index: filters + search
    // ---------------------------------------------------------------

    public function test_index_filters_by_company_status(): void
    {
        $this->makeCompany(['name' => 'Active Co', 'status' => 'active']);
        $this->makeCompany(['name' => 'Suspended Co', 'status' => 'suspended']);

        $response = $this->actingAs($this->makeAdmin())->get(route('admin.companies.index', ['status' => 'suspended']));

        $response->assertOk()->assertSee('Suspended Co')->assertDontSee('Active Co');
    }

    public function test_index_filters_by_subscription_status_trial_expired_cancelled_paid(): void
    {
        $plan = $this->makePlan();

        $trial = $this->makeCompany(['name' => 'Trial Co']);
        Subscription::create(['company_id' => $trial->id, 'plan_id' => $plan->id, 'status' => 'trialing', 'billing_cycle' => 'monthly', 'current_period_end' => now()->addDays(5)]);

        $expired = $this->makeCompany(['name' => 'Expired Co']);
        Subscription::create(['company_id' => $expired->id, 'plan_id' => $plan->id, 'status' => 'expired', 'billing_cycle' => 'monthly', 'current_period_end' => now()->subDays(5)]);

        $cancelled = $this->makeCompany(['name' => 'Cancelled Co']);
        Subscription::create(['company_id' => $cancelled->id, 'plan_id' => $plan->id, 'status' => 'cancelled', 'billing_cycle' => 'monthly', 'cancelled_at' => now(), 'current_period_end' => now()->addDays(5)]);

        $paid = $this->makeCompany(['name' => 'Paid Co']);
        Subscription::create(['company_id' => $paid->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_end' => now()->addDays(20)]);

        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('admin.companies.index', ['status' => 'trial']))
            ->assertSee('Trial Co')->assertDontSee('Expired Co')->assertDontSee('Cancelled Co')->assertDontSee('Paid Co');

        $this->actingAs($admin)->get(route('admin.companies.index', ['status' => 'expired']))
            ->assertSee('Expired Co')->assertDontSee('Trial Co');

        $this->actingAs($admin)->get(route('admin.companies.index', ['status' => 'cancelled']))
            ->assertSee('Cancelled Co')->assertDontSee('Trial Co');

        $this->actingAs($admin)->get(route('admin.companies.index', ['status' => 'paid']))
            ->assertSee('Paid Co')->assertDontSee('Trial Co');
    }

    public function test_index_filters_by_past_due(): void
    {
        $plan = $this->makePlan();

        $pastDue = $this->makeCompany(['name' => 'Past Due Co']);
        Subscription::create(['company_id' => $pastDue->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_start' => now()->subMonths(2), 'current_period_end' => now()->subDays(3)]);

        $healthy = $this->makeCompany(['name' => 'Healthy Co']);
        Subscription::create(['company_id' => $healthy->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_end' => now()->addDays(20)]);

        $response = $this->actingAs($this->makeAdmin())->get(route('admin.companies.index', ['status' => 'past_due']));

        $response->assertSee('Past Due Co')->assertDontSee('Healthy Co');
    }

    public function test_index_filters_by_zatca_connected_and_failed(): void
    {
        $this->makeCompany(['name' => 'Connected Co', 'zatca_onboarding_status' => 'onboarded']);
        $this->makeCompany(['name' => 'Failed Co', 'zatca_onboarding_status' => 'failed']);
        $this->makeCompany(['name' => 'NotStarted Co', 'zatca_onboarding_status' => 'not_started']);

        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('admin.companies.index', ['zatca' => 'connected']))
            ->assertSee('Connected Co')->assertDontSee('Failed Co')->assertDontSee('NotStarted Co');

        $this->actingAs($admin)->get(route('admin.companies.index', ['zatca' => 'failed']))
            ->assertSee('Failed Co')->assertDontSee('Connected Co');
    }

    public function test_index_filters_by_plan_and_registration_date(): void
    {
        $planA = $this->makePlan(['name' => 'Plan A']);
        $planB = $this->makePlan(['name' => 'Plan B']);

        $companyA = $this->makeCompany(['name' => 'On Plan A']);
        Subscription::create(['company_id' => $companyA->id, 'plan_id' => $planA->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_end' => now()->addMonth()]);

        $companyB = $this->makeCompany(['name' => 'On Plan B']);
        Subscription::create(['company_id' => $companyB->id, 'plan_id' => $planB->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_end' => now()->addMonth()]);

        $old = $this->makeCompany(['name' => 'Old Signup']);
        $old->forceFill(['created_at' => now()->subYear()])->save();

        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('admin.companies.index', ['plan_id' => $planA->id]))
            ->assertSee('On Plan A')->assertDontSee('On Plan B');

        $this->actingAs($admin)->get(route('admin.companies.index', ['date_from' => now()->subDays(1)->toDateString()]))
            ->assertSee('On Plan A')->assertDontSee('Old Signup');
    }

    public function test_index_search_matches_company_owner_vat_and_cr_number(): void
    {
        $company = $this->makeCompany(['name' => 'Findable Co', 'vat_number' => '300012345600003', 'cr_number' => '1010101010']);
        User::factory()->create(['company_id' => $company->id, 'role' => 'owner', 'name' => 'Khalid Owner', 'email' => 'khalid@findable.test']);

        $other = $this->makeCompany(['name' => 'Other Co']);
        User::factory()->create(['company_id' => $other->id, 'role' => 'owner', 'name' => 'Someone Else']);

        $admin = $this->makeAdmin();

        foreach (['Findable Co', 'Khalid Owner', 'khalid@findable.test', '300012345600003', '1010101010'] as $term) {
            $this->actingAs($admin)->get(route('admin.companies.index', ['q' => $term]))
                ->assertSee('Findable Co')->assertDontSee('Other Co');
        }
    }

    // ---------------------------------------------------------------
    // Show page: tenant isolation of Role/Branch/Attachment queries
    // ---------------------------------------------------------------

    public function test_show_page_only_lists_the_target_companys_own_branches_and_roles(): void
    {
        $company = $this->makeCompany(['name' => 'Target Co']);
        $other = $this->makeCompany(['name' => 'Other Co']);

        Branch::create(['company_id' => $company->id, 'name' => 'Target Branch']);
        Branch::create(['company_id' => $other->id, 'name' => 'Other Branch']);

        Role::create(['company_id' => $company->id, 'name' => 'Target Role', 'slug' => 'target-role-'.uniqid(), 'permissions' => []]);
        Role::create(['company_id' => $other->id, 'name' => 'Other Role', 'slug' => 'other-role-'.uniqid(), 'permissions' => []]);

        $response = $this->actingAs($this->makeAdmin())->get(route('admin.companies.show', $company));

        $response->assertOk()->assertSee('Target Branch')->assertDontSee('Other Branch')
            ->assertSee('Target Role')->assertDontSee('Other Role');
    }

    public function test_storage_used_only_counts_the_target_companys_attachments(): void
    {
        $company = $this->makeCompany();
        $other = $this->makeCompany();

        Attachment::create(['company_id' => $company->id, 'attachable_type' => Company::class, 'attachable_id' => $company->id, 'original_name' => 'a.pdf', 'path' => 'a.pdf', 'size' => 2 * 1024 * 1024]);
        Attachment::create(['company_id' => $other->id, 'attachable_type' => Company::class, 'attachable_id' => $other->id, 'original_name' => 'b.pdf', 'path' => 'b.pdf', 'size' => 9 * 1024 * 1024]);

        $this->assertSame(2 * 1024 * 1024, $company->storageUsedBytes());
    }

    // ---------------------------------------------------------------
    // Extend trial
    // ---------------------------------------------------------------

    public function test_extend_trial_pushes_trial_and_trialing_subscription_forward(): void
    {
        $plan = $this->makePlan();
        $company = $this->makeCompany(['trial_ends_at' => now()->addDays(3)]);
        $subscription = Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'trialing', 'billing_cycle' => 'monthly', 'current_period_end' => now()->addDays(3)]);

        $response = $this->withConfirmedPassword($this->makeAdmin())
            ->post(route('admin.companies.extend-trial', $company), ['days' => 10]);

        $response->assertRedirect();
        $company->refresh();
        $subscription->refresh();

        $this->assertTrue($company->trial_ends_at->isAfter(now()->addDays(12)));
        $this->assertTrue($subscription->current_period_end->isAfter(now()->addDays(12)));

        $log = AuditLog::where('action', 'company.extend_trial')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertNotNull($log->old_value);
        $this->assertNotNull($log->new_value);
        $this->assertSame($company->id, $log->company_id);
    }

    public function test_extend_trial_rejects_invalid_days(): void
    {
        $company = $this->makeCompany();

        $response = $this->withConfirmedPassword($this->makeAdmin())
            ->post(route('admin.companies.extend-trial', $company), ['days' => 0]);

        $response->assertSessionHasErrors('days');
    }

    public function test_extend_trial_requires_recent_password_confirmation(): void
    {
        $company = $this->makeCompany();

        $response = $this->actingAs($this->makeAdmin())
            ->post(route('admin.companies.extend-trial', $company), ['days' => 10]);

        $response->assertRedirect(route('admin.password.confirm'));
    }

    // ---------------------------------------------------------------
    // Cancel / resume subscription
    // ---------------------------------------------------------------

    public function test_cancel_subscription_schedules_cancellation_and_logs_old_new(): void
    {
        $plan = $this->makePlan();
        $company = $this->makeCompany();
        $subscription = Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_end' => now()->addMonth()]);

        $response = $this->withConfirmedPassword($this->makeAdmin())
            ->post(route('admin.companies.cancel-subscription', $company));

        $response->assertRedirect();
        $subscription->refresh();
        $this->assertNotNull($subscription->cancelled_at);
        // Cancelling doesn't immediately flip status — the existing
        // subscriptions:expire-cancelled scheduled command does that.
        $this->assertSame('active', $subscription->status);

        $log = AuditLog::where('action', 'company.cancel_subscription')->latest('id')->first();
        $this->assertNull($log->old_value['cancelled_at']);
        $this->assertNotNull($log->new_value['cancelled_at']);
    }

    public function test_cancel_subscription_is_a_no_op_when_already_cancelled(): void
    {
        $plan = $this->makePlan();
        $company = $this->makeCompany();
        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'cancelled_at' => now(), 'current_period_end' => now()->addMonth()]);

        $response = $this->withConfirmedPassword($this->makeAdmin())
            ->post(route('admin.companies.cancel-subscription', $company));

        $response->assertNotFound();
    }

    public function test_resume_subscription_clears_pending_cancellation(): void
    {
        $plan = $this->makePlan();
        $company = $this->makeCompany();
        $subscription = Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'cancelled_at' => now(), 'current_period_end' => now()->addMonth()]);

        $response = $this->withConfirmedPassword($this->makeAdmin())
            ->post(route('admin.companies.resume-subscription', $company));

        $response->assertRedirect();
        $subscription->refresh();
        $this->assertNull($subscription->cancelled_at);

        $log = AuditLog::where('action', 'company.resume_subscription')->latest('id')->first();
        $this->assertNotNull($log->old_value['cancelled_at']);
        $this->assertNull($log->new_value['cancelled_at']);
    }

    public function test_resume_subscription_is_a_no_op_when_not_cancelled(): void
    {
        $plan = $this->makePlan();
        $company = $this->makeCompany();
        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_end' => now()->addMonth()]);

        $response = $this->withConfirmedPassword($this->makeAdmin())
            ->post(route('admin.companies.resume-subscription', $company));

        $response->assertNotFound();
    }

    // ---------------------------------------------------------------
    // Reset company settings
    // ---------------------------------------------------------------

    public function test_reset_settings_restores_operational_defaults_without_touching_protected_fields(): void
    {
        $company = $this->makeCompany([
            'currency' => 'USD', 'locale' => 'ar', 'invoice_prefix' => 'CUSTOM', 'next_invoice_number' => 42,
            'vat_number' => '300099998800003', 'zatca_onboarding_status' => 'onboarded',
        ]);
        $owner = User::factory()->create(['company_id' => $company->id, 'role' => 'owner']);

        $response = $this->withConfirmedPassword($this->makeAdmin())
            ->post(route('admin.companies.reset-settings', $company));

        $response->assertRedirect();
        $company->refresh();

        $this->assertSame('SAR', $company->currency);
        $this->assertSame('en', $company->locale);
        $this->assertSame('INV', $company->invoice_prefix);
        $this->assertSame(1, $company->next_invoice_number);

        // Protected: never touched by a "reset settings" action.
        $this->assertSame('300099998800003', $company->vat_number);
        $this->assertSame('onboarded', $company->zatca_onboarding_status);
        $this->assertTrue($company->users()->whereKey($owner->id)->exists());

        $log = AuditLog::where('action', 'company.reset_settings')->latest('id')->first();
        $this->assertSame('USD', $log->old_value['currency']);
        $this->assertSame('SAR', $log->new_value['currency']);
    }

    // ---------------------------------------------------------------
    // Audit logging: never bypassable, captures actor/ip/user-agent
    // ---------------------------------------------------------------

    public function test_suspend_and_activate_record_audit_entries_with_ip_and_user_agent(): void
    {
        $company = $this->makeCompany();
        $admin = $this->makeAdmin();

        $this->withConfirmedPassword($admin)
            ->withHeaders(['User-Agent' => 'Module03-Test-Agent'])
            ->post(route('admin.companies.suspend', $company))
            ->assertRedirect();

        $log = AuditLog::where('action', 'company.suspend')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->admin_user_id);
        $this->assertSame($company->id, $log->company_id);
        $this->assertSame('active', $log->old_value['status']);
        $this->assertSame('suspended', $log->new_value['status']);
        $this->assertSame('Module03-Test-Agent', $log->user_agent);
    }

    public function test_destructive_actions_require_the_companies_admin_permission(): void
    {
        $company = $this->makeCompany();
        $staff = User::factory()->create(['role' => 'admin_staff', 'company_id' => null]);

        $response = $this->withConfirmedPassword($staff)
            ->post(route('admin.companies.cancel-subscription', $company));

        $response->assertForbidden();
    }
}
