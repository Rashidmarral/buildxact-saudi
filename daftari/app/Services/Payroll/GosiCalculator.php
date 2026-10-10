<?php

namespace App\Services\Payroll;

use App\Models\Employee;

/**
 * GOSI (General Organization for Social Insurance) employee/employer
 * contribution calculation, based on the branches GOSI publishes:
 * Annuities (pensions), SANED (unemployment insurance), and Occupational
 * Hazards. Saudi (and GCC) nationals are covered by all three; non-Saudi
 * employees are only covered by Occupational Hazards (employer-paid,
 * no employee contribution — expatriates aren't part of the pension
 * scheme).
 *
 * Rates and the contributory-wage ceiling below are current published
 * GOSI rates at the time this was written, applied to basic salary +
 * housing allowance (GOSI's "contributory wage" — other allowances like
 * transport aren't contributory). GOSI updates these by regulation from
 * time to time, and a company's registered occupational-hazard risk
 * category can shift the hazards rate — confirm current rates on your
 * GOSI portal before relying on this for real payroll.
 */
class GosiCalculator
{
    public const ANNUITIES_EMPLOYEE_RATE = 0.09;

    public const ANNUITIES_EMPLOYER_RATE = 0.09;

    public const SANED_EMPLOYEE_RATE = 0.0075;

    public const SANED_EMPLOYER_RATE = 0.0075;

    public const OCCUPATIONAL_HAZARDS_EMPLOYER_RATE = 0.02;

    public const CONTRIBUTORY_WAGE_CEILING = 45000.0;

    /**
     * @return array{contributory_wage: float, employee_contribution: float, employer_contribution: float}
     */
    public function calculate(Employee $employee): array
    {
        $contributoryWage = min(
            (float) $employee->basic_salary + (float) $employee->housing_allowance,
            self::CONTRIBUTORY_WAGE_CEILING
        );

        if (! $employee->is_saudi) {
            return [
                'contributory_wage' => round($contributoryWage, 2),
                'employee_contribution' => 0.0,
                'employer_contribution' => round($contributoryWage * self::OCCUPATIONAL_HAZARDS_EMPLOYER_RATE, 2),
            ];
        }

        $employeeRate = self::ANNUITIES_EMPLOYEE_RATE + self::SANED_EMPLOYEE_RATE;
        $employerRate = self::ANNUITIES_EMPLOYER_RATE + self::SANED_EMPLOYER_RATE + self::OCCUPATIONAL_HAZARDS_EMPLOYER_RATE;

        return [
            'contributory_wage' => round($contributoryWage, 2),
            'employee_contribution' => round($contributoryWage * $employeeRate, 2),
            'employer_contribution' => round($contributoryWage * $employerRate, 2),
        ];
    }
}
