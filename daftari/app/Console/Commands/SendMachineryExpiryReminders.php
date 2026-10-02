<?php

namespace App\Console\Commands;

use App\Models\MachineryAsset;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * A machine's Istimara (registration) and insurance both lapse on their
 * own renewal cycle, silently, unless someone happens to check the asset
 * page. This warns once per expiry, the same "notify once, clear the
 * guard once resolved" shape as CheckLowStock's low_stock_notified_at.
 */
class SendMachineryExpiryReminders extends Command
{
    protected $signature = 'machinery:send-expiry-reminders';

    protected $description = 'Notify users with Machinery & Equipment access once when a machine\'s registration or insurance is about to expire';

    private const WINDOW_DAYS = 30;

    public function handle(): int
    {
        $notified = $this->remindForField('registration_expiry_date', 'registration_reminder_sent_at', __('Registration expiring soon'))
            + $this->remindForField('insurance_expiry_date', 'insurance_reminder_sent_at', __('Insurance expiring soon'));

        $this->info("Sent {$notified} machinery expiry reminder(s).");

        return self::SUCCESS;
    }

    private function remindForField(string $dateColumn, string $sentColumn, string $title): int
    {
        $horizon = now()->addDays(self::WINDOW_DAYS);

        // A machine renewed after being flagged clears its guard so the
        // next renewal cycle reminds again — otherwise one expiry would
        // silence this machine's reminders forever.
        MachineryAsset::withoutGlobalScopes()
            ->whereNotNull($sentColumn)
            ->where(fn ($q) => $q->whereNull($dateColumn)->orWhere($dateColumn, '>', $horizon))
            ->update([$sentColumn => null]);

        $assets = MachineryAsset::withoutGlobalScopes()
            ->whereNotNull($dateColumn)
            ->whereNull($sentColumn)
            ->where($dateColumn, '<=', $horizon)
            ->where($dateColumn, '>=', now()->toDateString())
            ->get();

        foreach ($assets as $asset) {
            $this->notifyCompany($asset, $dateColumn, $title);
            $asset->update([$sentColumn => now()]);
        }

        return $assets->count();
    }

    private function notifyCompany(MachineryAsset $asset, string $dateColumn, string $title): void
    {
        /** @var Carbon $date */
        $date = $asset->{$dateColumn};

        User::where('company_id', $asset->company_id)
            ->get()
            ->filter(fn (User $user) => $user->hasPermission('machinery_equipment'))
            ->each(fn (User $user) => $user->notify(new GenericNotification(
                title: $title,
                body: __(':asset (:code) — expires :date', ['asset' => $asset->name, 'code' => $asset->asset_code, 'date' => $date->format('Y-m-d')]),
                url: route('app.machinery.assets.show', $asset),
                icon: 'machinery',
            )));
    }
}
