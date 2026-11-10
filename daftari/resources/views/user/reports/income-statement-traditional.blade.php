@extends('layouts.app')

@section('title', __('Income Statement (Traditional T-Account)'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Income Statement (Traditional T-Account)') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('The classic ledger-style presentation — Expenses on the debit side, Revenue on the credit side, both totals equal.') }}</p>
    </div>
    <a href="{{ route('app.reports.income-statement', request()->query()) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Formal (single-column) view') }}</a>
</div>

@include('user.reports.partials.period-selector')

<div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
    <div class="grid grid-cols-2 divide-x divide-slate-100">
        <div>
            <div class="px-5 py-2.5 bg-slate-50 border-b border-slate-100 font-semibold text-slate-700 text-sm">{{ __('Dr (Expenses)') }}</div>
            <table class="w-full text-sm">
                <tbody>
                    @foreach ($expenseLines as $row)
                        <tr class="border-b border-slate-50">
                            <td class="px-5 py-2 text-slate-600">{{ $row['account']->name }}</td>
                            <td class="px-5 py-2 text-end">{{ number_format($row['amount'], 2) }}</td>
                        </tr>
                    @endforeach
                    @if ($netProfit >= 0)
                        <tr class="border-b border-slate-50 italic text-emerald-700">
                            <td class="px-5 py-2">{{ __('Net Profit c/d') }}</td>
                            <td class="px-5 py-2 text-end">{{ number_format($netProfit, 2) }}</td>
                        </tr>
                    @endif
                </tbody>
                <tfoot>
                    <tr class="border-t border-slate-200 font-semibold text-slate-900">
                        <td class="px-5 py-3">{{ __('Total') }}</td>
                        <td class="px-5 py-3 text-end">{{ number_format($debitTotal, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div>
            <div class="px-5 py-2.5 bg-slate-50 border-b border-slate-100 font-semibold text-slate-700 text-sm">{{ __('Cr (Revenue)') }}</div>
            <table class="w-full text-sm">
                <tbody>
                    @foreach ($revenueLines as $row)
                        <tr class="border-b border-slate-50">
                            <td class="px-5 py-2 text-slate-600">{{ $row['account']->name }}</td>
                            <td class="px-5 py-2 text-end">{{ number_format($row['amount'], 2) }}</td>
                        </tr>
                    @endforeach
                    @if ($netProfit < 0)
                        <tr class="border-b border-slate-50 italic text-red-700">
                            <td class="px-5 py-2">{{ __('Net Loss c/d') }}</td>
                            <td class="px-5 py-2 text-end">{{ number_format(abs($netProfit), 2) }}</td>
                        </tr>
                    @endif
                </tbody>
                <tfoot>
                    <tr class="border-t border-slate-200 font-semibold text-slate-900">
                        <td class="px-5 py-3">{{ __('Total') }}</td>
                        <td class="px-5 py-3 text-end">{{ number_format($creditTotal, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<p class="mt-3 text-xs text-slate-400">
    {{ $netProfit >= 0
        ? __('Net Profit brought down (b/d): :amount', ['amount' => number_format($netProfit, 2)])
        : __('Net Loss brought down (b/d): :amount', ['amount' => number_format(abs($netProfit), 2)]) }}
</p>
@endsection
