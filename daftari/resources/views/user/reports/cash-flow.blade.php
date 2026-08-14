@extends('layouts.app')

@section('title', __('Cash Flow'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Cash Flow') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Movement across your cash and bank accounts for the period.') }}</p>
    </div>
    <a href="{{ route('app.reports.cash-flow', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
</div>

@include('user.reports.partials.period-selector')

<div class="grid sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-100 p-5"><p class="text-xs text-slate-400">{{ __('Opening balance') }}</p><p class="text-xl font-bold text-slate-900 mt-1">SAR {{ number_format($openingTotal, 2) }}</p></div>
    <div class="bg-white rounded-xl border border-slate-100 p-5"><p class="text-xs text-slate-400">{{ __('Cash in') }}</p><p class="text-xl font-bold text-emerald-600 mt-1">SAR {{ number_format($totalInflow, 2) }}</p></div>
    <div class="bg-white rounded-xl border border-slate-100 p-5"><p class="text-xs text-slate-400">{{ __('Cash out') }}</p><p class="text-xl font-bold text-red-600 mt-1">SAR {{ number_format($totalOutflow, 2) }}</p></div>
    <div class="bg-white rounded-xl border border-slate-100 p-5"><p class="text-xs text-slate-400">{{ __('Closing balance') }}</p><p class="text-xl font-bold text-slate-900 mt-1">SAR {{ number_format($closingTotal, 2) }}</p></div>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-5 py-3 font-medium">{{ __('Account') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Opening') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Cash in') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Cash out') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Closing') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-5 py-3 font-medium text-slate-800">{{ $row['account']->label() }}</td>
                    <td class="px-5 py-3 text-end">{{ number_format($row['opening'], 2) }}</td>
                    <td class="px-5 py-3 text-end text-emerald-600">{{ $row['inflow'] > 0 ? number_format($row['inflow'], 2) : '—' }}</td>
                    <td class="px-5 py-3 text-end text-red-600">{{ $row['outflow'] > 0 ? number_format($row['outflow'], 2) : '—' }}</td>
                    <td class="px-5 py-3 text-end font-semibold">{{ number_format($row['closing'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
