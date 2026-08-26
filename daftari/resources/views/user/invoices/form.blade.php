@extends('layouts.app')

@section('title', $invoice->exists ? __('Edit Invoice') : __('New Invoice'))

@section('content')
@php
$existingItems = $invoice->exists ? $invoice->items()->orderBy('sort_order')->get() : collect();
$company = auth()->user()->company;
@endphp

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ __('Create invoice') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Manage invoices and track who owes you') }}</p>
    </div>
    <div class="flex items-center gap-3">
        <div class="relative">
            <div class="flex">
                <button type="submit" form="invoice-form" name="send_immediately" value="0" class="rounded-s-lg bg-brand-700 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-800">{{ __('Save as draft') }}</button>
                <button type="button" id="save-menu-toggle" class="rounded-e-lg border-s border-brand-800 bg-brand-700 px-2 text-white hover:bg-brand-800">▾</button>
            </div>
            <div id="save-menu" class="hidden absolute end-0 mt-1 w-48 rounded-lg border border-slate-200 bg-white shadow-lg z-10">
                <button type="submit" form="invoice-form" name="send_immediately" value="1" class="block w-full text-start px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">{{ __('Save & send') }}</button>
            </div>
        </div>
        <button type="button" id="preview-btn" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Preview') }}</button>
        <a href="{{ route('app.invoices.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Back') }}</a>
    </div>
</div>

