@extends('layouts.app')

@section('title', __('Profit & Loss by Location'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Profit & Loss by Location') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Income and expenses split by business location. Lines with no location are shown separately as Unassigned.') }}</p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <a href="{{ route('app.reports.profit-loss-by-location', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
        <a href="{{ route('app.reports.profit-loss-by-location', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
    </div>
</div>

@include('user.reports.partials.period-selector')

<div class="bg-white rounded-xl border border-slate-100">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-5 py-3 font-medium">{{ __('Location') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Income') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Expenses') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Net') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-5 py-3 font-medium text-slate-800">{{ $row['branch']?->name ?? __('Unassigned') }}</td>
                    <td class="px-5 py-3 text-end">{{ \App\Support\Money::format($row['income']) }}</td>
                    <td class="px-5 py-3 text-end">{{ \App\Support\Money::format($row['expenses']) }}</td>
                    <td class="px-5 py-3 text-end font-semibold {{ $row['net'] >= 0 ? 'text-emerald-700' : 'text-red-600' }}">{{ \App\Support\Money::format($row['net']) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-5 py-10 text-center text-slate-400">{{ __('No income or expenses in this period.') }}</td></tr>
            @endforelse
        </tbody>
        @if ($rows->isNotEmpty())
            <tfoot>
                <tr class="border-t-2 border-slate-800 font-semibold text-slate-900">
                    <td class="px-5 py-3">{{ __('Total') }}</td>
                    <td class="px-5 py-3 text-end">{{ \App\Support\Money::format($totalIncome) }}</td>
                    <td class="px-5 py-3 text-end">{{ \App\Support\Money::format($totalExpenses) }}</td>
                    <td class="px-5 py-3 text-end">{{ \App\Support\Money::format($totalIncome - $totalExpenses) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>
@endsection
