@extends('layouts.app')

@section('title', __('Receivables/Payables by Contact'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Receivables/Payables by Contact') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Every customer or supplier\'s own opening balance, movement and closing balance for the period, from the receivable/payable control account.') }}</p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <a href="{{ route('app.reports.receivables-payables-by-contact', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
        <a href="{{ route('app.reports.receivables-payables-by-contact', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
    </div>
</div>

<div class="flex items-center gap-2 mb-6 text-sm border-b border-slate-100">
    <a href="{{ route('app.reports.receivables-payables-by-contact', array_merge(request()->query(), ['type' => 'receivables'])) }}" class="px-3 py-2 border-b-2 {{ $type === 'receivables' ? 'border-brand-600 text-brand-700 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700' }}">{{ __('Receivables (customers)') }}</a>
    <a href="{{ route('app.reports.receivables-payables-by-contact', array_merge(request()->query(), ['type' => 'payables'])) }}" class="px-3 py-2 border-b-2 {{ $type === 'payables' ? 'border-brand-600 text-brand-700 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700' }}">{{ __('Payables (suppliers)') }}</a>
</div>

@include('user.reports.partials.period-selector')

<div class="bg-white rounded-xl border border-slate-100 overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-5 py-3 font-medium">{{ $type === 'receivables' ? __('Customer') : __('Supplier') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Opening balance') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Debit') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Credit') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Closing balance') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-5 py-3 font-medium text-slate-800">{{ $row['party']->name }}</td>
                    <td class="px-5 py-3 text-end">{{ \App\Support\Money::format($row['opening']) }}</td>
                    <td class="px-5 py-3 text-end">{{ \App\Support\Money::format($row['debit']) }}</td>
                    <td class="px-5 py-3 text-end">{{ \App\Support\Money::format($row['credit']) }}</td>
                    <td class="px-5 py-3 text-end font-semibold text-slate-900">{{ \App\Support\Money::format($row['closing']) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">{{ __('No activity in this period.') }}</td></tr>
            @endforelse
        </tbody>
        @if ($rows->isNotEmpty())
            <tfoot>
                <tr class="border-t-2 border-slate-800 font-semibold text-slate-900">
                    <td class="px-5 py-3">{{ __('Total') }}</td>
                    <td class="px-5 py-3 text-end">{{ \App\Support\Money::format($totals['opening']) }}</td>
                    <td class="px-5 py-3 text-end">{{ \App\Support\Money::format($totals['debit']) }}</td>
                    <td class="px-5 py-3 text-end">{{ \App\Support\Money::format($totals['credit']) }}</td>
                    <td class="px-5 py-3 text-end">{{ \App\Support\Money::format($totals['closing']) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>
@endsection
