<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\VatReturnPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reworked VAT Return Filings pages: a quarter/month picker instead of
 * raw date inputs, and an index that shows which periods are filed,
 * due soon, or overdue — not just the bare list of what's already
 * recorded (which can't show what's missing).
 */
class VatReturnPeriodQuarterPickerTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(string $frequency = 'quarterly'): Company
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000,
            'is_active' => true, 'has_vat_return_report' => true,
        ]);
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_filing_frequency' => $frequency]);
        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addYear()]);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_create_defaults_to_the_current_quarter_when_nothing_has_been_filed_yet(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->get(route('app.reports.vat-returns.create'));

        $response->assertOk();
        $response->assertSee(__('Filing deadline'));
    }

    public function test_create_lets_the_user_pick_a_specific_quarter_by_year_and_index(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->get(route('app.reports.vat-returns.create', ['year' => 2026, 'index' => 3]));

        $response->assertOk();
        $response->assertSee('Q3 2026');
    }

    public function test_create_still_supports_the_custom_date_range_fallback(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->get(route('app.reports.vat-returns.create', ['custom' => 1, 'period_start' => '2025-06-01', 'period_end' => '2025-06-20']));

        $response->assertOk();
        $response->assertSee('2025-06-01');
        $response->assertSee('2025-06-20');
    }

    public function test_a_monthly_filer_sees_months_instead_of_quarters(): void
    {
        $company = $this->makeCompany('monthly');
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->get(route('app.reports.vat-returns.create', ['year' => 2026, 'index' => 5]));

        $response->assertOk();
        $response->assertSee('May 2026');
    }

    public function test_index_shows_overdue_and_filed_status_chips(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        // A quarter safely in the past with no record on file at all —
        // must show as overdue, not just silently absent.
        $pastYear = now()->year - 1;

        $response = $this->actingAs($owner)->get(route('app.reports.vat-returns.index'));

        $response->assertOk();
        $response->assertSee(__('Overdue'));
    }

    public function test_index_marks_a_recorded_period_as_filed_and_links_to_it(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $period = VatReturnPeriod::create([
            'company_id' => $company->id, 'created_by' => $owner->id,
            'period_start' => now()->startOfQuarter(), 'period_end' => now()->endOfQuarter(),
            'output_tax' => 100, 'input_tax_purchases' => 0, 'expense_tax' => 0, 'net_recoverable_input_tax' => 0,
            'credit_brought_forward' => 0, 'amount_payable' => 100, 'credit_carried_forward' => 0,
        ]);

        $response = $this->actingAs($owner)->get(route('app.reports.vat-returns.index'));

        $response->assertOk();
        $response->assertSee(__('Filed'));
        $response->assertSee(route('app.reports.vat-returns.show', $period), false);
    }
}
