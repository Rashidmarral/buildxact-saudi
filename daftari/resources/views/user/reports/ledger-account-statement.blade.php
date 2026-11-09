@extends('layouts.app')

@section('title', __('Ledger Account Statement'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Ledger Account Statement') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('A statement of one or more ledger accounts at once — opening balance, every posting, running balance and closing balance — narrowable by transaction source and user.') }}</p>
    </div>
    @if ($rows->isNotEmpty())
        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ route('app.reports.ledger-account-statement', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
            <a href="{{ route('app.reports.ledger-account-statement', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
        </div>
    @endif
</div>

<form method="GET" class="bg-white rounded-xl border border-slate-100 p-4 mb-6 space-y-3">
    <div>
        <label class="text-xs font-semibold uppercase text-slate-400 mb-1 block">{{ __('Accounts') }}</label>
        <select name="account_ids[]" multiple size="6" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            @foreach ($accounts as $acc)
                <option value="{{ $acc->id }}" @selected($accountIds->contains($acc->id))>{{ $acc->label() }}</option>
            @endforeach
        </select>
        <p class="text-xs text-slate-400 mt-1">{{ __('Ctrl/Cmd-click to select more than one.') }}</p>
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <input type="date" name="from" value="{{ $period['from']->toDateString() }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        <span class="text-slate-400 text-sm">{{ __('to') }}</span>
        <input type="date" name="to" value="{{ $period['to']->toDateString() }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        <input type="hidden" name="period" value="custom">
        <select name="source_type" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <option value="">{{ __('All transaction types') }}</option>
            @foreach ($sourceTypes as $st)
                <option value="{{ $st }}" @selected($sourceType === $st)>{{ ucfirst(str_replace('_', ' ', $st)) }}</option>
            @endforeach
        </select>
        <select name="user_id" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <option value="">{{ __('All users') }}</option>
            @foreach ($users as $u)
                <option value="{{ $u->id }}" @selected($userId === $u->id)>{{ $u->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Show statement') }}</button>
    </div>
    @if ($sourceType || $userId)
        <p class="text-xs text-amber-600">{{ __('A transaction-type or user filter is active: the running/closing balance below reflects only the filtered postings, not the account\'s true balance.') }}</p>
    @endif
</form>

@forelse ($rows as $row)
    <div class="bg-white rounded-xl border border-slate-100 mb-6 overflow-x-auto">
        <div class="px-5 py-3 border-b border-slate-100 font-semibold text-slate-800">{{ $row['account']->label() }}</div>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-5 py-3 font-medium">{{ __('Date') }}</th>
                    <th class="px-5 py-3 font-medium">{{ __('Entry') }}</th>
                    <th class="px-5 py-3 font-medium">{{ __('Description') }}</th>
                    <th class="px-5 py-3 font-medium text-end">{{ __('Debit') }}</th>
                    <th class="px-5 py-3 font-medium text-end">{{ __('Credit') }}</th>
                    <th class="px-5 py-3 font-medium text-end">{{ __('Balance') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-b border-slate-50 text-slate-400 italic">
                    <td class="px-5 py-3" colspan="5">{{ __('Opening balance') }}</td>
                    <td class="px-5 py-3 text-end">{{ number_format($row['openingBalance'], 2) }}</td>
                </tr>
                @forelse ($row['lines'] as $line)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-5 py-3 text-slate-500">{{ $line->journalEntry->entry_date->format('Y-m-d') }}</td>
                        <td class="px-5 py-3"><a href="{{ route('app.journals.show', $line->journalEntry) }}" class="text-brand-700 hover:underline">{{ $line->journalEntry->entry_number }}</a></td>
                        <td class="px-5 py-3">{{ $line->journalEntry->description }}</td>
                        <td class="px-5 py-3 text-end">{{ $line->debit > 0 ? number_format($line->debit, 2) : '' }}</td>
                        <td class="px-5 py-3 text-end">{{ $line->credit > 0 ? number_format($line->credit, 2) : '' }}</td>
                        <td class="px-5 py-3 text-end font-medium">{{ number_format($line->running_balance, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-6 text-center text-slate-400">{{ __('No matching postings in this period.') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-slate-800 font-semibold text-slate-900">
                    <td class="px-5 py-3" colspan="5">{{ __('Closing balance') }}</td>
                    <td class="px-5 py-3 text-end">{{ number_format($row['closingBalance'], 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
@empty
    <div class="bg-white rounded-xl border border-slate-100 px-6 py-16 text-center text-sm text-slate-400">{{ __('Select one or more accounts above to see their statement.') }}</div>
@endforelse
@endsection
