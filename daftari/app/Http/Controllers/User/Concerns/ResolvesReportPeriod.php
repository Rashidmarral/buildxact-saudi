<?php

namespace App\Http\Controllers\User\Concerns;

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

        [$from, $to] = match ($preset) {
            'this_month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [$today->copy()->startOfQuarter(), $today->copy()->endOfQuarter()],
            'last_year' => [$today->copy()->subYear()->startOfYear(), $today->copy()->subYear()->endOfYear()],
            'custom' => [
                $request->filled('from') ? Carbon::parse($request->query('from')) : $today->copy()->startOfYear(),
                $request->filled('to') ? Carbon::parse($request->query('to')) : $today->copy(),
            ],
            default => [$today->copy()->startOfYear(), $today->copy()],
        };

        return ['preset' => $preset, 'from' => $from->startOfDay(), 'to' => $to->endOfDay()];
    }
}
