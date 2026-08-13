@extends('layouts.app')

@section('title', $expense->exists ? __('Edit Expense') : __('New Expense'))

@section('content')
<form method="POST" action="{{ $expense->exists ? route('app.expenses.update', $expense) : route('app.expenses.store') }}" class="max-w-2xl bg-white rounded-xl border border-slate-100 p-6 space-y-5">
    @csrf
    @if ($expense->exists) @method('PUT') @endif

    <div class="grid sm:grid-cols-2 gap-5">
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Vendor') }}</label>
            <input type="text" name="vendor_name" value="{{ old('vendor_name', $expense->vendor_name) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Category') }}</label>
            <select name="expense_category_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="">—</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('expense_category_id', $expense->expense_category_id) == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Amount (SAR)') }}</label>
            <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount', $expense->amount) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('VAT amount (SAR)') }}</label>
            <input type="number" step="0.01" min="0" name="vat_amount" value="{{ old('vat_amount', $expense->vat_amount ?? 0) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Date') }}</label>
            <input type="date" name="expense_date" value="{{ old('expense_date', optional($expense->expense_date)->format('Y-m-d') ?? now()->toDateString()) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-slate-700">{{ __('Description') }}</label>
            <input type="text" name="description" value="{{ old('description', $expense->description) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
    </div>

    <div class="flex gap-3 pt-2">
        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        <a href="{{ route('app.expenses.index') }}" class="rounded-lg border border-slate-200 px-6 py-2.5 font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
