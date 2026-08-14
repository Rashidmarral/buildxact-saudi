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
        <button onclick="window.print()" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Print / PDF') }}</button>
    </div>
</div>

@php
    $accent = $template->accent_color ?? null;
    $layout = $template->layout ?? 'minimal';
    $showLogo = $template->show_logo ?? true;
@endphp
<div class="bg-white rounded-xl border border-slate-100 p-8 print:border-0 print:shadow-none">
    @if ($layout === 'bordered' && $accent)
        <div class="h-2 rounded-full mb-6 -mt-2" style="background-color: {{ $accent }}"></div>
    @endif
    <div class="flex justify-between items-start {{ $layout === 'bordered' && $accent ? 'border-s-4 ps-4' : '' }}" @if ($layout === 'bordered' && $accent) style="border-color: {{ $accent }}" @endif>
        <div class="flex items-center gap-3">
            @if ($showLogo && $quotation->company->logo_path)
                <img src="{{ Storage::url($quotation->company->logo_path) }}" alt="{{ $quotation->company->name }}" class="h-12 w-12 rounded-lg object-cover border border-slate-100">
            @endif
            <div>
                <h1 class="text-2xl font-bold text-slate-900">{{ $quotation->company->name }}</h1>
                @if ($quotation->company->vat_number)<p class="text-sm text-slate-500">{{ __('VAT') }}: {{ $quotation->company->vat_number }}</p>@endif
            </div>
        </div>
        <div class="text-end">
            <h2 class="text-xl font-bold" style="color: {{ $layout === 'minimal' && $accent ? $accent : '#0f172a' }}">{{ $quotation->type === 'proforma' ? __('Proforma Invoice') : __('Quotation') }}</h2>
            <p class="text-sm text-slate-500">{{ $quotation->quotation_number }}</p>
            <p class="text-sm text-slate-500">{{ __('Issued') }}: {{ $quotation->issue_date->format('Y-m-d') }}</p>
            @if ($quotation->expiry_date)<p class="text-sm text-slate-500">{{ __('Valid until') }}: {{ $quotation->expiry_date->format('Y-m-d') }}</p>@endif
        </div>
    </div>

    <div class="mt-8">
        <h3 class="text-xs font-semibold uppercase text-slate-400">{{ __('To') }}</h3>
        <p class="mt-1 font-medium text-slate-800">{{ $quotation->client->name }}</p>
        @if ($quotation->client->vat_number)<p class="text-sm text-slate-500">{{ __('VAT') }}: {{ $quotation->client->vat_number }}</p>@endif
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
            @foreach ($quotation->items as $item)
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
        <div class="w-full max-w-xs space-y-2 text-sm {{ $layout === 'boxed' && $accent ? 'rounded-lg p-4 text-white' : '' }}" @if ($layout === 'boxed' && $accent) style="background-color: {{ $accent }}" @endif>
            @php($boxed = $layout === 'boxed' && $accent)
            <div class="flex justify-between {{ $boxed ? 'text-white/80' : 'text-slate-500' }}"><span>{{ __('Subtotal') }}</span><span>SAR {{ number_format($quotation->subtotal, 2) }}</span></div>
            @if ($quotation->discount_total > 0)
                <div class="flex justify-between {{ $boxed ? 'text-white/80' : 'text-slate-500' }}"><span>{{ __('Discount') }}</span><span>-SAR {{ number_format($quotation->discount_total, 2) }}</span></div>
            @endif
            <div class="flex justify-between {{ $boxed ? 'text-white/80' : 'text-slate-500' }}"><span>{{ __('VAT') }}</span><span>SAR {{ number_format($quotation->vat_total, 2) }}</span></div>
            <div class="flex justify-between font-bold text-base pt-2 border-t {{ $boxed ? 'border-white/30 text-white' : 'border-slate-200 text-slate-900' }}"><span>{{ __('Total') }}</span><span>SAR {{ number_format($quotation->total, 2) }}</span></div>
        </div>
    </div>

    @if ($quotation->notes)
        <div class="mt-8 pt-4 border-t border-slate-100 text-sm text-slate-500">{{ $quotation->notes }}</div>
    @endif

    @if ($template && $template->notesFor(app()->getLocale()))
        <div class="mt-2 text-sm text-slate-400">{{ $template->notesFor(app()->getLocale()) }}</div>
    @endif
</div>
@endsection
