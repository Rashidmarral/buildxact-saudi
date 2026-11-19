<?php

namespace App\Support;

use Carbon\Carbon as BaseCarbon;
use Illuminate\Support\Carbon;

/**
 * Saudi VAT return periods — quarterly (the default ZATCA filing cycle
 * for most businesses) or monthly (mandatory once a company's taxable
 * supplies cross ZATCA's large-taxpayer revenue threshold), driven by
 * Company::vat_filing_frequency. Centralizes the one rule
 * VatReturnPeriodController and the report period picker both need: a
 * VAT return's due date is always the last day of the month following
 * the end of the tax period — a calendar-quarter-aware version of the
 * `addMonths(3)` approximation RecurringInvoice/RecurringExpense use for
 * their own, unrelated "quarterly" recurrence.
 */
class VatPeriods
{
    private const AR_MONTHS = [
        1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل', 5 => 'مايو', 6 => 'يونيو',
        7 => 'يوليو', 8 => 'أغسطس', 9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
    ];

    private const AR_QUARTERS = [1 => 'الربع الأول', 2 => 'الربع الثاني', 3 => 'الربع الثالث', 4 => 'الربع الرابع'];

    /**
     * Every period of $year for the given frequency, in order.
     *
     * @return array<int, array{index: int, label_en: string, label_ar: string, start: Carbon, end: Carbon, due_date: Carbon}>
     */
    public static function periodsForYear(string $frequency, int $year): array
    {
        $count = $frequency === 'monthly' ? 12 : 4;
        $periods = [];

        for ($i = 1; $i <= $count; $i++) {
            $start = $frequency === 'monthly'
                ? Carbon::create($year, $i, 1)->startOfMonth()
                : Carbon::create($year, ($i - 1) * 3 + 1, 1)->startOfQuarter();

            $end = $frequency === 'monthly' ? $start->copy()->endOfMonth() : $start->copy()->endOfQuarter();

            $periods[] = [
                'index' => $i,
                'label_en' => $frequency === 'monthly' ? $start->format('F Y') : 'Q'.$i.' '.$year,
                'label_ar' => $frequency === 'monthly' ? self::AR_MONTHS[$i].' '.$year : self::AR_QUARTERS[$i].' '.$year,
                'start' => $start->copy()->startOfDay(),
                'end' => $end->copy()->endOfDay(),
                // ZATCA's own rule: the return and payment are due by the
                // last day of the month following the tax period's end.
                // Anchored to the 1st before adding a month so a 31-day
                // end (e.g. 31 Mar/31 Jan) can't overflow Carbon into the
                // month after next (31 Mar + 1 month would otherwise land
                // on 1 May, not 30 Apr, since April has no 31st).
                'due_date' => $end->copy()->startOfMonth()->addMonth()->endOfMonth()->startOfDay(),
            ];
        }

        return $periods;
    }

    /**
     * The period $date falls inside, for the given frequency.
     *
     * @return array{index: int, label_en: string, label_ar: string, start: Carbon, end: Carbon, due_date: Carbon}
     */
    public static function periodContaining(string $frequency, BaseCarbon $date): array
    {
        $periods = self::periodsForYear($frequency, $date->year);

        foreach ($periods as $period) {
            if (Carbon::instance($date)->betweenIncluded($period['start'], $period['end'])) {
                return $period;
            }
        }

        // Unreachable in practice (every date falls in exactly one
        // period of its own year), but keeps the return type honest.
        return $periods[0];
    }

    /**
     * The period immediately after $afterDate (e.g. the day after the
     * last recorded VatReturnPeriod's end) — spans a year boundary
     * correctly (Q4 2026 -> Q1 2027, December 2026 -> January 2027).
     */
    public static function periodAfter(string $frequency, BaseCarbon $afterDate): array
    {
        $candidate = Carbon::instance($afterDate)->copy()->addDay();

        return self::periodContaining($frequency, $candidate);
    }

    /**
     * True once $period's due date has passed and it's still unfiled —
     * callers decide "unfiled" themselves (this only knows about dates).
     */
    public static function isOverdue(array $period, ?BaseCarbon $now = null): bool
    {
        return Carbon::instance($now ?? now())->startOfDay()->gt($period['due_date']);
    }

    /**
     * Days until $period is due — negative once it's overdue.
     */
    public static function dueInDays(array $period, ?BaseCarbon $now = null): int
    {
        $now = Carbon::instance($now ?? now())->copy()->startOfDay();

        return (int) $now->diffInDays($period['due_date'], false);
    }
}
