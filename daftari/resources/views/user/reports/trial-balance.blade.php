@extends('layouts.app')

@section('title', __('Trial Balance'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Trial Balance') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Opening balance, this period\'s movement, and closing balance for every account, grouped by category.') }}</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('app.reports.trial-balance-detailed', request()->query()) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('View detailed') }}</a>
        <a href="{{ route('app.reports.trial-balance', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
        <a href="{{ route('app.reports.trial-balance', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
    </div>
</div>

@include('user.reports.partials.period-selector')

<div class="bg-white rounded-xl border border-slate-100 overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-5 py-3 font-medium" rowspan="2">{{ __('Category') }}</th>
                <th class="px-5 py-3 font-medium text-end border-l border-slate-100" colspan="2">{{ __('Opening Balance') }}</th>
                <th class="px-5 py-3 font-medium text-end border-l border-slate-100" colspan="2">{{ __('Transactions') }}</th>
                <th class="px-5 py-3 font-medium text-end border-l border-slate-100" colspan="2">{{ __('Closing Balance') }}</th>
            </tr>
            <tr class="text-left text-slate-400 text-xs border-b border-slate-100">
                <th class="px-5 py-1.5 font-medium text-end border-l border-slate-100">{{ __('Dr') }}</th>
                <th class="px-5 py-1.5 font-medium text-end">{{ __('Cr') }}</th>
                <th class="px-5 py-1.5 font-medium text-end border-l border-slate-100">{{ __('Dr') }}</th>
                <th class="px-5 py-1.5 font-medium text-end">{{ __('Cr') }}</th>
                <th class="px-5 py-1.5 font-medium text-end border-l border-slate-100">{{ __('Dr') }}</th>
                <th class="px-5 py-1.5 font-medium text-end">{{ __('Cr') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($types as $type)
                <tr class="bg-slate-50">
                    <td colspan="7" class="px-5 py-2 font-semibold text-slate-700">{{ $type['label'] }}</td>
                </tr>
                @foreach ($type['categories'] as $categoryLabel => $category)
                    <tr class="border-b border-slate-50">
                        <td class="px-5 py-2.5 text-slate-700">{{ $categoryLabel }} <span class="text-slate-400">({{ $category['rows']->count() }})</span></td>
                        <td class="px-5 py-2.5 text-end border-l border-slate-50">{{ $category['openingDebit'] > 0 ? number_format($category['openingDebit'], 2) : '' }}</td>
                        <td class="px-5 py-2.5 text-end">{{ $category['openingCredit'] > 0 ? number_format($category['openingCredit'], 2) : '' }}</td>
                        <td class="px-5 py-2.5 text-end border-l border-slate-50">{{ $category['periodDebit'] > 0 ? number_format($category['periodDebit'], 2) : '' }}</td>
                        <td class="px-5 py-2.5 text-end">{{ $category['periodCredit'] > 0 ? number_format($category['periodCredit'], 2) : '' }}</td>
                        <td class="px-5 py-2.5 text-end border-l border-slate-50">{{ $category['closingDebit'] > 0 ? number_format($category['closingDebit'], 2) : '' }}</td>
                        <td class="px-5 py-2.5 text-end">{{ $category['closingCredit'] > 0 ? number_format($category['closingCredit'], 2) : '' }}</td>
                    </tr>
                @endforeach
                <tr class="border-b border-slate-100 font-semibold text-slate-800">
                    <td class="px-5 py-2.5">{{ __('Total of :type', ['type' => $type['label']]) }}</td>
                    <td class="px-5 py-2.5 text-end border-l border-slate-50">{{ number_format($type['openingDebit'], 2) }}</td>
                    <td class="px-5 py-2.5 text-end">{{ number_format($type['openingCredit'], 2) }}</td>
                    <td class="px-5 py-2.5 text-end border-l border-slate-50">{{ number_format($type['periodDebit'], 2) }}</td>
                    <td class="px-5 py-2.5 text-end">{{ number_format($type['periodCredit'], 2) }}</td>
                    <td class="px-5 py-2.5 text-end border-l border-slate-50">{{ number_format($type['closingDebit'], 2) }}</td>
                    <td class="px-5 py-2.5 text-end">{{ number_format($type['closingCredit'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-5 py-10 text-center text-slate-400">{{ __('No posted journal entries for this period.') }}</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="border-t border-slate-200 font-semibold text-slate-900">
                <td class="px-5 py-3">{{ __('Grand Total') }}</td>
                <td class="px-5 py-3 text-end border-l border-slate-100">{{ number_format($grand['openingDebit'], 2) }}</td>
                <td class="px-5 py-3 text-end">{{ number_format($grand['openingCredit'], 2) }}</td>
                <td class="px-5 py-3 text-end border-l border-slate-100">{{ number_format($grand['periodDebit'], 2) }}</td>
                <td class="px-5 py-3 text-end">{{ number_format($grand['periodCredit'], 2) }}</td>
                <td class="px-5 py-3 text-end border-l border-slate-100">{{ number_format($grand['closingDebit'], 2) }}</td>
                <td class="px-5 py-3 text-end">{{ number_format($grand['closingCredit'], 2) }}</td>
            </tr>
        </tfoot>
    </table>
</div>

@if (abs($grand['closingDebit'] - $grand['closingCredit']) > 0.01)
    <p class="mt-3 text-xs text-red-600">{{ __('Debits and credits do not balance — this should never happen. Please contact support.') }}</p>
@else
    <p class="mt-3 text-xs text-emerald-600">{{ __('Debits equal credits — books are balanced.') }}</p>
@endif
@endsection
