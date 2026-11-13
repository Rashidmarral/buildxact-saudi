<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\CashTracker;
use App\Models\CashTrackerEntry;
use App\Services\MpdfRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * "something to tackle this kind of situation like i directly add two
 * deposit into other company account and add entries where this cash is
 * used and get report for it and that will not affect our accounting
 * system like a separate module ... just to get exact report of that
 * account to show where cash goes on of that account to manage."
 *
 * See CashTracker's own doc comment for what this deliberately is not
 * connected to. Every amount here is free-text/manual, loosely
 * validated on purpose (a party_name/purpose typo or a skipped entry
 * doesn't throw off anyone's real books) — this is a management-visibility
 * tool, not a second set of accounts.
 */
class CashTrackerController extends Controller
{
    public function index()
    {
        $trackers = Auth::user()->company->cashTrackers()->withCount('entries')->orderByDesc('id')->get();

        return view('user.cash-trackers.index', [
            'trackers' => $trackers,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $company = Auth::user()->company;

        $tracker = $company->cashTrackers()->create($data + [
            'currency' => $company->currency ?? 'SAR',
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('app.cash-trackers.show', $tracker)->with('status', __('Cash tracker created.'));
    }

    public function show(CashTracker $cashTracker)
    {
        $cashTracker->load('entries');

        return view('user.cash-trackers.show', [
            'tracker' => $cashTracker,
            'summary' => $this->summarizeByParty($cashTracker),
        ]);
    }

    public function storeEntry(Request $request, CashTracker $cashTracker): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'type' => ['required', Rule::in(CashTrackerEntry::TYPES)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'party_name' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $cashTracker->entries()->create($data + [
            'company_id' => $cashTracker->company_id,
            'created_by' => Auth::id(),
        ]);

        return back()->with('status', __('Entry added.'));
    }

    public function destroyEntry(CashTracker $cashTracker, CashTrackerEntry $entry): RedirectResponse
    {
        abort_unless($entry->cash_tracker_id === $cashTracker->id, 404);

        $entry->delete();

        return back()->with('status', __('Entry removed.'));
    }

    public function destroy(CashTracker $cashTracker): RedirectResponse
    {
        $cashTracker->delete();

        return redirect()->route('app.cash-trackers.index')->with('status', __('Cash tracker deleted.'));
    }

    public function pdf(CashTracker $cashTracker, MpdfRenderer $renderer)
    {
        $cashTracker->load('entries');

        $pdf = $renderer->render('user.cash-trackers.pdf', [
            'company' => Auth::user()->company,
            'tracker' => $cashTracker,
            'summary' => $this->summarizeByParty($cashTracker),
            'template' => Auth::user()->company->defaultTemplateFor('cash_tracker_report'),
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.\Illuminate\Support\Str::slug($cashTracker->name).'-cash-report.pdf"',
        ]);
    }

    /**
     * Deposited/withdrawn/net per free-text party_name — the "where did
     * the cash with the Project Manager vs. what I kept myself vs. what
     * went back to the owner actually come out to" breakdown the owner
     * asked for. Entries with no party_name land under "Unspecified"
     * rather than being silently dropped from the summary.
     */
    private function summarizeByParty(CashTracker $cashTracker): \Illuminate\Support\Collection
    {
        return $cashTracker->entries
            ->groupBy(fn (CashTrackerEntry $e) => $e->party_name ?: __('Unspecified'))
            ->map(function ($entries, $party) {
                $deposited = (float) $entries->where('type', 'deposit')->sum('amount');
                $withdrawn = (float) $entries->where('type', 'withdrawal')->sum('amount');

                return [
                    'party' => $party,
                    'deposited' => $deposited,
                    'withdrawn' => $withdrawn,
                    'net' => round($deposited - $withdrawn, 2),
                ];
            })
            ->sortByDesc(fn ($row) => $row['deposited'] + $row['withdrawn'])
            ->values();
    }
}