<form method="POST" action="{{ $invoice->exists ? route('app.invoices.update', $invoice) : route('app.invoices.store') }}" id="invoice-form" class="space-y-6">
    @csrf
    @if ($invoice->exists) @method('PUT') @endif

    <div class="bg-white rounded-xl border border-slate-100 p-6 flex items-start justify-between gap-6">
        <div class="space-y-2 text-sm">
            @unless ($invoice->exists)
                <p class="text-slate-500">{{ __('Invoice number') }}: <span class="font-semibold text-slate-800">{{ $nextNumberPreview }}</span></p>
            @endunless
        </div>
        <div class="flex items-center gap-3">
            @if ($company->logo_path)
                <img src="{{ Storage::url($company->logo_path) }}" alt="{{ $company->name }}" class="h-12 w-12 rounded-lg object-cover border border-slate-100">
            @else
                <a href="{{ route('app.settings.index') }}" class="h-12 w-12 shrink-0 rounded-lg border border-dashed border-slate-200 flex items-center justify-center text-slate-300 text-xs text-center hover:border-brand-300">{{ __('+ Logo') }}</a>
            @endif
            <div>
                <p class="font-semibold text-slate-900">{{ $company->name }}</p>
                @if ($company->vat_number)<p class="text-xs text-slate-400">{{ __('Tax ID') }}: {{ $company->vat_number }}</p>@endif
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6 grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Client') }}</label>
            <select name="client_id" id="pv-client" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('Select a client') }}</option>
                @foreach ($clients as $client)
                    <option value="{{ $client->id }}" @selected(old('client_id', $invoice->client_id) == $client->id)>{{ $client->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Type') }}</label>
            <select name="type" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="standard" @selected(old('type', $invoice->type ?? 'standard') === 'standard')>{{ __('Standard (B2B)') }}</option>
                <option value="simplified" @selected(old('type', $invoice->type ?? 'standard') === 'simplified')>{{ __('Simplified (B2C)') }}</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Issue date') }}</label>
            <input type="date" name="issue_date" id="pv-issue-date" value="{{ old('issue_date', optional($invoice->issue_date)->format('Y-m-d')) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Due date') }}</label>
            <input type="date" name="due_date" value="{{ old('due_date', optional($invoice->due_date)->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Currency') }}</label>
            <select name="currency" id="currency" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                @foreach ($currencies as $code => $label)
                    <option value="{{ $code }}" data-base="{{ $code === $company->currency ? '1' : '0' }}" @selected(old('currency', $invoice->currency ?? $company->currency) === $code)>{{ __($label) }}</option>
                @endforeach
            </select>
        </div>
        <div id="exchange-rate-wrap" class="{{ old('currency', $invoice->currency ?? $company->currency) === $company->currency ? 'hidden' : '' }}">
            <label class="block text-sm font-medium text-slate-700">{{ __('Exchange rate (to :currency)', ['currency' => $company->currency]) }}</label>
            <input type="number" step="0.000001" min="0.000001" name="exchange_rate" id="exchange_rate" value="{{ old('exchange_rate', $invoice->exchange_rate ?? 1) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            <p class="text-xs text-slate-400 mt-1">{{ __('Amounts below stay in the selected currency. Your ledger converts them to :currency using this rate.', ['currency' => $company->currency]) }}</p>
        </div>
        @if ($salespersons->isNotEmpty())
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Salesperson (optional)') }}</label>
                <select name="salesperson_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    <option value="">{{ __('None') }}</option>
                    @foreach ($salespersons as $salesperson)
                        <option value="{{ $salesperson->id }}" @selected(old('salesperson_id', $invoice->salesperson_id) == $salesperson->id)>{{ $salesperson->name }}</option>
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
                        <option value="{{ $project->id }}" @selected(old('project_id', $invoice->project_id) == $project->id)>{{ $project->code }} - {{ $project->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
            <h2 class="font-semibold text-slate-900">{{ __('Line items') }}</h2>
            <div class="flex items-center gap-3">
                @if ($warehouses->isNotEmpty())
                    <select name="warehouse_id" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">{{ __('No stock deduction') }}</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected(old('warehouse_id', $invoice->warehouse_id ?? $warehouse->id) == $warehouse->id)>{{ $warehouse->code }} — {{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                @endif
                <button type="button" id="scan-line-barcode" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('Scan barcode') }}</button>
                <button type="button" id="add-row" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('+ Add line') }}</button>
            </div>
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

        <div class="mt-6 flex flex-col sm:flex-row justify-between gap-6">
            <div class="w-full max-w-xs">
                <h3 class="text-xs font-semibold uppercase text-slate-500 mb-2">{{ __('Bank details') }}</h3>
                <select name="bank_account_id" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">{{ __('None') }}</option>
                    @foreach ($bankAccounts as $account)
                        <option value="{{ $account->id }}" @selected(old('bank_account_id', $invoice->bank_account_id) == $account->id)>{{ $account->name }}</option>
                    @endforeach
                </select>

                <h3 class="text-xs font-semibold uppercase text-slate-500 mt-6 mb-2">{{ __('Stamp') }}</h3>
                @if ($company->stamp_path)
                    <img src="{{ Storage::url($company->stamp_path) }}" alt="{{ __('Company stamp') }}" class="h-16 w-16 rounded-lg object-cover border border-slate-200">
                @else
                    <a href="{{ route('app.settings.index') }}" class="inline-flex h-16 w-16 rounded-lg border border-dashed border-slate-200 items-center justify-center text-slate-300 text-xs text-center hover:border-brand-300">{{ __('+ Stamp') }}</a>
                @endif
            </div>
            <div class="w-full max-w-xs space-y-2 text-sm">
                <div class="flex justify-between text-slate-500"><span>{{ __('Subtotal') }}</span><span id="summary-subtotal">SAR 0.00</span></div>
                <div class="flex justify-between items-center text-slate-500">
                    <span>{{ __('Discount') }}</span>
                    <input type="number" step="0.01" min="0" name="discount_total" id="discount_total" value="{{ old('discount_total', $invoice->discount_total ?? 0) }}" class="w-28 rounded-lg border border-slate-200 text-end focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div class="flex justify-between text-slate-500"><span>{{ __('VAT') }}</span><span id="summary-vat">SAR 0.00</span></div>
                <div class="flex justify-between font-bold text-slate-900 text-base pt-2 border-t border-slate-100"><span>{{ __('Total') }}</span><span id="summary-total">SAR 0.00</span></div>
                <div id="retention-row" class="hidden flex justify-between text-slate-500"><span>{{ __('Retention held') }}</span><span id="summary-retention">SAR 0.00</span></div>
                <button type="button" id="add-retention-btn" class="text-xs font-semibold text-brand-700 hover:underline">{{ __('+ Add retention') }}</button>
                <div id="retention-input-wrap" class="hidden flex items-center justify-between text-slate-500">
                    <span>{{ __('Retention %') }}</span>
                    <input type="number" step="0.01" min="0" max="100" name="retention_rate" id="retention_rate" value="{{ old('retention_rate', $invoice->retention_rate ?? 0) }}" class="w-28 rounded-lg border border-slate-200 text-end focus:border-brand-500 focus:ring-brand-500">
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <label class="block text-sm font-medium text-slate-700">{{ __('Notes') }}</label>
        <textarea name="notes" id="pv-notes" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('notes', $invoice->notes) }}</textarea>
    </div>

    @if ($invoice->exists)
        <p class="text-xs text-slate-400">{{ __('Manage attachments from the invoice page after saving.') }}</p>
    @else
        <p class="text-xs text-slate-400">{{ __('Attachments can be added once the invoice is saved.') }}</p>
    @endif
