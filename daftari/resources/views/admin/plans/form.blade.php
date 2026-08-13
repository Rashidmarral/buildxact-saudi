@extends('layouts.admin')

@section('title', $plan->exists ? __('Edit Plan') : __('New Plan'))

@section('content')
<form method="POST" action="{{ $plan->exists ? route('admin.plans.update', $plan) : route('admin.plans.store') }}" class="max-w-2xl bg-white rounded-xl border border-slate-100 p-6 space-y-5">
    @csrf
    @if ($plan->exists) @method('PUT') @endif

    <div class="grid sm:grid-cols-2 gap-5">
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Name') }}</label>
            <input type="text" name="name" value="{{ old('name', $plan->name) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Name (Arabic)') }}</label>
            <input type="text" name="name_ar" value="{{ old('name_ar', $plan->name_ar) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Monthly price (SAR)') }}</label>
            <input type="number" step="0.01" min="0" name="price_monthly" value="{{ old('price_monthly', $plan->price_monthly) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Yearly price (SAR)') }}</label>
            <input type="number" step="0.01" min="0" name="price_yearly" value="{{ old('price_yearly', $plan->price_yearly) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Max users (blank = unlimited)') }}</label>
            <input type="number" min="1" name="max_users" value="{{ old('max_users', $plan->max_users) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Max invoices/month (blank = unlimited)') }}</label>
            <input type="number" min="1" name="max_invoices_per_month" value="{{ old('max_invoices_per_month', $plan->max_invoices_per_month) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Sort order') }}</label>
            <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $plan->sort_order ?? 0) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-slate-700">{{ __('Feature list (one per line)') }}</label>
            <textarea name="features" rows="4" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('features', is_array($plan->features) ? implode("\n", $plan->features) : '') }}</textarea>
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $plan->is_active ?? true)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            {{ __('Visible on pricing page') }}
        </label>
    </div>

    <div class="flex gap-3 pt-2">
        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        <a href="{{ route('admin.plans.index') }}" class="rounded-lg border border-slate-200 px-6 py-2.5 font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
