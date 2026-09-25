@extends('layouts.app')

@section('title', __('Cash Flow'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Cash Flow') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Where cash actually came from and went — operating, investing, and financing activities for the period.') }}</p>
    </div>
    <div class="flex items-center gap-2">
        @if ($reconciled)
            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-1.5 text-xs font-semibold">✓ {{ __('Reconciled to cash & bank') }}</span>
        @else
            <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200 px-3 py-1.5 text-xs font-semibold" title="{{ __('A credit-financed transaction (e.g. an asset bought on account) can cause a legitimate small gap — this is informational, not an error.') }}">! {{ __('Reconciliation gap') }}</span>
        @endif
        <a href="{{ route('app.reports.cash-flow', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
    </div>
</div>

@include('user.reports.partials.period-selector')

<div class="grid sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-100 p-5"><p class="text-xs text-slate-400">{{ __('Opening balance') }}</p><p class="text-xl font-bold text-slate-900 mt-1">{{ \App\Support\Money::format($openingCash) }}</p></div>
    <div class="bg-white rounded-xl border border-slate-100 p-5"><p class="text-xs text-slate-400">{{ __('Net change in cash') }}</p><p class="text-xl font-bold {{ $netChange >= 0 ? 'text-emerald-600' : 'text-red-600' }} mt-1">{{ \App\Support\Money::format($netChange) }}</p></div>
    <div class="bg-white rounded-xl border border-slate-100 p-5"><p class="text-xs text-slate-400">{{ __('Closing balance') }}</p><p class="text-xl font-bold text-slate-900 mt-1">{{ \App\Support\Money::format($closingCash) }}</p></div>
    <div class="bg-white rounded-xl border border-slate-100 p-5"><p class="text-xs text-slate-400">{{ __('Actual cash & bank balance') }}</p><p class="text-xl font-bold text-slate-900 mt-1">{{ \App\Support\Money::format($actualClosingCash) }}</p></div>
</div>

<div class="grid lg:grid-cols-3 gap-6">
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-1">{{ __('Operating activities') }}</h3>
        <p class="text-xs text-slate-400 mb-4">{{ __('Net income, adjusted for non-cash items and working-capital swings.') }}</p>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between text-slate-600"><span>{{ __('Net income') }}</span><span class="tabular-nums">{{ number_format($netIncome, 2) }}</span></div>
            @if (abs($depreciation) > 0.005)
                <div class="flex justify-between text-slate-600"><span>{{ __('Depreciation') }}</span><span class="tabular-nums">{{ number_format($depreciation, 2) }}</span></div>
            @endif
            @forelse ($workingCapitalLines as $row)
                <div class="flex justify-between text-slate-600">
                    <span>{{ $row['account']->label() }}</span>
                    <span class="tabular-nums {{ $row['cashEffect'] < 0 ? 'text-red-600' : '' }}">{{ number_format($row['cashEffect'], 2) }}</span>
                </div>
            @empty
                <p class="text-slate-400">{{ __('No working-capital movement this period.') }}</p>
            @endforelse
        </div>
        <div class="flex justify-between font-bold text-slate-900 pt-3 mt-3 border-t border-slate-200"><span>{{ __('Net cash from operating activities') }}</span><span>{{ \App\Support\Money::format($operatingTotal) }}</span></div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-1">{{ __('Investing activities') }}</h3>
        <p class="text-xs text-slate-400 mb-4">{{ __('Fixed assets bought or sold, from the Fixed Asset register.') }}</p>
        <div class="space-y-2 text-sm">
            @if (abs($acquisitions) > 0.005)
                <div class="flex justify-between text-slate-600"><span>{{ __('Fixed asset acquisitions') }}</span><span class="tabular-nums text-red-600">{{ number_format(-$acquisitions, 2) }}</span></div>
            @endif
            @if (abs($disposalProceeds) > 0.005)
                <div class="flex justify-between text-slate-600"><span>{{ __('Fixed asset disposal proceeds') }}</span><span class="tabular-nums">{{ number_format($disposalProceeds, 2) }}</span></div>
            @endif
            @if (abs($acquisitions) <= 0.005 && abs($disposalProceeds) <= 0.005)
                <p class="text-slate-400">{{ __('No fixed asset activity this period.') }}</p>
            @endif
        </div>
        <div class="flex justify-between font-bold text-slate-900 pt-3 mt-3 border-t border-slate-200"><span>{{ __('Net cash from investing activities') }}</span><span>{{ \App\Support\Money::format($investingTotal) }}</span></div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-1">{{ __('Financing activities') }}</h3>
        <p class="text-xs text-slate-400 mb-4">{{ __('Owner capital contributions and drawings.') }}</p>
        <div class="space-y-2 text-sm">
            @forelse ($equityLines as $row)
                <div class="flex justify-between text-slate-600">
                    <span>{{ $row['account']->label() }}</span>
                    <span class="tabular-nums {{ $row['change'] < 0 ? 'text-red-600' : '' }}">{{ number_format($row['change'], 2) }}</span>
                </div>
            @empty
                <p class="text-slate-400">{{ __('No equity movement this period.') }}</p>
            @endforelse
        </div>
        <div class="flex justify-between font-bold text-slate-900 pt-3 mt-3 border-t border-slate-200"><span>{{ __('Net cash from financing activities') }}</span><span>{{ \App\Support\Money::format($financingTotal) }}</span></div>
    </div>
</div>
@endsection
