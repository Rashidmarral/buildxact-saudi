<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\CollectionNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "nothing stops you from issuing a new invoice to someone who's badly
 * overdue" — a collections log per client (who was contacted, what they
 * promised, when) plus a credit-hold flag that InvoiceController::send()
 * actually enforces before a new invoice goes out. The automated email
 * dunning ladder (SendOverdueInvoiceReminders) already escalates the
 * reminder itself; this is the human follow-up trail and the one lever
 * that actually stops new business going to a client who hasn't paid.
 */
class CollectionNoteController extends Controller
{
    public function store(Request $request, Client $client)
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
            'promise_to_pay_date' => ['nullable', 'date'],
            'promise_to_pay_amount' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        $client->collectionNotes()->create($data + ['created_by' => Auth::id()]);

        return back()->with('status', __('Collection note added.'));
    }

    public function destroy(CollectionNote $collectionNote)
    {
        $collectionNote->delete();

        return back()->with('status', __('Collection note removed.'));
    }

    public function toggleHold(Request $request, Client $client)
    {
        $data = $request->validate([
            'credit_hold' => ['required', 'boolean'],
            'credit_hold_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $client->update([
            'credit_hold' => $data['credit_hold'],
            'credit_hold_reason' => $data['credit_hold'] ? ($data['credit_hold_reason'] ?? null) : null,
        ]);

        AuditLog::record(
            $data['credit_hold'] ? 'client.credit_hold' : 'client.credit_hold_released',
            $client,
            $data['credit_hold']
                ? __('Put :name on credit hold: :reason', ['name' => $client->name, 'reason' => $data['credit_hold_reason'] ?? __('no reason given')])
                : __('Released credit hold on :name', ['name' => $client->name]),
        );

        return back()->with('status', $data['credit_hold'] ? __('Client put on credit hold.') : __('Credit hold released.'));
    }
}
