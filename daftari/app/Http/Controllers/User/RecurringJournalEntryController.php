<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\RecurringJournalEntry;
use App\Models\RecurringJournalEntryLine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RecurringJournalEntryController extends Controller
{
    public function index()
    {
        $recurringEntries = RecurringJournalEntry::orderByDesc('id')->paginate(20);

        return view('user.recurring-journal-entries.index', compact('recurringEntries'));
    }

    public function create()
    {
        $accounts = Auth::user()->company->accounts()->where('is_active', true)->orderBy('code')->get();

        return view('user.recurring-journal-entries.form', [
            'recurringJournalEntry' => new RecurringJournalEntry(['start_date' => now()->toDateString(), 'frequency' => 'monthly']),
            'accounts' => $accounts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $recurringEntry = DB::transaction(function () use ($data) {
            $recurringEntry = RecurringJournalEntry::create([
                'created_by' => Auth::id(),
                'title' => $data['title'],
                'frequency' => $data['frequency'],
                'start_date' => $data['start_date'],
                'next_run_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
            ]);

            $this->syncLines($recurringEntry, $data['lines']);

            return $recurringEntry;
        });

        AuditLog::record('recurring_journal_entry.create', $recurringEntry, __('Created recurring journal entry ":title"', ['title' => $recurringEntry->title]));

        return redirect()->route('app.recurring-journal-entries.index')->with('status', __('Recurring journal entry created.'));
    }

    public function edit(RecurringJournalEntry $recurringJournalEntry)
    {
        $accounts = Auth::user()->company->accounts()->where('is_active', true)->orderBy('code')->get();

        return view('user.recurring-journal-entries.form', [
            'recurringJournalEntry' => $recurringJournalEntry->load('lines'),
            'accounts' => $accounts,
        ]);
    }

    public function update(Request $request, RecurringJournalEntry $recurringJournalEntry): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($recurringJournalEntry, $data) {
            $recurringJournalEntry->update([
                'title' => $data['title'],
                'frequency' => $data['frequency'],
                'end_date' => $data['end_date'] ?? null,
            ]);

            $recurringJournalEntry->lines()->delete();
            $this->syncLines($recurringJournalEntry, $data['lines']);
        });

        AuditLog::record('recurring_journal_entry.update', $recurringJournalEntry, __('Updated recurring journal entry ":title"', ['title' => $recurringJournalEntry->title]));

        return redirect()->route('app.recurring-journal-entries.index')->with('status', __('Recurring journal entry updated.'));
    }

    public function pause(RecurringJournalEntry $recurringJournalEntry): RedirectResponse
    {
        $recurringJournalEntry->update(['status' => 'paused']);

        AuditLog::record('recurring_journal_entry.pause', $recurringJournalEntry, __('Paused recurring journal entry ":title"', ['title' => $recurringJournalEntry->title]));

        return back()->with('status', __('Recurring journal entry paused.'));
    }

    public function resume(RecurringJournalEntry $recurringJournalEntry): RedirectResponse
    {
        $recurringJournalEntry->update([
            'status' => 'active',
            'next_run_date' => $recurringJournalEntry->next_run_date->isPast() ? now()->toDateString() : $recurringJournalEntry->next_run_date,
        ]);

        AuditLog::record('recurring_journal_entry.resume', $recurringJournalEntry, __('Resumed recurring journal entry ":title"', ['title' => $recurringJournalEntry->title]));

        return back()->with('status', __('Recurring journal entry resumed.'));
    }

    public function destroy(RecurringJournalEntry $recurringJournalEntry): RedirectResponse
    {
        $title = $recurringJournalEntry->title;
        $recurringJournalEntry->delete();

        AuditLog::record('recurring_journal_entry.delete', null, __('Deleted recurring journal entry ":title"', ['title' => $title]));

        return redirect()->route('app.recurring-journal-entries.index')->with('status', __('Recurring journal entry deleted.'));
    }

    private function syncLines(RecurringJournalEntry $recurringEntry, array $lines): void
    {
        foreach ($lines as $line) {
            RecurringJournalEntryLine::create([
                'recurring_journal_entry_id' => $recurringEntry->id,
                'account_id' => $line['account_id'],
                'debit' => $line['debit'] ?? 0,
                'credit' => $line['credit'] ?? 0,
                'memo' => $line['memo'] ?? null,
            ]);
        }
    }

    private function validated(Request $request): array
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'frequency' => ['required', Rule::in(RecurringJournalEntry::FREQUENCIES)],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', Rule::exists('accounts', 'id')->where('company_id', $companyId)],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.memo' => ['nullable', 'string', 'max:255'],
        ]);

        $lines = collect($data['lines'])
            ->filter(fn (array $line) => (float) ($line['debit'] ?? 0) > 0 || (float) ($line['credit'] ?? 0) > 0)
            ->values();

        if ($lines->count() < 2) {
            throw \Illuminate\Validation\ValidationException::withMessages(['lines' => __('A recurring journal entry needs at least two lines with an amount.')]);
        }

        $totalDebit = round((float) $lines->sum('debit'), 2);
        $totalCredit = round((float) $lines->sum('credit'), 2);

        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw \Illuminate\Validation\ValidationException::withMessages(['lines' => __('Total debits (:debit) must equal total credits (:credit).', ['debit' => number_format($totalDebit, 2), 'credit' => number_format($totalCredit, 2)])]);
        }

        $data['lines'] = $lines->all();

        return $data;
    }
}
