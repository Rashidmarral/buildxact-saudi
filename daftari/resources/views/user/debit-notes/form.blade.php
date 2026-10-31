@extends('layouts.app')

@section('title', __('Create Debit Note'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ __('Create Debit Note') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Adding a charge on top of invoice :number for :client', ['number' => $invoice->invoice_number, 'client' => $invoice->client->display_name]) }}</p>
    </div>
    <a href="{{ route('app.debit-notes.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100 p-6 mb-6">
    <h2 class="font-semibold text-slate-900 mb-4">{{ __('Original invoice') }}</h2>
    <div class="grid sm:grid-cols-3 gap-4 text-sm">
        <div><span class="text-slate-400">{{ __('Invoice No.') }}:</span> <span class="font-medium text-slate-800">{{ $invoice->invoice_number }}</span></div>
        <div><span class="text-slate-400">{{ __('Date') }}:</span> <span class="font-medium text-slate-800">{{ $invoice->issue_date->format('Y-m-d') }}</span></div>
        <div><span class="text-slate-400">{{ __('Customer') }}:</span> <span class="font-medium text-slate-800">{{ $invoice->client->display_name }}</span></div>
        @if ($invoice->branch)
            <div><span class="text-slate-400">{{ __('Business Location') }}:</span> <span class="font-medium text-slate-800">{{ $invoice->branch->name }}</span></div>
        @endif
        <div><span class="text-slate-400">{{ __('Total') }}:</span> <span class="font-medium text-slate-800">{{ \App\Support\Money::format($invoice->total) }}</span></div>
        <div><span class="text-slate-400">{{ __('Currency') }}:</span> <span class="font-medium text-slate-800">{{ $invoice->currency }}</span></div>
    </div>
</div>

<form method="POST" action="{{ route('app.debit-notes.store') }}" class="space-y-6">
    @csrf
    <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">

    <div class="bg-white rounded-xl border border-slate-100 p-6 grid sm:grid-cols-3 gap-5">
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Debit note number') }}</label>
            <input type="text" value="{{ $nextNumberPreview }}" disabled class="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 text-slate-400">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Issue date') }}</label>
            <input type="date" name="issue_date" value="{{ old('issue_date', now()->toDateString()) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Reason type') }}</label>
            <select name="reason_type" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('Please select') }}</option>
                @foreach (\App\Models\DebitNote::REASON_TYPES as $type)
                    <option value="{{ $type }}" @selected(old('reason_type') === $type)>
                        {{ match ($type) {
                            'price_correction' => __('Price correction'),
                            'additional_charges' => __('Additional charges or services'),
                            'freight' => __('Freight billed after the invoice'),
                            'other' => __('Other'),
                        } }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="sm:col-span-3">
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Reason for issuance (optional)') }}</label>
            <textarea name="reason" rows="2" placeholder="{{ __('e.g. under-billed charge, pricing correction') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('reason') }}</textarea>
        </div>
    </div>

    @if ($invoice->items->isNotEmpty())
        <div class="bg-white rounded-xl border border-slate-100 p-6">
            <h2 class="font-semibold text-slate-900 mb-1">{{ __('Invoice lines') }}</h2>
            <p class="text-xs text-slate-400 mb-4">{{ __('What was originally billed — for reference. Click "Correct price" to pre-fill a debit note line for just the increase on that line.') }}</p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 border-b border-slate-100">
                            <th class="py-2 pe-3 font-medium">#</th>
                            <th class="py-2 pe-3 font-medium">{{ __('Product') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('Quantity') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('Unit Price') }}</th>
                            <th class="py-2 pe-3 font-medium">{{ __('Tax') }}</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice->items as $index => $line)
                            <tr class="border-b border-slate-50 last:border-0 text-slate-600">
                                <td class="py-2 pe-3">{{ $index + 1 }}</td>
                                <td class="py-2 pe-3">{{ $line->description }}</td>
                                <td class="py-2 pe-3">{{ rtrim(rtrim(number_format($line->quantity, 2), '0'), '.') }}</td>
                                <td class="py-2 pe-3">{{ \App\Support\Money::format($line->unit_price) }}</td>
                                <td class="py-2 pe-3">{{ __('VAT @:rate%', ['rate' => rtrim(rtrim(number_format($line->vat_rate, 2), '0'), '.')]) }}</td>
                                <td class="py-2 text-end">
                                    <button type="button" class="correct-price-btn text-xs font-semibold text-brand-700 hover:underline"
                                        data-description="{{ __('Price correction — :description', ['description' => $line->description]) }}"
                                        data-quantity="{{ $line->quantity }}" data-vat="{{ $line->vat_rate }}">
                                        {{ __('Correct price') }}
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h2 class="font-semibold text-slate-900 mb-1">{{ __('Debit note lines') }}</h2>
        <p class="text-xs text-slate-400 mb-4">{{ __('Unit price before VAT, in the invoice currency. For a price correction enter only the increase per unit.') }}</p>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-100">
                        <th class="py-2 pe-3 font-medium w-2/5">{{ __('Description') }}</th>
                        <th class="py-2 pe-3 font-medium w-24">{{ __('Qty') }}</th>
                        <th class="py-2 pe-3 font-medium w-28">{{ __('Unit price') }}</th>
                        <th class="py-2 pe-3 font-medium w-24">{{ __('VAT %') }}</th>
                        <th class="py-2 font-medium w-28 text-end">{{ __('Line total') }}</th>
                        <th class="py-2 ps-2 w-8"></th>
                    </tr>
                </thead>
                <tbody id="items-body"></tbody>
            </table>
        </div>

        <button type="button" id="add-row" class="mt-3 text-sm font-semibold text-brand-700 hover:underline">{{ __('+ Add charge line') }}</button>

        <div class="mt-6 flex justify-end">
            <div class="w-full max-w-xs space-y-2 text-sm">
                <div class="flex justify-between text-slate-500"><span>{{ __('Subtotal') }}</span><span id="summary-subtotal">{{ \App\Support\Money::format(0) }}</span></div>
                <div class="flex justify-between text-slate-500"><span>{{ __('VAT') }}</span><span id="summary-vat">{{ \App\Support\Money::format(0) }}</span></div>
                <div class="flex justify-between font-bold text-slate-900 text-base border-t border-slate-100 pt-2"><span>{{ __('Total debit') }}</span><span id="summary-total">{{ \App\Support\Money::format(0) }}</span></div>
            </div>
        </div>
    </div>

    <button type="submit" class="w-full rounded-lg bg-brand-800 px-6 py-3 font-semibold text-white hover:bg-brand-900">{{ __('Issue debit note') }}</button>
