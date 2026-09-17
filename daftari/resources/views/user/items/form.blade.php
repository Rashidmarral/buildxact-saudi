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
                <input type="text" id="barcode" name="barcode" value="{{ old('barcode', $item->barcode) }}" placeholder="{{ __('Enter, scan, or generate barcode') }}" class="w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <button type="button" id="scan-barcode" class="shrink-0 rounded-lg border border-slate-200 px-4 text-sm font-semibold text-slate-600 hover:border-brand-300">{{ __('Scan') }}</button>
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

        <div class="sm:col-span-2">
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Base unit') }}</label>
            <select name="base_unit_id" id="base-unit-select" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('None (defaults to Piece / PCE)') }}</option>
                @foreach ($units as $unit)
                    <option value="{{ $unit->id }}" @selected((int) old('base_unit_id', $item->base_unit_id) === $unit->id)>{{ $unit->label() }}@if($unit->code) — {{ $unit->code }}@endif</option>
                @endforeach
            </select>
            <p class="text-xs text-slate-400 mt-1">{{ __('The unit stock is tracked and priced in, e.g. Ton.') }} <a href="{{ route('app.units.index') }}" target="_blank" class="text-brand-600 hover:underline">{{ __('Manage units') }}</a></p>
        </div>

        <div class="sm:col-span-2 rounded-lg border border-slate-200 p-4">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Alternate units') }}</label>
                    <p class="text-xs text-slate-400">{{ __('Sell or buy this item in other units too — e.g. base unit Ton, alternate unit Bag with a conversion factor of 40 (1 Ton = 40 Bags).') }}</p>
                </div>
                <button type="button" id="add-alt-unit" class="shrink-0 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-brand-300">{{ __('+ Add alternate unit') }}</button>
            </div>
            <div id="alt-units-rows" class="space-y-2"></div>
            <p id="alt-units-empty" class="text-xs text-slate-400">{{ __('No alternate units added.') }}</p>
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Default tax category') }}</label>
            @php
                $defaultVatRate = $item->vat_rate ?? \App\Models\TaxRate::defaultRate(auth()->user()->company_id);
            @endphp
            <select name="vat_rate" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                @foreach ($taxRates as $taxRate)
                    <option value="{{ $taxRate->rate }}" @selected((float) old('vat_rate', $defaultVatRate) === (float) $taxRate->rate)>{{ $taxRate->name }} ({{ number_format((float) $taxRate->rate, 2) }}%)</option>
                @endforeach
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
            <label class="flex items-center gap-2 text-sm text-slate-600" id="is-kit-wrap">
                <input type="checkbox" name="is_kit" id="is-kit-checkbox" value="1" @checked(old('is_kit', $item->is_kit ?? false)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                {{ __('This is a kit (bundle of other items)') }}
            </label>
        </div>

        <div class="sm:col-span-2" id="tracking-type-wrap">
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Batch tracking') }}</label>
            <select name="tracking_type" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="none" @selected(old('tracking_type', $item->tracking_type ?? 'none') === 'none')>{{ __('Not tracked') }}</option>
                <option value="lot" @selected(old('tracking_type', $item->tracking_type ?? 'none') === 'lot')>{{ __('Lot/batch number') }}</option>
                <option value="serial" @selected(old('tracking_type', $item->tracking_type ?? 'none') === 'serial')>{{ __('Serial number (one unit per lot)') }}</option>
            </select>
            <p class="text-xs text-slate-400 mt-1">{{ __('Receive and consume specific lots/serials from the Lots & Serials page once enabled here.') }}</p>
        </div>

        <div class="sm:col-span-2 rounded-lg border border-slate-200 p-4" id="kit-components-wrap">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Kit components') }}</label>
                    <p class="text-xs text-slate-400">{{ __('Selling this kit deducts each component\'s own stock instead of the kit itself — e.g. a "Gift Basket" kit deducts stock of the fruit, box and ribbon it contains.') }}</p>
                </div>
                <button type="button" id="add-kit-component" class="shrink-0 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-brand-300">{{ __('+ Add component') }}</button>
            </div>
            <div id="kit-components-rows" class="space-y-2"></div>
            <p id="kit-components-empty" class="text-xs text-slate-400">{{ __('No components added.') }}</p>
        </div>

        @if ($item->exists && ! $item->is_kit)
            <div class="sm:col-span-2 rounded-lg border border-slate-200 p-4">
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-2">{{ __('Variants') }}</label>
                @if ($item->parentItem)
                    <p class="text-sm text-slate-600">{{ __('This is a variant of') }} <a href="{{ route('app.items.edit', $item->parentItem) }}" class="text-brand-600 hover:underline">{{ $item->parentItem->name }}</a>@if($item->variant_label) — {{ $item->variant_label }}@endif</p>
                @endif

                @php
                    $variantList = $item->variants;
                @endphp
                @if ($variantList->isNotEmpty())
                    <ul class="divide-y divide-slate-100 mb-3">
                        @foreach ($variantList as $variant)
                            <li class="py-2 flex items-center justify-between text-sm">
                                <a href="{{ route('app.items.edit', $variant) }}" class="text-brand-600 hover:underline">{{ $variant->name }}</a>
                                <span class="text-slate-400">{{ $variant->variant_label }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-xs text-slate-400 mb-3">{{ __('No variants yet.') }}</p>
                @endif

                <form method="POST" action="{{ route('app.items.variants.store', $item) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div>
                        <label class="block text-xs text-slate-500">{{ __('New variant label') }}</label>
                        <input type="text" name="variant_label" placeholder="{{ __('e.g. Red / Large') }}" required class="mt-1 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500">{{ __('SKU') }}</label>
                        <input type="text" name="sku" class="mt-1 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <button type="submit" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:border-brand-300">{{ __('+ Add variant') }}</button>
                </form>
                <p class="text-xs text-slate-400 mt-2">{{ __('Each variant is its own item with its own SKU, price and stock — clone this item, then adjust the new one.') }}</p>
            </div>
        @endif
    </div>

    @include('partials.custom-fields')

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
    const trackingTypeWrap = document.getElementById('tracking-type-wrap');
    const trackingTypeSelect = trackingTypeWrap.querySelector('select[name="tracking_type"]');
    const isKitCheckbox = document.getElementById('is-kit-checkbox');
    const kitComponentsWrap = document.getElementById('kit-components-wrap');

    function sync() {
        const isKit = isKitCheckbox.checked;
        kitComponentsWrap.classList.toggle('hidden', ! isKit);

        const isPhysical = physicalRadio.checked && ! isKit;
        trackWrap.classList.toggle('opacity-40', ! isPhysical);
        trackWrap.classList.toggle('pointer-events-none', ! isPhysical);
        if (! isPhysical) trackCheckbox.checked = false;

        const canTrackLots = isPhysical && trackCheckbox.checked;
        trackingTypeWrap.classList.toggle('opacity-40', ! canTrackLots);
        trackingTypeWrap.classList.toggle('pointer-events-none', ! canTrackLots);
        if (! canTrackLots) trackingTypeSelect.value = 'none';
    }

    trackCheckbox.addEventListener('change', sync);
    isKitCheckbox.addEventListener('change', sync);

    serviceRadio.addEventListener('change', sync);
    physicalRadio.addEventListener('change', sync);
    sync();

    document.getElementById('generate-barcode').addEventListener('click', function () {
        fetch('{{ route('app.items.generate-barcode') }}')
            .then(r => r.json())
            .then(data => { document.getElementById('barcode').value = data.barcode; });
    });

    document.getElementById('scan-barcode').addEventListener('click', function () {
        window.DaftariBarcodeScanner.open(function (code) {
            document.getElementById('barcode').value = code;
        }, @json(__('Scan barcode')), @json(__('Point the camera at the item\'s barcode.')));
    });
})();

