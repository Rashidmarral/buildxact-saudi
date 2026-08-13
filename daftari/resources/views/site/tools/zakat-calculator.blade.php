@extends('layouts.site')

@section('title', __('Zakat calculator') . ' · Daftari')

@section('content')
@include('site.tools.partials.header', ['title' => __('Zakat calculator'), 'description' => __('Estimate Zakat due on wealth or business assets that have reached Nisab and been held for a full year.')])

<section class="mx-auto max-w-2xl px-6 pb-8">
    <div class="rounded-xl border border-slate-100 bg-white p-6">
        <label class="block text-sm font-medium text-slate-700">{{ __('Zakatable amount (SAR)') }}</label>
        <p class="text-xs text-slate-400 mb-1">{{ __('Cash, business assets, and receivables, minus short-term liabilities.') }}</p>
        <input type="number" id="z-amount" step="0.01" min="0" value="100000" class="w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">

        <label class="block text-sm font-medium text-slate-700 mt-5">{{ __('Calendar used') }}</label>
        <div class="flex items-center gap-1 rounded-full border border-slate-200 p-1 w-fit mt-1" id="z-mode">
            <button type="button" data-mode="hijri" class="mode-btn rounded-full px-4 py-1.5 text-sm font-semibold">{{ __('Hijri (2.5%)') }}</button>
            <button type="button" data-mode="gregorian" class="mode-btn rounded-full px-4 py-1.5 text-sm font-semibold">{{ __('Gregorian (2.5775%)') }}</button>
        </div>

        <div class="mt-6 rounded-lg bg-brand-50 p-4 text-center">
            <div class="text-xs text-brand-700">{{ __('Estimated Zakat due') }}</div>
            <div class="mt-1 text-2xl font-bold text-brand-700" id="z-result">SAR 0.00</div>
        </div>

        @include('site.tools.partials.disclaimer', ['text' => __('Zakat calculation depends on your specific asset and liability mix and Nisab eligibility. This is a simplified estimate — confirm your obligation with ZATCA or a qualified Zakat advisor.')])
    </div>
</section>

@include('site.tools.partials.cta')

<script>
(function () {
    const buttons = document.querySelectorAll('#z-mode .mode-btn');
    let rate = 0.025;
    function fmt(n) { return 'SAR ' + (Math.round(n * 100) / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function recalc() {
        const amount = parseFloat(document.getElementById('z-amount').value) || 0;
        document.getElementById('z-result').textContent = fmt(amount * rate);
    }
    buttons.forEach(btn => btn.addEventListener('click', () => {
        rate = btn.dataset.mode === 'hijri' ? 0.025 : 0.025775;
        buttons.forEach(b => { b.classList.toggle('bg-brand-600', b === btn); b.classList.toggle('text-white', b === btn); b.classList.toggle('text-slate-600', b !== btn); });
        recalc();
    }));
    document.getElementById('z-amount').addEventListener('input', recalc);
    buttons[0].click();
})();
</script>
@endsection
