@extends('layouts.app')

@section('title', $debitNote->debit_note_number)

@section('content')
<div class="flex items-center justify-between mb-6 print:hidden">
    <div class="flex items-center gap-3">
        <h2 class="text-lg font-semibold text-slate-900">{{ $debitNote->debit_note_number }}</h2>
        @if ($debitNote->status === 'void')
            <span class="inline-block rounded-full bg-red-50 text-red-600 text-xs font-medium px-2.5 py-1">{{ __('Void') }}</span>
        @else
            <span class="inline-block rounded-full bg-brand-50 text-brand-700 text-xs font-medium px-2.5 py-1">{{ __('Issued') }}</span>
        @endif
        @if ($debitNote->isZatcaSynced())
            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                @include('partials.icon', ['name' => 'shield', 'class' => 'h-3.5 w-3.5'])
                {{ __('ZATCA synced') }}
            </span>
        @endif
    </div>
    <div class="flex items-center gap-3">
        @if ($debitNote->status === 'issued')
            <form method="POST" action="{{ route('app.debit-notes.void', $debitNote) }}" onsubmit="return confirm('{{ __('Void this debit note?') }}')">
                @csrf
                <button type="submit" class="rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-600 hover:border-red-300">{{ __('Void') }}</button>
            </form>
        @endif
        @if ($debitNote->isZatcaSynced())
            <a href="{{ route('app.debit-notes.xml', $debitNote) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download XML') }}</a>
        @endif
        <a href="{{ route('app.debit-notes.pdf', $debitNote) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
        <button onclick="window.print()" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Print / PDF') }}</button>
    </div>
</div>

@php
    $doc = [
        'type_label' => __('Debit Note'),
        'type_label_ar' => 'إشعار مدين',
        'number' => $debitNote->debit_note_number,
        'date_label' => __('Issued'),
        'date' => $debitNote->issue_date,
        'ref_no' => $debitNote->invoice->invoice_number,
        'party_label' => __('Debit to'),
        'party_label_ar' => 'العميل',
        'party' => $debitNote->client,
        'qr_code' => $debitNote->qr_code,
        'zatca_status' => $debitNote->zatcaDebitNoteLogs()->whereIn('status', ['cleared', 'reported'])->latest('id')->value('status'),
        'lines' => $debitNote->items,
        'subtotal' => $debitNote->subtotal,
        'vat_total' => $debitNote->vat_total,
        'total' => $debitNote->total,
        'notes' => $debitNote->reason,
    ];
@endphp
<div class="bg-white rounded-xl border border-slate-100 p-8 print:border-0 print:shadow-none">
    <p class="text-sm text-slate-500 mb-4 print:hidden">{{ __('Against invoice') }}: <a href="{{ route('app.invoices.show', $debitNote->invoice) }}" class="text-brand-700 hover:underline">{{ $debitNote->invoice->invoice_number }}</a></p>
    @include('documents.print.body', ['doc' => $doc, 'company' => $debitNote->company, 'template' => $template])
</div>
@endsection
