@extends('layouts.app')

@section('title', __('Reconcile :account', ['account' => $bankAccount->name]))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-lg font-semibold text-slate-900">{{ __('Reconcile :account', ['account' => $bankAccount->name]) }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Match a bank statement against the receipts, payments, and transfers recorded for this account.') }}</p>
    </div>
    <a href="{{ route('app.bank-accounts.index') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Back to accounts') }}</a>
</div>

@php($inProgress = $reconciliations->firstWhere('status', 'in_progress'))

<div class="bg-white rounded-xl border border-slate-100 p-6 mb-6 flex items-center justify-between">
    @if ($inProgress)
        <div>
            <p class="text-sm font-medium text-slate-900">{{ __('A reconciliation is in progress') }}</p>
            <p class="text-xs text-slate-500 mt-1">{{ __('Statement date :date, ending balance :balance', ['date' => $inProgress->statement_date->format('Y-m-d'), 'balance' => \App\Support\Money::format($inProgress->statement_ending_balance)]) }}</p>
        </div>
        <a href="{{ route('app.bank-reconciliations.show', $inProgress) }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Continue') }}</a>
    @else
        <div>
            <p class="text-sm font-medium text-slate-900">{{ __('No reconciliation in progress') }}</p>
            <p class="text-xs text-slate-500 mt-1">{{ __('Start one whenever a new bank statement arrives.') }}</p>
        </div>
        <a href="{{ route('app.bank-reconciliations.create', $bankAccount) }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ Start reconciliation') }}</a>
    @endif
</div>

<div class="bg-white rounded-xl border border-slate-100">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-5 py-3 font-medium">{{ __('Statement date') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Ending balance') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('Status') }}</th>
                <th class="px-5 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($reconciliations as $r)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-5 py-3">{{ $r->statement_date->format('Y-m-d') }}</td>
                    <td class="px-5 py-3 text-end">{{ \App\Support\Money::format($r->statement_ending_balance) }}</td>
                    <td class="px-5 py-3">
                        @if ($r->status === 'completed')
                            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">{{ __('Completed') }}</span>
                        @else
                            <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">{{ __('In progress') }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-end">
                        <a href="{{ route('app.bank-reconciliations.show', $r) }}" class="text-brand-700 hover:underline">{{ __('View') }}</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-5 py-10 text-center text-slate-400">{{ __('No reconciliations yet.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
