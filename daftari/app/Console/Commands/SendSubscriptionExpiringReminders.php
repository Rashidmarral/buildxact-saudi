<?php

namespace App\Console\Commands;

use App\Services\Subscriptions\SubscriptionLifecycleService;
use Illuminate\Console\Command;

class SendSubscriptionExpiringReminders extends Command
{
    protected $signature = 'subscriptions:send-expiring-reminders';

    protected $description = 'Email company owners once when their trial or subscription period is about to end, since renewals are not collected automatically';

    public function handle(SubscriptionLifecycleService $service): int
    {
        $count = $service->sendTrialEndingReminders();

        $this->info("Sent {$count} subscription-expiring reminder(s).");

        return self::SUCCESS;
    }
}
