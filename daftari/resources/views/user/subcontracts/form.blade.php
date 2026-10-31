@extends('layouts.app')

@section('title', $subcontract->exists ? __('Edit Subcontract') : __('New Subcontract'))

@section('content')
@php
    $milestoneRows = $subcontract->exists && $subcontract->milestones->isNotEmpty()
        ? $subcontract->milestones->map(fn ($m) => ['name' => $m->name, 'percentage' => (float) $m->percentage, 'requires_handover_approval' => $m->requires_handover_approval])->values()->all()
        : $defaultMilestones;
    $boqRows = $subcontract->exists ? $subcontract->boqItems->map(fn ($i) => ['description' => $i->description, 'quantity' => $i->quantity, 'unit' => $i->unit, 'rate' => $i->rate, 'amount' => (float) $i->amount])->values()->all() : [];
@endphp

<form method="POST" action="{{ $subcontract->exists ? route('app.subcontracts.update', $subcontract) : route('app.subcontracts.store') }}" class="max-w-4xl" x-data="subcontractForm({{ json_encode($boqRows) }}, {{ json_encode($milestoneRows) }}, {{ (float) old('contract_value', $subcontract->contract_value ?: 0) }})">
    @csrf
    @if ($subcontract->exists) @method('PUT') @endif

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <ul class="list-disc ps-4 space-y-0.5">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-xl border border-slate-100 p-5 mb-6 grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">{{ __('Project') }}</label>
            <select name="project_id" required class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('Select a project') }}</option>
                @foreach ($projects as $p)<option value="{{ $p->id }}" @selected(old('project_id', $subcontract->project_id) == $p->id)>{{ $p->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">{{ __('Subcontractor (Supplier)') }}</label>
            <select name="supplier_id" required class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('Select a supplier') }}</option>
                @foreach ($suppliers as $s)<option value="{{ $s->id }}" @selected(old('supplier_id', $subcontract->supplier_id) == $s->id)>{{ $s->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">{{ __('Contract No.') }}</label>
            <input type="text" name="contract_number" value="{{ old('contract_number', $subcontract->contract_number) }}" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">{{ __('Title / Scope') }}</label>
            <input type="text" name="title" value="{{ old('title', $subcontract->title) }}" placeholder="{{ __('e.g. Subbase, Base Course & Asphalt Works') }}" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">{{ __('Contract value') }}</label>
            <input type="number" step="0.01" min="0" name="contract_value" x-model.number="contractValue" required class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <p class="text-xs text-slate-400 mt-1" x-show="boqItems.length">{{ __('Auto-filled from the BOQ total below — edit it directly if there is no BOQ breakdown.') }}</p>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">{{ __('Retention %') }}</label>
            <input type="number" step="0.01" min="0" max="100" name="retention_percent" value="{{ old('retention_percent', $subcontract->retention_percent ?? 5) }}" required class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">{{ __('Start date') }}</label>
            <input type="date" name="start_date" value="{{ old('start_date', optional($subcontract->start_date)->toDateString()) }}" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">{{ __('Status') }}</label>
            <select name="status" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="active" @selected(old('status', $subcontract->status ?: 'active') === 'active')>{{ __('Active') }}</option>
                <option value="completed" @selected(old('status', $subcontract->status) === 'completed')>{{ __('Completed') }}</option>
                <option value="cancelled" @selected(old('status', $subcontract->status) === 'cancelled')>{{ __('Cancelled') }}</option>
            </select>
        </div>
        <div class="sm:col-span-2">
            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">{{ __('Notes') }}</label>
            <textarea name="notes" rows="2" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">{{ old('notes', $subcontract->notes) }}</textarea>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-5 mb-6">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-slate-900">{{ __('BOQ breakdown (optional)') }}</h3>
            <button type="button" @click="addBoqRow()" class="text-xs font-semibold text-brand-700 hover:underline">+ {{ __('Add line') }}</button>
        </div>
        <template x-for="(row, i) in boqItems" :key="i">
            <div class="grid grid-cols-12 gap-2 mb-2 items-center">
                <input type="text" :name="`boq_items[${i}][description]`" x-model="row.description" placeholder="{{ __('Description') }}" class="col-span-5 rounded-lg border border-slate-200 text-sm">
                <input type="number" step="0.001" :name="`boq_items[${i}][quantity]`" x-model.number="row.quantity" placeholder="{{ __('Qty') }}" class="col-span-2 rounded-lg border border-slate-200 text-sm">
                <input type="text" :name="`boq_items[${i}][unit]`" x-model="row.unit" placeholder="{{ __('Unit') }}" class="col-span-1 rounded-lg border border-slate-200 text-sm">
                <input type="number" step="0.01" :name="`boq_items[${i}][rate]`" x-model.number="row.rate" @input="recalcBoqRow(i)" placeholder="{{ __('Rate') }}" class="col-span-2 rounded-lg border border-slate-200 text-sm">
                <input type="number" step="0.01" :name="`boq_items[${i}][amount]`" x-model.number="row.amount" @input="recalcContractFromBoq()" placeholder="{{ __('Amount') }}" class="col-span-1 rounded-lg border border-slate-200 text-sm">
                <button type="button" @click="removeBoqRow(i)" class="col-span-1 text-red-500 text-xs">&times;</button>
            </div>
        </template>
        <p class="text-xs text-slate-400" x-show="boqItems.length">{{ __('BOQ total') }}: <span x-text="boqTotal().toFixed(2)"></span></p>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-5 mb-6">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-slate-900">{{ __('Milestone payment schedule') }}</h3>
            <button type="button" @click="addMilestoneRow()" class="text-xs font-semibold text-brand-700 hover:underline">+ {{ __('Add milestone') }}</button>
        </div>
        <p class="text-xs text-slate-400 mb-3">{{ __('Percentages must add up to 100%. Default: 20% advance / 30% / 30% / 15% / 5% after handover & approval.') }}</p>
        <template x-for="(row, i) in milestones" :key="i">
            <div class="grid grid-cols-12 gap-2 mb-2 items-center">
                <input type="text" :name="`milestones[${i}][name]`" x-model="row.name" placeholder="{{ __('Milestone name') }}" class="col-span-6 rounded-lg border border-slate-200 text-sm">
                <div class="col-span-2 flex items-center gap-1">
                    <input type="number" step="0.01" min="0" max="100" :name="`milestones[${i}][percentage]`" x-model.number="row.percentage" class="w-full rounded-lg border border-slate-200 text-sm">
                    <span class="text-xs text-slate-400">%</span>
                </div>
                <div class="col-span-2 text-xs text-slate-500" x-text="(contractValue * (row.percentage || 0) / 100).toFixed(2)"></div>
                <label class="col-span-1 inline-flex items-center gap-1 text-[11px] text-slate-500">
                    <input type="checkbox" :name="`milestones[${i}][requires_handover_approval]`" value="1" x-model="row.requires_handover_approval">
                    {{ __('Handover') }}
                </label>
                <button type="button" @click="removeMilestoneRow(i)" class="col-span-1 text-red-500 text-xs">&times;</button>
            </div>
        </template>
        <p class="text-xs mt-2" :class="milestoneTotal() === 100 ? 'text-teal-600' : 'text-red-600'">
            {{ __('Total') }}: <span x-text="milestoneTotal().toFixed(2)"></span>%
        </p>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">{{ $subcontract->exists ? __('Save changes') : __('Create subcontract') }}</button>
        <a href="{{ $subcontract->exists ? route('app.subcontracts.show', $subcontract) : route('app.subcontracts.index') }}" class="text-sm text-slate-500 hover:text-slate-700">{{ __('Cancel') }}</a>
    </div>
</form>

<script>
function subcontractForm(initialBoq, initialMilestones, initialContractValue) {
    return {
        boqItems: initialBoq,
        milestones: initialMilestones,
        contractValue: initialContractValue,
        addBoqRow() { this.boqItems.push({ description: '', quantity: null, unit: '', rate: null, amount: null }); },
        removeBoqRow(i) { this.boqItems.splice(i, 1); this.recalcContractFromBoq(); },
        recalcBoqRow(i) {
            const row = this.boqItems[i];
            if (row.quantity && row.rate) { row.amount = Math.round(row.quantity * row.rate * 100) / 100; }
            this.recalcContractFromBoq();
        },
        recalcContractFromBoq() { if (this.boqItems.length) { this.contractValue = this.boqTotal(); } },
        boqTotal() { return this.boqItems.reduce((sum, r) => sum + (parseFloat(r.amount) || 0), 0); },
        addMilestoneRow() { this.milestones.push({ name: '', percentage: 0, requires_handover_approval: false }); },
        removeMilestoneRow(i) { this.milestones.splice(i, 1); },
        milestoneTotal() { return Math.round(this.milestones.reduce((sum, r) => sum + (parseFloat(r.percentage) || 0), 0) * 100) / 100; },
    };
}
</script>
@endsection
