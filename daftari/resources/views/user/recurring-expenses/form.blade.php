@extends('layouts.app')

@section('title', $recurringExpense->exists ? __('Edit Recurring Expense') : __('New Recurring Expense'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ $recurringExpense->exists ? __('Edit recurring expense') : __('New recurring expense') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('An expense is recorded automatically each time this schedule runs.') }}</p>
    </div>
    <div class="flex items-center gap-3">
        <button type="submit" form="recurring-expense-form" class="rounded-lg bg-brand-700 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-800">{{ __('Save') }}</button>
        <a href="{{ route('app.recurring-expenses.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Back') }}</a>
    </div>
</div>

<form method="POST" action="{{ $recurringExpense->exists ? route('app.recurring-expenses.update', $recurringExpense) : route('app.recurring-expenses.store') }}" id="recurring-expense-form" class="max-w-3xl bg-white rounded-xl border border-slate-100 p-6 space-y-5">
    @csrf
    @if ($recurringExpense->exists) @method('PUT') @endif

    <div>
        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Title') }}</label>
        <input type="text" name="title" required value="{{ old('title', $recurringExpense->title) }}" placeholder="{{ __('e.g. Office rent, Software subscription') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Frequency') }}</label>
            <select name="frequency" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                @foreach (['weekly' => __('Weekly'), 'monthly' => __('Monthly'), 'quarterly' => __('Quarterly'), 'yearly' => __('Yearly')] as $value => $label)
                    <option value="{{ $value }}" @selected(old('frequency', $recurringExpense->frequency ?? 'monthly') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Start date') }}</label>
            <input type="date" name="start_date" value="{{ old('start_date', optional($recurringExpense->start_date)->format('Y-m-d')) }}" required @if($recurringExpense->exists) readonly @endif class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500 read-only:bg-slate-50 read-only:text-slate-400">
            @if ($recurringExpense->exists)
                <p class="text-xs text-slate-400 mt-1">{{ __('Next run date') }}: {{ optional($recurringExpense->next_run_date)->format('Y-m-d') }}</p>
            @endif
        </div>
    </div>

    <div>
        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('End date (optional)') }}</label>
        <input type="date" name="end_date" value="{{ old('end_date', optional($recurringExpense->end_date)->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        <p class="text-xs text-slate-400 mt-1">{{ __('Leave blank to keep generating indefinitely.') }}</p>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Category') }}</label>
            <select name="expense_category_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('None') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('expense_category_id', $recurringExpense->expense_category_id) == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Vendor name') }}</label>
            <input type="text" name="vendor_name" value="{{ old('vendor_name', $recurringExpense->vendor_name) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Financial account') }}</label>
            <select name="bank_account_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="" @selected(! old('bank_account_id', $recurringExpense->bank_account_id))>{{ __('Unpaid (record as payable)') }}</option>
                @foreach ($bankAccounts as $account)
                    <option value="{{ $account->id }}" @selected(old('bank_account_id', $recurringExpense->bank_account_id) == $account->id)>{{ $account->name }}</option>
                @endforeach
            </select>
            <p class="text-xs text-slate-400 mt-1">{{ __('Leave unpaid to settle later with a payment voucher.') }}</p>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Expense account') }}</label>
            <select name="account_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('Default operating expenses') }}</option>
                @foreach ($glAccounts as $account)
                    <option value="{{ $account->id }}" @selected(old('account_id', $recurringExpense->account_id) == $account->id)>{{ $account->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Gross amount (incl. VAT)') }}</label>
            <input type="number" step="0.01" min="0.01" id="gross_amount" name="gross_amount" value="{{ old('gross_amount', $recurringExpense->gross_amount) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Tax category') }}</label>
            <select id="tax_category" name="tax_category" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="standard_15" data-rate="15" @selected(old('tax_category', $recurringExpense->tax_category ?? 'standard_15') === 'standard_15')>{{ __('Standard rated 15%') }}</option>
                <option value="zero_rated" data-rate="0" @selected(old('tax_category', $recurringExpense->tax_category) === 'zero_rated')>{{ __('Zero rated') }}</option>
                <option value="exempt" data-rate="0" @selected(old('tax_category', $recurringExpense->tax_category) === 'exempt')>{{ __('Exempt') }}</option>
            </select>
        </div>
    </div>

    <p class="text-xs text-slate-500">{{ __('Net amount') }}: <span id="net-preview" class="font-semibold text-slate-700">SAR 0.00</span> &middot; {{ __('VAT') }}: <span id="vat-preview" class="font-semibold text-slate-700">SAR 0.00</span></p>

    <div>
        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Reference') }}</label>
        <input type="text" name="reference" value="{{ old('reference', $recurringExpense->reference) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
    </div>

    <div>
        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Description') }}</label>
        <textarea name="description" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('description', $recurringExpense->description) }}</textarea>
    </div>

    @if ($projects->isNotEmpty())
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Project (optional)') }}</label>
            <select name="project_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('None') }}</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}" @selected(old('project_id', $recurringExpense->project_id) == $project->id)>{{ $project->code }} - {{ $project->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div>
        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Notes') }}</label>
        <textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('notes', $recurringExpense->notes) }}</textarea>
    </div>
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
