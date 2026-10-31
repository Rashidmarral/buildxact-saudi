<?php

namespace App\Services\Payroll;

use Carbon\Carbon;

/**
 * End-of-service gratuity under Saudi Labor Law (Royal Decree M/51),
 * Articles 84-87:
 *
 * Full gratuity: half a month's wage for each of the first 5 years of
 * service, and a full month's wage for each year beyond that (Art. 84),
 * based on the employee's last wage, prorated for a partial final year.
 *
 * When the employee resigns (Art. 85), the entitlement is scaled by
 * years of service: under 2 years = nothing; 2 to under 5 years = 1/3;
 * 5 to under 10 years = 2/3; 10+ years = full. Any other end of
 * employment (employer-initiated termination, end of a fixed-term
 * contract, retirement, death, disability) is entitled to the full
 * amount regardless of tenure (Art. 84/87) — this does not attempt to
 * model an Article 80 for-cause dismissal that forfeits gratuity
 * entirely, which HR should apply as a manual override when it applies.
 *
 * "Wage" here is the employee's last basic salary — the most common
 * practice and the safer default where the employment contract doesn't
 * specify total wage; some employers include housing/other allowances,
 * which callers can pass in instead if that's their contracted basis.
 */
class EndOfServiceCalculator
{
    public const REASON_RESIGNATION = 'resignation';

    public const REASON_TERMINATION = 'termination';

    public const REASON_END_OF_CONTRACT = 'end_of_contract';

    public const REASON_RETIREMENT = 'retirement';

    public const REASON_DEATH = 'death';

    public const REASON_DISABILITY = 'disability';

    /**
     * @return array{years_of_service: float, gratuity_days: float, entitlement_fraction: float, gratuity_amount: float}
     */
    public function calculate(float $lastWage, Carbon $hireDate, Carbon $terminationDate, string $reason): array
    {
        $totalDays = $hireDate->diffInDays($terminationDate);
        $yearsOfService = round($totalDays / 365, 4);

        $fullGratuityDays = $this->fullGratuityDays($yearsOfService);
        $entitlementFraction = $this->entitlementFraction($yearsOfService, $reason);
        $gratuityDays = round($fullGratuityDays * $entitlementFraction, 2);

        // A day's wage is the monthly wage over a 30-day convention —
        // the same convention this app already uses for payroll days
        // worked, and the one GOSI/MHRSD guidance uses for EOS.
        $dailyWage = $lastWage / 30;
        $gratuityAmount = round($gratuityDays * $dailyWage, 2);

        return [
            'years_of_service' => round($yearsOfService, 2),
            'gratuity_days' => $gratuityDays,
            'entitlement_fraction' => $entitlementFraction,
            'gratuity_amount' => $gratuityAmount,
        ];
    }

    /**
     * 15 days per year for each of the first 5 years, 30 days per year
     * after that — applied continuously across a partial year rather
     * than as a year-by-year step function, matching standard practice.
     */
    private function fullGratuityDays(float $yearsOfService): float
    {
        if ($yearsOfService <= 5) {
            return $yearsOfService * 15;
        }

        return (5 * 15) + (($yearsOfService - 5) * 30);
    }

    private function entitlementFraction(float $yearsOfService, string $reason): float
    {
        if ($reason !== self::REASON_RESIGNATION) {
            return 1.0;
        }

        return match (true) {
            $yearsOfService < 2 => 0.0,
            $yearsOfService < 5 => 1 / 3,
            $yearsOfService < 10 => 2 / 3,
            default => 1.0,
        };
    }
}
