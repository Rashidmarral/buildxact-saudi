@extends('layouts.admin')

@section('title', $setupPackage->exists ? __('Edit setup package') : __('New setup package'))

@section('content')
<form method="POST" action="{{ $setupPackage->exists ? route('admin.setup-packages.update', $setupPackage) : route('admin.setup-packages.store') }}" class="max-w-2xl bg-white rounded-xl border border-slate-100 p-6 space-y-4">
    @csrf
    @if ($setupPackage->exists) @method('PUT') @endif

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Name (English)') }}</label>
            <input type="text" name="name_en" required maxlength="255" value="{{ old('name_en', $setupPackage->name_en) }}" placeholder="{{ __('ZATCA Onboarding Setup') }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Name (Arabic)') }}</label>
            <input type="text" name="name_ar" maxlength="255" value="{{ old('name_ar', $setupPackage->name_ar) }}" dir="rtl" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div class="sm:col-span-2">
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Slug') }}</label>
            <input type="text" name="slug" maxlength="60" value="{{ old('slug', $setupPackage->slug) }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <p class="text-xs text-slate-400 mt-1">{{ __('Leave blank to generate automatically from the English name.') }}</p>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Description (English)') }}</label>
            <textarea name="description_en" rows="3" maxlength="2000" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">{{ old('description_en', $setupPackage->description_en) }}</textarea>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Description (Arabic)') }}</label>
            <textarea name="description_ar" rows="3" maxlength="2000" dir="rtl" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">{{ old('description_ar', $setupPackage->description_ar) }}</textarea>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Features (English, one per line)') }}</label>
            <textarea name="features_en" rows="4" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">{{ old('features_en', $setupPackage->features_en ? implode("\n", $setupPackage->features_en) : '') }}</textarea>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Features (Arabic, one per line)') }}</label>
            <textarea name="features_ar" rows="4" dir="rtl" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">{{ old('features_ar', $setupPackage->features_ar ? implode("\n", $setupPackage->features_ar) : '') }}</textarea>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Price') }}</label>
            <input type="number" step="0.01" min="0" name="price" value="{{ old('price', $setupPackage->price) }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <p class="text-xs text-slate-400 mt-1">{{ __('Leave blank to show "Contact us for pricing" instead of a fixed price.') }}</p>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Sort order') }}</label>
            <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $setupPackage->sort_order ?? 0) }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
    </div>

    <div class="pt-2 border-t border-slate-100">
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $setupPackage->exists ? $setupPackage->is_active : true)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            {{ __('Active (visible on the public setup packages page)') }}
        </label>
    </div>

    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save setup package') }}</button>
</form>
@endsection
