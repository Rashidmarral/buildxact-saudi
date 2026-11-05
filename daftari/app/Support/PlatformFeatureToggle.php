<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Storage convention for the platform-wide master switch on each 'gated'
 * FeatureRegistry entry — see FeatureRegistry's docblock. Deliberately
 * just a thin naming convention over the existing Setting key/value
 * store (matching every other platform-wide toggle in this app, e.g.
 * maintenance mode, mail, storage) rather than a new table.
 */
class PlatformFeatureToggle
{
    public static function settingKey(string $featureKey): string
    {
        return "platform_feature_enabled_{$featureKey}";
    }

    /**
     * Defaults to true (enabled) so introducing this system doesn't
     * silently switch off any feature a company already has via its plan
     * — a super admin has to make an explicit choice to turn one off.
     */
    public static function isEnabled(string $featureKey): bool
    {
        return Setting::getBool(self::settingKey($featureKey), true);
    }

    public static function setEnabled(string $featureKey, bool $enabled): void
    {
        Setting::set(self::settingKey($featureKey), $enabled ? '1' : '0');
    }
}
