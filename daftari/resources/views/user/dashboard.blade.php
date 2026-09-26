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

<div class="grid grid-cols-2 gap-4 sm:gap-5 lg:grid-cols-4">
    @foreach ([
        ['label' => __('Total invoiced'), 'value' => \App\Support\Money::format($stats['total_invoiced']), 'icon' => 'sales', 'accent' => 'from-brand-500 to-emerald-500'],
        ['label' => __('Outstanding'), 'value' => \App\Support\Money::format($stats['total_outstanding']), 'icon' => 'clock', 'accent' => 'from-amber-500 to-orange-500'],
        ['label' => __('Paid this month'), 'value' => \App\Support\Money::format($stats['total_paid_this_month']), 'icon' => 'check-circle', 'accent' => 'from-teal-500 to-cyan-500'],
        ['label' => __('Expenses this month'), 'value' => \App\Support\Money::format($stats['total_expenses_this_month']), 'icon' => 'purchases', 'accent' => 'from-rose-500 to-red-500'],
        ['label' => __('Purchases this month'), 'value' => \App\Support\Money::format($stats['total_purchases_this_month']), 'icon' => 'bank', 'accent' => 'from-sky-500 to-blue-500'],
        ['label' => __('Open quotations'), 'value' => $stats['open_quotations'], 'icon' => 'clipboard', 'accent' => 'from-violet-500 to-purple-500'],
        ['label' => __('Overdue invoices'), 'value' => $stats['overdue_count'], 'icon' => 'alert', 'accent' => 'from-red-500 to-rose-600'],
        ['label' => __('Profit this month'), 'value' => \App\Support\Money::format($stats['profit_this_month']), 'icon' => 'trend-up', 'accent' => $stats['profit_this_month'] >= 0 ? 'from-brand-500 to-teal-500' : 'from-slate-500 to-slate-600'],
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

<div class="mt-6 grid gap-5 lg:grid-cols-3">
    <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-card lg:col-span-2">
        <h2 class="mb-1 font-semibold text-slate-900">{{ __('Sales & Purchases') }}</h2>
        <p class="mb-4 text-sm text-slate-500">{{ __('Last 7 days.') }}</p>
        <div class="h-72">
            <canvas id="chart-sales-purchases"></canvas>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-card">
        <h2 class="mb-1 font-semibold text-slate-900">{{ __('Top Selling Products') }}</h2>
        <p class="mb-4 text-sm text-slate-500">{{ __('Last 30 days.') }}</p>
        @if ($charts['topItems']->isEmpty())
            <p class="flex h-56 items-center justify-center text-sm text-slate-400">{{ __('No sales yet.') }}</p>
        @else
            <div class="h-56">
                <canvas id="chart-top-items"></canvas>
            </div>
        @endif
    </div>
</div>

<div class="mt-5 grid gap-5 lg:grid-cols-3">
    <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-card lg:col-span-2">
        <h2 class="mb-1 font-semibold text-slate-900">{{ __('Payment Sent & Received') }}</h2>
        <p class="mb-4 text-sm text-slate-500">{{ __('Last 7 days.') }}</p>
        @if (array_sum($charts['paymentFlow']['received']) + array_sum($charts['paymentFlow']['sent']) <= 0)
            <p class="flex h-72 items-center justify-center text-sm text-slate-400">{{ __('No payments recorded this week.') }}</p>
        @else
            <div class="h-72">
                <canvas id="chart-payment-flow"></canvas>
            </div>
        @endif
    </div>

    <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-card">
        <h2 class="mb-1 font-semibold text-slate-900">{{ __('Top Customers') }}</h2>
        <p class="mb-4 text-sm text-slate-500">{{ __('Last 90 days.') }}</p>
        @if ($charts['topCustomers']->isEmpty())
            <p class="flex h-56 items-center justify-center text-sm text-slate-400">{{ __('No sales yet.') }}</p>
        @else
            <div class="h-56">
                <canvas id="chart-top-customers"></canvas>
            </div>
        @endif
    </div>
</div>

<div class="mt-5 grid gap-5 lg:grid-cols-2">
    <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-card">
        <h2 class="mb-1 font-semibold text-slate-900">{{ __('Sales by Payment Method') }}</h2>
        <p class="mb-4 text-sm text-slate-500">{{ __('Last 90 days.') }}</p>
        @php($paymentLabels = ['cash' => __('Cash'), 'bank_transfer' => __('Bank transfer'), 'card' => __('Card'), 'other' => __('Other')])
        @php($paymentMax = $charts['paymentMethods']->max() ?: 1)
        @if ($charts['paymentMethods']->isEmpty())
            <p class="flex h-40 items-center justify-center text-sm text-slate-400">{{ __('No payments recorded yet.') }}</p>
        @else
            <div class="space-y-3">
                @foreach ($charts['paymentMethods'] as $method => $amount)
                    <div>
                        <div class="mb-1 flex items-center justify-between text-sm">
                            <span class="text-slate-600">{{ $paymentLabels[$method] ?? ucfirst(str_replace('_', ' ', $method)) }}</span>
                            <span class="font-semibold text-slate-900">{{ \App\Support\Money::format($amount) }}</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-gradient-to-r from-brand-500 to-emerald-400" style="width: {{ max(4, round($amount / $paymentMax * 100)) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-card">
        <h2 class="mb-1 font-semibold text-slate-900">{{ __('Receivables Aging') }}</h2>
        <p class="mb-4 text-sm text-slate-500">{{ __('Outstanding invoice balances by how overdue they are.') }}</p>
        @php($agingLabels = ['current' => __('Current'), '1_30' => __('1–30 days'), '31_60' => __('31–60 days'), '61_plus' => __('61+ days')])
        @php($agingMax = max(array_merge(array_values($aging), [1])))
        <div class="space-y-3">
            @foreach ($agingLabels as $key => $label)
                <div>
                    <div class="mb-1 flex items-center justify-between text-sm">
                        <span class="text-slate-600">{{ $label }}</span>
                        <span class="font-semibold {{ $key !== 'current' && $aging[$key] > 0 ? 'text-amber-700' : 'text-slate-900' }}">{{ \App\Support\Money::format($aging[$key]) }}</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full {{ $key === 'current' ? 'bg-gradient-to-r from-teal-500 to-cyan-400' : 'bg-gradient-to-r from-amber-500 to-orange-400' }}" style="width: {{ $aging[$key] > 0 ? max(4, round($aging[$key] / $agingMax * 100)) : 0 }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="mt-5 overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-card">
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
                        <td class="px-6 py-3">{{ $invoice->client->display_name }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ \App\Support\PlatformFormat::date($invoice->issue_date) }}</td>
                        <td class="px-6 py-3 font-medium">{{ \App\Support\Money::format($invoice->total) }}</td>
                        <td class="px-6 py-3">@include('user.invoices.partials.status-badge', ['status' => $invoice->status])</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const palette = ['#1ab27e', '#8b5cf6', '#f59e0b', '#f43f5e', '#06b6d4', '#94a3b8'];
    const gridColor = 'rgba(148, 163, 184, 0.15)';
    const commonScales = {
        x: { grid: { display: false }, ticks: { font: { size: 11 } } },
        y: { grid: { color: gridColor }, ticks: { font: { size: 11 } }, beginAtZero: true },
    };

    const salesPurchases = @json($charts['salesPurchases']);
    new Chart(document.getElementById('chart-sales-purchases'), {
        type: 'bar',
        data: {
            labels: salesPurchases.labels,
            datasets: [
                { label: @js(__('Sales')), data: salesPurchases.sales, backgroundColor: '#1ab27e', borderRadius: 6, maxBarThickness: 28 },
                { label: @js(__('Purchases')), data: salesPurchases.purchases, backgroundColor: '#8b5cf6', borderRadius: 6, maxBarThickness: 28 },
            ],
        },
        options: { responsive: true, maintainAspectRatio: false, scales: commonScales, plugins: { legend: { position: 'bottom' } } },
    });

    @if (array_sum($charts['paymentFlow']['received']) + array_sum($charts['paymentFlow']['sent']) > 0)
        const paymentFlow = @json($charts['paymentFlow']);
        new Chart(document.getElementById('chart-payment-flow'), {
            type: 'line',
            data: {
                labels: paymentFlow.labels,
                datasets: [
                    { label: @js(__('Received')), data: paymentFlow.received, borderColor: '#1ab27e', backgroundColor: 'rgba(26,178,126,0.12)', fill: true, tension: 0.35, pointRadius: 3 },
                    { label: @js(__('Sent')), data: paymentFlow.sent, borderColor: '#f43f5e', backgroundColor: 'rgba(244,63,94,0.10)', fill: true, tension: 0.35, pointRadius: 3 },
                ],
            },
            options: { responsive: true, maintainAspectRatio: false, scales: commonScales, plugins: { legend: { position: 'bottom' } } },
        });
    @endif

    @if ($charts['topItems']->isNotEmpty())
        const topItems = @json($charts['topItems']);
        new Chart(document.getElementById('chart-top-items'), {
            type: 'doughnut',
            data: {
                labels: topItems.map(i => i.label),
                datasets: [{ data: topItems.map(i => i.total), backgroundColor: palette, borderWidth: 0 }],
            },
            options: { responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 8, font: { size: 11 } } } } },
        });
    @endif

    @if ($charts['topCustomers']->isNotEmpty())
        const topCustomers = @json($charts['topCustomers']);
        new Chart(document.getElementById('chart-top-customers'), {
            type: 'pie',
            data: {
                labels: topCustomers.map(c => c.label),
                datasets: [{ data: topCustomers.map(c => c.total), backgroundColor: palette, borderWidth: 0 }],
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 8, font: { size: 11 } } } } },
        });
    @endif
});
</script>
@endsection
