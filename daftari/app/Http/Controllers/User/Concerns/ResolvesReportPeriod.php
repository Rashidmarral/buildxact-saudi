<?php

namespace App\Http\Controllers\User\Concerns;

use App\Models\Setting;
use App\Support\VatPeriods;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

trait ResolvesReportPeriod
{
    /**
     * @return array{preset: string, from: Carbon, to: Carbon, quarter: ?int, year: ?int}
     */
    private function resolvePeriod(Request $request): array
    {
        $preset = $request->query('period', 'this_year');
        $today = now();
        [$fyStart, $fyEnd] = $this->fiscalYearBounds($today);

        $quarter = null;
        $year = null;

        [$from, $to] = match ($preset) {
            'this_month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [$today->copy()->startOfQuarter(), $today->copy()->endOfQuarter()],
            'last_quarter' => (function () use ($today) {
                $period = VatPeriods::periodContaining('quarterly', $today->copy()->startOfQuarter()->subDay());

                return [$period['start'], $period['end']];
            })(),
            // A specific calendar quarter (any year) — "the easier way
            // to fulfil VAT by quarter" extends to every report that
            // shares this same period selector, not just the VAT
            // return tracker's own wizard.
            'quarter' => (function () use ($request, $today, &$quarter, &$year) {
                $year = (int) $request->query('year', $today->year);
                $quarter = max(1, min(4, (int) $request->query('quarter', $today->quarter)));
                $period = VatPeriods::periodsForYear('quarterly', $year)[$quarter - 1];

                return [$period['start'], $period['end']];
            })(),
            'last_year' => [$fyStart->copy()->subYear(), $fyEnd->copy()->subYear()],
            'custom' => [
                $request->filled('from') ? Carbon::parse($request->query('from')) : $fyStart->copy(),
                $request->filled('to') ? Carbon::parse($request->query('to')) : $today->copy(),
            ],
            default => [$fyStart, $fyEnd],
        };

        return ['preset' => $preset, 'from' => $from->startOfDay(), 'to' => $to->endOfDay(), 'quarter' => $quarter, 'year' => $year];
    }

    /**
     * "add more detailed reports... compare" — the comparison range for
     * a report's "Compare with" option, matching how Zoho/QuickBooks/
     * Xero let you put last period or last year's figures side by side
     * with the current ones. 'prior_period' is the immediately
     * preceding stretch of the same length (e.g. comparing October to
     * September when the selected period is October); 'prior_year' is
     * the exact same dates one year earlier. Returns null for 'none' or
     * an unrecognized value, so the caller can skip the comparison pass
     * entirely rather than querying a range nobody asked for.
     *
     * @return array{from: Carbon, to: Carbon}|null
     */
    private function comparisonPeriod(array $period, string $compare): ?array
    {
        return match ($compare) {
            'prior_period' => (function () use ($period) {
                $days = $period['from']->diffInDays($period['to']) + 1;
                $to = $period['from']->copy()->subDay()->endOfDay();

                return ['from' => $to->copy()->subDays($days - 1)->startOfDay(), 'to' => $to];
            })(),
            'prior_year' => ['from' => $period['from']->copy()->subYear(), 'to' => $period['to']->copy()->subYear()],
            default => null,
        };
    }

    /**
     * The comparison date for a point-in-time report (Balance Sheet's
     * "as of" snapshot, which has no period length to mirror).
     * 'prior_period' here means the end of the previous calendar month
     * (the usual "how did this month-end compare to last month-end");
     * 'prior_year' is the exact same date one year earlier.
     */
    private function comparisonAsOf(Carbon $asOf, string $compare): ?Carbon
    {
        return match ($compare) {
            'prior_period' => $asOf->copy()->startOfMonth()->subDay()->endOfDay(),
            'prior_year' => $asOf->copy()->subYear(),
            default => null,
        };
    }

    /**
     * The fiscal year (start, end) that $reference falls inside, per the
     * company's own "Fiscal year start" override if it has set one
     * (Settings → Regional preferences), else the platform-wide "Fiscal
     * year start" setting (a month, 1-12; default January — i.e. the
     * calendar year, so an install with neither set behaves exactly as
     * before either setting existed). A start of e.g. April means the
     * fiscal year containing a January date began the previous April.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function fiscalYearBounds(Carbon $reference): array
    {
        $startMonth = (int) (Auth::user()?->company?->fiscal_year_start ?? Setting::get('general_fiscal_year_start', 1));
        $startMonth = $startMonth >= 1 && $startMonth <= 12 ? $startMonth : 1;

        $start = $reference->copy()->startOfMonth()->month($startMonth);

        if ($reference->month < $startMonth) {
            $start->subYear();
        }

        return [$start, $start->copy()->addYear()->subDay()];
    }
}
