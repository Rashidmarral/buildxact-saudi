@extends('layouts.app')

@section('title', __('General Ledger'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('General Ledger') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Every account, its opening balance, every line posted in the period, and its closing balance — the standard audit document.') }}</p>
    </div>
    <a href="{{ route('app.reports.general-ledger', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
    <a href="{{ route('app.reports.general-ledger', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
</div>

@include('user.reports.partials.period-selector')

@if ($accounts->isEmpty())
    <div class="bg-white rounded-xl border border-slate-100 px-6 py-16 text-center text-sm text-slate-400">{{ __('No account has an opening balance or activity in this period.') }}</div>
@else
    <div class="space-y-6">
        @foreach ($accounts as $row)
            <div class="bg-white rounded-xl border border-slate-100">
                <div class="flex items-center justify-between px-5 py-3 border-b border-slate-100">
                    <p class="font-semibold text-slate-900">{{ $row['account']->label() }}</p>
                    <p class="text-xs text-slate-400">{{ __('Opening') }} {{ number_format($row['openingBalance'], 2) }} &rarr; {{ __('Closing') }} <span class="font-semibold text-slate-700">{{ number_format($row['closingBalance'], 2) }}</span></p>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 border-b border-slate-100">
                            <th class="px-5 py-2.5 font-medium">{{ __('Date') }}</th>
                            <th class="px-5 py-2.5 font-medium">{{ __('Entry') }}</th>
                            <th class="px-5 py-2.5 font-medium">{{ __('Description') }}</th>
                            <th class="px-5 py-2.5 font-medium text-end">{{ __('Debit') }}</th>
                            <th class="px-5 py-2.5 font-medium text-end">{{ __('Credit') }}</th>
                            <th class="px-5 py-2.5 font-medium text-end">{{ __('Balance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b border-slate-50 text-slate-400 italic">
                            <td class="px-5 py-2.5" colspan="5">{{ __('Opening balance') }}</td>
                            <td class="px-5 py-2.5 text-end">{{ number_format($row['openingBalance'], 2) }}</td>
                        </tr>
                        @foreach ($row['lines'] as $line)
                            <tr class="border-b border-slate-50 last:border-0">
                                <td class="px-5 py-2.5 text-slate-500">{{ $line->journalEntry->entry_date->format('Y-m-d') }}</td>
                                <td class="px-5 py-2.5"><a href="{{ route('app.journals.show', $line->journalEntry) }}" class="text-brand-700 hover:underline">{{ $line->journalEntry->entry_number }}</a></td>
                                <td class="px-5 py-2.5">{{ $line->journalEntry->description }}</td>
                                <td class="px-5 py-2.5 text-end">{{ $line->debit > 0 ? number_format($line->debit, 2) : '' }}</td>
                                <td class="px-5 py-2.5 text-end">{{ $line->credit > 0 ? number_format($line->credit, 2) : '' }}</td>
                                <td class="px-5 py-2.5 text-end font-medium">{{ number_format($line->running_balance, 2) }}</td>
                            </tr>
                        @endforeach
                        <tr class="border-t border-slate-200 font-semibold text-slate-900">
                            <td class="px-5 py-2.5" colspan="3">{{ __('Closing balance') }}</td>
                            <td class="px-5 py-2.5 text-end">{{ number_format($row['periodDebit'], 2) }}</td>
                            <td class="px-5 py-2.5 text-end">{{ number_format($row['periodCredit'], 2) }}</td>
                            <td class="px-5 py-2.5 text-end">{{ number_format($row['closingBalance'], 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>
@endif
@endsection
