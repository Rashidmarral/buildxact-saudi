<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;

class ExpireCancelledSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire-cancelled';

    protected $description = 'Flip subscriptions scheduled for cancellation to "cancelled" once their already-paid period actually ends';

    public function handle(): int
    {
        $subscriptions = Subscription::withoutGlobalScopes()
            ->whereNotNull('cancelled_at')
            ->whereIn('status', ['trialing', 'active'])
            ->where('current_period_end', '<=', now())
            ->get();

        foreach ($subscriptions as $subscription) {
            $subscription->update(['status' => 'cancelled']);
        }

        $this->info("Expired {$subscriptions->count()} cancelled subscription(s).");

        return self::SUCCESS;
    }
}
