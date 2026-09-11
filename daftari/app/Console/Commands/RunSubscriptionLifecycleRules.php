<?php

namespace App\Console\Commands;

use App\Services\Subscriptions\SubscriptionLifecycleService;
use Illuminate\Console\Command;

/**
 * Runs the automatic subscription dunning ladder daily — see
 * SubscriptionLifecycleService's class docblock for the full state
 * machine. Order matters: each step only picks up subscriptions the
 * previous step already moved into the right starting status, so running
 * them in sequence lets a subscription that's very overdue cascade through
 * more than one stage in a single run (e.g. after the app was offline for
 * a while) rather than waiting one day per stage.
 */
class RunSubscriptionLifecycleRules extends Command
{
    protected $signature = 'subscriptions:run-lifecycle-rules';

    protected $description = 'Advance every subscription through the automatic trial-expiry / past-due / grace-period / suspension / cancellation ladder';

    public function handle(SubscriptionLifecycleService $service): int
    {
        $expired = $service->expireTrials();
        $pastDue = $service->flagPastDue();
        $graced = $service->advanceGracePeriod();
        $suspended = $service->suspendExpiredGracePeriods();
        $cancelled = $service->cancelAbandonedSuspensions();

        $this->info("Expired trials: {$expired}. Flagged past due: {$pastDue}. Entered grace period: {$graced}. Suspended: {$suspended}. Auto-cancelled: {$cancelled}.");

        return self::SUCCESS;
    }
}
