@extends('layouts.app')

@section('title', __('VAT Report'))

@section('content')
<form method="GET" class="flex items-center gap-3 mb-6">
    <select name="month" onchange="this.form.submit()" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        @foreach (range(1, 12) as $m)
            <option value="{{ $m }}" @selected($month == $m)>{{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}</option>
        @endforeach
    </select>
    <select name="year" onchange="this.form.submit()" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        @foreach (range(now()->year, now()->year - 3) as $y)
            <option value="{{ $y }}" @selected($year == $y)>{{ $y }}</option>
        @endforeach
    </select>
</form>

<div class="grid sm:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900">{{ __('Sales (Output VAT)') }}</h3>
        <div class="mt-4 space-y-2 text-sm">
            <div class="flex justify-between text-slate-500"><span>{{ __('Invoices issued') }}</span><span>{{ $invoiceCount }}</span></div>
            <div class="flex justify-between text-slate-500"><span>{{ __('Sales subtotal') }}</span><span>{{ \App\Support\Money::format($salesTotal) }}</span></div>
            <div class="flex justify-between font-semibold text-slate-900 pt-2 border-t border-slate-100"><span>{{ __('Output VAT') }}</span><span>{{ \App\Support\Money::format($outputVat) }}</span></div>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900">{{ __('Purchases (Input VAT)') }}</h3>
        <div class="mt-4 space-y-2 text-sm">
            <div class="flex justify-between text-slate-500"><span>{{ __('Expenses recorded') }}</span><span>{{ $expenseCount }}</span></div>
            <div class="flex justify-between text-slate-500"><span>{{ __('Purchases total') }}</span><span>{{ \App\Support\Money::format($purchasesTotal) }}</span></div>
            <div class="flex justify-between font-semibold text-slate-900 pt-2 border-t border-slate-100"><span>{{ __('Input VAT') }}</span><span>{{ \App\Support\Money::format($inputVat) }}</span></div>
        </div>
    </div>
</div>

<div class="mt-6 bg-brand-600 text-white rounded-xl p-6 flex items-center justify-between">
    <span class="font-semibold">{{ $netVatDue >= 0 ? __('Net VAT due') : __('Net VAT refundable') }}</span>
    <span class="text-2xl font-bold">SAR {{ $company->formatNumber($netVatDue) }}</span>
</div>

<p class="mt-4 text-xs text-slate-400">{{ __('This is a summary for your own records. Verify figures against your ZATCA VAT return before filing.') }}</p>
@endsection
