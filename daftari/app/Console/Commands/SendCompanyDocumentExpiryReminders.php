<?php

namespace App\Console\Commands;

use App\Http\Controllers\User\SettingsController;
use App\Models\Attachment;
use App\Models\Company;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Console\Command;

/**
 * Settings already lets a company store its compliance documents (CR,
 * VAT Certificate, Municipality License, GOSI Certificate, Zakat/Tax
 * Certificate, ...) with an expiry date, and shows it passively — but
 * nothing ever proactively said one was about to lapse, unlike Machinery
 * (SendMachineryExpiryReminders), Invoices, or Subscriptions, which all
 * already have a reminder job. A lapsed CR or Municipality License in
 * Saudi Arabia can mean real fines or a frozen ability to trade, so this
 * closes that one remaining silent gap — same "notify once, clear the
 * guard once resolved" shape as the machinery job, except it keeps
 * reminding (doesn't go silent) once the document has actually expired,
 * matching Attachment::isExpired()/isExpiringSoon()'s own definition of
 * what's worth flagging.
 */
class SendCompanyDocumentExpiryReminders extends Command
{
    protected $signature = 'company-documents:send-expiry-reminders';

    protected $description = 'Notify users with Settings access once when a company compliance document is expiring soon or has expired';

    private const WINDOW_DAYS = 30;

    public function handle(): int
    {
        $horizon = now()->addDays(self::WINDOW_DAYS);

        // A document renewed (replaced by a new upload with a later
        // expiry, or had its date cleared) clears any stale guard, so a
        // future renewal cycle reminds again rather than staying silenced
        // by a reminder sent for the old expiry.
        Attachment::withoutGlobalScopes()
            ->where('attachable_type', Company::class)
            ->whereNotNull('expiry_reminder_sent_at')
            ->where(fn ($q) => $q->whereNull('expiry_date')->orWhere('expiry_date', '>', $horizon))
            ->update(['expiry_reminder_sent_at' => null]);

        $documents = Attachment::withoutGlobalScopes()
            ->where('attachable_type', Company::class)
            ->whereNotNull('expiry_date')
            ->whereNull('expiry_reminder_sent_at')
            ->where('expiry_date', '<=', $horizon)
            ->get();

        foreach ($documents as $document) {
            $this->notifyCompany($document);
            $document->update(['expiry_reminder_sent_at' => now()]);
        }

        $this->info("Sent {$documents->count()} company document expiry reminder(s).");

        return self::SUCCESS;
    }

    private function notifyCompany(Attachment $document): void
    {
        $label = __(SettingsController::DOCUMENT_TYPES[$document->document_type] ?? $document->document_type);
        $expired = $document->isExpired();

        User::where('company_id', $document->company_id)
            ->get()
            ->filter(fn (User $user) => $user->hasPermission('settings'))
            ->each(fn (User $user) => $user->notify(new GenericNotification(
                title: $expired ? __('Document expired') : __('Document expiring soon'),
                body: __(':document — :status :date', [
                    'document' => $label,
                    'status' => $expired ? __('expired') : __('expires'),
                    'date' => $document->expiry_date->format('Y-m-d'),
                ]),
                url: route('app.settings.index'),
                icon: 'alert',
            )));
    }
}
