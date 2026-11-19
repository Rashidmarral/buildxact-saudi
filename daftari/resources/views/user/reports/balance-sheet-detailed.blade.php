@extends('layouts.app')

@section('title', __('Balance Sheet (Detailed)'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Balance Sheet (Detailed)') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Two-sided balance sheet listing every account with its ledger code under its group.') }}</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('app.reports.balance-sheet-detailed', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
        <a href="{{ route('app.reports.balance-sheet-detailed', array_merge(request()->query(), ['export' => 'excel'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download Excel') }}</a>
        <a href="{{ route('app.reports.balance-sheet-detailed', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
    </div>
</div>

<div class="mb-4 flex items-center gap-4 text-sm">
    <a href="{{ route('app.reports.balance-sheet', ['as_of' => $asOf->toDateString()]) }}" class="text-brand-600 font-medium hover:underline">{{ __('Plain view') }}</a>
    <a href="{{ route('app.reports.balance-sheet-summary', ['as_of' => $asOf->toDateString()]) }}" class="text-brand-600 font-medium hover:underline">{{ __('Two-sided (summary) view') }}</a>
</div>

<form method="GET" class="bg-white rounded-xl border border-slate-100 p-4 mb-6 flex items-center gap-3">
    <label class="text-xs font-semibold uppercase text-slate-500">{{ __('As of') }}</label>
    <input type="date" name="as_of" value="{{ $asOf->toDateString() }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
    <button type="submit" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-300">{{ __('Apply') }}</button>
</form>

<div class="bg-white rounded-xl border border-slate-100 p-5 mb-4">
    <p class="font-semibold text-slate-900">{{ __('Balance sheet (detailed)') }} <span class="text-xs font-normal text-slate-400">{{ __('As at :date', ['date' => $asOf->format('d/m/Y')]) }}</span></p>
</div>

@include('user.reports.partials.balance-sheet-two-sided-table', ['detailed' => true])

<p class="mt-4 text-center">
    <span class="inline-flex items-center gap-1 rounded-full {{ $balanced ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' }} px-3 py-1.5 text-xs font-semibold">
        {{ $balanced ? __('Assets equal liabilities plus equity') : __('Assets do NOT equal liabilities plus equity — this should never happen. Please contact support.') }}
    </span>
</p>
@endsection
