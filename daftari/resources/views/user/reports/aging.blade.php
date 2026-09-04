@extends('layouts.app')

@section('title', __('Aged Receivables/Payables'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Aged Receivables/Payables') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Every outstanding invoice or bill, bucketed by how overdue it is — as of today.') }}</p>
    </div>
    <a href="{{ route('app.reports.aging', ['type' => $type, 'export' => 'csv']) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
</div>

<div class="flex items-center gap-2 mb-6 text-sm border-b border-slate-100">
    <a href="{{ route('app.reports.aging', ['type' => 'receivables']) }}" class="px-3 py-2 border-b-2 {{ $type === 'receivables' ? 'border-brand-600 text-brand-700 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700' }}">{{ __('Receivables (customers owe you)') }}</a>
    <a href="{{ route('app.reports.aging', ['type' => 'payables']) }}" class="px-3 py-2 border-b-2 {{ $type === 'payables' ? 'border-brand-600 text-brand-700 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700' }}">{{ __('Payables (you owe suppliers)') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100 overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-5 py-3 font-medium">{{ $type === 'receivables' ? __('Customer') : __('Supplier') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Current') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('1-30 days') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('31-60 days') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('61-90 days') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('90+ days') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Total') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-5 py-3 font-medium text-slate-800">{{ $row['party']->name }}</td>
                    <td class="px-5 py-3 text-end">{{ \App\Support\Money::format($row['buckets']['current']) }}</td>
                    <td class="px-5 py-3 text-end">{{ \App\Support\Money::format($row['buckets']['days_1_30']) }}</td>
                    <td class="px-5 py-3 text-end">{{ \App\Support\Money::format($row['buckets']['days_31_60']) }}</td>
                    <td class="px-5 py-3 text-end {{ $row['buckets']['days_61_90'] > 0 ? 'text-amber-600 font-medium' : '' }}">{{ \App\Support\Money::format($row['buckets']['days_61_90']) }}</td>
                    <td class="px-5 py-3 text-end {{ $row['buckets']['days_over_90'] > 0 ? 'text-red-600 font-semibold' : '' }}">{{ \App\Support\Money::format($row['buckets']['days_over_90']) }}</td>
                    <td class="px-5 py-3 text-end font-semibold text-slate-900">{{ \App\Support\Money::format($row['total']) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-5 py-10 text-center text-slate-400">{{ $type === 'receivables' ? __('No outstanding customer balances.') : __('No outstanding supplier balances.') }}</td></tr>
            @endforelse
        </tbody>
        @if ($rows->isNotEmpty())
            <tfoot>
                <tr class="border-t-2 border-slate-800 font-semibold text-slate-900">
                    <td class="px-5 py-3">{{ __('Total') }}</td>
                    <td class="px-5 py-3 text-end">{{ \App\Support\Money::format($totals['current']) }}</td>
                    <td class="px-5 py-3 text-end">{{ \App\Support\Money::format($totals['days_1_30']) }}</td>
                    <td class="px-5 py-3 text-end">{{ \App\Support\Money::format($totals['days_31_60']) }}</td>
                    <td class="px-5 py-3 text-end">{{ \App\Support\Money::format($totals['days_61_90']) }}</td>
                    <td class="px-5 py-3 text-end">{{ \App\Support\Money::format($totals['days_over_90']) }}</td>
                    <td class="px-5 py-3 text-end">{{ \App\Support\Money::format($totals['total']) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>
@endsection
