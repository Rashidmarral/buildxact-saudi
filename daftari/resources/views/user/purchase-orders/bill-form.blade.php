@extends('layouts.app')

@section('title', __('Bill purchase order'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ __('Bill purchase order') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Creating a bill against :number for :supplier', ['number' => $order->po_number, 'supplier' => $order->supplier->name]) }}</p>
    </div>
    <a href="{{ route('app.purchase-orders.show', $order) }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</a>
</div>

@if ($errors->any())
    <div class="mb-6 rounded-xl border border-red-100 bg-red-50 p-4 text-sm text-red-700">
        <ul class="list-disc ps-5 space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('app.purchase-orders.bill-store', $order) }}" class="space-y-6">
    @csrf

    <div class="bg-white rounded-xl border border-slate-100 p-6 grid sm:grid-cols-3 gap-5">
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Bill number') }}</label>
            <input type="text" value="{{ $nextNumberPreview }}" disabled class="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 text-slate-400">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Bill date') }}</label>
            <input type="date" name="bill_date" value="{{ old('bill_date', now()->toDateString()) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Due date (optional)') }}</label>
            <input type="date" name="due_date" value="{{ old('due_date') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Warehouse (optional)') }}</label>
            <select name="warehouse_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('None') }}</option>
                @foreach ($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" @selected(old('warehouse_id') == $warehouse->id)>{{ $warehouse->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h2 class="font-semibold text-slate-900 mb-1">{{ __('Lines to bill') }}</h2>
        <p class="text-sm text-slate-500 mb-4">{{ __('Quantity is capped at what remains unbilled on each line.') }}</p>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-100">
                        <th class="py-2 pe-3 font-medium w-1/3">{{ __('Description') }}</th>
                        <th class="py-2 pe-3 font-medium text-right">{{ __('Ordered') }}</th>
                        <th class="py-2 pe-3 font-medium text-right">{{ __('Remaining') }}</th>
                        <th class="py-2 pe-3 font-medium w-28">{{ __('Bill qty') }}</th>
                        <th class="py-2 pe-3 font-medium w-28">{{ __('Unit price') }}</th>
                        <th class="py-2 pe-3 font-medium w-24">{{ __('VAT %') }}</th>
                        <th class="py-2 font-medium w-28 text-end">{{ __('Line total') }}</th>
                    </tr>
                </thead>
                <tbody id="items-body">
                    @foreach ($order->items as $index => $item)
                        @php $remaining = $item->remainingQuantity(); @endphp
                        <tr class="border-b border-slate-50 last:border-0 {{ $remaining <= 0.01 ? 'opacity-40' : '' }}" data-row>
                            <td class="py-2 pe-3">
                                {{ $item->description }}
                                <input type="hidden" name="items[{{ $index }}][purchase_order_item_id]" value="{{ $item->id }}">
                                <input type="hidden" name="items[{{ $index }}][description]" value="{{ $item->description }}">
                            </td>
                            <td class="py-2 pe-3 text-right">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
                            <td class="py-2 pe-3 text-right">{{ rtrim(rtrim(number_format($remaining, 2), '0'), '.') }}</td>
                            <td class="py-2 pe-3">
                                <input type="number" step="0.01" min="0" max="{{ $remaining }}" value="{{ $remaining }}"
                                       name="items[{{ $index }}][quantity]" data-qty
                                       {{ $remaining <= 0.01 ? 'disabled' : '' }}
                                       class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                            </td>
                            <td class="py-2 pe-3">
                                <input type="number" step="0.01" min="0" value="{{ $item->unit_price }}"
                                       name="items[{{ $index }}][unit_price]" data-price
                                       class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                            </td>
                            <td class="py-2 pe-3">
                                <input type="number" step="0.01" min="0" max="100" value="{{ $item->vat_rate }}"
                                       name="items[{{ $index }}][vat_rate]" data-vat
                                       class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                            </td>
                            <td class="py-2 text-end font-medium" data-line-total>{{ \App\Support\Money::symbol() }} 0.00</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6 flex justify-end">
            <div class="w-full max-w-xs space-y-2 text-sm">
                <div class="flex justify-between text-slate-500"><span>{{ __('Subtotal') }}</span><span id="summary-subtotal">{{ \App\Support\Money::format(0) }}</span></div>
                <div class="flex justify-between text-slate-500"><span>{{ __('VAT') }}</span><span id="summary-vat">{{ \App\Support\Money::format(0) }}</span></div>
                <div class="flex justify-between font-bold text-slate-900 text-base border-t border-slate-100 pt-2"><span>{{ __('Total bill') }}</span><span id="summary-total">{{ \App\Support\Money::format(0) }}</span></div>
            </div>
        </div>
    </div>

    <button type="submit" class="w-full rounded-lg bg-brand-800 px-6 py-3 font-semibold text-white hover:bg-brand-900">{{ __('Create bill') }}</button>
</form>

<script>
(function () {
    const CURRENCY_SYMBOL = '{{ \App\Support\Money::symbol() }}';
    const body = document.getElementById('items-body');

    function recalc() {
        let subtotal = 0, vat = 0;

        body.querySelectorAll('tr[data-row]').forEach(row => {
            const qtyInput = row.querySelector('[data-qty]');
            const qty = qtyInput.disabled ? 0 : (parseFloat(qtyInput.value) || 0);
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

    body.addEventListener('input', recalc);
    recalc();
})();
</script>
@endsection
