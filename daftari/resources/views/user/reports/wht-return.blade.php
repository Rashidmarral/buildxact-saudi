@extends('layouts.app')

@section('title', __('Withholding Tax'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Withholding Tax') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Tax withheld from non-resident supplier payments in the selected period — the figures your monthly WHT return is built from.') }}</p>
    </div>
    <a href="{{ route('app.reports.wht-return', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
</div>

@include('user.reports.partials.period-selector')

<div class="bg-white rounded-xl border border-slate-100 p-5 mb-6 max-w-xs">
    <p class="text-xs text-slate-400">{{ __('Total withheld') }}</p>
    <p class="text-2xl font-bold text-slate-900 mt-1">{{ \App\Support\Money::format($total) }}</p>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-5 py-3 font-medium">{{ __('Date') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('Supplier') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('Bill') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('Category') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Rate') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Taxable base') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('WHT amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($vouchers as $voucher)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-5 py-3 text-slate-500">{{ $voucher->date->format('Y-m-d') }}</td>
                    <td class="px-5 py-3">{{ $voucher->bill?->supplier?->name ?? '—' }}</td>
                    <td class="px-5 py-3">{{ $voucher->bill?->bill_number ?? '—' }}</td>
                    <td class="px-5 py-3">{{ $voucher->bill?->whtRate?->name ?? '—' }}</td>
                    <td class="px-5 py-3 text-end">{{ number_format((float) ($voucher->bill?->whtRate?->rate ?? 0), 2) }}%</td>
                    <td class="px-5 py-3 text-end">{{ \App\Support\Money::format((float) ($voucher->bill?->subtotal ?? 0) - (float) ($voucher->bill?->discount_total ?? 0)) }}</td>
                    <td class="px-5 py-3 text-end font-medium">{{ \App\Support\Money::format($voucher->wht_amount) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-5 py-10 text-center text-slate-400">{{ __('No withholding tax in this period.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
