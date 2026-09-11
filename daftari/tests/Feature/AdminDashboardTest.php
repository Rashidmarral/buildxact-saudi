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
}
