<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\PartyPeriodClosure;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * "app have an ability to close records of supplier and client on
 * monthly/quarterly basis" (task #158) — a per-party mirror of
 * ApprovalSettingsController's company-wide close/reopen, enforced by
 * LedgerPostingService::guardAgainstPartyLock() on every post/reverse.
 * Reached from the Account Statement page for the party currently
 * selected there.
 */
class PartyPeriodClosureController extends Controller
{
    public function closeClient(Request $request, Client $client): RedirectResponse
    {
        return $this->close($request, $client, 'client');
    }

    public function reopenClient(Request $request, Client $client): RedirectResponse
    {
        return $this->reopen($request, $client, 'client');
    }

    public function closeSupplier(Request $request, Supplier $supplier): RedirectResponse
    {
        return $this->close($request, $supplier, 'supplier');
    }

    public function reopenSupplier(Request $request, Supplier $supplier): RedirectResponse
    {
        return $this->reopen($request, $supplier, 'supplier');
    }

    /**
     * Forward-only, same as the company-wide close — moving the lock
     * date backward is reopen()'s job alone, with its own mandatory
     * reason.
     */
    private function close(Request $request, Client|Supplier $party, string $partyType): RedirectResponse
    {
        $data = $request->validate(['lock_date' => ['required', 'date']]);
        $new = $data['lock_date'];

        if ($party->lock_date && Carbon::parse($new)->lte($party->lock_date)) {
            return back()->withErrors(['lock_date' => __('The new lock date must be after the current one — use "Reopen" to move it backward.')]);
        }

        $old = $party->lock_date?->toDateString();
        $party->update(['lock_date' => $new]);

        PartyPeriodClosure::create([
            'company_id' => $party->company_id, 'party_type' => $partyType, 'party_id' => $party->id,
            'action' => 'close', 'lock_date' => $new, 'performed_by' => Auth::id(),
        ]);

        AuditLog::record(
            $partyType.'.lock_date_update',
            $party,
            __('Locked :name\'s records through :date', ['name' => $party->name, 'date' => $new]),
            old: ['lock_date' => $old],
            new: ['lock_date' => $new],
        );

        return back()->with('status', __(':name\'s records are locked through :date.', ['name' => $party->name, 'date' => $new]));
    }

    /**
     * The only path that can move the lock date backward or clear it —
     * always requires a reason, which close() above does not.
     */
    private function reopen(Request $request, Client|Supplier $party, string $partyType): RedirectResponse
    {
        $data = $request->validate([
            'lock_date' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $old = $party->lock_date?->toDateString();
        $new = $data['lock_date'] ?: null;

        if ($new !== null && $party->lock_date && Carbon::parse($new)->gt($party->lock_date)) {
            return back()->withErrors(['lock_date' => __('That date is not earlier than the current lock date — use "Close" to move it forward.')]);
        }

        $party->update(['lock_date' => $new]);

        PartyPeriodClosure::create([
            'company_id' => $party->company_id, 'party_type' => $partyType, 'party_id' => $party->id,
            'action' => 'reopen', 'lock_date' => $new, 'performed_by' => Auth::id(), 'reason' => $data['reason'],
        ]);

        AuditLog::record(
            $partyType.'.lock_date_update',
            $party,
            $new
                ? __('Reopened :name\'s records back to :date — :reason', ['name' => $party->name, 'date' => $new, 'reason' => $data['reason']])
                : __('Reopened :name\'s records entirely — :reason', ['name' => $party->name, 'reason' => $data['reason']]),
            old: ['lock_date' => $old],
            new: ['lock_date' => $new],
        );

        return back()->with('status', __(':name\'s period was reopened.', ['name' => $party->name]));
    }
}
