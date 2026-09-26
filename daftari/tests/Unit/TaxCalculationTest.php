<?php

namespace Tests\Unit;

use App\Models\InvoiceItem;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Verifies InvoiceItem::recalculate() — the actual arithmetic every sales
 * document (invoices, quotations, bills, credit notes, purchase
 * orders/returns) shares — for the three tax cases Module 09 calls out
 * (15%, 0%, exempt), plus rounding. This is a pure Unit test (no DB): the
 * formula was already reading vat_rate off the model, not a hardcoded
 * literal, before this module existed — these tests pin that behavior so
 * it can't regress while the module's Currency/TaxRate additions land
 * around it.
 */
class TaxCalculationTest extends TestCase
{
    public function test_standard_15_percent_vat_calculates_correctly(): void
    {
        $item = new InvoiceItem(['quantity' => 2, 'unit_price' => 100, 'vat_rate' => 15]);
        $item->recalculate();

        $this->assertSame(30.0, (float) $item->vat_amount);
        $this->assertSame(230.0, (float) $item->line_total);
    }

    public function test_zero_percent_vat_calculates_correctly(): void
    {
        // Zero-rated: still a taxable supply, but the rate itself is 0 —
        // vat_amount must be exactly 0.00, not null or omitted.
        $item = new InvoiceItem(['quantity' => 3, 'unit_price' => 50, 'vat_rate' => 0]);
        $item->recalculate();

        $this->assertSame(0.0, (float) $item->vat_amount);
        $this->assertSame(150.0, (float) $item->line_total);
    }

    public function test_exempt_calculates_the_same_zero_vat_as_zero_rated(): void
    {
        // At the arithmetic level exempt and zero-rated are identical
        // (both 0% -> 0.00 VAT) — what tells them apart is the TaxRate's
        // `type`, tested separately in TaxRateTest and the ZATCA XML
        // category test, not the vat_amount math itself.
        $item = new InvoiceItem(['quantity' => 1, 'unit_price' => 200, 'vat_rate' => 0]);
        $item->recalculate();

        $this->assertSame(0.0, (float) $item->vat_amount);
        $this->assertSame(200.0, (float) $item->line_total);
    }

    #[DataProvider('roundingCases')]
    public function test_rounding_matches_standard_two_decimal_rounding(float $quantity, float $unitPrice, float $vatRate, float $expectedVat, float $expectedTotal): void
    {
        $item = new InvoiceItem(['quantity' => $quantity, 'unit_price' => $unitPrice, 'vat_rate' => $vatRate]);
        $item->recalculate();

        $this->assertSame($expectedVat, (float) $item->vat_amount);
        $this->assertSame($expectedTotal, (float) $item->line_total);
    }

    public static function roundingCases(): array
    {
        return [
            // qty, unit_price, vat_rate, expected vat_amount, expected line_total
            'exact half-cent rounds up' => [1, 33.335, 15, 5.0, 38.34],
            'repeating decimal' => [3, 10.333, 15, 4.65, 35.65],
            'small odd amount' => [7, 1.11, 15, 1.17, 8.94],
            'single unit standard price' => [1, 99.99, 15, 15.0, 114.99],
        ];
    }

    public function test_vat_rate_never_defaults_to_a_hardcoded_fifteen_in_the_model(): void
    {
        // Guards against a regression back to a hardcoded fallback: with
        // no vat_rate given at all, Eloquent's own attribute default (null
        // -> cast to 0 by the calculation) applies, never a silent 15.
        $item = new InvoiceItem(['quantity' => 1, 'unit_price' => 100]);
        $item->recalculate();

        $this->assertSame(0.0, (float) $item->vat_amount);
    }
}
