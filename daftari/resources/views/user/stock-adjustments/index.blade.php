@extends('layouts.app')

@section('title', __('Stock adjustments'))

@section('content')
@php($canAdjust = $items->isNotEmpty())

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ __('Inventory Adjustments') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Correct counted stock quantities') }}</p>
    </div>
    @if ($canAdjust)
        <button type="button" onclick="document.getElementById('adjust-modal').showModal()" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ Record adjustment') }}</button>
    @else
        <a href="{{ route('app.items.index') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Go to Items') }}</a>
    @endif
</div>

@unless ($canAdjust)
    <div class="bg-white rounded-xl border border-slate-100 p-4 mb-6">
        <p class="text-sm text-slate-500">{{ __('No items are tracked for inventory yet. Enable "Track inventory" on an item to adjust its stock.') }}</p>
    </div>
@endunless

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

@if ($canAdjust)
<dialog id="adjust-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-md backdrop:bg-slate-900/40">
    <form method="POST" action="{{ route('app.stock-adjustments.store') }}" class="p-6 space-y-4">
        @csrf
        <div class="flex items-start justify-between">
            <h3 class="text-lg font-bold text-slate-900">{{ __('Record adjustment') }}</h3>
            <button type="button" onclick="document.getElementById('adjust-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Item') }}</label>
            <select name="item_id" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('Select tracked item') }}</option>
                @foreach ($items as $item)
                    <option value="{{ $item->id }}">{{ $item->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Warehouse') }}</label>
            <select name="warehouse_id" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('Select a warehouse') }}</option>
                @foreach ($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" @selected($warehouse->is_default)>{{ $warehouse->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500 mb-2">{{ __('Type') }}</label>
            <input type="hidden" name="type" id="adjust-type" value="increase">
            <div class="grid grid-cols-2 gap-3">
                <button type="button" data-type="increase" class="adjust-type-btn rounded-lg border py-2.5 font-semibold flex items-center justify-center gap-2 border-emerald-500 bg-emerald-50 text-emerald-700">
                    <span>↗</span>{{ __('Increase') }}
                </button>
                <button type="button" data-type="decrease" class="adjust-type-btn rounded-lg border border-slate-200 py-2.5 font-semibold text-slate-600 flex items-center justify-center gap-2">
                    <span>−</span>{{ __('Decrease') }}
                </button>
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Quantity') }}</label>
            <input type="number" step="0.01" min="0.01" name="quantity" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Reason') }}</label>
            <input type="text" name="reason" placeholder="{{ __('e.g. stock count correction, damaged goods') }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>

        <div class="flex gap-3 pt-2">
            <button type="button" onclick="document.getElementById('adjust-modal').close()" class="flex-1 rounded-lg border border-slate-200 px-4 py-2.5 font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</button>
            <button type="submit" class="flex-1 rounded-lg bg-brand-800 px-4 py-2.5 font-semibold text-white hover:bg-brand-900">{{ __('Record adjustment') }}</button>
        </div>
    </form>
</dialog>

<script>
(function () {
    const buttons = document.querySelectorAll('.adjust-type-btn');
    const typeInput = document.getElementById('adjust-type');

    buttons.forEach(btn => btn.addEventListener('click', () => {
        typeInput.value = btn.dataset.type;
        buttons.forEach(b => {
            const active = b === btn;
            b.classList.toggle('border-emerald-500', active);
            b.classList.toggle('bg-emerald-50', active);
            b.classList.toggle('text-emerald-700', active);
            b.classList.toggle('border-slate-200', ! active);
            b.classList.toggle('text-slate-600', ! active);
        });
    }));
})();
</script>
@endif
@endsection