@php
    $unitOptionsData = $units->map(fn ($u) => ['id' => $u->id, 'label' => $u->label()])->values();
    $existingAltUnitsData = $item->exists
        ? $item->itemUnits->map(fn ($iu) => [
            'unit_id' => $iu->unit_id,
            'conversion_factor' => (float) $iu->conversion_factor,
            'unit_price' => $iu->unit_price !== null ? (float) $iu->unit_price : '',
        ])->values()
        : collect();
@endphp
(function () {
    const UNITS = @json($unitOptionsData);
    const EXISTING = @json($existingAltUnitsData);

    const rowsWrap = document.getElementById('alt-units-rows');
    const emptyHint = document.getElementById('alt-units-empty');
    let rowIndex = 0;

    function unitOptions(selectedId) {
        return UNITS.map(u => `<option value="${u.id}" ${String(u.id) === String(selectedId) ? 'selected' : ''}>${u.label}</option>`).join('');
    }

    function refreshEmptyHint() {
        emptyHint.classList.toggle('hidden', rowsWrap.children.length > 0);
    }

    function addRow(data) {
        data = data || {};
        const i = rowIndex++;
        const row = document.createElement('div');
        row.className = 'grid grid-cols-12 gap-2 items-center';
        row.innerHTML = `
            <select name="alt_units[${i}][unit_id]" class="col-span-5 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('Select unit') }}</option>
                ${unitOptions(data.unit_id)}
            </select>
            <input type="number" step="0.0001" min="0.0001" name="alt_units[${i}][conversion_factor]" value="${data.conversion_factor ?? ''}" placeholder="{{ __('Factor, e.g. 40') }}" class="col-span-3 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <input type="number" step="0.01" min="0" name="alt_units[${i}][unit_price]" value="${data.unit_price ?? ''}" placeholder="{{ __('Price (optional)') }}" class="col-span-3 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <button type="button" class="col-span-1 text-red-500 hover:text-red-700" title="{{ __('Remove') }}">✕</button>
        `;
        row.querySelector('button').addEventListener('click', () => {
            row.remove();
            refreshEmptyHint();
        });
        rowsWrap.appendChild(row);
        refreshEmptyHint();
    }

    document.getElementById('add-alt-unit').addEventListener('click', () => addRow());

    EXISTING.forEach(addRow);
    refreshEmptyHint();
})();

