<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module 02 (Advanced Super Admin Dashboard): exercises the full new query
 * set — latest-subscription-per-company, trial conversion, past-due
 * detection, failed_jobs/sessions lookups, system health checks — against
 * a small but real dataset, so a query mistake fails loudly here instead
 * of only on first production admin login.
 */
class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'company_id' => null]);
    }

    public function test_dashboard_loads_with_no_data_at_all(): void
    {
        $response = $this->actingAs($this->makeAdmin())->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('stats', fn ($stats) => $stats['total_companies'] === 0
            && (float) $stats['mrr'] === 0.0
            && $stats['trial_conversion_rate'] === 0.0);
    }

    public function test_dashboard_computes_real_kpis_from_seeded_data(): void
    {
        $plan = Plan::create(['name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true]);

        $trialCompany = Company::create(['name' => 'Trial Co', 'slug' => 'trial-'.uniqid(), 'status' => 'active']);
        Subscription::create(['company_id' => $trialCompany->id, 'plan_id' => $plan->id, 'status' => 'trialing', 'billing_cycle' => 'monthly', 'current_period_end' => now()->addDays(2)]);

        $convertedCompany = Company::create(['name' => 'Converted Co', 'slug' => 'converted-'.uniqid(), 'status' => 'active']);
        Subscription::create(['company_id' => $convertedCompany->id, 'plan_id' => $plan->id, 'status' => 'trialing', 'billing_cycle' => 'monthly', 'current_period_end' => now()->subDays(10)]);
        Subscription::create(['company_id' => $convertedCompany->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_start' => now()->subDays(9), 'current_period_end' => now()->addDays(20)]);
        Payment::create(['company_id' => $convertedCompany->id, 'plan_id' => $plan->id, 'amount' => 100, 'currency' => 'SAR', 'status' => 'paid', 'paid_at' => now()]);

        $pastDueCompany = Company::create(['name' => 'Past Due Co', 'slug' => 'pastdue-'.uniqid(), 'status' => 'active']);
        Subscription::create(['company_id' => $pastDueCompany->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_start' => now()->subMonths(2), 'current_period_end' => now()->subDays(3)]);

        $suspendedCompany = Company::create(['name' => 'Suspended Co', 'slug' => 'suspended-'.uniqid(), 'status' => 'suspended']);

        $response = $this->actingAs($this->makeAdmin())->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('stats', function ($stats) {
            return $stats['total_companies'] === 4
                && $stats['trialing_companies'] === 1
                && $stats['active_subscriptions'] === 2
                && $stats['past_due_subscriptions'] === 1
                && $stats['suspended_companies'] === 1
                && $stats['trial_conversion_rate'] === 50.0
                && $stats['arr'] === $stats['mrr'] * 12;
        });
    }

    public function test_dashboard_shows_system_health_and_attention_sections(): void
    {
        $response = $this->actingAs($this->makeAdmin())->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('systemHealthChecks', fn ($checks) => count($checks) === 9);
        $response->assertViewHas('failedJobsCount', fn ($count) => $count === 0);
        $response->assertSee(__('System health'));
    }

    /**
     * The four hand-rolled CSS-bar sections (subscription status, revenue
     * trend, plan distribution, signups trend) were rewritten as Chart.js
     * canvases. This can't read numbers back out of a <canvas>, so it
     * asserts on what the markup itself must still contain: a canvas per
     * chart when there's data to plot, and the same empty-state copy as
     * before when there isn't.
     */
    public function test_dashboard_renders_chart_canvases_with_seeded_data(): void
    {
        $plan = Plan::create(['name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true]);
        $company = Company::create(['name' => 'Chart Admin Co', 'slug' => 'chart-admin-'.uniqid(), 'status' => 'active']);
        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth()]);
        Payment::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'amount' => 100, 'currency' => 'SAR', 'status' => 'paid', 'paid_at' => now()]);

        $response = $this->actingAs($this->makeAdmin())->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('id="subscriptionStatusChart"', false);
        $response->assertSee('id="revenueTrendChart"', false);
        $response->assertSee('id="planDistributionChart"', false);
        $response->assertSee('id="signupsTrendChart"', false);
        $response->assertDontSee(__('No subscriptions yet.'));
        $response->assertDontSee(__('No revenue collected yet.'));
        $response->assertDontSee(__('No active subscriptions yet.'));
        $response->assertDontSee(__('No signups yet.'));
    }

    public function test_dashboard_shows_chart_empty_states_with_no_data(): void
    {
        $response = $this->actingAs($this->makeAdmin())->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertDontSee('id="subscriptionStatusChart"', false);
        $response->assertDontSee('id="revenueTrendChart"', false);
        $response->assertDontSee('id="planDistributionChart"', false);
        $response->assertDontSee('id="signupsTrendChart"', false);
        $response->assertSee(__('No subscriptions yet.'));
        $response->assertSee(__('No revenue collected yet.'));
        $response->assertSee(__('No active subscriptions yet.'));
        $response->assertSee(__('No signups yet.'));
    }

    public function test_dashboard_renders_in_arabic(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->get(route('locale.switch', 'ar'));

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(__('Subscription status'))
            ->assertSee(__('Revenue — last 6 months'));
    }
}
