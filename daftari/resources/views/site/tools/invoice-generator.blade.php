@extends('layouts.site')

@section('title', __('Free Invoice Generator') . ' · Daftari')

@section('content')
<style>
    @media print {
        header, footer, .print-hide { display: none !important; }
        .print-area { box-shadow: none !important; border: none !important; }
    }
</style>

@include('site.tools.partials.header', ['title' => __('Free Invoice Generator by Daftari'), 'description' => __("Design your invoice, add line items, and this free generator calculates VAT automatically — then print it or save as PDF from your browser. Nothing is uploaded anywhere.")])

<section class="mx-auto max-w-6xl px-6 pb-8 print-hide">
    <div class="rounded-lg bg-brand-50 border border-brand-100 px-4 py-3 text-sm text-brand-800">
        {{ __('This is a quick printable draft, not a certified tax invoice. A Phase-1-style QR code and sequential numbering are added automatically when you create invoices inside Daftari.') }}
        <a href="{{ route('register') }}" class="font-semibold underline">{{ __('Open Daftari') }}</a>
    </div>
</section>

<section class="mx-auto max-w-6xl px-6 pb-16 grid lg:grid-cols-2 gap-8">
    <div class="print-hide space-y-6">
        <div class="rounded-xl border border-slate-100 bg-white p-6">
            <h3 class="font-semibold text-slate-900 mb-4">{{ __('Issuer details') }}</h3>
            <div class="space-y-3">
                <input type="text" id="ig-issuer-name" placeholder="{{ __('Name / business') }}" class="w-full rounded-lg border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <div class="grid grid-cols-2 gap-3">
                    <input type="text" id="ig-issuer-vat" placeholder="{{ __('VAT number (optional)') }}" class="rounded-lg border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <input type="text" id="ig-issuer-phone" placeholder="{{ __('Phone (optional)') }}" class="rounded-lg border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <input type="text" id="ig-issuer-address" placeholder="{{ __('Address (optional)') }}" class="w-full rounded-lg border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>

        <div class="rounded-xl border border-slate-100 bg-white p-6">
            <h3 class="font-semibold text-slate-900 mb-4">{{ __('Customer details') }}</h3>
            <div class="space-y-3">
                <input type="text" id="ig-customer-name" placeholder="{{ __('Customer name') }}" class="w-full rounded-lg border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <div class="grid grid-cols-2 gap-3">
                    <input type="text" id="ig-customer-vat" placeholder="{{ __('VAT number (optional)') }}" class="rounded-lg border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <input type="text" id="ig-customer-phone" placeholder="{{ __('Phone (optional)') }}" class="rounded-lg border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-slate-100 bg-white p-6">
            <h3 class="font-semibold text-slate-900 mb-4">{{ __('Document details') }}</h3>
            <div class="grid grid-cols-2 gap-3 mb-4">
                <div>
                    <label class="block text-xs text-slate-500 mb-1">{{ __('Invoice no.') }}</label>
                    <input type="text" id="ig-number" value="0001" class="w-full rounded-lg border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1">{{ __('Date') }}</label>
                    <input type="date" id="ig-date" class="w-full rounded-lg border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
            </div>

            <label class="block text-xs text-slate-500 mb-1">{{ __('Line items') }}</label>
            <div id="ig-items"></div>
            <button type="button" id="ig-add-item" class="mt-2 text-sm font-semibold text-brand-700 hover:underline">{{ __('+ Add item') }}</button>

            <div class="grid grid-cols-2 gap-3 mt-4">
                <div>
                    <label class="block text-xs text-slate-500 mb-1">{{ __('Discount on total (optional)') }}</label>
                    <input type="number" id="ig-discount" value="0" min="0" step="0.01" class="w-full rounded-lg border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1">{{ __('Prices entered are') }}</label>
                    <select id="ig-price-mode" class="w-full rounded-lg border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="excl">{{ __('VAT-exclusive') }}</option>
                        <option value="incl">{{ __('VAT-inclusive') }}</option>
                    </select>
                </div>
            </div>

            <label class="block text-xs text-slate-500 mb-1 mt-4">{{ __('Notes (optional)') }}</label>
            <textarea id="ig-notes" rows="2" placeholder="{{ __('Payment terms, offer validity, ...') }}" class="w-full rounded-lg border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
        </div>

        <button type="button" onclick="window.print()" class="w-full rounded-lg bg-brand-600 px-6 py-3 font-semibold text-white hover:bg-brand-700">{{ __('Print / Save as PDF') }}</button>
    </div>

    <div class="print-area rounded-xl border border-slate-100 bg-white p-8 h-fit">
        <div class="rounded bg-amber-50 border border-amber-100 px-3 py-2 text-xs text-amber-700 mb-4 print-hide">{{ __('Draft / template — not a certified tax invoice') }}</div>
        <div class="flex justify-between items-start">
            <div>
                <h2 class="text-xl font-bold text-slate-900">{{ __('Invoice') }}</h2>
                <p class="text-xs text-slate-500" id="ig-p-number">{{ __('No.') }}: —</p>
                <p class="text-xs text-slate-500" id="ig-p-date">{{ __('Date') }}: —</p>
            </div>
        </div>
        <div class="mt-6 grid grid-cols-2 gap-6 text-sm">
            <div>
                <div class="text-xs text-slate-400 uppercase">{{ __('Issuer') }}</div>
                <div id="ig-p-issuer" class="font-medium text-slate-800">—</div>
            </div>
            <div>
                <div class="text-xs text-slate-400 uppercase">{{ __('Customer') }}</div>
                <div id="ig-p-customer" class="font-medium text-slate-800">—</div>
            </div>
        </div>
        <table class="w-full text-sm mt-6">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-200">
                    <th class="py-2">{{ __('Description') }}</th>
                    <th class="py-2 text-end">{{ __('Qty') }}</th>
                    <th class="py-2 text-end">{{ __('Price') }}</th>
                    <th class="py-2 text-end">{{ __('VAT %') }}</th>
                    <th class="py-2 text-end">{{ __('Subtotal') }}</th>
                </tr>
            </thead>
            <tbody id="ig-p-items"></tbody>
        </table>
        <div class="mt-4 flex justify-end">
            <div class="w-full max-w-xs space-y-1 text-sm">
                <div class="flex justify-between text-slate-500"><span>{{ __('Subtotal (excl. VAT)') }}</span><span id="ig-p-subtotal">0.00 SAR</span></div>
                <div class="flex justify-between text-slate-500"><span id="ig-p-vat-label">{{ __('VAT (15%)') }}</span><span id="ig-p-vat">0.00</span></div>
                <div class="flex justify-between font-bold text-slate-900 pt-2 border-t border-slate-200"><span>{{ __('Total due') }}</span><span id="ig-p-total">0.00 SAR</span></div>
            </div>
        </div>
        <p id="ig-p-notes" class="mt-6 text-xs text-slate-500"></p>
        <div class="mt-8 pt-4 border-t border-slate-100 grid grid-cols-2 text-xs text-slate-400 text-center">
            <div>{{ __('Issuer signature') }}</div>
            <div>{{ __('Recipient signature') }}</div>
        </div>
        <p class="mt-6 text-[10px] text-slate-400 text-center print-hide">{{ __('This invoice was created with Daftari\'s free online tool. For saved, numbered, VAT-compliant invoicing, sign up at Daftari.') }}</p>
    </div>
