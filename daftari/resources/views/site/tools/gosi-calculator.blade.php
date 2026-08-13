@extends('layouts.site')

@section('title', __('GOSI calculator') . ' · Daftari')

@section('content')
@include('site.tools.partials.header', ['title' => __('GOSI calculator'), 'description' => __('Estimate employee and employer social insurance contributions on a monthly wage.')])

<section class="mx-auto max-w-2xl px-6 pb-8">
    <div class="rounded-xl border border-slate-100 bg-white p-6">
        <label class="block text-sm font-medium text-slate-700">{{ __('Monthly wage — basic + housing (SAR)') }}</label>
        <input type="number" id="g-wage" step="0.01" min="0" value="8000" class="w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">

        <label class="block text-sm font-medium text-slate-700 mt-5">{{ __('Employee nationality') }}</label>
        <div class="flex items-center gap-1 rounded-full border border-slate-200 p-1 w-fit mt-1" id="g-mode">
            <button type="button" data-mode="saudi" class="mode-btn rounded-full px-4 py-1.5 text-sm font-semibold">{{ __('Saudi') }}</button>
            <button type="button" data-mode="non-saudi" class="mode-btn rounded-full px-4 py-1.5 text-sm font-semibold">{{ __('Non-Saudi') }}</button>
        </div>

        <div class="mt-6 grid grid-cols-2 gap-4 text-center">
            <div class="rounded-lg bg-slate-50 p-4">
                <div class="text-xs text-slate-500">{{ __('Employee contribution') }}</div>
                <div class="mt-1 text-lg font-bold text-slate-900" id="g-employee">0.00</div>
            </div>
            <div class="rounded-lg bg-brand-50 p-4">
                <div class="text-xs text-brand-700">{{ __('Employer contribution') }}</div>
                <div class="mt-1 text-lg font-bold text-brand-700" id="g-employer">0.00</div>
            </div>
        </div>

        @include('site.tools.partials.disclaimer', ['text' => __("Uses illustrative contribution percentages (annuities, unemployment insurance, and occupational hazards branches) and doesn't account for wage caps or category-specific hazard rates. Confirm exact figures with GOSI.")])
    </div>
</section>

@include('site.tools.partials.cta')

<script>
(function () {
    const buttons = document.querySelectorAll('#g-mode .mode-btn');
    let nationality = 'saudi';
    function fmt(n) { return (Math.round(n * 100) / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function recalc() {
        const wage = parseFloat(document.getElementById('g-wage').value) || 0;
        let employee, employer;
        if (nationality === 'saudi') {
            employee = wage * 0.0975;
            employer = wage * 0.0975 + wage * 0.02;
        } else {
            employee = 0;
            employer = wage * 0.02;
        }
        document.getElementById('g-employee').textContent = fmt(employee);
        document.getElementById('g-employer').textContent = fmt(employer);
    }
    buttons.forEach(btn => btn.addEventListener('click', () => {
        nationality = btn.dataset.mode;
        buttons.forEach(b => { b.classList.toggle('bg-brand-600', b === btn); b.classList.toggle('text-white', b === btn); b.classList.toggle('text-slate-600', b !== btn); });
        recalc();
    }));
    document.getElementById('g-wage').addEventListener('input', recalc);
    buttons[0].click();
})();
</script>
@endsection
