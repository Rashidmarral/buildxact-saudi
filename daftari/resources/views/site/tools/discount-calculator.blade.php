@extends('layouts.site')

@section('title', __('Discount calculator') . ' · Daftari')

@section('content')
@include('site.tools.partials.header', ['title' => __('Discount calculator'), 'description' => __('Work out the price after a discount, and how much a customer saves.')])

<section class="mx-auto max-w-2xl px-6 pb-8">
    <div class="rounded-xl border border-slate-100 bg-white p-6">
        <div class="grid sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Original price (SAR)') }}</label>
                <input type="number" id="d-price" step="0.01" min="0" value="100" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Discount (%)') }}</label>
                <input type="number" id="d-rate" step="0.01" min="0" max="100" value="20" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>

        <div class="mt-6 grid grid-cols-2 gap-4 text-center">
            <div class="rounded-lg bg-slate-50 p-4">
                <div class="text-xs text-slate-500">{{ __('You save') }}</div>
                <div class="mt-1 text-lg font-bold text-slate-900" id="d-savings">0.00</div>
            </div>
            <div class="rounded-lg bg-brand-50 p-4">
                <div class="text-xs text-brand-700">{{ __('Final price') }}</div>
                <div class="mt-1 text-lg font-bold text-brand-700" id="d-final">0.00</div>
            </div>
        </div>

        @include('site.tools.partials.disclaimer')
    </div>
</section>

@include('site.tools.partials.cta')

<script>
(function () {
    function fmt(n) { return (Math.round(n * 100) / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function recalc() {
        const price = parseFloat(document.getElementById('d-price').value) || 0;
        const rate = parseFloat(document.getElementById('d-rate').value) || 0;
        const savings = price * (rate / 100);
        document.getElementById('d-savings').textContent = fmt(savings);
        document.getElementById('d-final').textContent = fmt(price - savings);
    }
    document.getElementById('d-price').addEventListener('input', recalc);
    document.getElementById('d-rate').addEventListener('input', recalc);
    recalc();
})();
</script>
@endsection
