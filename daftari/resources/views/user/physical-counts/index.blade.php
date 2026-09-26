@extends('layouts.app')

@section('title', __('Physical Inventory Count'))

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('Physical Inventory Count') }}</h1>
    <p class="text-sm text-slate-500 mt-1">{{ __('Count a warehouse\'s stock and reconcile it in one pass — only items whose count differs from the system quantity get an adjustment.') }}</p>
</div>

<div class="bg-white rounded-xl border border-slate-100 p-6 mb-4">
    <form method="GET" action="{{ route('app.physical-counts.index') }}" class="max-w-xs">
        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Warehouse') }}</label>
        <select name="warehouse_id" onchange="this.form.submit()" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            @foreach ($warehouses as $w)
                <option value="{{ $w->id }}" @selected($warehouse && $warehouse->id === $w->id)>{{ $w->name }}</option>
            @endforeach
        </select>
    </form>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if (! $warehouse)
        <p class="px-6 py-16 text-center text-sm text-slate-500">{{ __('Add a warehouse first to start a physical count.') }}</p>
    @elseif ($rows->isEmpty())
        <p class="px-6 py-16 text-center text-sm text-slate-500">{{ __('No tracked items to count.') }}</p>
    @else
        <form method="POST" action="{{ route('app.physical-counts.store') }}" class="p-6 space-y-4">
            @csrf
            <input type="hidden" name="warehouse_id" value="{{ $warehouse->id }}">

            @error('items')
                <p class="text-xs text-red-600 bg-red-50 rounded-lg px-3 py-2">{{ $message }}</p>
            @enderror

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 border-b border-slate-100">
                            <th class="py-3 pe-3 font-medium">{{ __('Item') }}</th>
                            <th class="py-3 pe-3 font-medium">{{ __('SKU') }}</th>
                            <th class="py-3 pe-3 font-medium">{{ __('System quantity') }}</th>
                            <th class="py-3 font-medium">{{ __('Counted quantity') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $i => $row)
                            <tr class="border-b border-slate-50 last:border-0">
                                <td class="py-3 pe-3 font-medium text-slate-800">{{ $row['item']->name }}</td>
                                <td class="py-3 pe-3 text-slate-500">{{ $row['item']->sku }}</td>
                                <td class="py-3 pe-3 count-system" data-value="{{ $row['system_quantity'] }}">{{ rtrim(rtrim(number_format($row['system_quantity'], 2), '0'), '.') }}</td>
                                <td class="py-3">
                                    <input type="hidden" name="items[{{ $i }}][item_id]" value="{{ $row['item']->id }}">
                                    <input type="number" step="0.01" min="0" name="items[{{ $i }}][counted_quantity]" value="{{ $row['system_quantity'] }}" class="count-input w-28 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <button type="submit" class="rounded-lg bg-brand-800 px-5 py-2.5 font-semibold text-white hover:bg-brand-900">{{ __('Save count and adjust stock') }}</button>
        </form>
    @endif
</div>
@endsection
