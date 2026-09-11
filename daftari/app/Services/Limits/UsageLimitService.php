<?php

namespace App\Services\Limits;

use App\Models\Company;
use App\Models\CompanyOverride;
use App\Support\LimitRegistry;

/**
 * The single place to ask "how much of $key has $company used, what's its
 * cap, and has it hit the cap?" (Module 07). See LimitRegistry for the
 * catalog of the nine limit keys and their usage-counting logic.
 *
 * Order of precedence for the effective cap: a Super Admin override for
 * this company always wins (an override row with is_unlimited=true means
 * "no cap", one with a numeric value means "use this number instead of the
 * plan's"); otherwise the company's active plan's column value is used
 * (null = unlimited). A company with no active subscription is never
 * blocked, matching the pre-existing Company::hasReachedPlanLimit()
 * behavior it replaces.
 */
class UsageLimitService
{
    public function usage(Company $company, string $key): int
    {
        $entry = LimitRegistry::catalog()[$key] ?? null;

        if (! $entry) {
            return 0;
        }

        return ($entry['usage'])($company);
    }

    /**
     * The effective cap for $key, or null for unlimited.
     */
    public function limit(Company $company, string $key): ?int
    {
        $override = CompanyOverride::where('company_id', $company->id)
            ->where('type', 'limit')
            ->where('key', $key)
            ->first();

        if ($override) {
            return $override->is_unlimited ? null : (int) $override->value;
        }

        $subscription = $company->activeSubscription();

        if (! $subscription) {
            return null;
        }

        $entry = LimitRegistry::catalog()[$key] ?? null;

        if (! $entry) {
            return null;
        }

        return $subscription->plan->{$entry['column']};
    }

    /**
     * Remaining headroom before the cap, or null when unlimited.
     */
    public function remaining(Company $company, string $key): ?int
    {
        $limit = $this->limit($company, $key);

        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $this->usage($company, $key));
    }

    public function reached(Company $company, string $key): bool
    {
        $limit = $this->limit($company, $key);

        if ($limit === null) {
            return false;
        }

        return $this->usage($company, $key) >= $limit;
    }

    /**
     * A friendly, translatable "you've hit your plan limit" message for
     * $key, suitable for a validation error bag or an API error response.
     */
    public function friendlyMessage(Company $company, string $key): string
    {
        $label = LimitRegistry::catalog()[$key]['label'] ?? $key;

        return __("You've reached your plan's :label limit. Upgrade your plan or contact support to raise it.", ['label' => $label]);
    }
}
