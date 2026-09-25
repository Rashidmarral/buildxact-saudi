@extends('layouts.app')

@section('title', __('Job :number', ['number' => $job->job_number]))

@php
    // Mirrors PosSaleService::checkout() exactly (net-of-core-credit line,
    // VAT computed on that net line, each rounded per line before
    // summing) — this total is what the checkout panel below charges, so
    // it must match to the cent or the "pay in full" button under-fills
    // the payment and checkout is rejected.
    $subtotal = $job->items->sum(fn ($line) => $line->lineTotal());
    $vatTotal = $job->items->sum(fn ($line) => round($line->lineTotal() * ((float) $line->vat_rate / 100), 2));
    $total = round($subtotal + $vatTotal, 2);
    $statusBadge = ['received' => 'bg-slate-100 text-slate-600', 'diagnosing' => 'bg-sky-50 text-sky-700', 'awaiting_approval' => 'bg-amber-50 text-amber-700', 'in_repair' => 'bg-violet-50 text-violet-700', 'ready' => 'bg-emerald-50 text-emerald-700', 'collected' => 'bg-emerald-50 text-emerald-700', 'cancelled' => 'bg-red-50 text-red-700'];
    $statusOptions = ['received' => __('Received'), 'diagnosing' => __('Diagnosing'), 'awaiting_approval' => __('Awaiting approval'), 'in_repair' => __('In repair'), 'ready' => __('Ready for pickup')];
@endphp

@section('content')
<input type="hidden" id="csrf-token" value="{{ csrf_token() }}">

<div class="flex items-start justify-between mb-6">
    <div>
        <a href="{{ route('app.repair-jobs.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; {{ __('Repair Jobs') }}</a>
        <h1 class="text-2xl font-bold text-slate-900 mt-1">{{ $job->job_number }}</h1>
        <p class="text-sm text-slate-500 mt-1">
            {{ $job->item_description }}
            @if ($job->brand || $job->model) · {{ trim($job->brand.' '.$job->model) }} @endif
            @if ($job->year) ({{ $job->year }}) @endif
            @if ($job->client) · {{ $job->client->name }} @else · {{ __('Walk-in') }} @endif
        </p>
    </div>
    <div class="flex items-center gap-3">
        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $statusBadge[$job->status] ?? '' }}">{{ ucfirst(str_replace('_', ' ', $job->status)) }}</span>
        @if ($job->isOpen())
            <button type="button" onclick="document.getElementById('cancel-job-modal').showModal()" class="rounded-lg border border-red-200 bg-white px-3 py-1.5 text-xs font-semibold text-red-600 hover:border-red-300">{{ __('Cancel job') }}</button>
        @elseif ($job->sale)
            <a href="{{ route('app.pos.sales.show', $job->sale) }}" class="text-sm text-brand-700 hover:underline">{{ __('View receipt') }}</a>
        @endif
    </div>
</div>

