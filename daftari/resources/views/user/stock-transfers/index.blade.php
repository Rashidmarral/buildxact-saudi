@extends('layouts.app')

@section('title', __('Stock transfers'))

@section('content')
@php($canTransfer = $items->isNotEmpty() && $warehouses->count() >= 2)

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ __('Stock Transfers') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Move stock between your warehouses') }}</p>
    </div>
    @if ($canTransfer)
        <button type="button" onclick="document.getElementById('transfer-modal').showModal()" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New transfer') }}</button>
    @endif
</div>

@unless ($canTransfer)
    <div class="bg-white rounded-xl border border-slate-100 p-4 mb-6">
        <p class="text-sm text-slate-500">
            @if ($warehouses->count() < 2)
                {{ __('You need at least two warehouses to transfer stock between them.') }}
                <a href="{{ route('app.warehouses.index') }}" class="text-brand-700 font-semibold hover:underline">{{ __('Add another warehouse') }}</a>
            @else
                {{ __('No items are tracked for inventory yet. Enable "Track inventory" on an item to transfer its stock.') }}
            @endif
        </p>
    </div>
@endunless

<div class="bg-white rounded-xl border border-slate-100">
    @if ($transfers->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No stock transfers yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Item') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('From') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('To') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Quantity') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($transfers as $transfer)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                        <td class="px-6 py-3">{{ $transfer->date->format('Y-m-d') }}</td>
                        <td class="px-6 py-3">{{ $transfer->item->name }}</td>
                        <td class="px-6 py-3">{{ $transfer->fromWarehouse->name }}</td>
                        <td class="px-6 py-3">{{ $transfer->toWarehouse->name }}</td>
                        <td class="px-6 py-3">{{ rtrim(rtrim(number_format($transfer->quantity, 2), '0'), '.') }}</td>
                        <td class="px-6 py-3">
                            @if ($transfer->status === 'reversed')
                                <span class="inline-block rounded-full bg-red-50 text-red-600 text-xs font-medium px-2.5 py-1">{{ __('Reversed') }}</span>
                            @else
                                <span class="inline-block rounded-full bg-slate-100 text-slate-600 text-xs font-medium px-2.5 py-1">{{ __('Recorded') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-right">
                            @if ($transfer->status === 'recorded')
                                <form method="POST" action="{{ route('app.stock-transfers.reverse', $transfer) }}" onsubmit="return confirm('{{ __('Reverse this transfer?') }}')">
                                    @csrf
                                    <button type="submit" class="text-red-600 hover:underline text-xs font-semibold">{{ __('Reverse') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
<div class="mt-4">{{ $transfers->links() }}</div>

@if ($canTransfer)
<dialog id="transfer-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-md backdrop:bg-slate-900/40">
    <form method="POST" action="{{ route('app.stock-transfers.store') }}" class="p-6 space-y-4">
        @csrf
        <div class="flex items-start justify-between">
            <h3 class="text-lg font-bold text-slate-900">{{ __('New transfer') }}</h3>
            <button type="button" onclick="document.getElementById('transfer-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>

        @error('quantity')
            <p class="text-xs text-red-600 bg-red-50 rounded-lg px-3 py-2">{{ $message }}</p>
        @enderror

        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Item') }}</label>
            <select name="item_id" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('Select tracked item') }}</option>
                @foreach ($items as $item)
                    <option value="{{ $item->id }}">{{ $item->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('From') }}</label>
                <select name="from_warehouse_id" id="from_warehouse_id" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">{{ __('Select a warehouse') }}</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected($warehouse->is_default)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('To') }}</label>
                <select name="to_warehouse_id" id="to_warehouse_id" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">{{ __('Select a warehouse') }}</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Quantity') }}</label>
            <input type="number" step="0.01" min="0.01" name="quantity" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Notes') }}</label>
            <input type="text" name="notes" placeholder="{{ __('e.g. rebalancing stock ahead of a promotion') }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>

        <div class="flex gap-3 pt-2">
            <button type="button" onclick="document.getElementById('transfer-modal').close()" class="flex-1 rounded-lg border border-slate-200 px-4 py-2.5 font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</button>
            <button type="submit" class="flex-1 rounded-lg bg-brand-800 px-4 py-2.5 font-semibold text-white hover:bg-brand-900">{{ __('Transfer') }}</button>
        </div>
    </form>
</dialog>

<script>
(function () {
    const from = document.getElementById('from_warehouse_id');
    const to = document.getElementById('to_warehouse_id');

    function preventSameWarehouse(changed, other) {
        if (changed.value && changed.value === other.value) {
            [...other.options].forEach(opt => {
                if (opt.value === changed.value) opt.disabled = true;
            });
        }
    }

    [[from, to], [to, from]].forEach(([a, b]) => {
        a.addEventListener('change', () => {
            [...b.options].forEach(opt => { opt.disabled = false; });
            preventSameWarehouse(a, b);
        });
    });
})();
</script>
@endif

@if ($errors->any())
    <script>document.getElementById('transfer-modal')?.showModal();</script>
@endif
@endsection
