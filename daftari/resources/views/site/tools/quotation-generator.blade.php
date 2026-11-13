@extends('layouts.site')

@section('title', __('Free Quotation Generator') . ' · Daftari')

@section('content')
<style>
    @media print {
        header, footer, .print-hide { display: none !important; }
        .print-area { box-shadow: none !important; border: none !important; }
    }
</style>

@include('site.tools.partials.header', ['title' => __('Free Quotation Generator by Daftari'), 'description' => __('Build a professional quotation with line items and a validity date, then print it or save as PDF.')])

<section class="mx-auto max-w-6xl px-6 pb-16 grid lg:grid-cols-2 gap-8">
    <div class="print-hide space-y-6">
        <div class="rounded-xl border border-slate-100 bg-white p-6">
            <h3 class="font-semibold text-slate-900 mb-4">{{ __('From') }}</h3>
            <input type="text" id="qg-issuer-name" placeholder="{{ __('Name / business') }}" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>

        <div class="rounded-xl border border-slate-100 bg-white p-6">
            <h3 class="font-semibold text-slate-900 mb-4">{{ __('To') }}</h3>
            <input type="text" id="qg-customer-name" placeholder="{{ __('Customer name') }}" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>

        <div class="rounded-xl border border-slate-100 bg-white p-6">
            <h3 class="font-semibold text-slate-900 mb-4">{{ __('Document details') }}</h3>
            <div class="grid grid-cols-2 gap-3 mb-4">
                <div>
                    <label class="block text-xs text-slate-500 mb-1">{{ __('Quotation no.') }}</label>
                    <input type="text" id="qg-number" value="Q-0001" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1">{{ __('Valid until') }}</label>
                    <input type="date" id="qg-valid-until" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
            </div>

            <label class="block text-xs text-slate-500 mb-1">{{ __('Line items') }}</label>
            <div id="qg-items"></div>
            <button type="button" id="qg-add-item" class="mt-2 text-sm font-semibold text-brand-700 hover:underline">{{ __('+ Add item') }}</button>

            <label class="block text-xs text-slate-500 mb-1 mt-4">{{ __('Notes (optional)') }}</label>
            <textarea id="qg-notes" rows="2" placeholder="{{ __('Payment terms, delivery time, ...') }}" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
        </div>

        <button type="button" onclick="window.print()" class="w-full rounded-lg bg-brand-600 px-6 py-3 font-semibold text-white hover:bg-brand-700">{{ __('Print / Save as PDF') }}</button>
    </div>

    <div class="print-area rounded-xl border border-slate-100 bg-white p-8 h-fit">
        <h2 class="text-xl font-bold text-slate-900">{{ __('Quotation') }}</h2>
        <p class="text-xs text-slate-500" id="qg-p-number">{{ __('No.') }}: —</p>
        <p class="text-xs text-slate-500" id="qg-p-valid">{{ __('Valid until') }}: —</p>

        <div class="mt-6 grid grid-cols-2 gap-6 text-sm">
            <div>
                <div class="text-xs text-slate-400 uppercase">{{ __('From') }}</div>
                <div id="qg-p-issuer" class="font-medium text-slate-800">—</div>
            </div>
            <div>
                <div class="text-xs text-slate-400 uppercase">{{ __('To') }}</div>
                <div id="qg-p-customer" class="font-medium text-slate-800">—</div>
            </div>
        </div>

        <table class="w-full text-sm mt-6">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-200">
                    <th class="py-2">{{ __('Description') }}</th>
                    <th class="py-2 text-end">{{ __('Qty') }}</th>
                    <th class="py-2 text-end">{{ __('Price') }}</th>
                    <th class="py-2 text-end">{{ __('Subtotal') }}</th>
                </tr>
            </thead>
            <tbody id="qg-p-items"></tbody>
        </table>
        <div class="mt-4 flex justify-end">
            <div class="w-full max-w-xs space-y-1 text-sm">
                <div class="flex justify-between font-bold text-slate-900 pt-2 border-t border-slate-200"><span>{{ __('Estimated total') }}</span><span id="qg-p-total">0.00 SAR</span></div>
            </div>
        </div>
        <p id="qg-p-notes" class="mt-6 text-xs text-slate-500"></p>
        <p class="mt-8 text-[10px] text-slate-400 text-center print-hide">{{ __('Created with Daftari\'s free online tool. Convert quotations straight into compliant invoices by signing up at Daftari.') }}</p>
    </div>
