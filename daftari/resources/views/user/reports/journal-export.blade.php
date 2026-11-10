@extends('layouts.app')

@section('title', __('Journal Export (All Postings)'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Journal Export (All Postings)') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Every posting line of the period in one flat list — date, reference, source, account, debit, credit, cost centre, contact, location and user — for auditors and external accountants.') }}</p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <a href="{{ route('app.reports.journal-export', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
        <a href="{{ route('app.reports.journal-export', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
    </div>
</div>

@include('user.reports.partials.period-selector', ['extra' => array_filter([
    'cost_center_id' => $costCenterId,
    'location_id' => $locationId,
])])

<form method="GET" class="bg-white rounded-xl border border-slate-100 p-4 mb-6 flex flex-wrap items-center gap-3">
    <input type="hidden" name="period" value="{{ $period['preset'] }}">
    <input type="hidden" name="from" value="{{ $period['from']->toDateString() }}">
    <input type="hidden" name="to" value="{{ $period['to']->toDateString() }}">
    <select name="cost_center_id" onchange="this.form.submit()" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        <option value="">{{ __('All cost centres') }}</option>
        @foreach ($costCenters as $cc)
            <option value="{{ $cc->id }}" @selected($costCenterId === $cc->id)>{{ $cc->label() }}</option>
        @endforeach
    </select>
    <select name="location_id" onchange="this.form.submit()" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        <option value="">{{ __('All locations') }}</option>
        @foreach ($branches as $branch)
            <option value="{{ $branch->id }}" @selected($locationId === $branch->id)>{{ $branch->name }}</option>
        @endforeach
    </select>
    @if ($locationId)
        <p class="text-xs text-amber-600">{{ __('A manual journal entry, payment voucher, or expense carries no location and is always excluded when a location filter is on.') }}</p>
    @endif
</form>

<div class="bg-white rounded-xl border border-slate-100 overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-4 py-3 font-medium">{{ __('Date') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Reference') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Source') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Code') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Account') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Description') }}</th>
                <th class="px-4 py-3 font-medium text-end">{{ __('Debit') }}</th>
                <th class="px-4 py-3 font-medium text-end">{{ __('Credit') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Cost centre') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Contact') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Location') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('User') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-4 py-2 text-slate-500 whitespace-nowrap">{{ $row['date']->format('Y-m-d') }}</td>
                    <td class="px-4 py-2">{{ $row['reference'] }}</td>
                    <td class="px-4 py-2 text-slate-500">{{ $row['source'] ? ucfirst(str_replace('_', ' ', $row['source'])) : '—' }}</td>
                    <td class="px-4 py-2 text-slate-500">{{ $row['code'] }}</td>
                    <td class="px-4 py-2">{{ $row['account'] }}</td>
                    <td class="px-4 py-2 text-slate-500">{{ $row['description'] }}</td>
                    <td class="px-4 py-2 text-end">{{ $row['debit'] > 0 ? number_format($row['debit'], 2) : '' }}</td>
                    <td class="px-4 py-2 text-end">{{ $row['credit'] > 0 ? number_format($row['credit'], 2) : '' }}</td>
                    <td class="px-4 py-2 text-slate-500">{{ $row['costCentre'] ?? '—' }}</td>
                    <td class="px-4 py-2 text-slate-500">{{ $row['contact'] ?? '—' }}</td>
                    <td class="px-4 py-2 text-slate-500">{{ $row['location'] ?? '—' }}</td>
                    <td class="px-4 py-2 text-slate-500">{{ $row['user'] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="12" class="px-5 py-10 text-center text-slate-400">{{ __('No postings in this period.') }}</td></tr>
            @endforelse
        </tbody>
        @if ($rows->isNotEmpty())
            <tfoot>
                <tr class="border-t-2 border-slate-800 font-semibold text-slate-900">
                    <td class="px-4 py-3" colspan="6">{{ __('Total') }}</td>
                    <td class="px-4 py-3 text-end">{{ number_format($totalDebit, 2) }}</td>
                    <td class="px-4 py-3 text-end">{{ number_format($totalCredit, 2) }}</td>
                    <td class="px-4 py-3" colspan="4"></td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>
@endsection
