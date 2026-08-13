@extends('layouts.app')

@section('title', __('Receipt vouchers'))

@section('content')
<div class="flex flex-wrap items-center gap-2 mb-6 text-sm">
    <a href="{{ route('app.bank-accounts.index') }}" class="rounded-lg px-3 py-1.5 text-slate-600 hover:bg-slate-100">{{ __('Accounts') }}</a>
    <a href="{{ route('app.receipt-vouchers.index') }}" class="rounded-lg px-3 py-1.5 bg-slate-900 text-white">{{ __('Receipt vouchers') }}</a>
    <a href="{{ route('app.payment-vouchers.index') }}" class="rounded-lg px-3 py-1.5 text-slate-600 hover:bg-slate-100">{{ __('Payment vouchers') }}</a>
    <a href="{{ route('app.bank-transfers.index') }}" class="rounded-lg px-3 py-1.5 text-slate-600 hover:bg-slate-100">{{ __('Transfers') }}</a>
</div>

<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500">{{ __('Money received into your accounts.') }}</p>
    <a href="{{ route('app.receipt-vouchers.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New receipt voucher') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($vouchers->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No receipt vouchers yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Number') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Received from') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Account') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Amount') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($vouchers as $voucher)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50 cursor-pointer" onclick="window.location='{{ route('app.receipt-vouchers.show', $voucher) }}'">
                        <td class="px-6 py-3 font-medium text-brand-700">{{ $voucher->voucher_number }}</td>
                        <td class="px-6 py-3">{{ $voucher->date->format('Y-m-d') }}</td>
                        <td class="px-6 py-3">{{ $voucher->payer_name }}</td>
                        <td class="px-6 py-3">{{ $voucher->bankAccount->name }}</td>
                        <td class="px-6 py-3">SAR {{ number_format($voucher->amount, 2) }}</td>
                        <td class="px-6 py-3">
                            @if ($voucher->status === 'void')
                                <span class="inline-block rounded-full bg-red-50 text-red-600 text-xs font-medium px-2.5 py-1">{{ __('Void') }}</span>
                            @else
                                <span class="inline-block rounded-full bg-brand-50 text-brand-700 text-xs font-medium px-2.5 py-1">{{ __('Issued') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
<div class="mt-4">{{ $vouchers->links() }}</div>
@endsection
