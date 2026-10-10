<?php

namespace Tests\Unit;

use App\Models\Currency;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyMoneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Money caches Currency lookups in a static array for the life of
        // the process — RefreshDatabase resets the DB between tests but
        // not that cache, so a code reused across tests (e.g. 'SAR')
        // would otherwise return a stale, possibly since-deleted row.
        Money::clearCache();
    }

    public function test_money_format_uses_the_currency_own_decimal_places_and_separators(): void
    {
        Currency::create([
            'code' => 'KWD', 'name' => 'Kuwaiti Dinar', 'symbol' => 'KWD',
            'decimal_places' => 3, 'decimal_separator' => '.', 'thousands_separator' => ',',
            'symbol_position' => 'before', 'is_active' => true,
        ]);

        $this->assertSame('KWD 1,234.567', Money::format(1234.567, 'KWD'));
    }

    public function test_money_format_respects_symbol_position_after(): void
    {
        Currency::create([
            'code' => 'EUR', 'name' => 'Euro', 'symbol' => '€',
            'decimal_places' => 2, 'decimal_separator' => ',', 'thousands_separator' => '.',
            'symbol_position' => 'after', 'is_active' => true,
        ]);

        $this->assertSame('1.234,50 €', Money::format(1234.5, 'EUR'));
    }

    public function test_money_format_falls_back_to_two_decimals_when_currency_is_unknown(): void
    {
        $this->assertSame('XYZ 1,234.50', Money::format(1234.5, 'XYZ'));
    }

    public function test_money_uses_the_platform_default_currency_when_none_given(): void
    {
        Currency::create([
            'code' => 'SAR', 'name' => 'Saudi Riyal', 'symbol' => 'SAR',
            'decimal_places' => 2, 'decimal_separator' => '.', 'thousands_separator' => ',',
            'symbol_position' => 'before', 'is_active' => true, 'is_default' => true,
        ]);

        \App\Models\Setting::set('general_default_currency', 'SAR');

        $this->assertSame('SAR 500.00', Money::format(500));
    }

    public function test_make_default_unsets_the_previous_default(): void
    {
        $sar = Currency::create(['code' => 'SAR', 'name' => 'Saudi Riyal', 'symbol' => 'SAR', 'is_default' => true, 'is_active' => true]);
        $usd = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$']);

        $usd->makeDefault();

        $this->assertFalse($sar->fresh()->is_default);
        $this->assertTrue($usd->fresh()->is_default);
        $this->assertTrue($usd->fresh()->is_active, 'making a currency the default should also activate it');
    }

    public function test_active_scope_excludes_inactive_currencies(): void
    {
        Currency::create(['code' => 'SAR', 'name' => 'Saudi Riyal', 'symbol' => 'SAR', 'is_active' => true]);
        Currency::create(['code' => 'JOD', 'name' => 'Jordanian Dinar', 'symbol' => 'JOD', 'is_active' => false]);

        $codes = Currency::active()->pluck('code');

        $this->assertTrue($codes->contains('SAR'));
        $this->assertFalse($codes->contains('JOD'));
    }
}
