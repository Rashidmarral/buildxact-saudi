@extends('layouts.app')

@section('title', __('Record a VAT Return Period'))

@section('content')
<div class="max-w-2xl">
    <h1 class="text-xl font-bold text-slate-900 mb-1">{{ __('Record a VAT Return Period') }}</h1>
    <p class="text-sm text-slate-500 mb-6">{{ __('A bookkeeping record for a period you\'ve filed (or are about to file) with ZATCA — not the filing itself. If input VAT exceeds output VAT, that excess is a credit ZATCA owes you; recording it here carries it into the next period automatically instead of you having to remember it.') }}</p>

    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 mb-6">
        {{ __('The actual VAT return is filed through ZATCA\'s own portal. This page only keeps your output/input tax and the carried-forward credit straight between periods.') }}
    </div>

    <form method="GET" action="{{ route('app.reports.vat-returns.create') }}" class="flex items-end gap-3 mb-6">
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Period start') }}</label>
            <input type="date" name="period_start" value="{{ $periodStart->format('Y-m-d') }}" class="rounded-lg border border-slate-200 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Period end') }}</label>
            <input type="date" name="period_end" value="{{ $periodEnd->format('Y-m-d') }}" class="rounded-lg border border-slate-200 text-sm">
        </div>
        <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Recalculate') }}</button>
    </form>

    <form method="POST" action="{{ route('app.reports.vat-returns.store') }}" class="bg-white rounded-xl border border-slate-100 p-6 space-y-4">
        @csrf
        <input type="hidden" name="period_start" value="{{ $periodStart->format('Y-m-d') }}">
        <input type="hidden" name="period_end" value="{{ $periodEnd->format('Y-m-d') }}">

        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Period') }}</label>
            <p class="text-sm font-semibold text-slate-900">{{ $periodStart->format('Y-m-d') }} — {{ $periodEnd->format('Y-m-d') }}</p>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Output tax (sales)') }}</label>
                <p class="text-sm font-semibold text-slate-900">{{ \App\Support\Money::format($summary['outputTax']) }}</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Input tax — purchases') }}</label>
                <p class="text-sm font-semibold text-slate-900">{{ \App\Support\Money::format($summary['inputTaxPurchases']) }}</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Input tax — expenses') }}</label>
                <p class="text-sm font-semibold text-slate-900">{{ \App\Support\Money::format($summary['expenseTax']) }}</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Net recoverable input tax') }}</label>
                <p class="text-sm font-semibold text-slate-900">{{ \App\Support\Money::format($summary['netRecoverableInputTax']) }}</p>
                @if ($summary['vatApportionmentEnabled'])
                    <p class="text-xs text-slate-400 mt-1">{{ __('Apportioned at :pct% recovery — :amount disallowed as attributable to exempt supplies.', ['pct' => number_format($summary['recoveryPercentage'], 2), 'amount' => \App\Support\Money::format($summary['nonRecoverableInputTax'])]) }}</p>
                @endif
            </div>
        </div>

        <p class="text-xs text-slate-400 mt-1">{{ __('Figures above are computed from your real posted Invoices, Bills, and Expenses for this exact date range and cannot be typed over — change the dates above and recalculate instead.') }}</p>

        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Credit brought forward from the prior period') }}</label>
            <input type="number" step="0.01" min="0" name="credit_brought_forward" value="{{ old('credit_brought_forward', $creditBroughtForward) }}" class="w-full rounded-lg border border-slate-200 text-sm">
            @if ($priorPeriod)
                <p class="text-xs text-slate-400 mt-1">{{ __('Auto-filled from the prior recorded period (:from to :to).', ['from' => $priorPeriod->period_start->format('Y-m-d'), 'to' => $priorPeriod->period_end->format('Y-m-d')]) }}</p>
            @else
                <p class="text-xs text-slate-400 mt-1">{{ __('No prior period is on file — if you already carried a credit from earlier quarters filed before you started using this page, enter it here once; every period after this one will then carry forward automatically.') }}</p>
            @endif
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Notes (optional)') }}</label>
            <textarea name="notes" rows="2" class="w-full rounded-lg border border-slate-200 text-sm">{{ old('notes') }}</textarea>
        </div>

        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Record this period') }}</button>
    </form>
</div>
@endsection
