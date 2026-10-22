<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per company per calendar month, counting API requests made
 * through a Sanctum token — the usage side of the "API calls" limit (see
 * LimitRegistry, EnsureWithinApiLimit).
 */
class ApiUsageCounter extends Model
{
    protected $fillable = ['company_id', 'period', 'count'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function currentPeriod(): string
    {
        return now()->format('Y-m');
    }

    public static function currentPeriodCount(int $companyId): int
    {
        return (int) (self::where('company_id', $companyId)->where('period', self::currentPeriod())->value('count') ?? 0);
    }

    /**
     * Atomically increments this month's counter for $companyId, creating
     * the row on first use, and returns the new total — used by
     * EnsureWithinApiLimit so two concurrent requests can't both read a
     * stale count and both slip in under the limit. Named recordCall()
     * rather than increment() to avoid colliding with Eloquent Model's own
     * (non-static) increment() method.
     */
    public static function recordCall(int $companyId): int
    {
        $period = self::currentPeriod();

        $counter = self::firstOrCreate(['company_id' => $companyId, 'period' => $period], ['count' => 0]);
        $counter->increment('count');

        return $counter->count;
    }
}
