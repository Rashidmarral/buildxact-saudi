@extends('layouts.app')

@section('title', __('Dashboard'))

@section('content')
@php($pendingSteps = collect($checklist)->reject(fn ($step) => $step['done']))
@if ($pendingSteps->isNotEmpty())
    <div class="mb-6 rounded-2xl border border-slate-100 bg-white p-6 shadow-card">
        <h2 class="mb-1 font-semibold text-slate-900">{{ __('Get started in a few steps') }}</h2>
        <p class="mb-4 text-sm text-slate-500">{{ __('A quick checklist to get your books set up.') }}</p>
        <div class="grid gap-2 sm:grid-cols-2">
            @foreach ($checklist as $step)
                <a href="{{ route($step['route']) }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition-colors {{ $step['done'] ? 'text-slate-400' : 'text-slate-700 hover:bg-slate-50' }}">
                    <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-xs {{ $step['done'] ? 'bg-brand-500 text-white' : 'border-2 border-slate-200' }}">
                        @if ($step['done'])
                            @include('partials.icon', ['name' => 'check-circle', 'class' => 'h-3.5 w-3.5'])
                        @endif
                    </span>
                    <span class="{{ $step['done'] ? 'line-through' : '' }}">{{ $step['label'] }}</span>
                </a>
            @endforeach
        </div>
    </div>
@endif

<div class="grid grid-cols-2 gap-4 sm:gap-5 lg:grid-cols-5">
    @foreach ([
        ['label' => __('Total invoiced'), 'value' => 'SAR '.number_format($stats['total_invoiced'], 2), 'icon' => 'sales', 'accent' => 'from-brand-500 to-emerald-500'],
        ['label' => __('Outstanding'), 'value' => 'SAR '.number_format($stats['total_outstanding'], 2), 'icon' => 'clock', 'accent' => 'from-amber-500 to-orange-500'],
        ['label' => __('Paid this month'), 'value' => 'SAR '.number_format($stats['total_paid_this_month'], 2), 'icon' => 'check-circle', 'accent' => 'from-teal-500 to-cyan-500'],
        ['label' => __('Expenses this month'), 'value' => 'SAR '.number_format($stats['total_expenses_this_month'], 2), 'icon' => 'purchases', 'accent' => 'from-rose-500 to-red-500'],
        ['label' => __('Open quotations'), 'value' => $stats['open_quotations'], 'icon' => 'clipboard', 'accent' => 'from-violet-500 to-purple-500'],
    ] as $card)
        <div class="card-hover rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-slate-500">{{ $card['label'] }}</span>
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br {{ $card['accent'] }} text-white">
                    @include('partials.icon', ['name' => $card['icon'], 'class' => 'h-4 w-4'])
                </span>
            </div>
            <div class="mt-3 text-xl font-bold text-slate-900 sm:text-2xl">{{ $card['value'] }}</div>
        </div>
    @endforeach
</div>

<div class="mt-6 rounded-2xl border border-slate-100 bg-white p-6 shadow-card">
    <h2 class="mb-1 font-semibold text-slate-900">{{ __('Receivables aging') }}</h2>
    <p class="mb-4 text-sm text-slate-500">{{ __('Outstanding invoice balances by how overdue they are.') }}</p>
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        @foreach ([
            'current' => __('Current'),
            '1_30' => __('1–30 days'),
            '31_60' => __('31–60 days'),
            '61_plus' => __('61+ days'),
        ] as $key => $label)
            <div class="rounded-xl {{ $key === 'current' ? 'bg-slate-50' : ($aging[$key] > 0 ? 'bg-amber-50' : 'bg-slate-50') }} p-4 text-center transition-colors">
                <div class="text-xs text-slate-500">{{ $label }}</div>
                <div class="mt-1 text-lg font-bold {{ $key !== 'current' && $aging[$key] > 0 ? 'text-amber-700' : 'text-slate-900' }}">SAR {{ number_format($aging[$key], 2) }}</div>
            </div>
        @endforeach
    </div>
</div>

<div class="mt-6 overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-card">
    <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
        <h2 class="font-semibold text-slate-900">{{ __('Recent invoices') }}</h2>
        <a href="{{ route('app.invoices.create') }}" class="flex items-center gap-1 text-sm font-semibold text-brand-700 hover:underline">
            @include('partials.icon', ['name' => 'plus', 'class' => 'h-3.5 w-3.5'])
            {{ __('New invoice') }}
        </a>
    </div>
    @if ($recentInvoices->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No invoices yet. Create your first invoice to get started.') }}</p>
    @else
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 text-left text-slate-500">
                    <th class="px-6 py-3 font-medium">{{ __('Invoice') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Client') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Total') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($recentInvoices as $invoice)
                    <tr class="border-b border-slate-50 transition-colors last:border-0 hover:bg-slate-50">
                        <td class="px-6 py-3"><a href="{{ route('app.invoices.show', $invoice) }}" class="font-medium text-brand-700 hover:underline">{{ $invoice->invoice_number }}</a></td>
                        <td class="px-6 py-3">{{ $invoice->client->name }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ \App\Support\PlatformFormat::date($invoice->issue_date) }}</td>
                        <td class="px-6 py-3 font-medium">SAR {{ number_format($invoice->total, 2) }}</td>
                        <td class="px-6 py-3">@include('user.invoices.partials.status-badge', ['status' => $invoice->status])</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>
@endsection
