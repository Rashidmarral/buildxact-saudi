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
        <button onclick="window.print()" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Print / PDF') }}</button>
    </div>
</div>

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
        'vat_total' => $order->vat_total,
        'total' => $order->total,
        'notes' => $order->notes,
    ];
@endphp
<div class="bg-white rounded-xl border border-slate-100 p-8 print:border-0 print:shadow-none">
    @include('documents.print.body', ['doc' => $doc, 'company' => $order->company, 'template' => $template])
</div>

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
