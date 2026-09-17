<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Subscriptions\SubscriptionLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module 04 (SaaS Plans & Subscription Management): the plan lifecycle
 * columns, the automatic dunning ladder (trial -> past_due -> grace_period
 * -> suspended -> cancelled) that SubscriptionLifecycleService and
 * RunSubscriptionLifecycleRules drive, and every new Super Admin action
 * (upgrade/downgrade/grace period/pause/resume/reactivate/comp). Per the
 * user's explicit request, this covers: Trial, Upgrade, Downgrade, Expiry,
 * Failed payment, Cancellation, Reactivation.
 */
class SubscriptionLifecycleTest extends TestCase
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
    // Trial
    // ---------------------------------------------------------------

    public function test_plan_level_trial_days_override_the_platform_default(): void
    {
        $this->makePlan(['name' => 'Starter', 'trial_days' => 45, 'is_active' => true, 'is_public' => true]);

        $response = $this->post(route('register'), [
            'first_name' => 'Sara', 'last_name' => 'Al-Otaibi', 'email' => 'sara@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'phone' => '0512345678', 'job_title' => 'owner_ceo', 'company_name' => 'Sara Trading',
            'organization_size' => '11-50', 'industry' => 'retail_trading', 'primary_customer_type' => 'b2b',
        ]);

        $response->assertRedirect(route('app.dashboard'));
        $company = Company::where('name', 'Sara Trading')->first();
        $this->assertNotNull($company);
        $this->assertEqualsWithDelta(45, now()->diffInDays($company->trial_ends_at), 1);
    }

    public function test_trial_ending_reminder_uses_the_configurable_window_not_a_hardcoded_one(): void
    {
        Setting::set('subscription_trial_reminder_days_before', '10');
        $plan = $this->makePlan();
        $company = $this->makeCompany();
        User::factory()->create(['company_id' => $company->id, 'role' => 'owner']);
        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'trialing', 'billing_cycle' => 'monthly', 'current_period_end' => now()->addDays(9)]);

        $sent = app(SubscriptionLifecycleService::class)->sendTrialEndingReminders();

        // Would be 0 under the old hardcoded 3-day window — proves the
        // Setting is actually being read, not a constant.
        $this->assertSame(1, $sent);
    }

    // ---------------------------------------------------------------
    // Expiry (trial)
    // ---------------------------------------------------------------

    public function test_expire_trials_flips_a_lapsed_trial_to_expired_and_logs_it(): void
    {
        $plan = $this->makePlan();
        $company = $this->makeCompany();
        $subscription = Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'trialing', 'billing_cycle' => 'monthly', 'current_period_end' => now()->subDay()]);

        $count = app(SubscriptionLifecycleService::class)->expireTrials();

        $this->assertSame(1, $count);
        $this->assertSame('expired', $subscription->fresh()->status);

        $log = AuditLog::where('action', 'subscription.trial_expired')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('trialing', $log->old_value['status']);
        $this->assertSame('expired', $log->new_value['status']);
        $this->assertSame($company->id, $log->company_id);
        $this->assertNull($log->admin_user_id);
    }

    public function test_expired_trial_no_longer_counts_as_the_companys_active_subscription(): void
    {
        $plan = $this->makePlan();
        $company = $this->makeCompany();
        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'trialing', 'billing_cycle' => 'monthly', 'current_period_end' => now()->subDay()]);

        app(SubscriptionLifecycleService::class)->expireTrials();

        $this->assertNull($company->activeSubscription());
    }

    // ---------------------------------------------------------------
    // Failed payment -> past due -> grace period -> suspended -> cancelled
    // ---------------------------------------------------------------

    public function test_flag_past_due_catches_an_active_subscription_whose_period_lapsed_unpaid(): void
    {
        $plan = $this->makePlan();
        $company = $this->makeCompany();
        $subscription = Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_end' => now()->subDay()]);

        $count = app(SubscriptionLifecycleService::class)->flagPastDue();

        $this->assertSame(1, $count);
        $this->assertSame('past_due', $subscription->fresh()->status);
    }

    public function test_flag_past_due_never_touches_a_subscription_with_a_pending_self_service_cancellation(): void
    {
        $plan = $this->makePlan();
        $company = $this->makeCompany();
        $subscription = Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'cancelled_at' => now(), 'current_period_end' => now()->subDay()]);

        app(SubscriptionLifecycleService::class)->flagPastDue();

        $this->assertSame('active', $subscription->fresh()->status);
    }

    public function test_flag_past_due_never_touches_a_comp_account(): void
    {
        $plan = $this->makePlan();
        $company = $this->makeCompany();
        $subscription = Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'is_comp' => true, 'current_period_end' => now()->subDay()]);

        app(SubscriptionLifecycleService::class)->flagPastDue();

        $this->assertSame('active', $subscription->fresh()->status);
    }

    public function test_past_due_subscription_still_shows_as_the_companys_current_subscription(): void
    {
        $plan = $this->makePlan();
        $company = $this->makeCompany();
        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'past_due', 'billing_cycle' => 'monthly', 'current_period_end' => now()->subDays(2)]);

        $active = $company->activeSubscription();

        $this->assertNotNull($active);
        $this->assertSame('past_due', $active->status);
    }

    public function test_advance_grace_period_moves_a_sufficiently_overdue_past_due_subscription_forward(): void
    {
        Setting::set('subscription_grace_period_days', '3');
        Setting::set('subscription_suspend_after_grace_days', '5');
        $plan = $this->makePlan();
        $company = $this->makeCompany();
        $periodEnd = now()->subDays(4);
        $subscription = Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'past_due', 'billing_cycle' => 'monthly', 'current_period_end' => $periodEnd]);

        $count = app(SubscriptionLifecycleService::class)->advanceGracePeriod();

        $this->assertSame(1, $count);
        $subscription->refresh();
        $this->assertSame('grace_period', $subscription->status);
        // Anchored on current_period_end + (grace_period_days + suspend_after_grace_days), not now().
        $expected = $periodEnd->copy()->addDays(3 + 5);
        $this->assertEqualsWithDelta($expected->timestamp, $subscription->grace_period_ends_at->timestamp, 5);
    }

    public function test_advance_grace_period_leaves_a_recently_past_due_subscription_alone(): void
    {
        Setting::set('subscription_grace_period_days', '3');
        $plan = $this->makePlan();
        $company = $this->makeCompany();
        $subscription = Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'past_due', 'billing_cycle' => 'monthly', 'current_period_end' => now()->subDay()]);

        app(SubscriptionLifecycleService::class)->advanceGracePeriod();

        $this->assertSame('past_due', $subscription->fresh()->status);
    }

    public function test_suspend_expired_grace_periods_moves_a_lapsed_grace_period_to_suspended(): void
    {
        $plan = $this->makePlan();
        $company = $this->makeCompany();
        $subscription = Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'grace_period', 'billing_cycle' => 'monthly', 'grace_period_ends_at' => now()->subDay(), 'current_period_end' => now()->subDays(10)]);

        $count = app(SubscriptionLifecycleService::class)->suspendExpiredGracePeriods();

        $this->assertSame(1, $count);
        $subscription->refresh();
        $this->assertSame('suspended', $subscription->status);
        $this->assertNotNull($subscription->suspended_at);
    }

    public function test_cancel_abandoned_suspensions_cancels_after_the_configured_window(): void
    {
        Setting::set('subscription_cancel_after_suspended_days', '7');
        $plan = $this->makePlan();
        $company = $this->makeCompany();
        $subscription = Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'suspended', 'billing_cycle' => 'monthly', 'suspended_at' => now()->subDays(8), 'current_period_end' => now()->subDays(20)]);

        $count = app(SubscriptionLifecycleService::class)->cancelAbandonedSuspensions();

        $this->assertSame(1, $count);
        $subscription->refresh();
        $this->assertSame('cancelled', $subscription->status);
        $this->assertNotNull($subscription->cancelled_at);
    }

    public function test_cancel_abandoned_suspensions_leaves_a_recently_suspended_subscription_alone(): void
    {
        Setting::set('subscription_cancel_after_suspended_days', '7');
        $plan = $this->makePlan();
        $company = $this->makeCompany();
        $subscription = Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'suspended', 'billing_cycle' => 'monthly', 'suspended_at' => now()->subDays(2), 'current_period_end' => now()->subDays(20)]);

        app(SubscriptionLifecycleService::class)->cancelAbandonedSuspensions();

        $this->assertSame('suspended', $subscription->fresh()->status);
    }

    public function test_full_ladder_cascades_through_every_stage_when_the_rule_command_runs_after_a_long_gap(): void
    {
        // With very short configured windows, a subscription that's been
        // unpaid for a while should cascade through several stages in one
        // command run — the ladder isn't "one day per stage" but "as far
        // as the elapsed time justifies".
        Setting::set('subscription_grace_period_days', '1');
        Setting::set('subscription_suspend_after_grace_days', '1');
        Setting::set('subscription_cancel_after_suspended_days', '1');

        $plan = $this->makePlan();
        $company = $this->makeCompany();
        $subscription = Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_end' => now()->subDays(10)]);

        $this->artisan('subscriptions:run-lifecycle-rules')->assertSuccessful();

        // past_due -> grace_period -> suspended all within this single run
        // (each step's own where-clause is satisfied by the 10-day-old
        // current_period_end), landing on 'suspended'.
        $this->assertSame('suspended', $subscription->fresh()->status);
    }

    // ---------------------------------------------------------------
    // Upgrade / Downgrade
    // ---------------------------------------------------------------

    public function test_admin_can_upgrade_a_company_to_a_more_expensive_plan(): void
    {
        $starter = $this->makePlan(['name' => 'Starter', 'price_monthly' => 50]);
        $pro = $this->makePlan(['name' => 'Pro', 'price_monthly' => 150]);
        $company = $this->makeCompany();
        Subscription::create(['company_id' => $company->id, 'plan_id' => $starter->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_end' => now()->addMonth()]);

        $response = $this->withConfirmedPassword($this->makeAdmin())
            ->post(route('admin.companies.upgrade-plan', $company), ['plan_id' => $pro->id, 'billing_cycle' => 'monthly']);

        $response->assertRedirect();
        $this->assertSame($pro->id, $company->activeSubscription()->plan_id);

        $log = AuditLog::where('action', 'subscription.upgrade')->latest('id')->first();
        $this->assertSame($starter->id, $log->old_value['plan_id']);
        $this->assertSame($pro->id, $log->new_value['plan_id']);
    }

    public function test_upgrade_rejects_a_plan_that_is_actually_cheaper(): void
    {
        $starter = $this->makePlan(['name' => 'Starter', 'price_monthly' => 50]);
        $pro = $this->makePlan(['name' => 'Pro', 'price_monthly' => 150]);
        $company = $this->makeCompany();
        Subscription::create(['company_id' => $company->id, 'plan_id' => $pro->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_end' => now()->addMonth()]);

        $response = $this->withConfirmedPassword($this->makeAdmin())
            ->post(route('admin.companies.upgrade-plan', $company), ['plan_id' => $starter->id, 'billing_cycle' => 'monthly']);

        $response->assertStatus(422);
        $this->assertSame($pro->id, $company->activeSubscription()->plan_id);
    }

    public function test_admin_can_downgrade_a_company_to_a_cheaper_plan(): void
    {
        $starter = $this->makePlan(['name' => 'Starter', 'price_monthly' => 50]);
        $pro = $this->makePlan(['name' => 'Pro', 'price_monthly' => 150]);
        $company = $this->makeCompany();
        Subscription::create(['company_id' => $company->id, 'plan_id' => $pro->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_end' => now()->addMonth()]);

        $response = $this->withConfirmedPassword($this->makeAdmin())
            ->post(route('admin.companies.downgrade-plan', $company), ['plan_id' => $starter->id, 'billing_cycle' => 'monthly']);

        $response->assertRedirect();
        $this->assertSame($starter->id, $company->activeSubscription()->plan_id);

        $log = AuditLog::where('action', 'subscription.downgrade')->latest('id')->first();
        $this->assertSame($pro->id, $log->old_value['plan_id']);
        $this->assertSame($starter->id, $log->new_value['plan_id']);
    }

    public function test_downgrade_rejects_a_plan_that_is_actually_more_expensive(): void
    {
        $starter = $this->makePlan(['name' => 'Starter', 'price_monthly' => 50]);
        $pro = $this->makePlan(['name' => 'Pro', 'price_monthly' => 150]);
        $company = $this->makeCompany();
        Subscription::create(['company_id' => $company->id, 'plan_id' => $starter->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_end' => now()->addMonth()]);

        $response = $this->withConfirmedPassword($this->makeAdmin())
            ->post(route('admin.companies.downgrade-plan', $company), ['plan_id' => $pro->id, 'billing_cycle' => 'monthly']);

        $response->assertStatus(422);
    }

    // ---------------------------------------------------------------
    // Cancellation (manual, already existed) + Reactivation (new)
    // ---------------------------------------------------------------

    public function test_admin_can_reactivate_a_cancelled_subscription_with_a_fresh_period(): void
    {
        $plan = $this->makePlan();
        $company = $this->makeCompany();
        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'cancelled', 'billing_cycle' => 'monthly', 'cancelled_at' => now()->subDays(30), 'current_period_end' => now()->subDays(30)]);

        $response = $this->withConfirmedPassword($this->makeAdmin())
            ->post(route('admin.companies.reactivate-subscription', $company), ['plan_id' => $plan->id, 'billing_cycle' => 'monthly']);

        $response->assertRedirect();
        $fresh = $company->activeSubscription();
        $this->assertNotNull($fresh);
        $this->assertSame('active', $fresh->status);
        $this->assertTrue($fresh->current_period_end->isFuture());

        $log = AuditLog::where('action', 'subscription.reactivate')->latest('id')->first();
        $this->assertSame('cancelled', $log->old_value['status']);
    }

    public function test_reactivate_refuses_a_subscription_that_is_not_actually_terminal(): void
    {
        $plan = $this->makePlan();
        $company = $this->makeCompany();
        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_end' => now()->addMonth()]);

        $response = $this->withConfirmedPassword($this->makeAdmin())
            ->post(route('admin.companies.reactivate-subscription', $company), ['plan_id' => $plan->id, 'billing_cycle' => 'monthly']);

        $response->assertNotFound();
    }

    // ---------------------------------------------------------------
    // Grace period + Pause (manual) + Resume (extended to the ladder)
    // ---------------------------------------------------------------

    public function test_admin_can_manually_add_a_grace_period(): void
    {
        $plan = $this->makePlan();
        $company = $this->makeCompany();
        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'past_due', 'billing_cycle' => 'monthly', 'current_period_end' => now()->subDays(2)]);

        $response = $this->withConfirmedPassword($this->makeAdmin())
            ->post(route('admin.companies.add-grace-period', $company), ['days' => 5]);

        $response->assertRedirect();
        $subscription = $company->activeSubscription();
        $this->assertSame('grace_period', $subscription->status);
        $this->assertEqualsWithDelta(5, now()->diffInDays($subscription->grace_period_ends_at), 1);
    }

    public function test_admin_can_pause_an_active_subscription(): void
    {
        $plan = $this->makePlan();
        $company = $this->makeCompany();
        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_end' => now()->addMonth()]);

        $response = $this->withConfirmedPassword($this->makeAdmin())
            ->post(route('admin.companies.pause-subscription', $company));

        $response->assertRedirect();
        $subscription = $company->subscriptions()->latest('id')->first();
        $this->assertSame('suspended', $subscription->status);
        $this->assertNotNull($subscription->suspended_at);
    }

    public function test_resume_subscription_reactivates_a_suspended_subscription(): void
    {
        $plan = $this->makePlan();
        $company = $this->makeCompany();
        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'suspended', 'billing_cycle' => 'monthly', 'suspended_at' => now(), 'current_period_end' => now()->subDays(10)]);

        $response = $this->withConfirmedPassword($this->makeAdmin())
            ->post(route('admin.companies.resume-subscription', $company));

        $response->assertRedirect();
        $subscription = $company->activeSubscription();
        $this->assertSame('active', $subscription->status);
        $this->assertNull($subscription->suspended_at);
    }

    // ---------------------------------------------------------------
    // Comp account
    // ---------------------------------------------------------------

    public function test_admin_can_grant_a_comp_account(): void
    {
        $plan = $this->makePlan();
        $company = $this->makeCompany();

        $response = $this->withConfirmedPassword($this->makeAdmin())
            ->post(route('admin.companies.comp-account', $company), ['plan_id' => $plan->id, 'reason' => 'Partner account']);

        $response->assertRedirect();
        $subscription = $company->activeSubscription();
        $this->assertNotNull($subscription);
        $this->assertTrue($subscription->is_comp);
        $this->assertSame('Partner account', $subscription->comp_reason);
        $this->assertSame('active', $subscription->status);
    }

    // ---------------------------------------------------------------
    // Plan visibility (is_public) + configurable settings persistence
    // ---------------------------------------------------------------

    public function test_private_plans_are_excluded_from_the_public_pricing_page(): void
    {
        $this->makePlan(['name' => 'Public Plan', 'is_active' => true, 'is_public' => true]);
        $this->makePlan(['name' => 'Private Plan', 'is_active' => true, 'is_public' => false]);

        $response = $this->get(route('pricing'));

        $response->assertOk()->assertSee('Public Plan')->assertDontSee('Private Plan');
    }

    public function test_platform_settings_persist_the_configurable_lifecycle_rule_days(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post(route('admin.settings.signup.update'), [
            'trial_days' => 14,
            'support_email' => 'support@example.com',
            'subscription_trial_reminder_days_before' => 5,
            'subscription_grace_period_days' => 2,
            'subscription_suspend_after_grace_days' => 3,
            'subscription_cancel_after_suspended_days' => 10,
        ]);

        $response->assertRedirect();
        $this->assertSame('5', Setting::get('subscription_trial_reminder_days_before'));
        $this->assertSame(5, app(SubscriptionLifecycleService::class)->trialReminderDaysBefore());
        $this->assertSame(2, app(SubscriptionLifecycleService::class)->defaultGracePeriodDays());
        $this->assertSame(3, app(SubscriptionLifecycleService::class)->defaultSuspendAfterGraceDays());
        $this->assertSame(10, app(SubscriptionLifecycleService::class)->defaultCancelAfterSuspendedDays());
    }
}
