@extends('layouts.site')

@section('title', __('VAT calculator') . ' · Daftari')

@section('content')
@include('site.tools.partials.header', ['title' => __('VAT calculator'), 'description' => __('Add 15% VAT to an amount, or work out the VAT already included in a total.')])

<section class="mx-auto max-w-2xl px-6 pb-8">
    <div class="rounded-xl border border-slate-100 bg-white p-6">
        <div class="flex items-center gap-1 rounded-full border border-slate-200 p-1 w-fit mb-6" id="vat-mode">
            <button type="button" data-mode="add" class="mode-btn rounded-full px-4 py-1.5 text-sm font-semibold">{{ __('Add VAT') }}</button>
            <button type="button" data-mode="extract" class="mode-btn rounded-full px-4 py-1.5 text-sm font-semibold">{{ __('Extract VAT') }}</button>
        </div>

        <label class="block text-sm font-medium text-slate-700">{{ __('Amount (SAR)') }}</label>
        <input type="number" id="vat-amount" step="0.01" min="0" value="100" class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">

        <div class="mt-6 grid grid-cols-3 gap-4 text-center">
            <div class="rounded-lg bg-slate-50 p-4">
                <div class="text-xs text-slate-500" id="vat-label-base">{{ __('Amount excl. VAT') }}</div>
                <div class="mt-1 text-lg font-bold text-slate-900" id="vat-base">0.00</div>
            </div>
            <div class="rounded-lg bg-slate-50 p-4">
                <div class="text-xs text-slate-500">{{ __('VAT (15%)') }}</div>
                <div class="mt-1 text-lg font-bold text-slate-900" id="vat-amount-out">0.00</div>
            </div>
            <div class="rounded-lg bg-brand-50 p-4">
                <div class="text-xs text-brand-700" id="vat-label-total">{{ __('Amount incl. VAT') }}</div>
                <div class="mt-1 text-lg font-bold text-brand-700" id="vat-total">0.00</div>
            </div>
        </div>

        @include('site.tools.partials.disclaimer', ['text' => __('Uses the standard 15% Saudi VAT rate. Some goods/services may be zero-rated or exempt — verify your specific case.')])
    </div>
</section>

@include('site.tools.partials.cta')

<script>
(function () {
    const RATE = 0.15;
    const input = document.getElementById('vat-amount');
    const buttons = document.querySelectorAll('#vat-mode .mode-btn');
    let mode = 'add';

    function fmt(n) { return (Math.round(n * 100) / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

    function recalc() {
        const value = parseFloat(input.value) || 0;
        let base, vat, total;
        if (mode === 'add') {
            base = value; vat = base * RATE; total = base + vat;
        } else {
            total = value; base = total / (1 + RATE); vat = total - base;
        }
        document.getElementById('vat-base').textContent = fmt(base);
        document.getElementById('vat-amount-out').textContent = fmt(vat);
        document.getElementById('vat-total').textContent = fmt(total);
        document.getElementById('vat-label-base').textContent = mode === 'add' ? @json(__('Amount excl. VAT')) : @json(__('Amount excl. VAT (result)'));
        document.getElementById('vat-label-total').textContent = mode === 'add' ? @json(__('Amount incl. VAT (result)')) : @json(__('Amount incl. VAT'));
    }

    buttons.forEach(btn => btn.addEventListener('click', () => {
        mode = btn.dataset.mode;
        buttons.forEach(b => { b.classList.toggle('bg-brand-600', b === btn); b.classList.toggle('text-white', b === btn); b.classList.toggle('text-slate-600', b !== btn); });
        recalc();
    }));
    input.addEventListener('input', recalc);
    buttons[0].click();
})();
</script>
@endsection
