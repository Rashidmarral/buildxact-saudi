<?php

namespace Tests\Feature\Payroll;

use App\Models\Employee;
use App\Services\Payroll\GosiCalculator;
use Tests\TestCase;

class GosiCalculatorTest extends TestCase
{
    private function makeEmployee(array $overrides = []): Employee
    {
        return new Employee(array_merge([
            'basic_salary' => 8000,
            'housing_allowance' => 2000,
            'is_saudi' => true,
        ], $overrides));
    }

    public function test_a_saudi_employee_is_charged_annuities_and_saned_on_top_of_hazards(): void
    {
        $employee = $this->makeEmployee();

        $result = (new GosiCalculator)->calculate($employee);

        // Contributory wage: 8000 + 2000 = 10000.
        // Employee: (9% + 0.75%) * 10000 = 975.
        // Employer: (9% + 0.75% + 2%) * 10000 = 1175.
        $this->assertSame(10000.0, $result['contributory_wage']);
        $this->assertSame(975.0, $result['employee_contribution']);
        $this->assertSame(1175.0, $result['employer_contribution']);
    }

    public function test_a_non_saudi_employee_only_has_an_employer_paid_occupational_hazards_contribution(): void
    {
        $employee = $this->makeEmployee(['is_saudi' => false]);

        $result = (new GosiCalculator)->calculate($employee);

        $this->assertSame(0.0, $result['employee_contribution']);
        $this->assertSame(200.0, $result['employer_contribution']); // 2% of 10000
    }

    public function test_the_contributory_wage_is_capped_at_the_ceiling(): void
    {
        $employee = $this->makeEmployee(['basic_salary' => 60000, 'housing_allowance' => 10000]);

        $result = (new GosiCalculator)->calculate($employee);

        $this->assertSame(GosiCalculator::CONTRIBUTORY_WAGE_CEILING, $result['contributory_wage']);
    }

    public function test_transport_and_other_allowances_are_not_contributory(): void
    {
        $employee = $this->makeEmployee(['transport_allowance' => 5000, 'other_allowance' => 5000]);

        $result = (new GosiCalculator)->calculate($employee);

        $this->assertSame(10000.0, $result['contributory_wage']);
    }
}
