@extends('layouts.app')

@section('title', __('Order :number', ['number' => $order->order_number]))

@php
    $subtotal = $order->items->sum(fn ($line) => (float) $line->quantity * (float) $line->unit_price);
    $vatTotal = $order->items->sum(fn ($line) => (float) $line->quantity * (float) $line->unit_price * ((float) $line->vat_rate / 100));
    $total = $subtotal + $vatTotal;
    $kitchenLabels = ['pending' => __('Pending'), 'preparing' => __('Preparing'), 'ready' => __('Ready'), 'served' => __('Served')];
    $kitchenStyle = ['pending' => 'bg-slate-100 text-slate-600', 'preparing' => 'bg-amber-50 text-amber-700', 'ready' => 'bg-emerald-50 text-emerald-700', 'served' => 'bg-slate-200 text-slate-700'];
@endphp

@section('content')
<input type="hidden" id="csrf-token" value="{{ csrf_token() }}">

<div class="flex items-start justify-between mb-6">
    <div>
        <a href="{{ route('app.restaurant.orders.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; {{ __('Orders') }}</a>
        <h1 class="text-2xl font-bold text-slate-900 mt-1">{{ $order->order_number }}</h1>
        <p class="text-sm text-slate-500 mt-1">
            {{ $order->order_type === 'dine_in' ? __('Dine-in') : __('Takeaway') }}
            @if ($order->table) · {{ __('Table') }} {{ $order->table->name }} @endif
            @if ($order->customer_name) · {{ $order->customer_name }} @endif
            @if ($order->customer_phone) · {{ $order->customer_phone }} @endif
        </p>
    </div>
    <div class="flex items-center gap-3">
        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $order->status === 'completed' ? 'bg-emerald-50 text-emerald-700' : ($order->status === 'cancelled' ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700') }}">
            {{ ucfirst(str_replace('_', ' ', $order->status)) }}
        </span>
        @if ($order->isOpen())
            @if ($order->order_type === 'takeaway' && $order->customer_phone)
                <form method="POST" action="{{ route('app.restaurant.orders.notify-sms', $order) }}">
                    @csrf
                    <button type="submit" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-300">{{ __('Notify customer (SMS)') }}</button>
                </form>
                <form method="POST" action="{{ route('app.restaurant.orders.notify-whatsapp', $order) }}">
                    @csrf
                    <button type="submit" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-300">{{ __('Notify customer (WhatsApp)') }}</button>
                </form>
            @endif
            <button type="button" onclick="document.getElementById('cancel-order-modal').showModal()" class="rounded-lg border border-red-200 bg-white px-3 py-1.5 text-xs font-semibold text-red-600 hover:border-red-300">{{ __('Cancel order') }}</button>
        @elseif ($order->sale)
            <a href="{{ route('app.pos.sales.show', $order->sale) }}" class="text-sm text-brand-700 hover:underline">{{ __('View receipt') }}</a>
        @endif
    </div>
</div>

@if ($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
@endif

<div class="bg-white rounded-xl border border-slate-100 overflow-hidden mb-6">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-4 py-3 font-medium">{{ __('Item') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Qty') }}</th>
                <th class="px-4 py-3 font-medium text-end">{{ __('Price') }}</th>
                <th class="px-4 py-3 font-medium text-end">{{ __('Total') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Kitchen status') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($order->items as $line)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-4 py-2.5">
                        <p class="font-medium text-slate-800">{{ $line->description }}</p>
                        @if ($line->notes)<p class="text-xs text-slate-400">{{ $line->notes }}</p>@endif
                    </td>
                    <td class="px-4 py-2.5">{{ rtrim(rtrim(number_format($line->quantity, 3), '0'), '.') }}</td>
                    <td class="px-4 py-2.5 text-end tabular-nums">{{ number_format($line->unit_price, 2) }}</td>
                    <td class="px-4 py-2.5 text-end font-semibold tabular-nums">{{ number_format($line->lineTotal(), 2) }}</td>
                    <td class="px-4 py-2.5">
                        @if ($order->isOpen())
                            <select data-item-status-select data-status-url="{{ route('app.restaurant.order-items.status', $line) }}" class="rounded-lg border border-slate-200 text-xs focus:border-brand-500 focus:ring-brand-500">
                                @foreach ($kitchenLabels as $value => $label)
                                    <option value="{{ $value }}" @selected($line->kitchen_status === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        @else
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $kitchenStyle[$line->kitchen_status] ?? '' }}">{{ $kitchenLabels[$line->kitchen_status] ?? $line->kitchen_status }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">{{ __('No items yet — add some below.') }}</td></tr>
            @endforelse
        </tbody>
        @if ($order->items->isNotEmpty())
            <tfoot>
                <tr class="border-t border-slate-100 text-slate-600">
                    <td colspan="3" class="px-4 py-2 text-end">{{ __('Subtotal') }}</td>
                    <td colspan="2" class="px-4 py-2 text-end tabular-nums">{{ number_format($subtotal, 2) }}</td>
                </tr>
                <tr class="text-slate-600">
                    <td colspan="3" class="px-4 py-2 text-end">{{ __('VAT') }}</td>
                    <td colspan="2" class="px-4 py-2 text-end tabular-nums">{{ number_format($vatTotal, 2) }}</td>
                </tr>
                <tr class="text-lg font-bold text-slate-900">
                    <td colspan="3" class="px-4 py-2 text-end">{{ __('Total') }}</td>
                    <td colspan="2" class="px-4 py-2 text-end tabular-nums">{{ number_format($total, 2) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>

@if ($order->isOpen())
<div x-data="restaurantOrderCart({
        itemLookupUrl: '{{ route('app.restaurant.orders.item-lookup') }}',
        itemsUrl: '{{ route('app.restaurant.orders.items.store', $order) }}',
    })" class="bg-white rounded-xl border border-slate-100 p-5 mb-6">
    <h3 class="text-sm font-semibold text-slate-900 mb-3">{{ __('Add items') }}</h3>
    <div class="relative">
        <input type="text" x-model="query" @input.debounce.250ms="search()" @keydown.enter.prevent="search(true)" placeholder="{{ __('Search menu items...') }}" class="w-full rounded-xl border border-slate-200 px-4 py-3 focus:border-brand-500 focus:ring-brand-500">
        <div x-show="results.length > 0" x-cloak @click.outside="results = []" class="absolute z-10 mt-1 w-full rounded-xl border border-slate-200 bg-white shadow-lg max-h-72 overflow-y-auto">
            <template x-for="result in results" :key="result.id">
                <button type="button" @click="addToCart(result)" class="flex w-full items-center justify-between px-4 py-2.5 text-start hover:bg-slate-50">
                    <span class="text-sm font-medium text-slate-800" x-text="result.name"></span>
                    <span class="text-sm text-slate-500 tabular-nums" x-text="result.unit_price.toFixed(2)"></span>
                </button>
            </template>
        </div>
    </div>

    <template x-if="cart.length > 0">
        <div class="mt-4 space-y-2">
            <template x-for="(line, index) in cart" :key="index">
                <div class="flex items-center gap-2">
                    <span class="flex-1 text-sm font-medium text-slate-800" x-text="line.name"></span>
                    <input type="number" min="0.001" step="0.001" x-model.number="line.quantity" class="w-20 rounded-lg border border-slate-200 text-sm text-center focus:border-brand-500 focus:ring-brand-500">
                    <input type="text" x-model="line.notes" placeholder="{{ __('Notes') }}" class="w-40 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <button type="button" @click="cart.splice(index, 1)" class="text-red-500 hover:text-red-700">✕</button>
                </div>
            </template>
            <template x-if="error"><p class="text-xs text-red-600" x-text="error"></p></template>
            <button type="button" @click="sendToKitchen()" :disabled="submitting" class="w-full rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50">
                <span x-show="!submitting">{{ __('Send to kitchen') }}</span>
                <span x-show="submitting">{{ __('Sending...') }}</span>
            </button>
        </div>
    </template>
</div>

<div x-data="restaurantCheckout({ checkoutUrl: '{{ route('app.restaurant.orders.checkout', $order) }}', lookupCardUrl: '{{ route('app.coffee-shop.loyalty-cards.lookup') }}', total: {{ $total }} })" class="bg-white rounded-xl border border-slate-100 p-5">
    <h3 class="text-sm font-semibold text-slate-900 mb-3">{{ __('Checkout') }}</h3>
    <div class="grid grid-cols-3 gap-2 mb-3">
        <button type="button" @click="setFullPayment('cash')" class="rounded-lg border border-slate-200 py-2 text-sm font-semibold text-slate-600 hover:border-brand-400 hover:text-brand-700">{{ __('Cash') }}</button>
        <button type="button" @click="setFullPayment('card')" class="rounded-lg border border-slate-200 py-2 text-sm font-semibold text-slate-600 hover:border-brand-400 hover:text-brand-700">{{ __('Card') }}</button>
        <button type="button" @click="payments.push({ method: 'other', amount: 0, reference: '' })" class="rounded-lg border border-slate-200 py-2 text-sm font-semibold text-slate-600 hover:border-brand-400 hover:text-brand-700">{{ __('+ Split') }}</button>
    </div>
    @if (app(\App\Services\Features\FeatureAccessService::class)->enabled(auth()->user()->company, 'coffee_shop'))
        <button type="button" @click="payments.push({ method: 'loyalty_card', amount: 0, card_number: '', loyalty_card_id: null, cardBalance: null })" class="w-full mb-3 rounded-lg border border-amber-200 bg-amber-50 py-2 text-sm font-semibold text-amber-700 hover:border-amber-300">{{ __('+ Pay with loyalty card') }}</button>
    @endif
    <template x-for="(payment, index) in payments" :key="index">
        <div class="mb-2">
            <div class="flex items-center gap-2">
                <select x-model="payment.method" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="cash">{{ __('Cash') }}</option>
                    <option value="card">{{ __('Card') }}</option>
                    <option value="other">{{ __('Other') }}</option>
                    <option value="loyalty_card">{{ __('Loyalty card') }}</option>
                </select>
                <input type="number" min="0" step="0.01" x-model.number="payment.amount" class="w-24 rounded-lg border border-slate-200 text-sm text-end focus:border-brand-500 focus:ring-brand-500">
                <button type="button" @click="payments.splice(index, 1)" class="text-red-500 hover:text-red-700">✕</button>
            </div>
            <template x-if="payment.method === 'loyalty_card'">
                <div class="mt-1.5 flex items-center gap-2">
                    <input type="text" x-model="payment.card_number" @input="payment.loyalty_card_id = null; payment.cardBalance = null" placeholder="{{ __('Card number') }}" class="flex-1 rounded-lg border border-slate-200 text-sm font-mono focus:border-brand-500 focus:ring-brand-500">
                    <button type="button" @click="lookupCard(payment)" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-300">{{ __('Check') }}</button>
                    <span x-show="payment.cardBalance !== null" class="text-xs text-emerald-600 whitespace-nowrap" x-text="@js(__('Balance')) + ': ' + (payment.cardBalance ?? 0).toFixed(2)"></span>
                </div>
            </template>
        </div>
    </template>
    <p class="text-xs mb-3" :class="paidTotal().toFixed(2) === total.toFixed(2) ? 'text-emerald-600' : 'text-amber-600'">
        {{ __('Paid') }}: <span x-text="paidTotal().toFixed(2)"></span> / <span x-text="total.toFixed(2)"></span>
    </p>
    <template x-if="error"><p class="text-xs text-red-600 mb-2" x-text="error"></p></template>
    <button type="button" @click="checkout()" :disabled="{{ $order->items->isEmpty() ? 'true' : 'false' }} || submitting" class="w-full rounded-lg bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50">
        <span x-show="!submitting">{{ __('Complete order') }}</span>
        <span x-show="submitting">{{ __('Processing...') }}</span>
    </button>
</div>

<dialog id="cancel-order-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-sm backdrop:bg-slate-900/40">
    <form method="POST" action="{{ route('app.restaurant.orders.cancel', $order) }}" class="p-6 space-y-4">
        @csrf
        <h3 class="text-lg font-bold text-slate-900">{{ __('Cancel order') }}</h3>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Reason') }}</label>
            <input type="text" name="cancel_reason" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div class="flex gap-3">
            <button type="button" onclick="document.getElementById('cancel-order-modal').close()" class="flex-1 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600">{{ __('Keep order') }}</button>
            <button type="submit" class="flex-1 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">{{ __('Cancel order') }}</button>
        </div>
    </form>
</dialog>
@endif

<script>
document.querySelectorAll('[data-item-status-select]').forEach(select => {
    select.addEventListener('change', async () => {
        const token = document.getElementById('csrf-token').value;
        await fetch(select.dataset.statusUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
            body: JSON.stringify({ kitchen_status: select.value }),
        });
    });
});

function restaurantOrderCart(config) {
    return {
        query: '', results: [], cart: [], error: null, submitting: false,

        async search(forceFirst = false) {
            if (this.query.trim().length < 1) { this.results = []; return; }
            const res = await fetch(config.itemLookupUrl + '?q=' + encodeURIComponent(this.query));
            const data = await res.json();
            if (forceFirst && data.length === 1) { this.addToCart(data[0]); return; }
            this.results = data;
        },

        addToCart(result) {
            this.cart.push({ item_id: result.id, name: result.name, quantity: 1, notes: '' });
            this.query = ''; this.results = [];
        },

        async sendToKitchen() {
            this.error = null;
            this.submitting = true;
            const token = document.getElementById('csrf-token').value;
            const res = await fetch(config.itemsUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ lines: this.cart }),
            });
            this.submitting = false;
            if (res.ok || res.redirected) { window.location.reload(); return; }
            try {
                const body = await res.json();
                this.error = body.errors ? Object.values(body.errors).flat().join(' ') : (body.message || @js(__('Something went wrong.')));
            } catch (e) {
                this.error = @js(__('Something went wrong.'));
            }
        },
    };
}

function restaurantCheckout(config) {
    return {
        total: config.total, payments: [], error: null, submitting: false,

        paidTotal() { return this.payments.reduce((sum, p) => sum + (p.amount || 0), 0); },

        setFullPayment(method) {
            this.payments = [{ method, amount: Math.round(this.total * 100) / 100, reference: '' }];
        },

        async lookupCard(payment) {
            payment.loyalty_card_id = null;
            payment.cardBalance = null;
            if (! payment.card_number) { return; }
            const res = await fetch(config.lookupCardUrl + '?q=' + encodeURIComponent(payment.card_number));
            const data = await res.json();
            if (data.found) {
                payment.loyalty_card_id = data.id;
                payment.cardBalance = data.balance;
            }
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
                body: JSON.stringify({ payments: this.payments }),
            });
            this.submitting = false;
            if (res.redirected) { window.location.href = res.url; return; }
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
