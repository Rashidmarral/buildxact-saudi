@extends('layouts.site')

@section('title', __('Payment Voucher Template') . ' · Daftari')

@section('content')
<style>
    @media print {
        header, footer, .print-hide { display: none !important; }
        .print-area { box-shadow: none !important; border: none !important; }
    }
</style>

@include('site.tools.partials.header', ['title' => __('Payment Voucher Template'), 'description' => __('A ready cash or bank payment voucher — fill it in and print in a minute.')])

<section class="mx-auto max-w-6xl px-6 pb-16 grid lg:grid-cols-2 gap-8">
    <div class="print-hide space-y-6">
        <div class="rounded-xl border border-slate-100 bg-white p-6 space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-slate-500 mb-1">{{ __('Voucher no.') }}</label>
                    <input type="text" id="pv-number" value="PV-0001" class="w-full rounded-lg border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1">{{ __('Date') }}</label>
                    <input type="date" id="pv-date" class="w-full rounded-lg border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">{{ __('Paid to') }}</label>
                <input type="text" id="pv-to" class="w-full rounded-lg border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">{{ __('Amount (SAR)') }}</label>
                <input type="number" id="pv-amount" min="0" step="0.01" value="0" class="w-full rounded-lg border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">{{ __('Payment method') }}</label>
                <select id="pv-method" class="w-full rounded-lg border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="{{ __('Cash') }}">{{ __('Cash') }}</option>
                    <option value="{{ __('Bank transfer') }}">{{ __('Bank transfer') }}</option>
                    <option value="{{ __('Card') }}">{{ __('Card') }}</option>
                    <option value="{{ __('Cheque') }}">{{ __('Cheque') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">{{ __('For') }}</label>
                <input type="text" id="pv-reason" placeholder="{{ __('e.g. supplier bill #, expense, refund') }}" class="w-full rounded-lg border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>
        <button type="button" onclick="window.print()" class="w-full rounded-lg bg-brand-600 px-6 py-3 font-semibold text-white hover:bg-brand-700">{{ __('Print / Save as PDF') }}</button>
    </div>

    <div class="print-area rounded-xl border border-slate-100 bg-white p-8 h-fit">
        <div class="flex justify-between items-start border-b border-slate-100 pb-4">
            <h2 class="text-xl font-bold text-slate-900">{{ __('Payment Voucher') }}</h2>
            <div class="text-end text-xs text-slate-500">
                <div id="pv-p-number">{{ __('No.') }}: —</div>
                <div id="pv-p-date">{{ __('Date') }}: —</div>
            </div>
        </div>
        <div class="mt-6 space-y-4 text-sm">
            <div><span class="text-slate-400">{{ __('Paid to:') }}</span> <span id="pv-p-to" class="font-medium text-slate-800">—</span></div>
            <div><span class="text-slate-400">{{ __('Amount:') }}</span> <span id="pv-p-amount" class="font-bold text-slate-900">0.00 SAR</span></div>
            <div><span class="text-slate-400">{{ __('Payment method:') }}</span> <span id="pv-p-method" class="font-medium text-slate-800">—</span></div>
            <div><span class="text-slate-400">{{ __('For:') }}</span> <span id="pv-p-reason" class="font-medium text-slate-800">—</span></div>
        </div>
        <div class="mt-10 pt-6 border-t border-slate-100 grid grid-cols-2 text-xs text-slate-400 text-center">
            <div>{{ __('Paid by') }}</div>
            <div>{{ __('Authorized signature') }}</div>
        </div>
    </div>
</section>

@include('site.tools.partials.cta')

<script>
(function () {
    document.getElementById('pv-date').value = new Date().toISOString().slice(0, 10);
    function fmt(n) { return (Math.round(n * 100) / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function recalc() {
        document.getElementById('pv-p-number').textContent = @json(__('No.')) + ': ' + (document.getElementById('pv-number').value || '—');
        document.getElementById('pv-p-date').textContent = @json(__('Date')) + ': ' + (document.getElementById('pv-date').value || '—');
        document.getElementById('pv-p-to').textContent = document.getElementById('pv-to').value || '—';
        document.getElementById('pv-p-amount').textContent = fmt(parseFloat(document.getElementById('pv-amount').value) || 0) + ' SAR';
        document.getElementById('pv-p-method').textContent = document.getElementById('pv-method').value || '—';
        document.getElementById('pv-p-reason').textContent = document.getElementById('pv-reason').value || '—';
    }
    ['pv-number', 'pv-date', 'pv-to', 'pv-amount', 'pv-method', 'pv-reason'].forEach(id => {
        document.getElementById(id).addEventListener('input', recalc);
        document.getElementById(id).addEventListener('change', recalc);
    });
    recalc();
})();
</script>
@endsection
