@extends('layouts.app')

@section('title', $requisition->exists ? __('Edit Purchase Requisition') : __('New Purchase Requisition'))

@section('content')
@php
    $existingItems = $requisition->exists ? $requisition->items : collect();
@endphp

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-slate-900">{{ $requisition->exists ? __('Edit Purchase Requisition') : __('New Purchase Requisition') }}</h1>
    <div class="flex items-center gap-3">
        <a href="{{ route('app.purchase-requisitions.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</a>
        <button type="submit" form="pr-form" class="rounded-lg bg-brand-700 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-800">{{ $requisition->exists ? __('Save changes') : __('Submit requisition') }}</button>
    </div>
</div>

<form method="POST" action="{{ $requisition->exists ? route('app.purchase-requisitions.update', $requisition) : route('app.purchase-requisitions.store') }}" id="pr-form" class="space-y-6">
    @csrf
    @if ($requisition->exists) @method('PUT') @endif

    <div class="bg-white rounded-xl border border-slate-100 p-6 grid sm:grid-cols-2 gap-5">
        @if ($projects->isNotEmpty())
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Project (optional)') }}</label>
                <select name="project_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    <option value="">{{ __('None') }}</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}" @selected(old('project_id', $requisition->project_id) == $project->id)>{{ $project->code }} - {{ $project->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Needed by') }}</label>
            <input type="date" name="needed_by_date" value="{{ old('needed_by_date', optional($requisition->needed_by_date)->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold text-slate-900">{{ __('What\'s needed') }}</h2>
            <button type="button" onclick="addRow()" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('+ Add line') }}</button>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="py-2 font-medium">{{ __('Item (optional)') }}</th>
                    <th class="py-2 font-medium">{{ __('Description') }}</th>
                    <th class="py-2 font-medium w-32">{{ __('Quantity') }}</th>
                    <th class="py-2 font-medium w-40">{{ __('Unit') }}</th>
                    <th class="py-2 w-10"></th>
                </tr>
            </thead>
            <tbody id="items-body"></tbody>
        </table>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <label class="block text-sm font-medium text-slate-700">{{ __('Notes') }}</label>
        <textarea name="notes" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('notes', $requisition->notes) }}</textarea>
    </div>
</form>

@php
    $itemCatalogJson = \Illuminate\Support\Js::from($items->map(fn ($i) => ['id' => $i->id, 'name' => $i->display_name ?? $i->name])->values());
    $unitsJson = \Illuminate\Support\Js::from($units->map(fn ($u) => ['id' => $u->id, 'label' => $u->label()])->values());
    $existingJson = \Illuminate\Support\Js::from($existingItems->map(fn ($i) => ['item_id' => $i->item_id, 'unit_id' => $i->unit_id, 'description' => $i->description, 'quantity' => (float) $i->quantity])->values());
@endphp

<script>
const ITEM_CATALOG = {!! $itemCatalogJson !!};
const ALL_UNITS = {!! $unitsJson !!};
const EXISTING = {!! $existingJson !!};
const tbody = document.getElementById('items-body');
let rowIndex = 0;

function addRow(data) {
    data = data || { item_id: '', description: '', quantity: 1, unit_id: '' };
    const i = rowIndex++;
    const tr = document.createElement('tr');
    tr.className = 'border-b border-slate-50 last:border-0';
    tr.innerHTML = `
        <td class="py-2 pe-2">
            <select name="items[${i}][item_id]" class="w-full rounded-lg border border-slate-200 text-sm">
                <option value="">${@json(__('Free text'))}</option>
                ${ITEM_CATALOG.map(it => `<option value="${it.id}" ${String(it.id) === String(data.item_id) ? 'selected' : ''}>${it.name}</option>`).join('')}
            </select>
        </td>
        <td class="py-2 pe-2"><input type="text" name="items[${i}][description]" value="${(data.description || '').replace(/"/g, '&quot;')}" required class="w-full rounded-lg border border-slate-200 text-sm"></td>
        <td class="py-2 pe-2"><input type="number" step="0.01" min="0.01" name="items[${i}][quantity]" value="${data.quantity}" required class="w-full rounded-lg border border-slate-200 text-sm"></td>
        <td class="py-2 pe-2">
            <select name="items[${i}][unit_id]" class="w-full rounded-lg border border-slate-200 text-sm">
                <option value="">${@json(__('—'))}</option>
                ${ALL_UNITS.map(u => `<option value="${u.id}" ${String(u.id) === String(data.unit_id) ? 'selected' : ''}>${u.label}</option>`).join('')}
            </select>
        </td>
        <td class="py-2"><button type="button" onclick="this.closest('tr').remove()" class="text-slate-400 hover:text-red-600">✕</button></td>
    `;
    tbody.appendChild(tr);
}

if (EXISTING.length) {
    EXISTING.forEach(addRow);
} else {
    addRow();
}
</script>
@endsection
