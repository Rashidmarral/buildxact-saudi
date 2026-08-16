@extends('layouts.app')

@section('title', $creditNote->credit_note_number)

@section('content')
<div class="flex items-center justify-between mb-6 print:hidden">
    <div class="flex items-center gap-3">
        <h2 class="text-lg font-semibold text-slate-900">{{ $creditNote->credit_note_number }}</h2>
        @if ($creditNote->status === 'void')
            <span class="inline-block rounded-full bg-red-50 text-red-600 text-xs font-medium px-2.5 py-1">{{ __('Void') }}</span>
        @else
            <span class="inline-block rounded-full bg-brand-50 text-brand-700 text-xs font-medium px-2.5 py-1">{{ __('Issued') }}</span>
        @endif
        @if ($creditNote->isZatcaSynced())
            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                @include('partials.icon', ['name' => 'shield', 'class' => 'h-3.5 w-3.5'])
                {{ __('ZATCA synced') }}
            </span>
        @endif
    </div>
    <div class="flex items-center gap-3">
        @if ($creditNote->status === 'issued')
            <form method="POST" action="{{ route('app.credit-notes.void', $creditNote) }}" onsubmit="return confirm('{{ __('Void this credit note?') }}')">
                @csrf
                <button type="submit" class="rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-600 hover:border-red-300">{{ __('Void') }}</button>
            </form>
        @endif
        @if ($creditNote->isZatcaSynced())
            <a href="{{ route('app.credit-notes.xml', $creditNote) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download XML') }}</a>
        @endif
        <a href="{{ route('app.credit-notes.pdf', $creditNote) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
        <button onclick="window.print()" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Print / PDF') }}</button>
    </div>
</div>

@php
    $doc = [
        'type_label' => __('Credit Note'),
        'type_label_ar' => 'إشعار دائن',
        'number' => $creditNote->credit_note_number,
        'date_label' => __('Issued'),
        'date' => $creditNote->issue_date,
        'ref_no' => $creditNote->invoice->invoice_number,
        'party_label' => __('Credit to'),
        'party_label_ar' => 'العميل',
        'party' => $creditNote->client,
        'qr_code' => $creditNote->qr_code,
        'lines' => $creditNote->items,
        'subtotal' => $creditNote->subtotal,
        'vat_total' => $creditNote->vat_total,
        'total' => $creditNote->total,
        'notes' => $creditNote->reason,
    ];
@endphp
<div class="bg-white rounded-xl border border-slate-100 p-8 print:border-0 print:shadow-none">
    <p class="text-sm text-slate-500 mb-4 print:hidden">{{ __('Against invoice') }}: <a href="{{ route('app.invoices.show', $creditNote->invoice) }}" class="text-brand-700 hover:underline">{{ $creditNote->invoice->invoice_number }}</a></p>
    @include('documents.print.body', ['doc' => $doc, 'company' => $creditNote->company, 'template' => $template])
</div>
@endsection
