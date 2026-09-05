@extends('layouts.app')

@section('title', $voucher->voucher_number)

@section('content')
<style>
    @media print {
        @page { size: A4; margin: 12mm; }
    }
</style>
<div class="flex items-center justify-between mb-6 print:hidden">
    <div class="flex items-center gap-3">
        <h2 class="text-lg font-semibold text-slate-900">{{ $voucher->voucher_number }}</h2>
        @if ($voucher->status === 'void')
            <span class="inline-block rounded-full bg-red-50 text-red-600 text-xs font-medium px-2.5 py-1">{{ __('Void') }}</span>
        @else
            <span class="inline-block rounded-full bg-brand-50 text-brand-700 text-xs font-medium px-2.5 py-1">{{ __('Issued') }}</span>
        @endif
    </div>
    <div class="flex items-center gap-3">
        @if ($voucher->status === 'issued')
            <a href="{{ route('app.payment-vouchers.edit', $voucher) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Edit') }}</a>
            <form method="POST" action="{{ route('app.payment-vouchers.void', $voucher) }}" onsubmit="return confirm('{{ __('Void this payment voucher?') }}')">
                @csrf
                <button type="submit" class="rounded-lg border border-red-200 text-red-600 px-4 py-2 text-sm font-semibold hover:bg-red-50">{{ __('Void') }}</button>
            </form>
        @else
            <form method="POST" action="{{ route('app.payment-vouchers.destroy', $voucher) }}" onsubmit="return confirm('{{ __('Permanently delete this voided voucher? This cannot be undone.') }}')">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-lg border border-red-200 text-red-600 px-4 py-2 text-sm font-semibold hover:bg-red-50">{{ __('Delete') }}</button>
            </form>
        @endif
        <a href="{{ route('app.payment-vouchers.pdf', $voucher) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
        <button onclick="window.print()" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Print / PDF') }}</button>
    </div>
</div>

<div class="max-w-3xl bg-white rounded-xl border border-slate-100 p-8 print:border-0 print:shadow-none print:max-w-none">
    @include('documents.print.chrome-header', ['company' => $voucher->company, 'template' => $template])
    @include('documents.print.voucher-body', ['voucher' => $voucher, 'type' => 'payment'])
    @include('documents.print.chrome-footer', ['company' => $voucher->company, 'template' => $template])
</div>
@endsection
