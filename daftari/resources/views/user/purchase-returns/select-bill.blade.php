@extends('layouts.app')

@section('title', __('Create Purchase Return'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('Create Purchase Return') }}</h1>
    <a href="{{ route('app.purchase-returns.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</a>
</div>

@if ($errors->any())
    <div class="mb-6 rounded-lg bg-red-50 border border-red-100 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
@endif

<div class="bg-white rounded-xl border border-slate-100 p-6">
    <h2 class="text-lg font-bold text-slate-900">{{ __('Select a bill') }}</h2>
    <p class="text-sm text-slate-500 mt-1 mb-4">{{ __('Choose an eligible posted bill first, and we will load its details into the return form.') }}</p>

    <div class="relative">
        <input type="text" id="bill-search" placeholder="{{ __('Search by bill number or supplier') }}" class="w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500" autocomplete="off">
        <div id="bill-results" class="hidden absolute z-10 mt-1 w-full bg-white rounded-lg border border-slate-200 shadow-lg max-h-80 overflow-y-auto"></div>
    </div>
</div>

<script>
(function () {
    const input = document.getElementById('bill-search');
    const results = document.getElementById('bill-results');
    let timer = null;

    function render(bills) {
        if (! bills.length) {
            results.innerHTML = `<p class="px-4 py-3 text-sm text-slate-400">${@json(__('No eligible bills found.'))}</p>`;
            results.classList.remove('hidden');
            return;
        }

        results.innerHTML = bills.map(b => `
            <a href="{{ route('app.purchase-returns.create') }}?bill_id=${b.id}" class="block px-4 py-3 border-b border-slate-50 last:border-0 hover:bg-slate-50">
                <div class="flex items-center justify-between">
                    <span class="font-medium text-slate-800">${b.bill_number}</span>
                    <span class="text-slate-500">${b.supplier_name}</span>
                </div>
                <div class="flex items-center justify-between text-xs text-slate-400 mt-1">
                    <span>${b.bill_date}</span>
                    <span>${@json(__('Returnable'))}: SAR ${b.returnable}</span>
                </div>
            </a>
        `).join('');
        results.classList.remove('hidden');
    }

    function search(q) {
        fetch(`{{ route('app.purchase-returns.eligible-bills') }}?q=${encodeURIComponent(q)}`)
            .then(r => r.json())
            .then(render);
    }

    input.addEventListener('focus', () => search(input.value));
    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => search(input.value), 250);
    });
    document.addEventListener('click', (e) => {
        if (! e.target.closest('#bill-search') && ! e.target.closest('#bill-results')) {
            results.classList.add('hidden');
        }
    });
})();
</script>
@endsection
