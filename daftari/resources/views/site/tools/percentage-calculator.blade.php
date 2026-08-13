@extends('layouts.site')

@section('title', __('Percentage calculator') . ' · Daftari')

@section('content')
@include('site.tools.partials.header', ['title' => __('Percentage calculator'), 'description' => __('A percentage of an amount, an increase or decrease, or a profit margin.')])

<section class="mx-auto max-w-2xl px-6 pb-8">
    <div class="rounded-xl border border-slate-100 bg-white p-6">
        <div class="flex flex-wrap items-center gap-1 rounded-full border border-slate-200 p-1 w-fit mb-6" id="p-mode">
            <button type="button" data-mode="of" class="mode-btn rounded-full px-4 py-1.5 text-sm font-semibold">{{ __('X% of Y') }}</button>
            <button type="button" data-mode="change" class="mode-btn rounded-full px-4 py-1.5 text-sm font-semibold">{{ __('Increase / decrease') }}</button>
            <button type="button" data-mode="margin" class="mode-btn rounded-full px-4 py-1.5 text-sm font-semibold">{{ __('Profit margin') }}</button>
        </div>

        <div id="p-of" class="p-panel grid sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Percentage (%)') }}</label>
                <input type="number" id="p-of-x" step="0.01" value="15" class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Of amount') }}</label>
                <input type="number" id="p-of-y" step="0.01" value="1000" class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>

        <div id="p-change" class="p-panel hidden grid sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('From') }}</label>
                <input type="number" id="p-change-a" step="0.01" value="100" class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('To') }}</label>
                <input type="number" id="p-change-b" step="0.01" value="120" class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>

        <div id="p-margin" class="p-panel hidden grid sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Cost') }}</label>
                <input type="number" id="p-margin-cost" step="0.01" value="70" class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Selling price') }}</label>
                <input type="number" id="p-margin-price" step="0.01" value="100" class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>

        <div class="mt-6 rounded-lg bg-brand-50 p-4 text-center">
            <div class="text-xs text-brand-700" id="p-result-label">{{ __('Result') }}</div>
            <div class="mt-1 text-2xl font-bold text-brand-700" id="p-result">0</div>
        </div>

        @include('site.tools.partials.disclaimer')
    </div>
</section>

@include('site.tools.partials.cta')

<script>
(function () {
    const buttons = document.querySelectorAll('#p-mode .mode-btn');
    const panels = { of: document.getElementById('p-of'), change: document.getElementById('p-change'), margin: document.getElementById('p-margin') };
    let mode = 'of';

    function fmt(n) { return isFinite(n) ? (Math.round(n * 100) / 100).toLocaleString('en-US', { maximumFractionDigits: 2 }) : '—'; }

    function recalc() {
        let result = 0, label = @json(__('Result'));
        if (mode === 'of') {
            const x = parseFloat(document.getElementById('p-of-x').value) || 0;
            const y = parseFloat(document.getElementById('p-of-y').value) || 0;
            result = (x / 100) * y;
            label = @json(__('Result'));
        } else if (mode === 'change') {
            const a = parseFloat(document.getElementById('p-change-a').value) || 0;
            const b = parseFloat(document.getElementById('p-change-b').value) || 0;
            result = a !== 0 ? ((b - a) / a) * 100 : 0;
            label = result >= 0 ? @json(__('Increase')) : @json(__('Decrease'));
        } else {
            const cost = parseFloat(document.getElementById('p-margin-cost').value) || 0;
            const price = parseFloat(document.getElementById('p-margin-price').value) || 0;
            result = price !== 0 ? ((price - cost) / price) * 100 : 0;
            label = @json(__('Profit margin'));
        }
        document.getElementById('p-result-label').textContent = label;
        document.getElementById('p-result').textContent = fmt(result) + (mode === 'of' ? '' : '%');
    }

    buttons.forEach(btn => btn.addEventListener('click', () => {
        mode = btn.dataset.mode;
        buttons.forEach(b => { b.classList.toggle('bg-brand-600', b === btn); b.classList.toggle('text-white', b === btn); b.classList.toggle('text-slate-600', b !== btn); });
        Object.entries(panels).forEach(([key, el]) => el.classList.toggle('hidden', key !== mode));
        recalc();
    }));
    document.querySelectorAll('.p-panel input').forEach(el => el.addEventListener('input', recalc));
    buttons[0].click();
})();
</script>
@endsection
