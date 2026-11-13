@extends('layouts.app')

@section('title', __('Balance Sheet (Detailed)'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Balance Sheet (Detailed)') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Two-sided view — Assets against Liabilities + Equity, every account with its ledger code.') }}</p>
    </div>
    <div class="flex items-center gap-2">
        @if ($balanced)
            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-1.5 text-xs font-semibold">✓ {{ __('Balance check') }}</span>
        @else
            <span class="inline-flex items-center gap-1 rounded-full bg-red-50 text-red-700 border border-red-200 px-3 py-1.5 text-xs font-semibold">✕ {{ __('Balance check') }}</span>
        @endif
        <a href="{{ route('app.reports.balance-sheet-detailed', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
        <a href="{{ route('app.reports.balance-sheet-detailed', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
    </div>
</div>

<div class="mb-4 flex items-center gap-4 text-sm">
    <a href="{{ route('app.reports.balance-sheet', ['as_of' => $asOf->toDateString()]) }}" class="text-brand-600 font-medium hover:underline">{{ __('Plain view') }}</a>
    <a href="{{ route('app.reports.balance-sheet-summary', ['as_of' => $asOf->toDateString()]) }}" class="text-brand-600 font-medium hover:underline">{{ __('Summary (two-sided) view') }}</a>
</div>

<form method="GET" class="bg-white rounded-xl border border-slate-100 p-4 mb-6 flex items-center gap-3">
    <label class="text-xs font-semibold uppercase text-slate-500">{{ __('As of') }}</label>
    <input type="date" name="as_of" value="{{ $asOf->toDateString() }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
    <button type="submit" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-300">{{ __('Apply') }}</button>
</form>

@php
    // Same type-color system Trial Balance established, adapted to this
    // page's two-sided card layout instead of a type-grouped table.
    $typeStyles = [
        'asset' => ['accent' => 'border-blue-600', 'text' => 'text-blue-700', 'soft' => 'bg-blue-50/60', 'badge' => 'bg-blue-50 text-blue-700'],
        'liability' => ['accent' => 'border-amber-500', 'text' => 'text-amber-700', 'soft' => 'bg-amber-50/60', 'badge' => 'bg-amber-50 text-amber-700'],
        'equity' => ['accent' => 'border-violet-600', 'text' => 'text-violet-700', 'soft' => 'bg-violet-50/60', 'badge' => 'bg-violet-50 text-violet-700'],
    ];
@endphp
<div class="grid md:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl border border-slate-100 border-s-4 {{ $typeStyles['asset']['accent'] }} p-6">
        <h3 class="font-semibold {{ $typeStyles['asset']['text'] }} mb-4">{{ __('Assets') }}</h3>
        <div class="space-y-3 text-sm">
            @forelse ($assetCategories as $catLabel => $category)
                <div>
                    <span class="inline-flex rounded-full {{ $typeStyles['asset']['badge'] }} px-2 py-0.5 text-[11px] font-semibold mb-1">{{ $catLabel }}</span>
                    @foreach ($category['rows'] as $row)
                        <div class="flex justify-between text-slate-600">
                            <span>{{ $row['account']?->label() ?? $row['label'] }}</span>
                            <span>{{ number_format(abs($row['balance']), 2) }} {{ $row['account']?->drCrSuffix($row['balance']) ?? ($row['balance'] >= 0 ? __('Cr') : __('Dr')) }}</span>
                        </div>
                    @endforeach
                    <div class="flex justify-between {{ $typeStyles['asset']['text'] }} font-medium border-t border-slate-50 pt-1 mt-1">
                        <span>{{ __('Subtotal') }}</span>
                        <span>{{ number_format(abs($category['total']), 2) }}</span>
                    </div>
                </div>
            @empty
                <p class="text-slate-400">{{ __('No activity yet.') }}</p>
            @endforelse
        </div>
        <div class="flex justify-between font-bold {{ $typeStyles['asset']['text'] }} {{ $typeStyles['asset']['soft'] }} rounded-lg px-3 py-2.5 mt-3">
            <span>{{ __('Total assets') }}</span>
            <span>{{ \App\Support\Money::format($totalAssets) }}</span>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-4">{{ __('Liabilities + Equity') }}</h3>
        <div class="space-y-3 text-sm">
            @forelse ($liabilityCategories as $catLabel => $category)
                <div class="border-s-4 {{ $typeStyles['liability']['accent'] }} ps-3">
                    <span class="inline-flex rounded-full {{ $typeStyles['liability']['badge'] }} px-2 py-0.5 text-[11px] font-semibold mb-1">{{ $catLabel }}</span>
                    @foreach ($category['rows'] as $row)
                        <div class="flex justify-between text-slate-600">
                            <span>{{ $row['account']?->label() ?? $row['label'] }}</span>
                            <span>{{ number_format(abs($row['balance']), 2) }} {{ $row['account']?->drCrSuffix($row['balance']) ?? ($row['balance'] >= 0 ? __('Cr') : __('Dr')) }}</span>
                        </div>
                    @endforeach
                    <div class="flex justify-between {{ $typeStyles['liability']['text'] }} font-medium border-t border-slate-50 pt-1 mt-1">
                        <span>{{ __('Subtotal') }}</span>
                        <span>{{ number_format(abs($category['total']), 2) }}</span>
                    </div>
                </div>
            @empty
                <p class="text-slate-400">{{ __('No liabilities.') }}</p>
            @endforelse
            @foreach ($equityCategories as $catLabel => $category)
                <div class="border-s-4 {{ $typeStyles['equity']['accent'] }} ps-3">
                    <span class="inline-flex rounded-full {{ $typeStyles['equity']['badge'] }} px-2 py-0.5 text-[11px] font-semibold mb-1">{{ $catLabel }}</span>
                    @foreach ($category['rows'] as $row)
                        <div class="flex justify-between text-slate-600">
                            <span>{{ $row['account']?->label() ?? $row['label'] }}</span>
                            <span>{{ number_format(abs($row['balance']), 2) }} {{ $row['account']?->drCrSuffix($row['balance']) ?? ($row['balance'] >= 0 ? __('Cr') : __('Dr')) }}</span>
                        </div>
                    @endforeach
                    <div class="flex justify-between {{ $typeStyles['equity']['text'] }} font-medium border-t border-slate-50 pt-1 mt-1">
                        <span>{{ __('Subtotal') }}</span>
                        <span>{{ number_format(abs($category['total']), 2) }}</span>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="flex justify-between font-bold text-slate-900 bg-slate-50 rounded-lg px-3 py-2.5 mt-3">
            <span>{{ __('Total liabilities + equity') }}</span>
            <span>{{ \App\Support\Money::format($totalLiabilities + $totalEquity) }}</span>
        </div>
    </div>
</div>

<p class="mt-4 text-sm {{ $balanced ? 'text-emerald-600' : 'text-red-600' }}">
    {{ $balanced ? __('Assets equal liabilities plus equity.') : __('Assets do NOT equal liabilities plus equity — this should never happen. Please contact support.') }}
</p>
@endsection
