<?php

namespace App\Support;

/**
 * The platform's supported UI locales — English and Arabic today, but
 * every consumer (SetLocale middleware, the language switcher, every
 * layout's `dir` attribute) reads this list instead of hardcoding 'en'/
 * 'ar', so adding a third language later is a one-line change here, not a
 * hunt through every layout for `=== 'ar'`.
 */
class Locales
{
    public const LIST = [
        'en' => ['label' => 'English', 'native' => 'English', 'dir' => 'ltr'],
        'ar' => ['label' => 'Arabic', 'native' => 'العربية', 'dir' => 'rtl'],
    ];

    public static function codes(): array
    {
        return array_keys(self::LIST);
    }

    public static function isValid(?string $locale): bool
    {
        return $locale && array_key_exists($locale, self::LIST);
    }

    public static function isRtl(?string $locale): bool
    {
        return (self::LIST[$locale] ?? self::LIST['en'])['dir'] === 'rtl';
    }

    public static function dir(?string $locale): string
    {
        return (self::LIST[$locale] ?? self::LIST['en'])['dir'];
    }

    public static function nativeName(?string $locale): string
    {
        return (self::LIST[$locale] ?? self::LIST['en'])['native'];
    }
}