</form>

@php
    $catalogJson = json_encode($items->map(function ($i) {
        return [
            'id' => $i->id,
            'name' => $i->name,
            'unit_price' => (float) $i->unit_price,
            'vat_rate' => (float) $i->vat_rate,
            'barcode' => $i->barcode,
            'units' => collect($i->baseUnit ? [['id' => $i->baseUnit->id, 'label' => $i->baseUnit->label(), 'price' => (float) $i->unit_price]] : [])
                ->merge($i->itemUnits->map(fn ($iu) => ['id' => $iu->unit_id, 'label' => $iu->unit?->label(), 'price' => (float) $i->priceForUnit($iu->unit_id)]))
                ->values(),
        ];
    })->values());
    $existingJson = json_encode($existingItems->map(function ($i) {
        return ['item_id' => $i->item_id, 'unit_id' => $i->unit_id, 'description' => $i->description, 'quantity' => (float) $i->quantity, 'unit_price' => (float) $i->unit_price, 'vat_rate' => (float) $i->vat_rate];
    })->values());
@endphp

<dialog id="preview-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-2xl backdrop:bg-slate-900/40">
    <div class="p-6">
        <div class="flex items-start justify-between mb-4">
            <h3 class="text-lg font-bold text-slate-900">{{ __('Invoice preview') }}</h3>
            <button type="button" onclick="document.getElementById('preview-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <div class="flex justify-between items-start">
            <div>
                <h2 class="text-xl font-bold text-slate-900">{{ $company->name }}</h2>
                @if ($company->vat_number)<p class="text-sm text-slate-500">{{ __('VAT') }}: {{ $company->vat_number }}</p>@endif
            </div>
            <div class="text-end text-sm text-slate-500">
                <p id="preview-date"></p>
            </div>
        </div>
        <p class="mt-4 text-sm text-slate-500">{{ __('Client') }}: <span id="preview-client" class="font-medium text-slate-800"></span></p>
        <table class="w-full text-sm mt-4">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-200">
                    <th class="py-2">{{ __('Description') }}</th>
                    <th class="py-2 text-end">{{ __('Qty') }}</th>
                    <th class="py-2 text-end">{{ __('Unit price') }}</th>
                    <th class="py-2 text-end">{{ __('Total') }}</th>
                </tr>
            </thead>
            <tbody id="preview-items"></tbody>
        </table>
        <div class="mt-4 flex justify-end">
            <div class="w-full max-w-xs space-y-1 text-sm">
                <div class="flex justify-between text-slate-500"><span>{{ __('Subtotal') }}</span><span id="preview-subtotal"></span></div>
                <div class="flex justify-between text-slate-500"><span>{{ __('VAT') }}</span><span id="preview-vat"></span></div>
                <div class="flex justify-between font-bold text-slate-900 border-t border-slate-100 pt-1"><span>{{ __('Total') }}</span><span id="preview-total"></span></div>
            </div>
        </div>
        <p id="preview-notes" class="mt-4 text-sm text-slate-500"></p>
    </div>
</dialog>

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

    const retentionRate = parseFloat(document.getElementById('retention_rate').value) || 0;
    const retentionAmount = subtotal * retentionRate / 100;
    const retentionRow = document.getElementById('retention-row');
    if (retentionRate > 0) {
        retentionRow.classList.remove('hidden');
        document.getElementById('summary-retention').textContent = fmt(retentionAmount);
    } else {
        retentionRow.classList.add('hidden');
    }
}

