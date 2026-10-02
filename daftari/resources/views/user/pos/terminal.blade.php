@extends('layouts.app')

@section('title', __('Point of Sale'))

@section('content')
<input type="hidden" id="csrf-token" value="{{ csrf_token() }}">

<div x-data="posTerminal({
        registerId: {{ $register->id }},
        itemLookupUrl: '{{ route('app.pos.item-lookup') }}',
        checkoutUrl: '{{ route('app.pos.checkout') }}',
    })" class="grid lg:grid-cols-3 gap-6">

    {{-- Search & cart --}}
    <div class="lg:col-span-2 space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-lg font-semibold text-slate-900">{{ $register->name }}</h1>
                <p class="text-xs text-slate-400">{{ __('Shift opened') }}: {{ $shift->opened_at->format('Y-m-d H:i') }} · {{ __('Opening cash') }}: {{ number_format($shift->opening_cash, 2) }}</p>
            </div>
            <a href="{{ route('app.pos.shift.close', $shift) }}" onclick="event.preventDefault(); document.getElementById('close-shift-modal').showModal()" class="rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-600 hover:border-red-300">{{ __('Close shift') }}</a>
        </div>

        <div class="relative">
            <input type="text" x-model="query" @input.debounce.250ms="search()" @keydown.enter.prevent="search(true)" placeholder="{{ __('Scan barcode or search by name...') }}" autofocus class="w-full rounded-xl border border-slate-200 px-4 py-3 text-lg focus:border-brand-500 focus:ring-brand-500">

            <div x-show="results.length > 0" x-cloak @click.outside="results = []" class="absolute z-10 mt-1 w-full rounded-xl border border-slate-200 bg-white shadow-lg max-h-72 overflow-y-auto">
                <template x-for="result in results" :key="result.id">
                    <button type="button" @click="addToCart(result)" class="flex w-full items-center justify-between px-4 py-2.5 text-start hover:bg-slate-50">
                        <span class="text-sm font-medium text-slate-800" x-text="result.name"></span>
                        <span class="text-sm text-slate-500 tabular-nums" x-text="result.unit_price.toFixed(2)"></span>
                    </button>
                </template>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-100">
                        <th class="px-4 py-3 font-medium">{{ __('Item') }}</th>
                        <th class="px-4 py-3 font-medium w-24">{{ __('Qty') }}</th>
                        <th class="px-4 py-3 font-medium text-end">{{ __('Price') }}</th>
                        <th class="px-4 py-3 font-medium text-end">{{ __('Total') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="cart.length === 0">
                        <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">{{ __('Cart is empty — search or scan an item to begin.') }}</td></tr>
                    </template>
                    <template x-for="(line, index) in cart" :key="index">
                        <tr class="border-b border-slate-50 last:border-0">
                            <td class="px-4 py-2.5 font-medium text-slate-800" x-text="line.name"></td>
                            <td class="px-4 py-2.5">
                                <input type="number" min="0.001" step="0.001" x-model.number="line.quantity" @input="recalc()" class="w-20 rounded-lg border border-slate-200 text-sm text-center focus:border-brand-500 focus:ring-brand-500">
                            </td>
                            <td class="px-4 py-2.5 text-end">
                                <input type="number" min="0" step="0.01" x-model.number="line.unit_price" @input="recalc()" class="w-24 rounded-lg border border-slate-200 text-sm text-end focus:border-brand-500 focus:ring-brand-500">
                            </td>
                            <td class="px-4 py-2.5 text-end font-semibold text-slate-900 tabular-nums" x-text="lineTotal(line).toFixed(2)"></td>
                            <td class="px-4 py-2.5 text-end">
                                <button type="button" @click="cart.splice(index, 1); recalc()" class="text-red-500 hover:text-red-700">✕</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Totals & payment --}}
    <div class="space-y-4">
        <div class="bg-white rounded-xl border border-slate-100 p-5 space-y-2 text-sm">
            <div class="flex justify-between text-slate-500"><span>{{ __('Subtotal') }}</span><span class="tabular-nums" x-text="subtotal.toFixed(2)"></span></div>
            <div class="flex justify-between text-slate-500"><span>{{ __('VAT') }}</span><span class="tabular-nums" x-text="vatTotal.toFixed(2)"></span></div>
            <div class="flex justify-between text-lg font-bold text-slate-900 pt-2 border-t border-slate-100"><span>{{ __('Total') }}</span><span class="tabular-nums" x-text="total.toFixed(2)"></span></div>
        </div>

        <div class="bg-white rounded-xl border border-slate-100 p-5 space-y-3">
            <h3 class="text-sm font-semibold text-slate-900">{{ __('Payment') }}</h3>
            <div class="grid grid-cols-3 gap-2">
                <button type="button" @click="setFullPayment('cash')" class="rounded-lg border border-slate-200 py-2 text-sm font-semibold text-slate-600 hover:border-brand-400 hover:text-brand-700">{{ __('Cash') }}</button>
                <button type="button" @click="setFullPayment('card')" class="rounded-lg border border-slate-200 py-2 text-sm font-semibold text-slate-600 hover:border-brand-400 hover:text-brand-700">{{ __('Card') }}</button>
                <button type="button" @click="payments.push({ method: 'other', amount: 0, reference: '' }); recalc()" class="rounded-lg border border-slate-200 py-2 text-sm font-semibold text-slate-600 hover:border-brand-400 hover:text-brand-700">{{ __('+ Split') }}</button>
            </div>

            <template x-for="(payment, index) in payments" :key="index">
                <div class="flex items-center gap-2">
                    <select x-model="payment.method" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="cash">{{ __('Cash') }}</option>
                        <option value="card">{{ __('Card') }}</option>
                        <option value="other">{{ __('Other') }}</option>
                    </select>
                    <input type="number" min="0" step="0.01" x-model.number="payment.amount" class="w-24 rounded-lg border border-slate-200 text-sm text-end focus:border-brand-500 focus:ring-brand-500">
                    <button type="button" @click="payments.splice(index, 1)" class="text-red-500 hover:text-red-700">✕</button>
                </div>
            </template>

            <p class="text-xs" :class="paidTotal().toFixed(2) === total.toFixed(2) ? 'text-emerald-600' : 'text-amber-600'">
                {{ __('Paid') }}: <span x-text="paidTotal().toFixed(2)"></span> / <span x-text="total.toFixed(2)"></span>
            </p>

            <template x-if="error">
                <p class="text-xs text-red-600" x-text="error"></p>
            </template>

            <button type="button" @click="checkout()" :disabled="cart.length === 0 || submitting" class="w-full rounded-lg bg-brand-600 px-4 py-3 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50">
                <span x-show="!submitting">{{ __('Complete sale') }}</span>
                <span x-show="submitting">{{ __('Processing...') }}</span>
            </button>
        </div>
    </div>

    <dialog id="close-shift-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-sm backdrop:bg-slate-900/40">
        <form method="POST" action="{{ route('app.pos.shift.close', $shift) }}" class="p-6 space-y-4">
            @csrf
            <h3 class="text-lg font-bold text-slate-900">{{ __('Close shift') }}</h3>
            <p class="text-sm text-slate-500">{{ __('Count the cash in the drawer now.') }}</p>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Counted cash') }}</label>
                <input type="number" step="0.01" min="0" name="counted_cash" required class="mt-1 w-full rounded-lg border border-slate-200 text-lg focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div class="flex gap-3">
                <button type="button" onclick="document.getElementById('close-shift-modal').close()" class="flex-1 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600">{{ __('Cancel') }}</button>
                <button type="submit" class="flex-1 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Close shift') }}</button>
            </div>
        </form>
    </dialog>
