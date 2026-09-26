@extends('layouts.admin')

@section('title', $currency->exists ? __('Edit currency') : __('New currency'))

@section('content')
<form method="POST" action="{{ $currency->exists ? route('admin.currencies.update', $currency) : route('admin.currencies.store') }}" class="max-w-xl bg-white rounded-xl border border-slate-100 p-6 space-y-4">
    @csrf
    @if ($currency->exists) @method('PUT') @endif

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Currency code') }}</label>
            <input type="text" name="code" required maxlength="3" minlength="3" value="{{ old('code', $currency->code) }}" placeholder="SAR" class="mt-1 w-full rounded-lg border border-slate-200 text-sm uppercase focus:border-brand-500 focus:ring-brand-500">
            <p class="text-xs text-slate-400 mt-1">{{ __('3-letter ISO 4217 code.') }}</p>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Currency name') }}</label>
            <input type="text" name="name" required maxlength="255" value="{{ old('name', $currency->name) }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Symbol') }}</label>
            <input type="text" name="symbol" required maxlength="10" value="{{ old('symbol', $currency->symbol) }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Symbol position') }}</label>
            <select name="symbol_position" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="before" @selected(old('symbol_position', $currency->symbol_position ?? 'before') === 'before')>{{ __('Before amount (SAR 100)') }}</option>
                <option value="after" @selected(old('symbol_position', $currency->symbol_position) === 'after')>{{ __('After amount (100 SAR)') }}</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Decimal places') }}</label>
            <input type="number" name="decimal_places" required min="0" max="4" value="{{ old('decimal_places', $currency->decimal_places ?? 2) }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Sort order') }}</label>
            <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $currency->sort_order ?? 0) }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Decimal separator') }}</label>
            <input type="text" name="decimal_separator" required maxlength="1" minlength="1" value="{{ old('decimal_separator', $currency->decimal_separator ?? '.') }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Thousands separator') }}</label>
            <input type="text" name="thousands_separator" required maxlength="1" minlength="1" value="{{ old('thousands_separator', $currency->thousands_separator ?? ',') }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
    </div>

    <div class="space-y-2 pt-2 border-t border-slate-100">
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $currency->exists ? $currency->is_active : true)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            {{ __('Active (selectable by companies)') }}
        </label>
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $currency->is_default ?? false)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            {{ __('Default currency for new companies') }}
        </label>
    </div>

    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save currency') }}</button>
</form>
@endsection
