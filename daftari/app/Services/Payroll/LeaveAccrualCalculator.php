<?php

namespace App\Services\Payroll;

use Carbon\Carbon;

/**
 * Annual (paid) leave accrual under Saudi Labor Law (Royal Decree M/51),
 * Article 109: no less than 21 days per year for the first 5 years of
 * service, rising to 30 days per year from the start of the 5th year
 * onward. Accrued continuously (days employed × the applicable daily
 * rate) rather than credited in a lump sum each anniversary, the same
 * "blend across a partial period" treatment EndOfServiceCalculator
 * already uses for gratuity — a mid-year balance should reflect the
 * fraction of the year actually worked, not jump on the employee's
 * anniversary date.
 *
 * A pure calculator with no persistence of its own, same shape as
 * EndOfServiceCalculator — LeaveRequestService is what turns this into a
 * balance by subtracting approved leave already taken.
 */
class LeaveAccrualCalculator
{
    private const STANDARD_DAYS_PER_YEAR = 21;

    private const SENIOR_DAYS_PER_YEAR = 30;

    private const SENIORITY_THRESHOLD_YEARS = 5;

    public function accruedDays(Carbon $hireDate, Carbon $asOf): float
    {
        if ($asOf->lessThanOrEqualTo($hireDate)) {
            return 0.0;
        }

        // Whole days only — diffInDays() otherwise carries the current
        // time-of-day as a fraction (e.g. 365.48), which would make an
        // employee's accrued balance visibly tick up hour by hour rather
        // than day by day.
        $totalDays = (int) $hireDate->diffInDays($asOf);
        $thresholdDays = self::SENIORITY_THRESHOLD_YEARS * 365;

        $standardTenureDays = min($totalDays, $thresholdDays);
        $seniorTenureDays = max(0, $totalDays - $thresholdDays);

        $accrued = ($standardTenureDays * self::STANDARD_DAYS_PER_YEAR / 365)
            + ($seniorTenureDays * self::SENIOR_DAYS_PER_YEAR / 365);

        return round($accrued, 2);
    }
}
