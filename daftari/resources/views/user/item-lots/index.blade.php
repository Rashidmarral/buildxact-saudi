@extends('layouts.app')

@section('title', __('Lots & serials'))

@section('content')
@php($canReceive = $items->isNotEmpty())

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ __('Lots & Serials') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Track batches and serial numbers for items that need it') }}</p>
    </div>
    @if ($canReceive)
        <button type="button" onclick="document.getElementById('lot-modal').showModal()" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ Receive lot') }}</button>
    @endif
</div>

@unless ($canReceive)
    <div class="bg-white rounded-xl border border-slate-100 p-4 mb-6">
        <p class="text-sm text-slate-500">
            {{ __('No items are set up for lot/serial tracking yet. Enable "Lot tracking" or "Serial tracking" on an item first.') }}
            <a href="{{ route('app.items.index') }}" class="text-brand-700 font-semibold hover:underline">{{ __('Go to Items') }}</a>
        </p>
    </div>
@endunless

<form method="GET" class="mb-4 flex items-center gap-2">
    <select name="item_id" onchange="this.form.submit()" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        <option value="">{{ __('All tracked items') }}</option>
        @foreach ($items as $item)
            <option value="{{ $item->id }}" @selected($selectedItemId === $item->id)>{{ $item->name }}</option>
        @endforeach
    </select>
</form>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($lots->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No lots on hand.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Item') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Lot / Serial') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Warehouse') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Expiry') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Remaining') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lots as $lot)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                        <td class="px-6 py-3">{{ $lot->item->name }}</td>
                        <td class="px-6 py-3">
                            {{ $lot->lot_number }}
                            @if ($lot->serial_number)
                                <span class="block text-xs text-slate-400">{{ __('S/N') }}: {{ $lot->serial_number }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-3">{{ $lot->warehouse->name }}</td>
                        <td class="px-6 py-3">
                            @if ($lot->expiry_date)
                                <span class="{{ $lot->isExpired() ? 'text-red-600 font-semibold' : ($lot->isExpiringSoon() ? 'text-amber-600 font-semibold' : 'text-slate-600') }}">
                                    {{ $lot->expiry_date->format('Y-m-d') }}
                                </span>
                                @if ($lot->isExpired())
                                    <span class="block text-xs text-red-500">{{ __('Expired') }}</span>
                                @elseif ($lot->isExpiringSoon())
                                    <span class="block text-xs text-amber-500">{{ __('Expiring soon') }}</span>
                                @endif
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-3">{{ rtrim(rtrim(number_format($lot->quantity, 2), '0'), '.') }}</td>
                        <td class="px-6 py-3 text-right">
                            <button type="button" onclick="document.getElementById('consume-modal-{{ $lot->id }}').showModal()" class="text-slate-600 hover:underline text-xs font-semibold">{{ __('Consume') }}</button>
                            <dialog id="consume-modal-{{ $lot->id }}" class="rounded-2xl border border-slate-100 p-0 w-full max-w-sm backdrop:bg-slate-900/40">
                                <form method="POST" action="{{ route('app.item-lots.consume', $lot) }}" class="p-6 space-y-4">
                                    @csrf
                                    <div class="flex items-start justify-between">
                                        <h3 class="text-base font-bold text-slate-900">{{ __('Consume from lot :lot', ['lot' => $lot->lot_number]) }}</h3>
                                        <button type="button" onclick="document.getElementById('consume-modal-{{ $lot->id }}').close()" class="text-slate-400 hover:text-slate-600">✕</button>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Quantity') }}</label>
                                        <input type="number" step="0.01" min="0.01" max="{{ $lot->quantity }}" name="quantity" required value="{{ $lot->serial_number ? $lot->quantity : '' }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                                    </div>
                                    <button type="submit" class="w-full rounded-lg bg-brand-800 px-4 py-2.5 font-semibold text-white hover:bg-brand-900">{{ __('Confirm') }}</button>
                                </form>
                            </dialog>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
<div class="mt-4">{{ $lots->links() }}</div>

@if ($canReceive)
<dialog id="lot-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-md backdrop:bg-slate-900/40">
    <form method="POST" action="{{ route('app.item-lots.store') }}" class="p-6 space-y-4">
        @csrf
        <div class="flex items-start justify-between">
            <h3 class="text-lg font-bold text-slate-900">{{ __('Receive lot') }}</h3>
            <button type="button" onclick="document.getElementById('lot-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>

        @error('item_id')
            <p class="text-xs text-red-600 bg-red-50 rounded-lg px-3 py-2">{{ $message }}</p>
        @enderror
        @error('quantity')
            <p class="text-xs text-red-600 bg-red-50 rounded-lg px-3 py-2">{{ $message }}</p>
        @enderror
        @error('serial_number')
            <p class="text-xs text-red-600 bg-red-50 rounded-lg px-3 py-2">{{ $message }}</p>
        @enderror

        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Item') }}</label>
            <select name="item_id" id="lot_item_id" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('Select tracked item') }}</option>
                @foreach ($items as $item)
                    <option value="{{ $item->id }}" data-tracking="{{ $item->tracking_type }}" @selected(old('item_id') == $item->id)>{{ $item->name }}</option>
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
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Lot number') }}</label>
            <input type="text" name="lot_number" required maxlength="60" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>

        <div id="lot_serial_field">
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Serial number') }}</label>
            <input type="text" name="serial_number" maxlength="60" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Quantity') }}</label>
                <input type="number" step="0.01" min="0.01" name="quantity" id="lot_quantity" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Expiry date') }}</label>
                <input type="date" name="expiry_date" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Notes') }}</label>
            <input type="text" name="notes" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>

        <div class="flex gap-3 pt-2">
            <button type="button" onclick="document.getElementById('lot-modal').close()" class="flex-1 rounded-lg border border-slate-200 px-4 py-2.5 font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</button>
            <button type="submit" class="flex-1 rounded-lg bg-brand-800 px-4 py-2.5 font-semibold text-white hover:bg-brand-900">{{ __('Receive') }}</button>
        </div>
    </form>
</dialog>

<script>
(function () {
    const itemSelect = document.getElementById('lot_item_id');
    const serialField = document.getElementById('lot_serial_field');
    const quantity = document.getElementById('lot_quantity');

    function syncForTracking() {
        const option = itemSelect.options[itemSelect.selectedIndex];
        const tracking = option ? option.dataset.tracking : '';

        if (tracking === 'serial') {
            serialField.classList.remove('hidden');
            quantity.value = 1;
            quantity.readOnly = true;
        } else {
            serialField.classList.remove('hidden');
            quantity.readOnly = false;
        }
    }

    itemSelect.addEventListener('change', syncForTracking);
    syncForTracking();
})();
</script>
@endif

@if ($errors->any())
    <script>document.getElementById('lot-modal')?.showModal();</script>
@endif
@endsection
