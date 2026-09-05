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
        @if ($order->status === 'pending_approval' && auth()->user()->hasPermission('approvals'))
            <form method="POST" action="{{ route('app.purchase-orders.approve', $order) }}">
                @csrf
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Approve') }}</button>
            </form>
            <button type="button" onclick="document.getElementById('po-reject-form').classList.toggle('hidden')" class="rounded-lg border border-red-200 text-red-600 px-4 py-2 text-sm font-semibold hover:bg-red-50">{{ __('Reject') }}</button>
        @endif
        @if (in_array($order->status, ['approved', 'partially_billed']))
            <a href="{{ route('app.purchase-orders.bill-form', $order) }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Bill this order') }}</a>
        @endif
        @if (! in_array($order->status, ['converted', 'partially_billed', 'void']))
            <form method="POST" action="{{ route('app.purchase-orders.void', $order) }}" onsubmit="return confirm('{{ __('Void this purchase order?') }}')">
                @csrf
                <button type="submit" class="rounded-lg border border-red-200 text-red-600 px-4 py-2 text-sm font-semibold hover:bg-red-50">{{ __('Void') }}</button>
            </form>
        @endif
        <a href="{{ route('app.purchase-orders.pdf', $order) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
        <button onclick="window.print()" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Print / PDF') }}</button>
    </div>
</div>

@if ($order->status === 'pending_approval' && auth()->user()->hasPermission('approvals'))
    <form id="po-reject-form" method="POST" action="{{ route('app.purchase-orders.reject', $order) }}" class="hidden mb-6 max-w-md rounded-xl border border-red-100 bg-red-50 p-4 print:hidden">
        @csrf
        <label class="block text-xs font-medium text-red-700 mb-1">{{ __('Reason for rejection (optional)') }}</label>
        <textarea name="rejection_reason" rows="2" class="w-full rounded-lg border border-red-200 text-sm mb-3"></textarea>
        <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">{{ __('Confirm rejection') }}</button>
    </form>
@endif

@if ($order->status === 'rejected' && $order->rejection_reason)
    <div class="mb-6 rounded-xl border border-red-100 bg-red-50 p-4 text-sm text-red-700 print:hidden">
        <span class="font-semibold">{{ __('Rejection reason:') }}</span> {{ $order->rejection_reason }}
    </div>
@endif

@php
    $doc = [
        'type_label' => __('Purchase Order'),
        'type_label_ar' => 'أمر شراء',
        'number' => $order->po_number,
        'date_label' => __('Order date'),
        'date' => $order->order_date,
        'date2_label' => __('Expected'),
        'date2_label_ar' => 'تاريخ التوريد المتوقع',
        'date2' => $order->expected_date,
        'party_label' => __('Supplier'),
        'party_label_ar' => 'المورد',
        'party' => $order->supplier,
        'lines' => $order->items,
        'subtotal' => $order->subtotal,
        'discount_total' => $order->discount_total,
        'discount_percent' => $order->discount_type === 'percentage' ? $order->discount_value : null,
        'vat_total' => $order->vat_total,
        'total' => $order->total,
        'notes' => $order->notes,
    ];
@endphp
<div class="bg-white rounded-xl border border-slate-100 p-8 print:border-0 print:shadow-none">
    @include('documents.print.body', ['doc' => $doc, 'company' => $order->company, 'template' => $template])
</div>

@if ($order->hasAnyBilledQuantity())
    <div class="bg-white rounded-xl border border-slate-100 p-6 mt-6 print:hidden">
        <h3 class="font-semibold text-slate-900 mb-4">{{ __('Billing progress') }}</h3>
        <table class="w-full text-sm mb-4">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="py-2 font-medium">{{ __('Item') }}</th>
                    <th class="py-2 font-medium text-right">{{ __('Ordered') }}</th>
                    <th class="py-2 font-medium text-right">{{ __('Billed') }}</th>
                    <th class="py-2 font-medium text-right">{{ __('Remaining') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($order->items as $item)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="py-2">{{ $item->description }}</td>
                        <td class="py-2 text-right">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
                        <td class="py-2 text-right">{{ rtrim(rtrim(number_format($item->billedQuantity(), 2), '0'), '.') }}</td>
                        <td class="py-2 text-right">{{ rtrim(rtrim(number_format($item->remainingQuantity(), 2), '0'), '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h4 class="text-xs font-semibold uppercase text-slate-400 mb-2">{{ __('Bills raised') }}</h4>
        <ul class="divide-y divide-slate-50">
            @foreach ($order->bills as $bill)
                <li class="flex items-center justify-between py-2 text-sm">
                    <a href="{{ route('app.bills.show', $bill) }}" class="text-brand-700 hover:underline">{{ $bill->bill_number }}</a>
                    <span class="text-slate-400">{{ $bill->bill_date->format('Y-m-d') }} &middot; {{ \App\Support\Money::format($bill->total) }}</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif

<div class="bg-white rounded-xl border border-slate-100 p-6 mt-6 print:hidden">
    <div class="flex items-center justify-between mb-4">
        <h3 class="font-semibold text-slate-900">{{ __('Attachments') }}</h3>
        <button type="button" onclick="document.getElementById('attach-file-input').click()" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('+ Attach file') }}</button>
        <form method="POST" action="{{ route('app.purchase-orders.attachments.store', $order) }}" enctype="multipart/form-data" id="attach-file-form" class="hidden">
            @csrf
            <input type="file" name="file" id="attach-file-input" onchange="document.getElementById('attach-file-form').submit()">
        </form>
    </div>

    @if ($order->attachments->isEmpty())
        <p class="text-sm text-slate-400">{{ __('No attachments') }}</p>
    @else
        <ul class="divide-y divide-slate-50">
            @foreach ($order->attachments as $attachment)
                <li class="flex items-center justify-between py-2 text-sm">
                    <a href="{{ Storage::url($attachment->path) }}" target="_blank" class="text-brand-700 hover:underline">{{ $attachment->original_name }}</a>
                    <div class="flex items-center gap-3 text-slate-400">
                        <span>{{ $attachment->humanSize() }}</span>
                        <form method="POST" action="{{ route('app.purchase-orders.attachments.destroy', [$order, $attachment]) }}" onsubmit="return confirm('{{ __('Remove this attachment?') }}')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">{{ __('Remove') }}</button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
