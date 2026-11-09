@extends('layouts.app')

@section('title', __('Fixed Asset Schedule'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Fixed Asset Schedule') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Opening net book value, additions, disposals and depreciation for the period, tied to the general ledger.') }}</p>
    </div>
    <a href="{{ route('app.reports.fixed-asset-schedule', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
    <a href="{{ route('app.reports.fixed-asset-schedule', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
</div>

@include('user.reports.partials.period-selector')

<div class="bg-white rounded-xl border border-slate-100">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-5 py-3 font-medium">{{ __('Asset') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('Category') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Opening NBV') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Additions') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Depreciation') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Disposals') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Closing NBV') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-5 py-3">
                        <p class="font-medium text-slate-800">{{ $row['asset']->name }}</p>
                        <p class="text-xs text-slate-400">{{ $row['asset']->asset_code }}</p>
                    </td>
                    <td class="px-5 py-3 text-slate-500">{{ $row['asset']->category }}</td>
                    <td class="px-5 py-3 text-end">{{ number_format($row['openingNbv'], 2) }}</td>
                    <td class="px-5 py-3 text-end">{{ $row['additions'] > 0 ? number_format($row['additions'], 2) : '' }}</td>
                    <td class="px-5 py-3 text-end {{ $row['depreciation'] > 0 ? 'text-red-600' : '' }}">{{ $row['depreciation'] > 0 ? '-'.number_format($row['depreciation'], 2) : '' }}</td>
                    <td class="px-5 py-3 text-end {{ $row['disposals'] > 0 ? 'text-red-600' : '' }}">{{ $row['disposals'] > 0 ? '-'.number_format($row['disposals'], 2) : '' }}</td>
                    <td class="px-5 py-3 text-end font-medium">{{ number_format($row['closingNbv'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-5 py-10 text-center text-slate-400">{{ __('No fixed asset existed or had activity in this period.') }}</td></tr>
            @endforelse
        </tbody>
        @if ($rows->isNotEmpty())
            <tfoot>
                <tr class="border-t border-slate-200 font-semibold text-slate-900">
                    <td class="px-5 py-3" colspan="2">{{ __('Total') }}</td>
                    <td class="px-5 py-3 text-end">{{ number_format($totals['openingNbv'], 2) }}</td>
                    <td class="px-5 py-3 text-end">{{ number_format($totals['additions'], 2) }}</td>
                    <td class="px-5 py-3 text-end">{{ $totals['depreciation'] > 0 ? '-'.number_format($totals['depreciation'], 2) : number_format(0, 2) }}</td>
                    <td class="px-5 py-3 text-end">{{ $totals['disposals'] > 0 ? '-'.number_format($totals['disposals'], 2) : number_format(0, 2) }}</td>
                    <td class="px-5 py-3 text-end">{{ number_format($totals['closingNbv'], 2) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>
@endsection
