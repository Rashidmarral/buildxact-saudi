@extends('layouts.app')

@section('title', __('Statement of Changes in Equity'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Statement of Changes in Equity') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('How owners\' equity moved over the period: opening balance, contributions/drawings, and net income.') }}</p>
    </div>
    <a href="{{ route('app.reports.statement-of-changes-in-equity', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
    <a href="{{ route('app.reports.statement-of-changes-in-equity', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
</div>

@include('user.reports.partials.period-selector')

<div class="bg-white rounded-xl border border-slate-100 border-s-4 border-violet-600 overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-violet-800 bg-violet-50/60 border-b border-violet-100">
                <th class="px-5 py-3 font-medium">{{ __('Account') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Opening balance') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Movement') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Net income') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Closing balance') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-5 py-3 font-medium text-slate-800">{{ $row['account']->label() }}</td>
                    <td class="px-5 py-3 text-end">{{ number_format($row['opening'], 2) }}</td>
                    <td class="px-5 py-3 text-end">{{ $row['movement'] != 0 ? number_format($row['movement'], 2) : '' }}</td>
                    <td class="px-5 py-3 text-end"></td>
                    <td class="px-5 py-3 text-end font-medium">{{ number_format($row['closing'], 2) }}</td>
                </tr>
            @endforeach
            <tr class="border-b border-slate-50 last:border-0">
                <td class="px-5 py-3 font-medium text-slate-800">{{ __('Retained earnings') }}</td>
                <td class="px-5 py-3 text-end">{{ number_format($openingRetainedEarnings, 2) }}</td>
                <td class="px-5 py-3 text-end"></td>
                <td class="px-5 py-3 text-end">{{ number_format($netIncomeForPeriod, 2) }}</td>
                <td class="px-5 py-3 text-end font-medium">{{ number_format($openingRetainedEarnings + $netIncomeForPeriod, 2) }}</td>
            </tr>
        </tbody>
        <tfoot>
            <tr class="border-t border-violet-200 font-bold text-violet-700 bg-violet-50/60">
                <td class="px-5 py-3">{{ __('Total equity') }}</td>
                <td class="px-5 py-3 text-end">{{ number_format($totalOpening, 2) }}</td>
                <td class="px-5 py-3 text-end">{{ number_format($totalMovement, 2) }}</td>
                <td class="px-5 py-3 text-end">{{ number_format($netIncomeForPeriod, 2) }}</td>
                <td class="px-5 py-3 text-end">{{ number_format($totalClosing, 2) }}</td>
            </tr>
        </tfoot>
    </table>
</div>
@endsection
