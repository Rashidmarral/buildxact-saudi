@extends('layouts.app')

@section('title', $item->exists ? __('Edit Item') : __('New Item'))

@section('content')
<form method="POST" action="{{ $item->exists ? route('app.items.update', $item) : route('app.items.store') }}" class="max-w-2xl bg-white rounded-xl border border-slate-100 p-6 space-y-5">
    @csrf
    @if ($item->exists) @method('PUT') @endif

    <div class="grid sm:grid-cols-2 gap-5">
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Name') }}</label>
            <input type="text" name="name" value="{{ old('name', $item->name) }}" required class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Name (Arabic)') }}</label>
            <input type="text" name="name_ar" value="{{ old('name_ar', $item->name_ar) }}" class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Unit price (SAR)') }}</label>
            <input type="number" step="0.01" min="0" name="unit_price" value="{{ old('unit_price', $item->unit_price) }}" required class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('VAT rate (%)') }}</label>
            <input type="number" step="0.01" min="0" max="100" name="vat_rate" value="{{ old('vat_rate', $item->vat_rate ?? 15) }}" required class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Unit') }}</label>
            <input type="text" name="unit" value="{{ old('unit', $item->unit ?? 'unit') }}" class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('SKU') }}</label>
            <input type="text" name="sku" value="{{ old('sku', $item->sku) }}" class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-slate-700">{{ __('Description') }}</label>
            <textarea name="description" rows="3" class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('description', $item->description) }}</textarea>
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active ?? true)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            {{ __('Active') }}
        </label>
    </div>

    <div class="flex gap-3 pt-2">
        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        <a href="{{ route('app.items.index') }}" class="rounded-lg border border-slate-200 px-6 py-2.5 font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
