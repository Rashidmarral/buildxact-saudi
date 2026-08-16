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
        <button onclick="window.print()" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Print / PDF') }}</button>
    </div>
</div>

<div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 print:hidden">
    {{ __('This is an internal accounting document for your own records. ZATCA e-invoicing only regulates documents you issue as the seller — your supplier is responsible for reporting their own credit note for this return.') }}
</div>

<div class="bg-white rounded-xl border border-slate-100 p-8 print:border-0 print:shadow-none">
    <div class="flex justify-between items-start">
        <div class="flex items-center gap-3">
            @if ($purchaseReturn->company->logo_path)
                <img src="{{ Storage::url($purchaseReturn->company->logo_path) }}" alt="{{ $purchaseReturn->company->name }}" class="h-12 w-12 rounded-lg object-cover border border-slate-100">
            @endif
            <div>
                <h1 class="text-2xl font-bold text-slate-900">{{ $purchaseReturn->company->name }}</h1>
                @if ($purchaseReturn->company->vat_number)<p class="text-sm text-slate-500">{{ __('VAT') }}: {{ $purchaseReturn->company->vat_number }}</p>@endif
            </div>
        </div>
        <div class="text-end">
            <h2 class="text-xl font-bold text-slate-900">{{ __('Purchase Return') }}</h2>
            <p class="text-sm text-slate-500">{{ $purchaseReturn->return_number }}</p>
            <p class="text-sm text-slate-500">{{ __('Issued') }}: {{ $purchaseReturn->issue_date->format('Y-m-d') }}</p>
            <p class="text-sm text-slate-500">{{ __('Against bill') }}: <a href="{{ route('app.bills.show', $purchaseReturn->bill) }}" class="text-brand-700 hover:underline">{{ $purchaseReturn->bill->bill_number }}</a></p>
        </div>
    </div>

    <div class="mt-8">
        <h3 class="text-xs font-semibold uppercase text-slate-400">{{ __('Return to') }}</h3>
        <p class="mt-1 font-medium text-slate-800">{{ $purchaseReturn->supplier->name }}</p>
        @if ($purchaseReturn->supplier->vat_number)<p class="text-sm text-slate-500">{{ __('VAT') }}: {{ $purchaseReturn->supplier->vat_number }}</p>@endif
        @if ($purchaseReturn->reason)<p class="text-sm text-slate-500 mt-2">{{ __('Reason') }}: {{ $purchaseReturn->reason }}</p>@endif
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
            @foreach ($purchaseReturn->items as $item)
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
            <div class="flex justify-between text-slate-500"><span>{{ __('Subtotal') }}</span><span>SAR {{ number_format($purchaseReturn->subtotal, 2) }}</span></div>
            <div class="flex justify-between text-slate-500"><span>{{ __('VAT') }}</span><span>SAR {{ number_format($purchaseReturn->vat_total, 2) }}</span></div>
            <div class="flex justify-between font-bold text-slate-900 text-base border-t border-slate-100 pt-2"><span>{{ __('Total return') }}</span><span>SAR {{ number_format($purchaseReturn->total, 2) }}</span></div>
        </div>
    </div>
</div>
@endsection