document.getElementById('add-row').addEventListener('click', () => addRow());

document.getElementById('scan-line-barcode').addEventListener('click', () => {
    window.DaftariBarcodeScanner.open((code) => {
        const item = CATALOG.find(c => c.barcode && c.barcode === code);
        if (!item) {
            alert(@json(__('No item found with that barcode.')));
            return;
        }

        const existingRow = Array.from(tbody.querySelectorAll('tr')).find(
            tr => tr.querySelector('[data-role="item_id"]')?.value === String(item.id)
        );
        if (existingRow) {
            const qtyInput = existingRow.querySelector('[data-role="quantity"]');
            qtyInput.value = (parseFloat(qtyInput.value) || 0) + 1;
            recalc();
            return;
        }

        addRow({ item_id: item.id, description: item.name, quantity: 1, unit_price: item.unit_price, vat_rate: item.vat_rate });
    }, @json(__('Scan barcode')), @json(__('Point the camera at the item\'s barcode to add it as a line.')));
});
document.getElementById('discount_total').addEventListener('input', recalc);
document.getElementById('retention_rate').addEventListener('input', recalc);

document.getElementById('add-retention-btn').addEventListener('click', () => {
    document.getElementById('retention-input-wrap').classList.remove('hidden');
    document.getElementById('add-retention-btn').classList.add('hidden');
});
if (parseFloat(document.getElementById('retention_rate').value) > 0) {
    document.getElementById('retention-input-wrap').classList.remove('hidden');
    document.getElementById('add-retention-btn').classList.add('hidden');
}

document.getElementById('currency').addEventListener('change', (e) => {
    const isBase = e.target.selectedOptions[0].dataset.base === '1';
    document.getElementById('exchange-rate-wrap').classList.toggle('hidden', isBase);
    if (isBase) {
        document.getElementById('exchange_rate').value = 1;
    }
});

if (EXISTING.length) {
    EXISTING.forEach(addRow);
} else {
    addRow();
}

document.getElementById('invoice-form').addEventListener('submit', (e) => {
    if (!tbody.querySelector('tr')) {
        e.preventDefault();
        alert(@json(__('Add at least one line item.')));
    }
});

document.getElementById('save-menu-toggle').addEventListener('click', () => {
    document.getElementById('save-menu').classList.toggle('hidden');
});
document.addEventListener('click', (e) => {
    if (! e.target.closest('#save-menu-toggle') && ! e.target.closest('#save-menu')) {
        document.getElementById('save-menu').classList.add('hidden');
    }
});

document.getElementById('preview-btn').addEventListener('click', () => {
    const clientSelect = document.getElementById('pv-client');
    document.getElementById('preview-client').textContent = clientSelect.selectedOptions[0]?.text || '—';
    document.getElementById('preview-date').textContent = document.getElementById('pv-issue-date').value || '';
    document.getElementById('preview-notes').textContent = document.getElementById('pv-notes').value || '';

    let subtotal = 0, vat = 0;
    const rows = [];
    tbody.querySelectorAll('tr').forEach(tr => {
        const description = tr.querySelector('[data-role="description"]').value;
        const q = parseFloat(tr.querySelector('[data-role="quantity"]').value) || 0;
        const p = parseFloat(tr.querySelector('[data-role="unit_price"]').value) || 0;
        const v = parseFloat(tr.querySelector('[data-role="vat_rate"]').value) || 0;
        const lineSub = q * p;
        subtotal += lineSub;
        vat += lineSub * (v / 100);
        rows.push(`<tr class="border-b border-slate-50"><td class="py-2">${description}</td><td class="py-2 text-end">${q}</td><td class="py-2 text-end">${fmt(p)}</td><td class="py-2 text-end">${fmt(lineSub)}</td></tr>`);
    });
    document.getElementById('preview-items').innerHTML = rows.join('');
    document.getElementById('preview-subtotal').textContent = fmt(subtotal);
    document.getElementById('preview-vat').textContent = fmt(vat);
    document.getElementById('preview-total').textContent = fmt(subtotal + vat - (parseFloat(document.getElementById('discount_total').value) || 0));

    document.getElementById('preview-modal').showModal();
});
</script>
@endsection
