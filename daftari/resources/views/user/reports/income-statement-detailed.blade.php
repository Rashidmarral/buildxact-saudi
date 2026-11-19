@extends('layouts.app')

@section('title', __('Income Statement (Detailed)'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Income Statement (Detailed)') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Every income and expense account with its ledger code under direct income, direct expenses, other income and indirect expenses, with gross and net profit.') }}</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('app.reports.income-statement-detailed', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
        <a href="{{ route('app.reports.income-statement-detailed', array_merge(request()->query(), ['export' => 'excel'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download Excel') }}</a>
        <a href="{{ route('app.reports.income-statement-detailed', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
    </div>
</div>

<div class="mb-4 flex items-center gap-4 text-sm">
    <a href="{{ route('app.reports.income-statement', request()->query()) }}" class="text-brand-600 font-medium hover:underline">{{ __('Two-sided view') }}</a>
</div>

@include('user.reports.partials.period-selector')

<div class="bg-white rounded-xl border border-slate-100 p-5 mb-4">
    <p class="font-semibold text-slate-900">{{ __('Income statement (detailed)') }} <span class="text-xs font-normal text-slate-400">{{ $period['from']->format('d/m/Y') }} ~ {{ $period['to']->format('d/m/Y') }}</span></p>
</div>

<div class="bg-white rounded-xl border border-slate-100 overflow-x-auto shadow-sm">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 bg-slate-50/80 border-b-2 border-slate-200">
                <th class="px-5 py-3 font-semibold">{{ __('Ledger no.') }}</th>
                <th class="px-5 py-3 font-semibold">{{ __('Account') }}</th>
                <th class="px-5 py-3 font-semibold text-end">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @include('user.reports.partials.income-statement-detailed-section', ['label' => __('Direct income'), 'typeKey' => 'revenue', 'categories' => $directIncome])
            @include('user.reports.partials.income-statement-detailed-section', ['label' => __('Direct expenses'), 'typeKey' => 'expense', 'categories' => $directExpenses])
            <tr class="bg-slate-900 text-white font-bold">
                <td colspan="2" class="px-5 py-3">{{ $grossProfit >= 0 ? __('Gross profit') : __('Gross loss') }}</td>
                <td class="px-5 py-3 text-end tabular-nums">{{ number_format(abs($grossProfit), 2) }}</td>
            </tr>

            @include('user.reports.partials.income-statement-detailed-section', ['label' => __('Other income'), 'typeKey' => 'revenue', 'categories' => $otherIncome])
            @include('user.reports.partials.income-statement-detailed-section', ['label' => __('Indirect expenses'), 'typeKey' => 'expense', 'categories' => $indirectExpenses])
            <tr class="bg-slate-900 text-white font-bold">
                <td colspan="2" class="px-5 py-3">{{ $netProfit >= 0 ? __('Net profit') : __('Net loss') }}</td>
                <td class="px-5 py-3 text-end tabular-nums">{{ number_format(abs($netProfit), 2) }}</td>
            </tr>
        </tbody>
    </table>
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
