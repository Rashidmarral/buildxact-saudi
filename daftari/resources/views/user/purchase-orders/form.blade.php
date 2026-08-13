@extends('layouts.app')

@section('title', __('New Purchase Order'))

@section('content')
<form method="POST" action="{{ route('app.purchase-orders.store') }}" id="po-form" class="space-y-6">
    @csrf

    <div class="bg-white rounded-xl border border-slate-100 p-6 grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Supplier') }}</label>
            <select name="supplier_id" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('Select a supplier') }}</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Order date') }}</label>
            <input type="date" name="order_date" value="{{ old('order_date', optional($order->order_date)->format('Y-m-d')) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Expected date') }}</label>
            <input type="date" name="expected_date" value="{{ old('expected_date') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('PO number') }}</label>
            <input type="text" value="{{ $nextNumberPreview }}" disabled class="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 text-slate-400">
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
        <textarea name="notes" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('notes') }}</textarea>
    </div>

    <div class="flex gap-3">
        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Save purchase order') }}</button>
        <a href="{{ route('app.purchase-orders.index') }}" class="rounded-lg border border-slate-200 px-6 py-2.5 font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</a>
    </div>
</form>

@php
    $catalogJson = json_encode($items->map(function ($i) {
        return ['id' => $i->id, 'name' => $i->name, 'unit_price' => (float) $i->unit_price, 'vat_rate' => (float) $i->vat_rate];
    })->values());
@endphp

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

document.getElementById('po-form').addEventListener('submit', (e) => {
    if (!tbody.querySelector('tr')) {
        e.preventDefault();
        alert(@json(__('Add at least one line item.')));
    }
});
</script>
@endsection
