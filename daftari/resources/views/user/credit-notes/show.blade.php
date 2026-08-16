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

<div class="bg-white rounded-xl border border-slate-100 p-8 print:border-0 print:shadow-none">
    <div class="flex justify-between items-start">
        <div class="flex items-center gap-3">
            @if ($creditNote->company->logo_path)
                <img src="{{ Storage::url($creditNote->company->logo_path) }}" alt="{{ $creditNote->company->name }}" class="h-12 w-12 rounded-lg object-cover border border-slate-100">
            @endif
            <div>
                <h1 class="text-2xl font-bold text-slate-900">{{ $creditNote->company->name }}</h1>
                @if ($creditNote->company->vat_number)<p class="text-sm text-slate-500">{{ __('VAT') }}: {{ $creditNote->company->vat_number }}</p>@endif
            </div>
        </div>
        <div class="text-end">
            <h2 class="text-xl font-bold text-slate-900">{{ __('Credit Note') }}</h2>
            <p class="text-sm text-slate-500">{{ $creditNote->credit_note_number }}</p>
            <p class="text-sm text-slate-500">{{ __('Issued') }}: {{ $creditNote->issue_date->format('Y-m-d') }}</p>
            <p class="text-sm text-slate-500">{{ __('Against invoice') }}: <a href="{{ route('app.invoices.show', $creditNote->invoice) }}" class="text-brand-700 hover:underline">{{ $creditNote->invoice->invoice_number }}</a></p>
        </div>
    </div>

    <div class="mt-8 grid grid-cols-2 gap-8">
        <div>
            <h3 class="text-xs font-semibold uppercase text-slate-400">{{ __('Credit to') }}</h3>
            <p class="mt-1 font-medium text-slate-800">{{ $creditNote->client->name }}</p>
            @if ($creditNote->client->vat_number)<p class="text-sm text-slate-500">{{ __('VAT') }}: {{ $creditNote->client->vat_number }}</p>@endif
            @if ($creditNote->reason)<p class="text-sm text-slate-500 mt-2">{{ __('Reason') }}: {{ $creditNote->reason }}</p>@endif
        </div>
        <div class="text-end">
            @if ($creditNote->qr_code)
                <img src="data:image/png;base64,{{ $creditNote->qr_code }}" alt="{{ __('ZATCA QR code') }}" class="ms-auto h-28 w-28" onerror="this.style.display='none'">
            @endif
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
            @foreach ($creditNote->items as $item)
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
            <div class="flex justify-between text-slate-500"><span>{{ __('Subtotal') }}</span><span>SAR {{ number_format($creditNote->subtotal, 2) }}</span></div>
            <div class="flex justify-between text-slate-500"><span>{{ __('VAT') }}</span><span>SAR {{ number_format($creditNote->vat_total, 2) }}</span></div>
            <div class="flex justify-between font-bold text-slate-900 text-base border-t border-slate-100 pt-2"><span>{{ __('Total credit') }}</span><span>SAR {{ number_format($creditNote->total, 2) }}</span></div>
        </div>
    </div>
</div>
@endsection
