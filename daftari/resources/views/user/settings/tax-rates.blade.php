@extends('layouts.app')

@section('title', __('Tax rates'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ __('Tax rates') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('The VAT rates available on invoice, bill, and quotation lines. The default rate pre-fills every new line.') }}</p>
    </div>
    <button type="button" onclick="document.getElementById('add-tax-rate-modal').showModal()" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ Add tax rate') }}</button>
</div>

@if ($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ $errors->first() }}
    </div>
@endif

<div class="bg-white rounded-xl border border-slate-100">
    @if ($taxRates->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No tax rates yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Rate') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Type') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Effective date') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($taxRates as $taxRate)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                        <td class="px-6 py-3 font-medium text-slate-800">{{ $taxRate->name }}</td>
                        <td class="px-6 py-3 text-slate-700">{{ number_format((float) $taxRate->rate, 2) }}%</td>
                        <td class="px-6 py-3 text-slate-500">{{ __($types[$taxRate->type] ?? $taxRate->type) }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $taxRate->effective_date?->format('Y-m-d') ?? __('Always') }}</td>
                        <td class="px-6 py-3">
                            @if ($taxRate->is_default)
                                <span class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-700">{{ __('Default') }}</span>
                            @elseif ($taxRate->is_active)
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ __('Active') }}</span>
                            @else
                                <span class="rounded-full bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-400">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-right space-x-3 rtl:space-x-reverse">
                            <button type="button" class="text-brand-700 hover:underline"
                                data-edit-tax-rate
                                data-update-url="{{ route('app.tax-rates.update', $taxRate) }}"
                                data-name="{{ $taxRate->name }}"
                                data-rate="{{ $taxRate->rate }}"
                                data-type="{{ $taxRate->type }}"
                                data-effective-date="{{ optional($taxRate->effective_date)->format('Y-m-d') }}"
                                data-is-active="{{ $taxRate->is_active ? '1' : '0' }}"
                                data-is-default="{{ $taxRate->is_default ? '1' : '0' }}"
                            >{{ __('Edit') }}</button>
                            <form method="POST" action="{{ route('app.tax-rates.destroy', $taxRate) }}" class="inline" onsubmit="return confirm('{{ __('Delete this tax rate?') }}')">
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

@php($taxRateTypes = $types)

<dialog id="add-tax-rate-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-md backdrop:bg-slate-900/40">
    <form method="POST" action="{{ route('app.tax-rates.store') }}" class="p-6 space-y-4">
        @csrf
        <div class="flex items-start justify-between">
            <h3 class="text-lg font-bold text-slate-900">{{ __('Add tax rate') }}</h3>
            <button type="button" onclick="document.getElementById('add-tax-rate-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Name') }}</label>
            <input type="text" name="name" placeholder="{{ __('e.g. VAT 15%') }}" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-slate-500">{{ __('Rate') }} (%)</label>
                <input type="number" step="0.01" min="0" max="100" name="rate" required value="0" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">{{ __('Type') }}</label>
                <select name="type" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    @foreach ($taxRateTypes as $key => $label)
                        <option value="{{ $key }}">{{ __($label) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Effective date (optional)') }}</label>
            <input type="date" name="effective_date" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <p class="mt-1 text-xs text-slate-400">{{ __('Leave blank for a rate that is always in effect.') }}</p>
        </div>
        <div class="space-y-2">
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                {{ __('Active') }}
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="is_default" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                {{ __('Default rate for new lines') }}
            </label>
        </div>
        <div class="flex gap-3">
            <button type="button" onclick="document.getElementById('add-tax-rate-modal').close()" class="flex-1 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</button>
            <button type="submit" class="flex-1 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        </div>
    </form>
</dialog>

<dialog id="edit-tax-rate-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-md backdrop:bg-slate-900/40">
    <form method="POST" id="edit-tax-rate-form" class="p-6 space-y-4">
        @csrf
        @method('PUT')
        <div class="flex items-start justify-between">
            <h3 class="text-lg font-bold text-slate-900">{{ __('Edit tax rate') }}</h3>
            <button type="button" onclick="document.getElementById('edit-tax-rate-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Name') }}</label>
            <input type="text" name="name" id="edit-tax-rate-name" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-slate-500">{{ __('Rate') }} (%)</label>
                <input type="number" step="0.01" min="0" max="100" name="rate" id="edit-tax-rate-rate" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">{{ __('Type') }}</label>
                <select name="type" id="edit-tax-rate-type" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    @foreach ($taxRateTypes as $key => $label)
                        <option value="{{ $key }}">{{ __($label) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Effective date (optional)') }}</label>
            <input type="date" name="effective_date" id="edit-tax-rate-effective-date" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div class="space-y-2">
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="is_active" id="edit-tax-rate-active" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                {{ __('Active') }}
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="is_default" id="edit-tax-rate-default" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                {{ __('Default rate for new lines') }}
            </label>
        </div>
        <div class="flex gap-3">
            <button type="button" onclick="document.getElementById('edit-tax-rate-modal').close()" class="flex-1 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</button>
            <button type="submit" class="flex-1 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        </div>
    </form>
</dialog>

<script>
document.querySelectorAll('[data-edit-tax-rate]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('edit-tax-rate-form').action = btn.dataset.updateUrl;
        document.getElementById('edit-tax-rate-name').value = btn.dataset.name || '';
        document.getElementById('edit-tax-rate-rate').value = btn.dataset.rate || '0';
        document.getElementById('edit-tax-rate-type').value = btn.dataset.type || 'standard';
        document.getElementById('edit-tax-rate-effective-date').value = btn.dataset.effectiveDate || '';
        document.getElementById('edit-tax-rate-active').checked = btn.dataset.isActive === '1';
        document.getElementById('edit-tax-rate-default').checked = btn.dataset.isDefault === '1';
        document.getElementById('edit-tax-rate-modal').showModal();
    });
});
</script>
@endsection
