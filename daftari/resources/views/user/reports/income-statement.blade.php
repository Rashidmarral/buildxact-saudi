@extends('layouts.app')

@section('title', __('Income Statement (P&L)'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Income Statement (P&L)') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Financial results for selected period') }}</p>
    </div>
    <a href="{{ route('app.reports.income-statement', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
    <a href="{{ route('app.reports.income-statement', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
</div>

@include('user.reports.partials.period-selector')

<div class="grid sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Net sales') }}</p>
        <p class="text-2xl font-bold text-slate-900 mt-1">{{ \App\Support\Money::format($netSales) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Gross profit') }}</p>
        <p class="text-2xl font-bold text-slate-900 mt-1">{{ \App\Support\Money::format($grossProfit) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Operating profit (loss)') }}</p>
        <p class="text-2xl font-bold {{ $operatingProfit >= 0 ? 'text-slate-900' : 'text-red-600' }} mt-1">{{ \App\Support\Money::format($operatingProfit) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Net profit') }}</p>
        <p class="text-2xl font-bold {{ $netProfit >= 0 ? 'text-slate-900' : 'text-red-600' }} mt-1">{{ \App\Support\Money::format($netProfit) }}</p>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-100 p-6">
    <h3 class="font-semibold text-slate-900 mb-1">{{ __('Profit & Loss details') }}</h3>
    <p class="text-sm text-slate-500 mb-4">{{ __('Computed from posted ledger entries.') }}</p>

    @if ($revenueLines->isEmpty() && $expenseLines->isEmpty())
        <div class="py-16 text-center">
            <p class="font-semibold text-slate-800">{{ __('No posted journal entries for this period') }}</p>
            <p class="text-sm text-slate-500 mt-1">{{ __('There are no posted entries in the selected range. Try widening the date range, or check that your books have entries posted.') }}</p>
            <a href="{{ route('app.journals.index') }}" class="inline-block mt-4 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('View journals') }}</a>
        </div>
    @else
        <div class="grid md:grid-cols-2 gap-6">
            <div>
                <p class="text-xs font-semibold uppercase text-slate-400 mb-2">{{ __('Revenue') }}</p>
                <div class="space-y-2 text-sm">
                    @foreach ($revenueLines as $row)
                        <div class="flex justify-between text-slate-600"><span>{{ $row['account']->label() }}</span><span>{{ number_format($row['amount'], 2) }}</span></div>
                    @endforeach
                </div>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase text-slate-400 mb-2">{{ __('Expenses') }}</p>
                <div class="space-y-2 text-sm">
                    @foreach ($expenseLines as $row)
                        <div class="flex justify-between text-slate-600"><span>{{ $row['account']->label() }}</span><span>{{ number_format($row['amount'], 2) }}</span></div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>

@if ($priorPeriodAdjustments->isNotEmpty())
    <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-6">
        <h3 class="font-semibold text-amber-900 mb-1">{{ __('Prior-period adjustments included above') }}</h3>
        <p class="text-sm text-amber-800 mb-4">{{ __('Voiding a bill, invoice, or voucher reverses it on the date it was voided, not back on its original date — a closed period\'s own figures are never silently rewritten. That reversal lands in THIS period\'s totals above, which is why profit can move with no new sales, payment, expense or purchase to show for it.') }}</p>
        <div class="space-y-2 text-sm">
            @foreach ($priorPeriodAdjustments as $row)
                <div class="flex items-center justify-between gap-4 rounded-lg bg-white/60 px-4 py-2.5">
                    <div>
                        <p class="font-medium text-slate-800">{{ $row['label'] }}</p>
                        <p class="text-xs text-slate-500">{{ __('Originally posted :date — outside this period.', ['date' => $row['originalDate']->format('d M Y')]) }}</p>
                    </div>
                    <p class="font-semibold {{ $row['amount'] >= 0 ? 'text-emerald-700' : 'text-red-600' }}">
                        {{ $row['amount'] >= 0 ? '+' : '' }}{{ number_format($row['amount'], 2) }}
                    </p>
                </div>
            @endforeach
        </div>
    </div>
@endif
@endsection
