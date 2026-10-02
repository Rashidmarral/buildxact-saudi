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
        <a href="{{ route('app.pos.sales.pdf', $sale) }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
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

@include('documents.print.pos-receipt-body', ['sale' => $sale, 'qr' => $qr, 'layout' => $layout ?? 'receipt_compact', 'languageMode' => $languageMode ?? 'bilingual'])

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
