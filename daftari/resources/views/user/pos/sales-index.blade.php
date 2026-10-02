@extends('layouts.app')

@section('title', __('POS Sales'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ __('POS Sales') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Every sale rung up across all registers.') }}</p>
    </div>
    <a href="{{ route('app.pos.terminal') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Open checkout') }}</a>
</div>

@if (session('status'))
    <div class="mb-6 rounded-lg bg-emerald-50 border border-emerald-100 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
@endif

<div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-6 py-3 font-medium">{{ __('Sale #') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Register') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Cashier') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                <th class="px-6 py-3 font-medium text-end">{{ __('Total') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sales as $sale)
                <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                    <td class="px-6 py-3"><a href="{{ route('app.pos.sales.show', $sale) }}" class="font-medium text-brand-700 hover:underline">{{ $sale->sale_number }}</a></td>
                    <td class="px-6 py-3 text-slate-500">{{ $sale->register->name ?? '—' }}</td>
                    <td class="px-6 py-3 text-slate-500">{{ $sale->creator->name ?? '—' }}</td>
                    <td class="px-6 py-3 text-slate-500">{{ $sale->created_at->format('Y-m-d H:i') }}</td>
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
                <tr><td colspan="6" class="px-6 py-8 text-center text-slate-400">{{ __('No sales yet.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $sales->links() }}
</div>
@endsection
