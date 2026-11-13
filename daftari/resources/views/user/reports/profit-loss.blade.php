@extends('layouts.app')

@section('title', __('Profit & Loss (Simple)'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Profit & Loss (Simple)') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('A quick Income / Expenses / Net Profit view — for the full Gross Profit / Operating Profit staging, see the Income Statement.') }}</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('app.reports.profit-loss', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
        <a href="{{ route('app.reports.profit-loss', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
    </div>
</div>

@include('user.reports.partials.period-selector')

<div class="grid md:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-4">{{ __('Income') }}</h3>
        <div class="space-y-2 text-sm">
            @forelse ($revenueLines as $row)
                <div class="flex justify-between text-slate-600">
                    <span>{{ $row['account']->label() }}</span>
                    <span>{{ number_format($row['amount'], 2) }}</span>
                </div>
            @empty
                <p class="text-slate-400">{{ __('No income yet.') }}</p>
            @endforelse
        </div>
        <div class="flex justify-between font-bold text-slate-900 pt-3 mt-3 border-t border-slate-200">
            <span>{{ __('Total income') }}</span>
            <span>{{ \App\Support\Money::format($totalIncome) }}</span>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-4">{{ __('Expenses') }}</h3>
        <div class="space-y-2 text-sm">
            @forelse ($expenseLines as $row)
                <div class="flex justify-between text-slate-600">
                    <span>{{ $row['account']->label() }}</span>
                    <span>{{ number_format($row['amount'], 2) }}</span>
                </div>
            @empty
                <p class="text-slate-400">{{ __('No expenses yet.') }}</p>
            @endforelse
        </div>
        <div class="flex justify-between font-bold text-slate-900 pt-3 mt-3 border-t border-slate-200">
            <span>{{ __('Total expenses') }}</span>
            <span>{{ \App\Support\Money::format($totalExpenses) }}</span>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-100 p-6 flex items-center justify-between">
    <span class="font-semibold text-slate-900">{{ __('Net Profit') }}</span>
    <span class="text-xl font-bold {{ $netProfit >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ \App\Support\Money::format($netProfit) }}</span>
</div>
@endsection
