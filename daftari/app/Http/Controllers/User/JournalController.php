<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class JournalController extends Controller
{
    public function index(Request $request)
    {
        $company = Auth::user()->company;

        $entries = $company->journalEntries()
            ->withSum('lines as total_debit', 'debit')
            ->when($request->filled('source_type'), fn ($q) => $q->where('source_type', $request->query('source_type')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('entry_date', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('entry_date', '<=', $request->query('to')))
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $sourceTypes = $company->journalEntries()->distinct()->pluck('source_type');

        return view('user.journals.index', compact('entries', 'sourceTypes'));
    }

    public function show(JournalEntry $journalEntry)
    {
        $journalEntry->load('lines.account', 'lines.costCenter', 'creator');

        $alreadyReversed = $journalEntry->source_type === 'manual' && JournalEntry::where('company_id', $journalEntry->company_id)
            ->where('source_type', 'manual_reversal')
            ->where('source_id', $journalEntry->source_id)
            ->exists();

        return view('user.journals.show', compact('journalEntry', 'alreadyReversed'));
    }

    public function ledger(Request $request)
    {
        $company = Auth::user()->company;
        $accounts = $company->accounts()->orderBy('code')->get();
        $account = null;
        $lines = collect();
        $openingBalance = 0.0;

        if ($request->filled('account_id')) {
            $account = $accounts->firstWhere('id', (int) $request->query('account_id'));
        }
        $account ??= $accounts->first();

        if ($account) {
            $query = $account->journalEntryLines()->with('journalEntry');

            if ($request->filled('from')) {
                $openingDebit = (clone $query)->whereHas('journalEntry', fn ($q) => $q->whereDate('entry_date', '<', $request->query('from')))->sum('debit');
                $openingCredit = (clone $query)->whereHas('journalEntry', fn ($q) => $q->whereDate('entry_date', '<', $request->query('from')))->sum('credit');
                $openingBalance = $account->normal_balance === 'debit' ? $openingDebit - $openingCredit : $openingCredit - $openingDebit;
                $query->whereHas('journalEntry', fn ($q) => $q->whereDate('entry_date', '>=', $request->query('from')));
            }

            if ($request->filled('to')) {
                $query->whereHas('journalEntry', fn ($q) => $q->whereDate('entry_date', '<=', $request->query('to')));
            }

            $lines = $query->get()->sortBy(fn ($l) => $l->journalEntry->entry_date.'-'.$l->id);

            $running = $openingBalance;
            $lines = $lines->map(function ($line) use (&$running, $account) {
                $running += $account->normal_balance === 'debit' ? ($line->debit - $line->credit) : ($line->credit - $line->debit);
                $line->running_balance = $running;

                return $line;
            });
        }

        return view('user.journals.ledger', compact('accounts', 'account', 'lines', 'openingBalance'));
    }
}
