@extends('layouts.app')

@section('title', $bill->bill_number)

@section('content')
<div class="flex items-center justify-between mb-6 print:hidden">
    <div class="flex items-center gap-3">
        <h2 class="text-lg font-semibold text-slate-900">{{ $bill->bill_number }}</h2>
        @include('user.bills.partials.status-badge', ['status' => $bill->status])
    </div>
    <div class="flex items-center gap-3">
        @if ($bill->status === 'draft')
            <form method="POST" action="{{ route('app.bills.post', $bill) }}">
                @csrf
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Post bill') }}</button>
            </form>
        @endif
        @if ($bill->status !== 'void')
            <form method="POST" action="{{ route('app.bills.void', $bill) }}" onsubmit="return confirm('{{ __('Void this bill?') }}')">
                @csrf
                <button type="submit" class="rounded-lg border border-red-200 text-red-600 px-4 py-2 text-sm font-semibold hover:bg-red-50">{{ __('Void') }}</button>
            </form>
        @endif
        <a href="{{ route('app.payment-vouchers.create') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Record payment') }}</a>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-100 p-8">
    <div class="flex justify-between items-start">
        <div>
            <h3 class="text-xs font-semibold uppercase text-slate-400">{{ __('Supplier') }}</h3>
            <p class="mt-1 font-medium text-slate-800">{{ $bill->supplier->name }}</p>
            @if ($bill->supplier->vat_number)<p class="text-sm text-slate-500">{{ __('VAT') }}: {{ $bill->supplier->vat_number }}</p>@endif
        </div>
        <div class="text-end">
            <p class="text-sm text-slate-500">{{ __('Bill date') }}: {{ $bill->bill_date->format('Y-m-d') }}</p>
            @if ($bill->due_date)<p class="text-sm text-slate-500">{{ __('Due date') }}: {{ $bill->due_date->format('Y-m-d') }}</p>@endif
            @if ($bill->supplier_reference)<p class="text-sm text-slate-500">{{ __('Reference') }}: {{ $bill->supplier_reference }}</p>@endif
        </div>
    </div>

    <table class="w-full text-sm mt-8">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-200">
                <th class="py-2">{{ __('Description') }}</th>
                <th class="py-2 text-end">{{ __('Qty') }}</th>
                <th class="py-2 text-end">{{ __('Unit price') }}</th>
                <th class="py-2 text-end">{{ __('VAT') }}</th>
                <th class="py-2 text-end">{{ __('Total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($bill->items as $item)
                <tr class="border-b border-slate-50">
                    <td class="py-2">{{ $item->description }}</td>
                    <td class="py-2 text-end">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
                    <td class="py-2 text-end">SAR {{ number_format($item->unit_price, 2) }}</td>
                    <td class="py-2 text-end">SAR {{ number_format($item->vat_amount, 2) }}</td>
                    <td class="py-2 text-end">SAR {{ number_format($item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-6 flex justify-end">
        <div class="w-full max-w-xs space-y-2 text-sm">
            <div class="flex justify-between text-slate-500"><span>{{ __('Subtotal') }}</span><span>SAR {{ number_format($bill->subtotal, 2) }}</span></div>
            @if ($bill->discount_total > 0)
                <div class="flex justify-between text-slate-500"><span>{{ __('Discount') }}</span><span>-SAR {{ number_format($bill->discount_total, 2) }}</span></div>
            @endif
            <div class="flex justify-between text-slate-500"><span>{{ __('VAT') }}</span><span>SAR {{ number_format($bill->vat_total, 2) }}</span></div>
            <div class="flex justify-between font-bold text-slate-900 text-base pt-2 border-t border-slate-200"><span>{{ __('Total') }}</span><span>SAR {{ number_format($bill->total, 2) }}</span></div>
            <div class="flex justify-between text-slate-500"><span>{{ __('Paid') }}</span><span>SAR {{ number_format($bill->amount_paid, 2) }}</span></div>
            <div class="flex justify-between font-semibold text-brand-700"><span>{{ __('Balance due') }}</span><span>SAR {{ number_format($bill->balanceDue(), 2) }}</span></div>
        </div>
    </div>

    @if ($bill->billPayments->isNotEmpty())
        <div class="mt-8 pt-4 border-t border-slate-100">
            <h3 class="text-sm font-semibold text-slate-900 mb-2">{{ __('Payments') }}</h3>
            <ul class="text-sm text-slate-600 space-y-1">
                @foreach ($bill->billPayments as $payment)
                    <li>{{ $payment->paid_at->format('Y-m-d') }} — SAR {{ number_format($payment->amount, 2) }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($bill->notes)
        <div class="mt-6 pt-4 border-t border-slate-100 text-sm text-slate-500">{{ $bill->notes }}</div>
    @endif
</div>
@endsection
