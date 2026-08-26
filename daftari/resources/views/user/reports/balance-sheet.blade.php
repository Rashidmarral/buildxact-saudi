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
        <a href="{{ route('app.reports.balance-sheet', ['as_of' => $asOf->toDateString(), 'export' => 'pdf']) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
        <a href="{{ route('app.reports.balance-sheet', ['as_of' => $asOf->toDateString(), 'export' => 'csv']) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
    </div>
</div>

<form method="GET" class="bg-white rounded-xl border border-slate-100 p-4 mb-6 flex items-center gap-3">
    <label class="text-xs font-semibold uppercase text-slate-500">{{ __('As of') }}</label>
    <input type="date" name="as_of" value="{{ $asOf->toDateString() }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
    <button type="submit" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-300">{{ __('Apply') }}</button>
</form>

<div class="grid md:grid-cols-3 gap-6">
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-4">{{ __('Assets') }}</h3>
        <div class="space-y-2 text-sm">
            @forelse ($assets as $row)
                <div class="flex justify-between text-slate-600"><span>{{ $row['account']->label() }}</span><span>{{ number_format($row['balance'], 2) }}</span></div>
            @empty
                <p class="text-slate-400">{{ __('No activity yet.') }}</p>
            @endforelse
        </div>
        <div class="flex justify-between font-bold text-slate-900 pt-3 mt-3 border-t border-slate-200"><span>{{ __('Total assets') }}</span><span>{{ \App\Support\Money::format($totalAssets) }}</span></div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-4">{{ __('Liabilities') }}</h3>
        <div class="space-y-2 text-sm">
            @forelse ($liabilities as $row)
                <div class="flex justify-between text-slate-600"><span>{{ $row['account']->label() }}</span><span>{{ number_format($row['balance'], 2) }}</span></div>
            @empty
                <p class="text-slate-400">{{ __('No activity yet.') }}</p>
            @endforelse
        </div>
        <div class="flex justify-between font-bold text-slate-900 pt-3 mt-3 border-t border-slate-200"><span>{{ __('Total liabilities') }}</span><span>{{ \App\Support\Money::format($totalLiabilities) }}</span></div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-4">{{ __('Equity') }}</h3>
        <div class="space-y-2 text-sm">
            @forelse ($equity as $row)
                <div class="flex justify-between text-slate-600">
                    <span>{{ $row['account'] ? $row['account']->label() : ($row['key'].' - '.$row['label']) }}</span>
                    <span>{{ number_format($row['balance'], 2) }}</span>
                </div>
            @empty
                <p class="text-slate-400">{{ __('No activity yet.') }}</p>
            @endforelse
        </div>
        <div class="flex justify-between font-bold text-slate-900 pt-3 mt-3 border-t border-slate-200"><span>{{ __('Total equity') }}</span><span>{{ \App\Support\Money::format($totalEquity) }}</span></div>
    </div>
</div>
@endsection
