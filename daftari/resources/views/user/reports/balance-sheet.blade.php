@extends('layouts.app')

@section('title', __('Balance Sheet'))

@section('content')
<div class="mb-6">
    <h2 class="text-lg font-semibold text-slate-900">{{ __('Balance Sheet') }}</h2>
    <p class="text-sm text-slate-500 mt-1">{{ __('Assets, liabilities, and equity as of the end of the selected period.') }}</p>
</div>

@include('user.reports.partials.period-selector')

<div class="grid md:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-4">{{ __('Assets') }}</h3>
        <div class="space-y-2 text-sm">
            @forelse ($assets as $row)
                <div class="flex justify-between text-slate-600"><span>{{ $row['account']->label() }}</span><span>{{ number_format($row['balance'], 2) }}</span></div>
            @empty
                <p class="text-slate-400">{{ __('No activity yet.') }}</p>
            @endforelse
        </div>
        <div class="flex justify-between font-bold text-slate-900 text-base pt-3 mt-3 border-t border-slate-200"><span>{{ __('Total assets') }}</span><span>SAR {{ number_format($totalAssets, 2) }}</span></div>
    </div>

    <div class="space-y-6">
        <div class="bg-white rounded-xl border border-slate-100 p-6">
            <h3 class="font-semibold text-slate-900 mb-4">{{ __('Liabilities') }}</h3>
            <div class="space-y-2 text-sm">
                @forelse ($liabilities as $row)
                    <div class="flex justify-between text-slate-600"><span>{{ $row['account']->label() }}</span><span>{{ number_format($row['balance'], 2) }}</span></div>
                @empty
                    <p class="text-slate-400">{{ __('No activity yet.') }}</p>
                @endforelse
            </div>
            <div class="flex justify-between font-bold text-slate-900 pt-3 mt-3 border-t border-slate-200"><span>{{ __('Total liabilities') }}</span><span>SAR {{ number_format($totalLiabilities, 2) }}</span></div>
        </div>

        <div class="bg-white rounded-xl border border-slate-100 p-6">
            <h3 class="font-semibold text-slate-900 mb-4">{{ __('Equity') }}</h3>
            <div class="space-y-2 text-sm">
                @forelse ($equity as $row)
                    <div class="flex justify-between text-slate-600"><span>{{ $row['account']?->label() ?? $row['label'] }}</span><span>{{ number_format($row['balance'], 2) }}</span></div>
                @empty
                    <p class="text-slate-400">{{ __('No activity yet.') }}</p>
                @endforelse
            </div>
            <div class="flex justify-between font-bold text-slate-900 pt-3 mt-3 border-t border-slate-200"><span>{{ __('Total equity') }}</span><span>SAR {{ number_format($totalEquity, 2) }}</span></div>
        </div>
    </div>
</div>

<div class="mt-6 rounded-xl p-6 flex items-center justify-between {{ abs($totalAssets - ($totalLiabilities + $totalEquity)) < 0.01 ? 'bg-brand-600 text-white' : 'bg-red-600 text-white' }}">
    <span class="font-semibold">{{ __('Assets = Liabilities + Equity') }}</span>
    <span class="text-sm">SAR {{ number_format($totalAssets, 2) }} = SAR {{ number_format($totalLiabilities + $totalEquity, 2) }}</span>
</div>
@endsection