</form>

<script>
(function () {
    const CURRENCY_SYMBOL = '{{ \App\Support\Money::symbol() }}';
    const DEFAULT_VAT = 15;
    const body = document.getElementById('items-body');
    let rowIndex = 0;

    function addRow(prefill) {
        prefill = prefill || {};
        const index = rowIndex++;
        const tr = document.createElement('tr');
        tr.className = 'border-b border-slate-50';
        tr.setAttribute('data-row', '');
        tr.innerHTML = `
            <td class="py-2 pe-3">
                <input type="text" name="items[${index}][description]" value="${prefill.description ? prefill.description.replace(/"/g, '&quot;') : ''}" required class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </td>
            <td class="py-2 pe-3">
                <input type="number" step="0.01" min="0.01" name="items[${index}][quantity]" value="${prefill.quantity || 1}" data-qty class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </td>
            <td class="py-2 pe-3">
                <input type="number" step="0.01" min="0" name="items[${index}][unit_price]" value="${prefill.unitPrice || 0}" data-price class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </td>
            <td class="py-2 pe-3">
                <input type="number" step="0.01" min="0" max="100" name="items[${index}][vat_rate]" value="${prefill.vat !== undefined ? prefill.vat : DEFAULT_VAT}" data-vat class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </td>
            <td class="py-2 text-end font-medium" data-line-total>${CURRENCY_SYMBOL} 0.00</td>
            <td class="py-2 ps-2 text-end">
                <button type="button" data-remove class="text-slate-400 hover:text-red-600">&times;</button>
            </td>
        `;
        body.appendChild(tr);
        recalc();

        return tr;
    }

    function recalc() {
        let subtotal = 0, vat = 0;

        body.querySelectorAll('tr[data-row]').forEach(row => {
            const qty = parseFloat(row.querySelector('[data-qty]').value) || 0;
            const price = parseFloat(row.querySelector('[data-price]').value) || 0;
            const rate = parseFloat(row.querySelector('[data-vat]').value) || 0;
            const lineSubtotal = qty * price;
            const lineVat = lineSubtotal * rate / 100;
            row.querySelector('[data-line-total]').textContent = CURRENCY_SYMBOL + ' ' + (lineSubtotal + lineVat).toFixed(2);
            subtotal += lineSubtotal;
            vat += lineVat;
        });

        document.getElementById('summary-subtotal').textContent = CURRENCY_SYMBOL + ' ' + subtotal.toFixed(2);
        document.getElementById('summary-vat').textContent = CURRENCY_SYMBOL + ' ' + vat.toFixed(2);
        document.getElementById('summary-total').textContent = CURRENCY_SYMBOL + ' ' + (subtotal + vat).toFixed(2);
    }

    document.getElementById('add-row').addEventListener('click', () => addRow());
    body.addEventListener('input', recalc);
    body.addEventListener('click', (e) => {
        if (e.target.closest('[data-remove]')) {
            e.target.closest('tr[data-row]').remove();
            recalc();
        }
    });

    // "Correct price" pre-fills a new debit note line for just the
    // increase on that invoice line — unit price deliberately left
    // blank (0) rather than copying the original price, since this
    // document only bills the shortfall, not the full corrected amount.
    document.querySelectorAll('.correct-price-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const row = addRow({
                description: btn.dataset.description,
                quantity: btn.dataset.quantity,
                vat: btn.dataset.vat,
            });
            row.querySelector('[data-price]').focus();
        });
    });

    addRow();
})();
</script>
@endsection
