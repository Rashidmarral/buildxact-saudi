@extends('layouts.app')

@section('title', $item->exists ? __('Edit Item') : __('New Item'))

@section('content')
<form method="POST" action="{{ $item->exists ? route('app.items.update', $item) : route('app.items.store') }}" enctype="multipart/form-data" class="max-w-2xl bg-white rounded-xl border border-slate-100 p-6 space-y-5">
    @csrf
    @if ($item->exists) @method('PUT') @endif

    <div class="grid sm:grid-cols-2 gap-5">
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Name') }}</label>
            <input type="text" name="name" value="{{ old('name', $item->name) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Name (Arabic)') }}</label>
            <input type="text" name="name_ar" value="{{ old('name_ar', $item->name_ar) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('SKU') }}</label>
            <input type="text" name="sku" value="{{ old('sku', $item->sku) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Barcode') }}</label>
            <div class="mt-1 flex gap-2">
                <input type="text" id="barcode" name="barcode" value="{{ old('barcode', $item->barcode) }}" placeholder="{{ __('Enter or generate barcode') }}" class="w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <button type="button" id="generate-barcode" class="shrink-0 rounded-lg border border-slate-200 px-4 text-sm font-semibold text-slate-600 hover:border-brand-300">{{ __('Generate') }}</button>
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Category') }}</label>
            <input type="text" name="category" value="{{ old('category', $item->category) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Expiry date') }}</label>
            <input type="date" name="expiry_date" value="{{ old('expiry_date', optional($item->expiry_date)->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            <p class="text-xs text-slate-400 mt-1">{{ __('Optional — shows a soft warning on invoices near/after this date.') }}</p>
        </div>

        <div class="sm:col-span-2">
            <label class="block text-xs font-semibold uppercase text-slate-500 mb-2">{{ __('Item type') }}</label>
            <div class="grid grid-cols-2 gap-3">
                <label class="cursor-pointer">
                    <input type="radio" name="item_type" id="type-service" value="service" class="peer sr-only" @checked(old('item_type', $item->item_type ?? 'service') === 'service')>
                    <span class="block text-center rounded-lg border border-slate-200 py-2.5 font-semibold text-slate-600 peer-checked:border-brand-500 peer-checked:bg-brand-50 peer-checked:text-brand-700">{{ __('Service') }}</span>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="item_type" id="type-physical" value="physical" class="peer sr-only" @checked(old('item_type', $item->item_type) === 'physical')>
                    <span class="block text-center rounded-lg border border-slate-200 py-2.5 font-semibold text-slate-600 peer-checked:border-brand-500 peer-checked:bg-brand-50 peer-checked:text-brand-700">{{ __('Physical Product') }}</span>
                </label>
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Sale price') }}</label>
            <input type="number" step="0.01" min="0" name="unit_price" value="{{ old('unit_price', $item->unit_price) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Purchase price') }}</label>
            <input type="number" step="0.01" min="0" name="purchase_price" value="{{ old('purchase_price', $item->purchase_price) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Unit name') }}</label>
            <input type="text" name="unit" value="{{ old('unit', $item->unit ?? 'unit') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Unit code') }}</label>
            <select name="unit_code" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                @foreach (\App\Models\Item::UNIT_CODES as $code => $label)
                    <option value="{{ $code }}" @selected(old('unit_code', $item->unit_code ?? 'PCE') === $code)>{{ $code }} — {{ __($label) }}</option>
                @endforeach
            </select>
            <p class="text-xs text-slate-400 mt-1">{{ __('Used on the ZATCA e-invoice XML for this item.') }}</p>
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Default tax category') }}</label>
            <select name="vat_rate" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="15" @selected((float) old('vat_rate', $item->vat_rate ?? 15) === 15.0)>{{ __('Standard rated 15%') }}</option>
                <option value="0" @selected((float) old('vat_rate', $item->vat_rate ?? 15) === 0.0)>{{ __('Zero rated / Exempt') }}</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Reorder point (optional)') }}</label>
            <input type="number" step="0.01" min="0" name="reorder_point" value="{{ old('reorder_point', $item->reorder_point) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>

        <div class="sm:col-span-2">
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Description') }}</label>
            <textarea name="description" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('description', $item->description) }}</textarea>
        </div>

        <div class="sm:col-span-2">
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Item image') }}</label>
            <div class="mt-1 flex items-center gap-4">
                @if ($item->image_path)
                    <img src="{{ Storage::url($item->image_path) }}" alt="" class="w-16 h-16 rounded-lg object-cover border border-slate-200">
                @endif
                <input type="file" name="image" accept="image/png,image/jpeg,image/webp" class="w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-slate-600 hover:file:bg-slate-200">
            </div>
            <p class="text-xs text-slate-400 mt-1">{{ __('PNG, JPEG, WebP · max 2MB') }}</p>
        </div>

        <div class="sm:col-span-2 flex flex-wrap gap-6">
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active ?? true)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                {{ __('Active') }}
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-600" id="track-inventory-wrap">
                <input type="checkbox" name="track_inventory" value="1" @checked(old('track_inventory', $item->track_inventory ?? false)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                {{ __('Track inventory for this item') }}
            </label>
        </div>
    </div>

    <div class="flex gap-3 pt-2">
        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        <a href="{{ route('app.items.index') }}" class="rounded-lg border border-slate-200 px-6 py-2.5 font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</a>
    </div>
</form>

<script>
(function () {
    const serviceRadio = document.getElementById('type-service');
    const physicalRadio = document.getElementById('type-physical');
    const trackWrap = document.getElementById('track-inventory-wrap');
    const trackCheckbox = trackWrap.querySelector('input[name="track_inventory"]');

    function sync() {
        const isPhysical = physicalRadio.checked;
        trackWrap.classList.toggle('opacity-40', ! isPhysical);
        trackWrap.classList.toggle('pointer-events-none', ! isPhysical);
        if (! isPhysical) trackCheckbox.checked = false;
    }

    serviceRadio.addEventListener('change', sync);
    physicalRadio.addEventListener('change', sync);
    sync();

    document.getElementById('generate-barcode').addEventListener('click', function () {
        fetch('{{ route('app.items.generate-barcode') }}')
            .then(r => r.json())
            .then(data => { document.getElementById('barcode').value = data.barcode; });
    });
})();
</script>
@endsection
