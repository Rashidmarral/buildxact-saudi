@extends('layouts.admin')

@section('title', $partnerType->exists ? __('Edit partner type') : __('New partner type'))

@section('content')
<form method="POST" action="{{ $partnerType->exists ? route('admin.partner-types.update', $partnerType) : route('admin.partner-types.store') }}" class="max-w-xl bg-white rounded-xl border border-slate-100 p-6 space-y-4">
    @csrf
    @if ($partnerType->exists) @method('PUT') @endif

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Name (English)') }}</label>
            <input type="text" name="name_en" required maxlength="255" value="{{ old('name_en', $partnerType->name_en) }}" placeholder="{{ __('Accountant Partner') }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Name (Arabic)') }}</label>
            <input type="text" name="name_ar" maxlength="255" value="{{ old('name_ar', $partnerType->name_ar) }}" dir="rtl" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div class="sm:col-span-2">
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Slug') }}</label>
            <input type="text" name="slug" maxlength="60" value="{{ old('slug', $partnerType->slug) }}" placeholder="accountant-partner" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <p class="text-xs text-slate-400 mt-1">{{ __('Leave blank to generate automatically from the English name.') }}</p>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Commission type') }}</label>
            <select name="commission_type" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="percentage" @selected(old('commission_type', $partnerType->commission_type ?? 'percentage') === 'percentage')>{{ __('Percentage of subscription') }}</option>
                <option value="fixed" @selected(old('commission_type', $partnerType->commission_type) === 'fixed')>{{ __('Fixed amount') }}</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Commission value') }}</label>
            <input type="number" step="0.01" min="0" name="commission_value" required value="{{ old('commission_value', $partnerType->commission_value) }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <p class="text-xs text-slate-400 mt-1">{{ __('A percentage (e.g. 10 for 10%) or a fixed currency amount, depending on the type above.') }}</p>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Sort order') }}</label>
            <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $partnerType->sort_order ?? 0) }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
    </div>

    <div class="space-y-2 pt-2 border-t border-slate-100">
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="is_recurring" value="1" @checked(old('is_recurring', $partnerType->is_recurring ?? false)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            {{ __('Recurring — commission applies on every renewal, not just the first payment') }}
        </label>
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $partnerType->exists ? $partnerType->is_active : true)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            {{ __('Active (selectable when approving a partner application)') }}
        </label>
    </div>

    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save partner type') }}</button>
</form>
@endsection
