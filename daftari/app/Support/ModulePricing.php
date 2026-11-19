<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Display-only price tag for a gated FeatureRegistry module (Payroll,
 * POS, Restaurant, ...), shown on the company-facing Modules marketplace
 * (see ModuleRequestController). Storage convention mirrors
 * PlatformFeatureToggle: a thin naming wrapper over the existing
 * Setting key/value store rather than a new table. Purely informational
 * — it plays no part in FeatureAccessService::enabled()'s gating logic,
 * which is unaffected by whatever price is set here.
 */
class ModulePricing
{
    public static function settingKey(string $moduleKey): string
    {
        return "module_price_monthly_{$moduleKey}";
    }

    /**
     * Null means "no price set" (the marketplace then shows "Contact
     * sales" rather than a number) — the default, so introducing this
     * system doesn't invent a price for a module nobody has priced yet.
     */
    public static function get(string $moduleKey): ?float
    {
        $value = Setting::get(self::settingKey($moduleKey));

        return $value !== null && $value !== '' ? (float) $value : null;
    }

    public static function set(string $moduleKey, ?float $price): void
    {
        Setting::set(self::settingKey($moduleKey), $price !== null ? (string) $price : null);
    }
}
