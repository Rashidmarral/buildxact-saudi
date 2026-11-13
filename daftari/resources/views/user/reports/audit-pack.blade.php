@extends('layouts.app')

@section('title', __('Year-End Audit Pack'))

@section('content')
<div class="mb-6">
    <h2 class="text-lg font-semibold text-slate-900">{{ __('Year-End Audit Pack') }}</h2>
    <p class="text-sm text-slate-500 mt-1">{{ __('Trial Balance, General Ledger, Balance Sheet, Income Statement, Cash Flow, Statement of Changes in Equity, Fixed Asset Schedule, and Aged Receivables/Payables for the chosen period — bundled into one PDF for your auditor.') }}</p>
</div>

@include('user.reports.partials.period-selector')

<div class="bg-white rounded-xl border border-slate-100 p-6">
    <p class="text-sm text-slate-600 mb-4">{{ __('This downloads a single PDF with every statement above, in order, each starting on its own page.') }}</p>
    <a href="{{ route('app.reports.audit-pack.pdf', request()->query()) }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">
        {{ __('Download Audit Pack PDF') }}
    </a>
</div>
@endsection
