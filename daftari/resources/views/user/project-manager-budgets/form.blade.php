@extends('layouts.app')

@section('title', $subcontract->exists ? __('Edit Project Manager Budget') : __('New Project Manager Budget'))

@section('content')
@php
    $boqRows = $subcontract->exists ? $subcontract->boqItems->map(fn ($i) => ['description' => $i->description, 'quantity' => $i->quantity, 'unit' => $i->unit, 'rate' => $i->rate, 'amount' => (float) $i->amount])->values()->all() : [];
@endphp

<form method="POST" action="{{ $subcontract->exists ? route('app.project-manager-budgets.update', $subcontract) : route('app.project-manager-budgets.store') }}" class="max-w-4xl" x-data="pmBudgetForm({{ json_encode($boqRows) }}, {{ (float) old('contract_value', $subcontract->contract_value ?: 0) }})">
    @csrf
    @if ($subcontract->exists) @method('PUT') @endif
    <input type="hidden" name="party_type" value="employee">
    <input type="hidden" name="retention_percent" value="0">

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
            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">{{ __('Project manager') }}</label>
            <select name="employee_id" required class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('Select an employee') }}</option>
                @foreach ($employees as $e)<option value="{{ $e->id }}" @selected(old('employee_id', $subcontract->employee_id) == $e->id)>{{ $e->full_name }}</option>@endforeach
            </select>
            <p class="text-xs text-slate-400 mt-1">{{ __('Not listed? Add them under Employees first, then come back here.') }}</p>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">{{ __('Reference (optional)') }}</label>
            <input type="text" name="contract_number" value="{{ old('contract_number', $subcontract->contract_number) }}" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">{{ __('Title / Scope') }}</label>
            <input type="text" name="title" value="{{ old('title', $subcontract->title) }}" placeholder="{{ __('e.g. Subbase, Base Course & Asphalt Works') }}" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">{{ __('Total budget') }}</label>
            <input type="number" step="0.01" min="0" name="contract_value" x-model.number="contractValue" required class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <p class="text-xs text-slate-400 mt-1" x-show="boqItems.length">{{ __('Auto-filled from the budget breakdown below — edit it directly if there is no breakdown.') }}</p>
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

    <label class="flex items-center gap-2 text-sm text-slate-700 bg-white rounded-xl border border-slate-100 p-4 mb-6 cursor-pointer">
        <input type="checkbox" x-model="showBreakdown" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
        <span>{{ __('Track a detailed budget breakdown for this one') }}</span>
        <span class="text-xs text-slate-400">— {{ __('leave unchecked for a simple project: just the total budget above is enough.') }}</span>
    </label>

    <div class="bg-white rounded-xl border border-slate-100 p-5 mb-6" x-show="showBreakdown" x-cloak>
        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
            <h3 class="text-sm font-semibold text-slate-900">{{ __('Budget breakdown') }}</h3>
            <button type="button" @click="addBoqRow()" class="text-xs font-semibold text-brand-700 hover:underline">+ {{ __('Add line') }}</button>
        </div>
        <p class="text-xs text-slate-400 mb-3">{{ __('What the total budget is made of — e.g. Sub base 11,200 m² × 6.00, Asphalt 4,100 m² × 36.50.') }}</p>
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
        <p class="text-xs text-slate-400" x-show="boqItems.length">{{ __('Budget total') }}: <span x-text="boqTotal().toFixed(2)"></span></p>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">{{ $subcontract->exists ? __('Save changes') : __('Create budget') }}</button>
        <a href="{{ $subcontract->exists ? route('app.project-manager-budgets.show', $subcontract) : route('app.project-manager-budgets.index') }}" class="text-sm text-slate-500 hover:text-slate-700">{{ __('Cancel') }}</a>
    </div>
</form>

<script>
function pmBudgetForm(initialBoq, initialContractValue) {
    return {
        boqItems: initialBoq,
        contractValue: initialContractValue,
        showBreakdown: initialBoq.length > 0,
        addBoqRow() { this.boqItems.push({ description: '', quantity: null, unit: '', rate: null, amount: null }); },
        removeBoqRow(i) { this.boqItems.splice(i, 1); this.recalcContractFromBoq(); },
        recalcBoqRow(i) {
            const row = this.boqItems[i];
            if (row.quantity && row.rate) { row.amount = Math.round(row.quantity * row.rate * 100) / 100; }
            this.recalcContractFromBoq();
        },
        recalcContractFromBoq() { if (this.boqItems.length) { this.contractValue = this.boqTotal(); } },
        boqTotal() { return this.boqItems.reduce((sum, r) => sum + (parseFloat(r.amount) || 0), 0); },
    };
}
</script>
@endsection
