<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionExpiringMail;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendSubscriptionExpiringReminders extends Command
{
    protected $signature = 'subscriptions:send-expiring-reminders';

    protected $description = 'Email company owners once when their trial or subscription period is about to end, since renewals are not collected automatically';

    /**
     * How far ahead of the period end to warn. Subscriptions are billed
     * manually (no auto-charge gateway wired up), so this is the only
     * nudge an owner gets before access would lapse.
     */
    protected const DAYS_AHEAD = 3;

    public function handle(): int
    {
        $subscriptions = Subscription::withoutGlobalScopes()
            ->whereIn('status', ['trialing', 'active'])
            ->whereNotNull('current_period_end')
            ->whereNull('expiry_reminder_sent_at')
            ->where('current_period_end', '<=', now()->addDays(self::DAYS_AHEAD))
            ->where('current_period_end', '>', now())
            ->with('company', 'plan')
            ->get()
            ->filter(fn (Subscription $subscription) => $subscription->company?->owners()->exists());

        foreach ($subscriptions as $subscription) {
            foreach ($subscription->company->owners as $owner) {
                Mail::to($owner->email)->send(new SubscriptionExpiringMail($subscription));
            }

            $subscription->update(['expiry_reminder_sent_at' => now()]);
        }

        $this->info("Sent {$subscriptions->count()} subscription-expiring reminder(s).");

        return self::SUCCESS;
    }
}
