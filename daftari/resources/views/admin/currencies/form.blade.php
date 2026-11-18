@extends('layouts.admin')

@section('title', $currency->exists ? __('Edit currency') : __('New currency'))

@section('content')
<form method="POST" action="{{ $currency->exists ? route('admin.currencies.update', $currency) : route('admin.currencies.store') }}" class="max-w-xl bg-white rounded-xl border border-slate-100 p-6 space-y-4">
    @csrf
    @if ($currency->exists) @method('PUT') @endif

    <h2 class="font-semibold text-slate-900 mb-4 flex items-center gap-2">
        <span class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-brand-50 text-brand-600">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16ZM10.75 7.5a.75.75 0 0 0-1.5 0v.006c-.323.04-.631.114-.909.242-.556.256-1.091.767-1.091 1.591 0 .822.535 1.252 1.057 1.49.272.125.588.21.943.282v2.153c-.288-.082-.516-.209-.672-.339a.75.75 0 1 0-.96 1.153c.453.377 1.04.622 1.632.733v.006a.75.75 0 0 0 1.5 0v-.006c.323-.04.631-.114.909-.242.556-.256 1.091-.767 1.091-1.591 0-.822-.535-1.252-1.057-1.49a4.39 4.39 0 0 0-.943-.282V8.593c.288.082.516.209.672.339a.75.75 0 1 0 .96-1.153 2.98 2.98 0 0 0-1.632-.733V7.5Z" clip-rule="evenodd"/></svg>
        </span>
        {{ __('Currency details') }}
    </h2>

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
