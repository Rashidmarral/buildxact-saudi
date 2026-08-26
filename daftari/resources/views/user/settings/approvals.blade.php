@extends('layouts.app')

@section('title', __('Approvals'))

@section('content')
<div class="max-w-2xl space-y-6">
    <div>
        <h1 class="text-lg font-semibold text-slate-900">{{ __('Approvals') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Require a designated approver to sign off on purchase orders or expenses above a set amount, before they can be posted. Leave a threshold empty to keep instant approval for that document type.') }}</p>
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
        <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
    </form>
</div>
@endsection
