<?php

namespace Tests\Feature\Payroll;

use App\Services\Payroll\LeaveAccrualCalculator;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Core accounting audit finding: no leave/annual-leave accrual tracking
 * existed at all, despite Saudi Labor Law (Article 109) mandating it —
 * conspicuous given how complete the rest of payroll (GOSI, End-of-
 * Service, WPS) already was. Mirrors EndOfServiceCalculatorTest's own
 * style: a pure, stateless calculator, tested against hand-derived
 * numbers for the 21-day and post-5-year 30-day rates blended
 * continuously across the boundary.
 */
class LeaveAccrualCalculatorTest extends TestCase
{
    private LeaveAccrualCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new LeaveAccrualCalculator;
    }

    public function test_no_leave_accrues_on_the_hire_date_itself(): void
    {
        $hireDate = Carbon::parse('2026-01-01');

        $this->assertSame(0.0, $this->calculator->accruedDays($hireDate, $hireDate));
    }

    public function test_an_as_of_date_before_hire_accrues_nothing(): void
    {
        $hireDate = Carbon::parse('2026-01-01');

        $this->assertSame(0.0, $this->calculator->accruedDays($hireDate, $hireDate->copy()->subYear()));
    }

    public function test_one_full_year_accrues_21_days(): void
    {
        $hireDate = Carbon::parse('2025-01-01');
        $asOf = Carbon::parse('2026-01-01');

        $this->assertSame(21.0, $this->calculator->accruedDays($hireDate, $asOf));
    }

    public function test_half_a_year_accrues_roughly_half_the_annual_rate(): void
    {
        $hireDate = Carbon::parse('2026-01-01');
        $asOf = Carbon::parse('2026-07-02'); // ~182 days

        $accrued = $this->calculator->accruedDays($hireDate, $asOf);

        $this->assertEqualsWithDelta(10.5, $accrued, 0.1);
    }

    public function test_exactly_five_years_accrues_at_the_standard_rate_throughout(): void
    {
        $hireDate = Carbon::parse('2021-01-01');
        $asOf = $hireDate->copy()->addDays(5 * 365);

        $this->assertSame(105.0, $this->calculator->accruedDays($hireDate, $asOf)); // 5 * 21
    }

    public function test_service_beyond_five_years_blends_in_the_30_day_rate(): void
    {
        $hireDate = Carbon::parse('2020-01-01');
        $asOf = $hireDate->copy()->addDays((5 * 365) + 365); // 6 years

        // 5 years at 21/year (105) + 1 year at 30/year (30) = 135
        $this->assertSame(135.0, $this->calculator->accruedDays($hireDate, $asOf));
    }
}
