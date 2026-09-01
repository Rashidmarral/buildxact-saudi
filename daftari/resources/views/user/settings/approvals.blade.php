@extends('layouts.app')

@section('title', __('Approvals'))

@section('content')
<div class="max-w-2xl space-y-6">
    <div>
        <h1 class="text-lg font-semibold text-slate-900">{{ __('Approvals') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Require a designated approver to sign off on documents above a set amount, before they can be posted or sent. Leave a threshold empty to keep instant approval for that document type.') }}</p>
    </div>

    <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
        {{ __('Only users whose role includes the "Approve purchase orders & expenses" permission can approve or reject items above these thresholds — set that up under Members & Roles.') }}
    </div>

    <form method="POST" action="{{ route('app.settings.approvals.update') }}" class="bg-white rounded-xl border border-slate-100 p-6 space-y-4">
        @csrf
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Purchase order approval threshold (SAR)') }}</label>
            <input type="number" step="0.01" min="0" name="po_approval_threshold" value="{{ old('po_approval_threshold', $company->po_approval_threshold) }}" placeholder="{{ __('No threshold — instant approval') }}" class="w-full rounded-lg border border-slate-200 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Expense approval threshold (SAR)') }}</label>
            <input type="number" step="0.01" min="0" name="expense_approval_threshold" value="{{ old('expense_approval_threshold', $company->expense_approval_threshold) }}" placeholder="{{ __('No threshold — instant approval') }}" class="w-full rounded-lg border border-slate-200 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Invoice approval threshold (SAR)') }}</label>
            <input type="number" step="0.01" min="0" name="invoice_approval_threshold" value="{{ old('invoice_approval_threshold', $company->invoice_approval_threshold) }}" placeholder="{{ __('No threshold — instant send') }}" class="w-full rounded-lg border border-slate-200 text-sm">
            <p class="text-xs text-slate-400 mt-1">{{ __('An invoice at or above this total is parked pending approval instead of being sent and posted to the ledger.') }}</p>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Quotation approval threshold (SAR)') }}</label>
            <input type="number" step="0.01" min="0" name="quotation_approval_threshold" value="{{ old('quotation_approval_threshold', $company->quotation_approval_threshold) }}" placeholder="{{ __('No threshold — instant issue') }}" class="w-full rounded-lg border border-slate-200 text-sm">
        </div>
        <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
    </form>

    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Overdue invoice reminders') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Automatically email clients an escalating reminder — friendly at 7 days overdue, firmer at 14, a final notice at 30 — for as long as an invoice stays unpaid.') }}</p>
    </div>

    <form method="POST" action="{{ route('app.settings.approvals.dunning') }}" class="bg-white rounded-xl border border-slate-100 p-6">
        @csrf
        <label class="flex items-center gap-3">
            <input type="checkbox" name="invoice_dunning_enabled" value="1" @checked($company->invoice_dunning_enabled) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            <span class="text-sm font-medium text-slate-700">{{ __('Send automated overdue-invoice reminders') }}</span>
        </label>
        <button type="submit" class="mt-4 rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
    </form>

    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Accounting period lock') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Once a period is closed and reported, prevent any invoice, bill, expense, payment, or journal entry from being posted with a date on or before the lock date — across every module.') }}</p>
    </div>

    @if ($company->accounting_lock_date)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            {{ __('The books are currently locked through :date.', ['date' => $company->accounting_lock_date->format('Y-m-d')]) }}
        </div>
    @endif

    <form method="POST" action="{{ route('app.settings.approvals.lock-date') }}" class="bg-white rounded-xl border border-slate-100 p-6 space-y-4">
        @csrf
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Lock date') }}</label>
            <input type="date" name="accounting_lock_date" value="{{ old('accounting_lock_date', $company->accounting_lock_date?->toDateString()) }}" class="w-full rounded-lg border border-slate-200 text-sm">
            <p class="text-xs text-slate-400 mt-1">{{ __('Leave blank to keep the books fully open.') }}</p>
        </div>
        <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save lock date') }}</button>
    </form>
</div>
@endsection
