@extends('layouts.app')

@section('title', __('New stock adjustment'))

@section('content')
@if ($items->isEmpty())
    <div class="bg-white rounded-xl border border-slate-100 p-8 text-center">
        <p class="text-sm text-slate-500 mb-4">{{ __('No items are tracked for inventory yet. Enable "Track inventory" on an item to adjust its stock.') }}</p>
        <a href="{{ route('app.items.index') }}" class="inline-block rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Go to Items') }}</a>
    </div>
@else
    <form method="POST" action="{{ route('app.stock-adjustments.store') }}" class="max-w-xl space-y-6">
        @csrf

        <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-5">
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Item') }}</label>
                <select name="item_id" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    <option value="">{{ __('Select an item') }}</option>
                    @foreach ($items as $item)
                        <option value="{{ $item->id }}" @selected(old('item_id') == $item->id)>{{ $item->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Warehouse') }}</label>
                <select name="warehouse_id" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    <option value="">{{ __('Select a warehouse') }}</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected(old('warehouse_id') == $warehouse->id)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Type') }}</label>
                <div class="flex items-center gap-6">
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="radio" name="type" value="increase" checked class="text-brand-600 focus:ring-brand-500">
                        {{ __('Increase') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="radio" name="type" value="decrease" class="text-brand-600 focus:ring-brand-500">
                        {{ __('Decrease') }}
                    </label>
                </div>
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Quantity') }}</label>
                    <input type="number" step="0.01" min="0.01" name="quantity" value="{{ old('quantity') }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Date') }}</label>
                    <input type="date" name="date" value="{{ old('date', now()->toDateString()) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Reason (optional)') }}</label>
                <input type="text" name="reason" value="{{ old('reason') }}" placeholder="{{ __('e.g. stock count correction, damaged goods, received without a bill') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Notes') }}</label>
                <textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('notes') }}</textarea>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
            <a href="{{ route('app.stock-adjustments.index') }}" class="rounded-lg border border-slate-200 px-6 py-2.5 font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</a>
        </div>
    </form>
@endif
@endsection
