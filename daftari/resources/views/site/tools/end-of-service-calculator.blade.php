@extends('layouts.site')

@section('title', __('End-of-service calculator') . ' · Daftari')

@section('content')
@include('site.tools.partials.header', ['title' => __('End-of-service award calculator'), 'description' => __('Estimate the end-of-service award by length of service, last wage, and reason for leaving.')])

<section class="mx-auto max-w-2xl px-6 pb-8">
    <div class="rounded-xl border border-slate-100 bg-white p-6">
        <div class="grid sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Last monthly wage (SAR)') }}</label>
                <input type="number" id="e-wage" step="0.01" min="0" value="10000" class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Years of service') }}</label>
                <input type="number" id="e-years" step="0.1" min="0" value="6" class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>

        <label class="block text-sm font-medium text-slate-700 mt-5">{{ __('Reason for leaving') }}</label>
        <div class="flex flex-wrap items-center gap-1 rounded-full border border-slate-200 p-1 w-fit mt-1" id="e-mode">
            <button type="button" data-mode="terminated" class="mode-btn rounded-full px-4 py-1.5 text-sm font-semibold">{{ __('Employer-terminated / contract end') }}</button>
            <button type="button" data-mode="resigned" class="mode-btn rounded-full px-4 py-1.5 text-sm font-semibold">{{ __('Resignation') }}</button>
        </div>

        <div class="mt-6 rounded-lg bg-brand-50 p-4 text-center">
            <div class="text-xs text-brand-700">{{ __('Estimated award') }}</div>
            <div class="mt-1 text-2xl font-bold text-brand-700" id="e-result">SAR 0.00</div>
        </div>

        @include('site.tools.partials.disclaimer', ['text' => __('Based on the standard Saudi Labor Law formula (half a month\'s wage per year for the first 5 years, a full month per year after that, tiered for resignation). It does not account for for-cause dismissal or contract-specific terms. Confirm with a licensed HR/legal advisor.')])
    </div>
</section>

@include('site.tools.partials.cta')

<script>
(function () {
    const buttons = document.querySelectorAll('#e-mode .mode-btn');
    let reason = 'terminated';
    function fmt(n) { return 'SAR ' + (Math.round(n * 100) / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

    function fullAward(wage, years) {
        if (years <= 5) return years * 0.5 * wage;
        return 5 * 0.5 * wage + (years - 5) * wage;
    }

    function recalc() {
        const wage = parseFloat(document.getElementById('e-wage').value) || 0;
        const years = parseFloat(document.getElementById('e-years').value) || 0;
        const full = fullAward(wage, years);
        let award = full;
        if (reason === 'resigned') {
            if (years < 2) award = 0;
            else if (years < 5) award = full / 3;
            else if (years < 10) award = full * 2 / 3;
            else award = full;
        }
        document.getElementById('e-result').textContent = fmt(award);
    }

    buttons.forEach(btn => btn.addEventListener('click', () => {
        reason = btn.dataset.mode;
        buttons.forEach(b => { b.classList.toggle('bg-brand-600', b === btn); b.classList.toggle('text-white', b === btn); b.classList.toggle('text-slate-600', b !== btn); });
        recalc();
    }));
    document.getElementById('e-wage').addEventListener('input', recalc);
    document.getElementById('e-years').addEventListener('input', recalc);
    buttons[0].click();
})();
</script>
@endsection