</section>

@include('site.tools.partials.cta')

<script>
(function () {
    const itemsBody = document.getElementById('qg-items');
    const previewItems = document.getElementById('qg-p-items');
    let items = [];
    let rowId = 0;

    const defaultValidUntil = new Date();
    defaultValidUntil.setDate(defaultValidUntil.getDate() + 14);
    document.getElementById('qg-valid-until').value = defaultValidUntil.toISOString().slice(0, 10);

    function fmt(n) { return (Math.round(n * 100) / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

    function addItem() {
        const id = rowId++;
        items.push({ id, description: '', qty: 1, price: 0 });
        renderForm();
        recalc();
    }

    function renderForm() {
        itemsBody.innerHTML = '';
        items.forEach(item => {
            const row = document.createElement('div');
            row.className = 'grid grid-cols-12 gap-2 mb-2 items-center';
            row.innerHTML = `
                <input type="text" placeholder="${@json(__('Description'))}" class="col-span-7 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500" value="${item.description}" data-field="description">
                <input type="number" min="0" step="0.01" class="col-span-2 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500" value="${item.qty}" data-field="qty">
                <input type="number" min="0" step="0.01" class="col-span-2 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500" value="${item.price}" data-field="price">
                <button type="button" class="col-span-1 text-slate-400 hover:text-red-600" data-remove>&times;</button>
            `;
            row.querySelectorAll('[data-field]').forEach(input => {
                input.addEventListener('input', () => {
                    const field = input.dataset.field;
                    item[field] = field === 'description' ? input.value : parseFloat(input.value) || 0;
                    recalc();
                });
            });
            row.querySelector('[data-remove]').addEventListener('click', () => {
                items = items.filter(i => i.id !== item.id);
                renderForm();
                recalc();
            });
            itemsBody.appendChild(row);
        });
    }

    function recalc() {
        let total = 0;
        previewItems.innerHTML = '';
        items.forEach(item => {
            const lineTotal = item.qty * item.price;
            total += lineTotal;
            const tr = document.createElement('tr');
            tr.className = 'border-b border-slate-50';
            tr.innerHTML = `<td class="py-2">${item.description || '—'}</td><td class="py-2 text-end">${item.qty}</td><td class="py-2 text-end">${fmt(item.price)}</td><td class="py-2 text-end">${fmt(lineTotal)}</td>`;
            previewItems.appendChild(tr);
        });

        document.getElementById('qg-p-number').textContent = @json(__('No.')) + ': ' + (document.getElementById('qg-number').value || '—');
        document.getElementById('qg-p-valid').textContent = @json(__('Valid until')) + ': ' + (document.getElementById('qg-valid-until').value || '—');
        document.getElementById('qg-p-issuer').textContent = document.getElementById('qg-issuer-name').value || '—';
        document.getElementById('qg-p-customer').textContent = document.getElementById('qg-customer-name').value || '—';
        document.getElementById('qg-p-total').textContent = fmt(total) + ' SAR';
        document.getElementById('qg-p-notes').textContent = document.getElementById('qg-notes').value || '';
    }

    document.getElementById('qg-add-item').addEventListener('click', addItem);
    ['qg-issuer-name', 'qg-customer-name', 'qg-number', 'qg-valid-until', 'qg-notes'].forEach(id => {
        document.getElementById(id).addEventListener('input', recalc);
        document.getElementById(id).addEventListener('change', recalc);
    });

    addItem();
})();
</script>
@endsection
