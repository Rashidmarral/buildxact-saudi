@extends('layouts.app')

@section('title', __('Purchase & Sale Summary'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Purchase & Sale Summary') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Sales (issued invoices) and purchases (posted bills) for the selected period, side by side by month.') }}</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('app.reports.purchase-sale-summary', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
        <a href="{{ route('app.reports.purchase-sale-summary', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
    </div>
</div>

@include('user.reports.partials.period-selector')

<div class="grid sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Sales') }} ({{ $salesCount }})</p>
        <p class="text-2xl font-bold text-emerald-700 mt-1">{{ \App\Support\Money::format($salesTotal) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Purchases') }} ({{ $purchasesCount }})</p>
        <p class="text-2xl font-bold text-slate-900 mt-1">{{ \App\Support\Money::format($purchasesTotal) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Net') }}</p>
        <p class="text-2xl font-bold {{ $net >= 0 ? 'text-emerald-700' : 'text-red-600' }} mt-1">{{ \App\Support\Money::format($net) }}</p>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($rows->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No sales or purchases in this period.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Month') }}</th>
                    <th class="px-6 py-3 font-medium text-end">{{ __('Sales') }}</th>
                    <th class="px-6 py-3 font-medium text-end">{{ __('Purchases') }}</th>
                    <th class="px-6 py-3 font-medium text-end">{{ __('Net') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-6 py-3 font-medium text-slate-900">{{ $row['month']->translatedFormat('F Y') }}</td>
                        <td class="px-6 py-3 text-end text-emerald-700">{{ \App\Support\Money::format($row['sales']) }}</td>
                        <td class="px-6 py-3 text-end text-slate-700">{{ \App\Support\Money::format($row['purchases']) }}</td>
                        <td class="px-6 py-3 text-end font-medium {{ $row['net'] >= 0 ? 'text-emerald-700' : 'text-red-600' }}">{{ \App\Support\Money::format($row['net']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
