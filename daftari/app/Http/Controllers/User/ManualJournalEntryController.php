<?php

namespace App\Http\Controllers\User;

use App\Exceptions\PeriodLockedException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\EnforcesApprovalSeparation;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\JournalEntry;
use App\Models\PendingManualJournalEntry;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ManualJournalEntryController extends Controller
{
    use EnforcesApprovalSeparation;

    public function create()
    {
        $accounts = Auth::user()->company->accounts()->where('is_active', true)->orderBy('code')->get();

        return view('user.journals.manual-create', ['accounts' => $accounts]);
    }

    public function store(Request $request, LedgerPostingService $ledger): RedirectResponse
    {
        $company = Auth::user()->company;
        $companyId = $company->id;

        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', \Illuminate\Validation\Rule::exists('accounts', 'id')->where('company_id', $companyId)->where('is_active', true)],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.memo' => ['nullable', 'string', 'max:255'],
        ]);

        $lines = collect($data['lines'])
            ->filter(fn (array $line) => (float) ($line['debit'] ?? 0) > 0 || (float) ($line['credit'] ?? 0) > 0)
            ->values()
            ->all();

        // Audit finding GL-6: the same balance check postManual() would
        // otherwise do is run here first, so a submission routed to the
        // approval queue below gets the identical upfront validation a
        // directly-posted one gets from postManual() itself.
        $totalDebit = round(array_sum(array_map(fn ($line) => (float) ($line['debit'] ?? 0), $lines)), 2);
        $totalCredit = round(array_sum(array_map(fn ($line) => (float) ($line['credit'] ?? 0), $lines)), 2);

        if (count($lines) < 2 || abs($totalDebit - $totalCredit) > 0.01) {
            return back()->withInput()->withErrors(['lines' => __('Total debits (:debit) must equal total credits (:credit).', ['debit' => $totalDebit, 'credit' => $totalCredit])]);
        }

        if ($company->manualJournalRequiresApproval($totalDebit)) {
            $pending = PendingManualJournalEntry::create([
                'company_id' => $companyId,
                'entry_date' => $data['entry_date'],
                'description' => $data['description'],
                'lines' => $lines,
                'total' => $totalDebit,
                'status' => 'pending_approval',
                'created_by' => Auth::id(),
            ]);

            AuditLog::record('journal_entry.create_pending', $pending, __('Submitted manual journal entry for approval (total :total)', ['total' => $totalDebit]));

            return redirect()->route('app.journals.pending.index')->with('status', __('This entry is :total, at or above your approval threshold — it has been submitted for approval instead of posted.', ['total' => $totalDebit]));
        }

        try {
            $entry = $ledger->postManual($company, $data['description'], Carbon::parse($data['entry_date']), $lines);
        } catch (PeriodLockedException $e) {
            return back()->withInput()->withErrors(['entry_date' => $e->getMessage()]);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['lines' => $e->getMessage()]);
        }

        AuditLog::record('journal_entry.create_manual', $entry, __('Created manual journal entry ":number"', ['number' => $entry->entry_number]));

        return redirect()->route('app.journals.show', $entry)->with('status', __('Journal entry posted.'));
    }

    public function pendingIndex()
    {
        $pending = PendingManualJournalEntry::with('creator')
            ->where('status', 'pending_approval')
            ->orderByDesc('id')
            ->get();

        return view('user.journals.pending-index', compact('pending'));
    }

    /**
     * Audit finding GL-6: this is the only moment a threshold-gated entry
     * ever actually calls postManual() — nothing here has touched the
     * ledger before approval, so there is nothing to undo on rejection.
     */
    /**
     * Audit finding GL-04: approval used to check status, then post, then
     * update status as three separate unlocked steps. Two concurrent
     * approve requests for the same pending entry could both pass the
     * status check before either write landed, each calling postManual()
     * — postManual() has no natural source document to deduplicate
     * against (it self-assigns a fresh source_id per call, unlike post()),
     * so both would genuinely post, leaving one of the two orphaned: a
     * real, money-moving journal entry with no pending record pointing
     * back at it. Locking the pending row for the duration of the
     * check-then-act sequence and re-checking status after the lock is
     * acquired closes that race — a losing concurrent request now finds
     * the status already 'approved' and does nothing.
     */
    public function approvePending(PendingManualJournalEntry $pendingManualJournalEntry, LedgerPostingService $ledger): RedirectResponse
    {
        abort_if($pendingManualJournalEntry->company_id !== Auth::user()->company_id, 404);

        // Audit finding SEC-06: the preparer of this entry must not also
        // be its approver — see EnforcesApprovalSeparation.
        if ($this->approvalBlockedBySelfApproval($pendingManualJournalEntry->created_by, $pendingManualJournalEntry->company_id)) {
            return back()->withErrors(['approve' => __('You submitted this entry — it must be approved by a different user.')]);
        }

        try {
            $entry = DB::transaction(function () use ($pendingManualJournalEntry, $ledger) {
                $locked = PendingManualJournalEntry::where('id', $pendingManualJournalEntry->id)->lockForUpdate()->firstOrFail();

                abort_unless($locked->status === 'pending_approval', 404);

                $entry = $ledger->postManual(
                    Auth::user()->company,
                    $locked->description,
                    Carbon::parse($locked->entry_date),
                    $locked->lines
                );

                $locked->update([
                    'status' => 'approved', 'journal_entry_id' => $entry->id,
                    'approved_by' => Auth::id(), 'approved_at' => now(),
                ]);

                return $entry;
            });
        } catch (PeriodLockedException $e) {
            return back()->withErrors(['approve' => $e->getMessage()]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['approve' => $e->getMessage()]);
        }

        AuditLog::record('journal_entry.approve_pending', $entry, __('Approved and posted manual journal entry ":number"', ['number' => $entry->entry_number]));

        return redirect()->route('app.journals.show', $entry)->with('status', __('Journal entry approved and posted.'));
    }

    public function rejectPending(Request $request, PendingManualJournalEntry $pendingManualJournalEntry): RedirectResponse
    {
        abort_if($pendingManualJournalEntry->company_id !== Auth::user()->company_id, 404);

        $data = $request->validate(['rejection_reason' => ['nullable', 'string', 'max:1000']]);

        // Audit finding GL-04: same lock-then-recheck discipline as
        // approvePending() — a reject racing a concurrent approve must
        // not silently overwrite an entry that's already been posted.
        DB::transaction(function () use ($pendingManualJournalEntry, $data) {
            $locked = PendingManualJournalEntry::where('id', $pendingManualJournalEntry->id)->lockForUpdate()->firstOrFail();

            abort_unless($locked->status === 'pending_approval', 404);

            $locked->update([
                'status' => 'rejected', 'approved_by' => Auth::id(), 'approved_at' => now(),
                'rejection_reason' => $data['rejection_reason'] ?? null,
            ]);
        });

        AuditLog::record('journal_entry.reject_pending', $pendingManualJournalEntry, __('Rejected a pending manual journal entry (total :total)', ['total' => $pendingManualJournalEntry->total]));

        return back()->with('status', __('Journal entry rejected.'));
    }

    public function reverse(JournalEntry $journalEntry, LedgerPostingService $ledger): RedirectResponse
    {
        abort_if($journalEntry->company_id !== Auth::user()->company_id, 404);
        abort_unless($journalEntry->source_type === 'manual', 403, __('Only manual journal entries can be reversed here — a document-generated entry reverses automatically when its document is voided.'));

        $reversal = $ledger->reverse(
            Auth::user()->company,
            'manual',
            $journalEntry->source_id,
            __('Reversal of :number', ['number' => $journalEntry->entry_number])
        );

        if (! $reversal) {
            return back()->withErrors(['reverse' => __('This entry has already been reversed.')]);
        }

        AuditLog::record('journal_entry.reverse', $reversal, __('Reversed journal entry ":number"', ['number' => $journalEntry->entry_number]));

        return redirect()->route('app.journals.show', $reversal)->with('status', __('Reversing entry posted.'));
    }
}
