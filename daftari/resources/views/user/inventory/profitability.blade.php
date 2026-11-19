@extends('layouts.app')

@section('title', __('Inventory Reports'))

@section('content')
@include('user.inventory.partials.tabs')

<div class="flex items-center justify-end mb-4">
    <a href="{{ route('app.inventory.profitability', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
</div>

@include('user.reports.partials.period-selector')

<div class="bg-white rounded-xl border border-slate-100 overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-6 py-3 font-medium">{{ __('Item') }}</th>
                <th class="px-6 py-3 font-medium text-end">{{ __('Qty sold') }}</th>
                <th class="px-6 py-3 font-medium text-end">{{ __('Revenue') }}</th>
                <th class="px-6 py-3 font-medium text-end">{{ __('Cost') }}</th>
                <th class="px-6 py-3 font-medium text-end">{{ __('Margin') }}</th>
                <th class="px-6 py-3 font-medium text-end">{{ __('Margin %') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                    <td class="px-6 py-3 font-medium text-slate-800">{{ $row['item']->name }}</td>
                    <td class="px-6 py-3 text-end">{{ rtrim(rtrim(number_format($row['quantity'], 2), '0'), '.') }}</td>
                    <td class="px-6 py-3 text-end">{{ \App\Support\Money::format($row['revenue']) }}</td>
                    <td class="px-6 py-3 text-end">{{ \App\Support\Money::format($row['cost']) }}</td>
                    <td class="px-6 py-3 text-end {{ $row['margin'] < 0 ? 'text-red-600 font-semibold' : 'text-emerald-700 font-medium' }}">{{ \App\Support\Money::format($row['margin']) }}</td>
                    <td class="px-6 py-3 text-end {{ $row['margin'] < 0 ? 'text-red-600 font-semibold' : '' }}">{{ number_format($row['margin_percent'], 1) }}%</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-6 py-10 text-center text-slate-400">{{ __('No items were sold in this period.') }}</td></tr>
            @endforelse
        </tbody>
        @if ($rows->isNotEmpty())
            <tfoot>
                <tr class="border-t-2 border-slate-800 font-semibold text-slate-900">
                    <td class="px-6 py-3" colspan="2">{{ __('Total') }}</td>
                    <td class="px-6 py-3 text-end">{{ \App\Support\Money::format($totals['revenue']) }}</td>
                    <td class="px-6 py-3 text-end">{{ \App\Support\Money::format($totals['cost']) }}</td>
                    <td class="px-6 py-3 text-end">{{ \App\Support\Money::format($totals['margin']) }}</td>
                    <td class="px-6 py-3 text-end">{{ $totals['revenue'] > 0 ? number_format($totals['margin'] / $totals['revenue'] * 100, 1) : '0.0' }}%</td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>
<p class="text-xs text-slate-400 mt-3">{{ __('Cost is the item\'s current purchase price × quantity sold — link items to their real cost to keep this report accurate. Revenue and cost exclude VAT.') }}</p>
@endsection
