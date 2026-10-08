@extends('layouts.app')

@section('title', __('Payment vouchers'))

@section('content')
@include('user.bank-accounts.partials.tabs')

<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500">{{ __('Money paid out from your accounts.') }}</p>
    <a href="{{ route('app.payment-vouchers.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New payment voucher') }}</a>
</div>

<form method="GET" class="bg-white rounded-xl border border-slate-100 p-4 mb-6 flex flex-wrap items-end gap-3">
    <div>
        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Supplier') }}</label>
        <select name="supplier_id" class="mt-1 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500 min-w-[14rem]">
            <option value="">{{ __('All suppliers') }}</option>
            @foreach ($suppliers as $supplier)
                <option value="{{ $supplier->id }}" @selected(($filters['supplier_id'] ?? null) == $supplier->id)>{{ $supplier->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Client') }}</label>
        <select name="client_id" class="mt-1 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500 min-w-[14rem]">
            <option value="">{{ __('All clients') }}</option>
            @foreach ($clients as $client)
                <option value="{{ $client->id }}" @selected(($filters['client_id'] ?? null) == $client->id)>{{ $client->display_name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('From') }}</label>
        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="mt-1 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
    </div>
    <div>
        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('To') }}</label>
        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="mt-1 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
    </div>
    <button type="submit" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Apply') }}</button>
    @if (($filters['supplier_id'] ?? null) || ($filters['client_id'] ?? null))
        <a href="{{ route('app.payment-vouchers.summary-pdf', $filters) }}" class="ms-auto rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download combined PDF') }}</a>
    @endif
</form>

<div class="flex items-center justify-between mb-4 px-1">
    <p class="text-sm text-slate-500">{{ __('Total paid (filtered)') }}</p>
    <p class="text-lg font-bold text-slate-900">{{ \App\Support\Money::format($total) }}</p>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($vouchers->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No payment vouchers yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Number') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Paid to') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Account') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Amount') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($vouchers as $voucher)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50 cursor-pointer" onclick="window.location='{{ route('app.payment-vouchers.show', $voucher) }}'">
                        <td class="px-6 py-3 font-medium text-brand-700">{{ $voucher->voucher_number }}</td>
                        <td class="px-6 py-3">{{ $voucher->date->format('Y-m-d') }}</td>
                        <td class="px-6 py-3">{{ $voucher->payee_name }}</td>
                        <td class="px-6 py-3">{{ $voucher->bankAccount->name }}</td>
                        <td class="px-6 py-3">{{ \App\Support\Money::format($voucher->amount) }}</td>
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
<div class="mt-4">@include('partials.pagination', ['paginator' => $vouchers])</div>
@endsection
