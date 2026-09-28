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
        </form>
        <a href="{{ route('app.project-cash-flow.pdf', array_merge(['project' => $project], request()->only('bank_account_id'))) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
    </div>
</div>

<div class="grid sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Total received') }}</p>
        <p class="text-xl font-bold text-emerald-600 mt-1">{{ \App\Support\Money::format($summary['received']) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Total paid') }}</p>
        <p class="text-xl font-bold text-red-600 mt-1">{{ \App\Support\Money::format($summary['paid']) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Total transferred out') }}</p>
        <p class="text-xl font-bold text-red-600 mt-1">{{ \App\Support\Money::format($summary['transferred']) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Net cash position') }}</p>
        <p class="text-xl font-bold {{ $summary['net'] >= 0 ? 'text-slate-900' : 'text-red-600' }} mt-1">{{ \App\Support\Money::format($summary['net']) }}</p>
    </div>
</div>

@include('user.project-cash-flow.partials.ledger-table')
@endsection
