@extends('layouts.app')

@section('title', __('Bank Reconciliation Summary'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Bank Reconciliation Summary') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Every bank statement reconciled in the period, across all accounts, with its book balance and any difference still outstanding.') }}</p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <a href="{{ route('app.reports.bank-reconciliation-summary', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
        <a href="{{ route('app.reports.bank-reconciliation-summary', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
    </div>
</div>

@include('user.reports.partials.period-selector')

<div class="bg-white rounded-xl border border-slate-100">
    @if ($rows->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No bank statements were reconciled in this period.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-5 py-3 font-medium">{{ __('Bank account') }}</th>
                    <th class="px-5 py-3 font-medium">{{ __('Statement date') }}</th>
                    <th class="px-5 py-3 font-medium text-end">{{ __('Statement balance') }}</th>
                    <th class="px-5 py-3 font-medium text-end">{{ __('Book balance') }}</th>
                    <th class="px-5 py-3 font-medium text-end">{{ __('Difference') }}</th>
                    <th class="px-5 py-3 font-medium text-end">{{ __('Outstanding') }}</th>
                    <th class="px-5 py-3 font-medium">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                        <td class="px-5 py-3 font-medium text-slate-800">
                            <a href="{{ route('app.bank-reconciliations.show', $row['reconciliation']) }}" class="text-brand-700 hover:underline">{{ $row['bankAccount']?->name ?? '—' }}</a>
                        </td>
                        <td class="px-5 py-3 text-slate-500">{{ $row['reconciliation']->statement_date->format('Y-m-d') }}</td>
                        <td class="px-5 py-3 text-end">{{ number_format($row['statementBalance'], 2) }}</td>
                        <td class="px-5 py-3 text-end">{{ number_format($row['bookBalance'], 2) }}</td>
                        <td class="px-5 py-3 text-end font-medium {{ abs($row['difference']) > 0.005 ? 'text-amber-600' : 'text-emerald-600' }}">{{ number_format($row['difference'], 2) }}</td>
                        <td class="px-5 py-3 text-end">{{ $row['outstandingCount'] }}</td>
                        <td class="px-5 py-3">
                            @if ($row['reconciliation']->status === 'completed')
                                <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">{{ __('Completed') }}</span>
                            @else
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">{{ __('In progress') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
