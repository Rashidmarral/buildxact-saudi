<?php

namespace App\Http\Controllers\User;

use App\Exceptions\PeriodLockedException;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\JournalEntry;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class ManualJournalEntryController extends Controller
{
    public function create()
    {
        $accounts = Auth::user()->company->accounts()->where('is_active', true)->orderBy('code')->get();

        return view('user.journals.manual-create', ['accounts' => $accounts]);
    }

    public function store(Request $request, LedgerPostingService $ledger): RedirectResponse
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', \Illuminate\Validation\Rule::exists('accounts', 'id')->where('company_id', $companyId)],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.memo' => ['nullable', 'string', 'max:255'],
        ]);

        $lines = collect($data['lines'])
            ->filter(fn (array $line) => (float) ($line['debit'] ?? 0) > 0 || (float) ($line['credit'] ?? 0) > 0)
            ->values()
            ->all();

        try {
            $entry = $ledger->postManual(Auth::user()->company, $data['description'], Carbon::parse($data['entry_date']), $lines);
        } catch (PeriodLockedException $e) {
            return back()->withInput()->withErrors(['entry_date' => $e->getMessage()]);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['lines' => $e->getMessage()]);
        }

        AuditLog::record('journal_entry.create_manual', $entry, __('Created manual journal entry ":number"', ['number' => $entry->entry_number]));

        return redirect()->route('app.journals.show', $entry)->with('status', __('Journal entry posted.'));
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
