<?php

namespace Tests\Feature;

use App\Support\VatPeriods;
use Carbon\Carbon;
use Tests\TestCase;

class VatPeriodsHelperTest extends TestCase
{
    public function test_quarterly_periods_cover_the_whole_year_with_correct_due_dates(): void
    {
        $periods = VatPeriods::periodsForYear('quarterly', 2026);

        $this->assertCount(4, $periods);
        $this->assertSame('2026-01-01', $periods[0]['start']->toDateString());
        $this->assertSame('2026-03-31', $periods[0]['end']->toDateString());
        // ZATCA's rule: due by the last day of the month following the
        // period's end — for Q1 (ending 31 Mar) that's 30 Apr, not 31
        // May, which is what a naive addMonth() on a 31-day date would
        // produce via Carbon's month-end overflow.
        $this->assertSame('2026-04-30', $periods[0]['due_date']->toDateString());
        $this->assertSame('Q1 2026', $periods[0]['label_en']);

        $this->assertSame('2026-10-01', $periods[3]['start']->toDateString());
        $this->assertSame('2026-12-31', $periods[3]['end']->toDateString());
        $this->assertSame('2027-01-31', $periods[3]['due_date']->toDateString());
    }

    public function test_monthly_periods_cover_every_month_with_correct_due_dates(): void
    {
        $periods = VatPeriods::periodsForYear('monthly', 2026);

        $this->assertCount(12, $periods);
        $this->assertSame('2026-01-01', $periods[0]['start']->toDateString());
        $this->assertSame('2026-01-31', $periods[0]['end']->toDateString());
        // January (31 days) -> due last day of February, which is NOT
        // 31 days — the same overflow trap as the quarterly case above.
        $this->assertSame('2026-02-28', $periods[0]['due_date']->toDateString());
    }

    public function test_period_containing_finds_the_right_quarter(): void
    {
        $period = VatPeriods::periodContaining('quarterly', Carbon::create(2026, 8, 15));

        $this->assertSame('Q3 2026', $period['label_en']);
        $this->assertSame('2026-07-01', $period['start']->toDateString());
    }

    public function test_period_after_crosses_a_year_boundary(): void
    {
        $period = VatPeriods::periodAfter('quarterly', Carbon::create(2026, 12, 31));

        $this->assertSame('Q1 2027', $period['label_en']);
        $this->assertSame('2027-01-01', $period['start']->toDateString());
    }

    public function test_overdue_and_due_in_days_are_computed_relative_to_the_due_date(): void
    {
        $period = VatPeriods::periodsForYear('quarterly', 2026)[0]; // due 2026-04-30

        $this->assertFalse(VatPeriods::isOverdue($period, Carbon::create(2026, 4, 30)));
        $this->assertTrue(VatPeriods::isOverdue($period, Carbon::create(2026, 5, 1)));
        $this->assertSame(10, VatPeriods::dueInDays($period, Carbon::create(2026, 4, 20)));
        $this->assertSame(-5, VatPeriods::dueInDays($period, Carbon::create(2026, 5, 5)));
    }
}
