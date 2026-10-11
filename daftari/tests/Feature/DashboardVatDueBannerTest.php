<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\VatReturnPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * "manage vat easily" — the dashboard should surface a due/overdue VAT
 * period on its own rather than making the user remember to check the
 * Reports section, exactly as it already does for the onboarding
 * checklist.
 */
class DashboardVatDueBannerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeCompany(array $overrides = []): Company
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000,
            'is_active' => true, 'has_vat_return_report' => true,
        ]);
        $company = Company::create(array_merge(['name' => 'Dashboard VAT Co.', 'slug' => 'dvat-'.uniqid(), 'status' => 'active', 'currency' => 'SAR'], $overrides));
        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addYear()]);

        return $company;
    }

    public function test_the_banner_does_not_show_when_the_current_period_is_already_filed(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        VatReturnPeriod::create([
            'company_id' => $company->id, 'created_by' => $owner->id,
            'period_start' => now()->startOfQuarter(), 'period_end' => now()->endOfQuarter(),
            'output_tax' => 0, 'input_tax_purchases' => 0, 'expense_tax' => 0, 'net_recoverable_input_tax' => 0,
            'credit_brought_forward' => 0, 'amount_payable' => 0, 'credit_carried_forward' => 0,
        ]);

        $response = $this->actingAs($owner)->get(route('app.dashboard'));

        $response->assertOk();
        $response->assertDontSee(__('Record it'));
    }

    public function test_the_banner_does_not_show_for_a_company_without_the_feature(): void
    {
        // A company with no plan/subscription at all gets hasFeature()
        // = true for everything (Company::hasFeature()'s "never
        // subscribed yet" fallback) — a real "without the feature"
        // company has an active subscription to a plan that disables
        // it, same as VatReturnPeriodTest::makeCompany(withFeature:
        // false).
        $plan = Plan::create(['name' => 'Basic', 'slug' => 'basic-'.uniqid(), 'price_monthly' => 0, 'price_yearly' => 0, 'is_active' => true, 'has_vat_return_report' => false]);
        $company = Company::create(['name' => 'No Feature Co.', 'slug' => 'nf-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addYear()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $response = $this->actingAs($owner)->get(route('app.dashboard'));

        $response->assertOk();
        $response->assertDontSee(__('Record it'));
    }

    public function test_the_banner_shows_overdue_when_the_current_periods_due_date_has_passed_unfiled(): void
    {
        // Q1 2026 (Jan-Mar) is due 30 Apr 2026 — landing "now" a few days
        // after that, with nothing recorded, must show the overdue banner.
        Carbon::setTestNow(Carbon::create(2026, 5, 3));

        $company = $this->makeCompany();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $response = $this->actingAs($owner)->get(route('app.dashboard'));

        $response->assertOk();
        $response->assertSee(__('Record it'));
        $response->assertSee('is overdue', false);

        Carbon::setTestNow();
    }

    public function test_the_banner_shows_due_soon_within_15_days_of_the_deadline(): void
    {
        // Due 30 Apr 2026 — 10 days out on 20 Apr falls inside the
        // 15-day due-soon window.
        Carbon::setTestNow(Carbon::create(2026, 4, 20));

        $company = $this->makeCompany();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $response = $this->actingAs($owner)->get(route('app.dashboard'));

        $response->assertOk();
        $response->assertSee(__('Record it'));
        $response->assertSee('is due by', false);

        Carbon::setTestNow();
    }
}
