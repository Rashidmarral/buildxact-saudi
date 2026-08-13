@extends('layouts.app')

@section('title', $invoice->invoice_number)

@section('content')
<div class="flex items-center justify-between mb-6 print:hidden">
    <div class="flex items-center gap-3">
        <h2 class="text-lg font-semibold text-slate-900">{{ $invoice->invoice_number }}</h2>
        @include('user.invoices.partials.status-badge', ['status' => $invoice->status])
    </div>
    <div class="flex items-center gap-3">
        @if ($invoice->status === 'draft')
            <form method="POST" action="{{ route('app.invoices.send', $invoice) }}">
                @csrf
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Mark as sent') }}</button>
            </form>
            <a href="{{ route('app.invoices.edit', $invoice) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Edit') }}</a>
        @endif
        <button onclick="window.print()" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Print / PDF') }}</button>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-100 p-8 print:border-0 print:shadow-none">
    <div class="flex justify-between items-start">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">{{ $invoice->company->name }}</h1>
            @if ($invoice->company->vat_number)<p class="text-sm text-slate-500">{{ __('VAT') }}: {{ $invoice->company->vat_number }}</p>@endif
            @if ($invoice->company->address)<p class="text-sm text-slate-500">{{ $invoice->company->address }}</p>@endif
        </div>
        <div class="text-end">
            <h2 class="text-xl font-bold text-slate-900">{{ __('Tax Invoice') }}</h2>
            <p class="text-sm text-slate-500">{{ $invoice->invoice_number }}</p>
            <p class="text-sm text-slate-500">{{ __('Issued') }}: {{ $invoice->issue_date->format('Y-m-d') }}</p>
            @if ($invoice->due_date)<p class="text-sm text-slate-500">{{ __('Due') }}: {{ $invoice->due_date->format('Y-m-d') }}</p>@endif
        </div>
    </div>

    <div class="mt-8 grid grid-cols-2 gap-8">
        <div>
            <h3 class="text-xs font-semibold uppercase text-slate-400">{{ __('Bill to') }}</h3>
            <p class="mt-1 font-medium text-slate-800">{{ $invoice->client->name }}</p>
            @if ($invoice->client->vat_number)<p class="text-sm text-slate-500">{{ __('VAT') }}: {{ $invoice->client->vat_number }}</p>@endif
            @if ($invoice->client->email)<p class="text-sm text-slate-500">{{ $invoice->client->email }}</p>@endif
        </div>
        <div class="text-end">
            @if ($invoice->qr_code)
                <img src="data:image/png;base64,{{ $invoice->qr_code }}" alt="{{ __('ZATCA QR code') }}" class="ms-auto h-28 w-28" onerror="this.style.display='none'">
                <p class="mt-1 text-xs text-slate-400">{{ __('Scan to verify invoice details') }}</p>
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
            @foreach ($invoice->items as $item)
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
            <div class="flex justify-between text-slate-500"><span>{{ __('Subtotal') }}</span><span>SAR {{ number_format($invoice->subtotal, 2) }}</span></div>
            @if ($invoice->discount_total > 0)
                <div class="flex justify-between text-slate-500"><span>{{ __('Discount') }}</span><span>-SAR {{ number_format($invoice->discount_total, 2) }}</span></div>
            @endif
            <div class="flex justify-between text-slate-500"><span>{{ __('VAT') }}</span><span>SAR {{ number_format($invoice->vat_total, 2) }}</span></div>
            <div class="flex justify-between font-bold text-slate-900 text-base pt-2 border-t border-slate-200"><span>{{ __('Total') }}</span><span>SAR {{ number_format($invoice->total, 2) }}</span></div>
            <div class="flex justify-between text-slate-500"><span>{{ __('Paid') }}</span><span>SAR {{ number_format($invoice->amount_paid, 2) }}</span></div>
            <div class="flex justify-between font-semibold {{ $invoice->balanceDue() > 0 ? 'text-red-600' : 'text-brand-700' }}"><span>{{ __('Balance due') }}</span><span>SAR {{ number_format($invoice->balanceDue(), 2) }}</span></div>
        </div>
    </div>

    @if ($invoice->notes)
        <div class="mt-8 pt-4 border-t border-slate-100 text-sm text-slate-500">{{ $invoice->notes }}</div>
    @endif
</div>

<div class="mt-6 bg-white rounded-xl border border-slate-100 p-6 print:hidden">
    <h3 class="font-semibold text-slate-900 mb-4">{{ __('Payments') }}</h3>

    @if ($invoice->invoicePayments->isNotEmpty())
        <table class="w-full text-sm mb-6">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="py-2">{{ __('Date') }}</th>
                    <th class="py-2">{{ __('Method') }}</th>
                    <th class="py-2">{{ __('Reference') }}</th>
                    <th class="py-2 text-end">{{ __('Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->invoicePayments as $payment)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="py-2">{{ $payment->paid_at->format('Y-m-d') }}</td>
                        <td class="py-2">{{ $payment->method ?: '—' }}</td>
                        <td class="py-2">{{ $payment->reference ?: '—' }}</td>
                        <td class="py-2 text-end">SAR {{ number_format($payment->amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($invoice->balanceDue() > 0)
        <form method="POST" action="{{ route('app.invoices.payments.store', $invoice) }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div>
                <label class="block text-xs font-medium text-slate-500">{{ __('Amount') }}</label>
                <input type="number" step="0.01" min="0.01" max="{{ $invoice->balanceDue() }}" name="amount" value="{{ $invoice->balanceDue() }}" required class="mt-1 w-32 rounded-lg border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">{{ __('Date') }}</label>
                <input type="date" name="paid_at" value="{{ now()->toDateString() }}" required class="mt-1 rounded-lg border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">{{ __('Method') }}</label>
                <select name="method" class="mt-1 rounded-lg border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="cash">{{ __('Cash') }}</option>
                    <option value="bank_transfer">{{ __('Bank transfer') }}</option>
                    <option value="card">{{ __('Card') }}</option>
                    <option value="other">{{ __('Other') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">{{ __('Reference') }}</label>
                <input type="text" name="reference" class="mt-1 rounded-lg border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Record payment') }}</button>
        </form>
    @endif
</div>
@endsection
