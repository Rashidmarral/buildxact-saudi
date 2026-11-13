@extends('layouts.app')

@section('title', $project->name)

@section('content')
<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    <div>
        <a href="{{ route('app.project-cash-flow.index') }}" class="text-sm text-slate-400 hover:text-slate-600">{{ __('← Project Cash Flow') }}</a>
        <h2 class="text-lg font-semibold text-slate-900 mt-1">{{ $project->name }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Actual cash received, paid, and transferred for this project — not invoiced/billed amounts, but money that has actually moved.') }}</p>
    </div>
    <div class="flex items-center gap-2">
        <form method="GET" class="flex items-center gap-2">
            <select name="bank_account_id" onchange="this.form.submit()" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('All accounts') }}</option>
                @foreach ($bankAccounts as $account)
                    <option value="{{ $account->id }}" @selected($selectedBankAccountId == $account->id)>{{ $account->name }}</option>
                @endforeach
            </select>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500" title="{{ __('From') }}">
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500" title="{{ __('To') }}">
            <button type="submit" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Apply') }}</button>
        </form>
        <a href="{{ route('app.incomes.create', ['project_id' => $project->id]) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('+ Record income') }}</a>
        <a href="{{ route('app.project-cash-flow.pdf', array_merge(['project' => $project], request()->only('bank_account_id', 'from', 'to'))) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
    </div>
</div>

<div class="grid sm:grid-cols-5 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Total received') }}</p>
        <p class="text-xl font-bold text-emerald-600 mt-1">{{ \App\Support\Money::format($summary['received']) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Payment vouchers') }}</p>
        <p class="text-xl font-bold text-red-600 mt-1">{{ \App\Support\Money::format($summary['paid_vouchers']) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Expenses') }}</p>
        <p class="text-xl font-bold text-rose-600 mt-1">{{ \App\Support\Money::format($summary['expenses']) }}</p>
        <p class="text-xs text-slate-400 mt-1">{{ __('Directly-paid expenses, separate from payment vouchers') }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Moved to other accounts') }}</p>
        <p class="text-xl font-bold text-slate-600 mt-1">{{ \App\Support\Money::format($summary['transferred']) }}</p>
        <p class="text-xs text-slate-400 mt-1">{{ __('Not yet spent — e.g. a bank withdrawal into petty cash') }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Net cash position') }}</p>
        <p class="text-xl font-bold {{ $summary['net'] >= 0 ? 'text-slate-900' : 'text-red-600' }} mt-1">{{ \App\Support\Money::format($summary['net']) }}</p>
    </div>
</div>

@if ($summary['expense_by_category']->isNotEmpty())
<div class="bg-white rounded-xl border border-slate-100 p-5 mb-6">
    <p class="text-sm font-semibold text-slate-900 mb-3">{{ __('Expenses by category') }}</p>
    <div class="grid sm:grid-cols-3 gap-3">
        @foreach ($summary['expense_by_category'] as $row)
            <div class="rounded-lg border border-slate-100 px-4 py-3">
                <p class="text-xs text-slate-400">{{ $row['category'] }} · {{ trans_choice(':count expense|:count expenses', $row['count'], ['count' => $row['count']]) }}</p>
                <p class="text-sm font-bold text-rose-600 mt-1">{{ \App\Support\Money::format($row['total']) }}</p>
            </div>
        @endforeach
    </div>
</div>
@endif

@include('user.project-cash-flow.partials.ledger-table')
@endsection
