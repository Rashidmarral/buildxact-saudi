@extends('layouts.app')

@section('title', __('New Bill'))

@section('content')
@php
$company = auth()->user()->company;
@endphp

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('Create Bill') }}</h1>
    <div class="flex items-center gap-3">
        <button type="button" id="preview-btn" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Preview Bill') }}</button>
        <a href="{{ route('app.bills.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</a>
        <div class="relative">
            <div class="flex">
                <button type="submit" form="bill-form" name="post_immediately" value="0" class="rounded-s-lg bg-brand-700 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-800">{{ __('Save as draft') }}</button>
                <button type="button" id="save-menu-toggle" class="rounded-e-lg border-s border-brand-800 bg-brand-700 px-2 text-white hover:bg-brand-800">▾</button>
            </div>
            <div id="save-menu" class="hidden absolute end-0 mt-1 w-48 rounded-lg border border-slate-200 bg-white shadow-lg z-10">
                <button type="submit" form="bill-form" name="post_immediately" value="1" class="block w-full text-start px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">{{ __('Save & post') }}</button>
            </div>
        </div>
    </div>
</div>

<form method="POST" action="{{ route('app.bills.store') }}" id="bill-form" class="space-y-6">
    @csrf

    <div class="bg-white rounded-xl border border-slate-100 p-6 flex items-center justify-between gap-6">
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
        <div class="text-end">
            <p class="text-xs font-semibold uppercase text-slate-400">{{ __('Bill number') }}</p>
            <p class="font-semibold text-slate-800">{{ $nextNumberPreview }}</p>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6 grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Supplier') }}</label>
            <select name="supplier_id" id="pv-supplier" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('Select a supplier') }}</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Supplier reference (optional)') }}</label>
            <input type="text" name="supplier_reference" value="{{ old('supplier_reference') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Bill date') }}</label>
            <input type="date" name="bill_date" id="pv-bill-date" value="{{ old('bill_date', optional($bill->bill_date)->format('Y-m-d')) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Due date') }}</label>
            <input type="date" name="due_date" value="{{ old('due_date', optional($bill->due_date)->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
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
                    <input type="number" step="0.01" min="0" name="discount_total" id="discount_total" value="{{ old('discount_total', 0) }}" class="w-28 rounded-lg border border-slate-200 text-end focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div class="flex justify-between text-slate-500"><span>{{ __('VAT') }}</span><span id="summary-vat">SAR 0.00</span></div>
                <div class="flex justify-between font-bold text-slate-900 text-base pt-2 border-t border-slate-100"><span>{{ __('Total') }}</span><span id="summary-total">SAR 0.00</span></div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <label class="block text-sm font-medium text-slate-700">{{ __('Notes') }}</label>
        <textarea name="notes" id="pv-notes" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('notes') }}</textarea>
    </div>

    <p class="text-xs text-slate-400">{{ __('Attachments can be added once the bill is saved.') }}</p>
</form>

@php
    $catalogJson = json_encode($items->map(function ($i) {
        return ['id' => $i->id, 'name' => $i->name, 'unit_price' => (float) $i->unit_price, 'vat_rate' => (float) $i->vat_rate];
    })->values());
@endphp

<dialog id="preview-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-2xl backdrop:bg-slate-900/40">
    <div class="p-6">
        <div class="flex items-start justify-between mb-4">
            <h3 class="text-lg font-bold text-slate-900">{{ __('Bill preview') }}</h3>
            <button type="button" onclick="document.getElementById('preview-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <div class="flex justify-between items-start">
            <div>
                <h2 class="text-xl font-bold text-slate-900">{{ $company->name }}</h2>
                @if ($company->vat_number)<p class="text-sm text-slate-500">{{ __('VAT') }}: {{ $company->vat_number }}</p>@endif
            </div>
            <div class="text-end text-sm text-slate-500">
                <p class="font-semibold text-slate-800">{{ $nextNumberPreview }}</p>
                <p id="preview-date"></p>
            </div>
        </div>
        <p class="mt-4 text-sm text-slate-500">{{ __('Vendor') }}: <span id="preview-supplier" class="font-medium text-slate-800"></span></p>
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
const tbody = document.getElementById('items-body');
let rowIndex = 0;

function fmt(n) {
    return 'SAR ' + (Math.round(n * 100) / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function addRow() {
    const data = { item_id: '', description: '', quantity: 1, unit_price: 0, vat_rate: 15 };
    const i = rowIndex++;
    const tr = document.createElement('tr');
    tr.className = 'border-b border-slate-50';
    tr.innerHTML = `
        <td class="py-2 pe-3">
            <select data-role="item" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">${@json(__('Custom'))}</option>
                ${CATALOG.map(c => `<option value="${c.id}" data-price="${c.unit_price}" data-vat="${c.vat_rate}" data-name="${c.name}">${c.name}</option>`).join('')}
            </select>
            <input type="hidden" name="items[${i}][item_id]" data-role="item_id" value="${data.item_id}">
        </td>
        <td class="py-2 pe-3"><input type="text" name="items[${i}][description]" data-role="description" value="${data.description}" required class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></td>
        <td class="py-2 pe-3"><input type="number" step="0.01" min="0.01" name="items[${i}][quantity]" data-role="quantity" value="${data.quantity}" required class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></td>
        <td class="py-2 pe-3"><input type="number" step="0.01" min="0" name="items[${i}][unit_price]" data-role="unit_price" value="${data.unit_price}" required class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></td>
        <td class="py-2 pe-3"><input type="number" step="0.01" min="0" max="100" name="items[${i}][vat_rate]" data-role="vat_rate" value="${data.vat_rate}" required class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></td>
        <td class="py-2 pe-3 text-slate-700" data-role="line_total">SAR 0.00</td>
        <td class="py-2"><button type="button" data-role="remove" class="text-slate-400 hover:text-red-600">&times;</button></td>
    `;
    tbody.appendChild(tr);

    const itemSelect = tr.querySelector('[data-role="item"]');
    itemSelect.addEventListener('change', () => {
        const opt = itemSelect.selectedOptions[0];
        tr.querySelector('[data-role="item_id"]').value = itemSelect.value;
        if (itemSelect.value) {
            tr.querySelector('[data-role="description"]').value = opt.dataset.name;
            tr.querySelector('[data-role="unit_price"]').value = opt.dataset.price;
            tr.querySelector('[data-role="vat_rate"]').value = opt.dataset.vat;
        }
        recalc();
    });

    tr.querySelectorAll('input[type="number"]').forEach(el => el.addEventListener('input', recalc));
    tr.querySelector('[data-role="remove"]').addEventListener('click', () => { tr.remove(); recalc(); });

    recalc();
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

document.getElementById('add-row').addEventListener('click', addRow);
document.getElementById('discount_total').addEventListener('input', recalc);
addRow();

document.getElementById('bill-form').addEventListener('submit', (e) => {
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
    const supplierSelect = document.getElementById('pv-supplier');
    document.getElementById('preview-supplier').textContent = supplierSelect.selectedOptions[0]?.text || '—';
    document.getElementById('preview-date').textContent = document.getElementById('pv-bill-date').value || '';
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