@php
    $kitCandidatesData = $kitCandidates->map(fn ($i) => ['id' => $i->id, 'label' => $i->name])->values();
    $existingKitComponentsData = $item->exists
        ? $item->kitComponents->map(fn ($c) => [
            'component_item_id' => $c->component_item_id,
            'quantity' => (float) $c->quantity,
        ])->values()
        : collect();
@endphp
(function () {
    const CANDIDATES = @json($kitCandidatesData);
    const EXISTING = @json($existingKitComponentsData);

    const rowsWrap = document.getElementById('kit-components-rows');
    const emptyHint = document.getElementById('kit-components-empty');
    let rowIndex = 0;

    function itemOptions(selectedId) {
        return CANDIDATES.map(i => `<option value="${i.id}" ${String(i.id) === String(selectedId) ? 'selected' : ''}>${i.label}</option>`).join('');
    }

    function refreshEmptyHint() {
        emptyHint.classList.toggle('hidden', rowsWrap.children.length > 0);
    }

    function addRow(data) {
        data = data || {};
        const i = rowIndex++;
        const row = document.createElement('div');
        row.className = 'grid grid-cols-12 gap-2 items-center';
        row.innerHTML = `
            <select name="kit_components[${i}][component_item_id]" class="col-span-8 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('Select item') }}</option>
                ${itemOptions(data.component_item_id)}
            </select>
            <input type="number" step="0.01" min="0.01" name="kit_components[${i}][quantity]" value="${data.quantity ?? ''}" placeholder="{{ __('Qty per kit') }}" class="col-span-3 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <button type="button" class="col-span-1 text-red-500 hover:text-red-700" title="{{ __('Remove') }}">✕</button>
        `;
        row.querySelector('button').addEventListener('click', () => {
            row.remove();
            refreshEmptyHint();
        });
        rowsWrap.appendChild(row);
        refreshEmptyHint();
    }

    document.getElementById('add-kit-component').addEventListener('click', () => addRow());

    EXISTING.forEach(addRow);
    refreshEmptyHint();
})();
</script>
@endsection