@if ($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
@endif
@if (session('status'))
    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
@endif

<div class="grid lg:grid-cols-3 gap-6 mb-6">
    <div class="bg-white rounded-xl border border-slate-100 p-5 lg:col-span-2">
        <h3 class="text-sm font-semibold text-slate-900 mb-3">{{ __('Details') }}</h3>
        <dl class="grid sm:grid-cols-2 gap-3 text-sm mb-4">
            @if ($job->serial_or_plate)
                <div><dt class="text-slate-400">{{ __('Serial / plate') }}</dt><dd class="text-slate-700">{{ $job->serial_or_plate }}</dd></div>
            @endif
            @if ($job->client)
                <div><dt class="text-slate-400">{{ __('Client phone') }}</dt><dd class="text-slate-700">{{ $job->client->mobile ?: $job->client->phone ?: '—' }}</dd></div>
            @endif
        </dl>
        <p class="text-xs font-semibold uppercase text-slate-400 mb-1">{{ __('Reported issue') }}</p>
        <p class="text-sm text-slate-700 mb-4">{{ $job->issue_description ?: '—' }}</p>

        @if ($job->isOpen())
            <form method="POST" action="{{ route('app.repair-jobs.diagnosis', $job) }}" class="space-y-2">
                @csrf
                <label class="block text-xs font-semibold uppercase text-slate-400">{{ __('Diagnosis notes') }}</label>
                <textarea name="diagnosis_notes" rows="3" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">{{ $job->diagnosis_notes }}</textarea>
                <button type="submit" class="rounded-lg border border-slate-200 px-4 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-300">{{ __('Save diagnosis') }}</button>
            </form>
        @elseif ($job->diagnosis_notes)
            <p class="text-xs font-semibold uppercase text-slate-400 mb-1">{{ __('Diagnosis notes') }}</p>
            <p class="text-sm text-slate-700">{{ $job->diagnosis_notes }}</p>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-5 space-y-3">
        <h3 class="text-sm font-semibold text-slate-900">{{ __('Status') }}</h3>
        @if ($job->isOpen())
            <form method="POST" action="{{ route('app.repair-jobs.status', $job) }}" class="flex items-center gap-2">
                @csrf
                <select name="status" class="flex-1 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($job->status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-lg bg-slate-800 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-700">{{ __('Update') }}</button>
            </form>
            @if ($job->isApproved())
                <p class="text-xs text-emerald-600">{{ __('Estimate approved.') }}</p>
            @else
                <form method="POST" action="{{ route('app.repair-jobs.approve', $job) }}">
                    @csrf
                    <button type="submit" class="w-full rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-700 hover:border-amber-300">{{ __('Approve estimate') }}</button>
                </form>
                <p class="text-xs text-slate-400">{{ __('Required before moving to "In repair".') }}</p>
            @endif
        @endif

        @if ($job->client)
            <form method="POST" action="{{ route('app.repair-jobs.notify-sms', $job) }}">
                @csrf
                <button type="submit" class="w-full rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Notify customer (SMS)') }}</button>
            </form>
        @endif
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-100 overflow-hidden mb-6">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-4 py-3 font-medium">{{ __('Item') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Qty') }}</th>
                <th class="px-4 py-3 font-medium text-end">{{ __('Price') }}</th>
                <th class="px-4 py-3 font-medium text-end">{{ __('Core credit') }}</th>
                <th class="px-4 py-3 font-medium text-end">{{ __('Total') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($job->items as $line)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-4 py-2.5">
                        <p class="font-medium text-slate-800">{{ $line->description }}</p>
                        @if ($line->notes)<p class="text-xs text-slate-400">{{ $line->notes }}</p>@endif
                    </td>
                    <td class="px-4 py-2.5">{{ rtrim(rtrim(number_format($line->quantity, 3), '0'), '.') }}</td>
                    <td class="px-4 py-2.5 text-end tabular-nums">{{ number_format($line->unit_price, 2) }}</td>
                    <td class="px-4 py-2.5 text-end tabular-nums">{{ $line->core_exchange_credit > 0 ? '-'.number_format($line->core_exchange_credit, 2) : '—' }}</td>
                    <td class="px-4 py-2.5 text-end font-semibold tabular-nums">{{ number_format($line->lineTotal(), 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">{{ __('No parts/labor lines yet — add some below.') }}</td></tr>
            @endforelse
        </tbody>
        @if ($job->items->isNotEmpty())
            <tfoot>
                <tr class="border-t border-slate-100 text-slate-600">
                    <td colspan="4" class="px-4 py-2 text-end">{{ __('Subtotal') }}</td>
                    <td class="px-4 py-2 text-end tabular-nums">{{ number_format($subtotal, 2) }}</td>
                </tr>
                <tr class="text-slate-600">
                    <td colspan="4" class="px-4 py-2 text-end">{{ __('VAT') }}</td>
                    <td class="px-4 py-2 text-end tabular-nums">{{ number_format($vatTotal, 2) }}</td>
                </tr>
                <tr class="text-lg font-bold text-slate-900">
                    <td colspan="4" class="px-4 py-2 text-end">{{ __('Total') }}</td>
                    <td class="px-4 py-2 text-end tabular-nums">{{ number_format($total, 2) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>

@if ($job->isOpen())
<div x-data="repairJobCart({
        itemLookupUrl: '{{ route('app.repair-jobs.item-lookup') }}',
        itemsUrl: '{{ route('app.repair-jobs.items.store', $job) }}',
    })" class="bg-white rounded-xl border border-slate-100 p-5 mb-6">
    <h3 class="text-sm font-semibold text-slate-900 mb-3">{{ __('Add parts / labor') }}</h3>
    <div class="relative">
        <input type="text" x-model="query" @input.debounce.250ms="search()" @keydown.enter.prevent="search(true)" placeholder="{{ __('Search parts or services...') }}" class="w-full rounded-xl border border-slate-200 px-4 py-3 focus:border-brand-500 focus:ring-brand-500">
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
            <div class="flex items-center gap-2 text-[11px] font-medium uppercase tracking-wide text-slate-400">
                <span class="flex-1"></span>
                <span class="w-20 text-center">{{ __('Quantity') }}</span>
                <span class="w-28">{{ __('Core credit') }}</span>
                <span class="w-40">{{ __('Notes') }}</span>
                <span class="w-4"></span>
            </div>
            <template x-for="(line, index) in cart" :key="index">
                <div class="flex items-center gap-2">
                    <span class="flex-1 text-sm font-medium text-slate-800" x-text="line.name"></span>
                    <input type="number" min="0.001" step="0.001" x-model.number="line.quantity" class="w-20 rounded-lg border border-slate-200 text-sm text-center focus:border-brand-500 focus:ring-brand-500" title="{{ __('Quantity') }}">
                    <input type="number" min="0" step="0.01" x-model.number="line.core_exchange_credit" placeholder="{{ __('Core credit') }}" title="{{ __('Core credit') }}" class="w-28 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <input type="text" x-model="line.notes" placeholder="{{ __('Notes') }}" class="w-40 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <button type="button" @click="cart.splice(index, 1)" class="text-red-500 hover:text-red-700">✕</button>
                </div>
            </template>
            <template x-if="error"><p class="text-xs text-red-600" x-text="error"></p></template>
            <button type="button" @click="addLines()" :disabled="submitting" class="w-full rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50">
                <span x-show="!submitting">{{ __('Add to job') }}</span>
                <span x-show="submitting">{{ __('Adding...') }}</span>
            </button>
        </div>
    </template>
</div>

<div x-data="repairJobCheckout({ checkoutUrl: '{{ route('app.repair-jobs.checkout', $job) }}', total: {{ $total }} })" class="bg-white rounded-xl border border-slate-100 p-5">
    <h3 class="text-sm font-semibold text-slate-900 mb-3">{{ __('Checkout') }}</h3>
    <div class="grid grid-cols-3 gap-2 mb-3">
        <button type="button" @click="setFullPayment('cash')" class="rounded-lg border border-slate-200 py-2 text-sm font-semibold text-slate-600 hover:border-brand-400 hover:text-brand-700">{{ __('Cash') }}</button>
        <button type="button" @click="setFullPayment('card')" class="rounded-lg border border-slate-200 py-2 text-sm font-semibold text-slate-600 hover:border-brand-400 hover:text-brand-700">{{ __('Card') }}</button>
        <button type="button" @click="payments.push({ method: 'other', amount: 0, reference: '' })" class="rounded-lg border border-slate-200 py-2 text-sm font-semibold text-slate-600 hover:border-brand-400 hover:text-brand-700">{{ __('+ Split') }}</button>
    </div>
    <template x-for="(payment, index) in payments" :key="index">
        <div class="flex items-center gap-2 mb-2">
            <select x-model="payment.method" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="cash">{{ __('Cash') }}</option>
                <option value="card">{{ __('Card') }}</option>
                <option value="other">{{ __('Other') }}</option>
            </select>
            <input type="number" min="0" step="0.01" x-model.number="payment.amount" class="w-24 rounded-lg border border-slate-200 text-sm text-end focus:border-brand-500 focus:ring-brand-500">
            <button type="button" @click="payments.splice(index, 1)" class="text-red-500 hover:text-red-700">✕</button>
        </div>
    </template>
    <p class="text-xs mb-3" :class="paidTotal().toFixed(2) === total.toFixed(2) ? 'text-emerald-600' : 'text-amber-600'">
        {{ __('Paid') }}: <span x-text="paidTotal().toFixed(2)"></span> / <span x-text="total.toFixed(2)"></span>
    </p>
    <template x-if="error"><p class="text-xs text-red-600 mb-2" x-text="error"></p></template>
    <button type="button" @click="checkout()" :disabled="{{ $job->items->isEmpty() ? 'true' : 'false' }} || submitting" class="w-full rounded-lg bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50">
        <span x-show="!submitting">{{ __('Complete job & checkout') }}</span>
        <span x-show="submitting">{{ __('Processing...') }}</span>
    </button>
</div>

<dialog id="cancel-job-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-sm backdrop:bg-slate-900/40">
    <form method="POST" action="{{ route('app.repair-jobs.cancel', $job) }}" class="p-6 space-y-4">
        @csrf
        <h3 class="text-lg font-bold text-slate-900">{{ __('Cancel job') }}</h3>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Reason') }}</label>
            <input type="text" name="cancel_reason" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div class="flex gap-3">
            <button type="button" onclick="document.getElementById('cancel-job-modal').close()" class="flex-1 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600">{{ __('Keep job') }}</button>
            <button type="submit" class="flex-1 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">{{ __('Cancel job') }}</button>
        </div>
    </form>
</dialog>
@endif

<script>
function repairJobCart(config) {
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
            this.cart.push({ item_id: result.id, name: result.name, quantity: 1, core_exchange_credit: 0, notes: '' });
            this.query = ''; this.results = [];
        },

        async addLines() {
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

function repairJobCheckout(config) {
    return {
        total: config.total, payments: [], error: null, submitting: false,

        paidTotal() { return this.payments.reduce((sum, p) => sum + (p.amount || 0), 0); },

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
