@extends('layouts.app')

@section('title', $quotation->quotation_number)

@section('content')
<div class="flex items-center justify-between mb-6 print:hidden">
    <div class="flex items-center gap-3">
        <h2 class="text-lg font-semibold text-slate-900">{{ $quotation->quotation_number }}</h2>
        @include('user.quotations.partials.status-badge', ['status' => $quotation->status])
    </div>
    <div class="flex items-center gap-3">
        @if ($quotation->status === 'draft')
            <form method="POST" action="{{ route('app.quotations.send', $quotation) }}">
                @csrf
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Mark as issued') }}</button>
            </form>
            <a href="{{ route('app.quotations.edit', $quotation) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Edit') }}</a>
        @endif
        @if (in_array($quotation->status, ['draft', 'issued']))
            <form method="POST" action="{{ route('app.quotations.accept', $quotation) }}">
                @csrf
                <button type="submit" class="rounded-lg border border-brand-200 text-brand-700 px-4 py-2 text-sm font-semibold hover:bg-brand-50">{{ __('Mark as accepted') }}</button>
            </form>
            <form method="POST" action="{{ route('app.quotations.reject', $quotation) }}">
                @csrf
                <button type="submit" class="rounded-lg border border-red-200 text-red-600 px-4 py-2 text-sm font-semibold hover:bg-red-50">{{ __('Mark as rejected') }}</button>
            </form>
        @endif
        @if ($quotation->status === 'accepted')
            <form method="POST" action="{{ route('app.quotations.convert', $quotation) }}">
                @csrf
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Convert to invoice') }}</button>
            </form>
        @endif
        @if ($quotation->status === 'converted' && $quotation->convertedInvoice)
            <a href="{{ route('app.invoices.show', $quotation->convertedInvoice) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('View invoice') }}</a>
        @endif
        <a href="{{ route('app.quotations.pdf', $quotation) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
        @if ($quotation->client->email)
            <form method="POST" action="{{ route('app.quotations.email', $quotation) }}">
                @csrf
                <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Email to client') }}</button>
            </form>
        @endif
        <button onclick="window.print()" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Print / PDF') }}</button>
    </div>
</div>

@php
    $doc = [
        'type_label' => $quotation->type === 'proforma' ? __('Proforma Invoice') : __('Quotation'),
        'type_label_ar' => $quotation->type === 'proforma' ? 'فاتورة أولية' : 'عرض سعر',
        'number' => $quotation->quotation_number,
        'date_label' => __('Issued'),
        'date' => $quotation->issue_date,
        'date2_label' => __('Valid until'),
        'date2_label_ar' => 'صالح حتى',
        'date2' => $quotation->expiry_date,
        'party_label' => __('To'),
        'party_label_ar' => 'العميل',
        'party' => $quotation->client,
        'lines' => $quotation->items,
        'subtotal' => $quotation->subtotal,
        'discount_total' => $quotation->discount_total,
        'vat_total' => $quotation->vat_total,
        'total' => $quotation->total,
        'bank_account' => $quotation->bankAccount,
        'salesperson' => $quotation->salesperson,
        'notes' => $quotation->notes,
    ];
@endphp
<div class="bg-white rounded-xl border border-slate-100 p-8 print:border-0 print:shadow-none">
    @include('documents.print.body', ['doc' => $doc, 'company' => $quotation->company, 'template' => $template])
</div>

<div class="mt-6 bg-white rounded-xl border border-slate-100 p-6 print:hidden">
    <div class="flex items-center justify-between mb-4">
        <h3 class="font-semibold text-slate-900">{{ __('Attachments') }}</h3>
        <button type="button" onclick="document.getElementById('attach-file-input').click()" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('+ Attach file') }}</button>
        <form method="POST" action="{{ route('app.quotations.attachments.store', $quotation) }}" enctype="multipart/form-data" id="attach-file-form" class="hidden">
            @csrf
            <input type="file" name="file" id="attach-file-input" onchange="document.getElementById('attach-file-form').submit()">
        </form>
    </div>

    @if ($quotation->attachments->isEmpty())
        <p class="text-sm text-slate-400">{{ __('No attachments') }}</p>
    @else
        <ul class="divide-y divide-slate-50">
            @foreach ($quotation->attachments as $attachment)
                <li class="flex items-center justify-between py-2 text-sm">
                    <a href="{{ Storage::url($attachment->path) }}" target="_blank" class="text-brand-700 hover:underline">{{ $attachment->original_name }}</a>
                    <div class="flex items-center gap-3 text-slate-400">
                        <span>{{ $attachment->humanSize() }}</span>
                        <form method="POST" action="{{ route('app.quotations.attachments.destroy', [$quotation, $attachment]) }}" onsubmit="return confirm('{{ __('Remove this attachment?') }}')">
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
