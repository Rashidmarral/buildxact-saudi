@extends('layouts.app')

@section('title', __('Payment Mode Statement'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Payment Mode Statement') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Every POS, restaurant, and repair-shop sale payment taken through a payment mode in this period, with a running total.') }}</p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <a href="{{ route('app.reports.payment-mode-statement', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
        <a href="{{ route('app.reports.payment-mode-statement', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
    </div>
</div>

<form method="GET" class="bg-white rounded-xl border border-slate-100 p-4 mb-6 flex flex-wrap items-center gap-3">
    <select name="payment_mode_id" onchange="this.form.submit()" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500 min-w-[14rem]">
        <option value="">{{ __('All payment modes') }}</option>
        @foreach ($modes as $mode)
            <option value="{{ $mode->id }}" @selected($modeId === $mode->id)>{{ $mode->name }}</option>
        @endforeach
    </select>
    <input type="date" name="from" value="{{ $period['from']->toDateString() }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
    <span class="text-slate-400 text-sm">{{ __('to') }}</span>
    <input type="date" name="to" value="{{ $period['to']->toDateString() }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
    <input type="hidden" name="period" value="custom">
    <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Filter') }}</button>
</form>

<div class="bg-white rounded-xl border border-slate-100 overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-5 py-3 font-medium">{{ __('Date') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('Sale') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('Payment mode') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Amount') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('Reference') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Running total') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-5 py-3 text-slate-500">{{ $row['date']->format('Y-m-d H:i') }}</td>
                    <td class="px-5 py-3"><a href="{{ route('app.pos.sales.show', $row['saleId']) }}" class="text-brand-700 hover:underline">{{ $row['saleNumber'] }}</a></td>
                    <td class="px-5 py-3">{{ $row['mode'] }}</td>
                    <td class="px-5 py-3 text-end">{{ number_format($row['amount'], 2) }}</td>
                    <td class="px-5 py-3 text-slate-500">{{ $row['reference'] }}</td>
                    <td class="px-5 py-3 text-end font-medium">{{ number_format($row['runningTotal'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-10 text-center text-slate-400">{{ __('No payments in this period.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
