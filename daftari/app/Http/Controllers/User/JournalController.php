<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ExportsCsv;
use App\Http\Controllers\User\Concerns\ResolvesPerPage;
use App\Models\JournalEntry;
use App\Services\MpdfRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class JournalController extends Controller
{
    use ExportsCsv, ResolvesPerPage;

    public function index(Request $request)
    {
        $company = Auth::user()->company;

        $filtered = $company->journalEntries()
            ->withSum('lines as total_debit', 'debit')
            ->withSum('lines as total_credit', 'credit')
            ->when($request->filled('source_type'), fn ($q) => $q->where('source_type', $request->query('source_type')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('entry_date', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('entry_date', '<=', $request->query('to')))
            ->orderByDesc('entry_date')
            ->orderByDesc('id');

        if ($request->query('export') === 'csv') {
            return $this->csvResponse('journal-entries.csv',
                [__('Entry'), __('Date'), __('Description'), __('Source'), __('Debit'), __('Credit')],
                $filtered->get()->map(fn (JournalEntry $entry) => [
                    $entry->entry_number, $entry->entry_date->format('Y-m-d'), $entry->description,
                    $entry->source_type, number_format((float) $entry->total_debit, 2, '.', ''), number_format((float) $entry->total_credit, 2, '.', ''),
                ]));
        }

        if ($request->query('export') === 'pdf') {
            $entries = $filtered->get();
            $pdf = app(MpdfRenderer::class)->render('user.journals.pdf.index', [
                'company' => $company, 'entries' => $entries,
                'from' => $request->query('from'), 'to' => $request->query('to'),
                'totalDebit' => (float) $entries->sum('total_debit'), 'totalCredit' => (float) $entries->sum('total_credit'),
                'template' => $company->defaultTemplateFor('journal_entries'),
            ]);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="journal-entries.pdf"']);
        }

        $entries = $filtered->paginate($this->resolvePerPage($request, 25))->withQueryString();
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

        $closingBalance = $lines->isNotEmpty() ? $lines->last()->running_balance : $openingBalance;

        if ($request->query('export') === 'csv' && $account) {
            $rows = $lines->map(fn ($line) => [
                $line->journalEntry->entry_date->format('Y-m-d'), $line->journalEntry->entry_number, $line->journalEntry->description,
                $line->debit > 0 ? number_format($line->debit, 2, '.', '') : '', $line->credit > 0 ? number_format($line->credit, 2, '.', '') : '',
                number_format($line->running_balance, 2, '.', ''),
            ]);
            $rows->prepend([__('Opening balance'), '', '', '', '', number_format($openingBalance, 2, '.', '')]);

            return $this->csvResponse('account-ledger-'.$account->code.'.csv',
                [__('Date'), __('Entry'), __('Description'), __('Debit'), __('Credit'), __('Balance')], $rows);
        }

        if ($request->query('export') === 'pdf' && $account) {
            $pdf = app(MpdfRenderer::class)->render('user.journals.pdf.ledger', [
                'company' => $company, 'account' => $account, 'lines' => $lines,
                'openingBalance' => $openingBalance, 'closingBalance' => $closingBalance,
                'from' => $request->query('from'), 'to' => $request->query('to'),
                'template' => $company->defaultTemplateFor('account_ledger'),
            ]);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="account-ledger-'.$account->code.'.pdf"']);
        }

        return view('user.journals.ledger', compact('accounts', 'account', 'lines', 'openingBalance'));
    }
}
