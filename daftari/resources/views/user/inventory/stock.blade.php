@extends('layouts.app')

@section('title', __('Stock levels'))

@section('content')
<div class="flex flex-wrap items-center gap-2 mb-6 text-sm">
    <a href="{{ route('app.warehouses.index') }}" class="rounded-lg px-3 py-1.5 text-slate-600 hover:bg-slate-100">{{ __('Warehouses') }}</a>
    <a href="{{ route('app.stock-adjustments.index') }}" class="rounded-lg px-3 py-1.5 text-slate-600 hover:bg-slate-100">{{ __('Stock adjustments') }}</a>
    <a href="{{ route('app.inventory.stock') }}" class="rounded-lg px-3 py-1.5 bg-slate-900 text-white">{{ __('Stock levels') }}</a>
</div>

<p class="text-sm text-slate-500 mb-6">{{ __('Current quantity on hand for tracked items, by warehouse.') }}</p>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($stocks->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No stock recorded yet. Record a stock adjustment to get started.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Item') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Warehouse') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Quantity on hand') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Reorder point') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($stocks as $stock)
                    @php $low = $stock->item->reorder_point !== null && $stock->quantity <= $stock->item->reorder_point; @endphp
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                        <td class="px-6 py-3 font-medium text-slate-800">{{ $stock->item->name }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $stock->warehouse->name }}</td>
                        <td class="px-6 py-3 {{ $low ? 'text-amber-700 font-semibold' : 'text-slate-700' }}">{{ rtrim(rtrim(number_format($stock->quantity, 2), '0'), '.') }} {{ $stock->item->unit }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $stock->item->reorder_point ?? '—' }}</td>
                        <td class="px-6 py-3">
                            @if ($low)
                                <span class="inline-block rounded-full bg-amber-50 text-amber-700 text-xs font-medium px-2.5 py-1">{{ __('Low stock') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
