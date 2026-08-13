@extends('layouts.app')

@section('title', __('Dashboard'))

@section('content')
<div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
    @foreach ([
        [__('Total invoiced'), 'SAR '.number_format($stats['total_invoiced'], 2), '🧾'],
        [__('Outstanding'), 'SAR '.number_format($stats['total_outstanding'], 2), '⏳'],
        [__('Paid this month'), 'SAR '.number_format($stats['total_paid_this_month'], 2), '✅'],
        [__('Expenses this month'), 'SAR '.number_format($stats['total_expenses_this_month'], 2), '💳'],
    ] as [$label, $value, $icon])
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <div class="flex items-center justify-between">
                <span class="text-sm text-slate-500">{{ $label }}</span>
                <span>{{ $icon }}</span>
            </div>
            <div class="mt-2 text-2xl font-bold text-slate-900">{{ $value }}</div>
        </div>
    @endforeach
</div>

<div class="mt-8 bg-white rounded-xl border border-slate-100">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
        <h2 class="font-semibold text-slate-900">{{ __('Recent invoices') }}</h2>
        <a href="{{ route('app.invoices.create') }}" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('+ New invoice') }}</a>
    </div>
    @if ($recentInvoices->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No invoices yet. Create your first invoice to get started.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Invoice') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Client') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Total') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($recentInvoices as $invoice)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                        <td class="px-6 py-3"><a href="{{ route('app.invoices.show', $invoice) }}" class="font-medium text-brand-700 hover:underline">{{ $invoice->invoice_number }}</a></td>
                        <td class="px-6 py-3">{{ $invoice->client->name }}</td>
                        <td class="px-6 py-3">{{ $invoice->issue_date->format('Y-m-d') }}</td>
                        <td class="px-6 py-3">SAR {{ number_format($invoice->total, 2) }}</td>
                        <td class="px-6 py-3">@include('user.invoices.partials.status-badge', ['status' => $invoice->status])</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
