<?php

namespace App\Http\Controllers\User\Concerns;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;

trait ResolvesReportPeriod
{
    /**
     * @return array{preset: string, from: Carbon, to: Carbon}
     */
    private function resolvePeriod(Request $request): array
    {
        $preset = $request->query('period', 'this_year');
        $today = now();
        [$fyStart, $fyEnd] = $this->fiscalYearBounds($today);

        [$from, $to] = match ($preset) {
            'this_month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [$today->copy()->startOfQuarter(), $today->copy()->endOfQuarter()],
            'last_year' => [$fyStart->copy()->subYear(), $fyEnd->copy()->subYear()],
            'custom' => [
                $request->filled('from') ? Carbon::parse($request->query('from')) : $fyStart->copy(),
                $request->filled('to') ? Carbon::parse($request->query('to')) : $today->copy(),
            ],
            default => [$fyStart, $fyEnd],
        };

        return ['preset' => $preset, 'from' => $from->startOfDay(), 'to' => $to->endOfDay()];
    }

    /**
     * The fiscal year (start, end) that $reference falls inside, per the
     * "Fiscal year start" platform setting (a month, 1-12; default January
     * — i.e. the calendar year, so unset behaves exactly as before this
     * setting existed). A start of e.g. April means the fiscal year
     * containing a January date began the previous April.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function fiscalYearBounds(Carbon $reference): array
    {
        $startMonth = (int) Setting::get('general_fiscal_year_start', 1);
        $startMonth = $startMonth >= 1 && $startMonth <= 12 ? $startMonth : 1;

        $start = $reference->copy()->startOfMonth()->month($startMonth);

        if ($reference->month < $startMonth) {
            $start->subYear();
        }

        return [$start, $start->copy()->addYear()->subDay()];
    }
}
