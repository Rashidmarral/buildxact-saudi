@extends('layouts.app')

@section('title', $deliveryNote->delivery_note_number)

@section('content')
<div class="flex items-center justify-between mb-6 print:hidden">
    <div class="flex items-center gap-3">
        <h2 class="text-lg font-semibold text-slate-900">{{ $deliveryNote->delivery_note_number }}</h2>
        @if ($deliveryNote->status === 'cancelled')
            <span class="inline-block rounded-full bg-red-50 text-red-600 text-xs font-medium px-2.5 py-1">{{ __('Cancelled') }}</span>
        @else
            <span class="inline-block rounded-full bg-brand-50 text-brand-700 text-xs font-medium px-2.5 py-1">{{ __('Issued') }}</span>
        @endif
    </div>
    <div class="flex items-center gap-3">
        @if ($deliveryNote->isIssued())
            <form method="POST" action="{{ route('app.delivery-notes.cancel', $deliveryNote) }}" onsubmit="return confirm('{{ __('Cancel this delivery note?') }}')">
                @csrf
                <button type="submit" class="rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-600 hover:border-red-300">{{ __('Cancel delivery note') }}</button>
            </form>
        @endif
        <a href="{{ route('app.delivery-notes.pdf', $deliveryNote) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
        <button onclick="window.print()" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Print / PDF') }}</button>
    </div>
</div>

@if ($deliveryNote->status === 'cancelled' && $deliveryNote->cancellation_reason)
    <div class="mb-6 rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700 print:hidden">
        <span class="font-semibold">{{ __('Cancellation reason:') }}</span> {{ $deliveryNote->cancellation_reason }}
    </div>
@endif

<div class="bg-white rounded-xl border border-slate-100 p-8 print:border-0 print:shadow-none">
    @if ($deliveryNote->invoice)
        <p class="text-sm text-slate-500 mb-4 print:hidden">{{ __('Against invoice') }}: <a href="{{ route('app.invoices.show', $deliveryNote->invoice) }}" class="text-brand-700 hover:underline">{{ $deliveryNote->invoice->invoice_number }}</a></p>
    @endif

    <div class="flex items-start justify-between gap-6">
        <div>
            @if ($deliveryNote->company->logo_path)<img src="{{ Storage::url($deliveryNote->company->logo_path) }}" alt="" class="h-14 mb-2">@endif
            <div class="text-lg font-bold text-slate-900">{{ $deliveryNote->company->name }}</div>
            @if ($deliveryNote->company->address)<div class="text-sm text-slate-500">{{ $deliveryNote->company->address }}</div>@endif
        </div>
        <div class="text-end">
            <div class="rounded border px-6 py-2 text-center" style="border-color: {{ $template->accent_color ?? '#92400e' }}; background-color: #fef9ec;">
                <div class="font-bold text-slate-900" dir="rtl">مذكرة تسليم</div>
                <div class="font-semibold text-slate-700">{{ __('Delivery Note') }}</div>
            </div>
            <div class="mt-2 text-sm text-slate-600">{{ __('No.') }} : {{ $deliveryNote->delivery_note_number }}</div>
            <div class="text-sm text-slate-500">{{ __('Date') }} : {{ $deliveryNote->issue_date->format('Y-m-d') }}</div>
        </div>
    </div>

    <div class="mt-6 grid sm:grid-cols-3 gap-4 text-sm border border-dashed border-slate-300 rounded-lg p-4">
        <div>
            <div class="text-xs text-slate-400 uppercase">{{ __('Client') }}</div>
            <div class="font-semibold text-slate-800">{{ $deliveryNote->client->name }}</div>
        </div>
        @if ($deliveryNote->driver_name)
            <div>
                <div class="text-xs text-slate-400 uppercase">{{ __('Driver') }}</div>
                <div class="font-semibold text-slate-800">{{ $deliveryNote->driver_name }}</div>
            </div>
        @endif
        @if ($deliveryNote->vehicle_number)
            <div>
                <div class="text-xs text-slate-400 uppercase">{{ __('Vehicle No.') }}</div>
                <div class="font-semibold text-slate-800">{{ $deliveryNote->vehicle_number }}</div>
            </div>
        @endif
    </div>

    <table class="w-full text-sm mt-6 border border-slate-200">
        <thead>
            <tr class="bg-slate-50 text-slate-600">
                <th class="px-3 py-2 text-left">#</th>
                <th class="px-3 py-2 text-left">{{ __('Item Code') }}</th>
                <th class="px-3 py-2 text-left">{{ __('Description') }}</th>
                <th class="px-3 py-2">{{ __('Unit') }}</th>
                <th class="px-3 py-2 text-end">{{ __('Quantity') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($deliveryNote->items as $index => $line)
                <tr class="border-t border-slate-100">
                    <td class="px-3 py-2">{{ $index + 1 }}</td>
                    <td class="px-3 py-2">{{ $line->item?->sku ?: '—' }}</td>
                    <td class="px-3 py-2">{{ $line->description }}</td>
                    <td class="px-3 py-2 text-center">{{ ($line->unit?->symbol ?: $line->unit?->nameFor(app()->getLocale())) ?? $line->item?->unit ?? '—' }}</td>
                    <td class="px-3 py-2 text-end">{{ rtrim(rtrim(number_format($line->quantity, 2), '0'), '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($deliveryNote->notes)
        <div class="mt-4 text-sm"><span class="font-semibold text-slate-700">{{ __('Notes') }}:</span> {{ $deliveryNote->notes }}</div>
    @endif

    <div class="mt-10 grid sm:grid-cols-2 gap-8 text-sm">
        <div>
            <div class="font-semibold text-slate-700">{{ __('Received By') }} :</div>
            <div class="mt-3 text-slate-500">{{ __('Name') }} : {{ $deliveryNote->received_by_name ?: '______________________' }}</div>
            <div class="mt-3 text-slate-500">{{ __('Signature') }} : ______________________</div>
        </div>
        <div class="text-end">
            <div class="font-semibold text-slate-700">{{ __('Issued By') }} :</div>
            <div class="mt-3 text-slate-500">{{ __('Name') }} : {{ $deliveryNote->creator->name ?? '______________________' }}</div>
            <div class="mt-3 text-slate-500">{{ __('Signature') }} : ______________________</div>
        </div>
    </div>
</div>
@endsection
