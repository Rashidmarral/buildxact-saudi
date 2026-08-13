@extends('layouts.app')

@section('title', __('Stock adjustments'))

@section('content')
<div class="flex flex-wrap items-center gap-2 mb-6 text-sm">
    <a href="{{ route('app.warehouses.index') }}" class="rounded-lg px-3 py-1.5 text-slate-600 hover:bg-slate-100">{{ __('Warehouses') }}</a>
    <a href="{{ route('app.stock-adjustments.index') }}" class="rounded-lg px-3 py-1.5 bg-slate-900 text-white">{{ __('Stock adjustments') }}</a>
    <a href="{{ route('app.inventory.stock') }}" class="rounded-lg px-3 py-1.5 text-slate-600 hover:bg-slate-100">{{ __('Stock levels') }}</a>
</div>

<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500">{{ __('Manual increases and decreases to stock on hand.') }}</p>
    <a href="{{ route('app.stock-adjustments.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New adjustment') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($adjustments->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No stock adjustments yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Item') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Warehouse') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Type') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Quantity') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($adjustments as $adjustment)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                        <td class="px-6 py-3">{{ $adjustment->date->format('Y-m-d') }}</td>
                        <td class="px-6 py-3">{{ $adjustment->item->name }}</td>
                        <td class="px-6 py-3">{{ $adjustment->warehouse->name }}</td>
                        <td class="px-6 py-3">
                            @if ($adjustment->type === 'increase')
                                <span class="inline-block rounded-full bg-brand-50 text-brand-700 text-xs font-medium px-2.5 py-1">{{ __('Increase') }}</span>
                            @else
                                <span class="inline-block rounded-full bg-amber-50 text-amber-700 text-xs font-medium px-2.5 py-1">{{ __('Decrease') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-3">{{ rtrim(rtrim(number_format($adjustment->quantity, 2), '0'), '.') }}</td>
                        <td class="px-6 py-3">
                            @if ($adjustment->status === 'revoked')
                                <span class="inline-block rounded-full bg-red-50 text-red-600 text-xs font-medium px-2.5 py-1">{{ __('Revoked') }}</span>
                            @else
                                <span class="inline-block rounded-full bg-slate-100 text-slate-600 text-xs font-medium px-2.5 py-1">{{ __('Recorded') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-right">
                            @if ($adjustment->status === 'recorded')
                                <form method="POST" action="{{ route('app.stock-adjustments.revoke', $adjustment) }}" onsubmit="return confirm('{{ __('Revoke this adjustment?') }}')">
                                    @csrf
                                    <button type="submit" class="text-red-600 hover:underline text-xs font-semibold">{{ __('Revoke') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
<div class="mt-4">{{ $adjustments->links() }}</div>
@endsection
