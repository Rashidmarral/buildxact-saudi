<?php

namespace Tests\Feature\Payroll;

use App\Services\Payroll\EndOfServiceCalculator;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EndOfServiceCalculatorTest extends TestCase
{
    private EndOfServiceCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new EndOfServiceCalculator;
    }

    public function test_a_resignation_under_two_years_gets_no_gratuity(): void
    {
        $result = $this->calculator->calculate(
            10000,
            Carbon::parse('2024-01-01'),
            Carbon::parse('2025-06-01'),
            EndOfServiceCalculator::REASON_RESIGNATION
        );

        $this->assertSame(0.0, $result['entitlement_fraction']);
        $this->assertSame(0.0, $result['gratuity_amount']);
    }

    public function test_a_resignation_between_two_and_five_years_gets_a_third(): void
    {
        $result = $this->calculator->calculate(
            10000,
            Carbon::parse('2020-01-01'),
            Carbon::parse('2023-01-01'), // 3 years
            EndOfServiceCalculator::REASON_RESIGNATION
        );

        // Full gratuity for 3 years: 3 * 15 = 45 days. At 1/3 = 15 days.
        $this->assertEqualsWithDelta(1 / 3, $result['entitlement_fraction'], 0.001);
        $this->assertEqualsWithDelta(15.0, $result['gratuity_days'], 0.5);
    }

    public function test_a_resignation_between_five_and_ten_years_gets_two_thirds(): void
    {
        $result = $this->calculator->calculate(
            10000,
            Carbon::parse('2017-01-01'),
            Carbon::parse('2024-01-01'), // 7 years
            EndOfServiceCalculator::REASON_RESIGNATION
        );

        $this->assertEqualsWithDelta(2 / 3, $result['entitlement_fraction'], 0.001);
    }

    public function test_a_resignation_at_ten_years_or_more_gets_full_gratuity(): void
    {
        $result = $this->calculator->calculate(
            10000,
            Carbon::parse('2010-01-01'),
            Carbon::parse('2024-01-01'), // 14 years
            EndOfServiceCalculator::REASON_RESIGNATION
        );

        $this->assertSame(1.0, $result['entitlement_fraction']);
    }

    public function test_an_employer_termination_gets_full_gratuity_regardless_of_tenure(): void
    {
        $result = $this->calculator->calculate(
            10000,
            Carbon::parse('2024-01-01'),
            Carbon::parse('2024-06-01'), // under 1 year
            EndOfServiceCalculator::REASON_TERMINATION
        );

        $this->assertSame(1.0, $result['entitlement_fraction']);
        $this->assertGreaterThan(0, $result['gratuity_amount']);
    }

    public function test_full_gratuity_uses_half_a_month_per_year_for_the_first_five_years(): void
    {
        $result = $this->calculator->calculate(
            9000, // 300/day at a 30-day month
            Carbon::parse('2019-01-01'),
            Carbon::parse('2024-01-01'), // 5 years
            EndOfServiceCalculator::REASON_TERMINATION
        );

        // 5 years * 15 days = 75 days * 300/day = 22500.
        $this->assertEqualsWithDelta(75.0, $result['gratuity_days'], 1.0);
        $this->assertEqualsWithDelta(22500.0, $result['gratuity_amount'], 500.0);
    }

    public function test_full_gratuity_uses_a_full_month_per_year_beyond_five_years(): void
    {
        $result = $this->calculator->calculate(
            9000,
            Carbon::parse('2014-01-01'),
            Carbon::parse('2024-01-01'), // 10 years
            EndOfServiceCalculator::REASON_TERMINATION
        );

        // 5 years * 15 + 5 years * 30 = 75 + 150 = 225 days.
        $this->assertEqualsWithDelta(225.0, $result['gratuity_days'], 1.0);
    }
}
