@extends('layouts.app')

@section('title', __('Profit & Loss by Month'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Profit & Loss by Month') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Income and expenses with one column per month of the period, to spot trends and seasonality.') }}</p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <a href="{{ route('app.reports.income-statement-by-month', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
        <a href="{{ route('app.reports.income-statement-by-month', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
    </div>
</div>

@include('user.reports.partials.period-selector')

<div class="bg-white rounded-xl border border-slate-100 overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-4 py-3 font-medium sticky left-0 bg-white">{{ __('Account') }}</th>
                @foreach ($months as $month)
                    <th class="px-4 py-3 font-medium text-end whitespace-nowrap">{{ $month->translatedFormat('M Y') }}</th>
                @endforeach
                <th class="px-4 py-3 font-medium text-end whitespace-nowrap">{{ __('Total') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr class="bg-slate-50"><td class="px-4 py-2 font-semibold text-slate-700" colspan="{{ $months->count() + 2 }}">{{ __('Income') }}</td></tr>
            @foreach ($revenueLines as $row)
                <tr class="border-b border-slate-50">
                    <td class="px-4 py-2 sticky left-0 bg-white">{{ $row['account']->name }}</td>
                    @foreach ($months as $month)
                        <td class="px-4 py-2 text-end">{{ number_format($row['monthly'][$month->format('Y-m')], 2) }}</td>
                    @endforeach
                    <td class="px-4 py-2 text-end font-medium">{{ number_format($row['total'], 2) }}</td>
                </tr>
            @endforeach
            <tr class="border-b-2 border-slate-800 font-semibold">
                <td class="px-4 py-2 sticky left-0 bg-white">{{ __('Total income') }}</td>
                @foreach ($months as $month)
                    <td class="px-4 py-2 text-end">{{ number_format($monthlyIncome[$month->format('Y-m')], 2) }}</td>
                @endforeach
                <td class="px-4 py-2 text-end">{{ number_format($totalIncome, 2) }}</td>
            </tr>

            <tr class="bg-slate-50"><td class="px-4 py-2 font-semibold text-slate-700" colspan="{{ $months->count() + 2 }}">{{ __('Expenses') }}</td></tr>
            @foreach ($expenseLines as $row)
                <tr class="border-b border-slate-50">
                    <td class="px-4 py-2 sticky left-0 bg-white">{{ $row['account']->name }}</td>
                    @foreach ($months as $month)
                        <td class="px-4 py-2 text-end">{{ number_format($row['monthly'][$month->format('Y-m')], 2) }}</td>
                    @endforeach
                    <td class="px-4 py-2 text-end font-medium">{{ number_format($row['total'], 2) }}</td>
                </tr>
            @endforeach
            <tr class="border-b-2 border-slate-800 font-semibold">
                <td class="px-4 py-2 sticky left-0 bg-white">{{ __('Total expenses') }}</td>
                @foreach ($months as $month)
                    <td class="px-4 py-2 text-end">{{ number_format($monthlyExpenses[$month->format('Y-m')], 2) }}</td>
                @endforeach
                <td class="px-4 py-2 text-end">{{ number_format($totalExpenses, 2) }}</td>
            </tr>
        </tbody>
        <tfoot>
            <tr class="font-bold text-slate-900">
                <td class="px-4 py-3 sticky left-0 bg-white">{{ __('Net profit') }}</td>
                @foreach ($months as $month)
                    <td class="px-4 py-3 text-end {{ $monthlyNetProfit[$month->format('Y-m')] >= 0 ? 'text-emerald-700' : 'text-red-600' }}">{{ number_format($monthlyNetProfit[$month->format('Y-m')], 2) }}</td>
                @endforeach
                <td class="px-4 py-3 text-end {{ $netProfit >= 0 ? 'text-emerald-700' : 'text-red-600' }}">{{ number_format($netProfit, 2) }}</td>
            </tr>
        </tfoot>
    </table>
</div>
@endsection
