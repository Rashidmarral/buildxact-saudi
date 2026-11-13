@extends('layouts.app')

@section('title', __('Balance Sheet'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Balance Sheet') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Assets, liabilities, and equity') }}</p>
    </div>
    <div class="flex items-center gap-2">
        @if ($balanced)
            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-1.5 text-xs font-semibold">✓ {{ __('Balance check') }}</span>
        @else
            <span class="inline-flex items-center gap-1 rounded-full bg-red-50 text-red-700 border border-red-200 px-3 py-1.5 text-xs font-semibold">✕ {{ __('Balance check') }}</span>
        @endif
        <a href="{{ route('app.reports.balance-sheet', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
        <a href="{{ route('app.reports.balance-sheet', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
    </div>
</div>

<div class="mb-4 flex items-center gap-4 text-sm">
    <a href="{{ route('app.reports.balance-sheet-detailed', request()->except('export')) }}" class="text-brand-600 font-medium hover:underline">{{ __('Detailed (two-sided) view') }}</a>
    <a href="{{ route('app.reports.balance-sheet-summary', request()->except('export')) }}" class="text-brand-600 font-medium hover:underline">{{ __('Summary (two-sided) view') }}</a>
</div>

@php
    $categoryLabel = fn ($row) => $row['account']?->category ?? ($row['label'] ?? __('Other'));
    // Same type-color system Trial Balance established, adapted to this
    // page's 3-card layout instead of a type-grouped table.
    $typeStyles = [
        'asset' => ['accent' => 'border-blue-600', 'text' => 'text-blue-700', 'soft' => 'bg-blue-50/60', 'badge' => 'bg-blue-50 text-blue-700'],
        'liability' => ['accent' => 'border-amber-500', 'text' => 'text-amber-700', 'soft' => 'bg-amber-50/60', 'badge' => 'bg-amber-50 text-amber-700'],
        'equity' => ['accent' => 'border-violet-600', 'text' => 'text-violet-700', 'soft' => 'bg-violet-50/60', 'badge' => 'bg-violet-50 text-violet-700'],
    ];
@endphp

@php($compare = $compare ?? 'none')
<form method="GET" class="bg-white rounded-xl border border-slate-100 p-4 mb-6 flex items-center gap-3">
    <label class="text-xs font-semibold uppercase text-slate-500">{{ __('As of') }}</label>
    <input type="date" name="as_of" value="{{ $asOf->toDateString() }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
    <button type="submit" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-300">{{ __('Apply') }}</button>
    <span class="flex items-center gap-2 ms-auto">
        <label class="text-xs text-slate-400">{{ __('Compare with') }}</label>
        <select name="compare" onchange="this.form.submit()" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <option value="none" @selected($compare === 'none')>{{ __('Nothing') }}</option>
            <option value="prior_period" @selected($compare === 'prior_period')>{{ __('Prior month-end') }}</option>
            <option value="prior_year" @selected($compare === 'prior_year')>{{ __('Same date, prior year') }}</option>
        </select>
    </span>
</form>
@if ($priorAsOf)
    <p class="text-xs text-slate-400 mb-4">{{ __('Figures in parentheses are as of :date.', ['date' => $priorAsOf->format('d M Y')]) }}</p>
@endif

