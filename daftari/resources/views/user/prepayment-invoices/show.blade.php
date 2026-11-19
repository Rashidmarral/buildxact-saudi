@extends('layouts.app')

@section('title', $prepaymentInvoice->prepayment_number)

@section('content')
<div class="flex items-center justify-between mb-6 print:hidden">
    <div class="flex items-center gap-3">
        <h2 class="text-lg font-semibold text-slate-900">{{ $prepaymentInvoice->prepayment_number }}</h2>
        @if ($prepaymentInvoice->status === 'cancelled')
            <span class="inline-block rounded-full bg-red-50 text-red-600 text-xs font-medium px-2.5 py-1">{{ __('Cancelled') }}</span>
        @elseif ($prepaymentInvoice->status === 'draft')
            <span class="inline-block rounded-full bg-slate-100 text-slate-600 text-xs font-medium px-2.5 py-1">{{ __('Draft') }}</span>
        @else
            <span class="inline-block rounded-full bg-brand-50 text-brand-700 text-xs font-medium px-2.5 py-1">{{ __('Issued') }}</span>
        @endif
        @if ($prepaymentInvoice->isZatcaSynced())
            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                @include('partials.icon', ['name' => 'shield', 'class' => 'h-3.5 w-3.5'])
                {{ __('ZATCA synced') }}
            </span>
        @elseif ($zatcaLog && $zatcaLog->status === 'failed')
            <span class="inline-block rounded-full bg-red-50 text-red-600 text-xs font-medium px-2.5 py-1">{{ __('Sync failed') }}</span>
        @endif
    </div>
    <div class="flex items-center gap-3">
        @if (! $prepaymentInvoice->isZatcaSynced() && Auth::user()->hasPermission('zatca'))
            <form method="POST" action="{{ route('app.zatca.sync.prepayment-invoice', $prepaymentInvoice) }}">
                @csrf
                <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-brand-300 hover:text-brand-600">{{ __('Sync with ZATCA') }}</button>
            </form>
        @endif
        @if ($prepaymentInvoice->isZatcaSynced())
            <a href="{{ route('app.prepayment-invoices.xml', $prepaymentInvoice) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download XML') }}</a>
        @endif
        <a href="{{ route('app.prepayment-invoices.pdf', $prepaymentInvoice) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
        @if (! $prepaymentInvoice->isZatcaSynced() && ! $prepaymentInvoice->isApplied())
            <form method="POST" action="{{ route('app.prepayment-invoices.destroy', $prepaymentInvoice) }}" onsubmit="return confirm('{{ __('Delete this prepayment invoice?') }}')">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-600 hover:border-red-300">{{ __('Delete') }}</button>
            </form>
        @endif
    </div>
</div>

@if ($zatcaLog && $zatcaLog->status === 'failed' && $zatcaLog->error_message)
    <div class="mb-6 rounded-lg bg-red-50 border border-red-100 px-4 py-3 text-sm text-red-700 print:hidden">{{ $zatcaLog->error_message }}</div>
@endif

@if ($prepaymentInvoice->appliedToInvoice)
    <div class="mb-6 rounded-lg bg-slate-50 border border-slate-100 px-4 py-3 text-sm text-slate-600 print:hidden">
        {{ __('Applied in full to invoice') }}: <a href="{{ route('app.invoices.show', $prepaymentInvoice->appliedToInvoice) }}" class="font-semibold text-brand-700 hover:underline">{{ $prepaymentInvoice->appliedToInvoice->invoice_number }}</a>
    </div>
@elseif ($prepaymentInvoice->applications->isNotEmpty())
    <div class="mb-6 bg-white rounded-xl border border-slate-100 p-5 print:hidden">
        <div class="grid sm:grid-cols-2 gap-4 mb-4">
            <div>
                <p class="text-xs text-slate-400">{{ __('Applied so far') }}</p>
                <p class="text-lg font-bold text-slate-900 mt-1">{{ \App\Support\Money::format($prepaymentInvoice->appliedTotal()) }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400">{{ __('Remaining (not yet applied)') }}</p>
                <p class="text-lg font-bold text-amber-600 mt-1">{{ \App\Support\Money::format($prepaymentInvoice->remainingTotal()) }}</p>
            </div>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="py-2">{{ __('Applied to invoice') }}</th>
                    <th class="py-2">{{ __('Date') }}</th>
                    <th class="py-2 text-end">{{ __('Amount applied') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($prepaymentInvoice->applications as $application)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="py-2"><a href="{{ route('app.invoices.show', $application->invoice) }}" class="text-brand-700 hover:underline">{{ $application->invoice->invoice_number }}</a></td>
                        <td class="py-2 text-slate-500">{{ $application->created_at->format('Y-m-d') }}</td>
                        <td class="py-2 text-end">{{ \App\Support\Money::format($application->total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@php
    $pseudoLine = (object) [
        'description' => $prepaymentInvoice->description ?: __('Advance payment'),
        'name_ar' => null,
        'quantity' => 1,
        'unit_price' => (float) $prepaymentInvoice->amount,
        'line_total' => (float) $prepaymentInvoice->amount,
        'vat_rate' => (float) $prepaymentInvoice->vat_rate,
        'vat_amount' => (float) $prepaymentInvoice->vat_amount,
        'unit' => null,
        'item' => null,
    ];

    $doc = [
        'type_label' => __('Prepayment Invoice'),
        'type_label_ar' => 'فاتورة ضريبية مقدمة',
        'number' => $prepaymentInvoice->prepayment_number,
        'date_label' => __('Issued'),
        'date' => $prepaymentInvoice->issue_date,
        'issue_time' => $prepaymentInvoice->created_at,
        'party_label' => __('Billed to'),
        'party_label_ar' => 'العميل',
        'party' => $prepaymentInvoice->client,
        'qr_code' => $prepaymentInvoice->qr_code,
        'zatca_status' => $prepaymentInvoice->zatcaInvoiceLogs()->whereIn('status', ['cleared', 'reported'])->latest('id')->value('status'),
        'lines' => [$pseudoLine],
        'subtotal' => $prepaymentInvoice->amount,
        'discount_total' => 0,
        'vat_total' => $prepaymentInvoice->vat_amount,
        'total' => $prepaymentInvoice->total,
        'notes' => $prepaymentInvoice->notes,
    ];
    $template = $prepaymentInvoice->company->defaultTemplateFor('invoice');
@endphp
<div class="bg-white rounded-xl border border-slate-100 p-8 print:border-0 print:shadow-none">
    @include('documents.print.body', ['doc' => $doc, 'company' => $prepaymentInvoice->company, 'template' => $template])
</div>
@endsection
