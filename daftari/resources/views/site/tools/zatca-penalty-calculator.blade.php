@extends('layouts.site')

@section('title', __('ZATCA penalty estimator') . ' · Daftari')

@section('content')
@include('site.tools.partials.header', ['title' => __('ZATCA e-invoicing penalty estimator'), 'description' => __('A rough illustration of the penalty ranges ZATCA has publicly announced for common e-invoicing violations.')])

<section class="mx-auto max-w-2xl px-6 pb-8">
    <div class="rounded-xl border border-slate-100 bg-white p-6">
        <label class="block text-sm font-medium text-slate-700">{{ __('Violation type') }}</label>
        <select id="pen-select" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500"></select>

        <div class="mt-6 rounded-lg bg-brand-50 p-4 text-center">
            <div class="text-xs text-brand-700">{{ __('Illustrative range') }}</div>
            <div class="mt-1 text-2xl font-bold text-brand-700" id="pen-range">—</div>
        </div>

        @include('site.tools.partials.disclaimer', ['text' => __('These ranges reflect the general brackets ZATCA has publicly communicated for e-invoicing non-compliance; first violations often receive a warning before a fine applies. ZATCA determines the exact amount case by case. This is not an official penalty calculation — check zatca.gov.sa for current, authoritative figures.')])
    </div>
</section>

@include('site.tools.partials.cta')

<script>
(function () {
    const violations = {!! json_encode([
        ['label' => __('Not issuing an e-invoice or a related notice'), 'range' => __('Warning (1st offense), then SAR 1,000–50,000')],
        ['label' => __('Missing QR code or required invoice fields'), 'range' => __('SAR 5,000–50,000')],
        ['label' => __('Not archiving e-invoices as required'), 'range' => __('SAR 10,000–50,000')],
        ['label' => __('Deleting or amending an invoice after issuance'), 'range' => __('SAR 20,000–50,000')],
        ['label' => __('Obstructing a ZATCA field inspection'), 'range' => __('SAR 40,000–50,000')],
    ]) !!};
    const select = document.getElementById('pen-select');
    violations.forEach((v, i) => {
        const opt = document.createElement('option');
        opt.value = i; opt.textContent = v.label;
        select.appendChild(opt);
    });
    function update() { document.getElementById('pen-range').textContent = violations[select.value].range; }
    select.addEventListener('change', update);
    update();
})();
</script>
@endsection
