@extends('layouts.app')

@section('title', __('Settlement'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div class="flex items-center gap-3">
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Card/POS Settlement') }}</h2>
        <span class="inline-block rounded-full bg-slate-100 text-slate-600 text-xs font-medium px-2.5 py-1">{{ $settlement->settlement_date->format('Y-m-d') }}</span>
        @if ($settlement->status === 'void')
            <span class="inline-block rounded-full bg-red-50 text-red-600 text-xs font-medium px-2.5 py-1">{{ __('Void') }}</span>
        @else
            <span class="inline-block rounded-full bg-brand-50 text-brand-700 text-xs font-medium px-2.5 py-1">{{ __('Posted') }}</span>
        @endif
    </div>
    <div class="flex items-center gap-3">
        <a href="{{ route('app.card-settlements.pdf', $settlement) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
        @if ($settlement->status === 'posted')
            <form method="POST" action="{{ route('app.card-settlements.void', $settlement) }}" onsubmit="return confirm('{{ __('Void this settlement? This reverses its ledger entry and removes it from reports — it stays visible, marked as void.') }}')">
                @csrf
                <button type="submit" class="rounded-lg border border-red-200 text-red-600 px-4 py-2 text-sm font-semibold hover:bg-red-50">{{ __('Void') }}</button>
            </form>
        @else
            <form method="POST" action="{{ route('app.card-settlements.destroy', $settlement) }}" onsubmit="return confirm('{{ __('Permanently delete this settlement? This cannot be undone.') }}')">
                @csrf @method('DELETE')
                <button type="submit" class="rounded-lg border border-red-200 text-red-600 px-4 py-2 text-sm font-semibold hover:bg-red-50">{{ __('Delete') }}</button>
            </form>
        @endif
        <a href="{{ route('app.card-settlements.index') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('← Back') }}</a>
    </div>
</div>

<div class="max-w-2xl bg-white rounded-xl border border-slate-100 p-6">
    <div class="grid sm:grid-cols-2 gap-6">
        <div>
            <p class="text-xs font-semibold uppercase text-slate-500">{{ __('Payment mode') }}</p>
            <p class="mt-1 font-medium text-slate-900">{{ $settlement->paymentMode?->name ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase text-slate-500">{{ __('Deposited to') }}</p>
            <p class="mt-1 font-medium text-slate-900">{{ $settlement->bankAccount->name }}</p>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase text-slate-500">{{ __('Gross amount') }}</p>
            <p class="mt-1 font-medium text-slate-900">{{ \App\Support\Money::format($settlement->gross_amount) }}</p>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase text-slate-500">{{ __('Fee (MDR) + VAT') }}</p>
            <p class="mt-1 font-medium text-slate-900">{{ \App\Support\Money::format($settlement->fee_amount) }} + {{ \App\Support\Money::format($settlement->fee_vat_amount) }}</p>
        </div>
        <div class="sm:col-span-2">
            <p class="text-xs font-semibold uppercase text-slate-500">{{ __('Net deposited') }}</p>
            <p class="mt-1 text-lg font-bold text-slate-900">{{ \App\Support\Money::format($settlement->net_amount) }}</p>
        </div>
        @if ($settlement->reference)
            <div>
                <p class="text-xs font-semibold uppercase text-slate-500">{{ __('Reference') }}</p>
                <p class="mt-1 font-medium text-slate-900">{{ $settlement->reference }}</p>
            </div>
        @endif
        <div>
            <p class="text-xs font-semibold uppercase text-slate-500">{{ __('Recorded by') }}</p>
            <p class="mt-1 font-medium text-slate-900">{{ $settlement->creator?->name ?? '—' }}</p>
        </div>
        @if ($settlement->notes)
            <div class="sm:col-span-2">
                <p class="text-xs font-semibold uppercase text-slate-500">{{ __('Notes') }}</p>
                <p class="mt-1 text-slate-700">{{ $settlement->notes }}</p>
            </div>
        @endif
    </div>
</div>
@endsection
