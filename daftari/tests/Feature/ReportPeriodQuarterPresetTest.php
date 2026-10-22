<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "full control" over reports extends to picking any calendar quarter,
 * not just the current one — ResolvesReportPeriod's new 'quarter' and
 * 'last_quarter' presets, exercised through the Sales Report (the
 * simplest report using the shared period-selector.blade.php partial).
 */
class ReportPeriodQuarterPresetTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $company = Company::create(['name' => 'Period Co.', 'slug' => 'period-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);

        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_picking_a_specific_quarter_and_year_resolves_that_exact_range(): void
    {
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->get(route('app.reports.sales', ['period' => 'quarter', 'quarter' => 3, 'year' => 2025]));

        $response->assertOk();
        $response->assertSee('2025-07-01');
        $response->assertSee('2025-09-30');
    }

    public function test_last_quarter_resolves_to_the_calendar_quarter_before_the_current_one(): void
    {
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->get(route('app.reports.sales', ['period' => 'last_quarter']));

        $response->assertOk();

        $expected = \App\Support\VatPeriods::periodContaining('quarterly', now()->startOfQuarter()->subDay());
        $response->assertSee($expected['start']->toDateString());
        $response->assertSee($expected['end']->toDateString());
    }
}
