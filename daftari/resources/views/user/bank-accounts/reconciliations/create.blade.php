@extends('layouts.app')

@section('title', __('Start reconciliation'))

@section('content')
<div class="max-w-lg">
    <h1 class="text-lg font-semibold text-slate-900 mb-1">{{ __('Start reconciliation') }}</h1>
    <p class="text-sm text-slate-500 mb-6">{{ __('Enter the statement date and ending balance from your bank statement for :account.', ['account' => $bankAccount->name]) }}</p>

    <form method="POST" action="{{ route('app.bank-reconciliations.store', $bankAccount) }}" class="bg-white rounded-xl border border-slate-100 p-6 space-y-4">
        @csrf
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Statement date') }}</label>
            <input type="date" name="statement_date" value="{{ old('statement_date', now()->toDateString()) }}" required class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Statement ending balance') }}</label>
            <input type="number" step="0.01" name="statement_ending_balance" value="{{ old('statement_ending_balance') }}" required class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div class="flex gap-3">
            <a href="{{ route('app.bank-reconciliations.index', $bankAccount) }}" class="flex-1 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300 text-center">{{ __('Cancel') }}</a>
            <button type="submit" class="flex-1 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Start') }}</button>
        </div>
    </form>
</div>
@endsection
