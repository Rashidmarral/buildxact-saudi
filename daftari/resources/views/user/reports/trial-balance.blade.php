@extends('layouts.app')

@section('title', __('Trial Balance'))

@section('content')
@php
    // Hamly-parity: a distinct accent per account type so Assets /
    // Liabilities / Equity / Income / Expenses read as clearly separate
    // sections at a glance, not just another gray-on-gray row.
    $typeStyles = [
        'asset' => ['dot' => 'bg-blue-600', 'accent' => 'border-blue-600', 'head' => 'bg-blue-50 text-blue-800', 'soft' => 'bg-blue-50/50', 'text' => 'text-blue-700', 'ring' => 'border-blue-200'],
        'liability' => ['dot' => 'bg-amber-500', 'accent' => 'border-amber-500', 'head' => 'bg-amber-50 text-amber-800', 'soft' => 'bg-amber-50/50', 'text' => 'text-amber-700', 'ring' => 'border-amber-200'],
        'equity' => ['dot' => 'bg-violet-600', 'accent' => 'border-violet-600', 'head' => 'bg-violet-50 text-violet-800', 'soft' => 'bg-violet-50/50', 'text' => 'text-violet-700', 'ring' => 'border-violet-200'],
        'revenue' => ['dot' => 'bg-emerald-600', 'accent' => 'border-emerald-600', 'head' => 'bg-emerald-50 text-emerald-800', 'soft' => 'bg-emerald-50/50', 'text' => 'text-emerald-700', 'ring' => 'border-emerald-200'],
        'expense' => ['dot' => 'bg-rose-600', 'accent' => 'border-rose-600', 'head' => 'bg-rose-50 text-rose-800', 'soft' => 'bg-rose-50/50', 'text' => 'text-rose-700', 'ring' => 'border-rose-200'],
    ];
    $fallbackStyle = ['dot' => 'bg-slate-500', 'accent' => 'border-slate-500', 'head' => 'bg-slate-50 text-slate-800', 'soft' => 'bg-slate-50/50', 'text' => 'text-slate-700', 'ring' => 'border-slate-200'];
