@extends('layouts.app')

@section('title', $voucher->voucher_number)

@section('content')
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
            <form method="POST" action="{{ route('app.payment-vouchers.void', $voucher) }}" onsubmit="return confirm('{{ __('Void this payment voucher?') }}')">
                @csrf
                <button type="submit" class="rounded-lg border border-red-200 text-red-600 px-4 py-2 text-sm font-semibold hover:bg-red-50">{{ __('Void') }}</button>
            </form>
        @endif
        <button onclick="window.print()" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Print / PDF') }}</button>
    </div>
</div>

<div class="max-w-xl bg-white rounded-xl border border-slate-100 p-8">
    <div class="flex justify-between items-start border-b border-slate-100 pb-4">
        <h2 class="text-xl font-bold text-slate-900">{{ __('Payment Voucher') }}</h2>
        <div class="text-end text-xs text-slate-500">
            <div>{{ __('No.') }}: {{ $voucher->voucher_number }}</div>
            <div>{{ __('Date') }}: {{ $voucher->date->format('Y-m-d') }}</div>
        </div>
    </div>
    <div class="mt-6 space-y-4 text-sm">
        <div><span class="text-slate-400">{{ __('Paid to:') }}</span> <span class="font-medium text-slate-800">{{ $voucher->payee_name }}</span> @if ($voucher->party_type !== 'manual')<span class="ms-1 text-xs text-slate-400">({{ ucfirst($voucher->party_type) }})</span>@endif</div>
        @if ($voucher->party_vat_number)<div><span class="text-slate-400">{{ __('VAT number:') }}</span> <span class="font-medium text-slate-800">{{ $voucher->party_vat_number }}</span></div>@endif
        <div><span class="text-slate-400">{{ __('Amount:') }}</span> <span class="font-bold text-slate-900">SAR {{ number_format($voucher->amount, 2) }}</span></div>
        <div><span class="text-slate-400">{{ __('Account:') }}</span> <span class="font-medium text-slate-800">{{ $voucher->bankAccount->name }}</span></div>
        @if ($voucher->counterAccount)<div><span class="text-slate-400">{{ __('Counter account:') }}</span> <span class="font-medium text-slate-800">{{ $voucher->counterAccount->label() }}</span></div>@endif
        <div><span class="text-slate-400">{{ __('Payment method:') }}</span> <span class="font-medium text-slate-800">{{ ucfirst(str_replace('_', ' ', $voucher->method)) }}</span></div>
        @if ($voucher->bill)
            <div><span class="text-slate-400">{{ __('Applied to bill:') }}</span> <a href="{{ route('app.bills.show', $voucher->bill) }}" class="font-medium text-brand-700 hover:underline">{{ $voucher->bill->bill_number }}</a></div>
        @endif
        @if ($voucher->expense)
            <div><span class="text-slate-400">{{ __('Related expense:') }}</span> <span class="font-medium text-slate-800">{{ $voucher->expense->vendor_name }} — {{ $voucher->expense->description }}</span></div>
        @endif
        @if ($voucher->reference)
            <div><span class="text-slate-400">{{ __('Reference:') }}</span> <span class="font-medium text-slate-800">{{ $voucher->reference }}</span></div>
        @endif
        @if ($voucher->notes)
            <div><span class="text-slate-400">{{ __('Notes:') }}</span> <span class="font-medium text-slate-800">{{ $voucher->notes }}</span></div>
        @endif
    </div>
    <div class="mt-10 pt-6 border-t border-slate-100 grid grid-cols-2 text-xs text-slate-400 text-center">
        <div>{{ __('Paid by') }}</div>
        <div>{{ __('Authorized signature') }}</div>
    </div>
</div>
@endsection
