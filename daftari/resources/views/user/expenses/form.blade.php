@extends('layouts.app')

@section('title', $expense->exists ? __('Edit Expense') : __('New Expense'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-slate-900">{{ $expense->exists ? __('Edit Expense') : __('New Expense') }}</h1>
    <a href="{{ route('app.expenses.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Back') }}</a>
</div>

<form method="POST" action="{{ $expense->exists ? route('app.expenses.update', $expense) : route('app.expenses.store') }}" class="max-w-2xl bg-white rounded-xl border border-slate-100 p-6 space-y-5">
    @csrf
    @if ($expense->exists) @method('PUT') @endif

    <h2 class="text-lg font-semibold text-slate-900">{{ $expense->exists ? __('Edit Expense') : __('New Expense') }}</h2>

    <div>
        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Expense date') }}</label>
        <input type="date" name="expense_date" value="{{ old('expense_date', optional($expense->expense_date)->format('Y-m-d') ?? now()->toDateString()) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Category') }}</label>
            <select name="expense_category_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('None') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('expense_category_id', $expense->expense_category_id) == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Vendor name') }}</label>
            <input type="text" name="vendor_name" value="{{ old('vendor_name', $expense->vendor_name) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Financial account') }}</label>
            <select name="bank_account_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="" @selected(! old('bank_account_id', $expense->bank_account_id))>{{ __('Unpaid (record as payable)') }}</option>
                @foreach ($bankAccounts as $account)
                    <option value="{{ $account->id }}" @selected(old('bank_account_id', $expense->bank_account_id) == $account->id)>{{ $account->name }}</option>
                @endforeach
            </select>
            <p class="text-xs text-slate-400 mt-1">{{ __('Leave unpaid to settle later with a payment voucher.') }}</p>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Expense account') }}</label>
            <select name="account_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('Default operating expenses') }}</option>
                @foreach ($glAccounts as $account)
                    <option value="{{ $account->id }}" @selected(old('account_id', $expense->account_id) == $account->id)>{{ $account->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Gross amount (incl. VAT)') }}</label>
            <input type="number" step="0.01" min="0.01" id="gross_amount" name="gross_amount" value="{{ old('gross_amount', $expense->gross_amount ?? ($expense->exists ? $expense->amount + $expense->vat_amount : '')) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Tax category') }}</label>
            <select id="tax_category" name="tax_category" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="standard_15" data-rate="15" @selected(old('tax_category', $expense->tax_category ?? 'standard_15') === 'standard_15')>{{ __('Standard rated 15%') }}</option>
                <option value="zero_rated" data-rate="0" @selected(old('tax_category', $expense->tax_category) === 'zero_rated')>{{ __('Zero rated') }}</option>
                <option value="exempt" data-rate="0" @selected(old('tax_category', $expense->tax_category) === 'exempt')>{{ __('Exempt') }}</option>
            </select>
        </div>
    </div>

    <p class="text-xs text-slate-500">{{ __('Net amount') }}: <span id="net-preview" class="font-semibold text-slate-700">SAR 0.00</span> &middot; {{ __('VAT') }}: <span id="vat-preview" class="font-semibold text-slate-700">SAR 0.00</span></p>

    <div>
        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Reference') }}</label>
        <input type="text" name="reference" value="{{ old('reference', $expense->reference) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
    </div>

    <div>
        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Description') }}</label>
        <textarea name="description" rows="4" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('description', $expense->description) }}</textarea>
    </div>

    @if ($projects->isNotEmpty())
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Project (optional)') }}</label>
            <select name="project_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('None') }}</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}" @selected(old('project_id', $expense->project_id) == $project->id)>{{ $project->code }} - {{ $project->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <button type="submit" class="w-full rounded-lg bg-brand-800 px-6 py-3 font-semibold text-white hover:bg-brand-900">{{ __('Save') }}</button>
</form>

<script>
(function () {
    const gross = document.getElementById('gross_amount');
    const taxCategory = document.getElementById('tax_category');
    const netPreview = document.getElementById('net-preview');
    const vatPreview = document.getElementById('vat-preview');

    function recalc() {
        const g = parseFloat(gross.value) || 0;
        const rate = parseFloat(taxCategory.selectedOptions[0]?.dataset.rate || 0);
        const vat = Math.round((g * rate / (100 + rate)) * 100) / 100;
        const net = Math.round((g - vat) * 100) / 100;
        netPreview.textContent = 'SAR ' + net.toFixed(2);
        vatPreview.textContent = 'SAR ' + vat.toFixed(2);
    }

    gross.addEventListener('input', recalc);
    taxCategory.addEventListener('change', recalc);
    recalc();
})();
</script>
@endsection
