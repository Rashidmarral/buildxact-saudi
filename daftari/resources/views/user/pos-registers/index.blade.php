@extends('layouts.app')

@section('title', __('POS Registers'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ __('POS Registers') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Each register deducts stock from its own linked warehouse when a sale is rung up.') }}</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('app.pos.terminal') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Open checkout') }}</a>
        <button type="button" onclick="document.getElementById('add-register-modal').showModal()" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ Add register') }}</button>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($registers->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No registers yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Branch') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Warehouse') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($registers as $register)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                        <td class="px-6 py-3 font-medium text-slate-800">{{ $register->name }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $register->branch->name ?? __('All branches') }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $register->warehouse->name ?? '—' }}</td>
                        <td class="px-6 py-3">
                            @if ($register->is_active)
                                <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">{{ __('Active') }}</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-right space-x-3 rtl:space-x-reverse">
                            <button type="button" class="text-brand-700 hover:underline"
                                data-edit-register
                                data-update-url="{{ route('app.pos-registers.update', $register) }}"
                                data-name="{{ $register->name }}"
                                data-branch-id="{{ $register->branch_id }}"
                                data-warehouse-id="{{ $register->warehouse_id }}"
                                data-is-active="{{ $register->is_active ? '1' : '0' }}"
                            >{{ __('Edit') }}</button>
                            <form method="POST" action="{{ route('app.pos-registers.destroy', $register) }}" class="inline" onsubmit="return confirm('{{ __('Delete this register?') }}')">
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

<dialog id="add-register-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-md backdrop:bg-slate-900/40">
    <form method="POST" action="{{ route('app.pos-registers.store') }}" class="p-6 space-y-4">
        @csrf
        <div class="flex items-start justify-between">
            <h3 class="text-lg font-bold text-slate-900">{{ __('Add register') }}</h3>
            <button type="button" onclick="document.getElementById('add-register-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Name') }}</label>
            <input type="text" name="name" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
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
            <label class="block text-xs font-medium text-slate-500">{{ __('Warehouse') }}</label>
            <select name="warehouse_id" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">—</option>
                @foreach ($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                @endforeach
            </select>
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            {{ __('Active') }}
        </label>
        <div class="flex gap-3">
            <button type="button" onclick="document.getElementById('add-register-modal').close()" class="flex-1 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</button>
            <button type="submit" class="flex-1 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        </div>
    </form>
</dialog>

<dialog id="edit-register-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-md backdrop:bg-slate-900/40">
    <form method="POST" id="edit-register-form" class="p-6 space-y-4">
        @csrf @method('PUT')
        <div class="flex items-start justify-between">
            <h3 class="text-lg font-bold text-slate-900">{{ __('Edit register') }}</h3>
            <button type="button" onclick="document.getElementById('edit-register-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Name') }}</label>
            <input type="text" name="name" id="edit-register-name" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Branch') }}</label>
            <select name="branch_id" id="edit-register-branch" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('All branches') }}</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Warehouse') }}</label>
            <select name="warehouse_id" id="edit-register-warehouse" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">—</option>
                @foreach ($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                @endforeach
            </select>
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="is_active" id="edit-register-active" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            {{ __('Active') }}
        </label>
        <div class="flex gap-3">
            <button type="button" onclick="document.getElementById('edit-register-modal').close()" class="flex-1 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</button>
            <button type="submit" class="flex-1 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        </div>
    </form>
</dialog>

<script>
document.querySelectorAll('[data-edit-register]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('edit-register-form').action = btn.dataset.updateUrl;
        document.getElementById('edit-register-name').value = btn.dataset.name || '';
        document.getElementById('edit-register-branch').value = btn.dataset.branchId || '';
        document.getElementById('edit-register-warehouse').value = btn.dataset.warehouseId || '';
        document.getElementById('edit-register-active').checked = btn.dataset.isActive === '1';
        document.getElementById('edit-register-modal').showModal();
    });
});
</script>
@endsection
