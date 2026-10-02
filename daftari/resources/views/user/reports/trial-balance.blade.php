@extends('layouts.app')

@section('title', __('Trial Balance'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Trial Balance') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Total debits and credits posted to each account for the period.') }}</p>
    </div>
    <a href="{{ route('app.reports.trial-balance', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
</div>

@include('user.reports.partials.period-selector')

<div class="bg-white rounded-xl border border-slate-100">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-5 py-3 font-medium">{{ __('Account') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Debit') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Credit') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-5 py-3 font-medium text-slate-800">{{ $row['account']->label() }}</td>
                    <td class="px-5 py-3 text-end">{{ $row['debit'] > 0 ? number_format($row['debit'], 2) : '' }}</td>
                    <td class="px-5 py-3 text-end">{{ $row['credit'] > 0 ? number_format($row['credit'], 2) : '' }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="px-5 py-10 text-center text-slate-400">{{ __('No posted journal entries for this period.') }}</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="border-t border-slate-200 font-semibold text-slate-900">
                <td class="px-5 py-3">{{ __('Total') }}</td>
                <td class="px-5 py-3 text-end">{{ number_format($totalDebit, 2) }}</td>
                <td class="px-5 py-3 text-end">{{ number_format($totalCredit, 2) }}</td>
            </tr>
        </tfoot>
    </table>
</div>

@if (abs($totalDebit - $totalCredit) > 0.01)
    <p class="mt-3 text-xs text-red-600">{{ __('Debits and credits do not balance — this should never happen. Please contact support.') }}</p>
@else
    <p class="mt-3 text-xs text-emerald-600">{{ __('Debits equal credits — books are balanced.') }}</p>
@endif
@endsection
