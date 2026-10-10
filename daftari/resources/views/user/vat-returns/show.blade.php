@extends('layouts.app')

@section('title', __('VAT Return Period'))

@section('content')
<div class="max-w-2xl">
    <a href="{{ route('app.reports.vat-returns.index') }}" class="text-sm text-slate-400 hover:text-slate-600">{{ __('← VAT Return Filings') }}</a>
    <div class="flex items-center justify-between mt-1 mb-6">
        <h1 class="text-xl font-bold text-slate-900">{{ $period->period_start->format('Y-m-d') }} — {{ $period->period_end->format('Y-m-d') }}</h1>
        <form method="POST" action="{{ route('app.reports.vat-returns.destroy', $period) }}" onsubmit="return confirm('{{ __('Delete this period? Any later period that picked up its carried-forward credit will no longer have it auto-filled.') }}')">
            @csrf @method('DELETE')
            <button type="submit" class="text-sm text-red-600 hover:underline">{{ __('Delete') }}</button>
        </form>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-4">
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <p class="text-xs text-slate-400">{{ __('Output tax (sales)') }}</p>
                <p class="text-sm font-semibold text-slate-900 mt-1">{{ \App\Support\Money::format($period->output_tax) }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400">{{ __('Net recoverable input tax') }}</p>
                <p class="text-sm font-semibold text-slate-900 mt-1">{{ \App\Support\Money::format($period->net_recoverable_input_tax) }}</p>
                <p class="text-xs text-slate-400 mt-1">{{ __('Input tax — purchases') }}: {{ \App\Support\Money::format($period->input_tax_purchases) }} · {{ __('Input tax — expenses') }}: {{ \App\Support\Money::format($period->expense_tax) }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400">{{ __('Credit brought forward') }}</p>
                <p class="text-sm font-semibold text-slate-900 mt-1">{{ \App\Support\Money::format($period->credit_brought_forward) }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400">{{ __('Net position before carry-forward') }}</p>
                <p class="text-sm font-semibold text-slate-900 mt-1">{{ \App\Support\Money::format($period->output_tax - $period->net_recoverable_input_tax) }}</p>
            </div>
        </div>

        <div class="border-t border-slate-100 pt-4 grid sm:grid-cols-2 gap-4">
            <div>
                <p class="text-xs text-slate-400">{{ __('Amount payable') }}</p>
                <p class="text-xl font-bold {{ $period->amount_payable > 0 ? 'text-red-600' : 'text-slate-400' }} mt-1">{{ \App\Support\Money::format($period->amount_payable) }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400">{{ __('Credit carried forward to next period') }}</p>
                <p class="text-xl font-bold {{ $period->credit_carried_forward > 0 ? 'text-emerald-600' : 'text-slate-400' }} mt-1">{{ \App\Support\Money::format($period->credit_carried_forward) }}</p>
            </div>
        </div>

        @if ($period->notes)
            <div class="border-t border-slate-100 pt-4">
                <p class="text-xs text-slate-400 mb-1">{{ __('Notes') }}</p>
                <p class="text-sm text-slate-700 whitespace-pre-line">{{ $period->notes }}</p>
            </div>
        @endif

        <p class="text-xs text-slate-400 border-t border-slate-100 pt-4">{{ __('Recorded by :name on :date.', ['name' => $period->creator?->name ?: '—', 'date' => $period->created_at->format('Y-m-d')]) }}</p>
    </div>
</div>
@endsection
