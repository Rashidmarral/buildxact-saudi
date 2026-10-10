<?php

namespace App\Console\Commands;

use App\Models\Quotation;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Console\Command;

/**
 * Audit finding MEDIUM-22: Quotation::isExpired() and the quotations index
 * "Expired" filter tab both already existed, but nothing ever actually set
 * a quotation's status column to 'expired' — isExpired() was purely a
 * computed, display-time check. The "Expired" tab's count query filters on
 * status='expired' directly, so it always showed 0 no matter how many
 * quotations had actually lapsed. This walks every company's issued
 * quotations daily and flips the ones past their expiry_date, so the
 * status column (and therefore that filter tab, reports, exports, …)
 * reflects reality instead of only ever being computed on the fly.
 */
class ExpireQuotations extends Command
{
    protected $signature = 'quotations:expire';

    protected $description = 'Mark issued quotations past their expiry_date as expired and notify the creator';

    public function handle(): int
    {
        $quotations = Quotation::withoutGlobalScopes()
            ->where('status', 'issued')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now()->toDateString())
            ->get();

        foreach ($quotations as $quotation) {
            $quotation->update(['status' => 'expired']);

            User::find($quotation->created_by)?->notify(new GenericNotification(
                title: __('Quotation expired'),
                body: __('Quotation :number expired without a response and has been marked expired.', ['number' => $quotation->quotation_number]),
                url: route('app.quotations.show', $quotation),
                icon: 'quotations',
            ));
        }

        $this->info("Expired {$quotations->count()} quotation(s).");

        return self::SUCCESS;
    }
}