</div>

<script>
function posTerminal(config) {
    return {
        registerId: config.registerId,
        query: '',
        results: [],
        cart: [],
        payments: [],
        subtotal: 0,
        vatTotal: 0,
        total: 0,
        error: null,
        submitting: false,

        async search(forceFirst = false) {
            if (this.query.trim().length < 1) { this.results = []; return; }
            const res = await fetch(config.itemLookupUrl + '?q=' + encodeURIComponent(this.query));
            const data = await res.json();
            if (forceFirst && data.length === 1) {
                this.addToCart(data[0]);
                return;
            }
            this.results = data;
        },

        addToCart(result) {
            const existing = this.cart.find(l => l.item_id === result.id);
            if (existing) {
                existing.quantity += 1;
            } else {
                this.cart.push({
                    item_id: result.id,
                    name: result.name,
                    quantity: 1,
                    unit_price: result.unit_price,
                    vat_rate: result.vat_rate,
                });
            }
            this.query = '';
            this.results = [];
            this.recalc();
        },

        lineTotal(line) {
            const net = (line.quantity || 0) * (line.unit_price || 0);
            return net + net * ((line.vat_rate || 0) / 100);
        },

        recalc() {
            this.subtotal = this.cart.reduce((sum, l) => sum + (l.quantity || 0) * (l.unit_price || 0), 0);
            this.vatTotal = this.cart.reduce((sum, l) => sum + ((l.quantity || 0) * (l.unit_price || 0)) * ((l.vat_rate || 0) / 100), 0);
            this.total = this.subtotal + this.vatTotal;

            if (this.payments.length === 1) {
                this.payments[0].amount = Math.round(this.total * 100) / 100;
            }
        },

        paidTotal() {
            return this.payments.reduce((sum, p) => sum + (p.amount || 0), 0);
        },

        setFullPayment(method) {
            this.payments = [{ method, amount: Math.round(this.total * 100) / 100, reference: '' }];
        },

        async checkout() {
            this.error = null;

            if (Math.abs(this.paidTotal() - this.total) > 0.01) {
                this.error = @js(__('Payments must add up to the total.'));
                return;
            }

            this.submitting = true;

            const token = document.getElementById('csrf-token').value;
            const res = await fetch(config.checkoutUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({
                    register_id: this.registerId,
                    lines: this.cart.map(l => ({ item_id: l.item_id, quantity: l.quantity, unit_price: l.unit_price })),
                    payments: this.payments,
                }),
            });

            this.submitting = false;

            if (res.redirected) {
                window.location.href = res.url;
                return;
            }

            if (!res.ok) {
                try {
                    const body = await res.json();
                    this.error = body.errors ? Object.values(body.errors).flat().join(' ') : (body.message || @js(__('Something went wrong.')));
                } catch (e) {
                    this.error = @js(__('Something went wrong.'));
                }
            }
        },
    };
}
</script>
@endsection
