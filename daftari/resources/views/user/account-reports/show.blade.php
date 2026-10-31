@extends('layouts.app')

@section('title', $account->name)

@section('content')
<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    <div>
        <a href="{{ route('app.account-reports.index') }}" class="text-sm text-slate-400 hover:text-slate-600">{{ __('← Account Reports') }}</a>
        <div class="flex items-center gap-2 mt-1">
            <h2 class="text-lg font-semibold text-slate-900">{{ $account->name }}</h2>
            <span class="inline-block rounded-full bg-slate-100 text-slate-500 text-xs font-medium px-2.5 py-1">{{ $account->type === 'cash' ? __('Cash') : __('Bank') }}</span>
            @if ($account->is_personal)
                <span class="inline-block rounded-full bg-amber-50 text-amber-700 text-xs font-medium px-2.5 py-1">{{ __('Custody') }}@if ($account->personal_owner_name) — {{ $account->personal_owner_name }}@endif</span>
            @endif
        </div>
        <p class="text-sm text-slate-500 mt-1">{{ __('A chronological statement of every receipt, payment, and transfer through this account, with a running balance.') }}</p>
    </div>
    <div class="flex items-center gap-2">
        <form method="GET" class="flex items-center gap-2">
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500" title="{{ __('From') }}">
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500" title="{{ __('To') }}">
            <button type="submit" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Apply') }}</button>
        </form>
        <a href="{{ route('app.account-reports.pdf', array_merge(['bankAccount' => $account], request()->only('from', 'to'))) }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Download PDF') }}</a>
    </div>
</div>

<div class="rounded-2xl bg-gradient-to-br from-slate-900 to-slate-700 px-6 py-6 mb-6 text-white">
    <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Closing balance') }}</p>
    <p class="text-3xl font-bold mt-1 tabular-nums">{{ \App\Support\Money::format($ledger['closing_balance']) }}</p>
    <p class="text-xs text-slate-400 mt-2">{{ __('Opening balance') }}: {{ \App\Support\Money::format($ledger['opening_balance']) }}</p>
</div>

<div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Total received') }}</p>
        <p class="text-xl font-bold text-emerald-600 mt-1 tabular-nums">{{ \App\Support\Money::format($summary['received']) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Payment vouchers') }}</p>
        <p class="text-xl font-bold text-red-600 mt-1 tabular-nums">{{ \App\Support\Money::format($summary['paid_vouchers']) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Expenses') }}</p>
        <p class="text-xl font-bold text-rose-600 mt-1 tabular-nums">{{ \App\Support\Money::format($summary['expenses']) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Moved to other accounts') }}</p>
        <p class="text-xl font-bold text-slate-600 mt-1 tabular-nums">{{ \App\Support\Money::format($summary['transferred']) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Net cash position') }}</p>
        <p class="text-xl font-bold {{ $summary['net'] >= 0 ? 'text-slate-900' : 'text-red-600' }} mt-1 tabular-nums">{{ \App\Support\Money::format($summary['net']) }}</p>
    </div>
</div>

@if ($summary['expense_by_category']->isNotEmpty())
<div class="bg-white rounded-xl border border-slate-100 p-5 mb-6">
    <p class="text-sm font-semibold text-slate-900 mb-3">{{ __('Expenses by category') }}</p>
    <div class="grid sm:grid-cols-3 gap-3">
        @foreach ($summary['expense_by_category'] as $row)
            <div class="rounded-lg border border-slate-100 px-4 py-3">
                <p class="text-xs text-slate-400">{{ $row['category'] }} · {{ trans_choice(':count expense|:count expenses', $row['count'], ['count' => $row['count']]) }}</p>
                <p class="text-sm font-bold text-rose-600 mt-1 tabular-nums">{{ \App\Support\Money::format($row['total']) }}</p>
            </div>
        @endforeach
    </div>
</div>
@endif

@include('user.project-cash-flow.partials.ledger-table')
@endsection
