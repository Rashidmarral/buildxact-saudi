<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Renders a date/time using the admin-configured "Date format" / "Time
 * format" platform settings (Platform Settings → General), instead of a
 * format hardcoded per view. Falls back to d/m/Y and 24h when unset, so
 * a fresh install renders exactly as it did before those settings existed.
 *
 * Deliberately NOT used for anything that round-trips back into an HTML
 * `<input type="date">`/`<input type="time">` value — those must always
 * stay ISO (Y-m-d / H:i) per the HTML spec regardless of display
 * preference; this is for text the user only ever reads.
 */
class PlatformFormat
{
    public static function dateFormat(): string
    {
        return Setting::get('general_date_format', 'd/m/Y');
    }

    public static function timeFormat(): string
    {
        return Setting::get('general_time_format', '24h') === '12h' ? 'h:i A' : 'H:i';
    }

    public static function date(mixed $value): string
    {
        return static::resolve($value)?->format(static::dateFormat()) ?? '';
    }

    public static function time(mixed $value): string
    {
        return static::resolve($value)?->format(static::timeFormat()) ?? '';
    }

    public static function dateTime(mixed $value): string
    {
        $date = static::resolve($value);

        return $date ? $date->format(static::dateFormat().' '.static::timeFormat()) : '';
    }

    private static function resolve(mixed $value): ?CarbonInterface
    {
        if (! $value) {
            return null;
        }

        return $value instanceof CarbonInterface ? $value : Carbon::parse($value);
    }
}
