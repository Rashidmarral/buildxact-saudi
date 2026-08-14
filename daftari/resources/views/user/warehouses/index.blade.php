@extends('layouts.app')

@section('title', __('Warehouses'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ __('Warehouses') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Manage your storage locations') }}</p>
    </div>
    <button type="button" onclick="document.getElementById('add-wh-modal').showModal()" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ Add warehouse') }}</button>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($warehouses->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No warehouses yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Branch') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Address') }}</th>
                    <th class="px-6 py-3 font-medium"></th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($warehouses as $warehouse)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                        <td class="px-6 py-3">
                            <span class="font-medium text-slate-800">{{ $warehouse->name }}</span>
                            @if ($warehouse->is_default)<span class="ms-2 inline-block rounded-full bg-brand-50 text-brand-700 text-xs font-medium px-2.5 py-1">{{ __('Default') }}</span>@endif
                            <p class="text-xs text-slate-400">{{ $warehouse->code }} &middot; {{ $warehouse->name }}</p>
                        </td>
                        <td class="px-6 py-3 text-slate-500">{{ $warehouse->branch->name ?? __('All branches') }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $warehouse->address ?: '—' }}</td>
                        <td class="px-6 py-3">
                            @unless ($warehouse->is_default)
                                <form method="POST" action="{{ route('app.warehouses.make-default', $warehouse) }}">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold text-slate-500 hover:text-brand-700">{{ __('Set as default') }}</button>
                                </form>
                            @endunless
                        </td>
                        <td class="px-6 py-3 text-right space-x-3 rtl:space-x-reverse">
                            <button type="button" class="text-brand-700 hover:underline"
                                data-edit-warehouse
                                data-update-url="{{ route('app.warehouses.update', $warehouse) }}"
                                data-code="{{ $warehouse->code }}"
                                data-branch-id="{{ $warehouse->branch_id }}"
                                data-name="{{ $warehouse->name }}"
                                data-name-ar="{{ $warehouse->name_ar }}"
                                data-address="{{ $warehouse->address }}"
                                data-is-default="{{ $warehouse->is_default ? '1' : '0' }}"
                            >{{ __('Edit') }}</button>
                            <form method="POST" action="{{ route('app.warehouses.destroy', $warehouse) }}" class="inline" onsubmit="return confirm('{{ __('Delete this warehouse?') }}')">
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

<dialog id="add-wh-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-md backdrop:bg-slate-900/40">
    <form method="POST" action="{{ route('app.warehouses.store') }}" class="p-6 space-y-4">
        @csrf
        <div class="flex items-start justify-between">
            <h3 class="text-lg font-bold text-slate-900">{{ __('Add warehouse') }}</h3>
            <button type="button" onclick="document.getElementById('add-wh-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
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
            <label class="block text-xs font-medium text-slate-500">{{ __('Code') }}</label>
            <input type="text" name="code" placeholder="{{ __('Auto-generated if left blank') }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Branch') }}</label>
            <select name="branch_id" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('All branches') }}</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Address') }}</label>
            <input type="text" name="address" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <label class="flex items-center justify-end gap-2 text-sm text-slate-600">
            {{ __('Default for this branch') }}
            <input type="checkbox" name="is_default" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
        </label>
        <div class="flex gap-3">
            <button type="button" onclick="document.getElementById('add-wh-modal').close()" class="flex-1 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</button>
            <button type="submit" class="flex-1 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        </div>
    </form>
</dialog>

<dialog id="edit-wh-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-md backdrop:bg-slate-900/40">
    <form method="POST" id="edit-wh-form" class="p-6 space-y-4">
        @csrf
        @method('PUT')
        <div class="flex items-start justify-between">
            <h3 class="text-lg font-bold text-slate-900">{{ __('Edit warehouse') }}</h3>
            <button type="button" onclick="document.getElementById('edit-wh-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Name') }}</label>
            <input type="text" name="name" id="edit-wh-name" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Name (Arabic)') }}</label>
            <input type="text" name="name_ar" id="edit-wh-name-ar" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Code') }}</label>
            <input type="text" name="code" id="edit-wh-code" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Branch') }}</label>
            <select name="branch_id" id="edit-wh-branch" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('All branches') }}</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Address') }}</label>
            <input type="text" name="address" id="edit-wh-address" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <label class="flex items-center justify-end gap-2 text-sm text-slate-600">
            {{ __('Default for this branch') }}
            <input type="checkbox" name="is_default" id="edit-wh-default" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
        </label>
        <div class="flex gap-3">
            <button type="button" onclick="document.getElementById('edit-wh-modal').close()" class="flex-1 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</button>
            <button type="submit" class="flex-1 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        </div>
    </form>
</dialog>

<script>
document.querySelectorAll('[data-edit-warehouse]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('edit-wh-form').action = btn.dataset.updateUrl;
        document.getElementById('edit-wh-name').value = btn.dataset.name || '';
        document.getElementById('edit-wh-name-ar').value = btn.dataset.nameAr || '';
        document.getElementById('edit-wh-code').value = btn.dataset.code || '';
        document.getElementById('edit-wh-branch').value = btn.dataset.branchId || '';
        document.getElementById('edit-wh-address').value = btn.dataset.address || '';
        document.getElementById('edit-wh-default').checked = btn.dataset.isDefault === '1';
        document.getElementById('edit-wh-modal').showModal();
    });
});
</script>
@endsection
