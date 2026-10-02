@extends('layouts.app')

@section('title', __('Units'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ __('Units') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Manage the units items are sold or stocked in — attach alternate units with a conversion factor from the item form.') }}</p>
    </div>
    <button type="button" onclick="document.getElementById('add-unit-modal').showModal()" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ Add unit') }}</button>
</div>

@if ($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ $errors->first() }}
    </div>
@endif

<div class="bg-white rounded-xl border border-slate-100">
    @if ($units->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No units yet. Add your first unit (e.g. Ton, Bag, Metre).') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Name (Arabic)') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Symbol') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('ZATCA code') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('In use') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($units as $unit)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                        <td class="px-6 py-3 font-medium text-slate-800">{{ $unit->name }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $unit->name_ar ?: '—' }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $unit->symbol ?: '—' }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $unit->code ?: '—' }}</td>
                        <td class="px-6 py-3 text-slate-500">
                            @php($usageCount = $unit->base_items_count + $unit->item_units_count)
                            @if ($usageCount > 0)
                                <span class="inline-block rounded-full bg-slate-100 text-slate-600 text-xs font-medium px-2.5 py-1">{{ __(':count items', ['count' => $usageCount]) }}</span>
                            @else
                                <span class="text-xs text-slate-400">{{ __('Unused') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-right space-x-3 rtl:space-x-reverse">
                            <button type="button" class="text-brand-700 hover:underline"
                                data-edit-unit
                                data-update-url="{{ route('app.units.update', $unit) }}"
                                data-name="{{ $unit->name }}"
                                data-name-ar="{{ $unit->name_ar }}"
                                data-symbol="{{ $unit->symbol }}"
                                data-code="{{ $unit->code }}"
                            >{{ __('Edit') }}</button>
                            <form method="POST" action="{{ route('app.units.destroy', $unit) }}" class="inline" onsubmit="return confirm('{{ __('Delete this unit?') }}')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

@php($unitCodeOptions = $unitCodes)

<dialog id="add-unit-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-md backdrop:bg-slate-900/40">
    <form method="POST" action="{{ route('app.units.store') }}" class="p-6 space-y-4">
        @csrf
        <div class="flex items-start justify-between">
            <h3 class="text-lg font-bold text-slate-900">{{ __('Add unit') }}</h3>
            <button type="button" onclick="document.getElementById('add-unit-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Name') }}</label>
            <input type="text" name="name" placeholder="{{ __('e.g. Ton, Bag, Square Metre') }}" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Name (Arabic)') }}</label>
            <input type="text" name="name_ar" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Symbol') }}</label>
            <input type="text" name="symbol" placeholder="{{ __('e.g. T, bag, m²') }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('ZATCA unit code') }}</label>
            <select name="code" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('None') }}</option>
                @foreach ($unitCodeOptions as $code => $label)
                    <option value="{{ $code }}">{{ $code }} — {{ $label }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-400">{{ __('Used on the ZATCA e-invoice XML for lines sold in this unit. Leave blank to fall back to Piece (PCE).') }}</p>
        </div>
        <div class="flex gap-3">
            <button type="button" onclick="document.getElementById('add-unit-modal').close()" class="flex-1 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</button>
            <button type="submit" class="flex-1 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        </div>
    </form>
</dialog>

<dialog id="edit-unit-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-md backdrop:bg-slate-900/40">
    <form method="POST" id="edit-unit-form" class="p-6 space-y-4">
        @csrf
        @method('PUT')
        <div class="flex items-start justify-between">
            <h3 class="text-lg font-bold text-slate-900">{{ __('Edit unit') }}</h3>
            <button type="button" onclick="document.getElementById('edit-unit-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Name') }}</label>
            <input type="text" name="name" id="edit-unit-name" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Name (Arabic)') }}</label>
            <input type="text" name="name_ar" id="edit-unit-name-ar" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Symbol') }}</label>
            <input type="text" name="symbol" id="edit-unit-symbol" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('ZATCA unit code') }}</label>
            <select name="code" id="edit-unit-code" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('None') }}</option>
                @foreach ($unitCodeOptions as $code => $label)
                    <option value="{{ $code }}">{{ $code }} — {{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-3">
            <button type="button" onclick="document.getElementById('edit-unit-modal').close()" class="flex-1 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</button>
            <button type="submit" class="flex-1 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        </div>
    </form>
</dialog>

<script>
document.querySelectorAll('[data-edit-unit]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('edit-unit-form').action = btn.dataset.updateUrl;
        document.getElementById('edit-unit-name').value = btn.dataset.name || '';
        document.getElementById('edit-unit-name-ar').value = btn.dataset.nameAr || '';
        document.getElementById('edit-unit-symbol').value = btn.dataset.symbol || '';
        document.getElementById('edit-unit-code').value = btn.dataset.code || '';
        document.getElementById('edit-unit-modal').showModal();
    });
});
</script>
@endsection