</section>

@include('site.tools.partials.cta')

<script>
(function () {
    const itemsBody = document.getElementById('ig-items');
    const previewItems = document.getElementById('ig-p-items');
    let items = [];
    let rowId = 0;

    document.getElementById('ig-date').value = new Date().toISOString().slice(0, 10);

    function fmt(n) { return (Math.round(n * 100) / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

    function addItem() {
        const id = rowId++;
        items.push({ id, description: '', qty: 1, price: 0, vat: 15 });
        renderForm();
        recalc();
    }

    function renderForm() {
        itemsBody.innerHTML = '';
        items.forEach(item => {
            const row = document.createElement('div');
            row.className = 'grid grid-cols-12 gap-2 mb-2 items-center';
            row.innerHTML = `
                <input type="text" placeholder="${@json(__('Description'))}" class="col-span-5 rounded-lg border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500" value="${item.description}" data-field="description">
                <input type="number" min="0" step="0.01" class="col-span-2 rounded-lg border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500" value="${item.qty}" data-field="qty">
                <input type="number" min="0" step="0.01" class="col-span-2 rounded-lg border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500" value="${item.price}" data-field="price">
                <input type="number" min="0" step="0.01" class="col-span-2 rounded-lg border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500" value="${item.vat}" data-field="vat">
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
        const priceMode = document.getElementById('ig-price-mode').value;
        const discount = parseFloat(document.getElementById('ig-discount').value) || 0;
        let subtotal = 0, vat = 0;
        previewItems.innerHTML = '';

        items.forEach(item => {
            let lineExclVat, lineVat;
            if (priceMode === 'incl') {
                const lineTotal = item.qty * item.price;
                lineExclVat = lineTotal / (1 + item.vat / 100);
                lineVat = lineTotal - lineExclVat;
            } else {
                lineExclVat = item.qty * item.price;
                lineVat = lineExclVat * (item.vat / 100);
            }
            subtotal += lineExclVat;
            vat += lineVat;

            const tr = document.createElement('tr');
            tr.className = 'border-b border-slate-50';
            tr.innerHTML = `<td class="py-2">${item.description || '—'}</td><td class="py-2 text-end">${item.qty}</td><td class="py-2 text-end">${fmt(item.price)}</td><td class="py-2 text-end">${item.vat}%</td><td class="py-2 text-end">${fmt(lineExclVat)}</td>`;
            previewItems.appendChild(tr);
        });

        const total = subtotal - discount + vat;

        document.getElementById('ig-p-number').textContent = @json(__('No.')) + ': ' + (document.getElementById('ig-number').value || '—');
        document.getElementById('ig-p-date').textContent = @json(__('Date')) + ': ' + (document.getElementById('ig-date').value || '—');
        document.getElementById('ig-p-issuer').textContent = document.getElementById('ig-issuer-name').value || '—';
        document.getElementById('ig-p-customer').textContent = document.getElementById('ig-customer-name').value || '—';
        document.getElementById('ig-p-subtotal').textContent = fmt(subtotal) + ' SAR';
        document.getElementById('ig-p-vat').textContent = fmt(vat);
        document.getElementById('ig-p-total').textContent = fmt(total) + ' SAR';
        document.getElementById('ig-p-notes').textContent = document.getElementById('ig-notes').value || '';
    }

    document.getElementById('ig-add-item').addEventListener('click', addItem);
    ['ig-issuer-name', 'ig-customer-name', 'ig-number', 'ig-date', 'ig-discount', 'ig-price-mode', 'ig-notes'].forEach(id => {
        document.getElementById(id).addEventListener('input', recalc);
        document.getElementById(id).addEventListener('change', recalc);
    });

    addItem();
})();
</script>
@endsection
