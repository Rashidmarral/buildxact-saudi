@extends('layouts.app')

@section('title', __('Withholding tax categories'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ __('Withholding tax categories') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Rates applied when paying a non-resident supplier. These follow Saudi tax law and can change — check the current rate before relying on the defaults here.') }}</p>
    </div>
    <button type="button" onclick="document.getElementById('add-wht-rate-modal').showModal()" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ Add category') }}</button>
</div>

@if ($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ $errors->first() }}
    </div>
@endif

<div class="bg-white rounded-xl border border-slate-100">
    @if ($whtRates->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No withholding tax categories yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Code') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Rate') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($whtRates as $rate)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                        <td class="px-6 py-3 font-medium text-slate-800">{{ $rate->name }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $rate->code }}</td>
                        <td class="px-6 py-3 text-slate-700">{{ number_format((float) $rate->rate, 2) }}%</td>
                        <td class="px-6 py-3">
                            @if ($rate->is_active)
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ __('Active') }}</span>
                            @else
                                <span class="rounded-full bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-400">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-right space-x-3 rtl:space-x-reverse">
                            <button type="button" class="text-brand-700 hover:underline"
                                data-edit-wht-rate
                                data-update-url="{{ route('app.wht-rates.update', $rate) }}"
                                data-code="{{ $rate->code }}"
                                data-name="{{ $rate->name }}"
                                data-name-ar="{{ $rate->name_ar }}"
                                data-rate="{{ $rate->rate }}"
                                data-is-active="{{ $rate->is_active ? '1' : '0' }}"
                            >{{ __('Edit') }}</button>
                            <form method="POST" action="{{ route('app.wht-rates.destroy', $rate) }}" class="inline" onsubmit="return confirm('{{ __('Delete this category?') }}')">
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

<dialog id="add-wht-rate-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-md backdrop:bg-slate-900/40">
    <form method="POST" action="{{ route('app.wht-rates.store') }}" class="p-6 space-y-4">
        @csrf
        <div class="flex items-start justify-between">
            <h3 class="text-lg font-bold text-slate-900">{{ __('Add category') }}</h3>
            <button type="button" onclick="document.getElementById('add-wht-rate-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Code') }}</label>
            <input type="text" name="code" placeholder="{{ __('e.g. technical_consulting') }}" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Name') }}</label>
            <input type="text" name="name" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Name (Arabic)') }}</label>
            <input type="text" name="name_ar" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Rate') }} (%)</label>
            <input type="number" step="0.01" min="0" max="100" name="rate" required value="0" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            {{ __('Active') }}
        </label>
        <div class="flex gap-3">
            <button type="button" onclick="document.getElementById('add-wht-rate-modal').close()" class="flex-1 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</button>
            <button type="submit" class="flex-1 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        </div>
    </form>
</dialog>

<dialog id="edit-wht-rate-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-md backdrop:bg-slate-900/40">
    <form method="POST" id="edit-wht-rate-form" class="p-6 space-y-4">
        @csrf
        @method('PUT')
        <div class="flex items-start justify-between">
            <h3 class="text-lg font-bold text-slate-900">{{ __('Edit category') }}</h3>
            <button type="button" onclick="document.getElementById('edit-wht-rate-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Code') }}</label>
            <input type="text" name="code" id="edit-wht-rate-code" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Name') }}</label>
            <input type="text" name="name" id="edit-wht-rate-name" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Name (Arabic)') }}</label>
            <input type="text" name="name_ar" id="edit-wht-rate-name-ar" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Rate') }} (%)</label>
            <input type="number" step="0.01" min="0" max="100" name="rate" id="edit-wht-rate-rate" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="is_active" id="edit-wht-rate-active" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            {{ __('Active') }}
        </label>
        <div class="flex gap-3">
            <button type="button" onclick="document.getElementById('edit-wht-rate-modal').close()" class="flex-1 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</button>
            <button type="submit" class="flex-1 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        </div>
    </form>
</dialog>

<script>
document.querySelectorAll('[data-edit-wht-rate]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('edit-wht-rate-form').action = btn.dataset.updateUrl;
        document.getElementById('edit-wht-rate-code').value = btn.dataset.code || '';
        document.getElementById('edit-wht-rate-name').value = btn.dataset.name || '';
        document.getElementById('edit-wht-rate-name-ar').value = btn.dataset.nameAr || '';
        document.getElementById('edit-wht-rate-rate').value = btn.dataset.rate || '0';
        document.getElementById('edit-wht-rate-active').checked = btn.dataset.isActive === '1';
        document.getElementById('edit-wht-rate-modal').showModal();
    });
});
</script>
@endsection
