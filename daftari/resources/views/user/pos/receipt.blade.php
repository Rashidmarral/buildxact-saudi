@extends('layouts.app')

@section('title', __('Sale Receipt'))

@section('content')
<div class="flex items-center justify-between mb-6 print:hidden">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ __('Sale receipt') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ $sale->sale_number }}</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('app.pos.sales.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('All sales') }}</a>
        <button type="button" onclick="window.print()" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Print') }}</button>
        @if ($sale->status === 'completed')
            <button type="button" onclick="document.getElementById('void-sale-modal').showModal()" class="rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-600 hover:border-red-300">{{ __('Void sale') }}</button>
        @endif
    </div>
</div>

@if (session('status'))
    <div class="mb-6 rounded-lg bg-emerald-50 border border-emerald-100 px-4 py-3 text-sm text-emerald-700 print:hidden">{{ session('status') }}</div>
@endif

@if ($sale->status === 'void')
    <div class="mb-6 rounded-lg bg-red-50 border border-red-100 px-4 py-3 text-sm text-red-700">
        {{ __('This sale was voided.') }} {{ $sale->void_reason }}
    </div>
@endif

<div class="max-w-md mx-auto bg-white rounded-xl border border-slate-100 p-6 font-mono text-sm">
    <div class="text-center mb-4">
        <p class="text-base font-bold text-slate-900">{{ $sale->company->name }}</p>
        @if ($sale->company->vat_number)
            <p class="text-xs text-slate-500">{{ __('VAT') }}: {{ $sale->company->vat_number }}</p>
        @endif
        <p class="text-xs text-slate-500 mt-1">{{ $sale->register->name }}</p>
        <p class="text-xs text-slate-500">{{ $sale->created_at->format('Y-m-d H:i') }}</p>
        <p class="text-xs text-slate-500">{{ __('Receipt') }}: {{ $sale->sale_number }}</p>
        @if ($sale->client)
            <p class="text-xs text-slate-500">{{ __('Client') }}: {{ $sale->client->name }}</p>
        @endif
    </div>

    <div class="border-t border-b border-dashed border-slate-300 py-3 space-y-2">
        @foreach ($sale->items as $line)
            <div class="flex justify-between gap-2">
                <span class="text-slate-800">{{ $line->item->name ?? $line->description }}</span>
            </div>
            <div class="flex justify-between text-xs text-slate-500">
                <span>{{ rtrim(rtrim(number_format($line->quantity, 3), '0'), '.') }} × {{ number_format($line->unit_price, 2) }}</span>
                <span class="tabular-nums">{{ number_format($line->line_total, 2) }}</span>
            </div>
        @endforeach
    </div>

    <div class="pt-3 space-y-1">
        <div class="flex justify-between text-slate-500"><span>{{ __('Subtotal') }}</span><span class="tabular-nums">{{ number_format($sale->subtotal, 2) }}</span></div>
        @if ($sale->discount_total > 0)
            <div class="flex justify-between text-slate-500"><span>{{ __('Discount') }}</span><span class="tabular-nums">-{{ number_format($sale->discount_total, 2) }}</span></div>
        @endif
        <div class="flex justify-between text-slate-500"><span>{{ __('VAT') }}</span><span class="tabular-nums">{{ number_format($sale->vat_total, 2) }}</span></div>
        <div class="flex justify-between text-base font-bold text-slate-900 pt-2 border-t border-dashed border-slate-300"><span>{{ __('Total') }}</span><span class="tabular-nums">{{ number_format($sale->total, 2) }}</span></div>
    </div>

    <div class="pt-3 mt-3 border-t border-dashed border-slate-300 space-y-1">
        <p class="text-xs font-semibold uppercase text-slate-400">{{ __('Payments') }}</p>
        @foreach ($sale->payments as $payment)
            <div class="flex justify-between text-slate-600">
                <span class="capitalize">{{ __(ucfirst($payment->method)) }}</span>
                <span class="tabular-nums">{{ number_format($payment->amount, 2) }}</span>
            </div>
        @endforeach
    </div>

    <div class="mt-5 text-center">
        <img src="data:image/png;base64,{{ $qr }}" alt="{{ __('ZATCA QR code') }}" class="mx-auto h-36 w-36" onerror="this.style.display='none'">
        <p class="mt-1 text-xs text-slate-400">{{ __('Scan to verify sale details') }}</p>
    </div>
</div>

<dialog id="void-sale-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-sm backdrop:bg-slate-900/40 print:hidden">
    <form method="POST" action="{{ route('app.pos.sales.void', $sale) }}" class="p-6 space-y-4">
        @csrf
        <h3 class="text-lg font-bold text-slate-900">{{ __('Void sale') }}</h3>
        <p class="text-sm text-slate-500">{{ __('This restores stock and reverses the accounting entries for this sale.') }}</p>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Reason') }}</label>
            <input type="text" name="void_reason" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div class="flex gap-3">
            <button type="button" onclick="document.getElementById('void-sale-modal').close()" class="flex-1 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600">{{ __('Cancel') }}</button>
            <button type="submit" class="flex-1 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">{{ __('Void sale') }}</button>
        </div>
    </form>
</dialog>
@endsection
