@extends('layouts.app')

@section('title', __('Barcode & SKU'))

@section('content')
<div class="max-w-2xl space-y-6">
    <div>
        <h1 class="text-lg font-semibold text-slate-900">{{ __('Barcode & SKU') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Entering a SKU or barcode by hand on an item form always works — these settings only control the optional shortcuts: auto-filling a SKU when you leave it blank, and how many digits the "Generate" barcode button hands out.') }}</p>
    </div>

    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('app.settings.barcode-sku.update') }}" class="bg-white rounded-xl border border-slate-100 p-6 space-y-5">
        @csrf
        <div>
            <h3 class="font-semibold text-slate-900 mb-1">{{ __('SKU') }}</h3>
            <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                <input type="checkbox" name="sku_auto_generate" value="1" @checked(old('sku_auto_generate', $company->sku_auto_generate)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                {{ __('Auto-generate a SKU for new items when the SKU field is left blank') }}
            </label>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('SKU prefix') }}</label>
            <input type="text" name="sku_prefix" maxlength="20" value="{{ old('sku_prefix', $company->sku_prefix) }}" placeholder="{{ __('e.g. ITM-') }}" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <p class="text-xs text-slate-400 mt-1">{{ __('A generated SKU looks like :example.', ['example' => ($company->sku_prefix ?: 'ITM-').'00001']) }}</p>
        </div>

        <div class="pt-2 border-t border-slate-100">
            <h3 class="font-semibold text-slate-900 mb-1">{{ __('Barcode') }}</h3>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Generated barcode length (digits)') }}</label>
            <input type="number" name="barcode_length" min="6" max="18" required value="{{ old('barcode_length', $company->barcode_length) }}" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <p class="text-xs text-slate-400 mt-1">{{ __('Used by the "Generate" button on the item form — 12 matches a standard UPC-A/EAN-13 barcode. Scanning or typing a barcode of any length still works regardless of this setting.') }}</p>
        </div>

        <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
    </form>
</div>
@endsection
