<?php

namespace App\Support;

use App\Models\Currency;
use App\Models\Setting;

/**
 * Formats an amount using a Currency record's own rules (decimal places,
 * decimal/thousands separators, symbol, symbol position) instead of the
 * "SAR " + number_format($x, 2) literal repeated across the app. Every
 * currency this formats through comes from the admin-managed `currencies`
 * table (Platform Settings → Currencies) — nothing here hardcodes SAR;
 * it's simply the seeded default currency, swappable like any other.
 */
class Money
{
    /** @var array<string, Currency> */
    private static array $cache = [];

    public static function format(float|int|string $amount, ?string $currencyCode = null, ?int $decimalsOverride = null): string
    {
        $amount = (float) $amount;
        $currency = static::resolve($currencyCode);

        $decimals = $decimalsOverride ?? ($currency?->decimal_places ?? 2);
        $decimalSep = $currency?->decimal_separator ?? '.';
        $thousandsSep = $currency?->thousands_separator ?? ',';
        $symbol = $currency?->symbol ?? ($currencyCode ?: static::defaultCode());
        $position = $currency?->symbol_position ?? 'before';

        $number = number_format($amount, $decimals, $decimalSep, $thousandsSep);

        return $position === 'after' ? "{$number} {$symbol}" : "{$symbol} {$number}";
    }

    /**
     * Just the number, formatted per the currency's own decimal/thousands
     * separators — no symbol. For places that already render the symbol
     * separately (e.g. a table column header labeled "Amount (SAR)").
     */
    public static function number(float|int|string $amount, ?string $currencyCode = null, ?int $decimalsOverride = null): string
    {
        $amount = (float) $amount;
        $currency = static::resolve($currencyCode);

        $decimals = $decimalsOverride ?? ($currency?->decimal_places ?? 2);

        return number_format($amount, $decimals, $currency?->decimal_separator ?? '.', $currency?->thousands_separator ?? ',');
    }

    /**
     * A document's "Balance due" extra_row — or, once payments exceed the
     * total, "Overpaid" instead. balanceDue() (Invoice/Bill/etc.) returns a
     * signed value that goes negative once payments exceed the total;
     * rendering that raw negative straight into "Balance due: -X" reads as
     * a diminished debt rather than what it actually is, a credit in the
     * payer's favor — confusing on a printed/emailed document.
     */
    public static function balanceRow(float $balanceDue): array
    {
        if ($balanceDue < -0.004) {
            return ['label' => __('Overpaid'), 'value' => abs($balanceDue), 'emphasis' => true, 'variant' => 'green'];
        }

        return [
            'label' => __('Balance due'),
            'value' => max($balanceDue, 0.0),
            'emphasis' => true,
            'variant' => $balanceDue > 0.004 ? 'red' : null,
        ];
    }

    public static function symbol(?string $currencyCode = null): string
    {
        return static::resolve($currencyCode)?->symbol ?? ($currencyCode ?: static::defaultCode());
    }

    public static function defaultCode(): string
    {
        return Setting::get('general_default_currency', config('daftari.default_currency', 'SAR'));
    }

    /**
     * Clears the in-memory currency lookup cache. Only needed where the
     * underlying `currencies` rows can change out from under an
     * already-running process — namely tests, which reset the database
     * between cases but not this class's static state.
     */
    public static function clearCache(): void
    {
        static::$cache = [];
    }

    private static function resolve(?string $code): ?Currency
    {
        $code = $code ?: static::defaultCode();

        if (array_key_exists($code, static::$cache)) {
            return static::$cache[$code];
        }

        try {
            return static::$cache[$code] = Currency::query()->where('code', $code)->first();
        } catch (\Throwable) {
            // currencies table not migrated yet (fresh install running an
            // early artisan command) — fall back to hardcoded 2dp/./, so
            // callers never see a crash, just the pre-Currency-table look.
            return static::$cache[$code] = null;
        }
    }
}
