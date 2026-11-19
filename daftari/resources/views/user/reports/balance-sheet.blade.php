@extends('layouts.app')

@section('title', __('Balance Sheet'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Balance Sheet') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Every account with its ledger code under Assets, Capital and Liabilities.') }}</p>
    </div>
    <div class="flex items-center gap-2">
        @if ($balanced)
            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-1.5 text-xs font-semibold">✓ {{ __('Balance check') }}</span>
        @else
            <span class="inline-flex items-center gap-1 rounded-full bg-red-50 text-red-700 border border-red-200 px-3 py-1.5 text-xs font-semibold">✕ {{ __('Balance check') }}</span>
        @endif
        <a href="{{ route('app.reports.balance-sheet', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
        <a href="{{ route('app.reports.balance-sheet', array_merge(request()->query(), ['export' => 'excel'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download Excel') }}</a>
        <a href="{{ route('app.reports.balance-sheet', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
    </div>
</div>

<div class="mb-4 flex items-center gap-4 text-sm">
    <a href="{{ route('app.reports.balance-sheet-detailed', request()->except('export')) }}" class="text-brand-600 font-medium hover:underline">{{ __('Detailed (two-sided) view') }}</a>
    <a href="{{ route('app.reports.balance-sheet-summary', request()->except('export')) }}" class="text-brand-600 font-medium hover:underline">{{ __('Two-sided (summary) view') }}</a>
</div>

@php($compare = $compare ?? 'none')
<form method="GET" class="bg-white rounded-xl border border-slate-100 p-4 mb-6 flex items-center gap-3">
    <label class="text-xs font-semibold uppercase text-slate-500">{{ __('As of') }}</label>
    <input type="date" name="as_of" value="{{ $asOf->toDateString() }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
    <button type="submit" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-300">{{ __('Apply') }}</button>
    <span class="flex items-center gap-2 ms-auto">
        <label class="text-xs text-slate-400">{{ __('Compare with') }}</label>
        <select name="compare" onchange="this.form.submit()" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <option value="none" @selected($compare === 'none')>{{ __('Nothing') }}</option>
            <option value="prior_period" @selected($compare === 'prior_period')>{{ __('Prior month-end') }}</option>
            <option value="prior_year" @selected($compare === 'prior_year')>{{ __('Same date, prior year') }}</option>
        </select>
    </span>
</form>

@php($hasPrior = (bool) $priorAsOf)

<div class="bg-white rounded-xl border border-slate-100 p-5 mb-4">
    <p class="font-semibold text-slate-900">{{ __('Balance sheet') }} <span class="text-xs font-normal text-slate-400">{{ __('As at :date', ['date' => $asOf->format('d/m/Y')]) }}</span></p>
    @if ($hasPrior)
        <p class="text-xs text-slate-400 mt-1">{{ __('The Prior column is as of :date.', ['date' => $priorAsOf->format('d M Y')]) }}</p>
    @endif
</div>

<div class="bg-white rounded-xl border border-slate-100 overflow-x-auto shadow-sm">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 bg-slate-50/80 border-b-2 border-slate-200">
                <th class="px-5 py-3 font-semibold">{{ __('Ledger no.') }}</th>
                <th class="px-5 py-3 font-semibold">{{ __('Account') }}</th>
                <th class="px-5 py-3 font-semibold text-end">{{ __('Amount') }}</th>
                @if ($hasPrior)<th class="px-5 py-3 font-semibold text-end">{{ __('Prior') }}</th>@endif
            </tr>
        </thead>
        <tbody>
            @include('user.reports.partials.balance-sheet-plain-section', ['label' => __('Assets'), 'typeKey' => 'asset', 'rows' => $assets, 'hasPrior' => $hasPrior])
            @include('user.reports.partials.balance-sheet-plain-section', ['label' => __('Capital'), 'typeKey' => 'equity', 'rows' => $equity, 'hasPrior' => $hasPrior])
            @include('user.reports.partials.balance-sheet-plain-section', ['label' => __('Liabilities'), 'typeKey' => 'liability', 'rows' => $liabilities, 'hasPrior' => $hasPrior])
        </tbody>
        <tfoot>
            <tr class="bg-slate-50 font-bold text-slate-900 border-t-2 border-slate-200">
                <td colspan="2" class="px-5 py-3">{{ __('Total assets') }}</td>
                <td class="px-5 py-3 text-end tabular-nums">{{ \App\Support\Money::format($totalAssets) }}</td>
                @if ($hasPrior)<td class="px-5 py-3 text-end tabular-nums text-slate-400">{{ \App\Support\Money::format($priorTotalAssets) }}</td>@endif
            </tr>
            <tr class="bg-slate-900 font-bold text-white">
                <td colspan="2" class="px-5 py-3.5">{{ __('Total liabilities + equity') }}</td>
                <td class="px-5 py-3.5 text-end tabular-nums">{{ \App\Support\Money::format($totalLiabilities + $totalEquity) }}</td>
                @if ($hasPrior)<td class="px-5 py-3.5 text-end tabular-nums text-slate-300">{{ \App\Support\Money::format($priorTotalLiabilities + $priorTotalEquity) }}</td>@endif
            </tr>
        </tfoot>
    </table>
</div>

<p class="mt-4 text-center">
    <span class="inline-flex items-center gap-1 rounded-full {{ $balanced ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' }} px-3 py-1.5 text-xs font-semibold">
        {{ $balanced ? __('Assets equal liabilities plus equity') : __('Assets do NOT equal liabilities plus equity — this should never happen. Please contact support.') }}
    </span>
</p>
@endsection
