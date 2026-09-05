@extends('layouts.app')

@section('title', __('Create Credit Note'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ __('Create Credit Note') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Crediting invoice :number for :client', ['number' => $invoice->invoice_number, 'client' => $invoice->client->name]) }}</p>
    </div>
    <a href="{{ route('app.credit-notes.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</a>
</div>

<form method="POST" action="{{ route('app.credit-notes.store') }}" class="space-y-6">
    @csrf
    <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">

    <div class="bg-white rounded-xl border border-slate-100 p-6 grid sm:grid-cols-3 gap-5">
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Credit note number') }}</label>
            <input type="text" value="{{ $nextNumberPreview }}" disabled class="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 text-slate-400">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Issue date') }}</label>
            <input type="date" name="issue_date" value="{{ old('issue_date', now()->toDateString()) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Reason (optional)') }}</label>
            <input type="text" name="reason" value="{{ old('reason') }}" placeholder="{{ __('e.g. returned goods, pricing error') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h2 class="font-semibold text-slate-900 mb-4">{{ __('Line items to credit') }}</h2>
        <p class="text-xs text-slate-400 mb-4">{{ __('Adjust quantities to credit only part of an invoice line, or clear a line entirely to leave it out.') }}</p>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-100">
                        <th class="py-2 pe-3 font-medium w-2/5">{{ __('Description') }}</th>
                        <th class="py-2 pe-3 font-medium w-24">{{ __('Qty') }}</th>
                        <th class="py-2 pe-3 font-medium w-28">{{ __('Unit price') }}</th>
                        <th class="py-2 pe-3 font-medium w-24">{{ __('VAT %') }}</th>
                        <th class="py-2 font-medium w-28 text-end">{{ __('Line total') }}</th>
                    </tr>
                </thead>
                <tbody id="items-body">
                    @foreach ($invoice->items as $index => $item)
                        <tr class="border-b border-slate-50" data-row>
                            <td class="py-2 pe-3">
                                <input type="hidden" name="items[{{ $index }}][invoice_item_id]" value="{{ $item->id }}">
                                <input type="text" name="items[{{ $index }}][description]" value="{{ $item->description }}" required class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                            </td>
                            <td class="py-2 pe-3">
                                <input type="number" step="0.01" min="0" max="{{ $item->quantity }}" name="items[{{ $index }}][quantity]" value="{{ $item->quantity }}" data-qty class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                            </td>
                            <td class="py-2 pe-3">
                                <input type="number" step="0.01" min="0" name="items[{{ $index }}][unit_price]" value="{{ $item->unit_price }}" data-price class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                            </td>
                            <td class="py-2 pe-3">
                                <input type="number" step="0.01" min="0" max="100" name="items[{{ $index }}][vat_rate]" value="{{ $item->vat_rate }}" data-vat class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                            </td>
                            <td class="py-2 text-end font-medium" data-line-total>{{ \App\Support\Money::format($item->line_total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6 flex justify-end">
            <div class="w-full max-w-xs space-y-2 text-sm">
                <div class="flex justify-between text-slate-500"><span>{{ __('Subtotal') }}</span><span id="summary-subtotal">{{ \App\Support\Money::format(0) }}</span></div>
                <div class="flex justify-between text-slate-500"><span>{{ __('VAT') }}</span><span id="summary-vat">{{ \App\Support\Money::format(0) }}</span></div>
                <div class="flex justify-between font-bold text-slate-900 text-base border-t border-slate-100 pt-2"><span>{{ __('Total credit') }}</span><span id="summary-total">{{ \App\Support\Money::format(0) }}</span></div>
            </div>
        </div>
    </div>

    <button type="submit" class="w-full rounded-lg bg-brand-800 px-6 py-3 font-semibold text-white hover:bg-brand-900">{{ __('Issue credit note') }}</button>
</form>

<script>
(function () {
    const CURRENCY_SYMBOL = '{{ \App\Support\Money::symbol() }}';
    const rows = () => document.querySelectorAll('#items-body tr[data-row]');

    function recalc() {
        let subtotal = 0, vat = 0;

        rows().forEach(row => {
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

    document.getElementById('items-body').addEventListener('input', recalc);
    recalc();
})();
</script>
@endsection
