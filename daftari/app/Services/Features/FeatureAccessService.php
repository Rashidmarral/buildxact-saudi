<?php

namespace App\Services\Features;

use App\Models\Company;
use App\Models\CompanyOverride;
use App\Support\FeatureRegistry;

/**
 * The single place to ask "can $company use this module-level feature?"
 * (Module 07). See FeatureRegistry's docblock for how this differs from
 * the pre-existing Company::hasFeature() / Plan::FEATURE_KEYS system,
 * which this does not replace or touch.
 *
 * Order of precedence: a Super Admin override for this company always
 * wins; otherwise a 'core' feature is always on, a 'planned' one is
 * always off, and a 'gated' one reads its Plan column (or, for
 * multi_branch, is derived from the branches limit) — with a company
 * that has no active subscription never blocked, matching the existing
 * Company::hasFeature()'s same "unrestricted without a subscription"
 * behavior.
 */
class FeatureAccessService
{
    public function enabled(Company $company, string $key): bool
    {
        $override = $this->override($company, $key);

        if ($override !== null) {
            return $override;
        }

        $entry = FeatureRegistry::catalog()[$key] ?? null;

        if (! $entry) {
            return false;
        }

        return match ($entry['type']) {
            'core' => true,
            'planned' => false,
            'gated' => $this->gatedEnabled($company, $key, $entry),
            default => false,
        };
    }

    private function gatedEnabled(Company $company, string $key, array $entry): bool
    {
        $subscription = $company->activeSubscription();

        if (! $subscription) {
            return true;
        }

        $plan = $subscription->plan;

        if ($key === 'multi_branch') {
            return $plan->max_branches === null || $plan->max_branches > 1;
        }

        return $entry['column'] ? (bool) $plan->{$entry['column']} : false;
    }

    /**
     * Null when no override row exists (defer to the plan); otherwise the
     * admin's explicit true/false.
     */
    private function override(Company $company, string $key): ?bool
    {
        $override = CompanyOverride::where('company_id', $company->id)->where('type', 'feature')->where('key', $key)->first();

        return $override ? $override->value === '1' : null;
    }
}
