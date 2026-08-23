@extends('layouts.app')

@section('title', $recurringInvoice->exists ? __('Edit Recurring Invoice') : __('New Recurring Invoice'))

@section('content')
@php
$existingItems = $recurringInvoice->exists ? $recurringInvoice->items()->orderBy('sort_order')->get() : collect();
@endphp

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ $recurringInvoice->exists ? __('Edit recurring invoice') : __('New recurring invoice') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('A draft invoice is generated automatically each time this schedule runs.') }}</p>
    </div>
    <div class="flex items-center gap-3">
        <button type="submit" form="recurring-invoice-form" class="rounded-lg bg-brand-700 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-800">{{ __('Save') }}</button>
        <a href="{{ route('app.recurring-invoices.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Back') }}</a>
    </div>
</div>

<form method="POST" action="{{ $recurringInvoice->exists ? route('app.recurring-invoices.update', $recurringInvoice) : route('app.recurring-invoices.store') }}" id="recurring-invoice-form" class="space-y-6">
    @csrf
    @if ($recurringInvoice->exists) @method('PUT') @endif

    <div class="bg-white rounded-xl border border-slate-100 p-6 grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-3">
            <label class="block text-sm font-medium text-slate-700">{{ __('Title') }}</label>
            <input type="text" name="title" required value="{{ old('title', $recurringInvoice->title) }}" placeholder="{{ __('e.g. Monthly retainer — Acme Co') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Client') }}</label>
            <select name="client_id" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('Select a client') }}</option>
                @foreach ($clients as $client)
                    <option value="{{ $client->id }}" @selected(old('client_id', $recurringInvoice->client_id) == $client->id)>{{ $client->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Type') }}</label>
            <select name="type" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="standard" @selected(old('type', $recurringInvoice->type ?? 'standard') === 'standard')>{{ __('Standard (B2B)') }}</option>
                <option value="simplified" @selected(old('type', $recurringInvoice->type ?? 'standard') === 'simplified')>{{ __('Simplified (B2C)') }}</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Frequency') }}</label>
            <select name="frequency" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                @foreach (['weekly' => __('Weekly'), 'monthly' => __('Monthly'), 'quarterly' => __('Quarterly'), 'yearly' => __('Yearly')] as $value => $label)
                    <option value="{{ $value }}" @selected(old('frequency', $recurringInvoice->frequency ?? 'monthly') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Start date') }}</label>
            <input type="date" name="start_date" value="{{ old('start_date', optional($recurringInvoice->start_date)->format('Y-m-d')) }}" required @if($recurringInvoice->exists) readonly @endif class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500 read-only:bg-slate-50 read-only:text-slate-400">
            @if ($recurringInvoice->exists)
                <p class="text-xs text-slate-400 mt-1">{{ __('Next invoice date') }}: {{ optional($recurringInvoice->next_run_date)->format('Y-m-d') }}</p>
            @endif
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('End date (optional)') }}</label>
            <input type="date" name="end_date" value="{{ old('end_date', optional($recurringInvoice->end_date)->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            <p class="text-xs text-slate-400 mt-1">{{ __('Leave blank to run indefinitely until paused or cancelled.') }}</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Payment terms (days)') }}</label>
            <input type="number" name="due_days" min="0" max="365" required value="{{ old('due_days', $recurringInvoice->due_days ?? 30) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        @if ($salespersons->isNotEmpty())
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Salesperson (optional)') }}</label>
                <select name="salesperson_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    <option value="">{{ __('None') }}</option>
                    @foreach ($salespersons as $salesperson)
                        <option value="{{ $salesperson->id }}" @selected(old('salesperson_id', $recurringInvoice->salesperson_id) == $salesperson->id)>{{ $salesperson->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if ($projects->isNotEmpty())
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Project (optional)') }}</label>
                <select name="project_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    <option value="">{{ __('None') }}</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}" @selected(old('project_id', $recurringInvoice->project_id) == $project->id)>{{ $project->code }} - {{ $project->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Bank account (optional)') }}</label>
            <select name="bank_account_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('None') }}</option>
                @foreach ($bankAccounts as $account)
                    <option value="{{ $account->id }}" @selected(old('bank_account_id', $recurringInvoice->bank_account_id) == $account->id)>{{ $account->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold text-slate-900">{{ __('Line items') }}</h2>
            <button type="button" id="add-row" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('+ Add line') }}</button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm" id="items-table">
                <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-100">
                        <th class="py-2 pe-3 font-medium w-1/4">{{ __('Item') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('Description') }}</th>
                        <th class="py-2 pe-3 font-medium w-20">{{ __('Qty') }}</th>
                        <th class="py-2 pe-3 font-medium w-28">{{ __('Unit') }}</th>
                        <th class="py-2 pe-3 font-medium w-28">{{ __('Unit price') }}</th>
                        <th class="py-2 pe-3 font-medium w-24">{{ __('VAT %') }}</th>
                        <th class="py-2 pe-3 font-medium w-28">{{ __('Line total') }}</th>
                        <th class="py-2 w-8"></th>
                    </tr>
                </thead>
                <tbody id="items-body"></tbody>
            </table>
        </div>

        <div class="mt-6 flex justify-end">
            <div class="w-full max-w-xs space-y-2 text-sm">
                <div class="flex justify-between text-slate-500"><span>{{ __('Subtotal') }}</span><span id="summary-subtotal">SAR 0.00</span></div>
                <div class="flex justify-between items-center text-slate-500">
                    <span>{{ __('Discount') }}</span>
                    <input type="number" step="0.01" min="0" name="discount_total" id="discount_total" value="{{ old('discount_total', $recurringInvoice->discount_total ?? 0) }}" class="w-28 rounded-lg border border-slate-200 text-end focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div class="flex justify-between text-slate-500"><span>{{ __('VAT') }}</span><span id="summary-vat">SAR 0.00</span></div>
                <div class="flex justify-between font-bold text-slate-900 text-base pt-2 border-t border-slate-100"><span>{{ __('Total') }}</span><span id="summary-total">SAR 0.00</span></div>
                <div class="flex justify-between items-center text-slate-500">
                    <span>{{ __('Retention %') }}</span>
                    <input type="number" step="0.01" min="0" max="100" name="retention_rate" id="retention_rate" value="{{ old('retention_rate', $recurringInvoice->retention_rate ?? 0) }}" class="w-28 rounded-lg border border-slate-200 text-end focus:border-brand-500 focus:ring-brand-500">
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <label class="block text-sm font-medium text-slate-700">{{ __('Notes') }}</label>
        <textarea name="notes" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('notes', $recurringInvoice->notes) }}</textarea>
    </div>
</form>

@php
    $catalogJson = json_encode($items->map(function ($i) {
        return [
            'id' => $i->id,
            'name' => $i->name,
            'unit_price' => (float) $i->unit_price,
            'vat_rate' => (float) $i->vat_rate,
            'units' => collect($i->baseUnit ? [['id' => $i->baseUnit->id, 'label' => $i->baseUnit->label(), 'price' => (float) $i->unit_price]] : [])
                ->merge($i->itemUnits->map(fn ($iu) => ['id' => $iu->unit_id, 'label' => $iu->unit?->label(), 'price' => (float) $i->priceForUnit($iu->unit_id)]))
                ->values(),
        ];
    })->values());
    $existingJson = json_encode($existingItems->map(function ($i) {
        return ['item_id' => $i->item_id, 'unit_id' => $i->unit_id, 'description' => $i->description, 'quantity' => (float) $i->quantity, 'unit_price' => (float) $i->unit_price, 'vat_rate' => (float) $i->vat_rate];
    })->values());
@endphp

<script>
const CATALOG = {!! $catalogJson !!};
const EXISTING = {!! $existingJson !!};

const tbody = document.getElementById('items-body');
let rowIndex = 0;

function fmt(n) {
    return 'SAR ' + (Math.round(n * 100) / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function addRow(data) {
    data = data || { item_id: '', description: '', quantity: 1, unit_price: 0, vat_rate: 15 };
    const i = rowIndex++;
    const tr = document.createElement('tr');
    tr.className = 'border-b border-slate-50';
    tr.innerHTML = `
        <td class="py-2 pe-3">
            <select data-role="item" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">${@json(__('Custom'))}</option>
                ${CATALOG.map(c => `<option value="${c.id}" data-price="${c.unit_price}" data-vat="${c.vat_rate}" data-name="${c.name}">${c.name}</option>`).join('')}
            </select>
            <input type="hidden" name="items[${i}][item_id]" data-role="item_id" value="${data.item_id || ''}">
        </td>
        <td class="py-2 pe-3"><input type="text" name="items[${i}][description]" data-role="description" value="${data.description || ''}" required class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></td>
        <td class="py-2 pe-3"><input type="number" step="0.01" min="0.01" name="items[${i}][quantity]" data-role="quantity" value="${data.quantity}" required class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></td>
        <td class="py-2 pe-3"><select name="items[${i}][unit_id]" data-role="unit" disabled class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></select></td>
        <td class="py-2 pe-3"><input type="number" step="0.01" min="0" name="items[${i}][unit_price]" data-role="unit_price" value="${data.unit_price}" required class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></td>
        <td class="py-2 pe-3"><input type="number" step="0.01" min="0" max="100" name="items[${i}][vat_rate]" data-role="vat_rate" value="${data.vat_rate}" required class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></td>
        <td class="py-2 pe-3 text-slate-700" data-role="line_total">SAR 0.00</td>
        <td class="py-2"><button type="button" data-role="remove" class="text-slate-400 hover:text-red-600">&times;</button></td>
    `;
    tbody.appendChild(tr);

    const itemSelect = tr.querySelector('[data-role="item"]');
    if (data.item_id) itemSelect.value = data.item_id;

    itemSelect.addEventListener('change', () => {
        const opt = itemSelect.selectedOptions[0];
        tr.querySelector('[data-role="item_id"]').value = itemSelect.value;
        if (itemSelect.value) {
            tr.querySelector('[data-role="description"]').value = opt.dataset.name;
            tr.querySelector('[data-role="unit_price"]').value = opt.dataset.price;
            tr.querySelector('[data-role="vat_rate"]').value = opt.dataset.vat;
        }
        populateUnitOptions(tr, itemSelect.value, null);
        recalc();
    });

    populateUnitOptions(tr, data.item_id, data.unit_id);

    tr.querySelector('[data-role="unit"]').addEventListener('change', () => {
        const unitSelect = tr.querySelector('[data-role="unit"]');
        const opt = unitSelect.selectedOptions[0];
        if (opt && opt.dataset.price !== undefined) {
            tr.querySelector('[data-role="unit_price"]').value = opt.dataset.price;
        }
        recalc();
    });

    tr.querySelectorAll('input[type="number"]').forEach(el => el.addEventListener('input', recalc));
    tr.querySelector('[data-role="remove"]').addEventListener('click', () => { tr.remove(); recalc(); });

    recalc();
}

function populateUnitOptions(tr, itemId, selectedUnitId) {
    const unitSelect = tr.querySelector('[data-role="unit"]');
    const catalogItem = CATALOG.find(c => String(c.id) === String(itemId));

    if (! catalogItem || ! catalogItem.units.length) {
        unitSelect.innerHTML = '';
        unitSelect.disabled = true;
        return;
    }

    unitSelect.disabled = false;
    unitSelect.innerHTML = catalogItem.units.map(u => `<option value="${u.id}" data-price="${u.price}">${u.label}</option>`).join('');
    unitSelect.value = selectedUnitId || catalogItem.units[0].id;
}

function recalc() {
    let subtotal = 0, vat = 0;
    tbody.querySelectorAll('tr').forEach(tr => {
        const q = parseFloat(tr.querySelector('[data-role="quantity"]').value) || 0;
        const p = parseFloat(tr.querySelector('[data-role="unit_price"]').value) || 0;
        const v = parseFloat(tr.querySelector('[data-role="vat_rate"]').value) || 0;
        const lineSub = q * p;
        const lineVat = lineSub * (v / 100);
        tr.querySelector('[data-role="line_total"]').textContent = fmt(lineSub + lineVat);
        subtotal += lineSub;
        vat += lineVat;
    });
    const discount = parseFloat(document.getElementById('discount_total').value) || 0;
    document.getElementById('summary-subtotal').textContent = fmt(subtotal);
    document.getElementById('summary-vat').textContent = fmt(vat);
    document.getElementById('summary-total').textContent = fmt(subtotal - discount + vat);
}

document.getElementById('add-row').addEventListener('click', () => addRow());
document.getElementById('discount_total').addEventListener('input', recalc);
document.getElementById('retention_rate').addEventListener('input', recalc);

if (EXISTING.length) {
    EXISTING.forEach(addRow);
} else {
    addRow();
}

document.getElementById('recurring-invoice-form').addEventListener('submit', (e) => {
    if (!tbody.querySelector('tr')) {
        e.preventDefault();
        alert(@json(__('Add at least one line item.')));
    }
});
</script>
@endsection
