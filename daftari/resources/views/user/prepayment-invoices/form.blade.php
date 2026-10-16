@extends('layouts.app')

@section('title', __('New prepayment invoice'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ __('New prepayment invoice') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Record an advance or deposit collected before the main supply — VAT is due on it now, not deferred to the final invoice.') }}</p>
    </div>
    <a href="{{ route('app.prepayment-invoices.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</a>
</div>

@if ($errors->any())
    <div class="mb-6 rounded-lg bg-red-50 border border-red-100 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
@endif

<form method="POST" action="{{ route('app.prepayment-invoices.store') }}" class="space-y-6">
    @csrf

    <div class="bg-white rounded-xl border border-slate-100 p-6 grid sm:grid-cols-3 gap-5">
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Prepayment number') }}</label>
            <input type="text" value="{{ $nextNumberPreview }}" disabled class="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 text-slate-400">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Issue date') }}</label>
            <input type="date" name="issue_date" value="{{ old('issue_date', now()->toDateString()) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Type') }}</label>
            <select name="type" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="standard" {{ old('type', 'standard') === 'standard' ? 'selected' : '' }}>{{ __('Standard (B2B)') }}</option>
                <option value="simplified" {{ old('type') === 'simplified' ? 'selected' : '' }}>{{ __('Simplified (B2C)') }}</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Customer') }}</label>
            <select name="client_id" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('Select a customer') }}</option>
                @foreach ($clients as $client)
                    <option value="{{ $client->id }}" {{ (string) old('client_id') === (string) $client->id ? 'selected' : '' }}>{{ $client->display_name ?? $client->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Project (optional)') }}</label>
            <select name="project_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('None') }}</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}" {{ (string) old('project_id') === (string) $project->id ? 'selected' : '' }}>{{ $project->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Tax rate') }}</label>
            <select name="tax_rate_id" id="tax-rate-select" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                @foreach ($taxRates as $taxRate)
                    <option value="{{ $taxRate->id }}" data-rate="{{ $taxRate->rate }}" {{ (string) old('tax_rate_id') === (string) $taxRate->id ? 'selected' : '' }}>{{ $taxRate->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="sm:col-span-2">
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Description (optional)') }}</label>
            <input type="text" name="description" value="{{ old('description') }}" placeholder="{{ __('e.g. Advance against Contract 2026-014') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Advance amount (excl. VAT)') }}</label>
            <input type="number" step="0.01" min="0.01" name="amount" id="amount-input" value="{{ old('amount') }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div class="sm:col-span-3">
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Notes (optional)') }}</label>
            <textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('notes') }}</textarea>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <div class="flex justify-end">
            <div class="w-full max-w-xs space-y-2 text-sm">
                <div class="flex justify-between text-slate-500"><span>{{ __('Amount') }}</span><span id="summary-amount">{{ \App\Support\Money::format(0) }}</span></div>
                <div class="flex justify-between text-slate-500"><span>{{ __('VAT') }}</span><span id="summary-vat">{{ \App\Support\Money::format(0) }}</span></div>
                <div class="flex justify-between font-bold text-slate-900 text-base border-t border-slate-100 pt-2"><span>{{ __('Total') }}</span><span id="summary-total">{{ \App\Support\Money::format(0) }}</span></div>
            </div>
        </div>
    </div>

    <button type="submit" class="w-full rounded-lg bg-brand-800 px-6 py-3 font-semibold text-white hover:bg-brand-900">{{ __('Issue prepayment invoice') }}</button>
</form>

<script>
(function () {
    const CURRENCY_SYMBOL = '{{ \App\Support\Money::symbol() }}';
    const amountInput = document.getElementById('amount-input');
    const taxRateSelect = document.getElementById('tax-rate-select');

    function format(n) {
        return CURRENCY_SYMBOL + ' ' + (Math.round(n * 100) / 100).toFixed(2);
    }

    function recalc() {
        const amount = parseFloat(amountInput.value) || 0;
        const rate = parseFloat(taxRateSelect.selectedOptions[0]?.dataset.rate || 0);
        const vat = amount * (rate / 100);

        document.getElementById('summary-amount').textContent = format(amount);
        document.getElementById('summary-vat').textContent = format(vat);
        document.getElementById('summary-total').textContent = format(amount + vat);
    }

    amountInput.addEventListener('input', recalc);
    taxRateSelect.addEventListener('change', recalc);
    recalc();
})();
</script>
@endsection
