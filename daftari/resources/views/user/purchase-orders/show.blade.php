@extends('layouts.app')

@section('title', $order->po_number)

@section('content')
<div class="flex items-center justify-between mb-6 print:hidden">
    <div class="flex items-center gap-3">
        <h2 class="text-lg font-semibold text-slate-900">{{ $order->po_number }}</h2>
        @include('user.purchase-orders.partials.status-badge', ['status' => $order->status])
    </div>
    <div class="flex items-center gap-3">
        @if ($order->status === 'draft')
            <form method="POST" action="{{ route('app.purchase-orders.approve', $order) }}">
                @csrf
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Approve') }}</button>
            </form>
        @endif
        @if ($order->status === 'approved')
            <form method="POST" action="{{ route('app.purchase-orders.convert', $order) }}">
                @csrf
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Convert to bill') }}</button>
            </form>
        @endif
        @if (! in_array($order->status, ['converted', 'void']))
            <form method="POST" action="{{ route('app.purchase-orders.void', $order) }}" onsubmit="return confirm('{{ __('Void this purchase order?') }}')">
                @csrf
                <button type="submit" class="rounded-lg border border-red-200 text-red-600 px-4 py-2 text-sm font-semibold hover:bg-red-50">{{ __('Void') }}</button>
            </form>
        @endif
        @if ($order->status === 'converted' && $order->convertedBill)
            <a href="{{ route('app.bills.show', $order->convertedBill) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('View bill') }}</a>
        @endif
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-100 p-8">
    <div class="flex justify-between items-start">
        <div>
            <h3 class="text-xs font-semibold uppercase text-slate-400">{{ __('Supplier') }}</h3>
            <p class="mt-1 font-medium text-slate-800">{{ $order->supplier->name }}</p>
        </div>
        <div class="text-end">
            <p class="text-sm text-slate-500">{{ __('Order date') }}: {{ $order->order_date->format('Y-m-d') }}</p>
            @if ($order->expected_date)<p class="text-sm text-slate-500">{{ __('Expected') }}: {{ $order->expected_date->format('Y-m-d') }}</p>@endif
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
            @foreach ($order->items as $item)
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
            <div class="flex justify-between text-slate-500"><span>{{ __('Subtotal') }}</span><span>SAR {{ number_format($order->subtotal, 2) }}</span></div>
            @if ($order->discount_total > 0)
                <div class="flex justify-between text-slate-500"><span>{{ __('Discount') }}</span><span>-SAR {{ number_format($order->discount_total, 2) }}</span></div>
            @endif
            <div class="flex justify-between text-slate-500"><span>{{ __('VAT') }}</span><span>SAR {{ number_format($order->vat_total, 2) }}</span></div>
            <div class="flex justify-between font-bold text-slate-900 text-base pt-2 border-t border-slate-200"><span>{{ __('Total') }}</span><span>SAR {{ number_format($order->total, 2) }}</span></div>
        </div>
    </div>

    @if ($order->notes)
        <div class="mt-6 pt-4 border-t border-slate-100 text-sm text-slate-500">{{ $order->notes }}</div>
    @endif
</div>
@endsection
