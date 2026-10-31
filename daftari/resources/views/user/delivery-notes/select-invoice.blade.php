@extends('layouts.app')

@section('title', __('Create Delivery Note'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('Create Delivery Note') }}</h1>
    <div class="flex items-center gap-3">
        <a href="{{ route('app.delivery-notes.create') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Create without an invoice') }}</a>
        <a href="{{ route('app.delivery-notes.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</a>
    </div>
</div>

@if ($errors->any())
    <div class="mb-6 rounded-lg bg-red-50 border border-red-100 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
@endif

<div class="bg-white rounded-xl border border-slate-100 p-6">
    <h2 class="text-lg font-bold text-slate-900">{{ __('Select an invoice') }}</h2>
    <p class="text-sm text-slate-500 mt-1 mb-4">{{ __('Choose an eligible invoice first, and we will load its remaining-to-deliver lines into the form. Already-delivered quantities are excluded, so you can ship an order across more than one delivery note.') }}</p>

    <div class="relative">
        <input type="text" id="invoice-search" placeholder="{{ __('Search by invoice number or customer') }}" class="w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500" autocomplete="off">
        <div id="invoice-results" class="hidden absolute z-10 mt-1 w-full bg-white rounded-lg border border-slate-200 shadow-lg max-h-80 overflow-y-auto"></div>
    </div>
</div>

<script>
(function () {
    const input = document.getElementById('invoice-search');
    const results = document.getElementById('invoice-results');
    let timer = null;

    function render(invoices) {
        if (! invoices.length) {
            results.innerHTML = `<p class="px-4 py-3 text-sm text-slate-400">${@json(__('No eligible invoices found.'))}</p>`;
            results.classList.remove('hidden');
            return;
        }

        results.innerHTML = invoices.map(inv => `
            <a href="{{ route('app.delivery-notes.create') }}?invoice_id=${inv.id}" class="block px-4 py-3 border-b border-slate-50 last:border-0 hover:bg-slate-50">
                <div class="flex items-center justify-between">
                    <span class="font-medium text-slate-800">${inv.invoice_number}</span>
                    <span class="text-slate-500">${inv.client_name}</span>
                </div>
                <div class="text-xs text-slate-400 mt-1">${inv.issue_date}</div>
            </a>
        `).join('');
        results.classList.remove('hidden');
    }

    function search(q) {
        fetch(`{{ route('app.delivery-notes.eligible-invoices') }}?q=${encodeURIComponent(q)}`)
            .then(r => r.json())
            .then(render);
    }

    input.addEventListener('focus', () => search(input.value));
    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => search(input.value), 250);
    });
    document.addEventListener('click', (e) => {
        if (! e.target.closest('#invoice-search') && ! e.target.closest('#invoice-results')) {
            results.classList.add('hidden');
        }
    });
})();
</script>
@endsection