@endphp
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Trial Balance') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Opening balance, this period\'s movement, and closing balance for every account, grouped by category.') }}</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('app.reports.trial-balance', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
        <a href="{{ route('app.reports.trial-balance', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
    </div>
</div>

@include('user.reports.partials.period-selector')

<form method="GET" class="bg-white rounded-xl border border-slate-100 p-4 mb-6 flex flex-wrap items-end gap-3">
    <input type="hidden" name="period" value="{{ $period['preset'] }}">
    @if ($period['preset'] === 'custom')
        <input type="hidden" name="from" value="{{ $period['from']->toDateString() }}">
        <input type="hidden" name="to" value="{{ $period['to']->toDateString() }}">
    @elseif ($period['preset'] === 'quarter')
        <input type="hidden" name="quarter" value="{{ $period['quarter'] ?? now()->quarter }}">
        <input type="hidden" name="year" value="{{ $period['year'] ?? now()->year }}">
    @endif
    <div>
        <label class="block text-xs font-semibold uppercase text-slate-400 mb-1">{{ __('Cost centre') }}</label>
        <select name="cost_center_id" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500 min-w-[12rem]">
            <option value="">{{ __('All cost centres') }}</option>
            @foreach ($costCenters as $cc)
                <option value="{{ $cc->id }}" @selected($costCenterId === $cc->id)>{{ $cc->label() ?? $cc->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-semibold uppercase text-slate-400 mb-1">{{ __('Location') }}</label>
        <select name="location_id" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500 min-w-[12rem]">
            <option value="">{{ __('All locations') }}</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected($locationId === $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-semibold uppercase text-slate-400 mb-1">{{ __('View') }}</label>
        <div class="inline-flex rounded-lg border border-slate-200 overflow-hidden text-sm">
            <a href="{{ route('app.reports.trial-balance', request()->query()) }}" class="px-3 py-2 font-semibold {{ ! $detailed ? 'bg-brand-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50' }}">{{ __('Summary') }}</a>
            <a href="{{ route('app.reports.trial-balance-detailed', request()->query()) }}" class="px-3 py-2 font-semibold border-l border-slate-200 {{ $detailed ? 'bg-brand-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50' }}">{{ __('Detailed') }}</a>
        </div>
    </div>
    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Apply') }}</button>
    @if ($locationId)
        <p class="w-full text-xs text-amber-600">{{ __('A location filter is active: only entries sourced from an invoice or bill at that location are included — manual journal lines carry no location and are always excluded when this filter is on.') }}</p>
    @endif
</form>

<div class="bg-white rounded-xl border border-slate-100 overflow-x-auto shadow-sm">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 bg-slate-50/80 border-b-2 border-slate-200">
                <th class="px-5 py-3 font-semibold" rowspan="2">{{ __('Heads of Account') }}</th>
                <th class="px-5 py-3 font-semibold text-end border-l border-slate-200" colspan="2">{{ __('Opening Balance') }}</th>
                <th class="px-5 py-3 font-semibold text-end border-l border-slate-200" colspan="2">{{ __('Transactions') }}</th>
                <th class="px-5 py-3 font-semibold text-end border-l border-slate-200" colspan="2">{{ __('Closing Balance') }}</th>
            </tr>
            <tr class="text-left text-slate-400 text-xs bg-slate-50/80 border-b-2 border-slate-200">
                <th class="px-5 py-1.5 font-medium text-end border-l border-slate-200">{{ __('Dr') }}</th>
                <th class="px-5 py-1.5 font-medium text-end">{{ __('Cr') }}</th>
                <th class="px-5 py-1.5 font-medium text-end border-l border-slate-200">{{ __('Dr') }}</th>
                <th class="px-5 py-1.5 font-medium text-end">{{ __('Cr') }}</th>
                <th class="px-5 py-1.5 font-medium text-end border-l border-slate-200">{{ __('Dr') }}</th>
                <th class="px-5 py-1.5 font-medium text-end">{{ __('Cr') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($types as $typeKey => $type)
                @php $style = $typeStyles[$typeKey] ?? $fallbackStyle; @endphp
                <tr class="{{ $style['head'] }}">
                    <td colspan="7" class="px-5 py-2.5 border-s-4 {{ $style['accent'] }} font-bold tracking-wide">
                        <span class="inline-flex h-2 w-2 rounded-full {{ $style['dot'] }} me-2 align-middle"></span>{{ $type['label'] }}
                    </td>
                </tr>
                @foreach ($type['categories'] as $categoryLabel => $category)
                    <tr class="border-b border-slate-50 {{ $style['soft'] }} hover:bg-slate-50 transition-colors">
                        <td class="px-5 py-2.5 text-slate-700 ps-8">
                            {{ $categoryLabel }}
                            <span class="ms-1 inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-500">{{ $category['rows']->count() }}</span>
                        </td>
                        <td class="px-5 py-2.5 text-end border-l border-slate-100 tabular-nums">{{ $category['openingDebit'] > 0 ? number_format($category['openingDebit'], 2) : '—' }}</td>
                        <td class="px-5 py-2.5 text-end tabular-nums">{{ $category['openingCredit'] > 0 ? number_format($category['openingCredit'], 2) : '—' }}</td>
                        <td class="px-5 py-2.5 text-end border-l border-slate-100 tabular-nums">{{ $category['periodDebit'] > 0 ? number_format($category['periodDebit'], 2) : '—' }}</td>
                        <td class="px-5 py-2.5 text-end tabular-nums">{{ $category['periodCredit'] > 0 ? number_format($category['periodCredit'], 2) : '—' }}</td>
                        <td class="px-5 py-2.5 text-end border-l border-slate-100 tabular-nums">{{ $category['closingDebit'] > 0 ? number_format($category['closingDebit'], 2) : '—' }}</td>
                        <td class="px-5 py-2.5 text-end tabular-nums">{{ $category['closingCredit'] > 0 ? number_format($category['closingCredit'], 2) : '—' }}</td>
                    </tr>
                @endforeach
                <tr class="border-b-2 {{ $style['ring'] }} font-bold {{ $style['text'] }} {{ $style['soft'] }}">
                    <td class="px-5 py-2.5">{{ __('Total of :type', ['type' => $type['label']]) }}</td>
                    <td class="px-5 py-2.5 text-end border-l border-slate-100">{{ number_format($type['openingDebit'], 2) }}</td>
                    <td class="px-5 py-2.5 text-end">{{ number_format($type['openingCredit'], 2) }}</td>
                    <td class="px-5 py-2.5 text-end border-l border-slate-100">{{ number_format($type['periodDebit'], 2) }}</td>
                    <td class="px-5 py-2.5 text-end">{{ number_format($type['periodCredit'], 2) }}</td>
                    <td class="px-5 py-2.5 text-end border-l border-slate-100">{{ number_format($type['closingDebit'], 2) }}</td>
                    <td class="px-5 py-2.5 text-end">{{ number_format($type['closingCredit'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-5 py-10 text-center text-slate-400">{{ __('This company has no accounts yet.') }}</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="bg-slate-900 font-bold text-white">
                <td class="px-5 py-3.5 tracking-wide">{{ __('Grand Total') }}</td>
                <td class="px-5 py-3.5 text-end border-l border-white/10">{{ number_format($grand['openingDebit'], 2) }}</td>
                <td class="px-5 py-3.5 text-end">{{ number_format($grand['openingCredit'], 2) }}</td>
                <td class="px-5 py-3.5 text-end border-l border-white/10">{{ number_format($grand['periodDebit'], 2) }}</td>
                <td class="px-5 py-3.5 text-end">{{ number_format($grand['periodCredit'], 2) }}</td>
                <td class="px-5 py-3.5 text-end border-l border-white/10">{{ number_format($grand['closingDebit'], 2) }}</td>
                <td class="px-5 py-3.5 text-end">{{ number_format($grand['closingCredit'], 2) }}</td>
            </tr>
        </tfoot>
    </table>
</div>

@if (abs($grand['closingDebit'] - $grand['closingCredit']) > 0.01)
    <p class="mt-3 flex items-center gap-1.5 text-xs font-medium text-red-600">
        <span class="inline-flex h-1.5 w-1.5 rounded-full bg-red-500"></span>
        {{ __('Debits and credits do not balance — this should never happen. Please contact support.') }}
    </p>
@else
    <p class="mt-3 flex items-center gap-1.5 text-xs font-medium text-emerald-600">
        <span class="inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
        {{ __('Debits equal credits — books are balanced.') }}
    </p>
@endif
@endsection