<div class="grid md:grid-cols-3 gap-6">
    <div class="bg-white rounded-xl border border-slate-100 border-s-4 {{ $typeStyles['asset']['accent'] }} p-6">
        <h3 class="font-semibold {{ $typeStyles['asset']['text'] }} mb-4">{{ __('Assets') }}</h3>
        <div class="space-y-3 text-sm">
            @forelse ($assets->groupBy($categoryLabel) as $catLabel => $catRows)
                <div>
                    <span class="inline-flex rounded-full {{ $typeStyles['asset']['badge'] }} px-2 py-0.5 text-[11px] font-semibold mb-1">{{ $catLabel }}</span>
                    @foreach ($catRows as $row)
                        <div class="flex justify-between text-slate-600">
                            <span>{{ $row['account']->label() }}</span>
                            <span>{{ number_format($row['balance'], 2) }}@if ($priorAsOf) <span class="text-slate-400">({{ number_format($row['priorAmount'], 2) }})</span>@endif</span>
                        </div>
                    @endforeach
                    <div class="flex justify-between {{ $typeStyles['asset']['text'] }} font-medium border-t border-slate-50 pt-1 mt-1">
                        <span>{{ __('Subtotal') }}</span>
                        <span>{{ number_format($catRows->sum('balance'), 2) }}</span>
                    </div>
                </div>
            @empty
                <p class="text-slate-400">{{ __('No activity yet.') }}</p>
            @endforelse
        </div>
        <div class="flex justify-between font-bold {{ $typeStyles['asset']['text'] }} {{ $typeStyles['asset']['soft'] }} rounded-lg px-3 py-2.5 mt-3">
            <span>{{ __('Total assets') }}</span>
            <span>{{ \App\Support\Money::format($totalAssets) }}@if ($priorAsOf) <span class="font-normal opacity-70">({{ \App\Support\Money::format($priorTotalAssets) }})</span>@endif</span>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 border-s-4 {{ $typeStyles['liability']['accent'] }} p-6">
        <h3 class="font-semibold {{ $typeStyles['liability']['text'] }} mb-4">{{ __('Liabilities') }}</h3>
        <div class="space-y-3 text-sm">
            @forelse ($liabilities->groupBy($categoryLabel) as $catLabel => $catRows)
                <div>
                    <span class="inline-flex rounded-full {{ $typeStyles['liability']['badge'] }} px-2 py-0.5 text-[11px] font-semibold mb-1">{{ $catLabel }}</span>
                    @foreach ($catRows as $row)
                        <div class="flex justify-between text-slate-600">
                            <span>{{ $row['account']->label() }}</span>
                            <span>{{ number_format($row['balance'], 2) }}@if ($priorAsOf) <span class="text-slate-400">({{ number_format($row['priorAmount'], 2) }})</span>@endif</span>
                        </div>
                    @endforeach
                    <div class="flex justify-between {{ $typeStyles['liability']['text'] }} font-medium border-t border-slate-50 pt-1 mt-1">
                        <span>{{ __('Subtotal') }}</span>
                        <span>{{ number_format($catRows->sum('balance'), 2) }}</span>
                    </div>
                </div>
            @empty
                <p class="text-slate-400">{{ __('No activity yet.') }}</p>
            @endforelse
        </div>
        <div class="flex justify-between font-bold {{ $typeStyles['liability']['text'] }} {{ $typeStyles['liability']['soft'] }} rounded-lg px-3 py-2.5 mt-3">
            <span>{{ __('Total liabilities') }}</span>
            <span>{{ \App\Support\Money::format($totalLiabilities) }}@if ($priorAsOf) <span class="font-normal opacity-70">({{ \App\Support\Money::format($priorTotalLiabilities) }})</span>@endif</span>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 border-s-4 {{ $typeStyles['equity']['accent'] }} p-6">
        <h3 class="font-semibold {{ $typeStyles['equity']['text'] }} mb-4">{{ __('Equity') }}</h3>
        <div class="space-y-3 text-sm">
            @forelse ($equity->groupBy($categoryLabel) as $catLabel => $catRows)
                <div>
                    <span class="inline-flex rounded-full {{ $typeStyles['equity']['badge'] }} px-2 py-0.5 text-[11px] font-semibold mb-1">{{ $catLabel }}</span>
                    @foreach ($catRows as $row)
                        <div class="flex justify-between text-slate-600">
                            <span>{{ $row['account'] ? $row['account']->label() : ($row['key'].' - '.$row['label']) }}</span>
                            <span>{{ number_format($row['balance'], 2) }}@if ($priorAsOf) <span class="text-slate-400">({{ number_format($row['priorAmount'], 2) }})</span>@endif</span>
                        </div>
                    @endforeach
                    <div class="flex justify-between {{ $typeStyles['equity']['text'] }} font-medium border-t border-slate-50 pt-1 mt-1">
                        <span>{{ __('Subtotal') }}</span>
                        <span>{{ number_format($catRows->sum('balance'), 2) }}</span>
                    </div>
                </div>
            @empty
                <p class="text-slate-400">{{ __('No activity yet.') }}</p>
            @endforelse
        </div>
        <div class="flex justify-between font-bold {{ $typeStyles['equity']['text'] }} {{ $typeStyles['equity']['soft'] }} rounded-lg px-3 py-2.5 mt-3">
            <span>{{ __('Total equity') }}</span>
            <span>{{ \App\Support\Money::format($totalEquity) }}@if ($priorAsOf) <span class="font-normal opacity-70">({{ \App\Support\Money::format($priorTotalEquity) }})</span>@endif</span>
        </div>
    </div>
</div>
@endsection
