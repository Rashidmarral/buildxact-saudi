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
        @if ($bill->status === 'posted' && $bill->remainingReturnableTotal() > 0.01)
            <a href="{{ route('app.purchase-returns.create') }}?bill_id={{ $bill->id }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Issue purchase return') }}</a>
        @endif
        <button onclick="window.print()" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Print / PDF') }}</button>
    </div>
</div>

@php
    $doc = [
        'type_label' => __('Bill'),
        'type_label_ar' => 'فاتورة مشتريات',
        'number' => $bill->bill_number,
        'date_label' => __('Bill date'),
        'date' => $bill->bill_date,
        'date2_label' => __('Due date'),
        'date2_label_ar' => 'الاستحقاق',
        'date2' => $bill->due_date,
        'ref_no' => $bill->supplier_reference,
        'party_label' => __('Supplier'),
        'party_label_ar' => 'المورد',
        'party' => $bill->supplier,
        'lines' => $bill->items,
        'subtotal' => $bill->subtotal,
        'discount_total' => $bill->discount_total,
        'vat_total' => $bill->vat_total,
        'total' => $bill->total,
        'extra_rows' => [
            ['label' => __('Paid'), 'value' => $bill->amount_paid],
            [
                'label' => __('Balance due'), 'value' => $bill->balanceDue(), 'emphasis' => true,
                'variant' => $bill->balanceDue() > 0 ? 'red' : null,
            ],
        ],
        'notes' => $bill->notes,
    ];
@endphp
<div class="bg-white rounded-xl border border-slate-100 p-8 print:border-0 print:shadow-none">
    @include('documents.print.body', ['doc' => $doc, 'company' => $bill->company, 'template' => $template])
</div>

@if ($bill->billPayments->isNotEmpty())
    <div class="bg-white rounded-xl border border-slate-100 p-6 mt-6 print:hidden">
        <h3 class="text-sm font-semibold text-slate-900 mb-2">{{ __('Payments') }}</h3>
        <ul class="text-sm text-slate-600 space-y-1">
            @foreach ($bill->billPayments as $payment)
                <li>{{ $payment->paid_at->format('Y-m-d') }} — SAR {{ number_format($payment->amount, 2) }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="bg-white rounded-xl border border-slate-100 p-6 mt-6 print:hidden">
    <div class="flex items-center justify-between mb-4">
        <h3 class="font-semibold text-slate-900">{{ __('Attachments') }}</h3>
        <button type="button" onclick="document.getElementById('attach-file-input').click()" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('+ Attach file') }}</button>
        <form method="POST" action="{{ route('app.bills.attachments.store', $bill) }}" enctype="multipart/form-data" id="attach-file-form" class="hidden">
            @csrf
            <input type="file" name="file" id="attach-file-input" onchange="document.getElementById('attach-file-form').submit()">
        </form>
    </div>

    @if ($bill->attachments->isEmpty())
        <p class="text-sm text-slate-400">{{ __('No attachments') }}</p>
    @else
        <ul class="divide-y divide-slate-50">
            @foreach ($bill->attachments as $attachment)
                <li class="flex items-center justify-between py-2 text-sm">
                    <a href="{{ Storage::url($attachment->path) }}" target="_blank" class="text-brand-700 hover:underline">{{ $attachment->original_name }}</a>
                    <div class="flex items-center gap-3 text-slate-400">
                        <span>{{ $attachment->humanSize() }}</span>
                        <form method="POST" action="{{ route('app.bills.attachments.destroy', [$bill, $attachment]) }}" onsubmit="return confirm('{{ __('Remove this attachment?') }}')">
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
