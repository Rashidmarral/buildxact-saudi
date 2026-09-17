<?php

namespace Tests\Feature;

use App\Support\Money;
use Tests\TestCase;

/**
 * balanceDue() (Invoice/Bill/etc.) returns a signed value that goes
 * negative once payments exceed the total — rendering that raw negative
 * straight into "Balance due: -X" on a printed/emailed document reads as
 * a diminished debt rather than what it actually is: a credit in the
 * payer's favor. Money::balanceRow() is the shared fix, used everywhere
 * a document builds its "Balance due" extra_row.
 */
class MoneyBalanceRowTest extends TestCase
{
    public function test_a_positive_balance_is_labelled_balance_due_in_red(): void
    {
        $row = Money::balanceRow(2062.50);

        $this->assertSame(__('Balance due'), $row['label']);
        $this->assertEqualsWithDelta(2062.50, $row['value'], 0.001);
        $this->assertSame('red', $row['variant']);
    }

    public function test_a_zero_balance_is_labelled_balance_due_with_no_variant(): void
    {
        $row = Money::balanceRow(0.0);

        $this->assertSame(__('Balance due'), $row['label']);
        $this->assertSame(0.0, $row['value']);
        $this->assertNull($row['variant']);
    }

    /**
     * The exact scenario reported: total 15,812.50, paid 17,875.00, so
     * balanceDue() returns -2,062.50 — this must render as "Overpaid
     * 2,062.50", not "Balance due -2,062.50".
     */
    public function test_a_negative_balance_is_labelled_overpaid_with_a_positive_value(): void
    {
        $row = Money::balanceRow(15812.50 - 17875.00);

        $this->assertSame(__('Overpaid'), $row['label']);
        $this->assertEqualsWithDelta(2062.50, $row['value'], 0.001);
        $this->assertSame('green', $row['variant']);
    }
}
