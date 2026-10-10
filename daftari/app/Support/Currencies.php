<?php

namespace App\Support;

use App\Models\Currency;

/**
 * The currencies invoices/bills can be denominated in — sourced from the
 * admin-managed `currencies` table (Platform Settings → Currencies), not a
 * hardcoded list, so turning a currency on/off there is what actually
 * controls what shows up here. Every entry still needs a manually-entered
 * exchange rate on the document itself — there's no live FX feed wired in.
 */
class Currencies
{
    /**
     * @return array<string, string> code => "Name (CODE)"
     */
    public static function catalog(): array
    {
        return Currency::query()->active()->orderBy('sort_order')->get()
            ->mapWithKeys(fn (Currency $c) => [$c->code => "{$c->name} ({$c->code})"])
            ->all();
    }

    public static function codes(): array
    {
        return array_keys(self::catalog());
    }
}
