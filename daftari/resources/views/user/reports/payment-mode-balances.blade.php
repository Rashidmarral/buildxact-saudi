@extends('layouts.app')

@section('title', __('Payment Mode Balances'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Payment Mode Balances') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('What each payment mode collected this period, next to its linked GL account\'s actual balance — the same idea as a bank reconciliation, for the tender dimension.') }}</p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <a href="{{ route('app.reports.payment-mode-balances', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
        <a href="{{ route('app.reports.payment-mode-balances', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
    </div>
</div>

@include('user.reports.partials.period-selector')

<div class="bg-white rounded-xl border border-slate-100">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-5 py-3 font-medium">{{ __('Payment mode') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Collected this period') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Account balance') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-5 py-3 font-medium text-slate-800">{{ $row['mode']->name }}</td>
                    <td class="px-5 py-3 text-end">{{ \App\Support\Money::format($row['periodTotal']) }}</td>
                    <td class="px-5 py-3 text-end">{{ $row['accountBalance'] === null ? '—' : \App\Support\Money::format($row['accountBalance']) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="px-5 py-10 text-center text-slate-400">{{ __('No payment modes configured yet.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
