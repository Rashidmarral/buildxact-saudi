@extends('layouts.app')

@section('title', __('Inventory Reports'))

@section('content')
@include('user.inventory.partials.tabs')

<button type="button" onclick="location.reload()" class="w-full rounded-lg border border-slate-200 bg-white py-3 text-sm font-semibold text-slate-600 hover:border-slate-300 mb-4">{{ __('Refresh') }}</button>

<div class="bg-white rounded-xl border border-slate-100 overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-6 py-3 font-medium">{{ __('Item') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('SKU') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Warehouse') }}</th>
                <th class="px-6 py-3 font-medium text-right">{{ __('Qty on hand') }}</th>
                <th class="px-6 py-3 font-medium text-right">{{ __('Avg cost') }}</th>
                <th class="px-6 py-3 font-medium text-right">{{ __('Total value') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                    <td class="px-6 py-3 font-medium text-slate-800">{{ $row['item']->name }}</td>
                    <td class="px-6 py-3 text-slate-500">{{ $row['item']->sku ?: '—' }}</td>
                    <td class="px-6 py-3 text-slate-500">{{ $row['warehouse']->name }}</td>
                    <td class="px-6 py-3 text-right">{{ rtrim(rtrim(number_format($row['quantity'], 2), '0'), '.') }}</td>
                    <td class="px-6 py-3 text-right">SAR {{ number_format($row['avg_cost'], 2) }}</td>
                    <td class="px-6 py-3 text-right">SAR {{ number_format($row['total_value'], 2) }}</td>
                </tr>
            @endforeach
            <tr class="border-t border-slate-200 font-semibold text-slate-900">
                <td class="px-6 py-3" colspan="5">{{ __('Grand total') }}</td>
                <td class="px-6 py-3 text-right">SAR {{ number_format($grandTotal, 2) }}</td>
            </tr>
        </tbody>
    </table>
</div>
<p class="text-xs text-slate-400 mt-3">{{ __('Average cost is the purchase price set on each item — link items to their real cost to keep this report accurate.') }}</p>
@endsection
