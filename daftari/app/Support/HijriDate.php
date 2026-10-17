<?php

namespace App\Support;

/**
 * Formats a Gregorian date as a Hijri (Umm al-Qura) one, e.g. "11 / 04 /
 * 1448" — the calendar Saudi government/business documents commonly show
 * alongside the Gregorian date. Built on PHP's intl extension (ICU's own
 * Umm al-Qura implementation), the same calculation Saudi institutions
 * use, rather than a hand-rolled conversion that could quietly drift from
 * the real calendar.
 *
 * ext-intl isn't guaranteed on every self-hosted install of this app, so
 * this returns null (never throws) when it's unavailable — a caller
 * simply omits the Hijri line rather than crashing PDF generation over a
 * missing optional extension.
 */
class HijriDate
{
    public static function format(\DateTimeInterface $date, string $pattern = 'dd / MM / yyyy'): ?string
    {
        if (! class_exists(\IntlDateFormatter::class)) {
            return null;
        }

        try {
            $formatter = new \IntlDateFormatter(
                'en_US@calendar=islamic-umalqura',
                \IntlDateFormatter::FULL,
                \IntlDateFormatter::NONE,
                'UTC',
                \IntlDateFormatter::TRADITIONAL,
                $pattern
            );

            $formatted = $formatter->format(new \DateTime($date->format('Y-m-d')));

            return $formatted !== false ? $formatted : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
