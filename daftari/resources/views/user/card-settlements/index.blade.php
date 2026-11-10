@extends('layouts.app')

@section('title', __('Card/POS Settlements'))

@section('content')
@include('user.bank-accounts.partials.tabs')

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Card/POS Settlements') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Record the bank actually settling a card/POS clearing balance: fee and VAT netted off, the rest deposited.') }}</p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <a href="{{ route('app.card-settlements.index', ['export' => 'csv']) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
        <a href="{{ route('app.card-settlements.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">+ {{ __('New settlement') }}</a>
    </div>
</div>

<x-list-toolbar>
<div class="bg-white rounded-xl border border-slate-100">
    @if ($settlements->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No settlements recorded yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Payment mode') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Deposited to') }}</th>
                    <th class="px-6 py-3 font-medium text-end">{{ __('Gross') }}</th>
                    <th class="px-6 py-3 font-medium text-end">{{ __('Fee + VAT') }}</th>
                    <th class="px-6 py-3 font-medium text-end">{{ __('Net deposited') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($settlements as $settlement)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50 cursor-pointer {{ $settlement->status === 'void' ? 'opacity-60' : '' }}" onclick="window.location='{{ route('app.card-settlements.show', $settlement) }}'">
                        <td class="px-6 py-3">{{ $settlement->settlement_date->format('Y-m-d') }}</td>
                        <td class="px-6 py-3">{{ $settlement->paymentMode?->name ?? '—' }}</td>
                        <td class="px-6 py-3">{{ $settlement->bankAccount->name }}</td>
                        <td class="px-6 py-3 text-end">{{ \App\Support\Money::format($settlement->gross_amount) }}</td>
                        <td class="px-6 py-3 text-end">{{ \App\Support\Money::format($settlement->fee_amount + $settlement->fee_vat_amount) }}</td>
                        <td class="px-6 py-3 text-end font-semibold">{{ \App\Support\Money::format($settlement->net_amount) }}</td>
                        <td class="px-6 py-3">
                            @if ($settlement->status === 'void')
                                <span class="inline-block rounded-full bg-red-50 text-red-600 text-xs font-medium px-2.5 py-1">{{ __('Void') }}</span>
                            @else
                                <span class="inline-block rounded-full bg-brand-50 text-brand-700 text-xs font-medium px-2.5 py-1">{{ __('Posted') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
</x-list-toolbar>
<div class="mt-4">@include('partials.pagination', ['paginator' => $settlements])</div>
@endsection
