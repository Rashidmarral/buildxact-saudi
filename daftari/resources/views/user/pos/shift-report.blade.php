@extends('layouts.app')

@section('title', __('Shift Report'))

@section('content')
@php
    $completedSales = $shift->sales->where('status', 'completed');
    $paymentTotals = $completedSales->flatMap->payments->groupBy('method')->map(fn ($rows) => $rows->sum('amount'));
    $salesTotal = $completedSales->sum('total');
@endphp

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ __('Shift report (Z-report)') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ $shift->register->name }} · {{ $shift->opened_at->format('Y-m-d H:i') }}
            @if ($shift->closed_at) &mdash; {{ $shift->closed_at->format('Y-m-d H:i') }} @endif
        </p>
    </div>
    <a href="{{ route('app.pos-registers.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Back to registers') }}</a>
</div>

@if (session('status'))
    <div class="mb-6 rounded-lg bg-emerald-50 border border-emerald-100 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
@endif

<div class="grid md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs font-semibold uppercase text-slate-400">{{ __('Status') }}</p>
        <p class="mt-1 text-lg font-bold text-slate-900">
            @if ($shift->status === 'open')
                <span class="text-emerald-600">{{ __('Open') }}</span>
            @else
                <span class="text-slate-600">{{ __('Closed') }}</span>
            @endif
        </p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs font-semibold uppercase text-slate-400">{{ __('Sales count') }}</p>
        <p class="mt-1 text-lg font-bold text-slate-900 tabular-nums">{{ $completedSales->count() }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs font-semibold uppercase text-slate-400">{{ __('Sales total') }}</p>
        <p class="mt-1 text-lg font-bold text-slate-900 tabular-nums">{{ number_format($salesTotal, 2) }}</p>
    </div>
</div>

<div class="grid md:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h2 class="text-sm font-semibold text-slate-900 mb-4">{{ __('Payments by method') }}</h2>
        <div class="space-y-2 text-sm">
            @forelse ($paymentTotals as $method => $amount)
                <div class="flex justify-between">
                    <span class="capitalize text-slate-500">{{ __(ucfirst($method)) }}</span>
                    <span class="font-semibold text-slate-900 tabular-nums">{{ number_format($amount, 2) }}</span>
                </div>
            @empty
                <p class="text-slate-400">{{ __('No sales recorded on this shift.') }}</p>
            @endforelse
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h2 class="text-sm font-semibold text-slate-900 mb-4">{{ __('Cash reconciliation') }}</h2>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between"><span class="text-slate-500">{{ __('Opening cash') }}</span><span class="font-semibold text-slate-900 tabular-nums">{{ number_format($shift->opening_cash, 2) }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">{{ __('Cash sales') }}</span><span class="font-semibold text-slate-900 tabular-nums">{{ number_format($paymentTotals['cash'] ?? 0, 2) }}</span></div>
            @if ($shift->status === 'closed')
                <div class="flex justify-between border-t border-slate-100 pt-2"><span class="text-slate-500">{{ __('Expected cash') }}</span><span class="font-semibold text-slate-900 tabular-nums">{{ number_format($shift->expected_cash, 2) }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">{{ __('Counted cash') }}</span><span class="font-semibold text-slate-900 tabular-nums">{{ number_format($shift->counted_cash, 2) }}</span></div>
                <div class="flex justify-between border-t border-slate-100 pt-2">
                    <span class="text-slate-500">{{ __('Difference') }}</span>
                    <span class="font-bold tabular-nums {{ $shift->cash_difference == 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ number_format($shift->cash_difference, 2) }}</span>
                </div>
            @else
                <p class="text-xs text-slate-400 pt-2">{{ __('Close the shift to see the expected-vs-counted reconciliation.') }}</p>
            @endif
        </div>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-6 py-3 font-medium">{{ __('Sale #') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Time') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Cashier') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                <th class="px-6 py-3 font-medium text-end">{{ __('Total') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($shift->sales as $sale)
                <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                    <td class="px-6 py-3"><a href="{{ route('app.pos.sales.show', $sale) }}" class="font-medium text-brand-700 hover:underline">{{ $sale->sale_number }}</a></td>
                    <td class="px-6 py-3 text-slate-500">{{ $sale->created_at->format('H:i') }}</td>
                    <td class="px-6 py-3 text-slate-500">{{ $sale->creator->name ?? '—' }}</td>
                    <td class="px-6 py-3">
                        @if ($sale->status === 'void')
                            <span class="rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-semibold text-red-700">{{ __('Voided') }}</span>
                        @else
                            <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">{{ __('Completed') }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-end font-semibold text-slate-900 tabular-nums">{{ number_format($sale->total, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-6 py-8 text-center text-slate-400">{{ __('No sales on this shift.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
