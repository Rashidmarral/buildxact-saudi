@extends('layouts.app')

@section('title', $purchaseReturn->return_number)

@section('content')
<div class="flex items-center justify-between mb-6 print:hidden">
    <div class="flex items-center gap-3">
        <h2 class="text-lg font-semibold text-slate-900">{{ $purchaseReturn->return_number }}</h2>
        @if ($purchaseReturn->status === 'void')
            <span class="inline-block rounded-full bg-red-50 text-red-600 text-xs font-medium px-2.5 py-1">{{ __('Void') }}</span>
        @else
            <span class="inline-block rounded-full bg-brand-50 text-brand-700 text-xs font-medium px-2.5 py-1">{{ __('Issued') }}</span>
        @endif
    </div>
    <div class="flex items-center gap-3">
        @if ($purchaseReturn->status === 'issued')
            <form method="POST" action="{{ route('app.purchase-returns.void', $purchaseReturn) }}" onsubmit="return confirm('{{ __('Void this purchase return?') }}')">
                @csrf
                <button type="submit" class="rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-600 hover:border-red-300">{{ __('Void') }}</button>
            </form>
        @endif
        <a href="{{ route('app.purchase-returns.pdf', $purchaseReturn) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
        <button onclick="window.print()" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Print / PDF') }}</button>
    </div>
</div>

<div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 print:hidden">
    {{ __('This is an internal accounting document for your own records. ZATCA e-invoicing only regulates documents you issue as the seller — your supplier is responsible for reporting their own credit note for this return.') }}
</div>

@php
    $doc = [
        'type_label' => __('Purchase Return'),
        'type_label_ar' => 'مرتجع مشتريات',
        'number' => $purchaseReturn->return_number,
        'date_label' => __('Issued'),
        'date' => $purchaseReturn->issue_date,
        'ref_no' => $purchaseReturn->bill->bill_number,
        'party_label' => __('Return to'),
        'party_label_ar' => 'المورد',
        'party' => $purchaseReturn->supplier,
        'lines' => $purchaseReturn->items,
        'subtotal' => $purchaseReturn->subtotal,
        'vat_total' => $purchaseReturn->vat_total,
        'total' => $purchaseReturn->total,
        'notes' => $purchaseReturn->reason,
    ];
@endphp
<div class="bg-white rounded-xl border border-slate-100 p-8 print:border-0 print:shadow-none">
    <p class="text-sm text-slate-500 mb-4 print:hidden">{{ __('Against bill') }}: <a href="{{ route('app.bills.show', $purchaseReturn->bill) }}" class="text-brand-700 hover:underline">{{ $purchaseReturn->bill->bill_number }}</a></p>
    @include('documents.print.body', ['doc' => $doc, 'company' => $purchaseReturn->company, 'template' => $template])
</div>
@endsection
